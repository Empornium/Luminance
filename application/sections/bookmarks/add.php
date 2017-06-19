<?php

authorize();

if (!can_bookmark($_GET['type'])) { error(404); }
$Feed = new Luminance\Legacy\Feed;
$Text = new Luminance\Legacy\Text;

$Type = $_GET['type'];

list($Table, $Col) = bookmark_schema($Type);

if (!is_number($_GET['id'])) {
    error(0);
}

$DB->query("SELECT UserID FROM $Table WHERE UserID='$LoggedUser[ID]' AND $Col='".db_string($_GET['id'])."'");
if ($DB->record_count() == 0) {
    $DB->query("INSERT IGNORE INTO $Table
        (UserID, $Col, Time)
        VALUES
        ('$LoggedUser[ID]', '".db_string($_GET['id'])."', '".sqltime()."')");
    $Cache->delete_value('bookmarks_'.$Type.'_'.$LoggedUser['ID']);
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
        $Cache->delete_value('bookmarks_torrent_'.$LoggedUser['ID'].'_full');
        $GroupID = $_GET['id'];

        $DB->query("SELECT Name, Body, TagList FROM torrents_group WHERE ID = '$GroupID'");
        list($GroupTitle, $Body, $TagList) = $DB->next_record();
        $TagList = str_replace('_','.',$TagList);

        $DB->query("SELECT ID, FreeTorrent, UserID FROM torrents WHERE GroupID = '$GroupID'");
        // RSS feed stuff
        while ($Torrent = $DB->next_record()) {
            $Title = $GroupTitle;
            list($TorrentID, $Freeleech, $UploaderID) = $Torrent;
            if ($Freeleech == "1") { $Title .= " / Freeleech!"; }
            if ($Freeleech == "2") { $Title .= " / Neutral leech!"; }

            $UploaderInfo = user_info($UploaderID);
            $Item = $Feed->item($Title,
                                $Text->strip_bbcode($Body),
                                'torrents.php?action=download&amp;authkey=[[AUTHKEY]]&amp;torrent_pass=[[PASSKEY]]&amp;id='.$TorrentID,
                                $UploaderInfo['Username'],
                                'torrents.php?id='.$GroupID,
                                trim($TagList));
            $Feed->populate('torrents_bookmarks_t_'.$LoggedUser['torrent_pass'], $Item);
        }
    } elseif ($Type == 'request') {
        $DB->query("SELECT UserID FROM $Table WHERE $Col='".db_string($_GET['id'])."'");
        $Bookmarkers = $DB->collect('UserID');
        $SS->UpdateAttributes('requests requests_delta', array('bookmarker'), array($_GET['id'] => array($Bookmarkers)), true);
    }
}
