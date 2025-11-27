<?php
//database initialization.
$config = include 'config.php';

try {
    $db = new SQLite3($config['postdb']);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$createQuery = <<<SQL
CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    board TEXT NOT NULL,
    isOP INTEGER NOT NULL,
    threadNumber INTEGER NOT NULL,
    name TEXT NOT NULL,
    post TEXT NOT NULL,
    ipaddress TEXT NOT NULL
);
SQL;

if ($db->exec($createQuery)) {
    echo "Created alright";
    exit(0);
} else {
    echo "Could not create tables.";
    exit(-1);
}