<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/includes/auth.php';

initDatabase();

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, '/') ?: '/';

try {
    if ($path === '/') {
        include __DIR__ . '/pages/home.php';
    }
    elseif ($path === '/captcha') {
        renderCaptchaImage();
    }
    elseif ($path === '/admin/login') {
        include __DIR__ . '/pages/admin/login.php';
    }
    elseif ($path === '/admin/logout') {
        logout();
        header('Location: /');
        exit;
    }
    elseif (preg_match('#^/admin#', $path)) {
        requireLogin();
        if ($path === '/admin' || $path === '/admin/') {
            include __DIR__ . '/pages/admin/dashboard.php';
        }
        elseif ($path === '/admin/reports') {
            include __DIR__ . '/pages/admin/reports.php';
        }
        elseif ($path === '/admin/bans') {
            include __DIR__ . '/pages/admin/bans.php';
        }
        elseif ($path === '/admin/logs') {
            include __DIR__ . '/pages/admin/logs.php';
        }
        elseif ($path === '/admin/users') {
            include __DIR__ . '/pages/admin/users.php';
        }
        elseif ($path === '/admin/announcements') {
            include __DIR__ . '/pages/admin/announcements.php';
        }
        elseif ($path === '/admin/boards') {
            include __DIR__ . '/pages/admin/boards.php';
        }
        elseif ($path === '/admin/settings') {
            include __DIR__ . '/pages/admin/settings.php';
        }
        elseif ($path === '/admin/hidden') {
            include __DIR__ . '/pages/admin/hidden.php';
        }
        elseif ($path === '/admin/wordfilters') {
            include __DIR__ . '/pages/admin/wordfilters.php';
        }
        elseif (preg_match('#^/admin/mod/(\w+)#', $path, $matches)) {
            $_GET['action'] = $matches[1];
            include __DIR__ . '/pages/admin/modaction.php';
        }
        else {
            http_response_code(404);
            echo 'Page not found';
        }
    }
    elseif($path === '/webring.json') {
        header('Content-Type: application/json; charset=utf-8');
        readfile(__DIR__ . '/includes/webring/webring.json');
    }
    elseif($path === '/api/status.json') {
        header('Content-Type: application/json; charset=utf-8');
        $pdo = getDB();
        $stmt = $pdo->query("SELECT COUNT(*) AS post_count FROM posts");
        $postCount = $stmt->fetch()['post_count'];
        echo json_encode(['posts' => (int)$postCount]);
    }
    elseif($path === '/webring/compiled_webring.json') {
        header('Content-Type: application/json; charset=utf-8');
        readfile(__DIR__ . '/includes/webring/compiled_webring.json');
    }
    elseif($path === '/webring/known_hosts.json') {
        header('Content-Type: application/json; charset=utf-8');
        readfile(__DIR__ . '/includes/webring/known_hosts.json');
    }
    elseif ($path === '/overboard') {
        include __DIR__ . '/pages/overboard.php';
    }
    elseif ($path === '/atom.xml') {
        include __DIR__ . '/pages/atom.php';
    }
    elseif (preg_match('#^/(\w+)/thread/(\d+)$#', $path, $matches)) {
        $_GET['board'] = $matches[1];
        $_GET['thread'] = $matches[2];
        include __DIR__ . '/pages/thread.php';
    }
    elseif (preg_match('#^/(\w+)/catalog$#', $path, $matches)) {
        $_GET['board'] = $matches[1];
        include __DIR__ . '/pages/catalog.php';
    }
    elseif (preg_match('#^/(\w+)/post$#', $path, $matches)) {
        $_GET['board'] = $matches[1];
        include __DIR__ . '/pages/post.php';
    }
    elseif (preg_match('#^/(\w+)/reply$#', $path, $matches)) {
        $_GET['board'] = $matches[1];
        include __DIR__ . '/pages/reply.php';
    }
    elseif (preg_match('#^/(\w+)/report/(\d+)$#', $path, $matches)) {
        $_GET['board'] = $matches[1];
        $_GET['post'] = $matches[2];
        include __DIR__ . '/pages/report.php';
    }
    elseif (preg_match('#^/(\w+)/?$#', $path, $matches)) {
        $_GET['board'] = $matches[1];
        include __DIR__ . '/pages/board.php';
    }
    elseif (preg_match('#^/uploads/#', $path)) {
        $file = __DIR__ . $path;
        if (file_exists($file)) {
            $mime = mime_content_type($file);
            header('Content-Type: ' . $mime);
            readfile($file);
        } else {
            http_response_code(404);
            echo 'File not found';
        }
    }
    elseif (preg_match('#^/static/#', $path)) {
        $file = __DIR__ . $path;
        if (file_exists($file)) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $mimes = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif'];
            header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
            readfile($file);
        } else {
            http_response_code(404);
        }
    }
    elseif (preg_match('#^/gpg/key/([a-zA-Z0-9]+)$#', $path, $matches)) {
        $keyId = $matches[1];
        $gpgKey = getGpgKey($keyId);

        if (!$gpgKey) {
            http_response_code(404);
            echo 'GPG key not found';
            exit;
        }

        header('Content-Type: application/pgp-keys');
        header('Content-Disposition: attachment; filename="' . $keyId . '.asc"');
        header('Content-Length: ' . strlen($gpgKey['public_key']));
        echo $gpgKey['public_key'];
        exit;
    }
    elseif (preg_match('#^/gpg/signed/(\d+)$#', $path, $matches)) {
        $postId = (int)$matches[1];

        // Get the post with GPG information
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT p.*, g.public_key
            FROM posts p
            LEFT JOIN gpg_keys g ON p.gpg_key_id = g.key_id
            WHERE p.id = ? AND p.gpg_verified = true
        ");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post) {
            http_response_code(404);
            echo 'GPG signed post not found';
            exit;
        }

        // Reconstruct the signed message
        $signedMessage = "-----BEGIN PGP SIGNED MESSAGE-----\n";
        $signedMessage .= "Hash: SHA512\n\n";
        $signedMessage .= $post['comment'] . "\n";
        $signedMessage .= "-----BEGIN PGP SIGNATURE-----\n";
        $signedMessage .= $post['gpg_signature'];
        $signedMessage .= "-----END PGP SIGNATURE-----\n";

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="post_' . $postId . '_signed.asc"');
        header('Content-Length: ' . strlen($signedMessage));
        echo $signedMessage;
        exit;
    }
    else {
        http_response_code(404);
        echo 'Page not found';
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'An error occurred';
}
