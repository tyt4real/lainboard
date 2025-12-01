<?php
require __DIR__ . '/../scripts/utils.php';
require __DIR__ . '/../scripts/functions.php';
$twig = registerFunc();
ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF');
    }
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $user = verify_user_credentials($username, $password);
    if ($user) {
        login_user_session($user);
        header('Location: /admin/panel');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}

echo $twig->render('admin/login.twig', ['error' => $error ?? null, 'csrf_token' => generateCsrfToken()]);
