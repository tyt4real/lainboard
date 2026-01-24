<?php
require_once __DIR__ . '/../templates/layout.php';

$boardUri = $_GET['board'] ?? '';
$board = getBoard($boardUri);

if (!$board) {
    http_response_code(404);
    echo 'Board not found';
    exit;
}

$page = max(1, intval($_GET['page'] ?? 1));
$threads = getThreads($board['id'], $page);
$error = $_SESSION['post_error'] ?? null;
$success = $_SESSION['post_success'] ?? null;
unset($_SESSION['post_error'], $_SESSION['post_success']);

$staff = getCurrentStaff();

renderHeader('/' . $board['uri'] . '/ - ' . $board['title']);
?>

<h2 class="text-2xl font-bold text-center text-[var(--font-color)] mb-1">
    /<?= htmlspecialchars($board['uri']) ?>/ - <?= htmlspecialchars($board['title']) ?>
</h2>

<?php if ($board['subtitle']): ?>
    <p class="text-center text-[color:var(--subordinate-header)] mb-2">
        <?= htmlspecialchars($board['subtitle']) ?>
    </p>
<?php endif; ?>

<div class="text-center mb-6">
    <button id="toggleFormBtn"
        class="bg-[var(--link-color)] text-[var(--post-bg)] px-6 py-2 rounded hover:bg-[var(--link-hover)] transition-colors shadow">
        New Thread
    </button>
</div>

<!-- No-JS fallback: show form automatically when JavaScript is disabled -->
<noscript>
<div class="text-center mb-6">
    <h3 class="text-lg font-semibold text-[var(--font-color)]">New Thread</h3>
</div>
<div class="flex justify-center">
    <div id="noscriptFormContainer" class="w-full max-w-3xl post-form bg-[var(--post-bg)] border border-[var(--post-border)] rounded-md p-6 shadow-[0_0_10px_rgba(125,192,212,0.3)] mb-8">
        <form action="/<?= htmlspecialchars($board['uri']) ?>/post" method="POST" enctype="multipart/form-data"
            class="flex flex-col gap-4">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <?php if ($error): ?>
                <div class="bg-red-900/10 text-[var(--error-color)] p-2 rounded text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-900/10 text-[var(--success-color)] p-2 rounded text-center">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)]">Name</label>
                <input class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                    type="text" name="name" placeholder="Anonymous" maxlength="<?= MAX_NAME_LENGTH ?>">
            </div>

            <div class="flex flex-col md:flex-row gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)]">Subject</label>
                <div class="flex flex-1 gap-2">
                    <input class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                        type="text" name="subject" maxlength="<?= MAX_SUBJECT_LENGTH ?>">
                    <input class="bg-[var(--link-color)] text-[var(--post-bg)] px-4 py-2 rounded cursor-pointer hover:bg-[var(--link-hover)]"
                        type="submit" value="Post">
                </div>
            </div>

            <div class="flex flex-col md:flex-row gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)] pt-2">Comment</label>
                <textarea class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                    name="comment" required maxlength="<?= MAX_COMMENT_LENGTH ?>"></textarea>
            </div>

            <?php if ($staff): ?>
                <div class="flex gap-4">
                    <label class="w-24 text-right font-semibold text-[var(--font-color)]">Options</label>
                    <label class="text-[var(--font-color)]">
                        <input type="checkbox" name="use_capcode">
                        Use capcode (<?= htmlspecialchars($staff['role']) ?>)
                    </label>
                </div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)]">File</label>
                <input class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                    type="file" name="file" accept="image/*">
            </div>

            <div class="flex gap-4 items-center">
                <label class="w-24"></label>
                <img src="/captcha?<?= time() ?>" class="h-10">
                <span class="text-[var(--link-color)] text-sm">Reload page to refresh captcha</span>
            </div>

            <div class="flex gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)]">Captcha</label>
                <input class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                    type="text" name="captcha" required maxlength="6">
            </div>
        </form>
    </div>
</div>
</noscript>

