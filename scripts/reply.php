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
// determine authenticated user id (if any) to persist user_id for authoritative capcodes
$authUser = current_admin_user();
$authId = isset($authUser['id']) ? intval($authUser['id']) : null;
// Verify CSRF
$csrf = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
if (!verifyCsrfToken($csrf)) {
    http_response_code(403);
    die("Invalid CSRF token");
}

$userCaptcha = filter_input(INPUT_POST, 'captcha', FILTER_SANITIZE_STRING);
if (!isset($_SESSION['captcha_phrase']) || strtolower($userCaptcha) !== strtolower($_SESSION['captcha_phrase'])) {
    http_response_code(400);
    echo('captcha is'.$_SESSION['captcha_phrase']);
    die("Captcha incorrect. Try again.");
}

// invalidate captcha after use
unset($_SESSION['captcha_phrase']);

//Get the OP's post from the board-specific table
try {
    $db = new SQLite3($config['postdb']);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
$table = boardTableName($board);
$stmt = $db->prepare("SELECT * FROM " . $table . " WHERE id = :id");
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

    //Check if the post is empty.
    if($comment == '' || $comment == NULL) {
        echo "Do not post empty posts, are you stupid?";
        die();
    }

    $insertId = postToDB($name, $filters->applyFilters($comment), $_SERVER['REMOTE_ADDR'], 0, $board, $threadId, $authId);
    if ($insertId !== false && $insertId > 0) {
            header("Location: /" . rawurlencode($board) . "/thread/" . intval($threadId));
        die();
    } else {
        echo "Could not post man sry";
        die();
    }
} else {
    echo 'post probably doenst exist';
}
