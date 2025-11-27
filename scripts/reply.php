<?php
$config = include('../config.php');
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
if ($name === null || $name === false || $name === '') {
    $name = 'Anonymous';
}
$comment  = filter_input(INPUT_POST, 'com', FILTER_SANITIZE_STRING);
$replyTo = filter_input(INPUT_POST, 'replyto', FILTER_SANITIZE_STRING);
$board = filter_input(INPUT_POST, 'board', FILTER_SANITIZE_STRING);
require_once 'utils.php';
require_once 'wordfilter.php';
// Verify CSRF
$csrf = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
if (!verifyCsrfToken($csrf)) {
    http_response_code(403);
    die("Invalid CSRF token");
}
//Get the OP's post
try {
    $db = new SQLite3($config['postdb']);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
$stmt = $db->prepare("SELECT * FROM posts WHERE id = :id");
$stmt->bindValue(':id', $replyTo, SQLITE3_TEXT);
$OPost = $stmt->execute();

if ($OPost) {
   
    $post = $OPost->fetchArray(SQLITE3_ASSOC);
    $filters = new FilterManager();
    $filters->addFilter(new FunnyFilter());
    //$filters->addFilter(new UrlFilter());
    $filters->addFilter(new CapsFilter());

    //RATELIMIT
    //rateLimit();
    $db->close();
    $threadId = null;
    if (isset($post['isOP']) && intval($post['isOP']) === 1) {
        $threadId = (int)$post['id'];
    } else {
        $threadId = isset($post['threadNumber']) ? (int)$post['threadNumber'] : (int)$post['id'];
    }

    // Attach reply to the resolved thread id
    $insertId = postToDB($name, $filters->applyFilters($comment), $_SERVER['REMOTE_ADDR'], 0, $post['board'], $threadId);
    if ($insertId !== false && $insertId > 0) {
        header("Location: /thread/" . intval($threadId));
        die();
    } else {
        echo "Could not post man sry";
        die();
    }
} else {
    echo 'post probably doenst exist';
}
