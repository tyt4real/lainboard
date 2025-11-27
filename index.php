<?php
require_once 'vendor/autoload.php';
// Start session early so CSRF tokens and other session-driven features work
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'scripts/utils.php';
require_once 'scripts/functions.php';

$config = require 'config.php';

// Parse the request URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Remove any empty segments
$segments = array_values(array_filter(explode('/', trim($requestUri, '/'))));

// Look for "thread" anywhere in segments
$threadPos = array_search('thread', $segments);
// Register Twig functions
$twig = registerFunc();
// Expose CSRF token to templates as a global
$twig->addGlobal('csrf_token', generateCsrfToken());
if ($threadPos !== false && isset($segments[$threadPos + 1])) {
    $board = $segments[$threadPos - 1] ?? 'b';      // the board before "thread"
    $pageType = 'thread';
    $threadId = (int)$segments[$threadPos + 1];

    echo $twig->render('thread.twig', [
        'board'    => $board,
        'threadId' => $threadId,
        'pages'    => $config['pages'],
    ]);
    exit;
}

// Otherwise, normal page rendering
$board = $segments[0] ?? 'b';
$pageSlug = $board;

if (!isset($config['pages'][$pageSlug])) {
    http_response_code(404);
    $pageSlug = 'board';
}

$pageConfig = $config['pages'][$pageSlug];

echo $twig->render($pageConfig['template'], [
    'current_page' => $pageSlug,
    'pages'        => $config['pages'],
    'boardname'    => $pageConfig['boardname'] ?? $pageSlug,
    'path'         => $segments,
    'param'        => $segments[1] ?? '',
]);
