<?php
$CollageID = $_GET['collageid'];
if (!is_number($CollageID)) {
    error(0);
}

$DB->query("SELECT Name, UserID, CategoryID, Permissions FROM collages WHERE ID='$CollageID'");
list($Name, $UserID, $CategoryID, $CPermissions) = $DB->next_record();
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

$DB->query("SELECT ct.GroupID,
    um.ID,
    um.Username,
    ct.Sort
    FROM collages_torrents AS ct
    JOIN torrents_group AS tg ON tg.ID=ct.GroupID
    LEFT JOIN users_main AS um ON um.ID=ct.UserID
    WHERE ct.CollageID='$CollageID'
    ORDER BY ct.Sort");

$GroupIDs = $DB->collect('GroupID');

$CollageDataList=$DB->to_array('GroupID', MYSQLI_ASSOC);
if (count($GroupIDs)>0) {
    $TorrentList = get_groups($GroupIDs);
    $TorrentList = $TorrentList['matches'];
} else {
    $TorrentList = array();
}

show_header('Manage collage '.$Name);
?>
<div class="thin">
    <h2>Manage collage <a href="/collages.php?id=<?=$CollageID?>"><?=$Name?></a></h2>
    <table>
        <tr class="colhead">
            <td>Sort</td>
            <td>Torrent</td>
            <td>User</td>
            <td>Submit</td>
        </tr>
<?php
$Number = 0;
foreach ($TorrentList as $GroupID => $Group) {
    list($GroupID, $GroupName, $TagList, $Torrents) = array_values($Group);
    list($GroupID2, $UserID, $Username, $Sort) = array_values($CollageDataList[$GroupID]);

    $Number++;

    $DisplayName = $Number.' - ';
    $DisplayName .= '<a href="/torrents.php?id='.$GroupID.'" title="View Torrent">'.display_str($GroupName).'</a>';
?>
        <tr>
            <form action="collages.php" method="post">
                <input type="hidden" name="action" value="manage_handle" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="collageid" value="<?=$CollageID?>" />
                <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                <td>
                    <input type="text" name="sort" value="<?=$Sort?>" size="4" title="The collage is sorted order of this number" />
                </td>
                <td>
                    <?=$DisplayName?>
                </td>
                <td>
                    <?=format_username($UserID, $Username)?>
                </td>
                <td>
                    <input type="submit" name="submit" value="Edit" />
                    <input type="submit" name="submit" value="Remove" />
                </td>
            </form>
        </tr>
<?php
}
?>
    </table>
</div>
<?php
show_footer();
