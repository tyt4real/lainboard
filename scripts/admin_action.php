<?php
require __DIR__ . '/utils.php';
ensure_session_started();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF');
}
$action = $_POST['action'] ?? '';
$user = current_admin_user();
if (!$user) {
    http_response_code(403);
    die('Not authenticated');
}

switch ($action) {
    case 'delete_post':
        if (!require_admin_permission('delete_post')) die('Permission denied');
        $board = $_POST['board'] ?? '';
        $id = intval($_POST['post_id'] ?? 0);
        if ($board && $id) {
            $ok = delete_post_by_board_and_id($board, $id);
            if ($ok) {
                log_admin_action($user['id'], 'delete_post', "board={$board} id={$id}");
                echo 'OK';
            } else echo 'FAILED';
        } else echo 'BAD_INPUT';
        break;
    case 'ban_ip':
        if (!require_admin_permission('ban_ip')) die('Permission denied');
        $ip = $_POST['ip'] ?? '';
        $reason = $_POST['reason'] ?? '';
        if ($ip) {
            $ok = ban_ip($ip, $reason, $user['id']);
            if ($ok) {
                log_admin_action($user['id'], 'ban_ip', "ip={$ip} reason={$reason}");
                echo 'OK';
            } else echo 'FAILED';
        } else echo 'BAD_INPUT';
        break;
    case 'create_user':
        if (!require_admin_permission('create_user')) die('Permission denied');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'moderator';
        if ($username && $password) {
            $ok = create_user($username, $password, $role);
            if ($ok) {
                $newUser = get_user_by_username($username);
                if ($newUser) log_admin_action($user['id'], 'create_user', "created={$username} id={$newUser['id']} role={$role}");
                echo 'OK';
            } else echo 'FAILED';
        } else echo 'BAD_INPUT';
        break;
    case 'change_password':
        if (!require_admin_permission('change_password')) die('Permission denied');
        $uid = intval($_POST['user_id'] ?? 0);
        $newpw = $_POST['new_password'] ?? '';
        if ($uid && $newpw) {
            $config = include __DIR__ . '/../config.php';
            $db = new SQLite3($config['postdb']);
            $hash = password_hash($newpw, PASSWORD_DEFAULT);
            $u = $db->prepare("UPDATE users SET password_hash = :ph WHERE id = :id");
            $u->bindValue(':ph', $hash, SQLITE3_TEXT);
            $u->bindValue(':id', $uid, SQLITE3_INTEGER);
            $res = $u->execute();
            $db->close();
            if ($res) { log_admin_action($user['id'], 'change_password', "user_id={$uid}"); echo 'OK'; } else echo 'FAILED';
        } else echo 'BAD_INPUT';
        break;
    case 'delete_user':
        if (!require_admin_permission('delete_user')) die('Permission denied');
        $uid = intval($_POST['user_id'] ?? 0);
        if ($uid) {
            $ok = delete_user_by_id($uid);
            if ($ok) {
                log_admin_action($user['id'], 'delete_user', "user_id={$uid}");
                echo 'OK';
            } else echo 'FAILED';
        } else echo 'BAD_INPUT';
        break;
    case 'edit_config':
        if (!require_admin_permission('edit_config')) die('Permission denied');
        $content = $_POST['config_php'] ?? '';
        if ($content) {
            $path = __DIR__ . '/../config.php';
            // backup
            copy($path, $path . '.bak.' . time());
            $written = file_put_contents($path, $content);
            if ($written !== false) { log_admin_action($user['id'], 'edit_config', 'updated config.php'); echo 'OK'; } else echo 'FAILED';
        } else echo 'BAD_INPUT';
        break;
    default:
        echo 'UNKNOWN_ACTION';
}
