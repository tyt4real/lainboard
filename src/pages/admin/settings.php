<?php
require_once __DIR__ . '/../../templates/layout.php';
require_once __DIR__ . '/../../includes/functions.php';
requirePermission('manage_boards'); // Using manage_boards permission for settings

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
        if (isset($_POST['update_gpg_settings'])) {
            $gpg_enabled = ($_POST['gpg_enabled'] ?? '0') === '1';

            setSetting('gpg_enabled', $gpg_enabled ? '1' : '0');

            logModAction($staff['id'], 'update_setting', 'setting', 0, 'GPG posting ' . ($gpg_enabled ? 'enabled' : 'disabled'));
            $message = 'GPG settings updated successfully';
        } elseif (isset($_POST['update_file_settings'])) {
            $max_file_size = (int)($_POST['max_file_size'] ?? 4);
            $allowed_extensions = trim($_POST['allowed_extensions'] ?? 'jpg,jpeg,png,gif,webp');
            $thumb_width = (int)($_POST['thumb_width'] ?? 200);
            $thumb_height = (int)($_POST['thumb_height'] ?? 200);
            $pdf_upload_enabled = ($_POST['pdf_upload_enabled'] ?? '0') === '1';

            $phpMaxSizeMB = $phpMaxSize / 1024 / 1024;
            if ($max_file_size < 1 || $max_file_size > 50) {
                $error = 'File size must be between 1-50 MB';
            } elseif ($max_file_size * 1024 * 1024 > $phpMaxSize) {
                $error = 'File size cannot exceed server PHP limit (' . formatBytes($phpMaxSize) . ')';
            } elseif ($thumb_width < 50 || $thumb_height < 50 || $thumb_width > 500 || $thumb_height > 500) {
                $error = 'Thumbnail dimensions must be between 50-500 pixels';
            } else {
                setSetting('max_file_size', $max_file_size);
                setSetting('allowed_extensions', $allowed_extensions);
                setSetting('thumb_width', $thumb_width);
                setSetting('thumb_height', $thumb_height);
                setSetting('pdf_upload_enabled', $pdf_upload_enabled ? '1' : '0');

                logModAction($staff['id'], 'update_setting', 'setting', 0, 'Updated file upload settings');
                $message = 'File settings updated successfully';
            }
        } elseif (isset($_POST['update_rate_settings'])) {
            $flood_time = (int)($_POST['flood_time'] ?? 30);
            $thread_cooldown = (int)($_POST['thread_cooldown'] ?? 120);

            if ($flood_time < 0 || $flood_time > 300) {
                $error = 'Flood time must be between 0-300 seconds';
            } elseif ($thread_cooldown < 0 || $thread_cooldown > 3600) {
                $error = 'Thread cooldown must be between 0-3600 seconds';
            } else {
                setSetting('flood_time', $flood_time);
                setSetting('thread_cooldown', $thread_cooldown);

                logModAction($staff['id'], 'update_setting', 'setting', 0, 'Updated rate limiting settings');
                $message = 'Rate settings updated successfully';
            }
        } elseif (isset($_POST['update_content_settings'])) {
            $max_comment_length = (int)($_POST['max_comment_length'] ?? 8000);
            $max_name_length = (int)($_POST['max_name_length'] ?? 75);
            $max_subject_length = (int)($_POST['max_subject_length'] ?? 100);
            $max_filename_length = (int)($_POST['max_filename_length'] ?? 25);

            if ($max_comment_length < 100 || $max_comment_length > 50000) {
                $error = 'Comment length must be between 100-50000 characters';
            } elseif ($max_name_length < 10 || $max_name_length > 255) {
                $error = 'Name length must be between 10-255 characters';
            } elseif ($max_subject_length < 10 || $max_subject_length > 255) {
                $error = 'Subject length must be between 10-255 characters';
            } elseif ($max_filename_length < 5 || $max_filename_length > 100) {
                $error = 'Filename length must be between 5-100 characters';
            } else {
                setSetting('max_comment_length', $max_comment_length);
                setSetting('max_name_length', $max_name_length);
                setSetting('max_subject_length', $max_subject_length);
                setSetting('max_filename_length', $max_filename_length);

                logModAction($staff['id'], 'update_setting', 'setting', 0, 'Updated content limit settings');
                $message = 'Content settings updated successfully';
            }
        } elseif (isset($_POST['update_display_settings'])) {
            $threads_per_page = (int)($_POST['threads_per_page'] ?? 10);
            $replies_shown = (int)($_POST['replies_shown'] ?? 5);
            $bump_limit = (int)($_POST['bump_limit'] ?? 300);
            $popular_threads_count = (int)($_POST['popular_threads_count'] ?? 10);
            $post_count_digits = (int)($_POST['post_count_digits'] ?? 6);

            if ($threads_per_page < 5 || $threads_per_page > 50) {
                $error = 'Threads per page must be between 5-50';
            } elseif ($replies_shown < 1 || $replies_shown > 20) {
                $error = 'Replies shown must be between 1-20';
            } elseif ($bump_limit < 50 || $bump_limit > 1000) {
                $error = 'Bump limit must be between 50-1000';
            } elseif ($popular_threads_count < 1 || $popular_threads_count > 50) {
                $error = 'Popular threads count must be between 1-50';
            } elseif ($post_count_digits < 1 || $post_count_digits > 12) {
                $error = 'Post count digits must be between 1-12';
            } else {
                setSetting('threads_per_page', $threads_per_page);
                setSetting('replies_shown', $replies_shown);
                setSetting('bump_limit', $bump_limit);
                setSetting('popular_threads_count', $popular_threads_count);
                setSetting('post_count_digits', $post_count_digits);

                logModAction($staff['id'], 'update_setting', 'setting', 0, 'Updated display settings');
                $message = 'Display settings updated successfully';
            }
        } elseif (isset($_POST['update_ui_settings'])) {
            $theme_selector_enabled = ($_POST['theme_selector_enabled'] ?? '0') === '1';
            $catalog_enabled = ($_POST['catalog_enabled'] ?? '0') === '1';
            $overboard_enabled = ($_POST['overboard_enabled'] ?? '0') === '1';

            setSetting('theme_selector_enabled', $theme_selector_enabled ? '1' : '0');
            setSetting('catalog_enabled', $catalog_enabled ? '1' : '0');
            setSetting('overboard_enabled', $overboard_enabled ? '1' : '0');

            logModAction($staff['id'], 'update_setting', 'setting', 0, 'Updated UI settings');
            $message = 'UI settings updated successfully';
        } elseif (isset($_POST['add_to_whitelist'])) {
            $ip = trim($_POST['whitelist_ip'] ?? '');
            $reason = trim($_POST['whitelist_reason'] ?? '');

            // Basic IP validation
            if (empty($ip)) {
                $error = 'IP address is required';
            } elseif (!filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $ip)) {
                $error = 'Invalid IP address format';
            } elseif (isWhitelisted($ip)) {
                $error = 'IP is already whitelisted';
            } else {
                if (addToWhitelist($ip, $reason, $staff['id'])) {
                    logModAction($staff['id'], 'add_to_whitelist', 'ip', 0, "Whitelisted IP: $ip - $reason");
                    $message = 'IP added to whitelist successfully';
                } else {
                    $error = 'Failed to add IP to whitelist';
                }
            }
        } elseif (isset($_POST['remove_from_whitelist'])) {
            $ip = trim($_POST['remove_ip'] ?? '');

            if (empty($ip)) {
                $error = 'IP address is required';
            } elseif (removeFromWhitelist($ip)) {
                logModAction($staff['id'], 'remove_from_whitelist', 'ip', 0, "Removed whitelisted IP: $ip");
                $message = 'IP removed from whitelist successfully';
            } else {
                $error = 'IP was not found in whitelist';
            }
        }
    }
}

