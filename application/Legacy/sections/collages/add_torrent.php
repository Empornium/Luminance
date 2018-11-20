<?php
authorize();

$Val = new Luminance\Legacy\Validate;

function AddTorrent($CollageID, $GroupID)
{
    global $Cache, $LoggedUser, $DB;

    $DB->query("SELECT MAX(Sort) FROM collages_torrents WHERE CollageID='$CollageID'");
    list($Sort) = $DB->next_record();
    $Sort+=10;

    $DB->query("SELECT GroupID FROM collages_torrents WHERE CollageID='$CollageID'");
    $GroupIDs = $DB->collect('GroupID');
    if (!in_array($GroupID, $GroupIDs)) {
        $DB->query("INSERT IGNORE INTO collages_torrents
            (CollageID, GroupID, UserID, Sort, AddedOn)
            VALUES
            ('$CollageID', '$GroupID', '$LoggedUser[ID]', '$Sort', '".sqltime()."')");

        $DB->query("UPDATE collages SET NumTorrents=NumTorrents+1 WHERE ID='$CollageID'");
        $DB->query("SELECT COUNT(GroupID) AS NumGroups FROM collages_torrents WHERE CollageID='$CollageID'");
        list($NumGroups) = $DB->next_record();

        /*$TorrentsPerPage = TORRENTS_PER_PAGE;
        $Pages = max(1, ceil((float)$NumGroups/(float)$TorrentsPerPage));
        for ($Page=1; $Page <= $Pages; $Page++) {
            $Cache->delete_value('collage_'.$CollageID.'_'.$Page);
        }*/
        $Cache->delete_value('collage_'.$CollageID);
        $Cache->delete_value('collage_torrents_'.$CollageID);

        $GroupIDs[] = $GroupID; // Clear cache for the torrent we've just added as well
        foreach ($GroupIDs as $GID) {
            $Cache->delete_value('torrents_details_'.$GID);
            $Cache->delete_value('torrent_collages_'.$GID);
            $Cache->delete_value('torrent_collages_personal_'.$GID);
        }

        $DB->query("SELECT UserID FROM users_collage_subs WHERE CollageID=$CollageID");
        while (list($CacheUserID) = $DB->next_record()) {
            $Cache->delete_value('collage_subs_user_new_'.$CacheUserID);
        }
    }
}

$CollageID = $_POST['collageid'];
if (!is_number($CollageID)) {
    error(404);
}
$DB->query("SELECT UserID, Name, CategoryID, Locked, NumTorrents, MaxGroups, MaxGroupsPerUser, Permissions FROM collages WHERE ID='$CollageID'");
list($UserID, $CategoryID, $Name, $Locked, $NumTorrents, $MaxGroups, $MaxGroupsPerUser, $CPermissions) = $DB->next_record();
//if ($CategoryID == 0 && $UserID!=$LoggedUser['ID'] && !check_perms('site_collages_delete')) { error(403); }
if (!check_perms('site_collages_manage')) {
    $CPermissions=(int) $CPermissions;
    if ($UserID == $LoggedUser['ID']) {
          $CanEdit = true;
    } elseif ($CPermissions>0) {
          $CanEdit = $LoggedUser['Class'] >= $CPermissions;
    } else {
          $CanEdit=false; // can be overridden by permissions
    }
    if (!$CanEdit) {
        error(403);
    }
}

if ($Locked) {
    error(403);
}
if ($MaxGroups>0 && $NumTorrents>=$MaxGroups) {
    error(403);
}
if ($MaxGroupsPerUser>0) {
    $DB->query("SELECT COUNT(ID) FROM collages_torrents WHERE CollageID='$CollageID' AND UserID='$LoggedUser[ID]'");
    if ($DB->record_count()>=$MaxGroupsPerUser) {
        error(403);
    }
}

$URLRegex = '/'.TORRENT_REGEX.'/i';
if ($_REQUEST['action'] == 'add_torrent') {
    $Val->SetFields('url', '1', 'regex', 'The URL must be a link to a torrent on the site.', array('regex'=>'/'.TORRENT_REGEX.'/i'));
    $Err = $Val->ValidateForm($_POST);

    if ($Err) {
        error($Err);
    }

    $URL = $_POST['url'];

    // Get torrent ID
    preg_match($URLRegex, $URL, $Matches);
    $TorrentID=$Matches[2];
    if (!$TorrentID || (int) $TorrentID == 0) {
        error(404);
    }

    $DB->query("SELECT ID FROM torrents_group WHERE ID='$TorrentID'");
    list($GroupID) = $DB->next_record();
    if (!$GroupID) {
        error('The torrent was not found in the database.');
    }

    AddTorrent($CollageID, $GroupID);
      write_log("Collage ".$CollageID." (".db_string($Name).") was edited by ".$LoggedUser['Username']." - added torrents $GroupID");
} else {
    $URLs = explode("\n", $_REQUEST['urls']);
    $GroupIDs = array();
    $Err = '';

    foreach ($URLs as $URL) {
        $URL = trim($URL);
        if ($URL == '') {
            continue;
        }

        $Matches = array();
        if (preg_match($URLRegex, $URL, $Matches)) {
            $GroupIDs[] = $Matches[2];
            $GroupID    = $Matches[2];
        } else {
            $Err = "One of the entered URLs ($URL) does not correspond to a torrent on the site.";
            break;
        }

        $DB->query("SELECT ID FROM torrents_group WHERE ID='$GroupID'");
        if (!$DB->record_count()) {
            $Err = "One of the entered URLs (".display_str($URL).") does not correspond to a torrent on the site.";
            break;
        }
    }

    if ($Err) {
        error($Err);
    }

    foreach ($GroupIDs as $GroupID) {
        AddTorrent($CollageID, $GroupID);
    }

    write_log("Collage ".$CollageID." (".db_string($Name).") was edited by ".$LoggedUser['Username']." - added torrents ".implode(',', $GroupIDs));
}

header('Location: collages.php?id='.$CollageID);
