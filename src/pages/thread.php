<?php
require_once __DIR__ . '/../templates/layout.php';

$boardUri = $_GET['board'] ?? '';
$threadId = intval($_GET['thread'] ?? 0);

$board = getBoard($boardUri);
if (!$board) {
    http_response_code(404);
    echo 'Board not found';
    exit;
}

$thread = getThread($threadId);
if (!$thread || $thread['board_id'] !== $board['id']) {
    http_response_code(404);
    echo 'Thread not found';
    exit;
}

$replies = getReplies($threadId);
$error = $_SESSION['post_error'] ?? null;
$success = $_SESSION['post_success'] ?? null;
unset($_SESSION['post_error'], $_SESSION['post_success']);

$staff = getCurrentStaff();

$pageTitle = $thread['subject'] ?: 'Thread #' . $thread['id'];
renderHeader($pageTitle . ' - /' . $board['uri'] . '/');
?>

<div class="text-center mb-6">
    <div class="inline-flex gap-4 text-[var(--font-color)]">
        <a class="text-[var(--link-color)] hover:text-[var(--link-hover)]" href="/<?= htmlspecialchars($board['uri']) ?>">[Return]</a>
        <a class="text-[var(--link-color)] hover:text-[var(--link-hover)]" href="#bottom">[Bottom]</a>
    </div>
</div>

<?php if ($board['is_closed']): ?>
<div class="bg-red-900/20 text-red-400 p-4 rounded-lg text-center mb-6 border border-red-800/30">
    <div class="text-lg font-semibold mb-2">🚫 Board Closed</div>
    <div>This board is currently closed and not accepting new posts.</div>
</div>
<?php elseif (!$thread['is_locked']): ?>
<div class="flex justify-center mb-6">
    <div class="w-full max-w-3xl post-form bg-[var(--post-bg)] border border-[var(--post-border)] rounded-md p-6 shadow-[0_0_10px_rgba(125,192,212,0.3)]">
        <form action="/<?= htmlspecialchars($board['uri']) ?>/reply" method="POST" enctype="multipart/form-data"
            class="flex flex-col gap-4">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="thread_id" value="<?= $thread['id'] ?>">

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
                <label class="w-24 text-right font-semibold text-[var(--font-color)] pt-2">Comment</label>
                <div class="flex flex-1 gap-2">
                    <textarea class="flex-1 bg-[var(--reply-bg)] border border-[var(--post-border)] p-2 rounded"
                        name="comment" required maxlength="<?= MAX_COMMENT_LENGTH ?>"></textarea>
                    <input class="bg-[var(--link-color)] text-[var(--post-bg)] px-4 py-2 rounded cursor-pointer hover:bg-[var(--link-hover)]"
                        type="submit" value="Reply">
                </div>
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
<?php else: ?>
<div class="bg-red-900/10 text-[var(--error-color)] p-4 rounded text-center mb-6">
    This thread is locked. You cannot reply.
</div>
<?php endif; ?>

<hr class="my-8 border-white/20">

<div class="thread w-full clear-both bg-[var(--post-bg)] border border-[var(--post-border)] rounded-md p-4 shadow">
    <?php renderPost($thread, $board['uri'], true); ?>
    <div class="clear-both"></div>

    <div class="text-sm text-[var(--subordinate-header)] mt-2">
        <?= count($replies) ?> replies
    </div>

    <div class="replies-container flex flex-col gap-3 pl-4 border-l-2 border-[var(--post-border)] mt-3">
        <?php foreach ($replies as $reply): ?>
            <?php renderPost($reply, $board['uri'], false); ?>
            <div class="clear-both"></div>
        <?php endforeach; ?>
    </div>
</div>

<div id="bottom" class="text-center my-6">
    <div class="inline-flex gap-4 text-[var(--font-color)]">
        <a class="text-[var(--link-color)] hover:text-[var(--link-hover)]" href="/<?= htmlspecialchars($board['uri']) ?>">[Return]</a>
        <a class="text-[var(--link-color)] hover:text-[var(--link-hover)]" href="#top">[Top]</a>
    </div>
</div>

<?php renderFooter(); ?>
