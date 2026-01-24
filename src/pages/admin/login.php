<?php
require_once __DIR__ . '/../../templates/layout.php';

if (isLoggedIn()) {
    header('Location: /admin');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (login($username, $password)) {
            header('Location: /admin');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

renderHeader('Staff Login');
?>

<h2 style="text-align: center;">Staff Login</h2>

<div class="post-form" style="max-width: 400px; margin: 20px auto;">
    <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <table>
            <tr>
                <td>Username</td>
                <td><input type="text" name="username" required autocomplete="username"></td>
            </tr>
            <tr>
                <td>Password</td>
                <td><input type="password" name="password" required autocomplete="current-password"></td>
            </tr>
            <tr>
                <td></td>
                <td><input type="submit" value="Login"></td>
            </tr>
        </table>
    </form>
</div>

<?php renderFooter(); ?>
