<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$boardUri = $_GET['board'] ?? '';
$board = getBoard($boardUri);

if (!$board) {
    http_response_code(404);
    echo 'Board not found';
    exit;
}

if ($board['is_closed']) {
    $_SESSION['post_error'] = 'This board is currently closed and not accepting new posts.';
    header('Location: /' . $boardUri);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['post_error'] = 'Invalid form submission. Please try again.';
    header('Location: /' . $boardUri);
    exit;
}

$ip = getClientIP();

$ban = checkBan($ip, $board['id']);
if ($ban) {
    $_SESSION['post_error'] = 'You are banned. Reason: ' . ($ban['public_reason'] ?: 'Violation of rules');
    header('Location: /' . $boardUri);
    exit;
}

if (!verifyCaptcha($_POST['captcha'] ?? '')) {
    $_SESSION['post_error'] = 'Invalid CAPTCHA. Please try again.';
    header('Location: /' . $boardUri);
    exit;
}

$flood = checkFlood($ip, 'thread');
if ($flood) {
    $_SESSION['post_error'] = "Please wait {$flood} seconds before creating another thread.";
    header('Location: /' . $boardUri);
    exit;
}

$name = trim($_POST['name'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if (empty($comment)) {
    $_SESSION['post_error'] = 'Comment is required.';
    header('Location: /' . $boardUri);
    exit;
}

if (strlen($comment) > getMaxCommentLength()) {
    $_SESSION['post_error'] = 'Comment is too long.';
    header('Location: /' . $boardUri);
    exit;
}

// Handle GPG signed posts
$gpgData = [];
if (isset($_POST['use_gpg']) && $_POST['use_gpg'] === '1' && getSetting('gpg_enabled', '1') === '1') {
    $gpgKeyId = trim($_POST['gpg_key_id'] ?? '');
    $gpgPublicKey = trim($_POST['gpg_public_key'] ?? '');
    $gpgSignature = trim($_POST['gpg_signature'] ?? '');
    $gpgEmail = trim($_POST['gpg_email'] ?? '');
    $gpgJid = trim($_POST['gpg_jid'] ?? '');

    // Check if using combined PGP signed message format
    $parsedMessage = parsePgpSignedMessage($comment);
    $isCombinedFormat = ($parsedMessage !== null);

    if ($isCombinedFormat) {
        // Combined format - extract message and signature from comment
        $actualMessage = $parsedMessage['message'];
        $actualSignature = $parsedMessage['signature'];

        if (empty($gpgPublicKey)) {
            $_SESSION['post_error'] = 'GPG public key is required for GPG signed posts.';
            header('Location: /' . $boardUri);
            exit;
        }
    } else {
        // Separate format - validate required fields
        if (empty($gpgPublicKey) || empty($gpgSignature)) {
            $_SESSION['post_error'] = 'GPG public key and signature are required for GPG signed posts.';
            header('Location: /' . $boardUri);
            exit;
        }
        $actualMessage = $comment;
        $actualSignature = $gpgSignature;
    }

    // Store or update GPG key
    $storedKeyId = storeGpgKey($gpgKeyId, $gpgPublicKey, $gpgEmail, $gpgJid);
    if (!$storedKeyId) {
        $_SESSION['post_error'] = 'Failed to store GPG key.';
        header('Location: /' . $boardUri);
        exit;
    }
    // Use the key ID that was actually stored (might be extracted)
    $gpgKeyId = $storedKeyId;

    // Verify signature
    $verificationResult = verifyGpgSignature($comment, $actualSignature, $gpgPublicKey);
    if ($verificationResult !== true) {
        $_SESSION['post_error'] = 'GPG signature verification failed.';
        header('Location: /' . $boardUri);
        exit;
    }

    // Use the actual message content for the post (without PGP headers if combined format)
    if ($isCombinedFormat) {
        $comment = $actualMessage;
    }

    $gpgData = [
        'key_id' => $gpgKeyId,
        'signature' => $actualSignature,
        'verified' => true
    ];

    // Update last used timestamp
    updateGpgKeyLastUsed($gpgKeyId);
}

list($displayName, $tripcode) = generateTripcode($name ?: DEFAULT_ANONYMOUS_NAME);

$capcode = null;
$staff = getCurrentStaff();
if ($staff && isset($_POST['use_capcode'])) {
    $capcode = $staff['role'];
}

$fileData = null;
if (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Pre-check file size against PHP limits
    $phpMaxSize = getPhpUploadMaxSize();
    if ($_FILES['file']['size'] > $phpMaxSize) {
        $_SESSION['post_error'] = 'File too large for server configuration (max ' . formatBytes($phpMaxSize) . '). Contact administrator to increase PHP limits.';
        header('Location: /' . $boardUri);
        exit;
    }

    try {
        $fileData = handleUpload($_FILES['file'], $boardUri);
    } catch (Exception $e) {
        $_SESSION['post_error'] = $e->getMessage();
        header('Location: /' . $boardUri);
        exit;
    }
}

try {
    $pdo = getDB();
    
    $gpgKeyId = $gpgData['key_id'] ?? null;
    $gpgSignature = $gpgData['signature'] ?? null;
    $gpgVerified = isset($gpgData['verified']) ? (bool)$gpgData['verified'] : false;

    $stmt = $pdo->prepare("
        INSERT INTO posts (board_id, name, tripcode, subject, comment, ip_address, user_agent,
                          file_path, file_name, file_size, file_hash, thumb_path,
                          image_width, image_height, capcode, gpg_key_id, gpg_signature, gpg_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        RETURNING id
    ");

    $stmt->execute([
        $board['id'],
        $displayName,
        $tripcode,
        $subject,
        $comment,
        $ip,
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $fileData['file_path'] ?? null,
        $fileData['file_name'] ?? null,
        $fileData['file_size'] ?? null,
        $fileData['file_hash'] ?? null,
        $fileData['thumb_path'] ?? null,
        $fileData['image_width'] ?? null,
        $fileData['image_height'] ?? null,
        $capcode,
        $gpgKeyId,
        $gpgSignature,
        $gpgVerified ? 't' : 'f'
    ]);
    
    $postId = $stmt->fetchColumn();

    $pdo->prepare("UPDATE boards SET post_count = post_count + 1 WHERE id = ?")->execute([$board['id']]);
    
    recordFlood($ip, 'thread');

    $_SESSION['post_success'] = 'Thread created successfully!';
    header('Location: /' . $boardUri . '/thread/' . $postId);
    exit;
    
} catch (Exception $e) {
    error_log("Post creation failed: " . $e->getMessage());
    $_SESSION['post_error'] = 'Failed to create thread. Please try again.';
    header('Location: /' . $boardUri);
    exit;
}
