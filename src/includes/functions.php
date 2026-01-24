<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/database.php';

function generateTripcode($name) {
    if (strpos($name, '#') === false) {
        return [$name, null];
    }
    
    $parts = explode('#', $name, 2);
    $displayName = $parts[0] ?: DEFAULT_ANONYMOUS_NAME;
    $password = $parts[1];
    
    if (strpos($password, '#') === 0) {
        $tripcode = '!!' . substr(base64_encode(hash('sha256', SECURE_TRIPCODE_SALT . substr($password, 1), true)), 0, 10);
    } else {
        $salt = substr($password . 'H.', 1, 2);
        $salt = preg_replace('/[^\.-z]/', '.', $salt);
        $salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
        $tripcode = '!' . substr(crypt($password, $salt), -10);
    }
    
    return [$displayName, $tripcode];
}

function formatComment($comment, $boardUri, $currentThreadId = null) {
    // Apply word filters before HTML escaping
    $comment = applyWordFilters($comment);

    $comment = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');

    // Video embedding - process before other formatting
    $comment = preg_replace_callback('/https?:\/\/[^\s<>"\']+/i', function($matches) {
        $url = $matches[0];
        $embed = generateVideoEmbed($url);
        if ($embed) {
            return $embed;
        }
        // If not a video URL, return the original URL but make it clickable
        return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" class="external-link">' . htmlspecialchars($url) . '</a>';
    }, $comment);

    // Handle crossboard quotes first: >>/board/postid
    $comment = preg_replace_callback('/&gt;&gt;\/([a-zA-Z0-9]+)\/(\d+)/', function($matches) {
        $targetBoard = $matches[1];
        $postId = $matches[2];
        return '<a href="/' . htmlspecialchars($targetBoard) . '/thread/' . $postId . '#p' . $postId . '" class="quotelink">&gt;&gt;/' . htmlspecialchars($targetBoard) . '/' . $postId . '</a>';
    }, $comment);

    // Handle local quotes: >>postid
    $comment = preg_replace_callback('/&gt;&gt;(\d+)/', function($matches) use ($boardUri, $currentThreadId) {
        $postId = $matches[1];
        $threadId = $currentThreadId ?: $postId; // Fallback to postId if no thread ID provided
        return '<a href="/' . $boardUri . '/thread/' . $threadId . '#p' . $postId . '" class="quotelink">&gt;&gt;' . $postId . '</a>';
    }, $comment);

    $comment = preg_replace('/^&gt;(.*)$/m', '<span class="greentext">&gt;$1</span>', $comment);

    $comment = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $comment);
    $comment = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $comment);
    $comment = preg_replace('/\[spoiler\](.+?)\[\/spoiler\]/s', '<span class="spoiler">$1</span>', $comment);
    //$comment = nl2br($comment);

    return $comment;
}

function checkBan($ip, $boardId = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT * FROM bans 
        WHERE ip_address = ? 
        AND (expires_at IS NULL OR expires_at > NOW())
        AND (board_id = ? OR board_id IS NULL OR is_global = TRUE)
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$ip, $boardId]);
    return $stmt->fetch();
}

function checkFlood($ip, $actionType) {
    // Whitelisted IPs bypass flood control
    if (isWhitelisted($ip)) {
        return false;
    }

    $pdo = getDB();
    $cooldown = $actionType === 'thread' ? getThreadCooldown() : getFloodTime();
    
    $stmt = $pdo->prepare("
        SELECT created_at FROM flood_control 
        WHERE ip_address = ? AND action_type = ?
        AND created_at > NOW() - INTERVAL '{$cooldown} seconds'
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$ip, $actionType]);
    $last = $stmt->fetch();
    
    if ($last) {
        $wait = $cooldown - (time() - strtotime($last['created_at']));
        return $wait > 0 ? $wait : false;
    }
    return false;
}

function recordFlood($ip, $actionType) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO flood_control (ip_address, action_type) VALUES (?, ?)");
    $stmt->execute([$ip, $actionType]);
    
    $pdo->exec("DELETE FROM flood_control WHERE created_at < NOW() - INTERVAL '1 hour'");
}

function getClientIP() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '127.0.0.1';
}

