<?php 
require_once 'vendor/autoload.php';
require_once 'scripts/functions.php';

$config = require 'config.php';

$pageSlug = $_GET['page'] ?? 'board';

if (!isset($config['pages'][$pageSlug])) {
    http_response_code(404);
    $pageSlug = 'board'; //fallback
}
$pageConfig = $config['pages'][$pageSlug];

$twig = registerFunc();

echo $twig->render($pageConfig['template'], [
    'current_page' => $pageSlug,
    'pages' => $config['pages'],
]);


?>