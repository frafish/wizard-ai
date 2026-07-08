<?php
require_once('/var/www/html/wp-load.php');
$comment_data = [
    'comment_post_ID' => 1,
    'comment_author' => 'Test',
    'comment_author_email' => 'test@example.com',
    'comment_content' => 'Test message',
    'comment_type' => 'wai_chat',
    'comment_author_IP' => '127.0.0.1',
    'comment_agent' => 'Mozilla/5.0'
];
$approved = wp_allow_comment($comment_data, true);
var_dump($approved);
