<?php
// table: posts
$config = include('../config.php');
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
if ($name === null || $name === false || $name === '') {
    $name = 'Anonymous';
}
$comment  = filter_input(INPUT_POST, 'com', FILTER_SANITIZE_STRING);
$board = filter_input(INPUT_POST, 'board', FILTER_SANITIZE_STRING);
$replyTo = filter_input(INPUT_POST, 'replyto', FILTER_SANITIZE_STRING);
    if ($comment == '' || $comment == NULL) {
        echo "Do not post empty posts, are you stupid?";
    } else {
        require_once 'utils.php';
        // determine authenticated user id (if any) to persist user_id for authoritative capcodes
        $authUser = current_admin_user();
        $authId = isset($authUser['id']) ? intval($authUser['id']) : null;
        // Verify CSRF token
        $csrf = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
        if (!verifyCsrfToken($csrf)) {
            http_response_code(403);
            die("Invalid CSRF token");
        }
        // APPLY WORDFILTERS
        require_once 'wordfilter.php';
        $filters = new FilterManager();
        $filters->addFilter(new FunnyFilter());
        //$filters->addFilter(new UrlFilter());
        $filters->addFilter(new CapsFilter());

        //RATELIMIT
        rateLimit();
        
        if (isset($config['pages'][$board])) {
                $insertId = postToDB($name, $filters->applyFilters($comment), $_SERVER['REMOTE_ADDR'], 1, $board, 0, $authId);
                    if ($insertId !== false && $insertId > 0) {
                        header("Location: /" . rawurlencode($board) . "/thread/" . intval($insertId));
                    die();
                } else {
                    echo "Could not post man sry";
                    die();
                }
        } else {
            echo "Stop passing bad arguments man";
            die();
        }
    }
