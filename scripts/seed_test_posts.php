<?php
// Seed a few test posts into the posts DB using postToDB helper.
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/wordfilter.php';

function create_post($name, $post, $isOP, $board, $threadNumber = 0) {
    return postToDB($name, $post, '127.0.0.1', $isOP, $board, $threadNumber);
}

// Create two OPs
$op1 = create_post('Anonymous', "Hello world\n>this is greentext test\nNormal line.", 1, 'b', 0);
echo "Created OP1: $op1\n";
$op2 = create_post('Anon2', "Second thread OP\n>Another greentext line\nEnd.", 1, 'b', 0);
echo "Created OP2: $op2\n";

// Add replies to OP1
$reply1 = create_post('Replier', "Reply here\n>reply greentext", 0, 'b', $op1);
echo "Created reply: $reply1\n";
$reply2 = create_post('Anon', "Another reply\nNo greentext", 0, 'b', $op1);
echo "Created reply: $reply2\n";

// Add reply to OP2
$reply3 = create_post('User', ">>nested?\n>greentext only", 0, 'b', $op2);
echo "Created reply to op2: $reply3\n";

echo "Seeding complete.\n";
