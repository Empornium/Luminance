<?php
include(SERVER_ROOT . '/Legacy/sections/tools/managers/speed_functions.php');

function history_span($value)
{
    return '<span style="color:'.($value=='false'?'red':'lightgrey').'">'.$value.'</span>';
}

if (!check_perms('users_manage_cheats')) { error(403); }

$Action = 'speed_zerocheats';

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $orderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $orderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], ['Username', 'peercount', 'grabbed', 'history', 'time', 'JoinDate'])) {
    $_GET['order_by'] = 'upspeed';
    $orderBy = 'upspeed';
} else {
    $orderBy = $_GET['order_by'];
}

$NumGrabbed = isset($_GET['grabbed']) ? (int) $_GET['grabbed'] : 2;
$ViewDays = isset($_GET['viewdays']) ? (int) $_GET['viewdays'] : 1;

$ViewInfo = "Min files: $NumGrabbed, joined > $ViewDays day ago" ;

$WHERE = '';

if (isset($_GET['viewbanned']) && $_GET['viewbanned']) {
    $ViewInfo .= ' (all)';
} else {
    $WHERE .= " AND um.Enabled='1' ";
    $ViewInfo .= ' (enabled only)';
}

show_header('Zero Stat Cheats', 'watchlist');

?>
<div class="thin">
  <h2>(possible) zero stat cheaters</h2>

    <div class="linkbox">
        <a href="/tools.php?action=speed_watchlist">[Watch-list]</a>
        <a href="/tools.php?action=speed_excludelist">[Exclude-list]</a>
        <a href="/tools.php?action=speed_records">[Speed Records]</a>
        <a href="/tools.php?action=speed_cheats">[Speed Cheats]</a>
        <a href="/tools.php?action=speed_zerocheats">[Zero Cheats]</a>
    </div>

    <div class="head">options</div>
    <table class="box pad">
        <tr class="colhead"><td colspan="3">view settings: <span style="float:right;font-weight: normal"><?=$ViewInfo?> &nbsp; (order: <?="$orderBy $orderWay"?>)</span> </td></tr>
            <tr class="rowb">
                <td class="center">
                            <label for="viewbanned" title="Keep Speed">show disabled users </label>
                        <input type="checkbox" value="1" onchange="change_zero_view()"
                               id="viewbanned" name="viewbanned" <?php  if (isset($_GET['viewbanned']) && $_GET['viewbanned'])echo' checked="checked"'?> />
                </td>
                <td class="center">

                    <label for="grabbed" title="Minimum number of grabbed files">Grabbed files </label>
                    <input type="text" id="grabbed" name="grabbed" size="3" value="<?=$NumGrabbed?>" onblur="change_zero_view()" />
                </td>
                <td class="center">

                    <label for="viewdays" title="Exclude users who joined recently">Exclude users who joined in the last</label>
                    <select id="viewdays" name="viewdays" title="Exclude users who joined in the specified time" onchange="change_zero_view()">
                        <option value="0"<?=($ViewDays==0?' selected="selected"':'');?>>&nbsp;0&nbsp;&nbsp;</option>
                        <option value="1"<?=($ViewDays==1?' selected="selected"':'');?>>&nbsp;1 day&nbsp;&nbsp;</option>
                        <option value="7"<?=($ViewDays==7?' selected="selected"':'');?>>&nbsp;1 week&nbsp;&nbsp;</option>
                        <option value="28"<?=($ViewDays==28?' selected="selected"':'');?>>&nbsp;4 weeks&nbsp;&nbsp;</option>
                        <option value="28"<?=($ViewDays==28?' selected="selected"':'');?>>&nbsp;26 weeks&nbsp;&nbsp;</option>
                        <option value="365"<?=($ViewDays==365?' selected="selected"':'');?>>&nbsp;1 year&nbsp;&nbsp;</option>
                    </select>

                </td>
            </tr>
    </table>
    <br/>

<?php

//---------- print records

list($Page, $Limit) = page_limit(50);

$Records = $master->db->rawQuery("SELECT SQL_CALC_FOUND_ROWS
                   uid, u.Username, COUNT(x.fid) as Peercount, Count(DISTINCT ud.TorrentID) as Grabbed,
                            MAX(x.upspeed) as upspeed, MAX(x.mtime) as time, ui.JoinDate,
                             GROUP_CONCAT(DISTINCT LEFT(x.peer_id,8) SEPARATOR '|'),
                             GROUP_CONCAT(DISTINCT INET6_NTOA(x.ipv4) SEPARATOR '|'),
                             ui.Donor, um.Enabled, um.PermissionID, IF(w.UserID, '1', '0'), IF(nc.UserID, '1', '0'),
                             IF(ui.SeedHistory, 'true', 'false') as history
               FROM xbt_files_users AS x
               JOIN torrents AS t ON t.ID=x.fid AND x.active=1
               JOIN users AS u ON u.ID=x.uid
               JOIN users_main AS um ON um.ID=x.uid AND  um.Downloaded=0 AND ( um.Uploaded=524288000 OR  um.Uploaded=0)
               JOIN users_info AS ui ON ui.UserID=um.ID
               LEFT JOIN users_downloads AS ud ON ud.UserID=um.ID
               LEFT JOIN users_watch_list AS w ON w.UserID=x.uid
               LEFT JOIN users_not_cheats AS nc ON nc.UserID=x.uid
                         WHERE ui.JoinDate<'".time_minus(3600*24*$ViewDays)."' {$WHERE}
                      GROUP BY x.uid
                        HAVING Grabbed >= ?
                      ORDER BY {$orderBy} {$orderWay}
                         LIMIT {$Limit}",
    [$NumGrabbed]
)->fetchAll(\PDO::FETCH_NUM);

