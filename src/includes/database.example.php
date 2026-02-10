<?php
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        
        $url = getenv('DATABASE_URL');
        $url = 'stuff'; //uncomment and fill it with the database url. The line above does not always work.
        if (!$url) {
            die('Database URL not configured');
        }
        
        $parts = parse_url($url);
        $host = $parts['host'] ?? 'localhost';
        $port = $parts['port'] ?? 5432;
        $dbname = ltrim($parts['path'] ?? '', '/');
        $user = $parts['user'] ?? '';
        $pass = $parts['pass'] ?? '';
        
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

function initDatabase() {
    $pdo = getDB();
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS boards (
            id SERIAL PRIMARY KEY,
            uri VARCHAR(10) UNIQUE NOT NULL,
            title VARCHAR(100) NOT NULL,
            subtitle VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            post_count INTEGER DEFAULT 0,
            is_nsfw BOOLEAN DEFAULT FALSE,
            is_closed BOOLEAN DEFAULT FALSE,
            rules TEXT
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS board_tags (
            id SERIAL PRIMARY KEY,
            board_id INTEGER REFERENCES boards(id) ON DELETE CASCADE,
            tag_name VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(board_id, tag_name)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id SERIAL PRIMARY KEY,
            key VARCHAR(100) UNIQUE NOT NULL,
            value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ip_whitelist (
            id SERIAL PRIMARY KEY,
            ip_address VARCHAR(45) UNIQUE NOT NULL,
            reason TEXT,
            created_by INTEGER REFERENCES staff(id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gpg_keys (
            id SERIAL PRIMARY KEY,
            key_id VARCHAR(64) UNIQUE NOT NULL,
            public_key TEXT NOT NULL,
            email VARCHAR(255),
            jid VARCHAR(255),
            fingerprint VARCHAR(40),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_verified BOOLEAN DEFAULT FALSE
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id SERIAL PRIMARY KEY,
            board_id INTEGER REFERENCES boards(id) ON DELETE CASCADE,
            thread_id INTEGER REFERENCES posts(id) ON DELETE CASCADE,
            name VARCHAR(75) DEFAULT 'Anonymous',
            tripcode VARCHAR(20),
            email VARCHAR(100),
            subject VARCHAR(100),
            comment TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            file_path VARCHAR(255),
            file_name VARCHAR(255),
            file_size INTEGER,
            file_hash VARCHAR(64),
            thumb_path VARCHAR(255),
            image_width INTEGER,
            image_height INTEGER,
            capcode VARCHAR(20),
            is_sticky BOOLEAN DEFAULT FALSE,
            is_locked BOOLEAN DEFAULT FALSE,
            is_deleted BOOLEAN DEFAULT FALSE,
            deleted_by INTEGER,
            edited_by INTEGER,
            edited_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            bumped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reply_count INTEGER DEFAULT 0,
            gpg_key_id VARCHAR(64),
            gpg_signature TEXT,
            gpg_verified BOOLEAN DEFAULT FALSE,
            is_censored BOOLEAN DEFAULT FALSE
        )
    ");

    // Update existing column sizes if needed
    try {
        $pdo->exec("ALTER TABLE gpg_keys ALTER COLUMN key_id TYPE VARCHAR(64)");
    } catch (Exception $e) {
        // Column might already be the right size, ignore error
    }

    try {
        $pdo->exec("ALTER TABLE posts ALTER COLUMN gpg_key_id TYPE VARCHAR(64)");
    } catch (Exception $e) {
        // Column might already be the right size, ignore error
    }

    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN is_censored BOOLEAN DEFAULT FALSE");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') === false) {
            throw $e;
        }
    }
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'janitor',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bans (
            id SERIAL PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            board_id INTEGER REFERENCES boards(id) ON DELETE CASCADE,
            reason TEXT,
            public_reason TEXT,
            expires_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            staff_id INTEGER REFERENCES staff(id),
            is_global BOOLEAN DEFAULT FALSE
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reports (
            id SERIAL PRIMARY KEY,
            post_id INTEGER REFERENCES posts(id) ON DELETE CASCADE,
            ip_address VARCHAR(45),
            reason TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP,
            resolved_by INTEGER REFERENCES staff(id),
            resolution VARCHAR(20)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS word_filters (
            id SERIAL PRIMARY KEY,
            word VARCHAR(100) NOT NULL,
            replacement VARCHAR(100) NOT NULL,
            created_by INTEGER REFERENCES staff(id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mod_logs (
            id SERIAL PRIMARY KEY,
            staff_id INTEGER REFERENCES staff(id),
            action VARCHAR(50) NOT NULL,
            target_type VARCHAR(20),
            target_id INTEGER,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS flood_control (
            id SERIAL PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action_type VARCHAR(20) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            id SERIAL PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            staff_id INTEGER REFERENCES staff(id),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_posts_board ON posts(board_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_posts_thread ON posts(thread_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_posts_bumped ON posts(bumped_at DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bans_ip ON bans(ip_address)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_flood_ip ON flood_control(ip_address, action_type)");
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM boards");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO boards (uri, title, subtitle) VALUES
            ('b', 'Random', 'Party of the hard variety'),
            ('a', 'Anime/Manga', 'Anime and manga discussion'),
            ('q', 'lain.rocks meta', 'Site discussion and feedback'),
            ('wsg', 'Worksafe GIF', 'Worksafe GIFs, videos and images'),
            ('int', 'International', 'Solution to the refugee crisis.'),

        ");

        // Add default tags
        $pdo->exec("INSERT INTO board_tags (board_id, tag_name) VALUES
            ((SELECT id FROM boards WHERE uri = 'b'), 'General'),
            ((SELECT id FROM boards WHERE uri = 'a'), 'Interests'),
            ((SELECT id FROM boards WHERE uri = 'q'), 'Meta'),
            ((SELECT id FROM boards WHERE uri = 'wsg'), 'Interests'),
            ((SELECT id FROM boards WHERE uri = 'int'), 'General')
        ");
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM staff");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO staff (username, password_hash, role) VALUES ('admin', ?, 'admin')")->execute([$hash]);
    }
}
