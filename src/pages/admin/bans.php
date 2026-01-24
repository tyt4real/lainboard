<?php
require_once __DIR__ . '/../../templates/layout.php';
requirePermission('ban_users');

$staff = getCurrentStaff();
$pdo = getDB();

$bans = $pdo->query("
    SELECT b.*, s.username as staff_name, bd.uri as board_uri
    FROM bans b
    LEFT JOIN staff s ON b.staff_id = s.id
    LEFT JOIN boards bd ON b.board_id = bd.id
    WHERE b.expires_at IS NULL OR b.expires_at > NOW()
    ORDER BY b.created_at DESC
")->fetchAll();

renderHeader('Bans');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> - Bans
    </div>
    <div>
        <a href="/">Home</a>
        <a href="/admin">Dashboard</a>
        <a href="/admin/reports">Reports</a>
        <a href="/admin/logs">Logs</a>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">Active Bans (<?= count($bans) ?>)</h2>

<?php if (empty($bans)): ?>
<p style="text-align: center;">No active bans.</p>
<?php else: ?>
<table class="admin-table">
    <tr>
        <th>ID</th>
        <th>IP</th>
        <th>Scope</th>
        <th>Reason</th>
        <th>Expires</th>
        <th>By</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($bans as $ban): ?>
    <tr>
        <td><?= $ban['id'] ?></td>
        <td><?= htmlspecialchars($ban['ip_address']) ?></td>
        <td><?= $ban['is_global'] ? 'Global' : ($ban['board_uri'] ? '/' . htmlspecialchars($ban['board_uri']) . '/' : 'Unknown') ?></td>
        <td><?= htmlspecialchars($ban['reason']) ?></td>
        <td><?= $ban['expires_at'] ? date('m/d/y H:i', strtotime($ban['expires_at'])) : 'Never' ?></td>
        <td><?= htmlspecialchars($ban['staff_name'] ?? 'Unknown') ?></td>
        <td>
            <a href="/admin/mod/unban?ban=<?= $ban['id'] ?>&csrf=<?= generateCSRFToken() ?>" class="btn" onclick="return confirm('Lift this ban?')">Lift Ban</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php renderFooter(); ?>
