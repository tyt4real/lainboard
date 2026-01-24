<?php
require_once __DIR__ . '/../../templates/layout.php';

$action = $_GET['action'] ?? '';
$staff = getCurrentStaff();
$pdo = getDB();

switch ($action) {
    case 'delete':
        requirePermission('delete_posts');
        $postId = intval($_GET['post'] ?? 0);
        $reportId = intval($_GET['report'] ?? 0);

        if (!verifyCSRFToken($_GET['csrf'] ?? '')) {
            die('Invalid CSRF token');
        }

        $stmt = $pdo->prepare("UPDATE posts SET is_deleted = TRUE, deleted_by = ? WHERE id = ?");
        $stmt->execute([$staff['id'], $postId]);

        logModAction($staff['id'], 'delete_post', 'post', $postId);

        if ($reportId) {
            $stmt = $pdo->prepare("UPDATE reports SET resolved_at = NOW(), resolved_by = ?, resolution = 'deleted' WHERE id = ?");
            $stmt->execute([$staff['id'], $reportId]);
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin'));
        break;

    case 'hide':
        requirePermission('hide_posts');
        $postId = intval($_GET['post'] ?? 0);
        $reportId = intval($_GET['report'] ?? 0);

        if (!verifyCSRFToken($_GET['csrf'] ?? '')) {
            die('Invalid CSRF token');
        }

        // Check if post is older than 24 hours
        $stmt = $pdo->prepare("SELECT created_at FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post) {
            die('Post not found');
        }

        $postAge = strtotime($post['created_at']);
        $twentyFourHoursAgo = strtotime('-24 hours');

        if ($postAge < $twentyFourHoursAgo) {
            die('Cannot hide posts older than 24 hours');
        }

        $stmt = $pdo->prepare("UPDATE posts SET is_deleted = TRUE, deleted_by = ? WHERE id = ?");
        $stmt->execute([$staff['id'], $postId]);

        logModAction($staff['id'], 'hide_post', 'post', $postId);

        if ($reportId) {
            $stmt = $pdo->prepare("UPDATE reports SET resolved_at = NOW(), resolved_by = ?, resolution = 'hidden' WHERE id = ?");
            $stmt->execute([$staff['id'], $reportId]);
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin'));
        break;

    case 'restore':
        requirePermission('restore_posts'); // Only admins/mods can restore
        $postId = intval($_GET['post'] ?? 0);

        if (!verifyCSRFToken($_GET['csrf'] ?? '')) {
            die('Invalid CSRF token');
        }

        $stmt = $pdo->prepare("UPDATE posts SET is_deleted = FALSE, deleted_by = NULL WHERE id = ?");
        $stmt->execute([$postId]);

        logModAction($staff['id'], 'restore_post', 'post', $postId);

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin'));
        break;

    case 'hard_delete':
        requirePermission('delete_posts'); // Only admins/mods can hard delete
        $postId = intval($_GET['post'] ?? 0);

        if (!verifyCSRFToken($_GET['csrf'] ?? '')) {
            die('Invalid CSRF token');
        }

        // First, delete associated reports
        $stmt = $pdo->prepare("DELETE FROM reports WHERE post_id = ?");
        $stmt->execute([$postId]);

        // Delete the post itself
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$postId]);

        logModAction($staff['id'], 'hard_delete_post', 'post', $postId);

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin'));
        break;

    case 'ban':
        requirePermission('ban_users');
        $postId = intval($_GET['post'] ?? 0);
        $reportId = intval($_GET['report'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        if (!$post) {
            die('Post not found');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                die('Invalid CSRF token');
            }
            
            $reason = trim($_POST['reason'] ?? '');
            $publicReason = trim($_POST['public_reason'] ?? '');
            $duration = $_POST['duration'] ?? 'permanent';
            $isGlobal = isset($_POST['is_global']);
            
            $expiresAt = null;
            if ($duration !== 'permanent') {
                $hours = intval($duration);
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO bans (ip_address, board_id, reason, public_reason, expires_at, staff_id, is_global)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $post['ip_address'],
                $isGlobal ? null : $post['board_id'],
                $reason,
                $publicReason,
                $expiresAt,
                $staff['id'],
                $isGlobal
            ]);
            
            logModAction($staff['id'], 'ban_user', 'ip', null, "IP: {$post['ip_address']}, Reason: {$reason}");
            
            if ($reportId) {
                $stmt = $pdo->prepare("UPDATE reports SET resolved_at = NOW(), resolved_by = ?, resolution = 'banned' WHERE id = ?");
                $stmt->execute([$staff['id'], $reportId]);
            }
            
            header('Location: /admin/bans');
            exit;
        }
        
        renderHeader('Ban User');
        ?>
        <div class="admin-header">
            <div><strong>Staff Panel</strong> - Ban User</div>
            <div><a href="/admin">Dashboard</a></div>
        </div>
        
        <h2 style="text-align: center;">Ban User</h2>
        
        <div class="section">
            <div class="section-header">Post Info</div>
            <div class="section-content">
                <p><strong>Post ID:</strong> <?= $postId ?></p>
                <p><strong>IP:</strong> <?= htmlspecialchars($post['ip_address']) ?></p>
                <p><strong>Comment:</strong> <?= htmlspecialchars(substr($post['comment'], 0, 200)) ?></p>
            </div>
        </div>
        
        <div class="post-form">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <table>
                    <tr>
                        <td>Reason (internal)</td>
                        <td><input type="text" name="reason" required style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <td>Reason (shown to user)</td>
                        <td><input type="text" name="public_reason" style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <td>Duration</td>
                        <td>
                            <select name="duration">
                                <option value="1">1 hour</option>
                                <option value="24">1 day</option>
                                <option value="168">1 week</option>
                                <option value="720">30 days</option>
                                <option value="permanent">Permanent</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>Scope</td>
                        <td>
                            <label><input type="checkbox" name="is_global"> Global ban (all boards)</label>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><input type="submit" value="Apply Ban" class="btn btn-danger"></td>
                    </tr>
                </table>
            </form>
        </div>
        <?php
        renderFooter();
        break;
        
    case 'unban':
        requirePermission('ban_users');
        $banId = intval($_GET['ban'] ?? 0);
        
        if (!verifyCSRFToken($_GET['csrf'] ?? '')) {
            die('Invalid CSRF token');
        }
        
        $stmt = $pdo->prepare("DELETE FROM bans WHERE id = ?");
        $stmt->execute([$banId]);
        
        logModAction($staff['id'], 'unban', 'ban', $banId);
        
        header('Location: /admin/bans');
        break;
        
    case 'sticky':
        requirePermission('sticky_threads');
        $threadId = intval($_GET['thread'] ?? 0);
        
        if (!verifyCSRFToken($_GET['csrf'] ?? '')) {
            die('Invalid CSRF token');
        }
        
        $stmt = $pdo->prepare("UPDATE posts SET is_sticky = NOT is_sticky WHERE id = ? AND thread_id IS NULL");
        $stmt->execute([$threadId]);
        
        logModAction($staff['id'], 'toggle_sticky', 'thread', $threadId);
        
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        break;
        
    case 'lock':
        requirePermission('lock_threads');
        $threadId = intval($_GET['thread'] ?? 0);
        
        if (!verifyCSRFToken($_GET['csrf'] ?? '')) {
            die('Invalid CSRF token');
        }
        
        $stmt = $pdo->prepare("UPDATE posts SET is_locked = NOT is_locked WHERE id = ? AND thread_id IS NULL");
        $stmt->execute([$threadId]);
        
        logModAction($staff['id'], 'toggle_lock', 'thread', $threadId);
        
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        break;
        
    case 'resolve':
        requirePermission('view_reports');
        $reportId = intval($_GET['report'] ?? 0);
        $resolution = $_GET['action'] ?? 'ignore';
        
        if (!verifyCSRFToken($_GET['csrf'] ?? '')) {
            die('Invalid CSRF token');
        }
        
        $stmt = $pdo->prepare("UPDATE reports SET resolved_at = NOW(), resolved_by = ?, resolution = ? WHERE id = ?");
        $stmt->execute([$staff['id'], $resolution, $reportId]);
        
        logModAction($staff['id'], 'resolve_report', 'report', $reportId, "Resolution: {$resolution}");
        
        header('Location: /admin/reports');
        break;
        
    default:
        http_response_code(404);
        echo 'Unknown action';
}
