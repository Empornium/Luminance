<?php
authorize();

$torrentID = $_POST['torrentid'];
if (!$torrentID || !is_integer_string($torrentID)) { error(404); }

$nextRecord = $master->db->rawQuery(
    "SELECT t.UserID,
            t.GroupID,
            t.Size,
            t.info_hash,
            tg.Name,
            t.Time,
            COUNT(x.uid)
       FROM torrents AS t
  LEFT JOIN torrents_group AS tg ON tg.ID = t.GroupID
  LEFT JOIN xbt_snatched AS x ON x.fid = t.ID
      WHERE t.ID = ?",
    [$torrentID]
)->fetch(\PDO::FETCH_NUM);
list($userID, $GroupID, $Size, $InfoHash, $Name, $time, $Snatches) = $nextRecord;

if (($activeUser['ID']!=$userID || time_ago($time) > 3600*24*7 || $Snatches > 4) && !check_perms('torrent_delete')) {
    error(403);
}

if (isset($_SESSION['logged_user']['multi_delete'])) {
    if ($_SESSION['logged_user']['multi_delete']>=3 && !check_perms('torrent_delete_fast')) {
        error('You have recently deleted 3 torrents, please contact a staff member if you need to delete more.');
    }
    $_SESSION['logged_user']['multi_delete']++;
} else {
    $_SESSION['logged_user']['multi_delete'] = 1;
}

$InfoHash = unpack("H*", $InfoHash);
delete_torrent($torrentID, $GroupID);
write_log("Torrent $torrentID ($Name) (". get_size($Size). ") (" .strtoupper($InfoHash[1]) .") was deleted by ".$activeUser['Username'].": ".$_POST['reason'].' '. $_POST['extra']);
write_group_log($GroupID, $torrentID, $activeUser['ID'], "deleted $Name (".get_size($Size).", ".strtoupper($InfoHash[1]).") for reason: ".$_POST['reason'].' '. $_POST['extra'], 0);

show_header('Torrent deleted');
?>
<div class="thin">
    <h3>Torrent was successfully deleted.</h3>
</div>
<?php
show_footer();
