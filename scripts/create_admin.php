<?php
// CLI helper to create an admin user: php scripts/create_admin.php <username> <password>
require __DIR__ . '/utils.php';

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from CLI\n";
    exit(1);
}

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;
if (!$username) {
    echo "Usage: php scripts/create_admin.php <username> <password>\n";
    exit(1);
}
if (!$password) {
    // prompt for password
    echo "Enter password: ";
    $password = trim(fgets(STDIN));
}

ensure_admin_tables();
$ok = create_user($username, $password, 'administrator');
if ($ok) {
    $u = get_user_by_username($username);
    if ($u) {
        // ensure role assignment
        assign_role_to_user($u['id'], 'administrator');
        echo "Administrator user created: {$username} (id={$u['id']})\n";
        exit(0);
    }
}
echo "Failed to create user\n";
exit(2);
