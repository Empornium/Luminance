<?php
ini_set('memory_limit', -1);
//~~~~~~~~~~~ Main bookmarks page ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//

authorize(true);

function compare($X, $Y)
{
    return($Y['count'] - $X['count']);
}

if (!empty($_GET['userid'])) {
    if (!check_perms('users_override_paranoia')) {
        error(403);
    }
    $UserID = $_GET['userid'];
    if (!is_number($UserID)) { error(404); }
    $DB->query("SELECT Username FROM users_main WHERE ID='$UserID'");
    list($Username) = $DB->next_record();
} else {
    $UserID = $LoggedUser['ID'];
}

$Sneaky = ($UserID != $LoggedUser['ID']);

$Data = $Cache->get_value('bookmarks_torrent_'.$UserID.'_full');

if ($Data) {
    $Data = unserialize($Data);
    list($K, list($TorrentList, $CollageDataList)) = each($Data);
} else {
    // Build the data for the collage and the torrent list
    $DB->query("SELECT
        bt.GroupID,
        tg.Image,
        tg.NewCategoryID,
        bt.Time
        FROM bookmarks_torrents AS bt
        JOIN torrents_group AS tg ON tg.ID=bt.GroupID
        WHERE bt.UserID='$UserID'
        ORDER BY bt.Time");

    $GroupIDs = $DB->collect('GroupID');
    $CollageDataList=$DB->to_array('GroupID', MYSQLI_ASSOC);
    if (count($GroupIDs)>0) {
        $TorrentList = get_groups($GroupIDs);
        $TorrentList = $TorrentList['matches'];
    } else {
        $TorrentList = array();
    }
}

$Title = ($Sneaky)?"$Username's bookmarked torrents":'Your bookmarked torrents';

// Loop through the result set, building up $Collage and $TorrentTable
// Then we print them.
$Collage = array();
$TorrentTable = '';

$NumGroups = 0;
$Tags = array();

foreach ($TorrentList as $GroupID=>$Group) {
    list($GroupID, $GroupName, $TagList, $Torrents) = array_values($Group);
    list($GroupID2, $Image, $GroupCategoryID, $AddedTime) = array_values($CollageDataList[$GroupID]);

    // Handle stats and stuff
    $NumGroups++;

    $TagList = explode(' ',str_replace('_','.',$TagList));

    $TorrentTags = array();
    $numtags=0;
    foreach ($TagList as $Tag) {
        if ($numtags++>=$LoggedUser['MaxTags'])  break;
        if (!isset($Tags[$Tag])) {
            $Tags[$Tag] = array('name'=>$Tag, 'count'=>1);
        } else {
            $Tags[$Tag]['count']++;
        }
        $TorrentTags[]='<a href="torrents.php?taglist='.$Tag.'">'.$Tag.'</a>';
    }
    $PrimaryTag = $TagList[0];
    $TorrentTags = implode(' ', $TorrentTags);
    $TorrentTags='<br /><div class="tags">'.$TorrentTags.'</div>';

    $DisplayName = '<a href="torrents.php?id='.$GroupID.'" title="View Torrent">'.$GroupName.'</a>';

    // Start an output buffer, so we can store this output in $TorrentTable
    ob_start();
    // Viewing a type that does not require grouping

    list($TorrentID, $Torrent) = each($Torrents);

    $DisplayName = '<a href="torrents.php?id='.$GroupID.'" title="View Torrent">'.$GroupName.'</a>';

    if ($Torrent['ReportCount'] > 0) {
            $Title = "This torrent has ".$Torrent['ReportCount']." active ".($Torrent['ReportCount'] > 1 ?'reports' : 'report');
            $DisplayName .= ' /<span class="reported" title="'.$Title.'"> Reported</span>';
    }

    $AddExtra = torrent_info($Torrent, $TorrentID, $UserID);
    if($AddExtra) $DisplayName .= $AddExtra;

    $TorrentTable.=ob_get_clean();

    // Album art

    ob_start();

    $DisplayName = $GroupName;
    $Collage[]=ob_get_clean();
}

uasort($Tags, 'compare');
$i = 0;
foreach ($Tags as $TagName => $Tag) {
    $i++;
    if ($i>5) { break; }
}

$JsonBookmarks = array();
foreach ($TorrentList as $Torrent) {
    $JsonTorrents = array();
    foreach ($Torrent['Torrents'] as $GroupTorrents) {
        $JsonTorrents[] = array(
            'id' => (int) $GroupTorrents['ID'],
            'groupId' => (int) $GroupTorrents['GroupID'],
            'fileCount' => (int) $GroupTorrents['FileCount'],
            'freeTorrent' => $GroupTorrents['FreeTorrent'] == 1,
            'size' => (float) $GroupTorrents['Size'],
            'leechers' => (int) $GroupTorrents['Leechers'],
            'seeders' => (int) $GroupTorrents['Seeders'],
            'snatched' => (int) $GroupTorrents['Snatched'],
            'time' => $GroupTorrents['Time'],
            'hasFile' => (int) $GroupTorrents['HasFile']
        );
    }
    $JsonBookmarks[] = array(
        'id' => (int) $Torrent['ID'],
        'name' => $Torrent['Name'],
        'tagList' => $Torrent['TagList'],
        'torrents' => $JsonTorrents
    );
}

print
    json_encode(
        array(
            'status' => 'success',
            'response' => array(
                'bookmarks' => $JsonBookmarks
            )
        )
    );
