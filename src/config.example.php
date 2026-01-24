<?php
define('SITE_NAME', 'urname');
define('SITE_TAGLINE', 'stuff');
define('SITE_QUOTE', '「どこにいたって、人はつながっているのよ。」');

define('TRIPCODE_SALT', 'somethingcomplex');
define('SECURE_TRIPCODE_SALT', 'somethingcomplex');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('THUMB_DIR', __DIR__ . '/uploads/thumbnails/');
define('MAX_FILE_SIZE', 4 * 1024 * 1024);
define('THUMB_WIDTH', 200);
define('THUMB_HEIGHT', 200);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

define('FLOOD_TIME', 30);
define('THREAD_COOLDOWN', 120);
define('MAX_COMMENT_LENGTH', 8000);
define('MAX_NAME_LENGTH', 75);
define('MAX_SUBJECT_LENGTH', 100);
define('THREADS_PER_PAGE', 10);
define('REPLIES_SHOWN', 5);
define('BUMP_LIMIT', 300);
define('POST_COUNT_DIGITS', 6);

define('DATABASE_URL', 'postgresql://username:password@localhost:5432/database_name');

define('DEFAULT_ANONYMOUS_NAME', 'Anonymous');

$capcodes = [
    'admin' => ['name' => '## Administrator', 'color' => '#FF0000', 'rank' => 1],
    'mod' => ['name' => '## Moderator', 'color' => '#800080', 'rank' => 2],
    'dev' => ['name' => '## Developer', 'color' => '#0000FF', 'rank' => 3],
    'janitor' => ['name' => '## Janitor', 'color' => '#006400', 'rank' => 4]
];

$permissions = [
    'admin' => ['delete_posts', 'restore_posts', 'edit_posts', 'ban_users', 'view_ips', 'manage_boards', 'manage_users', 'view_logs', 'lock_threads', 'sticky_threads', 'view_reports'],
    'mod' => ['delete_posts', 'restore_posts', 'edit_posts', 'ban_users', 'view_ips', 'view_logs', 'lock_threads', 'sticky_threads', 'view_reports'],
    'dev' => ['delete_posts', 'view_logs'],
    'janitor' => ['hide_posts', 'view_reports']
];
