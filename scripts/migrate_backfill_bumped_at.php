<?php
// Backfill bumped_at for existing threads.
// Usage: php scripts/migrate_backfill_bumped_at.php

$config = include __DIR__ . '/../config.php';
$dbPath = $config['postdb'];

if (!file_exists($dbPath)) {
    fwrite(STDERR, "Database file not found: $dbPath\n");
    exit(1);
}

$backup = $dbPath . '.' . date('Ymd_His') . '.bak';
if (!copy($dbPath, $backup)) {
    fwrite(STDERR, "Failed to create backup at $backup\n");
    exit(1);
}

echo "Backup created: $backup\n";

try {
    $db = new SQLite3($dbPath);
} catch (Exception $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$hasBumped = false;
$info = $db->query("PRAGMA table_info('posts')");
if ($info) {
    while ($col = $info->fetchArray(SQLITE3_ASSOC)) {
        if (isset($col['name']) && $col['name'] === 'bumped_at') {
            $hasBumped = true;
            break;
        }
    }
}
if (!$hasBumped) {
    echo "Adding bumped_at column...\n";
    @$db->exec("ALTER TABLE posts ADD COLUMN bumped_at INTEGER");
}

$stmt = $db->prepare("SELECT id, created_at FROM posts WHERE isOP = 1");
$res = $stmt->execute();
$count = 0;
while ($op = $res->fetchArray(SQLITE3_ASSOC)) {
    $opId = (int)$op['id'];
    $opCreated = (int)$op['created_at'];

    $q = $db->prepare("SELECT MAX(created_at) as max_created FROM posts WHERE threadNumber = :tn");
    $q->bindValue(':tn', $opId, SQLITE3_INTEGER);
    $r = $q->execute();
    $row = $r->fetchArray(SQLITE3_ASSOC);
    $max = $row && isset($row['max_created']) && $row['max_created'] ? (int)$row['max_created'] : $opCreated;

    if ($max === 0) {
        $max = $opCreated;
    }

    $u = $db->prepare("UPDATE posts SET bumped_at = :bumped WHERE id = :id");
    $u->bindValue(':bumped', $max, SQLITE3_INTEGER);
    $u->bindValue(':id', $opId, SQLITE3_INTEGER);
    $u->execute();

    echo "Thread {$opId} bumped_at set to {$max}\n";
    $count++;
}

echo "Backfilled bumped_at for {$count} threads.\n";
$db->close();
exit(0);
