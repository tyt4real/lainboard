<?php
    header('Content-Type: application/atom+xml; charset=UTF-8');
    $boardId = $_GET['boardid'];
    $page = $_GET['page'];
    

    $threads = getThreads($boardId, $page);
    $updated = $threads[0]['bumped_at'] ?? date('c');

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
//generates an atom file for a board.
?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>/<?= htmlspecialchars($boardId) ?>/</title>
    <id>https://boards.lain.rocks/<?= $boardId ?>/</id>
    <updated><?= date(DATE_ATOM, strtotime($updated)) ?></updated>
    <link rel="self" href="https://boards.lain.rocks/atom.xml?boardid=<?=$boardid?>&page=<?=$page?>"/>

<?php foreach ($threads as $thread): ?>
<?php
    $replies = getReplies($thread['id']);
?>
<entry>
    <title>
        <?= htmlspecialchars($thread['subject'] ?: 'Thread #' . $thread['id']) ?>
    </title>

    <id>https://boards.lain.rocks/<?= $boardId ?>/thread/<?= $thread['id'] ?></id>
    <link href="https://boards.lain.rocks/<?= $boardId ?>/thread/<?= $thread['id'] ?>"/>

    <published><?= date(DATE_ATOM, strtotime($thread['created_at'])) ?></published>
    <updated><?= date(DATE_ATOM, strtotime($thread['bumped_at'])) ?></updated>

    <author>
        <name>
            <?= htmlspecialchars(
                $thread['name'] .
                ($thread['tripcode'] ? ' !' . $thread['tripcode'] : '')
            ) ?>
        </name>
    </author>

    <summary>
        <?= (int)$thread['reply_count'] ?> replies
    </summary>

    <content type="html">
        <![CDATA[
            <article>
                <section class="op">
                    <h3>OP</h3>
                    <p><?= nl2br(htmlspecialchars($thread['comment'])) ?></p>

                    <?php if ($thread['file_path']): ?>
                        <img src="https://boards.lain.rocks/<?= $thread['file_path'] ?>">
                    <?php endif; ?>
                </section>

                <?php if ($replies): ?>
                    <hr>
                    <section class="replies">
                        <h3>Replies</h3>

                        <?php foreach ($replies as $reply): ?>
                            <div class="reply" id="p<?= $reply['id'] ?>">
                                <strong>
                                    <?= htmlspecialchars(
                                        $reply['name'] .
                                        ($reply['tripcode'] ? ' !' . $reply['tripcode'] : '')
                                    ) ?>
                                </strong>
                                <small><?= htmlspecialchars($reply['created_at']) ?></small>

                                <p><?= nl2br(htmlspecialchars($reply['comment'])) ?></p>

                                <?php if ($reply['file_path']): ?>
                                    <img src="https://boards.lain.rocks/<?= $reply['file_path'] ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            </article>
        ]]>
    </content>
</entry>
<?php endforeach; ?>


</feed>
