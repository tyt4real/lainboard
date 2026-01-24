<?php
require_once __DIR__ . '/../../templates/layout.php';

$staff = getCurrentStaff();
$pdo = getDB();

$pendingReports = $pdo->query("SELECT COUNT(*) FROM reports WHERE resolved_at IS NULL")->fetchColumn();
$activeBans = $pdo->query("SELECT COUNT(*) FROM bans WHERE expires_at IS NULL OR expires_at > NOW()")->fetchColumn();
$totalPosts = $pdo->query("SELECT COUNT(*) FROM posts WHERE is_deleted = FALSE")->fetchColumn();
$hiddenPosts = $pdo->query("SELECT COUNT(*) FROM posts WHERE is_deleted = TRUE")->fetchColumn();
$recentLogs = $pdo->query("SELECT COUNT(*) FROM mod_logs WHERE created_at > NOW() - INTERVAL '24 hours'")->fetchColumn();

renderHeader('Admin Dashboard');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> - 
        Logged in as: <?= htmlspecialchars($staff['username']) ?> (<?= htmlspecialchars($staff['role']) ?>)
    </div>
    <div>
        <a href="/">Home</a>
        <a href="/admin/reports">Reports</a>
        <a href="/admin/bans">Bans</a>
        <a href="/admin/users">Users</a>
        <a href="/admin/announcements">Announcements</a>
        <a href="/admin/boards">Boards</a>
        <a href="/admin/settings">Settings</a>
        <a href="/admin/logs">Logs</a>
        <?php if (hasPermission($staff['role'], 'manage_boards')): ?>
        <a href="/admin/wordfilters">Word Filters</a>
        <?php endif; ?>
        <?php if (hasPermission($staff['role'], 'restore_posts')): ?>
        <a href="/admin/hidden">Hidden (<?= $hiddenPosts ?>)</a>
        <?php endif; ?>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">Dashboard</h2>

<div style="display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; margin: 20px;">
    <div class="section" style="flex: 1; min-width: 200px;">
        <div class="section-header">Pending Reports</div>
        <div class="section-content" style="text-align: center; font-size: 36px;">
            <?= $pendingReports ?>
        </div>
    </div>
    
    <div class="section" style="flex: 1; min-width: 200px;">
        <div class="section-header">Active Bans</div>
        <div class="section-content" style="text-align: center; font-size: 36px;">
            <?= $activeBans ?>
        </div>
    </div>
    
    <div class="section" style="flex: 1; min-width: 200px;">
        <div class="section-header">Total Posts</div>
        <div class="section-content" style="text-align: center; font-size: 36px;">
            <?= number_format($totalPosts) ?>
        </div>
    </div>
    
    <div class="section" style="flex: 1; min-width: 200px;">
        <div class="section-header">Actions (24h)</div>
        <div class="section-content" style="text-align: center; font-size: 36px;">
            <?= $recentLogs ?>
        </div>
    </div>

    <?php if (hasPermission($staff['role'], 'delete_posts')): ?>
    <div class="section" style="flex: 1; min-width: 200px;">
        <div class="section-header">Hidden Posts</div>
        <div class="section-content" style="text-align: center; font-size: 36px;">
            <?= $hiddenPosts ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="section">
    <div class="section-header">Quick Actions</div>
    <div class="section-content">
        <ul>
            <li><a href="/admin/reports">View Pending Reports (<?= $pendingReports ?>)</a></li>
            <li><a href="/admin/bans">Manage Bans</a></li>
            <li><a href="/admin/logs">View Moderation Logs</a></li>
        </ul>
    </div>
</div>

<?php if (hasPermission($staff['role'], 'delete_posts') && $hiddenPosts > 0): ?>
<div class="section">
    <div class="section-header">Hidden Posts Management</div>
    <div class="section-content">
        <?php
        $hiddenPostsList = $pdo->query("
            SELECT p.*, b.uri as board_uri, b.title as board_title,
                   s.username as hidden_by_username
            FROM posts p
            JOIN boards b ON p.board_id = b.id
            LEFT JOIN staff s ON p.deleted_by = s.id
            WHERE p.is_deleted = TRUE
            ORDER BY p.created_at DESC
            LIMIT 10
        ")->fetchAll();
        ?>
        <?php if ($hiddenPostsList): ?>
        <table class="admin-table">
            <tr>
                <th>Board</th>
                <th>Post</th>
                <th>Hidden By</th>
                <th>Hidden At</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($hiddenPostsList as $post): ?>
            <tr>
                <td><a href="/<?= $post['board_uri'] ?>/"><?= htmlspecialchars($post['board_title']) ?></a></td>
                <td>
                    <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                        <?= htmlspecialchars(substr($post['comment'], 0, 100)) ?>
                        <?php if (strlen($post['comment']) > 100): ?>...<?php endif; ?>
                    </div>
                    <?php if ($post['file_path']): ?>
                    <small>[File attached]</small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($post['hidden_by_username'] ?? 'Unknown') ?></td>
                <td><?= date('m/d/y H:i', strtotime($post['created_at'])) ?></td>
                <td>
                    <a href="/admin/mod/restore?post=<?= $post['id'] ?>&csrf=<?= generateCSRFToken() ?>" onclick="return confirm('Restore this post?')" style="color: green;">[R]</a>
                    <a href="/admin/mod/hard_delete?post=<?= $post['id'] ?>&csrf=<?= generateCSRFToken() ?>" onclick="return confirm('Permanently delete this post?')" style="color: red;">[X]</a>
                    <a href="/admin/mod/ban?post=<?= $post['id'] ?>" style="color: orange;">[B]</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if ($hiddenPosts > 10): ?>
        <p><em>Showing 10 most recent. <a href="/admin/hidden">View all hidden posts</a></em></p>
        <?php endif; ?>
        <?php else: ?>
        <p>No hidden posts found.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="section">
    <div class="section-header">Recent Activity</div>
    <div class="section-content">
        <?php
        $logs = $pdo->query("
            SELECT ml.*, s.username 
            FROM mod_logs ml 
            LEFT JOIN staff s ON ml.staff_id = s.id 
            ORDER BY ml.created_at DESC LIMIT 10
        ")->fetchAll();
        ?>
        <?php if ($logs): ?>
        <table class="admin-table">
            <tr>
                <th>Time</th>
                <th>Staff</th>
                <th>Action</th>
                <th>Target</th>
            </tr>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= date('m/d/y H:i', strtotime($log['created_at'])) ?></td>
                <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><?= htmlspecialchars($log['target_type']) ?> #<?= $log['target_id'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p>No recent activity.</p>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>
