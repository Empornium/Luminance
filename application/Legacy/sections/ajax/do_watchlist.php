<?php
if (!check_perms('users_manage_cheats')) error(403,true);

if ( isset($_GET['userid']) && is_integer_string($_GET['userid']) && $_GET['userid'] > 0) {
    $userID = (int) $_GET['userid'];
    $torrentID =0;
} elseif ( isset($_GET['torrentid']) && is_integer_string($_GET['torrentid']) && $_GET['torrentid']>0
        && isset($_GET['groupid']) && is_integer_string($_GET['groupid']) && $_GET['groupid']>0) {
    $torrentID = (int) $_GET['torrentid'];
    $GroupID = (int) $_GET['groupid'];
    $userID =0;
} else {
    error(0,true);
}

if ($_GET['action']=='remove_records') {

    if ($userID) {
        $peerRecords = $master->db->rawQuery("DELETE FROM xbt_peers_history WHERE uid = ?", [$userID]);
        $Num = $peerRecords->rowCount();
        echo json_encode([true, "Removed $Num speed records of user $userID from watchlist"]);
    }

} elseif ($_GET['action']=='excludelist_add') {

    $Comment = $_GET['comm'];
    if ($userID) {
        $master->db->rawQuery(
            "INSERT IGNORE INTO users_not_cheats (UserID, StaffID, Time, Comment)
                         VALUES (?, ?, ?, ?)",
            [$userID, $activeUser['ID'], sqltime(), $Comment]
        );
        $Num = $master->db->rawQuery(
            "SELECT COUNT(*)
               FROM users_not_cheats"
        )->fetchColumn();
        write_user_log($userID, "User added to exclude users list. Reason: $Comment, by {$activeUser['Username']}");
        echo json_encode([true, "Added user $userID to exclude list\n$Num users are now being excluded"]);
    }

} elseif ($_GET['action']=='excludelist_remove') {

    if ($userID) {
        $removeExclude = $master->db->rawQuery("DELETE FROM users_not_cheats WHERE UserID = ?", [$userID]);
        if ($removeExclude->rowCount() > 0) {
            write_user_log($userID, "User removed from exclude list by {$activeUser['Username']}");
            echo json_encode([true, 'removed user from exclude list']);
        } else
            echo json_encode([false, 'failed to remove user from exclude list']);
    }

} elseif ($_GET['action']=='watchlist_add') {

    $Comment = $_GET['comm'];
    if ($userID) {
        $master->db->rawQuery(
            "INSERT IGNORE INTO users_watch_list (UserID, StaffID, Time, Comment, KeepTorrents)
                         VALUES (?, ?, ?, ?, '1')",
            [$userID, $activeUser['ID'], sqltime(), $Comment]
        );
        $Num = $master->db->rawQuery(
            "SELECT COUNT(*)
               FROM users_watch_list"
        )->fetchColumn();
        write_user_log($userID, "User added to watchlist. Reason: $Comment, by {$activeUser['Username']}");
        echo json_encode([true, "Added user $userID to watchlist, $Num users are now being watched"]);
    } else {
        $master->db->rawQuery(
            "INSERT IGNORE INTO torrents_watch_list (TorrentID, StaffID, Time, Comment)
                         VALUES (?, ?, ?, ?)",
            [$torrentID, $activeUser['ID'], sqltime(), $Comment]
        );
        $Num = $master->db->rawQuery(
            "SELECT COUNT(*)
               FROM torrents_watch_list"
        )->fetchColumn();
        write_group_log($GroupID, $torrentID, $activeUser['ID'], "Torrent added to watchlist. Reason: $Comment", '1') ;
        echo json_encode([true, "Added torrent to watchlist, $Num torrents are now being watched"]);
    }

} elseif ($_GET['action']=='watchlist_remove') {

    if ($userID) {
        $removed = $master->db->rawQuery("DELETE FROM users_watch_list WHERE UserID = ?", [$userID]);
        if ($removed->rowCount() > 0) {
            write_user_log($userID, "User removed from watchlist by {$activeUser['Username']}");
            echo json_encode([true, 'removed user from watchlist']);
        } else
            echo json_encode([false, 'failed to remove user from watchlist']);
    } else {
        $removed = $master->db->rawQuery("DELETE FROM torrents_watch_list WHERE TorrentID = ?", [$torrentID]);
        if ($removed->rowCount() > 0) {
            write_group_log($GroupID, $torrentID, $activeUser['ID'], "Torrent removed from watchlist", '1') ;
            echo json_encode([true, 'removed torrent from watchlist']);
        } else
            echo json_encode([false, 'failed to remove torrent from watchlist']);
    }

}