function handleUpload($file, $boardUri) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        throw new Exception('File upload error');
    }
    
    $appMaxSize = getMaxFileSize();
    $phpMaxSize = getPhpUploadMaxSize();

    if ($file['size'] > $phpMaxSize) {
        throw new Exception('File too large for server configuration (max ' . formatBytes($phpMaxSize) . '). Contact administrator to increase PHP limits.');
    }

    if ($file['size'] > $appMaxSize) {
        throw new Exception('File too large (max ' . formatBytes($appMaxSize) . ')');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = getAllowedExtensions();
    if (!in_array($ext, $allowedExtensions)) {
        throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowedExtensions));
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if ($ext === 'pdf') {
        if ($mimeType !== 'application/pdf') {
            throw new Exception('Invalid PDF file');
        }
        if (!isImageMagickAvailable()) {
            throw new Exception('PDF thumbnail generation requires ImageMagick');
        }
    } elseif (!in_array($mimeType, $allowedMimes)) {
        throw new Exception('Invalid file type');
    }
    
    $hash = md5_file($file['tmp_name']);
    $timestamp = time();
    $filename = $timestamp . '_' . $hash . '.' . $ext;
    $thumbname = $timestamp . '_' . $hash . '_thumb.jpg';
    
    $boardDir = UPLOAD_DIR . $boardUri . '/';
    $thumbDir = THUMB_DIR . $boardUri . '/';
    
    if (!is_dir($boardDir)) mkdir($boardDir, 0755, true);
    if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
    
    $filepath = $boardDir . $filename;
    $thumbpath = $thumbDir . $thumbname;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file');
    }
    
    if (function_exists('exif_read_data') && in_array($ext, ['jpg', 'jpeg'])) {
        $img = imagecreatefromjpeg($filepath);
        if ($img) {
            imagejpeg($img, $filepath, 90);
            imagedestroy($img);
        }
    }
    
    if ($ext === 'pdf') {
        // Generate PDF thumbnail
        if (!generatePdfThumbnail($filepath, $thumbpath)) {
            throw new Exception('Failed to generate PDF thumbnail');
        }
        // For PDFs, we don't have image dimensions, so we'll use placeholder values
        $width = 0;
        $height = 0;
    } else {
        // Generate image thumbnail
        list($width, $height) = getimagesize($filepath);
        createThumbnail($filepath, $thumbpath, $ext);
    }

    return [
        'file_path' => '/uploads/' . $boardUri . '/' . $filename,
        'thumb_path' => '/uploads/thumbnails/' . $boardUri . '/' . $thumbname,
        'file_name' => $file['name'],
        'file_size' => $file['size'],
        'file_hash' => $hash,
        'image_width' => $width,
        'image_height' => $height
    ];
}

function createThumbnail($source, $dest, $ext) {
    list($width, $height) = getimagesize($source);
    
    $thumbDims = getThumbDimensions();
    $ratio = min($thumbDims['width'] / $width, $thumbDims['height'] / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);
    
    switch ($ext) {
        case 'gif':
            $srcImg = imagecreatefromgif($source);
            break;
        case 'png':
            $srcImg = imagecreatefrompng($source);
            break;
        case 'webp':
            $srcImg = imagecreatefromwebp($source);
            break;
        default:
            $srcImg = imagecreatefromjpeg($source);
    }
    
    $dstImg = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagejpeg($dstImg, $dest, 80);
    
    imagedestroy($srcImg);
    imagedestroy($dstImg);
}

function logModAction($staffId, $action, $targetType, $targetId, $details = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO mod_logs (staff_id, action, target_type, target_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$staffId, $action, $targetType, $targetId, $details, getClientIP()]);
}

