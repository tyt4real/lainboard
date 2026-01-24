<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$boardUri = $_GET['board'] ?? '';
$threadId = intval($_POST['thread_id'] ?? 0);

$board = getBoard($boardUri);
if (!$board) {
    http_response_code(404);
    echo 'Board not found';
    exit;
}

if ($board['is_closed']) {
    $_SESSION['post_error'] = 'This board is currently closed and not accepting new posts.';
    header('Location: /' . $boardUri . '/thread/' . $threadId);
    exit;
}

$thread = getThread($threadId);
if (!$thread || $thread['board_id'] !== $board['id']) {
    http_response_code(404);
    echo 'Thread not found';
    exit;
}

if ($thread['is_locked']) {
    $_SESSION['post_error'] = 'This thread is locked.';
    header('Location: /' . $boardUri . '/thread/' . $threadId);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['post_error'] = 'Invalid form submission. Please try again.';
    header('Location: /' . $boardUri . '/thread/' . $threadId);
    exit;
}

$ip = getClientIP();

$ban = checkBan($ip, $board['id']);
if ($ban) {
    $_SESSION['post_error'] = 'You are banned. Reason: ' . ($ban['public_reason'] ?: 'Violation of rules');
    header('Location: /' . $boardUri . '/thread/' . $threadId);
    exit;
}

if (!verifyCaptcha($_POST['captcha'] ?? '')) {
    $_SESSION['post_error'] = 'Invalid CAPTCHA. Please try again.';
    header('Location: /' . $boardUri . '/thread/' . $threadId);
    exit;
}

$flood = checkFlood($ip, 'reply');
if ($flood) {
    $_SESSION['post_error'] = "Please wait {$flood} seconds before posting again.";
    header('Location: /' . $boardUri . '/thread/' . $threadId);
    exit;
}

$name = trim($_POST['name'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if (empty($comment)) {
    $_SESSION['post_error'] = 'Comment is required.';
    header('Location: /' . $boardUri . '/thread/' . $threadId);
    exit;
}

if (strlen($comment) > getMaxCommentLength()) {
    $_SESSION['post_error'] = 'Comment is too long.';
    header('Location: /' . $boardUri . '/thread/' . $threadId);
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
            header('Location: /' . $boardUri . '/thread/' . $threadId);
            exit;
        }
    } else {
        // Separate format - validate required fields
        if (empty($gpgPublicKey) || empty($gpgSignature)) {
            $_SESSION['post_error'] = 'GPG public key and signature are required for GPG signed posts.';
            header('Location: /' . $boardUri . '/thread/' . $threadId);
            exit;
        }
        $actualMessage = $comment;
        $actualSignature = $gpgSignature;
    }

    // Store or update GPG key
    $storedKeyId = storeGpgKey($gpgKeyId, $gpgPublicKey, $gpgEmail, $gpgJid);
    if (!$storedKeyId) {
        $_SESSION['post_error'] = 'Failed to store GPG key.';
        header('Location: /' . $boardUri . '/thread/' . $threadId);
        exit;
    }
    // Use the key ID that was actually stored (might be extracted)
    $gpgKeyId = $storedKeyId;

    // Verify signature
    $verificationResult = verifyGpgSignature($comment, $actualSignature, $gpgPublicKey);
    if ($verificationResult !== true) {
        $_SESSION['post_error'] = 'GPG signature verification failed.';
        header('Location: /' . $boardUri . '/thread/' . $threadId);
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
        header('Location: /' . $boardUri . '/thread/' . $threadId);
        exit;
    }

    try {
        $fileData = handleUpload($_FILES['file'], $boardUri);
    } catch (Exception $e) {
        $_SESSION['post_error'] = $e->getMessage();
        header('Location: /' . $boardUri . '/thread/' . $threadId);
        exit;
    }
}

try {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        INSERT INTO posts (board_id, thread_id, name, tripcode, comment, ip_address, user_agent,
                          file_path, file_name, file_size, file_hash, thumb_path,
                          image_width, image_height, capcode, gpg_key_id, gpg_signature, gpg_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        RETURNING id
    ");

    $gpgKeyId = $gpgData['key_id'] ?? null;
    $gpgSignature = $gpgData['signature'] ?? null;
    $gpgVerified = isset($gpgData['verified']) ? (bool)$gpgData['verified'] : false;

    $stmt->execute([
        $board['id'],
        $threadId,
        $displayName,
        $tripcode,
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
    
    $pdo->prepare("UPDATE posts SET reply_count = reply_count + 1 WHERE id = ?")->execute([$threadId]);
    
    if ($thread['reply_count'] < getBumpLimit()) {
        $pdo->prepare("UPDATE posts SET bumped_at = NOW() WHERE id = ?")->execute([$threadId]);
    }
    
    $pdo->prepare("UPDATE boards SET post_count = post_count + 1 WHERE id = ?")->execute([$board['id']]);
    
    recordFlood($ip, 'reply');
    
    header('Location: /' . $boardUri . '/thread/' . $threadId . '#p' . $postId);
    exit;
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['post_error'] = 'Failed to post reply. Please try again.';
    header('Location: /' . $boardUri . '/thread/' . $threadId);
    exit;
}
