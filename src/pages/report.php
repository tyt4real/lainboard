<?php
require_once __DIR__ . '/../templates/layout.php';

$boardUri = $_GET['board'] ?? '';
$postId = intval($_GET['post'] ?? 0);

$board = getBoard($boardUri);
if (!$board) {
    http_response_code(404);
    echo 'Board not found';
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND board_id = ? AND is_deleted = FALSE");
$stmt->execute([$postId, $board['id']]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo 'Post not found';
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $reason = trim($_POST['reason'] ?? '');
        if (empty($reason)) {
            $error = 'Please provide a reason for the report.';
        } else {
            $ip = getClientIP();
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM reports 
                WHERE post_id = ? AND ip_address = ? AND created_at > NOW() - INTERVAL '1 hour'
            ");
            $stmt->execute([$postId, $ip]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'You have already reported this post recently.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO reports (post_id, ip_address, reason) VALUES (?, ?, ?)");
                $stmt->execute([$postId, $ip, $reason]);
                $success = 'Report submitted successfully. Thank you!';
            }
        }
    }
}

renderHeader('Report Post');
?>

<h2>Report Post #<?= $postId ?></h2>

<div style="text-align: center; margin-bottom: 15px;">
    <a href="/<?= htmlspecialchars($boardUri) ?>/thread/<?= $post['thread_id'] ?: $postId ?>#p<?= $postId ?>">[Return to thread]</a>
</div>

<?php if ($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="success"><?= htmlspecialchars($success) ?></div>
<?php else: ?>

<div class="section">
    <div class="section-header">Post Preview</div>
    <div class="section-content">
        <?php renderPost($post, $boardUri, $post['thread_id'] === null); ?>
    </div>
</div>

<div class="section">
    <div class="section-header">Submit Report</div>
    <div class="section-content">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <table>
                <tr>
                    <td><strong>Reason:</strong></td>
                    <td>
                        <select name="reason" required>
                            <option value="">-- Select a reason --</option>
                            <option value="Rule violation">Rule violation</option>
                            <option value="Spam">Spam</option>
                            <option value="Illegal content">Illegal content</option>
                            <option value="Off-topic">Off-topic</option>
                            <option value="Harassment">Harassment</option>
                            <option value="Other">Other</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td><input type="submit" value="Submit Report" class="btn"></td>
                </tr>
            </table>
        </form>
    </div>
</div>

<?php endif; ?>

<?php renderFooter(); ?>