function hasPermission($role, $permission) {
    global $permissions;
    return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getBoards() {
    $pdo = getDB();
    return $pdo->query("SELECT * FROM boards ORDER BY uri")->fetchAll();
}

function getBoardsByTag() {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT b.*, bt.tag_name
        FROM boards b
        LEFT JOIN board_tags bt ON b.id = bt.board_id
        ORDER BY bt.tag_name, b.uri
    ");
    $results = $stmt->fetchAll();

    $boardsByTag = [];
    foreach ($results as $row) {
        $tag = $row['tag_name'] ?: 'Untagged';
        if (!isset($boardsByTag[$tag])) {
            $boardsByTag[$tag] = [];
        }
        $boardsByTag[$tag][] = $row;
    }

    return $boardsByTag;
}

function getBoardTags($boardId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT tag_name FROM board_tags WHERE board_id = ? ORDER BY tag_name");
    $stmt->execute([$boardId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getAllTags() {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT DISTINCT tag_name FROM board_tags ORDER BY tag_name");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function addBoardTag($boardId, $tagName) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO board_tags (board_id, tag_name) VALUES (?, ?) ON CONFLICT DO NOTHING");
    return $stmt->execute([$boardId, $tagName]);
}

function removeBoardTag($boardId, $tagName) {
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM board_tags WHERE board_id = ? AND tag_name = ?");
    return $stmt->execute([$boardId, $tagName]);
}

// GPG Functions
function storeGpgKey($keyId, $publicKey, $email = null, $jid = null) {
    $pdo = getDB();

    // If key ID is empty, extract it from the public key
    $originalKeyId = $keyId;
    if (empty($keyId)) {
        $keyId = extractGpgKeyId($publicKey);
        error_log("Extracted key ID from public key: $keyId (was empty)");
    } else {
        error_log("Using provided key ID: $keyId");
    }

    // Extract fingerprint from public key
    $fingerprint = extractGpgFingerprint($publicKey);

    $stmt = $pdo->prepare("
        INSERT INTO gpg_keys (key_id, public_key, email, jid, fingerprint)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT (key_id) DO UPDATE SET
            public_key = EXCLUDED.public_key,
            email = EXCLUDED.email,
            jid = EXCLUDED.jid,
            fingerprint = EXCLUDED.fingerprint,
            last_used = CURRENT_TIMESTAMP
    ");
    $result = $stmt->execute([$keyId, $publicKey, $email, $jid, $fingerprint]);

    // Return the key ID that was actually used
    return $result ? $keyId : false;
}

function getGpgKey($keyId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM gpg_keys WHERE key_id = ?");
    $stmt->execute([$keyId]);
    return $stmt->fetch();
}

function getSetting($key, $default = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['value'] : $default;
}

function setSetting($key, $value) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO settings (key, value, updated_at)
        VALUES (?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (key) DO UPDATE SET
            value = EXCLUDED.value,
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$key, $value]);
}

// Helper functions to get settings with defaults
function getMaxFileSize() {
    return (int)getSetting('max_file_size', 4) * 1024 * 1024;
}

function getAllowedExtensions() {
    $exts = getSetting('allowed_extensions', 'jpg,jpeg,png,gif,webp');

    // Add PDF if enabled
    if (isPdfUploadEnabled()) {
        $exts .= ',pdf';
    }

    return array_map('trim', explode(',', $exts));
}

function getThumbDimensions() {
    return [
        'width' => (int)getSetting('thumb_width', 200),
        'height' => (int)getSetting('thumb_height', 200)
    ];
}

function getFloodTime() {
    return (int)getSetting('flood_time', 30);
}

function getThreadCooldown() {
    return (int)getSetting('thread_cooldown', 120);
}

function getMaxCommentLength() {
    return (int)getSetting('max_comment_length', 8000);
}

function getMaxNameLength() {
    return (int)getSetting('max_name_length', 75);
}

function getMaxSubjectLength() {
    return (int)getSetting('max_subject_length', 100);
}

function getMaxFilenameLength() {
    return (int)getSetting('max_filename_length', 25);
}

function getThreadsPerPage() {
    return (int)getSetting('threads_per_page', 10);
}

function getRepliesShown() {
    return (int)getSetting('replies_shown', 5);
}

function getBumpLimit() {
    return (int)getSetting('bump_limit', 300);
}

function getPopularThreadsCount() {
    return (int)getSetting('popular_threads_count', 10);
}

function getPostCountDigits() {
    return (int)getSetting('post_count_digits', 6);
}

function isThemeSelectorEnabled() {
    return getSetting('theme_selector_enabled', '1') === '1';
}

function isCatalogEnabled() {
    return getSetting('catalog_enabled', '1') === '1';
}

function isOverboardEnabled() {
    return getSetting('overboard_enabled', '0') === '1';
}

function isPdfUploadEnabled() {
    return getSetting('pdf_upload_enabled', '0') === '1';
}

// PHP upload limit functions
function getPhpUploadMaxSize() {
    $uploadMax = ini_get('upload_max_filesize');
    $postMax = ini_get('post_max_size');

    // Convert to bytes
    $uploadBytes = parseSize($uploadMax);
    $postBytes = parseSize($postMax);

    // Return the smaller of the two limits
    return min($uploadBytes, $postBytes);
}

function parseSize($size) {
    $unit = strtolower(substr($size, -1));
    $size = (int)$size;

    switch($unit) {
        case 'g': $size *= 1024 * 1024 * 1024; break;
        case 'm': $size *= 1024 * 1024; break;
        case 'k': $size *= 1024; break;
    }

    return $size;
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// IP Whitelist functions
function addToWhitelist($ip, $reason = '', $staffId = null) {
    $pdo = getDB();
    try {
        $stmt = $pdo->prepare("INSERT INTO ip_whitelist (ip_address, reason, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$ip, $reason, $staffId]);
        return true;
    } catch (Exception $e) {
        return false; // IP might already be whitelisted
    }
}

function removeFromWhitelist($ip) {
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM ip_whitelist WHERE ip_address = ?");
    $stmt->execute([$ip]);
    return $stmt->rowCount() > 0;
}

function isWhitelisted($ip) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ip_whitelist WHERE ip_address = ?");
    $stmt->execute([$ip]);
    return $stmt->fetchColumn() > 0;
}

function getWhitelist() {
    $pdo = getDB();
    return $pdo->query("
        SELECT w.*, s.username as created_by_username
        FROM ip_whitelist w
        LEFT JOIN staff s ON w.created_by = s.id
        ORDER BY w.created_at DESC
    ")->fetchAll();
}

// PDF thumbnail generation
function generatePdfThumbnail($pdfPath, $thumbPath) {
    $thumbDir = dirname($thumbPath);
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }

    // Use ImageMagick to convert first page of PDF to image
    $command = sprintf(
        'convert -density 150 "%s[0]" -quality 85 -resize "%dx%d>" "%s" 2>&1',
        escapeshellarg($pdfPath),
        getThumbDimensions()['width'],
        getThumbDimensions()['height'],
        escapeshellarg($thumbPath)
    );

    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        error_log("PDF thumbnail generation failed: " . implode("\n", $output));
        return false;
    }

    return file_exists($thumbPath) && filesize($thumbPath) > 0;
}

// Check if ImageMagick is available
function isImageMagickAvailable() {
    $command = 'convert -version 2>&1';
    exec($command, $output, $returnCode);
    return $returnCode === 0;
}

function extractGpgFingerprint($publicKey) {
    // This is a simplified extraction - in a real implementation you'd use gnupg
    // For now, we'll create a simple hash
    return substr(hash('sha1', $publicKey), 0, 40);
}

function extractGpgKeyId($publicKey) {
    // Try to extract key ID from the public key armor
    // Look for key ID patterns in the armored key
    if (preg_match('/-----BEGIN PGP PUBLIC KEY BLOCK-----(.*?)-----END PGP PUBLIC KEY BLOCK-----/s', $publicKey, $matches)) {
        $keyBlock = $matches[1];

        // Look for key ID in the armor header (FiEE, FiEF, etc.)
        // Extract the longest hex string we can find (could be full fingerprint)
        if (preg_match('/([A-F0-9]{8,})/', $keyBlock, $keyMatches)) {
            $extractedId = strtoupper($keyMatches[1]);
            // If it's longer than 16 characters, take the last 16 (should be the key fingerprint end)
            if (strlen($extractedId) > 16) {
                $extractedId = substr($extractedId, -16);
            }
            error_log("Extracted key ID from armor: $extractedId (length: " . strlen($extractedId) . ")");
            return $extractedId;
        }
    }

    // Fallback: use last 16 characters of fingerprint as key ID (but ensure it's exactly 16)
    $fingerprint = extractGpgFingerprint($publicKey);
    $fallbackId = substr($fingerprint, -16);
    error_log("Using fallback key ID from fingerprint: $fallbackId");
    return $fallbackId;
}

function debugPgpMessage($signedMessage) {
    echo "=== PGP MESSAGE DEBUG ===\n";
    echo "Length: " . strlen($signedMessage) . " bytes\n";
    echo "Raw content:\n'" . $signedMessage . "'\n\n";

    $parsed = parsePgpSignedMessage($signedMessage);
    if ($parsed) {
        echo "PARSED SUCCESSFULLY:\n";
        echo "Message: '" . $parsed['message'] . "'\n";
        echo "Canonical: '" . str_replace("\n", "\\n", $parsed['canonical_message']) . "'\n";
        echo "Hash line: '" . str_replace("\n", "\\n", $parsed['hash_line']) . "'\n";
    } else {
        echo "PARSING FAILED\n";
    }
    echo "=== END DEBUG ===\n";
    return $parsed;
}

function parsePgpSignedMessage($signedMessage) {
    // Debug logging
    error_log("Parsing PGP message, length: " . strlen($signedMessage));
    error_log("First 300 chars: " . substr($signedMessage, 0, 300));

    // Normalize line endings (handle Windows CRLF from Cleopatra)
    $signedMessage = str_replace("\r\n", "\n", $signedMessage);
    $signedMessage = str_replace("\r", "\n", $signedMessage);

    // Check if it's a PGP signed message format
    if (!preg_match('/-----BEGIN PGP SIGNED MESSAGE-----/', $signedMessage)) {
        error_log("No PGP signed message header found");
        return null; // Not a PGP signed message
    }

    // Extract the message content (between signed message header and signature)
    $pattern = '/-----BEGIN PGP SIGNED MESSAGE-----(.*?)-----BEGIN PGP SIGNATURE-----/s';
    if (!preg_match($pattern, $signedMessage, $matches)) {
        error_log("Could not extract message content between headers");
        return null;
    }

    $messagePart = $matches[1];
    error_log("Raw message part: '" . $messagePart . "'");

    // Extract hash algorithm if present (should be the first line)
    $hashLine = '';
    $cleanMessagePart = $messagePart;
    $lines = explode("\n", trim($messagePart));
    if (count($lines) > 0 && preg_match('/^Hash:\s*(\w+)$/', $lines[0], $hashMatches)) {
        $hashLine = "Hash: " . $hashMatches[1] . "\n";
        // Remove the hash line from the message
        array_shift($lines);
        $cleanMessagePart = implode("\n", $lines);
        if (!empty($cleanMessagePart)) {
            $cleanMessagePart .= "\n"; // Add back the newline
        }
        error_log("Found hash algorithm: '" . $hashMatches[1] . "'");
    }

    // The message content should be everything after the hash line (if present)
    $messageContent = trim($cleanMessagePart);

    // Extract signature
    $signaturePattern = '/-----BEGIN PGP SIGNATURE-----(.*?)-----END PGP SIGNATURE-----/s';
    if (!preg_match($signaturePattern, $signedMessage, $sigMatches)) {
        error_log("Could not extract signature");
        return null;
    }

    $signatureContent = "-----BEGIN PGP SIGNATURE-----" . $sigMatches[1] . "-----END PGP SIGNATURE-----";
    error_log("Extracted signature: " . substr($signatureContent, 0, 150));

    // Create the canonical message for verification
    // For GPG verification, the message should be exactly as it appears in the signed message
    // This means hash line (if present) + message content + proper line endings
    $canonicalMessage = '';
    if (!empty($hashLine)) {
        $canonicalMessage .= $hashLine;
    }
    $canonicalMessage .= $messageContent;

    // Ensure it ends with a newline for GPG
    if (!empty($canonicalMessage) && substr($canonicalMessage, -1) !== "\n") {
        $canonicalMessage .= "\n";
    }

    error_log("Final message content: '" . $messageContent . "'");
    error_log("Canonical message for verification: '" . str_replace("\n", "\\n", $canonicalMessage) . "'");

    return [
        'message' => $messageContent,
        'canonical_message' => $canonicalMessage,
        'signature' => $signatureContent,
        'hash_line' => $hashLine
    ];
}

function verifyGpgSignature($message, $signature, $publicKey) {
    // Try PHP gnupg extension first, fall back to shell commands
    if (extension_loaded('gnupg')) {
        return verifyGpgSignatureExtension($message, $signature, $publicKey);
    } else {
        error_log("GPG extension not available, using shell commands");
        return verifyGpgSignatureShell($message, $signature, $publicKey);
    }
}

function verifyGpgSignatureExtension($message, $signature, $publicKey) {
    if (empty($publicKey)) {
        return "Public key is required for GPG verification.";
    }

    try {
        // Initialize GPG
        $gpg = new gnupg();
        $gpg->seterrormode(GNUPG_ERROR_EXCEPTION);

        // Check if message is in PGP signed format
        $parsed = parsePgpSignedMessage($message);
        if ($parsed) {
            // Use the parsed message and signature
            $verifyMessage = $parsed['canonical_message'];
            $verifySignature = $parsed['signature'];
        } else {
            // Use separate message and signature (legacy format)
            if (empty($signature) || empty($message)) {
                return "Both message and signature are required for GPG verification.";
            }
            $verifyMessage = $message;
            $verifySignature = $signature;
        }

        // Import the public key
        $keyInfo = $gpg->import($publicKey);
        if (!$keyInfo || !isset($keyInfo['fingerprint'])) {
            return "Failed to import GPG public key. Please check the key format.";
        }

        // Verify the signature
        $verified = $gpg->verify($verifyMessage, $verifySignature);

        if ($verified === false) {
            return "GPG signature verification failed. The signature may be invalid or the key may not match.";
        }

        // Check if verification was successful
        if (is_array($verified) && count($verified) > 0) {
            $firstVerification = $verified[0];
            if (isset($firstVerification['fingerprint'])) {
                // For our purposes, we accept any cryptographically valid signature
                // We don't require web-of-trust validation since we're verifying identity through GPG signatures
                return true; // Success - signature is cryptographically valid
            }
        }

        return "GPG signature verification failed: No valid signature found.";

    } catch (Exception $e) {
        error_log("GPG extension verification exception: " . $e->getMessage());
        return "GPG verification error: " . $e->getMessage();
    }
}

function verifyGpgSignatureShell($message, $signature, $publicKey) {
    if (empty($publicKey)) {
        return "Public key is required for GPG verification.";
    }

    // Log the original input for debugging
    error_log("Original message input: '" . str_replace(array("\r", "\n"), array("\\r", "\\n"), $message) . "'");
    error_log("Original signature input: '" . substr($signature, 0, 100) . "...'");

    // Clean up the public key - remove any extra whitespace or comments that might cause issues
    $publicKey = trim($publicKey);
    // Ensure proper line endings
    $publicKey = str_replace("\r\n", "\n", $publicKey);
    $publicKey = str_replace("\r", "\n", $publicKey);

    error_log("Cleaned public key (first 200 chars): " . substr($publicKey, 0, 200));

    // Check if message is in PGP signed format
    $parsed = parsePgpSignedMessage($message);
    if ($parsed) {
        // Use the parsed message and signature
        $verifyMessage = $parsed['canonical_message'];
        $verifySignature = $parsed['signature'];
        error_log("Using parsed PGP clearsigned message for verification");
        error_log("Parsed message content: '" . $parsed['message'] . "'");
        error_log("Parsed canonical message: '" . str_replace("\n", "\\n", $verifyMessage) . "'");
    } elseif (!empty($signature)) {
        // Use separate message and signature (legacy format or detached signature)
        if (empty($message)) {
            return "Message content is required for GPG verification.";
        }
        $verifyMessage = $message;
        $verifySignature = $signature;
        error_log("Using separate message and signature for verification (possible detached signature)");
    } else {
        return "Either a PGP signed message or separate signature is required for GPG verification.";
    }

    // Create temporary files for verification
    $tempDir = sys_get_temp_dir() . '/lainboard_gpg_' . uniqid();
    if (!mkdir($tempDir, 0700)) {
        return "Failed to create temporary directory for GPG verification.";
    }

    try {
        $keyFile = $tempDir . '/key.asc';
        $messageFile = $tempDir . '/message.txt';
        $signatureFile = $tempDir . '/signature.asc';

        // Write files
        $keyWritten = file_put_contents($keyFile, $publicKey);
        $messageWritten = file_put_contents($messageFile, $verifyMessage);
        $signatureWritten = file_put_contents($signatureFile, $verifySignature);

        if ($keyWritten === false || $messageWritten === false || $signatureWritten === false) {
            return "Failed to write temporary files for GPG verification.";
        }

        error_log("Wrote key ($keyWritten bytes), message ($messageWritten bytes), signature ($signatureWritten bytes)");
        error_log("Message content for verification: " . bin2hex($verifyMessage));
        error_log("Message as string: '" . str_replace(array("\r", "\n"), array("\\r", "\\n"), $verifyMessage) . "'");

        // Import the public key
        $importCmd = "gpg --homedir $tempDir --batch --yes --import $keyFile 2>&1";
        exec($importCmd, $importOutput, $importReturn);

        error_log("GPG import return code: $importReturn");
        error_log("GPG import output: " . implode("\n", $importOutput));

        if ($importReturn !== 0) {
            $errorMsg = "Failed to import GPG public key. This might be because:\n";
            $errorMsg .= "- The public key format is invalid or corrupted\n";
            $errorMsg .= "- The key contains unsupported features\n";
            $errorMsg .= "- Copy/paste errors from Cleopatra\n\n";
            $errorMsg .= "GPG error details: " . implode("\n", $importOutput);
            error_log("GPG import failed: " . $errorMsg);
            // Clean up
            array_map('unlink', glob($tempDir . '/*'));
            rmdir($tempDir);
            return $errorMsg;
        }

        error_log("Successfully imported GPG key");

        // First, let's see what the signature expects
        $sigDetailsCmd = "gpg --homedir $tempDir --batch --list-packets $signatureFile 2>&1";
        exec($sigDetailsCmd, $sigDetailsOutput, $sigDetailsReturn);
        error_log("Signature packet details: " . implode("\n", $sigDetailsOutput));

        // Verify the signature
        $verifyCmd = "gpg --homedir $tempDir --batch --verify $signatureFile $messageFile 2>&1";
        exec($verifyCmd, $verifyOutput, $verifyReturn);

        error_log("GPG verify return code: $verifyReturn");
        error_log("GPG verify output: " . implode("\n", $verifyOutput));

        // If verification failed, show detailed GPG information
        if ($verifyReturn !== 0) {
            // Get signature information
            $sigInfoCmd = "gpg --homedir $tempDir --batch --list-packets $signatureFile 2>/dev/null | head -20";
            exec($sigInfoCmd, $sigInfoOutput, $sigInfoReturn);
            error_log("Signature packet info: " . implode("\n", $sigInfoOutput));

            // Try to see what the signature was created for
            $verifyVerboseCmd = "gpg --homedir $tempDir --batch --verify --verbose $signatureFile $messageFile 2>&1";
            exec($verifyVerboseCmd, $verifyVerboseOutput, $verifyVerboseReturn);
            error_log("Verbose verification output: " . implode("\n", $verifyVerboseOutput));

            // Show the actual message content being verified
            $actualMessage = file_get_contents($messageFile);
            error_log("Actual message content being verified: '" . bin2hex($actualMessage) . "'");
            error_log("Actual message as text: '" . str_replace(array("\r", "\n", "\t"), array("\\r", "\\n", "\\t"), $actualMessage) . "'");
        }

        // If verification failed, try alternative message formats
        if ($verifyReturn !== 0) {
            $alternativeMessages = [];

            if ($parsed) {
                // Try message without hash line
                $messageWithoutHash = $parsed['message'];
                if (substr($messageWithoutHash, -1) !== "\n") {
                    $messageWithoutHash .= "\n";
                }
                $alternativeMessages[] = $messageWithoutHash;

                // Try message with different line ending combinations
                $alternativeMessages[] = trim($parsed['message']) . "\n";
                $alternativeMessages[] = trim($parsed['message']) . "\r\n";
                $alternativeMessages[] = $parsed['message']; // As-is

                // Try just the plain message without any PGP formatting (for detached signatures)
                $plainMessage = trim($parsed['message']);
                if (!empty($plainMessage)) {
                    $alternativeMessages[] = $plainMessage . "\n";
                }
            } else {
                // For non-parsed messages, try basic variations
                $alt1 = trim($verifyMessage);
                if (substr($alt1, -1) !== "\n") {
                    $alt1 .= "\n";
                }
                $alternativeMessages[] = $alt1;

                // Also try without trailing newline
                $alternativeMessages[] = trim($verifyMessage);
            }

            foreach ($alternativeMessages as $index => $altMessage) {
                if ($altMessage === $verifyMessage) continue; // Skip if same as original

                error_log("Trying alternative message format " . ($index + 1) . ": '" . str_replace(array("\r", "\n"), array("\\r", "\\n"), $altMessage) . "'");
                file_put_contents($messageFile, $altMessage);

                exec($verifyCmd, $verifyOutputAlt, $verifyReturnAlt);
                error_log("Alternative verification " . ($index + 1) . " return code: $verifyReturnAlt");

                if ($verifyReturnAlt === 0) {
                    error_log("GPG verification successful with alternative format " . ($index + 1));
                    // Clean up and return success
                    $files = glob($tempDir . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        } elseif (is_dir($file)) {
                            $subfiles = glob($file . '/*');
                            foreach ($subfiles as $subfile) {
                                if (is_file($subfile)) {
                                    unlink($subfile);
                                }
                            }
                            rmdir($file);
                        }
                    }
                    rmdir($tempDir);
                    return true;
                }
            }
        }

        // Clean up
        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $subfiles = glob($file . '/*');
                foreach ($subfiles as $subfile) {
                    if (is_file($subfile)) {
                        unlink($subfile);
                    }
                }
                rmdir($file);
            }
        }
        rmdir($tempDir);

        if ($verifyReturn === 0) {
            error_log("GPG verification successful");
            return true; // Success
        } else {
            $errorMsg = "GPG signature verification failed: " . implode("\n", $verifyOutput);
            error_log($errorMsg);
            return $errorMsg;
        }

    } catch (Exception $e) {
        // Clean up on error
        if (is_dir($tempDir)) {
            array_map('unlink', glob($tempDir . '/*'));
            rmdir($tempDir);
        }
        error_log("GPG shell verification exception: " . $e->getMessage());
        return "GPG verification error: " . $e->getMessage();
    }
}

