<?php
/* AJAX Previews, simple stuff. */
if (!check_perms('torrents_review_manage')) error(403);
include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

if (!empty($_POST['description'])) {
    echo $Text->full_format($_POST['description'], true, true);
}
