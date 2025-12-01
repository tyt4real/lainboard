<?php
return [
    //Twig config
    'debug' => true,
    'templatedir' => 'templates',
    'cachedir' => 'cache',

    //*    Forum config
    'ratelimittime' => 120, //120 seconds cooldown
    //Site Header
    'headertitle' => 'lain.rocks',
    'headersubtitle' => '<p>The (hopefully) cool place on the internet</p>
    <p>「どこに行ったって、人はつながっているのよ。」</p>',
    //Site Footer
    'footertext' => "Let's all love Lain. Copyleft 2025",
    'footernotice' => 'For all abuse complaints, contact us here: <a href="mailto:tyt4real@protonmail.com">tyt4real@protonmail.com</a>',
    //Site Post Menu
    'ruleslink' => '/rules.php',

    // How many reply anchors to show under an OP on the board index (defaults to 8)
    'max_reply_anchors' => 8,

    //Post location
    'postdb' => 'path to database, canonical path recommended',

    //board configuration
    'pages' => [
        'board' => [
            'template' => 'board.twig',
        ],
    ],
];
