<?php
function registerFunc()
{
    $twig = require 'scripts/twigInstance.php';
    $formatPost = function ($text, $threadId = null) {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $lines = explode("\n", $escaped);
        $outLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^((?:&gt;)+)\s*(\d+)?\s*(.*)$/', $line, $m)) {
                $chevrons = $m[1];
                $num = isset($m[2]) && $m[2] !== '' ? $m[2] : null;
                $rest = isset($m[3]) ? $m[3] : '';
                $level = preg_match_all('/&gt;/', $chevrons, $matches) ? count($matches[0]) : 0;

                if ($num !== null) {
                    $href = $threadId ? '/thread/' . intval($threadId) . '#p' . intval($num) : '#p' . intval($num);
                    $out = '<a class="quotelink" href="' . $href . '" data-postid="' . intval($num) . '">&gt;&gt;' . intval($num) . '</a>';
                    if ($rest !== '') {
                        $out .= ' ' . $rest;
                    }
                    $outLines[] = $out;
                } else {
                    $outLines[] = '<span class="quote level-' . $level . '">' . str_repeat('&gt;', $level) . ' ' . $rest . '</span>';
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

        $manualLinks = [];

        $dynamicLinks = [];
        foreach ($pages as $slug => $pageData) {
            $dynamicLinks[] = [
                'href' => '/' . urlencode($slug) . '/',
                'label' => ucfirst($slug),
                'target' => '_self'
            ];
        }

        $navbarLinks = array_merge($dynamicLinks, $manualLinks);
        $html = '';
        $html .= '<div style="text-align: center">';
        $html .= '  <div id="navbar" class="content">';
        $html .= '    <div class="mt-2 card">';
        $html .= '      <div class="card-body">';

        $lastIndex = count($navbarLinks) - 1;
        foreach ($navbarLinks as $index => $link) {
            $html .= '<a href="' . htmlspecialchars($link['href']) . '" target="' . htmlspecialchars($link['target']) . '">'
                . htmlspecialchars($link['label']) . '</a>';
            if ($index !== $lastIndex) {
                $html .= ' | ';
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
    //Render posts. //
    $twig->addFunction(new \Twig\TwigFunction('renderPosts', function () use ($formatPost) {
        $config = include 'config.php';
        try {
            $db = new SQLite3($config['postdb']);
            $page = 1;
            if (isset($_GET['page'])) {
                $page = max(1, intval($_GET['page']));
            }
            $perPage = isset($config['threads_per_page']) ? (int)$config['threads_per_page'] : 20;
            $offset = ($page - 1) * $perPage;
            $countRes = $db->querySingle("SELECT COUNT(*) as cnt FROM posts WHERE isOP = 1");
            $totalThreads = $countRes !== null ? (int)$countRes : 0;
            $totalPages = $perPage > 0 ? max(1, (int)ceil($totalThreads / $perPage)) : 1;

            $stmt = $db->prepare("SELECT * FROM posts WHERE isOP = 1 ORDER BY COALESCE(bumped_at, created_at) DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $perPage, SQLITE3_INTEGER);
            $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if ($result) {
                $previewLength = isset($config['board_preview_length']) ? (int)$config['board_preview_length'] : 400;
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM posts WHERE threadNumber = :tn AND id != :tn");
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
                            . '<span class="replyCount">(%d replies)</span>'
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
                        $row['name'], // name
                        $ts, // timestamp (for data-utc)
                        $displayDate, // formatted date/time
                        $row['id'],   // thread ID in <a href="/thread/ID"> (No.)
                        $row['id'],   // thread id for quoted link
                        $row['id'],   // quote id (post id)
                        $row['id'],   // anchor id in fragment
                        $row['id'],   // data-postid attribute
                        $row['id'],   // displayed post number
                        $replyCount,  // reply count
                        $row['id'],   // blockquote id
                        $formatPost($opPostText, $row['id'])  // truncated post content (formatted)
                    );

                    echo $postHtml;

                    // Fetch latest reply for preview (exclude OP itself)
                    // Get the most recent reply by created_at for preview
                    $previewStmt = $db->prepare("SELECT id, name, post, created_at FROM posts WHERE threadNumber = :tn AND id != :tn ORDER BY created_at DESC LIMIT 1");
                    $previewStmt->bindValue(':tn', $row['id'], SQLITE3_INTEGER);
                    $previewRes = $previewStmt->execute();
                    if ($previewRes) {
                        $pr = $previewRes->fetchArray(SQLITE3_ASSOC);
                        if ($pr) {
                            $pname = htmlspecialchars($pr['name'], ENT_QUOTES, 'UTF-8');
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
                            $previewHtml .= '<a href="/thread/' . $row['id'] . '" title="Link to this thread">No.</a>';
                            $previewHtml .= '<a href="/thread/' . $row['id'] . '?quote=' . $pr['id'] . '#p' . $pr['id'] . '" class="quoteLink" data-postid="' . $pr['id'] . '" title="Quote this post">' . $pr['id'] . '</a>';
                            $previewHtml .= '</span></div>';
                            $previewHtml .= '<blockquote class="postMessage preview" id="m' . $pr['id'] . '">' . $formatPost($pr['post'], $row['id']) . '</blockquote>';
                            $previewHtml .= '</div></div>';
                            echo $previewHtml;
                        }
                    }
                }

                // Render pagination controls
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
                echo '</div>';

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

        $countRes = $db->querySingle("SELECT COUNT(*) as cnt FROM posts WHERE isOP = 1");
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
    $twig->addFunction(new \Twig\TwigFunction('renderThread', function ($fid) use ($formatPost) {
        $config = include 'config.php';
        try {
            $db = new SQLite3($config['postdb']);
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
        $stmt = $db->prepare("SELECT * FROM posts WHERE threadNumber = :threadNumber ORDER BY id ASC");
        $stmt->bindValue(':threadNumber', $fid, SQLITE3_INTEGER);
        $result = $stmt->execute();

        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
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
                    $row['name'], // namew
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
            return [];
        }

        $stmt = $db->prepare("SELECT threadNumber, COUNT(*) as reply_count FROM posts WHERE isOP = 0 GROUP BY threadNumber ORDER BY reply_count DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $popularThreads = [];
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $popularThreads[] = [
                    'threadNumber' => (int)$row['threadNumber'],
                    'reply_count' => (int)$row['reply_count']
                ];
            }
        }

        $db->close();
        //return $popularThreads;
        //render the threads in a html table.
        //TODO: all the threads show up with the thread number 0. fix that shiet
        $html = '<table class="popular-threads"><tr><th>Thread No.</th><th>Replies</th></tr>';
        foreach ($popularThreads as $thread) {
            $html .= '<tr>';
            $html .= '<td><a href="/thread/' . intval($thread['threadNumber
']) . '">No. ' . intval($thread['threadNumber']) . '</a></td>';
            $html .= '<td>' . intval($thread['reply_count']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return new \Twig\Markup($html, 'UTF-8');
    }));
    return $twig;
}
