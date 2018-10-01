<?php
$Text = new Luminance\Legacy\Text;

authorize();

// Quick SQL injection check
if (!$_GET['postid'] || !is_number($_GET['postid'])) {
    error(0);
}
$PostID = $_GET['postid'];

// Make sure they are moderators
if (!check_perms('site_moderate_forums')) {
    error(403);
}

$DB->query("SELECT CollageID FROM collages_comments WHERE ID='$PostID'");
list($CollageID) = $DB->next_record();

$DB->query("DELETE FROM collages_comments WHERE ID='$PostID'");

$Cache->delete_value('collage_comments_'.$CollageID);
