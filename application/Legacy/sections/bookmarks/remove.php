<?php
authorize();

if (!can_bookmark($_GET['type'])) { error(404); }

$Type = $_GET['type'];

list($Table, $Col) = bookmark_schema($Type);

if (!is_number($_GET['id'])) {
    error(0);
}

$DB->query("DELETE FROM $Table WHERE UserID='".$LoggedUser['ID']."' AND $Col='".db_string($_GET['id'])."'");
$Cache->delete_value('bookmarks_'.$Type.'_'.$UserID);
if ($Type == 'torrent') {
    if (isset($LoggedUser['TorrentsPerPage'])) {
        $TorrentsPerPage = $LoggedUser['TorrentsPerPage'];
    } else {
        $TorrentsPerPage = TORRENTS_PER_PAGE;
    }
    $DB->query("SELECT COUNT(*) FROM bookmarks_torrents WHERE UserID='$LoggedUser[ID]'");
    list($NumGroups) = $DB->next_record();
    $PageLimit = ceil((float)$NumGroups/(float)$TorrentsPerPage);
    for($Page = 0; $Page <= $PageLimit; $Page++) {
      $Cache->delete_value('bookmarks_torrent_'.$LoggedUser['ID'].'_page_'.$Page);
    }
    $Cache->delete_value('bookmarks_torrent_'.$UserID.'_full');
} elseif ($Type == 'request') {
    $DB->query("SELECT UserID FROM $Table WHERE $Col='".db_string($_GET['id'])."'");
    $Bookmarkers = $DB->collect('UserID');
    $SS->UpdateAttributes('requests requests_delta', array('bookmarker'), array($_GET['id'] => array($Bookmarkers)), true);
}