function updateGpgKeyLastUsed($keyId) {
    if (empty($keyId)) {
        return false; // Can't update without key ID
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE gpg_keys SET last_used = CURRENT_TIMESTAMP WHERE key_id = ?");
    return $stmt->execute([$keyId]);
}

function getBoard($uri) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM boards WHERE uri = ?");
    $stmt->execute([$uri]);
    return $stmt->fetch();
}

function getThreads($boardId, $page = 1) {
    $pdo = getDB();
    $threadsPerPage = getThreadsPerPage();
    $offset = ($page - 1) * $threadsPerPage;
    $stmt = $pdo->prepare("
        SELECT p.*, g.email as gpg_email, g.jid as gpg_jid, g.username as gpg_username
        FROM posts p
        LEFT JOIN gpg_keys g ON p.gpg_key_id = g.key_id
        WHERE p.board_id = ? AND p.thread_id IS NULL AND p.is_deleted = FALSE
        ORDER BY p.is_sticky DESC, p.bumped_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$boardId, $threadsPerPage, $offset]);
    return $stmt->fetchAll();
}

function getThread($threadId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT p.*, g.email as gpg_email, g.jid as gpg_jid, g.username as gpg_username
        FROM posts p
        LEFT JOIN gpg_keys g ON p.gpg_key_id = g.key_id
        WHERE p.id = ? AND p.thread_id IS NULL AND p.is_deleted = FALSE
    ");
    $stmt->execute([$threadId]);
    return $stmt->fetch();
}

