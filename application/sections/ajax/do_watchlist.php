<?php
if (!check_perms('users_manage_cheats')) error(403,true);

if ( isset($_GET['userid']) && is_number($_GET['userid']) && $_GET['userid']>0 ) {
    $UserID = (int) $_GET['userid'];
    $TorrentID =0;
} elseif ( isset($_GET['torrentid']) && is_number($_GET['torrentid']) && $_GET['torrentid']>0
        && isset($_GET['groupid']) && is_number($_GET['groupid']) && $_GET['groupid']>0) {
    $TorrentID = (int) $_GET['torrentid'];
    $GroupID = (int) $_GET['groupid'];
    $UserID =0;
} else {
    error(0,true);
}

if ($_GET['action']=='remove_records') {

    if ($UserID) {
        $DB->query("DELETE FROM xbt_peers_history WHERE uid='$UserID'");
        list($Num) = $DB->affected_rows();
        echo json_encode(array(true, "Removed $Num speed records of user $UserID from watchlist"));
    }

} elseif ($_GET['action']=='excludelist_add') {

    $Comment = db_string($_GET['comm']);
    if ($UserID) {
        $DB->query("INSERT IGNORE INTO users_not_cheats ( UserID, StaffID, Time, Comment)
                                        VALUES ( '$UserID', '$LoggedUser[ID]', '".sqltime()."', '$Comment' ) ");
        $DB->query("SELECT Count(*) FROM users_not_cheats");
        list($Num) = $DB->next_record();
        write_user_log($UserID, "User added to exclude users list. Reason: $Comment, by $LoggedUser[Username]");
        echo json_encode(array(true, "Added user $UserID to exclude list\n$Num users are now being excluded"));
    }

} elseif ($_GET['action']=='excludelist_remove') {

    if ($UserID) {
        $DB->query("DELETE FROM users_not_cheats WHERE UserID='$UserID'");
        if ($DB->affected_rows()>0) {
            write_user_log($UserID, "User removed from exclude list by $LoggedUser[Username]");
            echo json_encode(array(true, 'removed user from exclude list'));
        } else
            echo json_encode(array(false, 'failed to remove user from exclude list'));
    }

} elseif ($_GET['action']=='watchlist_add') {

    $Comment = db_string($_GET['comm']);
    if ($UserID) {
        $DB->query("INSERT IGNORE INTO users_watch_list ( UserID, StaffID, Time, Comment, KeepTorrents)
                                        VALUES ( '$UserID', '$LoggedUser[ID]', '".sqltime()."', '$Comment', '1' ) ");
        $DB->query("SELECT Count(*) FROM users_watch_list");
        list($Num) = $DB->next_record();
        write_user_log($UserID, "User added to watchlist. Reason: $Comment, by $LoggedUser[Username]");
        echo json_encode(array(true, "Added user $UserID to watchlist, $Num users are now being watched"));
    } else {
        $DB->query("INSERT IGNORE INTO torrents_watch_list ( TorrentID, StaffID, Time, Comment)
                                        VALUES ( '$TorrentID', '$LoggedUser[ID]', '".sqltime()."', '$Comment' ) ");
        $DB->query("SELECT Count(*) FROM torrents_watch_list");
        list($Num) = $DB->next_record();
        write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "Torrent added to watchlist. Reason: $Comment", '1') ;
        echo json_encode(array(true, "Added torrent to watchlist, $Num torrents are now being watched"));
    }

} elseif ($_GET['action']=='watchlist_remove') {

    if ($UserID) {
        $DB->query("DELETE FROM users_watch_list WHERE UserID='$UserID'");
        if ($DB->affected_rows()>0) {
            write_user_log($UserID, "User removed from watchlist by $LoggedUser[Username]");
            echo json_encode(array(true, 'removed user from watchlist'));
        } else
            echo json_encode(array(false, 'failed to remove user from watchlist'));
    } else {
        $DB->query("DELETE FROM torrents_watch_list WHERE TorrentID='$TorrentID'");
        if ($DB->affected_rows()>0) {
            write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "Torrent removed from watchlist", '1') ;
            echo json_encode(array(true, 'removed torrent from watchlist'));
        } else
            echo json_encode(array(false, 'failed to remove torrent from watchlist'));
    }

}
