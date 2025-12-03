<?php
function registerFunc()
{
    $twig = require 'scripts/twigInstance.php';
    $formatPost = function ($text, $threadId = null) {
        // Determine current board for building thread links
        $config = include 'config.php';
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($requestUri, '/'))));
        $currentBoard = $segments[0] ?? null;
        $defaultBoard = array_key_first($config['pages']) ?: 'b';
        if ($currentBoard === null || !isset($config['pages'][$currentBoard])) {
            $currentBoard = $defaultBoard;
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        //$escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $escaped = $text;

        $lines = explode("\n", $escaped);
        $outLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^((?:&gt;)+)\s*(\d+)?\s*(.*)$/', $line, $m)) {
                $chevrons = $m[1];
                $num = isset($m[2]) && $m[2] !== '' ? $m[2] : null;
                $rest = isset($m[3]) ? $m[3] : '';
                $level = preg_match_all('/&gt;/', $chevrons, $matches) ? count($matches[0]) : 0;

                if ($num !== null) {
                    $href = $threadId ? '/' . rawurlencode($currentBoard) . '/thread/' . intval($threadId) . '#p' . intval($num) : '#p' . intval($num);
                    $out = '<a class="quotelink" href="' . $href . '" data-postid="' . intval($num) . '">&gt;&gt;' . intval($num) . '</a>';
                    if ($rest !== '') {
                        $out .= ' ' . $rest;
                    }
                    $outLines[] = $out;
                } else {
                    // Support cross-board reference syntax like >>>/b/123 or >>>/g/456
                    if ($level >= 3 && preg_match('#^/([A-Za-z0-9_]+)/([0-9]+)(.*)$#', $rest, $m2)) {
                        $targetBoard = $m2[1];
                        $targetId = intval($m2[2]);
                        $trailing = isset($m2[3]) ? $m2[3] : '';
                        $href = '/' . rawurlencode($targetBoard) . '/thread/' . $targetId . '#p' . $targetId;
                        $out = '<a class="quotelink crossboard" href="' . $href . '">&gt;&gt;&gt;/' . htmlspecialchars($targetBoard) . '/' . $targetId . '</a>';
                        if ($trailing !== '') {
                            $out .= ' ' . ltrim($trailing);
                        }
                        $outLines[] = $out;
                    } else {
                        $outLines[] = '<span class="quote level-' . $level . '">' . str_repeat('&gt;', $level) . ' ' . $rest . '</span>';
                    }
                }
            } else {
                $outLines[] = $line;
            }
        }

        return implode('<br>', $outLines);
    };
    //Render Navbar
    $twig->addFunction(new \Twig\TwigFunction('renderNavbar', function () {
        $config = include 'config.php';

        $pages = $config['pages'];

        $manualLinks = [['href' => '/home', 'label' => 'Home', 'target' => '_blank'],['href' => '/overboard', 'label' => 'overboard', 'target' => '_blank'],];

        $dynamicLinks = [];
        foreach ($pages as $slug => $pageData) {
            $dynamicLinks[] = [
                'href' => '/' . urlencode($slug) . '/',
                'label' => ucfirst($slug),
                'target' => '_self'
            ];
        }

        $navbarLinks = array_merge($manualLinks, $dynamicLinks);
        $html = '';
        $html .= '<div style="text-align: center">';
        $html .= '  <div id="navbar" class="content">';
        $html .= '    <div class="mt-2 card">';
        $html .= '      <div class="card-body">';

        $lastIndex = count($navbarLinks) - 1;
        foreach ($navbarLinks as $index => $link) {
            $html .= '<a href="' . htmlspecialchars($link['href']) . '" target="' . htmlspecialchars($link['target']) . '">/'
                . strtolower(htmlspecialchars($link['label'])) . '/</a>';
            if ($index !== $lastIndex) {
                $html .= '   |   ';
            }
        }

        $html .= '      </div>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';

        return new \Twig\Markup($html, 'UTF-8');
    }));



    // Render the footer. //
    $twig->addFunction(new \Twig\TwigFunction('renderFooter', function () {
        $config = include 'config.php';
        $htmlOutput = '';
        $htmlOutput .= '<div> <p>';
        $htmlOutput .= $config['footertext'];
        $htmlOutput .= '</p><p>';
        $htmlOutput .= $config['footernotice'];
        $htmlOutput .= '</p></div>';

        return new \Twig\Markup($htmlOutput, 'UTF-8');
    }));

    // Render the header. //
    $twig->addFunction(new \Twig\TwigFunction('renderHeader', function () {
        $config = include 'config.php';
        $htmlOutput = '';

        $htmlOutput = '<div id="header">';
        $htmlOutput .= '<h1 id="titleText">' . $config['headertitle'] . '</h1>';
        $htmlOutput .= $config['headersubtitle'];
        $htmlOutput .= '</div>';

        return new \Twig\Markup($htmlOutput, 'UTF-8');
    }));
    //Render rules under post menu
    $twig->addFunction(new \Twig\TwigFunction('renderRules', function () {
        $config = include 'config.php';
        $htmlOutput = '';
        $htmlOutput .= '<li>Please read the <a href="';
        $htmlOutput .= $config['ruleslink'];
        $htmlOutput .= '">Rules</a> before posting.</li>';
        return new \Twig\Markup($htmlOutput, 'UTF-8');
    }));

    // Render list of available boards for the homepage
    $twig->addFunction(new \Twig\TwigFunction('renderBoards', function () {
        $config = include 'config.php';
        $pages = $config['pages'] ?? [];
        $html = '<div class="boards-list">';
        $html .= '<ul style="list-style:none;padding:0;margin:0;">';
        foreach ($pages as $slug => $pdata) {
            $label = isset($pdata['boardname']) ? htmlspecialchars($pdata['boardname']) : htmlspecialchars($slug);
            $html .= '<li style="margin:6px 0;"><a href="/' . rawurlencode($slug) . '/">/' . htmlspecialchars($slug) . '/</a> - ' . $label . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        return new \Twig\Markup($html, 'UTF-8');
    }));
    //Render posts. //
    $twig->addFunction(new \Twig\TwigFunction('renderPosts', function () use ($formatPost) {
        $config = include 'config.php';
        require_once 'scripts/utils.php';
        try {
            $db = new SQLite3($config['postdb']);
            // determine current board from request URI (first path segment)
            $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $segments = array_values(array_filter(explode('/', trim($requestUri, '/'))));
            $board = $segments[0] ?? null;
            // Validate board exists in config; fallback to first configured board or 'b'
            $defaultBoard = array_key_first($config['pages']) ?: 'b';
            if ($board === null || !isset($config['pages'][$board])) {
                $board = $defaultBoard;
            }
            $table = boardTableName($board);

            $page = 1;
            if (isset($_GET['page'])) {
                $page = max(1, intval($_GET['page']));
            }
            $perPage = isset($config['threads_per_page']) ? (int)$config['threads_per_page'] : 20;
            $offset = ($page - 1) * $perPage;
            // Ensure per-board table exists to avoid prepare() returning false
            // include user_id to track authenticated authors
            $db->exec("CREATE TABLE IF NOT EXISTS " . $table . " (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                post TEXT,
                ipaddress TEXT,
                isOP INTEGER,
                threadNumber INTEGER,
                created_at INTEGER,
                bumped_at INTEGER,
                user_id INTEGER
            )");

            $countRes = $db->querySingle("SELECT COUNT(*) as cnt FROM " . $table . " WHERE isOP = 1");
            $totalThreads = $countRes !== null ? (int)$countRes : 0;
            $totalPages = $perPage > 0 ? max(1, (int)ceil($totalThreads / $perPage)) : 1;

            // Prepare lookup by user id (authoritative). We do not rely on username-based lookup
            // to decide capcodes to prevent impersonation.
            $roleLookupById = $db->prepare("SELECT r.name as role FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = :id LIMIT 1");
            // Fallback to users.role if user_roles not set for legacy accounts (still keyed by user id).
            $roleLookupUserTable = $db->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");

            $stmt = $db->prepare("SELECT * FROM " . $table . " WHERE isOP = 1 ORDER BY COALESCE(bumped_at, created_at) DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $perPage, SQLITE3_INTEGER);
            $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if ($result) {
                $previewLength = isset($config['board_preview_length']) ? (int)$config['board_preview_length'] : 400;
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    // determine display name with capcode if user exists
                    //verify if the users session corresponds to an admin user
                    
                        
                        $displayName = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                        // Only grant capcode based on authoritative user_id mapping.
                        if (isset($row['user_id']) && !empty($row['user_id'])) {
                            $uid = intval($row['user_id']);
                            $role = null;
                            if ($roleLookupById) {
                                $roleLookupById->bindValue(':id', $uid, SQLITE3_INTEGER);
                                $rlRes = $roleLookupById->execute();
                                if ($rlRes) {
                                    $rrole = $rlRes->fetchArray(SQLITE3_ASSOC);
                                    if ($rrole && !empty($rrole['role'])) {
                                        $role = $rrole['role'];
                                    }
                                }
                            }
                            // legacy fallback to users.role column (still keyed by id)
                            if ($role === null && $roleLookupUserTable) {
                                $roleLookupUserTable->bindValue(':id', $uid, SQLITE3_INTEGER);
                                $ru = $roleLookupUserTable->execute();
                                if ($ru) {
                                    $rrow = $ru->fetchArray(SQLITE3_ASSOC);
                                    if ($rrow && !empty($rrow['role'])) {
                                        $role = $rrow['role'];
                                    }
                                }
                            }
                            if ($role !== null && in_array($role, ['administrator', 'moderator'])) {
                                $roleLabel = htmlspecialchars(ucfirst($role));
                                $displayName = '<span class="name capcode cap-' . htmlspecialchars($role) . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . ' <span class="capcode-label">## ' . $roleLabel . '</span></span>';
                            }
                        }
                    
                    $countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM " . $table . " WHERE threadNumber = :tn AND id != :tn");
                    $countStmt->bindValue(':tn', $row['id'], SQLITE3_INTEGER);
                    $countRes = $countStmt->execute();
                    $replyCount = 0;
                    if ($countRes) {
                        $c = $countRes->fetchArray(SQLITE3_ASSOC);
                        $replyCount = (int)$c['cnt'];
                    }

                    $ts = (int)$row['created_at'];
                    $displayDate = date('m/d/Y H:i', $ts);

                    $opPostText = $row['post'];
                    if (function_exists('mb_strlen')) {
                        if (mb_strlen($opPostText) > $previewLength) {
                            $opPostText = mb_substr($opPostText, 0, $previewLength) . '...';
                        }
                    } else {
                        if (strlen($opPostText) > $previewLength) {
                            $opPostText = substr($opPostText, 0, $previewLength) . '...';
                        }
                    }

                    // Build reply anchor list (show up to configured number of recent replies)
                    $maxDisplay = isset($config['max_reply_anchors']) ? (int)$config['max_reply_anchors'] : 8;
                    $replyList = [];
                    $replyListHtml = '';
                    if ($replyCount > 0) {
                        // fetch most recent reply IDs for this thread
                        $rlStmt = $db->prepare("SELECT id, created_at FROM " . $table . " WHERE threadNumber = :tn AND id != :tn ORDER BY created_at DESC LIMIT :limit");
                        $rlStmt->bindValue(':tn', $row['id'], SQLITE3_INTEGER);
                        $rlStmt->bindValue(':limit', $maxDisplay, SQLITE3_INTEGER);
                        $rlRes = $rlStmt->execute();
                        if ($rlRes) {
                            while ($r = $rlRes->fetchArray(SQLITE3_ASSOC)) {
                                $replyList[] = $r;
                            }
                        }
                        // replyList currently in newest-first order; reverse to show oldest-first among shown
                        $replyList = array_reverse($replyList);
                        $replyListHtml .= '<span class="replyList">';
                        foreach ($replyList as $r) {
                            $rid = intval($r['id']);
                            $replyListHtml .= '<a class="replyAnchor" href="/' . rawurlencode($board) . '/thread/' . $row['id'] . '?quote=' . $rid . '#p' . $rid . '">&gt;&gt;' . $rid . '</a> ';
                        }
                        if ($replyCount > count($replyList)) {
                            $more = $replyCount - count($replyList);
                            $replyListHtml .= '<span class="replyMore">+' . $more . '</span>';
                        }
                        $replyListHtml .= '</span>';
                    } else {
                        $replyListHtml = '<span class="replyList">(0 replies)</span>';
                    }

                    $postHtml = sprintf(
                        '<div class="postContainer replyContainer" id="pc%d">'
                            . '<div class="sideArrows" id="sa%d">&gt;&gt;</div>'
                            . '<div id="p%d" class="post op">'
                            . '<div class="postInfo desktop" id="pi%d">'
                            . '<span class="nameBlock">'
                            . '<span class="name">%s</span>'
                            . '</span>'
                            . '<span class="dateTime" data-utc="%d">%s</span>'
                            . '<span class="postNum desktop">'
                            . '<a href="/thread/%d" title="View thread">No.</a>'
                            . '<a href="/thread/%d?quote=%d#p%d" class="quoteLink" data-postid="%d" title="Quote this post">%d</a>'
                            . '</span>'
                            . '%s'
                            . '<a href="#" class="postMenuBtn" title="Post menu" data-cmd="post-menu">▶</a>'
                            . '</div>'
                            . '<blockquote class="postMessage" id="m%d">'
                            . '%s'
                            . '</blockquote>'
                            . '</div>'
                            . '</div>',
                        $row['id'],   // pc id
                        $row['id'],   // sa id
                        $row['id'],   // p id
                        $row['id'],   // pi id
                        $displayName, // name (may include capcode HTML)
                        $ts, // timestamp (for data-utc)
                        $displayDate, // formatted date/time
                        $row['id'],   // thread ID in <a href="/thread/ID"> (No.)
                        $row['id'],   // thread id for quoted link
                        $row['id'],   // quote id (post id)
                        $row['id'],   // anchor id in fragment
                        $row['id'],   // data-postid attribute
                        $row['id'],   // displayed post number
                        $replyListHtml, // html list of reply anchors (or fallback)
                        $row['id'],   // blockquote id
                        $formatPost($opPostText, $row['id'])  // truncated post content (formatted)
                    );

                    // Ensure generated /thread/ links include the board prefix
                    $postHtml = str_replace('href="/thread/', 'href="/' . rawurlencode($board) . '/thread/', $postHtml);
                    echo $postHtml;

                    // Fetch latest reply for preview (exclude OP itself)
                    // Get the most recent reply by created_at for preview
                    $previewStmt = $db->prepare("SELECT id, name, post, created_at FROM " . $table . " WHERE threadNumber = :tn AND id != :tn ORDER BY created_at DESC LIMIT 1");
                    $previewStmt->bindValue(':tn', $row['id'], SQLITE3_INTEGER);
                    $previewRes = $previewStmt->execute();
                    if ($previewRes) {
                        $pr = $previewRes->fetchArray(SQLITE3_ASSOC);
                        if ($pr) {
                            // preview post name capcode
                            //todo: verify if the post was made by an admin user
                            $pname = htmlspecialchars($pr['name'], ENT_QUOTES, 'UTF-8');
                            // For preview reply, only consider capcode when reply row includes user_id
                            if (isset($pr['user_id']) && !empty($pr['user_id'])) {
                                $puid = intval($pr['user_id']);
                                $prole = null;
                                if ($roleLookupById) {
                                    $roleLookupById->bindValue(':id', $puid, SQLITE3_INTEGER);
                                    $prRoleRes = $roleLookupById->execute();
                                    if ($prRoleRes) {
                                        $prRoleRow = $prRoleRes->fetchArray(SQLITE3_ASSOC);
                                        if ($prRoleRow && !empty($prRoleRow['role'])) {
                                            $prole = $prRoleRow['role'];
                                        }
                                    }
                                }
                                if ($prole === null && $roleLookupUserTable) {
                                    $roleLookupUserTable->bindValue(':id', $puid, SQLITE3_INTEGER);
                                    $pru = $roleLookupUserTable->execute();
                                    if ($pru) {
                                        $rr = $pru->fetchArray(SQLITE3_ASSOC);
                                        if ($rr && !empty($rr['role'])) $prole = $rr['role'];
                                    }
                                }
                                if ($prole !== null && in_array($prole, ['administrator', 'moderator'])) {
                                    $pname = '<span class="name capcode cap-' . htmlspecialchars($prole) . '">' . htmlspecialchars($pr['name'], ENT_QUOTES, 'UTF-8') . ' <span class="capcode-label">## ' . htmlspecialchars(ucfirst($prole)) . '</span></span>';
                                }
                            }
                            $pcontent = htmlspecialchars($pr['post'], ENT_QUOTES, 'UTF-8');
                            $ptimestamp = (int)$pr['created_at'];
                            $pdisplay = date('m/d/Y H:i', $ptimestamp);

                            $previewHtml = '';
                            $previewHtml .= '<div class="postContainer replyContainer lastReplyPreview" id="pc_preview_' . $row['id'] . '">';
                            $previewHtml .= '<div class="sideArrows" id="sa_preview_' . $row['id'] . '">&gt;&gt;</div>';
                            $previewHtml .= '<div id="p' . $pr['id'] . '" class="post reply previewReply">';
                            $previewHtml .= '<div class="postInfo desktop" id="pi' . $pr['id'] . '">';
                            $previewHtml .= '<span class="nameBlock"><span class="name">' . $pname . '</span></span>';
                            $previewHtml .= '<span class="dateTime" data-utc="' . $ptimestamp . '">' . $pdisplay . '</span>';
                            $previewHtml .= '<span class="postNum desktop">';
                            $previewHtml .= '<a href="/' . rawurlencode($board) . '/thread/' . $row['id'] . '" title="Link to this thread">No.</a>';
                            $previewHtml .= '<a href="/' . rawurlencode($board) . '/thread/' . $row['id'] . '?quote=' . $pr['id'] . '#p' . $pr['id'] . '" class="quoteLink" data-postid="' . $pr['id'] . '" title="Quote this post">' . $pr['id'] . '</a>';
                            $previewHtml .= '</span></div>';
                            $previewHtml .= '<blockquote class="postMessage preview" id="m' . $pr['id'] . '">' . $formatPost($pr['post'], $row['id']) . '</blockquote>';
                            $previewHtml .= '</div></div>';
                            echo $previewHtml;
                        }
                    }
                }

/*                 // Render pagination controls
                echo '<div class="pagination">';
                if ($page > 1) {
                    $prev = $page - 1;
                    echo '<a class="page prev" href="?page=' . $prev . '">&laquo; Prev</a> ';
                }
                // page numbers (show a window around current page)
                $start = max(1, $page - 3);
                $end = min($totalPages, $page + 3);
                for ($p = $start; $p <= $end; $p++) {
                    if ($p == $page) {
                        echo '<span class="page current">' . $p . '</span> ';
                    } else {
                        echo '<a class="page" href="?page=' . $p . '">' . $p . '</a> ';
                    }
                }
                // next
                if ($page < $totalPages) {
                    $next = $page + 1;
                    echo '<a class="page next" href="?page=' . $next . '">Next &raquo;</a>';
                }
                echo '</div>'; */

                $db->close();
            } else {
                echo "Error fetching posts";
            }
        } catch (Exception $e) {
            echo ("Database connection failed: " . $e->getMessage());
        }
    }));
    $twig->addFunction(new \Twig\TwigFunction('renderPagination', function () {
        $config = include 'config.php';
        try {
            $db = new SQLite3($config['postdb']);
        } catch (Exception $e) {
            return '';
        }

        $page = 1;
        if (isset($_GET['page'])) {
            $page = max(1, intval($_GET['page']));
        }
        $perPage = isset($config['threads_per_page']) ? (int)$config['threads_per_page'] : 20;

        // determine current board and table
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($requestUri, '/'))));
        $board = $segments[0] ?? null;
        $defaultBoard = array_key_first($config['pages']) ?: 'b';
        if ($board === null || !isset($config['pages'][$board])) {
            $board = $defaultBoard;
        }
        $table = boardTableName($board);

        $countRes = $db->querySingle("SELECT COUNT(*) as cnt FROM " . $table . " WHERE isOP = 1");
        $totalThreads = $countRes !== null ? (int)$countRes : 0;
        $totalPages = $perPage > 0 ? max(1, (int)ceil($totalThreads / $perPage)) : 1;

        $params = $_GET;
        $html = '';
        $html .= '<div class="pagination">';
        if ($page > 1) {
            $params['page'] = $page - 1;
            $html .= '<a class="page prev" href="?' . htmlspecialchars(http_build_query($params)) . '">&laquo; Prev</a> ';
        }
        $start = max(1, $page - 3);
        $end = min($totalPages, $page + 3);
        for ($p = $start; $p <= $end; $p++) {
            if ($p == $page) {
                $html .= '<span class="page current">' . $p . '</span> ';
            } else {
                $params['page'] = $p;
                $html .= '<a class="page" href="?' . htmlspecialchars(http_build_query($params)) . '">' . $p . '</a> ';
            }
        }
        if ($page < $totalPages) {
            $params['page'] = $page + 1;
            $html .= '<a class="page next" href="?' . htmlspecialchars(http_build_query($params)) . '">Next &raquo;</a>';
        }
        $html .= '</div>';

        $db->close();
        return new \Twig\Markup($html, 'UTF-8');
    }));
    //render a single thread
    $twig->addFunction(new \Twig\TwigFunction('renderThread', function ($fid, $board = null) use ($formatPost) {
        $config = include 'config.php';
        try {
            $db = new SQLite3($config['postdb']);
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
        // Determine board: prefer passed-in board, otherwise infer from request URI and validate
        if ($board === null) {
            $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $segments = array_values(array_filter(explode('/', trim($requestUri, '/'))));
            $board = $segments[0] ?? null;
        }
        $defaultBoard = array_key_first($config['pages']) ?: 'b';
        if ($board === null || !isset($config['pages'][$board])) {
            $board = $defaultBoard;
        }
        $table = boardTableName($board);
        // Ensure table exists so later SELECT/prepare calls succeed
        $db->exec("CREATE TABLE IF NOT EXISTS " . $table . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            post TEXT,
            ipaddress TEXT,
            isOP INTEGER,
            threadNumber INTEGER,
            created_at INTEGER,
            bumped_at INTEGER,
            user_id INTEGER
        )");
        $stmt = $db->prepare("SELECT * FROM " . $table . " WHERE threadNumber = :threadNumber ORDER BY id ASC");
        $stmt->bindValue(':threadNumber', $fid, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result) {
            $roleLookupById = $db->prepare("SELECT r.name as role FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = :id LIMIT 1");
            $roleLookupUserTable = $db->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $displayName = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                if (isset($row['user_id']) && !empty($row['user_id'])) {
                    $uid = intval($row['user_id']);
                    $role = null;
                    if ($roleLookupById) {
                        $roleLookupById->bindValue(':id', $uid, SQLITE3_INTEGER);
                        $rl = $roleLookupById->execute();
                        if ($rl) {
                            $rr = $rl->fetchArray(SQLITE3_ASSOC);
                            if ($rr && !empty($rr['role'])) {
                                $role = $rr['role'];
                            }
                        }
                    }
                    if ($role === null && $roleLookupUserTable) {
                        $roleLookupUserTable->bindValue(':id', $uid, SQLITE3_INTEGER);
                        $ru = $roleLookupUserTable->execute();
                        if ($ru) {
                            $rrow = $ru->fetchArray(SQLITE3_ASSOC);
                            if ($rrow && !empty($rrow['role'])) $role = $rrow['role'];
                        }
                    }
                    if ($role !== null && in_array($role, ['administrator', 'moderator'])) {
                        $displayName = '<span class="name capcode cap-' . htmlspecialchars($role) . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . ' <span class="capcode-label">## ' . htmlspecialchars(ucfirst($role)) . '</span></span>';
                    }
                }
                // Format timestamp for thread posts
                $ts = (int)$row['created_at'];
                $displayDate = date('m/d/Y H:i', $ts);

                $postHtml = sprintf(
                    '<div class="postContainer replyContainer" id="pc%d">'
                        . '<div class="sideArrows" id="sa%d">&gt;&gt;</div>'
                        . '<div id="p%d" class="%s">'
                        . '<div class="postInfo desktop" id="pi%d">'
                        . '<span class="nameBlock">'
                        . '<span class="name">%s</span>'
                        . '</span>'
                        . '<span class="dateTime" data-utc="%d">%s</span>'
                        . '<span class="postNum desktop">'
                        . '<a href="/thread/%d#p%d" title="Permalink">No.</a>'
                        . '<a href="#p%d" class="quoteLink" data-postid="%d" title="Quote this post">%d</a>'
                        . '</span>'
                        . '<a href="#" class="postMenuBtn" title="Post menu" data-cmd="post-menu">▶</a>'
                        . '</div>'
                        . '<blockquote class="postMessage" id="m%d">'
                        . '%s'
                        . '</blockquote>'
                        . '</div>'
                        . '</div>',
                    $row['id'],   // pc id
                    $row['id'],   // sa id
                    $row['id'],   // p id
                    ($row['isOP'] ? 'post op' : 'post reply'), // class for the post
                    $row['id'],   // pi id
                    $displayName, // namew (may include capcode)
                    $ts, // timestamp (for data-utc)
                    $displayDate, // formatted date/time
                    $fid, // thread id for permalink
                    $row['id'], // permalink anchor id
                    $row['id'], // quote anchor href fragment
                    $row['id'], // data-postid
                    $row['id'], // displayed post number
                    $row['id'],   // blockquote id
                    $formatPost($row['post'], $fid)  // post content (formatted) - pass current thread id
                );

                echo $postHtml;
            }
        } else {
            echo "Error fetching posts";
        }
    }));
    $twig->addFunction(new \Twig\TwigFunction('getPopularThreads', function ($limit = 6) {
        $config = include 'config.php';
        try {
            $db = new SQLite3($config['postdb']);
        } catch (Exception $e) {
            return '';
        }

        $all = [];
        // Aggregate popular threads across boards defined in config
        foreach ($config['pages'] as $slug => $pageCfg) {
            $table = boardTableName($slug);
            // ensure table exists before trying to query it
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS " . $table . " (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT,
                    post TEXT,
                    ipaddress TEXT,
                    isOP INTEGER,
                    threadNumber INTEGER,
                    created_at INTEGER,
                    bumped_at INTEGER,
                    user_id INTEGER
                )");
            } catch (Exception $e) {
                // if creation fails, skip this board
                continue;
            }
            try {
                $q = $db->prepare("SELECT threadNumber, COUNT(*) as reply_count FROM " . $table . " WHERE isOP = 0 GROUP BY threadNumber");
                if ($q) {
                    $res = $q->execute();
                    if ($res) {
                        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
                            $tn = (int)$r['threadNumber'];
                            $cnt = (int)$r['reply_count'];
                            // store with board info
                            $all[] = ['board' => $slug, 'thread' => $tn, 'count' => $cnt];
                        }
                    }
                }
            } catch (Exception $e) {
                // table may not exist yet or other sqlite issue; ignore
            }
        }

        // sort by count desc
        usort($all, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $html = '<table class="popular-threads"><tr><th>Thread</th><th>Replies</th></tr>';
        $i = 0;
        foreach ($all as $entry) {
            if ($i++ >= $limit) break;
            $html .= '<tr>';
            $html .= '<td><a href="/' . htmlspecialchars($entry['board']) . '/thread/' . intval($entry['thread']) . '">/' . htmlspecialchars($entry['board']) . '/ No. ' . intval($entry['thread']) . '</a></td>';
            $html .= '<td>' . intval($entry['count']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        $db->close();
        return new \Twig\Markup($html, 'UTF-8');
    }));

    // Render an overboard which lists recent OP threads across all boards
    $twig->addFunction(new \Twig\TwigFunction('renderOverboard', function ($limit = 200) use ($formatPost) {
        $config = include 'config.php';
        try {
            $db = new SQLite3($config['postdb']);
        } catch (Exception $e) {
            return new \Twig\Markup('<div class="overboard-error">Error opening DB</div>', 'UTF-8');
        }

        $all = [];
        // iterate boards and collect OPs
        foreach ($config['pages'] as $slug => $pageCfg) {
            $table = boardTableName($slug);
            try {
                // ensure table exists
                $db->exec("CREATE TABLE IF NOT EXISTS " . $table . " (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT,
                    post TEXT,
                    ipaddress TEXT,
                    isOP INTEGER,
                    threadNumber INTEGER,
                    created_at INTEGER,
                    bumped_at INTEGER,
                    user_id INTEGER
                )");
                $q = $db->prepare('SELECT id, name, post, created_at, bumped_at FROM "' . $table . '" WHERE isOP = 1');
                if ($q) {
                    $res = $q->execute();
                    if ($res) {
                        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
                            $r['board'] = $slug;
                            // use bumped_at if present otherwise created_at for sorting
                            $r['sort_ts'] = !empty($r['bumped_at']) ? intval($r['bumped_at']) : intval($r['created_at']);
                            $all[] = $r;
                        }
                    }
                }
            } catch (Exception $e) {
                // ignore this board if any problem
                continue;
            }
        }

        // sort by sort_ts desc
        usort($all, function ($a, $b) { return intval($b['sort_ts']) <=> intval($a['sort_ts']); });
        $all = array_slice($all, 0, max(0, intval($limit)));

        $html = '<div class="overboard">';
        $html .= '<table class="overboard-table" style="width:100%;border-collapse:collapse;">';
        $html .= '<thead><tr><th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Thread</th><th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Board</th><th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">OP</th><th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Preview</th><th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Bumped</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($all as $row) {
            $tid = intval($row['id']);
            $b = htmlspecialchars($row['board']);
            $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
            $preview = htmlspecialchars($row['post'], ENT_QUOTES, 'UTF-8');
            if (function_exists('mb_strlen') && mb_strlen($preview) > 200) {
                $preview = mb_substr($preview, 0, 200) . '...';
            } elseif (strlen($preview) > 200) {
                $preview = substr($preview, 0, 200) . '...';
            }
            $bumpedTs = !empty($row['bumped_at']) ? intval($row['bumped_at']) : intval($row['created_at']);
            $bumped = date('Y-m-d H:i', $bumpedTs);
            $html .= '<tr>';
            $html .= '<td style="padding:6px;border-bottom:1px solid #eee;"><a href="/' . rawurlencode($b) . '/thread/' . $tid . '">No. ' . $tid . '</a></td>';
            $html .= '<td style="padding:6px;border-bottom:1px solid #eee;">/' . $b . '/</td>';
            $html .= '<td style="padding:6px;border-bottom:1px solid #eee;">' . $name . '</td>';
            $html .= '<td style="padding:6px;border-bottom:1px solid #eee;">' . $formatPost($preview, $tid) . '</td>';
            $html .= '<td style="padding:6px;border-bottom:1px solid #eee;">' . $bumped . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';

        $db->close();
        return new \Twig\Markup($html, 'UTF-8');
    }));

    // Render a post counter using gifs in images/counter
    $twig->addFunction(new \Twig\TwigFunction('renderPostCounter', function () {
        $config = include 'config.php';
        try {
            $db = new SQLite3($config['postdb']);
        } catch (Exception $e) {
            return new \Twig\Markup('<div class="post-counter">Error reading DB</div>', 'UTF-8');
        }

        $total = 0;
        $tablesRes = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'posts_%'");
        if ($tablesRes) {
            while ($t = $tablesRes->fetchArray(SQLITE3_ASSOC)) {
                $tname = $t['name'];
                try {
                    $c = $db->querySingle('SELECT COUNT(*) FROM "' . $tname . '"');
                    $total += (int)$c;
                } catch (Exception $e) {
                    // ignore tables we can't read
                }
            }
        }
        $db->close();

        // Build HTML using gif digits found in /images/counter/{digit}.gif
        $digitCount = isset($config['post_counter_digits']) ? (int)$config['post_counter_digits'] : 6;
        $padded = str_pad((string)$total, max(1, $digitCount), '0', STR_PAD_LEFT);
        $digits = str_split($padded);
        $html = '<div class="post-counter" aria-label="Total posts: ' . htmlspecialchars((string)$total) . '">';
        foreach ($digits as $d) {
            if (!preg_match('/^[0-9]$/', $d)) {
                $d = '0';
            }
            $src = '/images/counter/' . $d . '.gif';
            $html .= '<img class="post-counter-digit" src="' . htmlspecialchars($src) . '" alt="' . $d . '" />';
        }
        $html .= '</div>';

        return new \Twig\Markup($html, 'UTF-8');
    }));
    $twig->addFunction(new \Twig\TwigFunction('renderRightSideTopBar', function() {
        //check if the user is logged in, if so grab their username.
        $sessionUsername = $_SESSION['admin_user']['username'] ?? null;
        $html = '';
        if($sessionUsername === null) {
            //not authenticated, render the normal login link.
            $html .= '[<a href="/admin/login">administrator login</a>]';
        } else {
            //authenticated, show them where the panel at :3
            $html .= '[<a href="/admin/panel">logged in as ';
            $html .= $sessionUsername;
            $html .= '</a>]';
        }
        return new \Twig\Markup($html, 'UTF-8');
    }));
    return $twig;
}
