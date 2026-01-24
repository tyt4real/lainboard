<?php
require_once __DIR__ . '/../../templates/layout.php';
requirePermission('view_logs');

$pdo = getDB();

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$logs = $pdo->prepare("
    SELECT ml.*, s.username 
    FROM mod_logs ml 
    LEFT JOIN staff s ON ml.staff_id = s.id 
    ORDER BY ml.created_at DESC
    LIMIT ? OFFSET ?
");
$logs->execute([$perPage, $offset]);
$logs = $logs->fetchAll();

renderHeader('Moderation Logs');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> - Logs
    </div>
    <div>
        <a href="/">Home</a>
        <a href="/admin">Dashboard</a>
        <a href="/admin/reports">Reports</a>
        <a href="/admin/bans">Bans</a>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">Moderation Logs</h2>

<table class="admin-table">
    <tr>
        <th>ID</th>
        <th>Time</th>
        <th>Staff</th>
        <th>Action</th>
        <th>Target</th>
        <th>Details</th>
        <th>IP</th>
    </tr>
    <?php foreach ($logs as $log): ?>
    <tr>
        <td><?= $log['id'] ?></td>
        <td><?= date('m/d/y H:i:s', strtotime($log['created_at'])) ?></td>
        <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
        <td><?= htmlspecialchars($log['action']) ?></td>
        <td><?= htmlspecialchars($log['target_type']) ?> #<?= $log['target_id'] ?></td>
        <td><?= htmlspecialchars(substr($log['details'] ?? '', 0, 100)) ?></td>
        <td><?= htmlspecialchars($log['ip_address']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<div style="text-align: center; margin: 20px;">
    <?php if ($page > 1): ?>
    <a href="/admin/logs?page=<?= $page - 1 ?>">[Previous]</a>
    <?php endif; ?>
    Page <?= $page ?>
    <?php if (count($logs) >= $perPage): ?>
    <a href="/admin/logs?page=<?= $page + 1 ?>">[Next]</a>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
