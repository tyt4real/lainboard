<?php
require_once __DIR__ . '/../../templates/layout.php';
requirePermission('restore_posts');

$staff = getCurrentStaff();
$pdo = getDB();

$page = intval($_GET['page'] ?? 1);
$perPage = 50;
$offset = ($page - 1) * $perPage;

$totalHidden = $pdo->query("SELECT COUNT(*) FROM posts WHERE is_deleted = TRUE")->fetchColumn();
$totalPages = ceil($totalHidden / $perPage);

$hiddenPosts = $pdo->prepare("
    SELECT p.*, b.uri as board_uri, b.title as board_title,
           s.username as hidden_by_username,
           COALESCE(s2.username, 'Unknown') as hidden_by_name
    FROM posts p
    JOIN boards b ON p.board_id = b.id
    LEFT JOIN staff s ON p.deleted_by = s.id
    LEFT JOIN staff s2 ON p.deleted_by = s2.id
    WHERE p.is_deleted = TRUE
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$hiddenPosts->execute([$perPage, $offset]);
$hiddenPosts = $hiddenPosts->fetchAll();

renderHeader('Hidden Posts');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> - Hidden Posts
    </div>
    <div>
        <a href="/">Home</a>
        <a href="/admin">Dashboard</a>
        <a href="/admin/reports">Reports</a>
        <a href="/admin/bans">Bans</a>
        <a href="/admin/logs">Logs</a>
        <a href="/admin/hidden">Hidden</a>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">Hidden Posts Management (<?= $totalHidden ?> total)</h2>

<?php if (empty($hiddenPosts)): ?>
<p style="text-align: center;">No hidden posts found.</p>
<?php else: ?>
<div style="margin-bottom: 20px; text-align: center;">
    Page <?= $page ?> of <?= $totalPages ?>
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>">&laquo; Previous</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
    <?php endif; ?>
</div>

<table class="admin-table">
    <tr>
        <th>ID</th>
        <th>Board</th>
        <th>Post</th>
        <th>Hidden By</th>
        <th>Hidden At</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($hiddenPosts as $post): ?>
    <tr>
        <td><?= $post['id'] ?></td>
        <td><a href="/<?= $post['board_uri'] ?>/"><?= htmlspecialchars($post['board_title']) ?></a></td>
        <td>
            <div style="max-width: 400px; overflow: hidden; text-overflow: ellipsis;">
                <strong><?= htmlspecialchars($post['name']) ?>:</strong>
                <?= htmlspecialchars(substr($post['comment'], 0, 150)) ?>
                <?php if (strlen($post['comment']) > 150): ?>...<?php endif; ?>
            </div>
            <?php if ($post['file_path']): ?>
            <small>[File: <?= htmlspecialchars($post['file_name']) ?>]</small>
            <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($post['hidden_by_name']) ?></td>
        <td><?= date('m/d/y H:i', strtotime($post['created_at'])) ?></td>
        <td>
            <a href="/admin/mod/restore?post=<?= $post['id'] ?>&csrf=<?= generateCSRFToken() ?>"
               onclick="return confirm('Restore this post?')"
               style="color: green; margin-right: 10px;">[Restore]</a>
            <a href="/admin/mod/hard_delete?post=<?= $post['id'] ?>&csrf=<?= generateCSRFToken() ?>"
               onclick="return confirm('Permanently delete this post? This cannot be undone!')"
               style="color: red; margin-right: 10px;">[Delete]</a>
            <a href="/admin/mod/ban?post=<?= $post['id'] ?>"
               style="color: orange;">[Ban User]</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<div style="margin-top: 20px; text-align: center;">
    Page <?= $page ?> of <?= $totalPages ?>
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>">&laquo; Previous</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
