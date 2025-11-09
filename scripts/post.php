<?php
// table: posts
$config = include('../config.php');
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING) ?? 'Anonymous';
$comment  = filter_input(INPUT_POST, 'com', FILTER_SANITIZE_STRING);

//Connect to the SQLite databse if it doenst exist then fucking create it i guess
try {
    $db = new SQLite3($config['postdb']);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$createQuery = <<<SQL
CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    post TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    ipaddress TEXT NOT NULL
);
SQL;


if ($db->exec($createQuery)) {
    //posts table created or exists; continue posting
    if ($comment == '' || $comment == NULL) {
        echo "Do not post empty posts, are you stupid?";
    } else {
        // APPLY WORDFILTERS
        require_once 'wordfilter.php';
        $filters = new FilterManager();
        $filters->addFilter(new FunnyFilter());
        //$filters->addFilter(new UrlFilter());
        $filters->addFilter(new CapsFilter());

        //RATELIMIT
        $ip = $_SERVER['REMOTE_ADDR'];
        $limitFile = __DIR__ . "/utilities/rate_limit_$ip.txt";
        $waitTime = $config['ratelimittime'];

        $lastTime = 0;
        if (file_exists($limitFile)) {
            $lastTime = (int)file_get_contents($limitFile);
        }

        $now = time();

        if ($now - $lastTime < $waitTime) {
            $remaining = $waitTime - ($now - $lastTime);
            http_response_code(429); // Too Many Requests
            die("Rate limit exceeded. Try again in $remaining seconds.");
        }

        file_put_contents($limitFile, $now);

        //Post to DB
        $stmt = $db->prepare("INSERT INTO posts (name, post, ipaddress) VALUES (:name, :post, :ipaddr)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':post', $filters->applyFilters($comment), SQLITE3_TEXT);
        $stmt->bindValue(':ipaddr', $ip);
        $result = $stmt->execute();
        if ($result) {
            $db->close();
            //redirect
            header("Location: /");
            die();
        } else {
            echo "Could not post man sry";
        }
    }
} else {
    echo "Error creating table: " . $db->lastErrorMsg() . "\n";
    $db->close();
    exit(-1);
}
