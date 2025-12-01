<?php
function rateLimit()
{
    $config = include __DIR__ . '/../config.php';
    $ip = $_SERVER['REMOTE_ADDR'];
    // Use a hashed filename for the IP so IPv6 colons or other characters don't break filenames (fuck ipv6)
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

// Return sanitized table name for a board slug. Uses posts_{slug}.
function boardTableName($board)
{
    $slug = strtolower($board);
    // only allow a-z0-9 and underscore
    $slug = preg_replace('/[^a-z0-9_]/', '_', $slug);
    if ($slug === '') {
        $slug = 'b';
    }
    return 'posts_' . $slug;
}

function postToDB($fname, $fpost, $fip, $fisOP, $fboard, $fthreadnumber, $fuserId = null)
{
    $config = include __DIR__ . '/../config.php';
    try {
        $db = new SQLite3($config['postdb']);
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
    // Give SQLite some time to wait for locks instead of failing immediately
    if (method_exists($db, 'busyTimeout')) {
        @$db->busyTimeout(2000);
    }

    // Determine table name for this board
    $table = boardTableName($fboard);

    // Ensure per-board table exists. created_at stored as integer UNIX timestamp.
    $db->exec("CREATE TABLE IF NOT EXISTS " . $table . " (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        post TEXT,
        ipaddress TEXT,
        isOP INTEGER,
        threadNumber INTEGER,
        created_at INTEGER,
        bumped_at INTEGER,
        user_id INTEGER
    )");
    // checking if authenticated.
    $sessionUsername = $_SESSION['admin_user']['username'] ?? null;
    if ($sessionUsername === null) {
        // not authenticated
        $usernamesQuery = $db->query("SELECT username FROM users");

        $usernames = [];
        while ($row = $usernamesQuery->fetchArray(SQLITE3_ASSOC)) {
            $usernames[] = $row['username'];
        }
        //echo("<script>console.log('PHP: " . var_dump($usernames, $fname) . "');</script>");
        // variable to check
        $target = $fname;

        if (in_array($target, $usernames)) {
            echo "Did you know that impersonation is a crime in some jurisdictions?";
            die;
        } else {
            //pass do nothing
        }
    }

    // Ensure bumped_at column exists for older tables
    $hasBumped = false;
    $info = $db->query("PRAGMA table_info('" . $table . "')");
    if ($info) {
        while ($col = $info->fetchArray(SQLITE3_ASSOC)) {
            if (isset($col['name']) && $col['name'] === 'bumped_at') {
                $hasBumped = true;
                break;
            }
        }
    }
    if (!$hasBumped) {
        @$db->exec("ALTER TABLE " . $table . " ADD COLUMN bumped_at INTEGER");
    }
    // Ensure user_id column exists for posts to track authenticated authors
    $hasUserId = false;
    $info2 = $db->query("PRAGMA table_info('" . $table . "')");
    if ($info2) {
        while ($col = $info2->fetchArray(SQLITE3_ASSOC)) {
            if (isset($col['name']) && $col['name'] === 'user_id') {
                $hasUserId = true;
                break;
            }
        }
    }
    if (!$hasUserId) {
        // best-effort add
        @$db->exec("ALTER TABLE " . $table . " ADD COLUMN user_id INTEGER");
    }

    // To ensure globally unique post IDs across boards, reserve an autoincrement id
    // in a central table. This uses INSERT + lastInsertRowID() which is atomic on
    // the connection and avoids computing MAX(id) across tables (race-prone).
    $db->exec("CREATE TABLE IF NOT EXISTS global_post_ids (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at INTEGER
    )");

    // Best-effort retry loop to handle SQLITE_BUSY under concurrent writers.
    $attempts = 5;
    $waitBaseUs = 50000; // 50ms
    $reservedId = false;

    for ($try = 0; $try < $attempts; $try++) {
        // Start a transaction; keep it short
        if (!$db->exec('BEGIN')) {
            // Try again after backoff
            usleep($waitBaseUs * ($try + 1));
            continue;
        }

        // Reserve an id by inserting a row into the central counter table.
        $gstmt = $db->prepare("INSERT INTO global_post_ids (created_at) VALUES (:c)");
        $gstmt->bindValue(':c', time(), SQLITE3_INTEGER);
        $gres = $gstmt->execute();
        if ($gres) {
            $reservedId = $db->lastInsertRowID();
            // proceed to insert into the per-board table using this id
        } else {
            // couldn't reserve id; rollback and retry
            @$db->exec('ROLLBACK');
            usleep($waitBaseUs * ($try + 1));
            continue;
        }

        // Insert into per-board table with explicit global id
        $stmt = $db->prepare("INSERT INTO " . $table . " (id, name, post, ipaddress, isOP, threadNumber, created_at, user_id) VALUES (:id, :name, :post, :ipaddr, :isOP, :threadNumber, :created_at, :user_id)");
        if (!$stmt) {
            // prepare failed; rollback and retry
            @$db->exec('ROLLBACK');
            usleep($waitBaseUs * ($try + 1));
            $reservedId = false;
            continue;
        }
        $stmt->bindValue(':id', $reservedId, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $fname, SQLITE3_TEXT);
        $stmt->bindValue(':post', $fpost, SQLITE3_TEXT);
        $stmt->bindValue(':ipaddr', $fip);
        $stmt->bindValue(':isOP', $fisOP);
        $stmt->bindValue(':threadNumber', $fthreadnumber);
        $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $fuserId !== null ? intval($fuserId) : null, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result) {
            $lastId = $reservedId;
            $now = time();

            if ($fisOP && ($fthreadnumber === 0 || $fthreadnumber === '0')) {
                $update = $db->prepare("UPDATE " . $table . " SET threadNumber = :tn, bumped_at = :bumped WHERE id = :id");
                $update->bindValue(':tn', $lastId, SQLITE3_INTEGER);
                $update->bindValue(':bumped', $now, SQLITE3_INTEGER);
                $update->bindValue(':id', $lastId, SQLITE3_INTEGER);
                $update->execute();
            } else {
                if (!empty($fthreadnumber)) {
                    $bstmt = $db->prepare("UPDATE " . $table . " SET bumped_at = :bumped WHERE id = :threadId AND isOP = 1");
                    $bstmt->bindValue(':bumped', $now, SQLITE3_INTEGER);
                    $bstmt->bindValue(':threadId', $fthreadnumber, SQLITE3_INTEGER);
                    $bstmt->execute();
                }
            }

            $db->exec('COMMIT');
            $db->close();
            return $lastId;
        } else {
            // insertion into per-board table failed; rollback and retry
            @$db->exec('ROLLBACK');
            // small backoff before retrying
            usleep($waitBaseUs * ($try + 1));
            $reservedId = false;
            continue;
        }
    }

    // If we reach here, all attempts failed
    $db->close();
    return false;
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

// -------------------------
// Administration / Users
// -------------------------
function ensure_admin_tables()
{
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    // users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password_hash TEXT,
        role TEXT,
        created_at INTEGER
    )");
    // bans table
    $db->exec("CREATE TABLE IF NOT EXISTS banned_ips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT UNIQUE,
        reason TEXT,
        banned_by INTEGER,
        created_at INTEGER
    )");
    // roles/permissions tables for modular permissions
    $db->exec("CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        created_at INTEGER
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        created_at INTEGER
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS role_permissions (
        role_id INTEGER,
        permission_id INTEGER,
        PRIMARY KEY (role_id, permission_id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS user_roles (
        user_id INTEGER,
        role_id INTEGER,
        PRIMARY KEY (user_id, role_id)
    )");
    // admin audit log
    $db->exec("CREATE TABLE IF NOT EXISTS admin_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT,
        details TEXT,
        created_at INTEGER
    )");

    // Seed basic roles and permissions if missing
    $existing = $db->querySingle("SELECT COUNT(*) FROM roles");
    if ($existing == 0) {
        $db->exec("INSERT INTO roles (name, created_at) VALUES ('administrator', strftime('%s','now'))");
        $db->exec("INSERT INTO roles (name, created_at) VALUES ('moderator', strftime('%s','now'))");
    }
    $pexists = $db->querySingle("SELECT COUNT(*) FROM permissions");
    if ($pexists == 0) {
        $perms = ['delete_post', 'ban_ip', 'create_user', 'change_password', 'edit_config', 'view_admin', 'delete_user'];
        foreach ($perms as $p) {
            $stmt = $db->prepare("INSERT INTO permissions (name, created_at) VALUES (:n, :c)");
            $stmt->bindValue(':n', $p, SQLITE3_TEXT);
            $stmt->bindValue(':c', time(), SQLITE3_INTEGER);
            $stmt->execute();
        }
        // grant perms: administrator all, moderator limited
        $adminId = $db->querySingle("SELECT id FROM roles WHERE name='administrator'");
        $modId = $db->querySingle("SELECT id FROM roles WHERE name='moderator'");
        $res = $db->query("SELECT id, name FROM permissions");
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $pid = $r['id'];
            // grant to admin
            $db->exec("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (" . intval($adminId) . ", " . intval($pid) . ")");
            // grant subset to moderator
            if (in_array($r['name'], ['delete_post', 'ban_ip', 'view_admin'])) {
                $db->exec("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (" . intval($modId) . ", " . intval($pid) . ")");
            }
        }
    }
    $db->close();
}

function create_role($roleName)
{
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("INSERT OR IGNORE INTO roles (name, created_at) VALUES (:n, :c)");
    $stmt->bindValue(':n', $roleName, SQLITE3_TEXT);
    $stmt->bindValue(':c', time(), SQLITE3_INTEGER);
    $res = $stmt->execute();
    $db->close();
    return $res !== false;
}

function create_permission($permName)
{
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("INSERT OR IGNORE INTO permissions (name, created_at) VALUES (:n, :c)");
    $stmt->bindValue(':n', $permName, SQLITE3_TEXT);
    $stmt->bindValue(':c', time(), SQLITE3_INTEGER);
    $res = $stmt->execute();
    $db->close();
    return $res !== false;
}

function grant_permission_to_role($roleName, $permName)
{
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("SELECT id FROM roles WHERE name = :n");
    $stmt->bindValue(':n', $roleName, SQLITE3_TEXT);
    $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$r) {
        $db->close();
        return false;
    }
    $roleId = $r['id'];
    $stmt2 = $db->prepare("SELECT id FROM permissions WHERE name = :n");
    $stmt2->bindValue(':n', $permName, SQLITE3_TEXT);
    $p = $stmt2->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$p) {
        $db->close();
        return false;
    }
    $permId = $p['id'];
    $db->exec("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (" . intval($roleId) . ", " . intval($permId) . ")");
    $db->close();
    return true;
}

function assign_role_to_user($userId, $roleName)
{
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("SELECT id FROM roles WHERE name = :n");
    $stmt->bindValue(':n', $roleName, SQLITE3_TEXT);
    $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$r) {
        $db->close();
        return false;
    }
    $roleId = $r['id'];
    $db->exec("INSERT OR IGNORE INTO user_roles (user_id, role_id) VALUES (" . intval($userId) . ", " . intval($roleId) . ")");
    $db->close();
    return true;
}

