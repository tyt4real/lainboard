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

// We'll choose a template and vars and only instantiate Twig once at the end.
$renderTemplate = null;
$renderVars = [];

// --- Admin routing (handled inline so no helper admin files are required) ---
if (isset($segments[0]) && $segments[0] === 'admin') {
//if ($requestUri == '/admin') {
    $action = $segments[1] ?? '';
    // POST actions that should redirect immediately
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // handle login
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            header('HTTP/1.1 400 Bad Request');
            echo 'Invalid CSRF token';
            exit;
        }
        $user = verify_user_credentials($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($user) {
            login_user_session($user);
            header('Location: /admin/panel');
            exit;
        } else {
            // show login with error
            $renderTemplate = 'admin/login.twig';
            $renderVars = ['error' => 'Invalid credentials', 'csrf_token' => generateCsrfToken()];
        }
    } elseif ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // show login form
        $renderTemplate = 'admin/login.twig';
        $renderVars = ['csrf_token' => generateCsrfToken()];
    } elseif ($action === 'logout') {
        // logout is simple: destroy admin session and redirect
        logout_user_session();
        header('Location: /');
        exit;
    } elseif ($action === 'action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // central admin action handler (delete/ban/create user/edit config)
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            header('HTTP/1.1 400 Bad Request');
            echo 'Invalid CSRF token';
            exit;
        }
        $user = current_admin_user();
        if (!$user) {
            header('Location: /admin/login');
            exit;
        }
        $uid = $user['id'];
        $act = $_POST['act'] ?? '';
        $performed = false;
        switch ($act) {
            case 'delete_post':
                if (!user_has_permission($uid, 'delete_post')) break;
                $performed = delete_post_by_board_and_id($_POST['board'] ?? '', (int)($_POST['post_id'] ?? 0));
                if ($performed) log_admin_action($uid, 'delete_post', json_encode(['board'=>$_POST['board'] ?? '', 'post_id'=>$_POST['post_id'] ?? '']));
                break;
            case 'ban_ip':
                if (!user_has_permission($uid, 'ban_ip')) break;
                $performed = ban_ip($_POST['ip'] ?? '', $_POST['reason'] ?? '', $uid);
                if ($performed) log_admin_action($uid, 'ban_ip', json_encode(['ip'=>$_POST['ip'] ?? '', 'reason'=>$_POST['reason'] ?? '']));
                break;
            case 'create_user':
                if (!user_has_permission($uid, 'create_user')) break;
                $performed = create_user($_POST['username'] ?? '', $_POST['password'] ?? '', $_POST['role'] ?? 'moderator');
                if ($performed) log_admin_action($uid, 'create_user', json_encode(['username'=>$_POST['username'] ?? '', 'role'=>$_POST['role'] ?? '']));
                break;
            case 'edit_config':
                if (!user_has_permission($uid, 'edit_config')) break;
                // simple config edit: delegate to scripts/utils backup-safe helper if present
                // For now, deny direct config edits via inline handler (safer)
                $performed = false;
                break;
        }
        // Redirect back to panel with optional status
        header('Location: /admin/panel');
        exit;
    } elseif ($action === 'panel') {
        // show admin panel; verify permission
        $user = current_admin_user();
        if (!$user) {
            header('Location: /admin/login');
            exit;
        }
        if (!user_has_permission($user['id'], 'view_admin')) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Forbidden';
            exit;
        }
        // prepare data for panel template
        // gather user list and a simple total-posts stat (same logic as admin/panel.php)
        $users = list_admin_users();
        $totalPosts = 0;
        $db = new SQLite3($config['postdb']);
        $tablesRes = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'posts_%'");
        if ($tablesRes) {
            while ($t = $tablesRes->fetchArray(SQLITE3_ASSOC)) {
                $c = $db->querySingle('SELECT COUNT(*) FROM "' . $t['name'] . '"');
                $totalPosts += (int)$c;
            }
        }
        $db->close();

        $renderTemplate = 'admin/panel.twig';
        // Collect recent posts across boards for admin listing (limit to most recent 500 to avoid huge pages)
        $posts = [];
        $limitPerBoard = 200;
        $db2 = new SQLite3($config['postdb']);
        $tablesRes2 = $db2->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'posts_%'");
        if ($tablesRes2) {
            while ($t = $tablesRes2->fetchArray(SQLITE3_ASSOC)) {
                $tname = $t['name'];
                try {
                    $q = $db2->prepare('SELECT id, name, ipaddress, isOP, threadNumber, created_at FROM "' . $tname . '" ORDER BY created_at DESC LIMIT :lim');
                    $q->bindValue(':lim', $limitPerBoard, SQLITE3_INTEGER);
                    $res = $q->execute();
                    if ($res) {
                        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
                            $r['board'] = substr($tname, strlen('posts_'));
                            // try to find a linked user id for this poster name, if any
                            $uidStmt = $db2->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
                            if ($uidStmt) {
                                $uidStmt->bindValue(':u', $r['name'], SQLITE3_TEXT);
                                $uRes = $uidStmt->execute();
                                if ($uRes) {
                                    $uRow = $uRes->fetchArray(SQLITE3_ASSOC);
                                    $r['user_id'] = $uRow ? (int)$uRow['id'] : 0;
                                } else {
                                    $r['user_id'] = 0;
                                }
                            } else {
                                $r['user_id'] = 0;
                            }
                            $posts[] = $r;
                        }
                    }
                } catch (Exception $e) {
                    // ignore unreadable tables
                }
            }
        }
        // sort posts by created_at desc and limit to 500
        usort($posts, function ($a, $b) { return intval($b['created_at']) <=> intval($a['created_at']); });
        $posts = array_slice($posts, 0, 500);
        $db2->close();

        $renderVars = [
            'user' => $user,
            'users' => $users,
            'banned' => [],
            'csrf_token' => generateCsrfToken(),
            'total_posts' => $totalPosts,
            'posts' => $posts,
        ];
    } elseif ($action === '' || $action === null) {
        header('Location: /admin/panel');
        exit;
    } else {
        // unknown admin path -> prepare 404 render
        http_response_code(404);
        $renderTemplate = 'base.twig';
        $renderVars = ['content' => 'Admin page not found'];
    }
}

