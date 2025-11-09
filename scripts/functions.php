<?php
function registerFunc()
{
    $twig = require 'scripts/twigInstance.php';
    //Render Navbar
    $twig->addFunction(new \Twig\TwigFunction('renderNavbar', function () {
        $config = include 'config.php';

        $pages = $config['pages'];

        $manualLinks = [];

        $dynamicLinks = [];
        foreach ($pages as $slug => $pageData) {
            $dynamicLinks[] = [
                'href' => '?page=' . urlencode($slug),
                'label' => ucfirst($slug),
                'target' => '_self'
            ];
        }

        $navbarLinks = array_merge($dynamicLinks, $manualLinks);
        echo '<div style="text-align: center">';
        echo '  <div id="navbar" class="content">';
        echo '    <div class="mt-2 card">';
        echo '      <div class="card-body">';

        $lastIndex = count($navbarLinks) - 1;
        foreach ($navbarLinks as $index => $link) {
            echo '<a href="' . htmlspecialchars($link['href']) . '" target="' . htmlspecialchars($link['target']) . '">'
                . htmlspecialchars($link['label']) . '</a>';
            if ($index !== $lastIndex) {
                echo ' | ';
            }
        }

        echo '      </div>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
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

        echo $htmlOutput;
    }));

    // Render the header. //
    $twig->addFunction(new \Twig\TwigFunction('renderHeader', function () {
        $config = include 'config.php';
        $htmlOutput = '';

        $htmlOutput = '<div id="header">
    <h1 id="titleText">';
        $htmlOutput .= $config['headertitle'];
        $htmlOutput .= '</h1>';
        $htmlOutput .= $config['headersubtitle'];
        $htmlOutput .= '</div>';

        echo $htmlOutput;
    }));
    //Render rules under post menu
    $twig->addFunction(new \Twig\TwigFunction('renderRules', function () {
        $config = include 'config.php';
        $htmlOutput = '';
        $htmlOutput .= '<li>Please read the <a href="';
        $htmlOutput .= $config['ruleslink'];
        $htmlOutput .= '">Rules</a> before posting.</li>';
        echo $htmlOutput;
    }));
    //Render posts. //
    $twig->addFunction(new \Twig\TwigFunction('renderPosts', function () {
        $config = include 'config.php';
        try {
            $db = new SQLite3($config['postdb']);

            $result = $db->query("SELECT * FROM posts");
            if ($result) {
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    //echo "Post: {$row['name']} ({$row['post']})\n";
                    $postHtml = sprintf(
                        '<div class="postContainer replyContainer" id="pc%d">
        <div class="sideArrows" id="sa%d">&gt;&gt;</div>
        <div id="p%d" class="post reply">
            <div class="postInfo desktop" id="pi%d">
                <span class="nameBlock">
                    <span class="name">%s</span>
                </span>
                <span class="dateTime" data-utc="%d">%s</span>
                <span class="postNum desktop">
                    <a href="/" title="Link to this post">No.</a>
                    <a href="/" title="Reply to this post">%d</a>
                </span>
                <a href="#" class="postMenuBtn" title="Post menu" data-cmd="post-menu">▶</a>
            </div>
            <blockquote class="postMessage" id="m%d">
                %s
            </blockquote>
        </div>
    </div>',
                        $row['id'],
                        $row['id'],
                        $row['id'],
                        $row['id'],
                        $row['name'],
                        $row['created_at'],
                        $row['created_at'],
                        $row['id'],
                        $row['id'],
                        $row['post']
                    );
                    echo $postHtml;
                }
                $db->close();
            } else {
                echo "Error fetching posts";
            }
        } catch (Exception $e) {
            echo ("Database connection failed: " . $e->getMessage());
        }
    }));
    return $twig;
}