<div class="flex justify-center">
    <div id="postFormContainer"
        class="hidden w-full max-w-3xl post-form bg-[var(--post-bg)] border border-[var(--post-border)] rounded-md p-6 shadow-[0_0_10px_rgba(125,192,212,0.3)] mb-8">

        <form action="/<?= htmlspecialchars($board['uri']) ?>/post" method="POST" enctype="multipart/form-data"
            class="flex flex-col gap-4">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <?php if ($error): ?>
                <div class="bg-red-900/10 text-[var(--error-color)] p-2 rounded text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-900/10 text-[var(--success-color)] p-2 rounded text-center">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)]">Name</label>
                <input class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                    type="text" name="name" placeholder="Anonymous" maxlength="<?= MAX_NAME_LENGTH ?>">
            </div>

            <div class="flex flex-col md:flex-row gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)]">Subject</label>
                <div class="flex flex-1 gap-2">
                    <input class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                        type="text" name="subject" maxlength="<?= MAX_SUBJECT_LENGTH ?>">
                    <input class="bg-[var(--link-color)] text-[var(--post-bg)] px-4 py-2 rounded cursor-pointer hover:bg-[var(--link-hover)]"
                        type="submit" value="Post">
                </div>
            </div>

            <div class="flex flex-col md:flex-row gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)] pt-2">Comment</label>
                <textarea class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                    name="comment" required maxlength="<?= MAX_COMMENT_LENGTH ?>"></textarea>
            </div>

            <?php if ($staff): ?>
                <div class="flex gap-4">
                    <label class="w-24 text-right font-semibold text-[var(--font-color)]">Options</label>
                    <label class="text-[var(--font-color)]">
                        <input type="checkbox" name="use_capcode">
                        Use capcode (<?= htmlspecialchars($staff['role']) ?>)
                    </label>
                </div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)]">File</label>
                <input class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                    type="file" name="file" accept="image/*">
            </div>

            <div class="flex gap-4 items-center">
                <label class="w-24"></label>
                <img src="/captcha?<?= time() ?>" id="captcha-img" class="h-10">
                <a class="text-[var(--link-color)]"
                    href="#" onclick="document.getElementById('captcha-img').src='/captcha?'+Date.now(); return false;">
                    Reload
                </a>
            </div>

            <div class="flex gap-4">
                <label class="w-24 text-right font-semibold text-[var(--font-color)]">Captcha</label>
                <input class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                    type="text" name="captcha" required maxlength="6">
            </div>
        </form>
    </div>
</div>

<script>
const btn = document.getElementById('toggleFormBtn');
const form = document.getElementById('postFormContainer');

btn?.addEventListener('click', () => {
    form.classList.toggle('hidden');
    form.scrollIntoView({ behavior: 'smooth' });
});
</script>

<hr>

<?php if (empty($threads)): ?>
<p style="text-align: center;">No threads on this board. Be the first to post!</p>
<?php else: ?>
    <?php foreach ($threads as $thread): ?>
    <div class="thread">
        <?php renderPost($thread, $board['uri'], true); ?>
        
        <div class="thread-info">
            <?= $thread['reply_count'] ?> replies
            <?php if ($thread['file_path']): ?> | 1 image<?php endif; ?>
        </div>
        
        <?php 
        $replies = getLatestReplies($thread['id'], REPLIES_SHOWN);
        $omitted = $thread['reply_count'] - count($replies);
        ?>
        
        <?php if ($omitted > 0): ?>
        <div class="thread-info">
            <?= $omitted ?> posts omitted. <a href="/<?= $board['uri'] ?>/thread/<?= $thread['id'] ?>">Click here</a> to view.
        </div>
        <?php endif; ?>
        
        <div class="replies-container">
            <?php foreach ($replies as $reply): ?>
                <?php renderPost($reply, $board['uri'], false); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<div style="text-align: center; margin: 20px;">
    <?php if ($page > 1): ?>
    <a href="/<?= $board['uri'] ?>?page=<?= $page - 1 ?>">[Previous]</a>
    <?php endif; ?>
    Page <?= $page ?>
    <?php if (count($threads) >= THREADS_PER_PAGE): ?>
    <a href="/<?= $board['uri'] ?>?page=<?= $page + 1 ?>">[Next]</a>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
