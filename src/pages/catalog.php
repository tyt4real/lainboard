<?php
require_once __DIR__ . '/../templates/layout.php';

$boardUri = $_GET['board'] ?? '';
$board = getBoard($boardUri);

if (!$board) {
    http_response_code(404);
    echo 'Board not found';
    exit;
}

$threads = getAllThreads($board['id']);

renderHeader('/' . $board['uri'] . '/catalog - ' . $board['title']);
?>

<h2 class="text-2xl font-bold text-center text-[var(--font-color)] mb-1">
    /<?= htmlspecialchars($board['uri']) ?>/ - <?= htmlspecialchars($board['title']) ?> Catalog
</h2>

<?php if ($board['subtitle']): ?>
    <p class="text-center text-[color:var(--subordinate-header)] mb-6">
        <?= htmlspecialchars($board['subtitle']) ?>
    </p>
<?php endif; ?>

<div class="text-center mb-6">
    <a href="/<?= htmlspecialchars($board['uri']) ?>" class="bg-[var(--link-color)] text-[var(--post-bg)] px-4 py-2 rounded hover:bg-[var(--link-hover)] transition-colors">
        Back to Board
    </a>
</div>

<?php if (empty($threads)): ?>
    <p class="text-center text-[var(--font-color)]">No threads yet.</p>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4 px-4">
<?php foreach ($threads as $thread): ?>
    <?php
    $imageCount = getThreadImageCount($thread['id']);
    $hasImage = !empty($thread['file_path']);
    ?>
    <div class="bg-[var(--post-bg)] border border-[var(--post-border)] rounded-md overflow-hidden hover:border-[var(--link-color)] transition-colors">
        <a href="/<?= $board['uri'] ?>/thread/<?= $thread['id'] ?>" class="block">
            <?php if ($hasImage): ?>
                <div class="aspect-square overflow-hidden bg-[var(--reply-bg)] flex items-center justify-center">
                    <img src="<?= htmlspecialchars($thread['thumb_path']) ?>"
                         alt="<?= htmlspecialchars($thread['file_name']) ?>"
                         class="w-full h-full object-cover">
                </div>
            <?php else: ?>
                <div class="aspect-square bg-[var(--reply-bg)] flex items-center justify-center text-[var(--subordinate-header)]">
                    <div class="text-center">
                        <div class="text-4xl mb-2">📄</div>
                        <div class="text-sm">No image</div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="p-3">
                <?php if ($thread['subject']): ?>
                    <h3 class="text-[var(--font-color)] font-semibold text-sm mb-2 line-clamp-2 leading-tight">
                        <?= htmlspecialchars($thread['subject']) ?>
                    </h3>
                <?php endif; ?>

                <div class="text-xs text-[var(--subordinate-header)]">
                    R: <?= $thread['reply_count'] ?> / I: <?= $imageCount ?>
                </div>

                <?php if ($thread['is_sticky']): ?>
                    <div class="text-xs text-[var(--link-color)] mt-1">📌 Sticky</div>
                <?php endif; ?>
                <?php if ($thread['is_locked']): ?>
                    <div class="text-xs text-red-400 mt-1">🔒 Locked</div>
                <?php endif; ?>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php renderFooter(); ?>

