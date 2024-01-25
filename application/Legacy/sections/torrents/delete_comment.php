<?php

authorize();

// Quick SQL injection check
if (!$_GET['postid'] || !is_integer_string($_GET['postid'])) {
    error(0);
}
$postID = $_GET['postid'];

// Make sure they are moderators
if (!check_perms('torrent_post_delete')) {
    error(403);
}

$comment = $master->repos->torrentcomments->load($postID);
$master->repos->torrentcomments->delete($comment);
