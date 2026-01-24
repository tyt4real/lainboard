<?php
require_once __DIR__ . '/../../templates/layout.php';
requirePermission('manage_boards');

$staff = getCurrentStaff();
$pdo = getDB();

// Handle POST requests
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token';
    } else {
        if (isset($_POST['toggle_board_status'])) {
            $board_id = (int)($_POST['board_id'] ?? 0);
            $is_closed = ($_POST['is_closed'] ?? '0') === '1';

            if (!$board_id) {
                $error = 'Invalid board ID';
            } else {
                $stmt = $pdo->prepare("UPDATE boards SET is_closed = ? WHERE id = ?");
                $stmt->execute([$is_closed ? 1 : 0, $board_id]);

                logModAction($staff['id'], 'toggle_board_status', 'board', $board_id, $is_closed ? 'Closed board' : 'Opened board');
                $message = 'Board ' . ($is_closed ? 'closed' : 'opened') . ' successfully';
            }
        } elseif (isset($_POST['update_board_rules'])) {
            $board_id = (int)($_POST['board_id'] ?? 0);
            $rules = trim($_POST['rules'] ?? '');

            if (!$board_id) {
                $error = 'Invalid board ID';
            } elseif (strlen($rules) > 10000) {
                $error = 'Rules text is too long (max 10000 characters)';
            } else {
                $stmt = $pdo->prepare("UPDATE boards SET rules = ? WHERE id = ?");
                $stmt->execute([$rules, $board_id]);

                logModAction($staff['id'], 'update_board_rules', 'board', $board_id, 'Updated board rules');
                $message = 'Board rules updated successfully';
            }
        } elseif (isset($_POST['toggle_nsfw'])) {
            $board_id = (int)($_POST['board_id'] ?? 0);
            $is_nsfw = ($_POST['is_nsfw'] ?? '0') === '1';

            if (!$board_id) {
                $error = 'Invalid board ID';
            } else {
                $stmt = $pdo->prepare("UPDATE boards SET is_nsfw = ? WHERE id = ?");
                $stmt->execute([$is_nsfw ? 1 : 0, $board_id]);

                logModAction($staff['id'], 'toggle_nsfw', 'board', $board_id, $is_nsfw ? 'Marked as NSFW' : 'Unmarked as NSFW');
                $message = 'Board NSFW status updated successfully';
            }
        } elseif (isset($_POST['add_tag'])) {
            $board_id = (int)($_POST['board_id'] ?? 0);
            $tag_name = trim($_POST['tag_name'] ?? '');

            if (!$board_id) {
                $error = 'Invalid board ID';
            } elseif (empty($tag_name)) {
                $error = 'Tag name cannot be empty';
            } elseif (strlen($tag_name) > 50) {
                $error = 'Tag name too long (max 50 characters)';
            } else {
                addBoardTag($board_id, $tag_name);
                logModAction($staff['id'], 'add_board_tag', 'board', $board_id, 'Added tag: ' . $tag_name);
                $message = 'Tag added successfully';
            }
        } elseif (isset($_POST['remove_tag'])) {
            $board_id = (int)($_POST['board_id'] ?? 0);
            $tag_name = trim($_POST['tag_name'] ?? '');

            if (!$board_id) {
                $error = 'Invalid board ID';
            } elseif (empty($tag_name)) {
                $error = 'Invalid tag name';
            } else {
                removeBoardTag($board_id, $tag_name);
                logModAction($staff['id'], 'remove_board_tag', 'board', $board_id, 'Removed tag: ' . $tag_name);
                $message = 'Tag removed successfully';
            }
        }
    }
}

