<?php
require_once __DIR__ . '/../templates/layout.php';

$boards = getBoards();
$boardsByTag = getBoardsByTag();
$popularThreads = getPopularThreads(getPopularThreadsCount());


renderHeader();

function renderPostCount()
{
    $totalPosts = getTotalPosts();
    $digitCount = getPostCountDigits();
    $padded = str_pad((string)$totalPosts, max(1, $digitCount), '0', STR_PAD_LEFT);
    $digits = str_split($padded);
    $html = '<div class="post-counter flex justify-center gap-2" aria-label="Total posts: ' . htmlspecialchars((string)$totalPosts) . '">';
    foreach ($digits as $d) {
        if (!preg_match('/^[0-9]$/', $d)) $d = '0';
        $src = '/static/images/counter/' . $d . '.gif';
        $html .= '<img class="post-counter-digit p-2" src="' . htmlspecialchars($src) . '" alt="' . $d . '" />';
    }
    $html .= '</div>';
    echo $html;
}
?>

<div class="section text-center justify-center">
    <div class="section-content p-6 rounded-md border border-white/20 
    shadow-[0_0_10px_rgba(125,192,212,0.3)]">
        <div class="text-2xl font-bold bg-gradient-to-r from-red-400 via-yellow-300 via-green-400 via-blue-400 to-purple-500 bg-clip-text text-transparent" style="">Welcome to <?= SITE_NAME ?>! ; You know where you are.</div>
    </div>
</div>

<div class="section rounded-[5px]">
    <div class="section-header">Popular threads</div>
    <div class="section-content">
        <?php if (empty($popularThreads)): ?>
            <p>No threads yet. Be the first to post!</p>
        <?php else: ?>
            <table class="thread-list">
                <tr>
                    <th>Thread</th>
                    <th>Replies</th>
                </tr>
                <?php foreach ($popularThreads as $thread): ?>
                    <tr>
                        <td>
                            <a href="/<?= htmlspecialchars($thread['board_uri']) ?>/thread/<?= $thread['id'] ?>">
                                <?= htmlspecialchars($thread['subject'] ?: substr(strip_tags($thread['comment']), 0, 50)) ?>...
                            </a>
                            <small>(/<?= htmlspecialchars($thread['board_uri']) ?>/)</small>
                        </td>
                        <td><?= $thread['reply_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($boardsByTag as $tag => $tagBoards): ?>
<div class="section rounded-[5px] mb-6">
    <div class="section-header text-center text-[var(--font-color)] font-bold text-lg mb-4">
        <?= htmlspecialchars($tag) ?> Boards
    </div>
<div class="flex flex-wrap gap-4 justify-center">
        <?php foreach ($tagBoards as $board): ?>
        <a href="/<?= htmlspecialchars($board['uri']) ?>">
            <div class="px-6 py-4 rounded-[5px] border border-gray-200
              text-sm font-medium text-stone-50 whitespace-nowrap
              transition-all duration-200
              hover:ring-2 hover:ring-indigo-500 hover:shadow-lg hover:shadow-indigo-500/40">
                <strong>/<?= htmlspecialchars($board['uri']) ?>/
                    - <?= htmlspecialchars($board['title']) ?></strong>
            </div>
        </a>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<div class="section rounded-[5px]">
    <div class="section-header text-center text-[var(--font-color)] font-bold text-lg mb-4">
        Community
    </div>
    <div class="flex flex-wrap gap-4 justify-center">
    <a onclick="webring.openWindow(0, 0);">
                <div class="px-6 py-4 rounded-[5px] border border-gray-200
              text-sm font-medium text-stone-50 whitespace-nowrap
              transition-all duration-200
              hover:ring-2 hover:ring-indigo-500 hover:shadow-lg hover:shadow-indigo-500/40">
                <strong>/webring/</strong>
            </div>
    </a>
    </div>
</div>
<div class="section rounded-[5px]">
    <div class="section-header text-center text-[var(--font-color)] font-bold text-lg mb-4">
        Fren sites
    </div>
    <div class="flex flex-wrap gap-4 justify-center">
    <a href="https://rain.meth.cat/">
            <div>
                <img src="https://rain.meth.cat/banners/rainchan.gif" alt="Rainchan" style="margin-bottom: 10px;">
            </div>
    </a>
    </div>
</div>

<div class="stats-row">
    <span style="font-size: 48px;"><?= renderPostCount() ?></span>
</div>

<?php renderFooter(); ?>