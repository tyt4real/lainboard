<?php
// Dedupe duplicate post IDs across posts_* tables by remapping duplicates
// - makes a timestamped backup of the DB
// - finds ids that appear in more than one posts_* table
// - for each duplicate instance (except the first seen), reserves a new id from
//   global_post_ids (INSERT) and reinserts the row with the new id, deletes the
//   old row, and updates threadNumber references across all tables from old->new
// - updates sqlite_sequence for each posts_* table to MAX(id)

$root = __DIR__ . '/..';
$config = include $root . '/config.php';
$dbPath = $config['postdb'];

if (!file_exists($dbPath)) {
    echo "DB file not found: $dbPath\n";
    exit(1);
}

$bak = $dbPath . '.bak.' . date('Ymd_His');
if (!copy($dbPath, $bak)) {
    echo "Failed to copy DB to backup $bak\n";
    exit(1);
}
echo "Backup created: $bak\n";

$db = new SQLite3($dbPath);
@$db->busyTimeout(2000);

// ensure central table exists
$db->exec("CREATE TABLE IF NOT EXISTS global_post_ids (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at INTEGER)");

// collect posts_* tables
$tables = [];
$res = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'posts_%'");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
    $tables[] = $r['name'];
}

if (empty($tables)) {
    echo "No posts_* tables found. Nothing to do.\n";
    exit(0);
}

echo "Found tables: " . implode(', ', $tables) . "\n";

// Build id -> [tables...] map
$idMap = [];
foreach ($tables as $t) {
    $q = $db->query("SELECT id FROM \"$t\"");
    while ($row = $q->fetchArray(SQLITE3_ASSOC)) {
        $id = (int)$row['id'];
        if (!isset($idMap[$id])) $idMap[$id] = [];
        $idMap[$id][] = $t;
    }
}

// find duplicates
$duplicates = [];
foreach ($idMap as $id => $list) {
    if (count($list) > 1) $duplicates[$id] = $list;
}

if (empty($duplicates)) {
    echo "No duplicate ids found.\n";
    exit(0);
}

echo "Duplicate ids found: " . count($duplicates) . "\n";

$mapping = []; // old id => [ table => new id ]

foreach ($duplicates as $oldId => $tablesWithId) {
    echo "Processing duplicate id=$oldId in tables: " . implode(', ', $tablesWithId) . "\n";
    // keep first table as canonical
    $first = array_shift($tablesWithId);
    echo "  keeping in $first\n";

    foreach ($tablesWithId as $tbl) {
        // fetch the row to move
        // select rowid explicitly (alias to avoid driver mapping inconsistencies)
        $stmt = $db->prepare("SELECT rowid AS _rid, name, post, ipaddress, isOP, threadNumber, created_at, bumped_at FROM \"$tbl\" WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $oldId, SQLITE3_INTEGER);
        $r = $stmt->execute();
        $row = $r ? $r->fetchArray(SQLITE3_ASSOC) : false;
        if (!$row) {
            echo "   warning: row not found in $tbl for id=$oldId (skipping)\n";
            continue;
        }

        // Reserve a new global id
        $db->exec('BEGIN');
        $db->exec("INSERT INTO global_post_ids (created_at) VALUES (" . time() . ")");
        $newId = $db->lastInsertRowID();

        // Insert row with new id into same table
        $ins = $db->prepare("INSERT INTO \"$tbl\" (id, name, post, ipaddress, isOP, threadNumber, created_at, bumped_at) VALUES (:id, :name, :post, :ip, :isOP, :tn, :c, :b)");
        $ins->bindValue(':id', $newId, SQLITE3_INTEGER);
        $ins->bindValue(':name', $row['name'], SQLITE3_TEXT);
        $ins->bindValue(':post', $row['post'], SQLITE3_TEXT);
        $ins->bindValue(':ip', $row['ipaddress'], SQLITE3_TEXT);
        $ins->bindValue(':isOP', $row['isOP'], SQLITE3_INTEGER);
        $ins->bindValue(':tn', $row['threadNumber'], SQLITE3_INTEGER);
        $ins->bindValue(':c', $row['created_at'], SQLITE3_INTEGER);
        $ins->bindValue(':b', $row['bumped_at'], SQLITE3_INTEGER);
        $ok = $ins->execute();
        if (!$ok) {
            echo "   failed to insert new row in $tbl for old id=$oldId (rolling back)\n";
            $db->exec('ROLLBACK');
            continue;
        }

        // delete old row using the selected rowid
        $oldRowId = isset($row['_rid']) ? $row['_rid'] : null;
        if ($oldRowId !== null) {
            $del = $db->prepare("DELETE FROM \"$tbl\" WHERE rowid = :rid");
            $del->bindValue(':rid', $oldRowId, SQLITE3_INTEGER);
            $del->execute();
        } else {
            echo "   warning: could not determine rowid for $tbl:$oldId — leaving old row (this may leave duplicates)\n";
        }

        // update threadNumber references across all tables
        foreach ($tables as $t2) {
            $db->exec("UPDATE \"$t2\" SET threadNumber = " . $newId . " WHERE threadNumber = " . $oldId);
        }

        $db->exec('COMMIT');

        echo "   remapped $tbl:$oldId -> $newId\n";
        if (!isset($mapping[$oldId])) $mapping[$oldId] = [];
        $mapping[$oldId][$tbl] = $newId;
    }
}

    // Update sqlite_sequence for each posts_* table to MAX(id)
foreach ($tables as $t) {
    $mx = $db->querySingle("SELECT COALESCE(MAX(id), 0) FROM \"$t\"");
    if ($mx === null) $mx = 0;
    // sqlite_sequence is an internal table; attempt to update if present
        try {
            $db->exec("INSERT OR REPLACE INTO sqlite_sequence (name, seq) VALUES ('" . $t . "', " . intval($mx) . ")");
        } catch (Exception $e) {
            // ignore if sqlite_sequence is not writable/available
        }
    echo "Updated sqlite_sequence for $t => $mx\n";
}

echo "Dedupe complete. Mappings:\n";
foreach ($mapping as $old => $m) {
    foreach ($m as $tbl => $n) {
        echo "  $tbl: $old -> $n\n";
    }
}

echo "Done. Backup left at: $bak\n";

// final duplicate check
$dups = [];
$idMap2 = [];
foreach ($tables as $t) {
    $q = $db->query("SELECT id FROM \"$t\"");
    while ($row = $q->fetchArray(SQLITE3_ASSOC)) {
        $id = (int)$row['id'];
        if (!isset($idMap2[$id])) $idMap2[$id] = [];
        $idMap2[$id][] = $t;
    }
}
foreach ($idMap2 as $id => $l) if (count($l) > 1) $dups[$id] = $l;
if (empty($dups)) {
    echo "Post-merge duplicate check: no duplicates found.\n";
} else {
    echo "Post-merge duplicates remain: " . count($dups) . "\n";
    foreach ($dups as $id => $l) echo "  id=$id in tables: " . implode(', ', $l) . "\n";
}

exit(0);
