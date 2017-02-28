<?php
// echo out the slice of the form needed for the selected upload type ($_GET['section']).

// this is probably broken now... but is currently unused, and has been supplanted by the new category/tag system

// Include the necessary form class
include(SERVER_ROOT.'/classes/class_torrent_form.php');
$TorrentForm = new TORRENT_FORM();

$GenreTags = $Cache->get_value('genre_tags');
if (!$GenreTags) {
    $DB->query('SELECT Name FROM tags WHERE TagType=\'genre\' ORDER BY Name');
    $GenreTags =  $DB->collect('Name');
    $Cache->cache_value('genre_tags', $GenreTags, 3600*24);
}

$TorrentForm->simple_form($GenreTags);