// Get current settings
$gpg_enabled = getSetting('gpg_enabled', '1') === '1'; // Default to enabled

renderHeader('Admin Settings');
?>

<div class="admin-header">
    <div>
        <strong>Staff Panel</strong> -
        Logged in as: <?= htmlspecialchars($staff['username']) ?> (<?= htmlspecialchars($staff['role']) ?>)
    </div>
    <div>
        <a href="/admin/dashboard">Dashboard</a>
        <a href="/admin/reports">Reports</a>
        <a href="/admin/bans">Bans</a>
        <a href="/admin/users">Users</a>
        <a href="/admin/announcements">Announcements</a>
        <a href="/admin/boards">Boards</a>
        <a href="/admin/logs">Logs</a>
        <a href="/admin/logout">Logout</a>
    </div>
</div>

<h2 style="text-align: center;">Site Settings</h2>

<?php if ($message): ?>
<div class="success-message" style="background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 4px;">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="error-message" style="background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="section">
    <div class="section-header">GPG Settings</div>
    <div class="section-content">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="gpg_enabled" value="1" <?= $gpg_enabled ? 'checked' : '' ?>>
                    Enable GPG posting functionality
                </label>
                <small style="color: #666;">
                    When disabled, users cannot post with GPG signatures. Existing GPG verified posts will still show verification badges.
                </small>
            </div>

            <button type="submit" name="update_gpg_settings" class="admin-button">
                Save GPG Settings
            </button>
        </form>
    </div>