function user_has_permission($userId, $permission)
{
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM user_roles ur JOIN role_permissions rp ON ur.role_id=rp.role_id JOIN permissions p ON rp.permission_id=p.id WHERE ur.user_id = :uid AND p.name = :perm");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':perm', $permission, SQLITE3_TEXT);
    $res = $stmt->execute();
    $cnt = 0;
    if ($res) {
        $r = $res->fetchArray(SQLITE3_ASSOC);
        $cnt = (int)$r['cnt'];
    }
    $db->close();
    return $cnt > 0;
}

function log_admin_action($userId, $action, $details = '')
{
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("INSERT INTO admin_audit (user_id, action, details, created_at) VALUES (:uid, :act, :det, :c)");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':act', $action, SQLITE3_TEXT);
    $stmt->bindValue(':det', $details, SQLITE3_TEXT);
    $stmt->bindValue(':c', time(), SQLITE3_INTEGER);
    $res = $stmt->execute();
    $db->close();
    return $res !== false;
}

function create_user($username, $password, $role = 'moderator')
{
    ensure_admin_tables();
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, created_at) VALUES (:u, :ph, :r, :c)");
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $stmt->bindValue(':ph', $hash, SQLITE3_TEXT);
    $stmt->bindValue(':r', $role, SQLITE3_TEXT);
    $stmt->bindValue(':c', time(), SQLITE3_INTEGER);
    $res = $stmt->execute();
    if ($res) {
        $userId = $db->lastInsertRowID();
        // assign role in user_roles
        assign_role_to_user($userId, $role);
    }
    $db->close();
    return $res !== false;
}

