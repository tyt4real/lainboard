<?php
/**
 * Migrate rows from legacy `posts` table into per-board tables named posts_{slug}.
 *
 * Usage: php scripts/migrate_posts_to_board_tables.php
 *
 * This script will:
 *  - create a backup copy of the SQLite DB file (timestamped)
 *  - iterate all rows in `posts` and insert them into the board-specific tables
 *  - preserve id, created_at and bumped_at where possible
 *
 * Note: The legacy `posts` table is not dropped. Review the backup before removing it.
 */

require_once __DIR__ . '/utils.php';

$config = include __DIR__ . '/../config.php';
$dbFile = $config['postdb'];

if (!file_exists($dbFile)) {
    fwrite(STDERR, "Database file not found: $dbFile\n");
    exit(1);
}

// create backup
$ts = date('Ymd_His');
$backup = $dbFile . '.' . $ts . '.bak';
if (!copy($dbFile, $backup)) {
    fwrite(STDERR, "Failed to create backup at $backup\n");
    exit(1);
}
echo "Backup created: $backup\n";

try {
    $db = new SQLite3($dbFile);
} catch (Exception $e) {
    fwrite(STDERR, "Failed opening DB: " . $e->getMessage() . "\n");
    exit(1);
}

// Check if legacy posts table exists
$q = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
if (!$q) {
    echo "No legacy 'posts' table found. Nothing to migrate.\n";
    $db->close();
    exit(0);
}

$rows = $db->query("SELECT * FROM posts ORDER BY id ASC");
if (!$rows) {
    echo "No rows to migrate or failed to read legacy table.\n";
    $db->close();
    exit(0);
}

$migrated = 0;
while ($r = $rows->fetchArray(SQLITE3_ASSOC)) {
    // Legacy schema expected fields: id, name, post, ipaddress, isOP, board, threadNumber, created_at, bumped_at
    $board = isset($r['board']) && $r['board'] !== null && $r['board'] !== '' ? $r['board'] : 'b';
    $table = boardTableName($board);

    // Ensure target table exists
    $db->exec("CREATE TABLE IF NOT EXISTS " . $table . " (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        post TEXT,
        ipaddress TEXT,
        isOP INTEGER,
        threadNumber INTEGER,
        created_at INTEGER,
        bumped_at INTEGER
    )");

    // Insert preserving id if possible
    $stmt = $db->prepare("INSERT OR REPLACE INTO " . $table . " (id, name, post, ipaddress, isOP, threadNumber, created_at, bumped_at) VALUES (:id, :name, :post, :ip, :isop, :tn, :created, :bumped)");
    $stmt->bindValue(':id', isset($r['id']) ? $r['id'] : null, SQLITE3_INTEGER);
    $stmt->bindValue(':name', isset($r['name']) ? $r['name'] : '', SQLITE3_TEXT);
    $stmt->bindValue(':post', isset($r['post']) ? $r['post'] : '', SQLITE3_TEXT);
    $stmt->bindValue(':ip', isset($r['ipaddress']) ? $r['ipaddress'] : '', SQLITE3_TEXT);
    $stmt->bindValue(':isop', isset($r['isOP']) ? $r['isOP'] : 0, SQLITE3_INTEGER);
    $stmt->bindValue(':tn', isset($r['threadNumber']) ? $r['threadNumber'] : 0, SQLITE3_INTEGER);
    $stmt->bindValue(':created', isset($r['created_at']) ? $r['created_at'] : time(), SQLITE3_INTEGER);
    $stmt->bindValue(':bumped', isset($r['bumped_at']) ? $r['bumped_at'] : null, SQLITE3_INTEGER);

    $res = $stmt->execute();
    if ($res) {
        $migrated++;
    } else {
        fwrite(STDERR, "Failed to insert row id " . ($r['id'] ?? 'unknown') . " into $table\n");
    }
}

echo "Migration complete. Migrated $migrated rows into per-board tables.\n";
echo "Legacy 'posts' table remains untouched; review backup before removing.\n";

$db->close();
exit(0);
