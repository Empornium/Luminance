<?php
authorize();

$GroupID = $_POST['groupid'];
if (!$GroupID || !is_number($GroupID)) { error(404); }

//check user has permission to edit
$CanEdit = check_perms('torrents_edit');

if (!$CanEdit) {
    $DB->query("SELECT UserID FROM torrents WHERE GroupID='$GroupID'");
    list($AuthorID) = $DB->next_record();
    $CanEdit = check_perms('site_upload_anon') && $AuthorID == $LoggedUser['ID'];
}
if (!$CanEdit) { error(403); }

$IsAnon = (int) $_POST['anonymous'];
$IsAnon = ($IsAnon==1) ? '1' : '0' ;

$DB->query("UPDATE torrents SET Anonymous='$IsAnon' WHERE GroupID='$GroupID'");
$Cache->delete_value('torrents_details_'.$GroupID);
$Cache->delete_value('torrent_group_'.$GroupID);

//Fix Recent Uploads/Downloads for anon change
$DB->query("SELECT DISTINCT UserID
            FROM torrents AS t
            JOIN torrents_group AS tg ON t.GroupID=tg.ID
            WHERE tg.ID = $GroupID");

$UserIDs = $DB->collect('UserID');
foreach ($UserIDs as $UserID) {
    $Cache->delete_value('recent_uploads_'.$UserID);
}

write_group_log($GroupID, 0, $LoggedUser['ID'], "Anonymous status set to " . (($IsAnon=='1') ? 'TRUE' : 'FALSE'), 1);

header('Location: torrents.php?id='.$GroupID );
