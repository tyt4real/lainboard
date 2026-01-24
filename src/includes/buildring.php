<?php

chdir(dirname(__FILE__));
chdir('..');

require_once('webring/webring-config.php');
require_once('database.php');

$webRingPath = '/var/www/lainboard/includes/webring/webring.json';
$knownHostsPath = '/var/www/lainboard/includes/webring/known_hosts.json';
$outputPath = '/var/www/lainboard/includes/webring/compiled_webring.json';

function getLocalBoards($url){
    $pdo = getDB();

    $boardsStmt = $pdo->query("SELECT * FROM boards ORDER BY uri");
    $baseBoards = $boardsStmt->fetchAll(PDO::FETCH_ASSOC);

    $boards = [];

    foreach ($baseBoards as $board) {
        $boardId = (int)$board['id'];

        // Posts per hour
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM posts 
            WHERE board_id = ? 
              AND created_at > NOW() - INTERVAL '1 hour'
              AND is_deleted = FALSE
        ");
        $stmt->execute([$boardId]);
        $postsPerHour = (int)$stmt->fetchColumn();

        // Unique users (24h)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address)
            FROM posts
            WHERE board_id = ?
              AND created_at > NOW() - INTERVAL '24 hours'
              AND is_deleted = FALSE
        ");
        $stmt->execute([$boardId]);
        $uniqueUsers = (int)$stmt->fetchColumn();

        // Total posts
        $totalPosts = (int)$board['post_count'];

        // Last non-sage post
        $stmt = $pdo->prepare("
            SELECT created_at
            FROM posts
            WHERE board_id = ?
              AND (email IS NULL OR email NOT ILIKE 'sage')
              AND is_deleted = FALSE
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$boardId]);
        $lastPost = $stmt->fetchColumn();

        if ($lastPost) {
            $datetime = new DateTime($lastPost);
            $lastPostTimestamp = $datetime->format(DateTime::ATOM);
        } else {
            $lastPostTimestamp = null;
        }

        $boards[] = [
            'uri' => $board['uri'],
            'title' => $board['title'],
            'subtitle' => $board['subtitle'],
            'path' => rtrim($url, '/') . '/' . $board['uri'],
            'nsfw' => (bool)$board['is_nsfw'],
            'postsPerHour' => $postsPerHour,
            'uniqueUsers' => $uniqueUsers,
            'totalPosts' => $totalPosts,
            'lastPostTimestamp' => $lastPostTimestamp,
            'tags' => [] // optional, safe to leave empty
        ];
    }

    return $boards;
}


function isKnownHost($currentHost, $knownHosts){
    for ($j = 0; $j < count($knownHosts); $j++){
        if (!empty(parse_url($currentHost)['host']) && parse_url($currentHost)['host'] == parse_url($knownHosts[$j])['host']){
            return TRUE;
        }
    }
    return FALSE;
}

function isBlacklisted($currentHost, $knownHosts){
    for ($j = 0; $j < count($knownHosts); $j++){
        if (!empty(parse_url($currentHost)['host']) && parse_url($currentHost)['host'] == $knownHosts[$j]){
            return TRUE;
        }
    }
    return FALSE;
}

$knownHostsFile = @file_get_contents($knownHostsPath);

/* Only needed for working out if the blacklist has been updated TODO
$baseJsonFile = file_get_contents($webRingPath);
$baseJson = json_decode($baseJsonFile, TRUE);
if ($baseJson == NULL || $baseJson == FALSE){
    console.log('Missing or malformed webring.json.');
    die();
}*/

//The "known" field is now deprecated for security issues, for legacy reasons it will still be included in the produced webring.json however it will no longer be considered when spidering the webring.
//$knownHosts = @json_decode($knownHostsFile, TRUE);
$knownHosts = NULL;
if ($knownHosts == NULL || $knownHosts == FALSE)
    $knownHosts = array();

for ($i = 0; $i < count($webring['following']); $i++)
    if (!isKnownHost($webring['following'][$i], $knownHosts))
        $knownHosts[] = $webring['following'][$i];

$compiledJson = array();

for ($i = 0; $i < count($knownHosts); $i++){
    $currentRingFile = @file_get_contents($knownHosts[$i]);
    if ($currentRingFile == FALSE)
        continue;
    $currentRingJson = @json_decode($currentRingFile, TRUE);
    if ($currentRingJson == FALSE)
        continue;
    
    //TODO, try getting TOR jsons with curl --socks5 localhost:9050 --socks5-hostname localhost:9050 -s http://bhm5koavobq353j54qichcvzr6uhtri6x4bjjy4xkybgvxkzuslzcqid.onion/webring.json
    
    $compiledJson[] = $currentRingJson;

    for ($j = 0; !empty($currentRingJson['following']) && $j < count($currentRingJson['following']); $j++){
        if (!isKnownHost($currentRingJson['following'][$j], $knownHosts) && !isBlacklisted($currentRingJson['following'][$j], $webring['blacklist'])){
            if ($currentRingJson['following'][$j] != $webring['endpoint']){
                $knownHosts[] = $currentRingJson['following'][$j];
            }
        }
    }
}
$webring['known'] = $knownHosts;
$webring['boards'] = getLocalBoards($webring['url']);

//$compiledJson[] = $webring; TODO insert the webring base at the start

file_put_contents($webRingPath, json_encode($webring));
file_put_contents($knownHostsPath, json_encode($knownHosts));
file_put_contents($outputPath, json_encode($compiledJson));