</div>

<div class="section">
    <div class="section-header">File Upload Settings</div>
    <div class="section-content">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <?php
            $phpMaxSize = getPhpUploadMaxSize();
            $appMaxSize = (int)getSetting('max_file_size', 4) * 1024 * 1024;
            $hasWarning = $appMaxSize > $phpMaxSize;
            ?>

            <?php if ($hasWarning): ?>
            <div style="background: #fff3cd; color: #856404; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #ffeaa7;">
                <strong>⚠️ Warning:</strong> Your configured maximum file size (<?= formatBytes($appMaxSize) ?>) exceeds the server's PHP limits (<?= formatBytes($phpMaxSize) ?>).
                Files larger than <?= formatBytes($phpMaxSize) ?> will be rejected. Update your PHP configuration or lower the setting below.
            </div>
            <?php endif; ?>

            <div style="background: #e7f3ff; color: #0066cc; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                <strong>Server Limits:</strong><br>
                PHP upload_max_filesize: <?= ini_get('upload_max_filesize') ?><br>
                PHP post_max_size: <?= ini_get('post_max_size') ?><br>
                Effective limit: <?= formatBytes($phpMaxSize) ?>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Maximum file size (MB):
                    <input type="number" name="max_file_size" value="<?= htmlspecialchars(getSetting('max_file_size', '4')) ?>" min="1" max="50" style="margin-left: 10px; width: 80px;">
                    <small style="color: #666;">(Server limit: <?= formatBytes($phpMaxSize) ?>)</small>
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Allowed file extensions (comma-separated):
                    <input type="text" name="allowed_extensions" value="<?= htmlspecialchars(getSetting('allowed_extensions', 'jpg,jpeg,png,gif,webp')) ?>" style="margin-left: 10px; width: 300px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Thumbnail size (width x height):
                    <input type="number" name="thumb_width" value="<?= htmlspecialchars(getSetting('thumb_width', '200')) ?>" min="50" max="500" style="margin-left: 10px; width: 60px;">
                    x
                    <input type="number" name="thumb_height" value="<?= htmlspecialchars(getSetting('thumb_height', '200')) ?>" min="50" max="500" style="width: 60px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="pdf_upload_enabled" value="1" <?= getSetting('pdf_upload_enabled', '0') === '1' ? 'checked' : '' ?>>
                    Enable PDF upload support (requires ImageMagick)
                </label>
                <small style="color: #666;">
                    Allow users to upload PDF files with automatic thumbnail generation from the first page.
                </small>
            </div>

            <button type="submit" name="update_file_settings" class="admin-button">
                Save File Settings
            </button>
        </form>
    </div>
</div>

<div class="section">
    <div class="section-header">Rate Limiting Settings</div>
    <div class="section-content">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Flood time (seconds between posts):
                    <input type="number" name="flood_time" value="<?= htmlspecialchars(getSetting('flood_time', '30')) ?>" min="0" max="300" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Thread creation cooldown (seconds):
                    <input type="number" name="thread_cooldown" value="<?= htmlspecialchars(getSetting('thread_cooldown', '120')) ?>" min="0" max="3600" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <button type="submit" name="update_rate_settings" class="admin-button">
                Save Rate Settings
            </button>
        </form>
    </div>
</div>

<div class="section">
    <div class="section-header">Content Limits</div>
    <div class="section-content">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Maximum comment length:
                    <input type="number" name="max_comment_length" value="<?= htmlspecialchars(getSetting('max_comment_length', '8000')) ?>" min="100" max="50000" style="margin-left: 10px; width: 100px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Maximum name length:
                    <input type="number" name="max_name_length" value="<?= htmlspecialchars(getSetting('max_name_length', '75')) ?>" min="10" max="255" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Maximum subject length:
                    <input type="number" name="max_subject_length" value="<?= htmlspecialchars(getSetting('max_subject_length', '100')) ?>" min="10" max="255" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Maximum filename display length:
                    <input type="number" name="max_filename_length" value="<?= htmlspecialchars(getSetting('max_filename_length', '25')) ?>" min="5" max="100" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <button type="submit" name="update_content_settings" class="admin-button">
                Save Content Settings
            </button>
        </form>
    </div>
