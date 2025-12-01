<?php
require __DIR__ . '/../scripts/utils.php';
require __DIR__ . '/../scripts/functions.php';
$twig = registerFunc();
ensure_session_started();
$user = current_admin_user();
if (!$user) {
    header('Location: /admin/login');
    exit;
}

$users = [];
if (require_admin_permission('view_admin')) {
    $users = list_admin_users();
}

// Provide some basic stats
$config = include __DIR__ . '/../config.php';
$db = new SQLite3($config['postdb']);
$tablesRes = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'posts_%'");
$totalPosts = 0;
if ($tablesRes) {
    while ($t = $tablesRes->fetchArray(SQLITE3_ASSOC)) {
        $c = $db->querySingle('SELECT COUNT(*) FROM "' . $t['name'] . '"');
        $totalPosts += (int)$c;
    }
}
$db->close();

echo $twig->render('admin/panel.twig', ['user' => $user, 'users' => $users, 'total_posts' => $totalPosts, 'csrf_token' => generateCsrfToken()]);
