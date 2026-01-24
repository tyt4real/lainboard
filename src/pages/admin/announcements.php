<?php
require_once __DIR__ . '/../../templates/layout.php';
requirePermission('manage_boards'); // Using existing permission for admin access

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
        if (isset($_POST['create_announcement'])) {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($title) || empty($content)) {
                $error = 'Title and content are required';
            } elseif (strlen($title) > 200) {
                $error = 'Title must be 200 characters or less';
            } elseif (strlen($content) > 5000) {
                $error = 'Content must be 5000 characters or less';
            } else {
                $stmt = $pdo->prepare("INSERT INTO announcements (title, content, staff_id) VALUES (?, ?, ?)");
                $stmt->execute([$title, $content, $staff['id']]);

                logModAction($staff['id'], 'create_announcement', 'announcement', $pdo->lastInsertId(), "Created announcement: $title");
                $message = 'Announcement created successfully';
            }
        } elseif (isset($_POST['toggle_announcement'])) {
            $announcement_id = (int)($_POST['announcement_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? true : false;

            if (!$announcement_id) {
                $error = 'Invalid announcement ID';
            } else {
                $stmt = $pdo->prepare("UPDATE announcements SET is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$is_active ? 1 : 0, $announcement_id]);

                logModAction($staff['id'], 'toggle_announcement', 'announcement', $announcement_id, $is_active ? 'Activated' : 'Deactivated');
                $message = 'Announcement ' . ($is_active ? 'activated' : 'deactivated') . ' successfully';
            }
        } elseif (isset($_POST['delete_announcement'])) {
            $announcement_id = (int)($_POST['announcement_id'] ?? 0);

            if (!$announcement_id) {
                $error = 'Invalid announcement ID';
            } else {
                $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->execute([$announcement_id]);

                logModAction($staff['id'], 'delete_announcement', 'announcement', $announcement_id, 'Deleted announcement');
                $message = 'Announcement deleted successfully';
            }
        }
    }
}

// Get all announcements
$announcements = $pdo->query("
    SELECT a.*, s.username as staff_name
    FROM announcements a
    LEFT JOIN staff s ON a.staff_id = s.id
    ORDER BY a.created_at DESC
")->fetchAll();

renderHeader('Announcement Management');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> - Announcement Management
    </div>
    <div>
        <a href="/">Home</a>
        <a href="/admin">Dashboard</a>
        <a href="/admin/reports">Reports</a>
        <a href="/admin/bans">Bans</a>
        <a href="/admin/users">Users</a>
        <a href="/admin/logs">Logs</a>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">Announcement Management</h2>

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
        <div class="section-header">Create New Announcement</div>
        <div class="section-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="create_announcement" value="1">

                <div style="margin-bottom: 10px;">
                    <label for="title">Title:</label><br>
                    <input type="text" id="title" name="title" required maxlength="200" style="width: 100%; padding: 5px;">
                </div>

                <div style="margin-bottom: 10px;">
                    <label for="content">Content:</label><br>
                    <textarea id="content" name="content" required maxlength="5000" rows="4" style="width: 100%; padding: 5px;"></textarea>
                </div>

                <button type="submit" style="width: 100%; padding: 8px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Create Announcement
                </button>
            </form>
        </div>
    </div>

    <div class="section" style="flex: 1; min-width: 300px;">
        <div class="section-header">Current Announcements (<?= count($announcements) ?>)</div>
        <div class="section-content">
            <?php if (empty($announcements)): ?>
            <p>No announcements yet.</p>
            <?php else: ?>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($announcements as $announcement): ?>
                <div style="border: 1px solid var(--post-border); border-radius: 4px; padding: 10px; margin-bottom: 10px; background: var(--post-bg);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">
                        <strong style="color: var(--font-color);"><?= htmlspecialchars($announcement['title']) ?></strong>
                        <div style="display: flex; gap: 5px;">
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="toggle_announcement" value="1">
                                <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $announcement['is_active'] ? '0' : '1' ?>">
                                <button type="submit" style="padding: 2px 6px; font-size: 12px; background-color: <?= $announcement['is_active'] ? '#dc3545' : '#28a745' ?>; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                    <?= $announcement['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this announcement?')">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="delete_announcement" value="1">
                                <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                <button type="submit" style="padding: 2px 6px; font-size: 12px; background-color: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <div style="color: var(--font-color); font-size: 14px; margin-bottom: 5px;">
                        <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                    </div>
                    <div style="color: var(--font-color); font-size: 12px;">
                        Created by <?= htmlspecialchars($announcement['staff_name'] ?? 'System') ?> on <?= date('m/d/y H:i', strtotime($announcement['created_at'])) ?>
                        <?php if ($announcement['updated_at'] !== $announcement['created_at']): ?>
                            (Updated: <?= date('m/d/y H:i', strtotime($announcement['updated_at'])) ?>)
                        <?php endif; ?>
                        <span style="float: right; color: <?= $announcement['is_active'] ? '#28a745' : '#dc3545' ?>; font-weight: bold;">
                            <?= $announcement['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