function getReplies($threadId, $limit = null) {
    $pdo = getDB();
    $sql = "SELECT p.*, g.email as gpg_email, g.jid as gpg_jid, g.username as gpg_username
            FROM posts p
            LEFT JOIN gpg_keys g ON p.gpg_key_id = g.key_id
            WHERE p.thread_id = ? AND p.is_deleted = FALSE ORDER BY p.id ASC";
    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$threadId]);
    return $stmt->fetchAll();
}

function getLatestReplies($threadId, $limit = 5) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT * FROM (
            SELECT p.*, g.email as gpg_email, g.jid as gpg_jid, g.username as gpg_username
            FROM posts p
            LEFT JOIN gpg_keys g ON p.gpg_key_id = g.key_id
            WHERE p.thread_id = ? AND p.is_deleted = FALSE ORDER BY p.id DESC LIMIT ?
        ) sub ORDER BY id ASC
    ");
    $stmt->execute([$threadId, $limit]);
    return $stmt->fetchAll();
}

function getPopularThreads($limit = 10) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT p.*, b.uri as board_uri, b.title as board_title
        FROM posts p
        JOIN boards b ON p.board_id = b.id
        WHERE p.thread_id IS NULL AND p.is_deleted = FALSE AND p.reply_count > 0
        ORDER BY p.reply_count DESC, p.bumped_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getTotalPosts() {
    $pdo = getDB();
    return $pdo->query("SELECT COUNT(*) FROM posts WHERE is_deleted = FALSE")->fetchColumn();
}

