<?php
authorize(true);

if (!check_perms('site_torrents_notify')) {
    print
        json_encode(
            array(
                'status' => 'failure'
            )
        );
    die();
}

define('NOTIFICATIONS_PER_PAGE', 50);
list($Page,$Limit) = page_limit(NOTIFICATIONS_PER_PAGE);

$TokenTorrents = $Cache->get_value('users_tokens_'.$UserID);
if (empty($TokenTorrents)) {
    $DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$UserID");
    $TokenTorrents = $DB->to_array('TorrentID');
    $Cache->cache_value('users_tokens_'.$UserID, $TokenTorrents);
}

$Results = $DB->query("SELECT SQL_CALC_FOUND_ROWS
        t.ID,
        g.ID,
        g.Name,
        g.NewCategoryID,
        g.TagList,
        t.Size,
        t.FileCount,
        t.Snatched,
        t.Seeders,
        t.Leechers,
        t.Time,
        t.FreeTorrent,
                t.double_seed,
        tln.TorrentID AS LogInDB,
        unt.UnRead,
        unt.FilterID,
        unf.Label
        FROM users_notify_torrents AS unt
        JOIN torrents AS t ON t.ID=unt.TorrentID
        JOIN torrents_group AS g ON g.ID = t.GroupID
        LEFT JOIN users_notify_filters AS unf ON unf.ID=unt.FilterID
        LEFT JOIN torrents_logs_new AS tln ON tln.TorrentID=t.ID
        WHERE unt.UserID='$LoggedUser[ID]'
        GROUP BY t.ID
        ORDER BY t.ID DESC LIMIT $Limit");
$DB->query('SELECT FOUND_ROWS()');
list($TorrentCount) = $DB->next_record();

// Only clear the alert if they've specified to.
if (isset($_GET['clear']) && $_GET['clear'] == "1") {
    //Clear before header but after query so as to not have the alert bar on this page load
    $DB->query("UPDATE users_notify_torrents SET UnRead='0' WHERE UserID=".$LoggedUser['ID']);
    $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
}

$DB->set_query_id($Results);

$Pages=get_pages($Page,$TorrentCount,NOTIFICATIONS_PER_PAGE,9);

$JsonNotifications = array();
$NumNew = 0;

$FilterGroups = array();
while ($Result = $DB->next_record()) {
    if (!$Result['FilterID']) {
        $Result['FilterID'] = 0;
    }
    if (!isset($FilterGroups[$Result['FilterID']])) {
        $FilterGroups[$Result['FilterID']] = array();
        $FilterGroups[$Result['FilterID']]['FilterLabel'] = ($Result['FilterID'] && !empty($Result['Label']) ? $Result['Label'] : 'unknown filter'.($Result['FilterID']?' ['.$Result['FilterID'].']':''));
    }
    array_push($FilterGroups[$Result['FilterID']], $Result);
}
unset($Result);

foreach ($FilterGroups as $ID => $FilterResults) {
    unset($FilterResults['FilterLabel']);
    foreach ($FilterResults as $Result) {
        list($TorrentID, $GroupID, $GroupName, $GroupCategoryID, $TorrentTags, $Size, $FileCount,
            $Snatched, $Seeders, $Leechers, $NotificationTime, $FreeTorrent, $DoubleSeed, $LogInDB,
                        $UnRead) = $Result;

        if ($Unread) $NumNew++;

        $JsonNotifications[] = array(
            'torrentId' => (int) $TorrentID,
            'groupId' => (int) $GroupID,
            'groupName' => $GroupName,
            'groupCategoryId' => (int) $GroupCategoryID,
            'torrentTags' => $TorrentTags,
            'size' => (float) $Size,
            'fileCount' => (int) $FileCount,
            'snatched' => (int) $Snatched,
            'seeders' => (int) $Seeders,
            'leechers' => (int) $Leechers,
            'notificationTime' => $NotificationTime,
            'freeTorrent' => $FreeTorrent == 1,
                        'doubleSeed' => $DoubleSeed == 1,
            'logInDb' => $LogInDB,
            'unread' => $UnRead == 1
        );
    }
}

print
    json_encode(
        array(
            'status' => 'success',
            'response' => array(
                'currentPages' => intval($Page),
                'pages' => ceil($TorrentCount/NOTIFICATIONS_PER_PAGE),
                'numNew' => $NumNew,
                'results' => $JsonNotifications
            )
        )
    );
