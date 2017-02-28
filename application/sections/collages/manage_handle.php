<?php
authorize();

$CollageID = $_POST['collageid'];
if (!is_number($CollageID)) { error(404); }

$DB->query("SELECT UserID, Name, Permissions FROM collages WHERE ID='$CollageID'");
list($UserID, $Name, $CPermissions) = $DB->next_record();

if (!check_perms('site_collages_manage')) {
    $CPermissions=(int) $CPermissions;
    if ($UserID == $LoggedUser['ID']) {
          $CanEdit = true;
    } elseif ($CPermissions>0) {
          $CanEdit = $LoggedUser['Class'] >= $CPermissions;
    } else {
          $CanEdit=false; // can be overridden by permissions
    }
    if (!$CanEdit) { error(403); }
}

$GroupID = $_POST['groupid'];
if (!is_number($GroupID)) { error(404); }

if ($_POST['submit'] == 'Remove') {
    $DB->query("DELETE FROM collages_torrents WHERE CollageID='$CollageID' AND GroupID='$GroupID'");
    $Rows = $DB->affected_rows();
    $DB->query("UPDATE collages SET NumTorrents=NumTorrents-$Rows WHERE ID='$CollageID'");
    $Cache->delete_value('torrents_details_'.$GroupID);
    $Cache->delete_value('torrent_collages_'.$GroupID);
    $Cache->delete_value('torrent_collages_personal_'.$GroupID);
    write_log("Collage ".$CollageID." (".db_string($Name).") was edited by ".$LoggedUser['Username']." - removed torrents $GroupID");
} else {
    $Sort = $_POST['sort'];
    if (!is_number($Sort)) { error(404); }
    $DB->query("UPDATE collages_torrents SET Sort='$Sort' WHERE CollageID='$CollageID' AND GroupID='$GroupID'");
}
$DB->query("SELECT COUNT(GroupID) AS NumGroups FROM collages_torrents WHERE CollageID='$CollageID'");
list($NumGroups) = $DB->next_record();

$TorrentsPerPage = TORRENTS_PER_PAGE;
$Pages = max(1, ceil((float)$NumGroups/(float)$TorrentsPerPage));
for ($Page=1; $Page <= $Pages; $Page++) {
    $Cache->delete_value('collage_'.$CollageID.'_'.$Page);
}
$Cache->delete_value('collage_'.$CollageID);
header('Location: collages.php?action=manage&collageid='.$CollageID);
