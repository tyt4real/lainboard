<?php
require_once __DIR__ . '/../../templates/layout.php';
requirePermission('manage_boards'); // Admin-only access

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
        if (isset($_POST['add_filter'])) {
            $word = trim($_POST['word'] ?? '');
            $replacement = trim($_POST['replacement'] ?? '');

            if (empty($word) || empty($replacement)) {
                $error = 'Word and replacement are required';
            } elseif (strlen($word) > 100 || strlen($replacement) > 100) {
                $error = 'Word and replacement must be 100 characters or less';
            } else {
                // Check if word filter already exists
                $stmt = $pdo->prepare("SELECT id FROM word_filters WHERE word = ? AND is_active = TRUE");
                $stmt->execute([$word]);
                if ($stmt->fetch()) {
                    $error = 'A filter for this word already exists';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO word_filters (word, replacement, created_by) VALUES (?, ?, ?)");
                    $stmt->execute([$word, $replacement, $staff['id']]);

                    logModAction($staff['id'], 'add_word_filter', 'word_filter', $pdo->lastInsertId(), "Added filter: '$word' -> '$replacement'");
                    $message = 'Word filter added successfully';
                }
            }
        } elseif (isset($_POST['delete_filter'])) {
            $filter_id = (int)($_POST['filter_id'] ?? 0);

            if (!$filter_id) {
                $error = 'Invalid filter ID';
            } else {
                // Soft delete by setting is_active = FALSE
                $stmt = $pdo->prepare("UPDATE word_filters SET is_active = FALSE WHERE id = ?");
                $stmt->execute([$filter_id]);

                logModAction($staff['id'], 'delete_word_filter', 'word_filter', $filter_id, 'Deactivated word filter');
                $message = 'Word filter removed successfully';
            }
        }
    }
}

// Get all active word filters
$filters = $pdo->query("
    SELECT wf.*, s.username as created_by_name
    FROM word_filters wf
    LEFT JOIN staff s ON wf.created_by = s.id
    WHERE wf.is_active = TRUE
    ORDER BY wf.created_at DESC
")->fetchAll();

renderHeader('Word Filters');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> - Word Filters
    </div>
    <div>
        <a href="/">Home</a>
        <a href="/admin">Dashboard</a>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">Word Filters Management</h2>

<?php if ($message): ?>
<div style="background-color: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border: 1px solid #c3e6cb; border-radius: 4px;">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 4px;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="section">
    <div class="section-header">Add New Word Filter</div>
    <div class="section-content">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <table>
                <tr>
                    <td>Word to filter:</td>
                    <td><input type="text" name="word" required maxlength="100" placeholder="e.g., badword" style="width: 200px;"></td>
                </tr>
                <tr>
                    <td>Replacement:</td>
                    <td><input type="text" name="replacement" required maxlength="100" placeholder="e.g., ***" style="width: 200px;"></td>
                </tr>
                <tr>
                    <td></td>
                    <td><input type="submit" name="add_filter" value="Add Filter" class="btn btn-primary"></td>
                </tr>
            </table>
        </form>
    </div>
</div>

<div class="section">
    <div class="section-header">Active Word Filters (<?= count($filters) ?>)</div>
    <div class="section-content">
        <?php if (empty($filters)): ?>
        <p>No word filters configured.</p>
        <?php else: ?>
        <table class="admin-table">
            <tr>
                <th>ID</th>
                <th>Word</th>
                <th>Replacement</th>
                <th>Created By</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($filters as $filter): ?>
            <tr>
                <td><?= $filter['id'] ?></td>
                <td><code><?= htmlspecialchars($filter['word']) ?></code></td>
                <td><code><?= htmlspecialchars($filter['replacement']) ?></code></td>
                <td><?= htmlspecialchars($filter['created_by_name'] ?? 'Unknown') ?></td>
                <td><?= date('m/d/y H:i', strtotime($filter['created_at'])) ?></td>
                <td>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this word filter?')">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="filter_id" value="<?= $filter['id'] ?>">
                        <input type="submit" name="delete_filter" value="Remove" class="btn btn-danger btn-sm">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>
