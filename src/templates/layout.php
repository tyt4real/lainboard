<?php
function renderHeader($title = null)
{
    $pageTitle = $title ? $title . ' - ' . SITE_NAME : SITE_NAME;
    $staff = getCurrentStaff();

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($pageTitle) ?></title>
        <link rel="stylesheet" href="/static/css/style.css">
        <link rel="stylesheet" href="/static/css/tailwindout.css">
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <!--<link rel="stylesheet" href="/static/css/bootstrap.min.css">-->
    </head>

    <body>
        <div class="top-right">
            <?php if ($staff): ?>
                <a href="/admin">Dashboard</a> |
                <a href="/admin/logout">Logout (<?= htmlspecialchars($staff['username']) ?>)</a>
            <?php else: ?>
                <a href="/admin/login">Staff Login</a>
            <?php endif; ?>
            | <a href="#" id="settings-toggle" class="text-white/80 hover:text-[#ffd9a8] transition-colors duration-200">Settings</a>
            <?php if (isThemeSelectorEnabled()): ?>
            <div class="theme-selector">
                <select id="theme-select">
                    <option value="lainrocks">Default lainrocks</option>
                    <!--<option value="yotsuba">Yotsuba</option>
                    <option value="tea">Tea</option>-->
                    <option value="grey">Grey</option>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="header">
            <h1><a href="/" style="color: inherit; text-decoration: none;"><?= SITE_NAME ?></a></h1>
            <div class="tagline"><?= SITE_TAGLINE ?></div>
            <div class="quote"><?= SITE_QUOTE ?></div>
        </div>
        
        <nav class="flex flex-wrap items-center gap-3
    border border-white/10
    bg-neutral-900/70
    px-4 py-3
    backdrop-blur-sm
    shadow-[0_0_25px_rgba(255,255,255,0.05)">


            <?php
            $boards = getBoards();
            echo '<div class="flex items-center gap-3"><a class="text-xs sm:text-sm tracking-[0.35em] " style="--pulse-delay:0s;--pulse-duration:2.6s" href="/">[home]</a></div> | <div class="flex items-center gap-3"><a class="text-xs sm:text-sm tracking-[0.35em] " style="--pulse-delay:0s;--pulse-duration:2.6s" href="/overboard">[overboard]</a></div> | <div class="flex items-center gap-3"><a class="text-xs sm:text-sm tracking-[0.35em] " style="--pulse-delay:0s;--pulse-duration:2.6s" onClick="webring.openWindow(0, 0);">[webring]</a></div> | ';
            foreach ($boards as $i => $board) {
                echo '<div class="flex items-center gap-3">
                <a class="text-xs sm:text-sm tracking-[0.35em] " style="--pulse-delay:0s;--pulse-duration:2.6s" href="/' . htmlspecialchars($board['uri']) . '"> /' . htmlspecialchars($board['uri']) . '/ </a></div>';
                if ($i < count($boards) - 1) echo ' | ';
            }
            ?>
        </nav>
        <div class="bottom-0 left-0 right-0 h-[2px]
    bg-gradient-to-r from-transparent via-white/40 to-transparent
    opacity-70">
</div>

        <?php renderPageBanner(); ?>

        <div class="container">
        <?php
    }

function renderPageBanner()
{
    // Only show banners and announcements on board and thread pages
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $pathWithoutQuery = parse_url($currentPath, PHP_URL_PATH);

    // Check if it's a board page (e.g., /b, /a, /b/) or thread page (e.g., /b/thread/123)
    $isBoardPage = preg_match('#^/([a-zA-Z0-9]+)/?$#', $pathWithoutQuery);
    $isThreadPage = preg_match('#^/([a-zA-Z0-9]+)/thread/\d+$#', $pathWithoutQuery);

    if (!$isBoardPage && !$isThreadPage) {
        return;
    }
    $banner = getRandomBanner();
    if ($banner) {
        ?>
        <div class="page-banner">
            <img src="<?= htmlspecialchars($banner) ?>" alt="Banner" loading="lazy">
        </div>
        <?php
    }

    renderAnnouncements();
}

function renderAnnouncements()
{
    $announcements = getActiveAnnouncements();
    if (empty($announcements)) {
        return;
    }

    ?>
    <div id="announcements-container" class="announcements-container">
        <div class="announcements-header">
            <span class="announcements-title">📢 Announcements</span>
            <button id="toggle-announcements" class="announcements-toggle" title="Toggle announcements">−</button>
        </div>
        <div id="announcements-content" class="announcements-content">
            <?php foreach ($announcements as $announcement): ?>
            <div class="announcement-item">
                <div class="announcement-title">
                    <?= htmlspecialchars($announcement['title']) ?>
                </div>
                <div class="announcement-content">
                    <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                </div>
                <div class="announcement-meta">
                    Posted by <?= htmlspecialchars($announcement['staff_name'] ?? 'Admin') ?> on <?= date('m/d/y', strtotime($announcement['created_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function renderSettingsModal()
    {
        ?>
        <!-- Settings Modal -->
        <div id="settings-modal" class="fixed inset-0 bg-black/50 hidden z-50">
            <div id="settings-window" class="absolute bg-[var(--post-bg)] border border-[var(--post-border)] rounded-md shadow-2xl min-w-[400px] max-w-[600px]">
                <div class="flex items-center justify-between p-4 border-b border-[var(--post-border)] cursor-move bg-[var(--subordinate-header)] rounded-t-md" id="settings-header">
                    <h3 class="text-[var(--font-color)] font-bold">User Settings</h3>
                    <button id="settings-close" class="text-[var(--font-color)] hover:text-red-400 text-xl font-bold">&times;</button>
                </div>
                <div class="p-4 space-y-4">
                    <!-- Thread Updater -->
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" id="thread-updater" class="form-checkbox">
                            <span class="text-[var(--font-color)]">Thread Updater</span>
                        </label>
                        <p class="text-sm text-[var(--subordinate-header)] ml-6">Automatically append new posts to the bottom of threads without refreshing</p>
                    </div>

                    <!-- Thread Watcher -->
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" id="thread-watcher" class="form-checkbox">
                            <span class="text-[var(--font-color)]">Thread Watcher</span>
                        </label>
                        <p class="text-sm text-[var(--subordinate-header)] ml-6">Track watched threads and show alerts for new posts</p>
                        <div id="watched-threads" class="ml-6 space-y-1 hidden">
                            <p class="text-sm text-[var(--font-color)]">Watched Threads:</p>
                            <div id="watched-threads-list" class="max-h-32 overflow-y-auto text-xs space-y-1"></div>
                        </div>
                    </div>

                    <!-- Thread Hiding -->
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" id="thread-hiding" class="form-checkbox">
                            <span class="text-[var(--font-color)]">Thread Hiding</span>
                        </label>
                        <p class="text-sm text-[var(--subordinate-header)] ml-6">Hide threads you don't want to see</p>
                        <div id="hidden-threads" class="ml-6 space-y-1 hidden">
                            <p class="text-sm text-[var(--font-color)]">Hidden Threads:</p>
                            <div id="hidden-threads-list" class="max-h-32 overflow-y-auto text-xs space-y-1"></div>
                        </div>
                    </div>

                    <!-- Clickable Links -->
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" id="clickable-links" class="form-checkbox">
                            <span class="text-[var(--font-color)]">Clickable Links</span>
                        </label>
                        <p class="text-sm text-[var(--subordinate-header)] ml-6">Make user-posted links clickable</p>
                    </div>

                    <!-- Show Announcements -->
                    <div class="space-y-2">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" id="show-announcements" class="form-checkbox">
                            <span class="text-[var(--font-color)]">Show Announcements</span>
                        </label>
                        <p class="text-sm text-[var(--subordinate-header)] ml-6">Display global announcements below the banner</p>
                    </div>

                    <!-- Save/Cancel Buttons -->
                    <div class="flex justify-end space-x-2 pt-4 border-t border-[var(--post-border)]">
                        <button id="settings-cancel" class="px-4 py-2 bg-[var(--reply-bg)] text-[var(--font-color)] border border-[var(--post-border)] rounded hover:bg-[var(--post-border)]">Cancel</button>
                        <button id="settings-save" class="px-4 py-2 bg-[var(--link-color)] text-[var(--post-bg)] rounded hover:bg-[var(--link-hover)]">Save Settings</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    function renderFooter()
    {
        renderSettingsModal();
        renderWatchedThreadsWidget();
        ?>
        </div>
        <div style="text-align: center; padding: 20px; font-size: 11px; color: #666;">
            Powered by Lainboard
        </div>
        <script src="/static/js/main.js" data-cfasync="false"></script>
        <script src="/static/js/styleinit.js"></script>
        <script src="/static/js/themeManager.js"></script>
        <script src="/static/js/webring.js"></script>
        <script src="/static/js/settings.js" data-cfasync="false"></script>
    </body>

    </html>
<?php
    }

    function renderPost($post, $boardUri, $isOP = false)
    {
        global $capcodes;
        $staff = getCurrentStaff();
?>
    <div class="post <?= $isOP ? 'op' : '' ?> clearfix" id="p<?= $post['id'] ?>">
        <?php if ($post['file_path']): ?>
            <div class="post-file">
                <div class="post-file-info">
                    <?php
                    $displayName = $post['file_name'];
                    $maxFilenameLength = getMaxFilenameLength();
                    if (strlen($displayName) > $maxFilenameLength) {
                        $displayName = substr($displayName, 0, $maxFilenameLength) . '...';
                    }
                    echo htmlspecialchars($displayName);
                    ?>
                    (<?= formatFileSize($post['file_size']) ?>
                    <?php
                    $fileExt = strtolower(pathinfo($post['file_name'], PATHINFO_EXTENSION));
                    if ($fileExt === 'pdf') {
                        echo ', PDF';
                    } elseif ($post['image_width'] && $post['image_height']) {
                        echo ', ' . $post['image_width'] . 'x' . $post['image_height'];
                    }
                    ?>)
                </div>
                <a href="<?= htmlspecialchars($post['file_path']) ?>" target="_blank" <?php if ($fileExt !== 'pdf'): ?>onclick="expandImage(this, '<?= htmlspecialchars($post['file_path']) ?>'); return false;"<?php endif; ?>>
                    <img src="<?= htmlspecialchars($post['thumb_path']) ?>"
                        alt="<?= htmlspecialchars($post['file_name']) ?>"
                        class="post-thumb">
                </a>
            </div>
        <?php endif; ?>

        <div class="post-header">
            <?php if ($post['subject']): ?>
                <span class="post-subject"><?= htmlspecialchars($post['subject']) ?></span>
            <?php endif; ?>

            <span class="post-name"><?= htmlspecialchars($post['name'] ?: DEFAULT_ANONYMOUS_NAME) ?></span>

            <?php if ($post['tripcode']): ?>
                <span class="post-tripcode"><?= htmlspecialchars($post['tripcode']) ?></span>
            <?php endif; ?>

            <?php if ($post['capcode'] && isset($capcodes[$post['capcode']])): ?>
                <span class="capcode-<?= $post['capcode'] ?>"><?= htmlspecialchars($capcodes[$post['capcode']]['name']) ?></span>
            <?php endif; ?>

            <?php if ($post['gpg_verified']): ?>
                <?php
                $tooltip = 'GPG Verified';
                if (!empty($post['gpg_email'])) {
                    $tooltip .= ' | Email: ' . htmlspecialchars($post['gpg_email']);
                }
                if (!empty($post['gpg_jid'])) {
                    $tooltip .= ' | JID: ' . htmlspecialchars($post['gpg_jid']);
                }
                ?>
                <a href="/gpg/key/<?= htmlspecialchars($post['gpg_key_id']) ?>" class="gpg-verified" title="<?= $tooltip ?> - Click to download public key" download>🔐</a>
                <a href="/gpg/signed/<?= $post['id'] ?>" title="Download signed message" class="gpg-download" download>📄</a>
            <?php endif; ?>

            <span class="post-date"><?= date('m/d/y(D)H:i:s', strtotime($post['created_at'])) ?></span>
            <span class="post-number">
                No.<a class="post-number" href="/<?= $boardUri ?>/thread/<?= $isOP ? $post['id'] : $post['thread_id'] ?>#p<?= $post['id'] ?>"><?= $post['id'] ?></a>
            </span>
            <?php if ($isOP): ?>
                [<a href="/<?= $boardUri ?>/thread/<?= $post['id'] ?>" class="reply-link">Reply</a>]
            <?php endif; ?>

            <?php if ($isOP && $post['is_sticky']): ?>
                <span class="sticky-icon">Sticky</span>
            <?php endif; ?>
            <?php if ($isOP && $post['is_locked']): ?>
                <span class="locked-icon">Locked</span>
            <?php endif; ?>

            <?php if ($staff): ?>
                <span class="mod-controls">
                    <?php if (hasPermission($staff['role'], 'delete_posts') || hasPermission($staff['role'], 'hide_posts')): ?>
                        <?php $action = hasPermission($staff['role'], 'delete_posts') ? 'delete' : 'hide'; ?>
                        <?php $confirmText = hasPermission($staff['role'], 'delete_posts') ? 'Delete this post?' : 'Hide this post?'; ?>
                        <a href="/admin/mod/<?= $action ?>?post=<?= $post['id'] ?>&csrf=<?= generateCSRFToken() ?>" onclick="return confirm('<?= $confirmText ?>')">[<?= hasPermission($staff['role'], 'delete_posts') ? 'D' : 'H' ?>]</a>
                    <?php endif; ?>
                    <?php if (hasPermission($staff['role'], 'ban_users')): ?>
                        <a href="/admin/mod/ban?post=<?= $post['id'] ?>">[B]</a>
                    <?php endif; ?>
                    <?php if (hasPermission($staff['role'], 'view_ips')): ?>
                        <span title="<?= htmlspecialchars($post['ip_address']) ?>">[IP]</span>
                    <?php endif; ?>
                    <?php if ($isOP && hasPermission($staff['role'], 'sticky_threads')): ?>
                        <a href="/admin/mod/sticky?thread=<?= $post['id'] ?>&csrf=<?= generateCSRFToken() ?>">[<?= $post['is_sticky'] ? 'Unsticky' : 'Sticky' ?>]</a>
                    <?php endif; ?>
                    <?php if ($isOP && hasPermission($staff['role'], 'lock_threads')): ?>
                        <a href="/admin/mod/lock?thread=<?= $post['id'] ?>&csrf=<?= generateCSRFToken() ?>">[<?= $post['is_locked'] ? 'Unlock' : 'Lock' ?>]</a>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="post-body">
            <pre>
<?php
            $threadId = $post['thread_id'] ?: $post['id']; // If thread_id is null, it's an OP post, so thread_id = post id
            echo formatComment($post['comment'], $boardUri, $threadId);
            ?>
            </pre>
        </div>

        <div style="font-size: 10px; margin-top: 5px;">
            <a href="/<?= $boardUri ?>/report/<?= $post['id'] ?>">[Report]</a>
        </div>
    </div>
<?php
    }

function renderWatchedThreadsWidget()
{
    ?>
    <!-- Watched Threads Widget -->
    <div id="watched-threads-widget" class="fixed top-4 right-4 bg-[var(--post-bg)] border border-[var(--post-border)] rounded-md shadow-lg z-40 hidden" style="min-width: 300px; max-width: 400px;">
        <div class="flex items-center justify-between p-3 border-b border-[var(--post-border)] cursor-move bg-[var(--subordinate-header)] rounded-t-md" id="watched-threads-header">
            <h4 class="text-[var(--font-color)] font-bold text-sm">Watched Threads</h4>
            <div class="flex items-center gap-2">
                <button id="watched-threads-minimize" class="text-[var(--font-color)] hover:text-yellow-400 text-lg">−</button>
                <button id="watched-threads-close" class="text-[var(--font-color)] hover:text-red-400 text-lg">×</button>
            </div>
        </div>
        <div id="watched-threads-content" class="max-h-64 overflow-y-auto">
            <table id="watched-threads-table" class="w-full text-xs">
                <thead class="bg-[var(--reply-bg)]">
                    <tr>
                        <th class="text-left p-2 text-[var(--font-color)] font-semibold border-b border-[var(--post-border)]">Thread</th>
                        <th class="text-center p-2 text-[var(--font-color)] font-semibold border-b border-[var(--post-border)] w-16">New</th>
                        <th class="text-center p-2 text-[var(--font-color)] font-semibold border-b border-[var(--post-border)] w-12">×</th>
                    </tr>
                </thead>
                <tbody id="watched-threads-body">
                    <!-- Watched threads will be populated here -->
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

?>