// Thread route
if ($threadPos !== false && isset($segments[$threadPos + 1])) {
    $board = $segments[$threadPos - 1] ?? 'b';      // the board before "thread"
    $pageType = 'thread';
    $threadId = (int)$segments[$threadPos + 1];
    $renderTemplate = 'thread.twig';
    $renderVars = [
        'board'    => $board,
        'threadId' => $threadId,
        'pages'    => $config['pages'],
    ];
}

// Home route
if ($requestUri == '/home') {
    $renderTemplate = 'home.twig';
    $renderVars = [
        'pages' => $config['pages'],
        // passing site configuration to the template.
        'headertitle' => $config['headertitle'],
        'sitename' => $config['sitename'],
        'sitedescription' => $config['sitedescription'],
        'homepage' => $config['homepage'] ?? [],
    ];
}

// Overboard route: aggregate OPs from all boards
if ($requestUri == '/overboard' || (isset($segments[0]) && $segments[0] === 'overboard')) {
    $renderTemplate = 'overboard.twig';
    $renderVars = [
        'pages' => $config['pages'],
        'headertitle' => $config['headertitle'],
        'sitename' => $config['sitename'],
        'sitedescription' => $config['sitedescription'],
    ];
}

// If no template decided yet, do normal page rendering
if ($renderTemplate === null) {
    $board = $segments[0] ?? 'b';
    $pageSlug = $board;

    if (!isset($config['pages'][$pageSlug])) {
        http_response_code(404);
        $pageSlug = 'board';
    }

    $pageConfig = $config['pages'][$pageSlug];
    $renderTemplate = $pageConfig['template'];
    $renderVars = [
        'current_page' => $pageSlug,
        'pages'        => $config['pages'],
        'boardname'    => $pageConfig['boardname'] ?? $pageSlug,
        'path'         => $segments,
        'param'        => $segments[1] ?? '',
    ];
}

// Instantiate Twig once and render the chosen template
$twig = registerFunc();
$twig->addGlobal('csrf_token', generateCsrfToken());
echo $twig->render($renderTemplate, $renderVars);
