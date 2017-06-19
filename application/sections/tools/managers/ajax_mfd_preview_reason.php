<?php
/* AJAX Previews, simple stuff. */
if (!check_perms('torrents_review_manage')) error(403);
$Text = new Luminance\Legacy\Text;

if (!empty($_POST['description'])) {
    echo $Text->full_format($_POST['description'], true, true);
}
