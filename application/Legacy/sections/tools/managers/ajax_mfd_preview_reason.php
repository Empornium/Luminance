<?php
/* AJAX Previews, simple stuff. */
if (!check_perms('torrent_review_manage')) error(403);
$bbCode = new \Luminance\Legacy\Text;

if (!empty($_POST['description'])) {
    echo $bbCode->full_format($_POST['description'], true, true);
}