// Get all boards with statistics
$boards = $pdo->query("
    SELECT
        b.*,
        (SELECT COUNT(*) FROM posts WHERE board_id = b.id AND thread_id IS NULL AND is_deleted = FALSE) as thread_count,
        (SELECT COUNT(*) FROM posts WHERE board_id = b.id AND is_deleted = FALSE) as total_posts,
        (SELECT MAX(created_at) FROM posts WHERE board_id = b.id AND is_deleted = FALSE) as last_post
    FROM boards b
    ORDER BY b.uri
")->fetchAll();

renderHeader('Board Management');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> - Board Management
    </div>
    <div>
        <a href="/">Home</a>
        <a href="/admin">Dashboard</a>
        <a href="/admin/reports">Reports</a>
        <a href="/admin/bans">Bans</a>
        <a href="/admin/users">Users</a>
        <a href="/admin/announcements">Announcements</a>
        <a href="/admin/logs">Logs</a>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">Board Management</h2>

<?php if ($message): ?>
<div style="background-color: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 4px; text-align: center;">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px; text-align: center;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="section">
    <div class="section-header">Board Status Overview</div>
    <div class="section-content">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <?php
            $openBoards = 0;
            $closedBoards = 0;
            $totalThreads = 0;
            $totalPosts = 0;

            foreach ($boards as $board) {
                if ($board['is_closed']) {
                    $closedBoards++;
                } else {
                    $openBoards++;
                }
                $totalThreads += $board['thread_count'];
                $totalPosts += $board['total_posts'];
            }
            ?>

            <div style="background: var(--post-bg); border: 1px solid var(--post-border); padding: 15px; border-radius: 4px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: var(--font-color);"><?= count($boards) ?></div>
                <div style="color: var(--font-color);">Total Boards</div>
            </div>

            <div style="background: var(--post-bg); border: 1px solid var(--post-border); padding: 15px; border-radius: 4px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #28a745;"><?= $openBoards ?></div>
                <div style="color: var(--font-color);">Open Boards</div>
            </div>

            <div style="background: var(--post-bg); border: 1px solid var(--post-border); padding: 15px; border-radius: 4px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #dc3545;"><?= $closedBoards ?></div>
                <div style="var(--font-color);">Closed Boards</div>
            </div>

            <div style="background: var(--post-bg); border: 1px solid var(--post-border); padding: 15px; border-radius: 4px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: var(--font-color);"><?= number_format($totalThreads) ?></div>
                <div style="var(--font-color);">Total Threads</div>
            </div>

            <div style="background: var(--post-bg); border: 1px solid var(--post-border); padding: 15px; border-radius: 4px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: var(--font-color);"><?= number_format($totalPosts) ?></div>
                <div style="var(--font-color);">Total Posts</div>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">Board Management (<?= count($boards) ?> boards)</div>
    <div class="section-content">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($boards as $board): ?>
            <div style="background: var(--post-bg); border: 1px solid var(--post-border); border-radius: 6px; overflow: hidden;">
                <!-- Board Header -->
                <div style="padding: 12px; border-bottom: 1px solid var(--post-border); background: var(--subordinate-header);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; color: var(--font-color); font-size: 16px;">
                                <a href="/<?= htmlspecialchars($board['uri']) ?>" style="color: var(--font-color); text-decoration: none;">/<?= htmlspecialchars($board['uri']) ?>/ - <?= htmlspecialchars($board['title']) ?></a>
                            </h3>
                            <?php if ($board['subtitle']): ?>
                                <div style="color: var(--font-color); font-size: 12px; margin-top: 2px;">
                                    <?= htmlspecialchars($board['subtitle']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <?php if ($board['is_nsfw']): ?>
                                <span style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">NSFW</span>
                            <?php endif; ?>
                            <span style="color: <?= $board['is_closed'] ? '#dc3545' : '#28a745' ?>; font-weight: bold; font-size: 12px;">
                                <?= $board['is_closed'] ? 'CLOSED' : 'OPEN' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Board Stats -->
                <div style="padding: 12px; border-bottom: 1px solid var(--post-border);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;">
                        <div>
                            <span style="color: var(--font-color);">Threads:</span>
                            <span style="color: var(--font-color); font-weight: bold;"><?= number_format($board['thread_count']) ?></span>
                        </div>
                        <div>
                            <span style="color: var(--font-color);">Posts:</span>
                            <span style="color: var(--font-color); font-weight: bold;"><?= number_format($board['total_posts']) ?></span>
                        </div>
                        <div style="grid-column: span 2;">
                            <span style="var(--font-color);">Last Post:</span>
                            <span style="color: var(--font-color);">
                                <?php if ($board['last_post']): ?>
                                    <?= date('m/d/y H:i', strtotime($board['last_post'])) ?>
                                <?php else: ?>
                                    Never
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Board Controls -->
                <div style="padding: 12px; space-y: 8px;">
                    <!-- Status Toggle -->
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="toggle_board_status" value="1">
                        <input type="hidden" name="board_id" value="<?= $board['id'] ?>">
                        <input type="hidden" name="is_closed" value="<?= $board['is_closed'] ? '0' : '1' ?>">
                        <button type="submit" style="background-color: <?= $board['is_closed'] ? '#28a745' : '#dc3545' ?>; color: white; border: none; padding: 6px 12px; border-radius: 3px; cursor: pointer; font-size: 12px;">
                            <?= $board['is_closed'] ? 'Open Board' : 'Close Board' ?>
                        </button>
                    </form>

                    <!-- NSFW Toggle -->
                    <form method="post" style="display: inline; margin-left: 8px;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="toggle_nsfw" value="1">
                        <input type="hidden" name="board_id" value="<?= $board['id'] ?>">
                        <input type="hidden" name="is_nsfw" value="<?= $board['is_nsfw'] ? '0' : '1' ?>">
                        <button type="submit" style="background-color: <?= $board['is_nsfw'] ? '#6c757d' : '#ffc107' ?>; color: black; border: none; padding: 6px 12px; border-radius: 3px; cursor: pointer; font-size: 12px;">
                            <?= $board['is_nsfw'] ? 'Remove NSFW' : 'Mark NSFW' ?>
                        </button>
                    </form>

                    <!-- Rules Editor -->
                    <div style="margin-top: 12px;">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="update_board_rules" value="1">
                            <input type="hidden" name="board_id" value="<?= $board['id'] ?>">

                            <label style="display: block; color: var(--font-color); font-size: 12px; margin-bottom: 4px;">Board Rules:</label>
                            <textarea name="rules" rows="3" style="width: 100%; padding: 6px; border: 1px solid var(--post-border); border-radius: 3px; background: var(--reply-bg); color: var(--font-color); font-size: 12px; resize: vertical;" placeholder="Enter board rules..."><?= htmlspecialchars($board['rules'] ?? '') ?></textarea>

                            <button type="submit" style="margin-top: 6px; background-color: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 3px; cursor: pointer; font-size: 12px;">
                                Update Rules
                            </button>
                        </form>
                    </div>

                    <!-- Tag Management -->
                    <div style="margin-top: 12px;">
                        <label style="display: block; color: var(--font-color); font-size: 12px; margin-bottom: 4px;">Board Tags:</label>
                        <?php $boardTags = getBoardTags($board['id']); ?>
                        <div style="margin-bottom: 8px;">
                            <?php if (empty($boardTags)): ?>
                                <span style="color: var(--subordinate-header); font-size: 11px;">No tags assigned</span>
                            <?php else: ?>
                                <?php foreach ($boardTags as $tag): ?>
                                    <span style="display: inline-block; background: var(--link-color); color: var(--post-bg); padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-right: 4px; margin-bottom: 2px;">
                                        <?= htmlspecialchars($tag) ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="remove_tag" value="1">
                                            <input type="hidden" name="board_id" value="<?= $board['id'] ?>">
                                            <input type="hidden" name="tag_name" value="<?= htmlspecialchars($tag) ?>">
                                            <button type="submit" style="background: none; border: none; color: var(--post-bg); cursor: pointer; font-size: 10px; margin-left: 4px; padding: 0;" title="Remove tag">×</button>
                                        </form>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <form method="post" style="display: flex; gap: 4px;">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="add_tag" value="1">
                            <input type="hidden" name="board_id" value="<?= $board['id'] ?>">
                            <input type="text" name="tag_name" placeholder="New tag..." style="flex: 1; padding: 4px; border: 1px solid var(--post-border); border-radius: 3px; background: var(--reply-bg); color: var(--font-color); font-size: 11px;" maxlength="50">
                            <button type="submit" style="background-color: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 11px;">
                                Add
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
