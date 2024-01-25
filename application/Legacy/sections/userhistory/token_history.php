<?php
/************************************************************************
||------------------|| User token history page ||-----------------------||
This page lists the torrents a user has spent his tokens on. It
gets called if $_GET['action'] == 'token_history'.

Using $_GET['userid'] allows a mod to see any user's token history.
Nonmods and empty userid show $activeUser['ID']'s history
************************************************************************/
use \Luminance\Entities\User;


if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $orderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $orderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], ['Torrent', 'Size', 'Time', 'Freeleech' , 'Doubleseed'])) {
    $_GET['order_by'] = 'Time';
    $orderBy = 'Time';
} else {
    $orderBy = $_GET['order_by'];
}

if (isset($_GET['userid'])) {
    $userID = $_GET['userid'];
} else {
    $userID = $activeUser['ID'];
}

if (!is_integer_string($userID)) {
    error(404);
}

$user = $master->repos->users->load($userID);
if (!($user instanceof User)) {
    error(404);
}

if ($activeUser['ID'] != $userID && !check_paranoia(false, $user['Paranoia'], $user->class->Level, $user->ID)) {
    error(PARANOIA_MSG);
}

if (isset($_GET['expire'])) {
    if (!check_perms('users_mod')) {
        error(403);
    }
    $torrentID = $_GET['torrentid'];

    if (!is_integer_string($torrentID)) {
        error(404);
    }
    $InfoHash = $master->db->rawQuery("SELECT info_hash FROM torrents where ID = ?", [$torrentID])->fetchColumn();
    if (!empty($InfoHash)) {
        $master->db->rawQuery("DELETE FROM users_slots WHERE UserID = ? AND TorrentID = ?", [$user->ID, $torrentID]);
        $master->cache->deleteValue('users_tokens_'.$user->ID);
        $master->tracker->removeTokens($InfoHash, $user->ID);
    }
    header("Location: userhistory.php?action=token_history&userid={$user->ID}");
}

show_header('Current slots in use');

list($Page, $Limit) = page_limit(50);

$Tokens = $master->db->rawQuery(
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
      WHERE us.UserID = ?
   ORDER BY {$orderBy} {$orderWay}
      LIMIT {$Limit}",
    [$user->ID]
)->fetchAll();

$NumResults = $master->db->rawQuery("SELECT FOUND_ROWS()")->fetchColumn();
$Pages = get_pages($Page, $NumResults, 50);

?>
<div class="thin">
    <div class="linkbox pager"><?= $Pages ?></div>
    <div class="head">Slots in use for <?=format_username($user->ID, $user->legacy['Donor'], true, $user->legacy['Enabled'])?></div>
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
        list($torrentID, $GroupID, $Size, $time, $FreeLeech, $DoubleSeed, $Name) = $Token;
        if (empty($Name)) $Name = "(Deleted)";
        $Name = "<a href=\"torrents.php?torrentid=$torrentID\">$Name</a>";
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
            <td><?=time_diff($time,2,true,false,1)?></td>
            <td class="center"><?=$fl?></td>
            <td class="center"><?=$ds?></td>
        </tr>
<?php   }       ?>
    </table>
    <div class="linkbox pager"><?= $Pages ?></div>
</div>

<?php
show_footer();
