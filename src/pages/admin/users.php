<?php
require_once __DIR__ . '/../../templates/layout.php';
requirePermission('manage_users');

$staff = getCurrentStaff();
$pdo = getDB();

// Handle POST requests
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token';
    } else {
        if (isset($_POST['create_user'])) {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if (empty($username) || empty($password) || empty($role)) {
                $error = 'All fields are required';
            } elseif (strlen($username) < 3 || strlen($username) > 50) {
                $error = 'Username must be between 3 and 50 characters';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long';
            } elseif (!in_array($role, ['admin', 'mod', 'dev', 'janitor'])) {
                $error = 'Invalid role';
            } else {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM staff WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already exists';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO staff (username, password_hash, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $password_hash, $role]);

                    logModAction($staff['id'], 'create_user', 'staff', $pdo->lastInsertId(), "Created user: $username ($role)");
                    $message = 'User created successfully';
                }
            }
        } elseif (isset($_POST['change_password'])) {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $new_password = $_POST['new_password'] ?? '';

            if (!$user_id || empty($new_password)) {
                $error = 'User ID and new password are required';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters long';
            } else {
                // Verify the user exists
                $stmt = $pdo->prepare("SELECT username FROM staff WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'User not found';
                } else {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE staff SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$password_hash, $user_id]);

                    logModAction($staff['id'], 'change_password', 'staff', $user_id, "Changed password for user: {$user['username']}");
                    $message = 'Password changed successfully';
                }
            }
        } elseif (isset($_POST['toggle_status'])) {
            $user_id = (int)($_POST['user_id'] ?? 0);

            if (!$user_id) {
                $error = 'User ID is required';
            } else {
                // Get current status
                $stmt = $pdo->prepare("SELECT username, is_active FROM staff WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'User not found';
                } elseif ($user_id == $staff['id']) {
                    $error = 'You cannot deactivate your own account';
                } else {
                    $new_status = $user['is_active'] ? false : true;
                $stmt = $pdo->prepare("UPDATE staff SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status ? 1 : 0, $user_id]);

                    $action = $new_status ? 'activate' : 'deactivate';
                    logModAction($staff['id'], 'toggle_user_status', 'staff', $user_id, "$action user: {$user['username']}");
                    $message = 'User ' . ($new_status ? 'activated' : 'deactivated') . ' successfully';
                }
            }
        }
    }
}

// Get all staff users
$users = $pdo->query("
    SELECT id, username, role, created_at, last_login, is_active
    FROM staff
    ORDER BY created_at DESC
")->fetchAll();

renderHeader('User Management');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> - User Management
    </div>
    <div>
        <a href="/">Home</a>
        <a href="/admin">Dashboard</a>
        <a href="/admin/reports">Reports</a>
        <a href="/admin/bans">Bans</a>
        <a href="/admin/logs">Logs</a>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">User Management</h2>

<?php if ($message): ?>
<div style="background-color: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 4px; text-align: center;">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px; text-align: center;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">
    <div class="section" style="flex: 1; min-width: 300px;">
        <div class="section-header">Create New User</div>
        <div class="section-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="create_user" value="1">

                <div style="margin-bottom: 10px;">
                    <label for="username">Username:</label><br>
                    <input type="text" id="username" name="username" required style="width: 100%; padding: 5px;">
                </div>

                <div style="margin-bottom: 10px;">
                    <label for="password">Password:</label><br>
                    <input type="password" id="password" name="password" required style="width: 100%; padding: 5px;">
                </div>

                <div style="margin-bottom: 10px;">
                    <label for="role">Role:</label><br>
                    <select id="role" name="role" required style="width: 100%; padding: 5px;">
                        <option value="janitor">Janitor</option>
                        <option value="dev">Developer</option>
                        <option value="mod">Moderator</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <button type="submit" style="width: 100%; padding: 8px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Create User
                </button>
            </form>
        </div>
    </div>

    <div class="section" style="flex: 1; min-width: 300px;">
        <div class="section-header">Change Password</div>
        <div class="section-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="change_password" value="1">

                <div style="margin-bottom: 10px;">
                    <label for="user_id">Select User:</label><br>
                    <select id="user_id" name="user_id" required style="width: 100%; padding: 5px;">
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 10px;">
                    <label for="new_password">New Password:</label><br>
                    <input type="password" id="new_password" name="new_password" required style="width: 100%; padding: 5px;">
                </div>

                <button type="submit" style="width: 100%; padding: 8px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">Staff Users (<?= count($users) ?>)</div>
    <div class="section-content">
        <?php if (empty($users)): ?>
        <p>No staff users found.</p>
        <?php else: ?>
        <table class="admin-table">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td>
                    <span style="padding: 2px 6px; border-radius: 3px; font-size: 12px;
                        <?php
                        $colors = [
                            'admin' => '#dc3545',
                            'mod' => '#6f42c1',
                            'dev' => '#007bff',
                            'janitor' => '#28a745'
                        ];
                        echo 'background-color: ' . ($colors[$user['role']] ?? '#6c757d') . '; color: white;';
                        ?>">
                        <?= htmlspecialchars($user['role']) ?>
                    </span>
                </td>
                <td>
                    <span style="color: <?= $user['is_active'] ? '#28a745' : '#dc3545' ?>; font-weight: bold;">
                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td><?= date('m/d/y H:i', strtotime($user['created_at'])) ?></td>
                <td>
                    <?php if ($user['last_login']): ?>
                        <?= date('m/d/y H:i', strtotime($user['last_login'])) ?>
                    <?php else: ?>
                        Never
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($user['id'] != $staff['id']): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="toggle_status" value="1">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" style="padding: 2px 6px; font-size: 12px; background-color: <?= $user['is_active'] ? '#dc3545' : '#28a745' ?>; color: white; border: none; border-radius: 3px; cursor: pointer;">
                            <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <em>Current User</em>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>
