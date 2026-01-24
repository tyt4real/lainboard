<?php
require_once __DIR__ . '/../../templates/layout.php';
requirePermission('view_reports');

$staff = getCurrentStaff();
$pdo = getDB();

$reports = $pdo->query("
    SELECT r.*, p.comment as post_comment, p.name as post_name, p.id as post_id,
           p.board_id, p.thread_id, b.uri as board_uri
    FROM reports r
    JOIN posts p ON r.post_id = p.id
    JOIN boards b ON p.board_id = b.id
    WHERE r.resolved_at IS NULL
    ORDER BY r.created_at DESC
")->fetchAll();

renderHeader('Reports');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> - Reports
    </div>
    <div>
        <a href="/">Home</a>
        <a href="/admin">Dashboard</a>
        <a href="/admin/bans">Bans</a>
        <a href="/admin/logs">Logs</a>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">Pending Reports (<?= count($reports) ?>)</h2>

<?php if (empty($reports)): ?>
<p style="text-align: center;">No pending reports!</p>
<?php else: ?>
<table class="admin-table">
    <tr>
        <th>ID</th>
        <th>Post</th>
        <th>Board</th>
        <th>Reason</th>
        <th>Reported</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($reports as $report): ?>
    <tr>
        <td><?= $report['id'] ?></td>
        <td>
            <a href="/<?= htmlspecialchars($report['board_uri']) ?>/thread/<?= $report['thread_id'] ?: $report['post_id'] ?>#p<?= $report['post_id'] ?>" target="_blank">
                #<?= $report['post_id'] ?>
            </a>
            <br>
            <small><?= htmlspecialchars(substr($report['post_comment'], 0, 50)) ?>...</small>
        </td>
        <td>/<?= htmlspecialchars($report['board_uri']) ?>/</td>
        <td><?= htmlspecialchars($report['reason']) ?></td>
        <td><?= timeAgo($report['created_at']) ?></td>
        <td>
            <a href="/admin/mod/resolve?report=<?= $report['id'] ?>&action=ignore&csrf=<?= generateCSRFToken() ?>" class="btn">Dismiss</a>
            <a href="/admin/mod/delete?post=<?= $report['post_id'] ?>&report=<?= $report['id'] ?>&csrf=<?= generateCSRFToken() ?>" class="btn btn-danger" onclick="return confirm('Delete this post?')">Delete</a>
            <a href="/admin/mod/ban?post=<?= $report['post_id'] ?>&report=<?= $report['id'] ?>" class="btn btn-danger">Ban</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php renderFooter(); ?>
