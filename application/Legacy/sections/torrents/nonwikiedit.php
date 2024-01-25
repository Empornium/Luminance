<?php
authorize();

//Set by system
if (!$_POST['groupid'] || !is_integer_string($_POST['groupid'])) {
    error(404);
}
$GroupID = $_POST['groupid'];

//Usual perm checks
if (!check_perms('torrent_edit')) {
    $userIDs = $master->db->rawQuery(
        "SELECT UserID
           FROM torrents
          WHERE GroupID = ?",
        [$GroupID]
    )->fetchAll(\PDO::FETCH_COLUMN);
    if (!in_array($activeUser['ID'], $userIDs)) {
        error(403);
    }
}

if (check_perms('torrent_freeleech') && isset($_POST['freeleech'])) {
    $Free = (int) $_POST['freeleech'];
    $Free = $Free==1?1:0;

    freeleech_groups($GroupID, $Free, false, null);
}

header("Location: torrents.php?id=".$GroupID);