$NumResults = $master->db->foundRows();

$Pages = get_pages($Page, $NumResults, 50, 9);

?>

    <div class="linkbox pager"><?= $Pages ?></div>

    <div class="head"><?=$NumResults?> users with suspicious zero stats</div>
        <table>
            <tr class="colhead">
                <td style="width:20px"></td>
                <td class="center"><a href="<?=header_link('Username') ?>">User</a></td>
                <!--<td class="center"><a href="<?=header_link('upspeed') ?>">Max UpSpeed</a></td>-->
                <td class="center" title="number of current peer records"><a href="<?=header_link('peercount') ?>">peer on</a></td>
                <td class="center" title="number of grabbed files"><a href="<?=header_link('grabbed') ?>">grabbed</a></td>
                <td class="center" title="has seed history"><a href="<?=header_link('history') ?>">tracker history</a></td>
                <td class="center"><span style="color:#777">-clientID-</span></td>
                <td class="center">Client IP addresses</td>
                <td class="center" style="min-width:120px"><a href="<?=header_link('time') ?>">last seen</a></td>
                <td class="center" style="min-width:120px"><a href="<?=header_link('JoinDate') ?>">joined</a></td>
            </tr>
<?php
            $row = 'a';
            if ($NumResults==0) {
?>
                    <tr class="rowb">
                        <td class="center" colspan="10">no zero stat peers</td>
                    </tr>
<?php
            } else {
                foreach ($Records as $Record) {
                    list( $userID, $Username, $CountRecords, $Grabbed, $MaxUpSpeed, $LastTime, $JoinDate,  $PeerIDs, $IPs,
                            $IsDonor, $enabled, $classID, $OnWatchlist, $OnExcludelist, $HasSeedHistory) = $Record;
                    $row = ($row === 'a' ? 'b' : 'a');

                    $PeerIDs = explode('|', $PeerIDs);
                    $IPs = explode('|', $IPs);
?>
                    <tr class="row<?=$row?>">
                        <td>
<?php
                            if ($enabled=='1') {  ?>
                                <a href="/tools.php?action=ban_zero_cheat&banuser=1&userid=<?=$userID?>" title="ban this user for being a big fat zero stat cheat"><img src="/static/common/symbols/ban2.png" alt="ban" /></a>
<?php                           }
                           ?>
                        </td>
                        <td class="center">
<?php                           echo format_username($userID, $IsDonor, true, $enabled, $classID, false, false);

                            if (($IPDupeCount ?? 0) > 0) { ?>

                            <span style="float:right;">
                                <a href="#" title="view matching ip's for this user" onclick="$('#linkeddiv<?=$userID?>').toggle();this.innerHTML=this.innerHTML=='(hide)'?'(view)':'(hide)';return false;">(view)</a>
                            </span>
<?php
                            }
?>
                        </td>
                        <td class="center"><?=$CountRecords?></td>
                        <td class="center"><?=$Grabbed?></td>
                        <td class="center"><?=history_span($HasSeedHistory)?></td>
                        <td class="center"><?php
                            foreach ($PeerIDs as $PeerID) {
                        ?>  <span style="color:#555"><?=substr($PeerID,0,8)  ?></span> <br/>
                        <?php   } ?>
                        </td>
                        <td class="center"><?php
                            foreach ($IPs as $IP) {
                                echo display_ip($IP)."<br/>";
                            }
                        ?>
                        </td>
                        <td class="center"><?=time_diff($LastTime, 2, true, false, 1)?></td>
                        <td class="center"><?=time_diff($JoinDate, 2, true, false, 0)?></td>
                    </tr>
<?php
            if (($IPDupeCount ?? 0) > 0) {
?>
                    <tr id="linkeddiv<?=$userID?>" style="font-size:0.9em;" class="hidden row<?=$row?>">
                        <td colspan="10">
            <table width="100%" class="border">
<?php
            $i = 0;
            foreach ($IPDupes AS $IPDupe) {
                list($EUserID, $IP, $EType1, $EType2) = $IPDupe;
                $i++;
                $DupeInfo = user_info($EUserID);
?>
            <tr>

                <td align="center">
                    <?=format_username($EUserID, $DupeInfo['Donor'], $DupeInfo['Enabled'], $DupeInfo['PermissionID'])?>
                </td>
                <td align="center">
                    <?=display_ip($IP, $DupeInfo['ipcc'])?>
                </td>
                <td align="left">
                    <?="{$Username}'s {$EType1} <-> {$DupeInfo['Username']}'s {$EType2}"?>
                </td>
                <td>
<?php
                    if (!array_key_exists($EUserID, $Dupes)) {
?>
                        [<a href="/user.php?action=dupes&dupeaction=link&auth=<?=$activeUser['AuthKey']?>&userid=<?=$userID?>&targetid=<?=$EUserID?>" title="link this user to <?=$Username?>">link</a>]
<?php
                    }
?>
                </td>
            </tr>
<?php
            }
?>
            </table>

                        </td>
                    </tr>
<?php
            }
?>

<?php
                }
            }
            ?>
        </table>
    <div class="linkbox pager"><?= $Pages ?></div>
</div>
<?php
show_footer();