</div>

<div class="section">
    <div class="section-header">Display & Pagination Settings</div>
    <div class="section-content">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Threads per page:
                    <input type="number" name="threads_per_page" value="<?= htmlspecialchars(getSetting('threads_per_page', '10')) ?>" min="5" max="50" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Replies shown per thread:
                    <input type="number" name="replies_shown" value="<?= htmlspecialchars(getSetting('replies_shown', '5')) ?>" min="1" max="20" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Bump limit (replies before thread stops bumping):
                    <input type="number" name="bump_limit" value="<?= htmlspecialchars(getSetting('bump_limit', '300')) ?>" min="50" max="1000" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Popular threads to show:
                    <input type="number" name="popular_threads_count" value="<?= htmlspecialchars(getSetting('popular_threads_count', '10')) ?>" min="1" max="50" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    Post counter digits:
                    <input type="number" name="post_count_digits" value="<?= htmlspecialchars(getSetting('post_count_digits', '6')) ?>" min="1" max="12" style="margin-left: 10px; width: 80px;">
                </label>
            </div>

            <button type="submit" name="update_display_settings" class="admin-button">
                Save Display Settings
            </button>
        </form>
    </div>
</div>

<div class="section">
    <div class="section-header">UI Settings</div>
    <div class="section-content">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="theme_selector_enabled" value="1" <?= getSetting('theme_selector_enabled', '1') === '1' ? 'checked' : '' ?>>
                    Enable theme selector for users
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="catalog_enabled" value="1" <?= getSetting('catalog_enabled', '1') === '1' ? 'checked' : '' ?>>
                    Enable catalog view for boards
                </label>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="overboard_enabled" value="1" <?= getSetting('overboard_enabled', '0') === '1' ? 'checked' : '' ?>>
                    Enable overboard (/overboard)
                </label>
            </div>

            <button type="submit" name="update_ui_settings" class="admin-button">
                Save UI Settings
            </button>
        </form>
    </div>
</div>

<div class="section">
    <div class="section-header">IP Whitelist Management</div>
    <div class="section-content">
        <p style="margin-bottom: 15px; color: #666;">
            IPs on this list will bypass all flood control and cooldown restrictions. Use for trusted users, staff, or VPNs.
        </p>

        <!-- Add IP Form -->
        <form method="POST" action="" style="margin-bottom: 20px;">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                <input type="text" name="whitelist_ip" placeholder="192.168.1.1 or 10.0.0.0/24" style="flex: 1; padding: 8px; border: 1px solid #ccc;" required>
                <input type="text" name="whitelist_reason" placeholder="Reason (optional)" style="flex: 1; padding: 8px; border: 1px solid #ccc;">
                <button type="submit" name="add_to_whitelist" class="admin-button" style="padding: 8px 16px;">
                    Add IP
                </button>
            </div>
        </form>

        <!-- Current Whitelist -->
        <?php $whitelist = getWhitelist(); ?>
        <?php if (empty($whitelist)): ?>
            <p style="color: #666; font-style: italic;">No IPs are currently whitelisted.</p>
        <?php else: ?>
            <table class="admin-table" style="width: 100%;">
                <tr>
                    <th>IP Address</th>
                    <th>Reason</th>
                    <th>Added By</th>
                    <th>Added Date</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($whitelist as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars($entry['ip_address']) ?></td>
                        <td><?= htmlspecialchars($entry['reason'] ?: 'No reason given') ?></td>
                        <td><?= htmlspecialchars($entry['created_by_username'] ?: 'System') ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($entry['created_at'])) ?></td>
                        <td>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="remove_ip" value="<?= htmlspecialchars($entry['ip_address']) ?>">
                                <button type="submit" name="remove_from_whitelist" class="admin-button" style="padding: 4px 8px; font-size: 12px; background: #dc3545;" onclick="return confirm('Remove <?= htmlspecialchars($entry['ip_address']) ?> from whitelist?')">
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>

