<?php
require __DIR__ . '/../scripts/utils.php';
logout_user_session();
header('Location: /admin/login');
exit;
