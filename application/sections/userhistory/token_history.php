<?php
/************************************************************************
||------------------|| User token history page ||-----------------------||
This page lists the torrents a user has spent his tokens on. It
gets called if $_GET['action'] == 'token_history'.

Using $_GET['userid'] allows a mod to see any user's token history.
Nonmods and empty userid show $LoggedUser['ID']'s history
************************************************************************/

include_once(SERVER_ROOT.'/common/functions.php');

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $OrderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $OrderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('Torrent', 'Size', 'Time', 'Freeleech' ,'Doubleseed' ))) {
    $_GET['order_by'] = 'Time';
    $OrderBy = 'Time';
} else {
    $OrderBy = $_GET['order_by'];
}

if (isset($_GET['userid'])) {
    $UserID = $_GET['userid'];
} else {
    $UserID = $LoggedUser['ID'];
}
if (!is_number($UserID)) { error(404); }

$UserInfo = user_info($UserID);
$Perms = get_permissions($UserInfo['PermissionID']);
$UserClass = $Perms['Class'];

if ($LoggedUser['ID'] != $UserID && !check_paranoia(false, $User['Paranoia'], $UserClass, $UserID)) {
    error(PARANOIA_MSG);
}

if (isset($_GET['expire'])) {
    if (!check_perms('users_mod')) { error(403); }
    $UserID = $_GET['userid'];
    $TorrentID = $_GET['torrentid'];

    if (!is_number($UserID) || !is_number($TorrentID)) { error(403); }
    $InfoHash = $master->db->raw_query("SELECT info_hash FROM torrents where ID = :torrentid", [':torrentid' => $TorrentID])->fetchColumn();
    if (!empty($InfoHash)) {
        $master->db->raw_query("DELETE FROM users_slots WHERE UserID=$UserID AND TorrentID=:torrentid", [':torrentid' => $TorrentID]);
        $Cache->delete_value('users_tokens_'.$UserID);
        update_tracker('remove_tokens', array('info_hash' => rawurlencode($InfoHash), 'userid' => $UserID));
    }
    header("Location: userhistory.php?action=token_history&userid=$UserID");
}

show_header('Current slots in use');

list($Page,$Limit) = page_limit(50);

$Tokens = $master->db->raw_query(
    "SELECT SQL_CALC_FOUND_ROWS
        us.TorrentID,
        t.GroupID,
        t.Size,
        tg.Time,
        us.FreeLeech,
        us.DoubleSeed,
        tg.Name as Torrent
       FROM users_slots AS us
  LEFT JOIN torrents AS t ON t.ID = us.TorrentID
  LEFT JOIN torrents_group AS tg ON tg.ID = t.GroupID
      WHERE us.UserID = :userid
   ORDER BY :order
      LIMIT :limit",
    [':userid' => $UserID,
     ':order'  => "{$OrderBy} {$OrderWay}",
     ':limit'  => $Limit]
)->fetchAll();

$NumResults = $master->db->raw_query("SELECT FOUND_ROWS()")->fetchColumn();
$Pages=get_pages($Page, $NumResults, 50);

?>
<div class="thin">
    <div class="linkbox"><?=$Pages?></div>
    <div class="head">Slots in use for <?=format_username($UserID, $UserInfo['Username'], $UserInfo['Donor'], $UserInfo['Warned'], $UserInfo['Enabled'])?></div>
    <table>
        <tr class="colhead">
            <td style="width:60%"><a href="<?=header_link('Torrent') ?>">Torrent</a></td>
            <td><a href="<?=header_link('Size') ?>">Size</a></td>
            <td><a href="<?=header_link('Time') ?>">Time posted</a></td>
            <td class="center"><a href="<?=header_link('Freeleech') ?>">Freeleech</a></td>
            <td class="center"><a href="<?=header_link('Doubleseed') ?>">Doubleseed</a></td>
        </tr>
<?php
    foreach ($Tokens as $Token) {
        $GroupIDs[] = $Token['GroupID'];
    }

    $i = true;
    foreach ($Tokens as $Token) {
        $i = !$i;
        list($TorrentID, $GroupID, $Size, $Time, $FreeLeech, $DoubleSeed, $Name) = $Token;
        if(empty($Name)) $Name = "(Deleted)";
        $Name = "<a href=\"torrents.php?torrentid=$TorrentID\">$Name</a>";
        if ($FreeLeech == '0000-00-00 00:00:00') {
            $fl = 'No';
        } else {
            $fl = $FreeLeech > sqltime() ?
                time_diff($FreeLeech) : '<span style="color:red" title="' . time_diff($FreeLeech,2,false,false,1) . '">Expired</span>';
        }

        if ($DoubleSeed == '0000-00-00 00:00:00') {
            $ds = 'No';
        } else {
            $ds = time_diff($DoubleSeed,2,true,false,1);
            $ds = $DoubleSeed > sqltime() ?
                time_diff($DoubleSeed) : '<span style="color:red" title="' . time_diff($DoubleSeed,2,false,false,1) . '">Expired</span>';
        }
?>
        <tr class="<?=($i?'rowa':'rowb')?>">
            <td><?=$Name?></td>
            <td><?=get_size($Size)?></td>
            <td><?=time_diff($Time,2,true,false,1)?></td>
            <td class="center"><?=$fl?></td>
            <td class="center"><?=$ds?></td>
        </tr>
<?php   }       ?>
    </table>
    <div class="linkbox"><?=$Pages?></div>
</div>

<?php
show_footer();
