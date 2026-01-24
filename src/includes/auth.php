<?php
require_once __DIR__ . '/database.php';

function login($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE username = ? AND is_active = TRUE");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['staff_id'] = $user['id'];
        $_SESSION['staff_username'] = $user['username'];
        $_SESSION['staff_role'] = $user['role'];
        
        $stmt = $pdo->prepare("UPDATE staff SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        return true;
    }
    return false;
}

function logout() {
    unset($_SESSION['staff_id']);
    unset($_SESSION['staff_username']);
    unset($_SESSION['staff_role']);
}

function isLoggedIn() {
    return isset($_SESSION['staff_id']);
}

function getCurrentStaff() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['staff_id'],
        'username' => $_SESSION['staff_username'],
        'role' => $_SESSION['staff_role']
    ];
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /admin/login');
        exit;
    }
}

function requirePermission($permission) {
    requireLogin();
    $staff = getCurrentStaff();
    if (!hasPermission($staff['role'], $permission)) {
        http_response_code(403);
        die('Permission denied');
    }
}