function getAllThreads($boardId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT * FROM posts
        WHERE board_id = ? AND thread_id IS NULL AND is_deleted = FALSE
        ORDER BY is_sticky DESC, bumped_at DESC
    ");
    $stmt->execute([$boardId]);
    return $stmt->fetchAll();
}

function getThreadImageCount($threadId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM posts
        WHERE thread_id = ? AND file_path IS NOT NULL AND is_deleted = FALSE
    ");
    $stmt->execute([$threadId]);
    return $stmt->fetchColumn();
}

function getRandomBanner() {
    $bannerDir = __DIR__ . '/../static/images/banners/';
    if (!is_dir($bannerDir)) {
        return null;
    }

    $files = glob($bannerDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    if (empty($files)) {
        return null;
    }

    $randomFile = $files[array_rand($files)];
    $relativePath = str_replace(__DIR__ . '/../', '', $randomFile);
    return '/' . $relativePath;
}

function getActiveAnnouncements() {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT a.*, s.username as staff_name
        FROM announcements a
        LEFT JOIN staff s ON a.staff_id = s.id
        WHERE a.is_active = TRUE
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    return $stmt->fetchAll();
}

function generateVideoEmbed($url) {
    // YouTube embed
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $matches)) {
        $videoId = $matches[1];
        return '<div class="video-embed youtube-embed">
            <iframe width="560" height="315" src="https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>';
    }

    // Niconico embed
    if (preg_match('/(?:nicovideo\.jp\/watch\/|nico\.ms\/)([a-z]{2}\d+)/i', $url, $matches)) {
        $videoId = $matches[1];
        return '<div class="video-embed niconico-embed">
            <iframe width="560" height="315" src="https://embed.nicovideo.jp/watch/' . htmlspecialchars($videoId) . '" frameborder="0" allowfullscreen></iframe>
        </div>';
    }

    return false;
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return date('m/d/Y', $time);
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function applyWordFilters($text) {
    static $filters = null;

    // Cache filters to avoid repeated database queries
    if ($filters === null) {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT word, replacement FROM word_filters WHERE is_active = TRUE ORDER BY LENGTH(word) DESC");
        $filters = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Apply filters - sort by length descending to handle longer words first
    foreach ($filters as $word => $replacement) {
        $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', $replacement, $text);
    }

    return $text;
}
