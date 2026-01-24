<?php
require_once __DIR__ . '/../templates/layout.php';

$pdo = getDB();
$stmt = $pdo->query("
    SELECT p.*, b.uri as board_uri, b.title as board_title
    FROM posts p
    JOIN boards b ON p.board_id = b.id
    WHERE p.thread_id IS NULL AND p.is_deleted = FALSE
    ORDER BY p.bumped_at DESC
    LIMIT 50
");
$threads = $stmt->fetchAll();

renderHeader('Overboard');
?>

<h2 style="text-align: center;">Overboard</h2>
<p style="text-align: center; color: #666;">Recent threads from all boards</p>

<hr>

<?php if (empty($threads)): ?>
<p style="text-align: center;">No threads yet!</p>
<?php else: ?>
    <?php foreach ($threads as $thread): ?>
    <div class="thread">
        <div style="font-size: 11px; color: #666; margin-bottom: 5px;">
            <a href="/<?= htmlspecialchars($thread['board_uri']) ?>">
                /<?= htmlspecialchars($thread['board_uri']) ?>/ - <?= htmlspecialchars($thread['board_title']) ?>
            </a>
        </div>
        <?php renderPost($thread, $thread['board_uri'], true); ?>
        
        <div class="thread-info">
            <?= $thread['reply_count'] ?> replies
        </div>
        
        <?php 
        $replies = getLatestReplies($thread['id'], 3);
        ?>
        
        <?php if ($replies): ?>
        <div class="replies-container">
            <?php foreach ($replies as $reply): ?>
                <?php renderPost($reply, $thread['board_uri'], false); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php renderFooter(); ?>
