<?php
function rateLimit()
{
    $config = include __DIR__ . '/../config.php';
    $ip = $_SERVER['REMOTE_ADDR'];
    // Use a hashed filename for the IP so IPv6 colons or other characters don't break filenames
    $ipHash = md5($ip);
    $limitDir = __DIR__ . '/utilities';
    if (!is_dir($limitDir)) {
        @mkdir($limitDir, 0755, true);
    }
    $limitFile = $limitDir . "/rate_limit_{$ipHash}.txt";
    $waitTime = $config['ratelimittime'];

    $lastTime = 0;
    if (file_exists($limitFile)) {
        $lastTime = (int)file_get_contents($limitFile);
    }

    $now = time();

    if ($now - $lastTime < $waitTime) {
        $remaining = $waitTime - ($now - $lastTime);
        http_response_code(429); // Too Many Requests
        die("Rate limit exceeded. Try again in $remaining seconds.");
    }

    file_put_contents($limitFile, $now);
}

function postToDB($fname, $fpost, $fip, $fisOP, $fboard, $fthreadnumber)
{
    $config = include __DIR__ . '/../config.php';
    try {
        $db = new SQLite3($config['postdb']);
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
    $db->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        post TEXT,
        ipaddress TEXT,
        isOP INTEGER,
        board TEXT,
        threadNumber INTEGER,
        created_at INTEGER,
        bumped_at INTEGER
    )");

    $hasBumped = false;
    $info = $db->query("PRAGMA table_info('posts')");
    if ($info) {
        while ($col = $info->fetchArray(SQLITE3_ASSOC)) {
            if (isset($col['name']) && $col['name'] === 'bumped_at') {
                $hasBumped = true;
                break;
            }
        }
    }
    if (!$hasBumped) {
        @$db->exec("ALTER TABLE posts ADD COLUMN bumped_at INTEGER");
    }

    $stmt = $db->prepare("INSERT INTO posts (name, post, ipaddress, isOP, board, threadNumber, created_at) VALUES (:name, :post, :ipaddr, :isOP, :board, :threadNumber, :created_at)");
    $stmt->bindValue(':name', $fname, SQLITE3_TEXT);
    $stmt->bindValue(':post', $fpost, SQLITE3_TEXT);
    $stmt->bindValue(':ipaddr', $fip);
    $stmt->bindValue(':isOP', $fisOP);
    $stmt->bindValue(':board', $fboard);
    $stmt->bindValue(':threadNumber', $fthreadnumber);
    $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result) {
        $lastId = $db->lastInsertRowID();
        $now = time();

        if ($fisOP && ($fthreadnumber === 0 || $fthreadnumber === '0')) {
            $update = $db->prepare("UPDATE posts SET threadNumber = :tn, bumped_at = :bumped WHERE id = :id");
            $update->bindValue(':tn', $lastId, SQLITE3_INTEGER);
            $update->bindValue(':bumped', $now, SQLITE3_INTEGER);
            $update->bindValue(':id', $lastId, SQLITE3_INTEGER);
            $update->execute();
        } else {
            if (!empty($fthreadnumber)) {
                $bstmt = $db->prepare("UPDATE posts SET bumped_at = :bumped WHERE id = :threadId AND isOP = 1");
                $bstmt->bindValue(':bumped', $now, SQLITE3_INTEGER);
                $bstmt->bindValue(':threadId', $fthreadnumber, SQLITE3_INTEGER);
                $bstmt->execute();
            }
        }

        $db->close();
        return $lastId;
    } else {
        $db->close();
        return false;
    }
}

// CSRF helper functions
function ensure_session_started()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function generateCsrfToken()
{
    ensure_session_started();
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            // Fallback
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    ensure_session_started();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