function get_user_by_username($username)
{
    ensure_admin_tables();
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("SELECT id, username, password_hash, role, created_at FROM users WHERE username = :u LIMIT 1");
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
    $db->close();
    return $row ?: null;
}

function verify_user_credentials($username, $password)
{
    $user = get_user_by_username($username);
    if (!$user) return false;
    if (password_verify($password, $user['password_hash'])) {
        // Optionally rehash
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            // update hash
            $config = include __DIR__ . '/../config.php';
            $db = new SQLite3($config['postdb']);
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $u = $db->prepare("UPDATE users SET password_hash = :ph WHERE id = :id");
            $u->bindValue(':ph', $newHash, SQLITE3_TEXT);
            $u->bindValue(':id', $user['id'], SQLITE3_INTEGER);
            $u->execute();
            $db->close();
        }
        return $user;
    }
    return false;
}

function login_user_session($user)
{
    ensure_session_started();
    // Regenerate session id on login
    session_regenerate_id(true);
    $_SESSION['admin_user'] = ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']];
}

function logout_user_session()
{
    ensure_session_started();
    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}

function current_admin_user()
{
    ensure_session_started();
    return isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : null;
}

function require_admin_permission($permission)
{
    $user = current_admin_user();
    if (!$user) return false;
    // Use DB-driven permission checks
    $uid = $user['id'] ?? null;
    if (!$uid) return false;
    return user_has_permission($uid, $permission);
}

function ban_ip($ip, $reason = '', $banned_by = null)
{
    ensure_admin_tables();
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("INSERT OR REPLACE INTO banned_ips (ip, reason, banned_by, created_at) VALUES (:ip, :reason, :by, :c)");
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':reason', $reason, SQLITE3_TEXT);
    $stmt->bindValue(':by', $banned_by, SQLITE3_INTEGER);
    $stmt->bindValue(':c', time(), SQLITE3_INTEGER);
    $res = $stmt->execute();
    $db->close();
    return $res !== false;
}

function delete_user_by_id($userId)
{
    ensure_admin_tables();
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $res1 = $stmt->execute();
    // remove from user_roles as well
    $stmt2 = $db->prepare("DELETE FROM user_roles WHERE user_id = :id");
    $stmt2->bindValue(':id', $userId, SQLITE3_INTEGER);
    $res2 = $stmt2->execute();
    $db->close();
    return ($res1 !== false);
}

function is_ip_banned($ip)
{
    ensure_admin_tables();
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM banned_ips WHERE ip = :ip");
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $res = $stmt->execute();
    $cnt = 0;
    if ($res) {
        $r = $res->fetchArray(SQLITE3_ASSOC);
        $cnt = (int)$r['cnt'];
    }
    $db->close();
    return $cnt > 0;
}

function delete_post_by_board_and_id($board, $id)
{
    $config = include __DIR__ . '/../config.php';
    try {
        $db = new SQLite3($config['postdb']);
    } catch (Exception $e) {
        return false;
    }
    $table = boardTableName($board);
    // ensure table exists
    $db->exec("CREATE TABLE IF NOT EXISTS " . $table . " (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        post TEXT,
        ipaddress TEXT,
        isOP INTEGER,
        threadNumber INTEGER,
        created_at INTEGER,
        bumped_at INTEGER
    )");
    $stmt = $db->prepare("DELETE FROM " . $table . " WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $db->close();
    return $res !== false;
}

function list_admin_users()
{
    ensure_admin_tables();
    $config = include __DIR__ . '/../config.php';
    $db = new SQLite3($config['postdb']);
    $res = $db->query("SELECT u.id, u.username, u.role, u.created_at, GROUP_CONCAT(r.name, ',') as roles FROM users u LEFT JOIN user_roles ur ON u.id=ur.user_id LEFT JOIN roles r ON ur.role_id=r.id GROUP BY u.id ORDER BY u.id ASC");
    $out = [];
    if ($res) {
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            // Normalize roles: fallback to users.role if roles is empty
            if (empty($r['roles']) && !empty($r['role'])) {
                $r['roles'] = $r['role'];
            }
            $out[] = $r;
        }
    }
    $db->close();
    return $out;
}
