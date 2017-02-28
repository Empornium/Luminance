<?php
if (!check_perms('users_view_ips')) { error(403); }
include(SERVER_ROOT . '/common/functions.php');

if (empty($_GET['order_way']) || $_GET['order_way'] == 'asc') {
    $OrderWay = 'desc'; // For header links
} else {
    $_GET['order_way'] = 'asc';
    $OrderWay = 'asc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('new_id', 'new_name', 'joindate', 'IP', 'b_id', 'b_name', 'bandate' ))) {
    $_GET['order_by'] = 'joindate';
    $OrderBy = 'joindate';
} else {
    $OrderBy = $_GET['order_by'];
}
/*
 * BanReason 0 - Unknown, 1 - Manual, 2 - Ratio, 3 - Inactive, 4 - Cheating.
 */
$Reasons = array(0=>'Unknown',1=>'Manual',2=>'Ratio',3=>'Inactive',4=>'Cheating' );
$BanReason = (isset($_GET['ban_reason']) && is_number($_GET['ban_reason']) && $_GET['ban_reason'] < 5) ? (int) $_GET['ban_reason'] : 4 ;

$Weeks =  (isset($_GET['weeks']) && is_number($_GET['weeks']) ) ? (int) $_GET['weeks'] : 1 ;
if ($Weeks > 104) $Weeks = 104;

list($Page,$Limit) = page_limit(25);

$CachedDupeResults = $Cache->get_value("dupeip_users_{$BanReason}_{$Weeks}_$OrderBy{$OrderWay}_$Page");
if ($CachedDupeResults===false) {

    $DB->query("SELECT SQL_CALC_FOUND_ROWS
                       n.ID as new_id,
                       n.JoinDate as joindate,
                       b.IP as IP,
                       b.ID as b_id,
                       b.BanDate as bandate,
                       n.Username as new_name,
                       b.Username as b_name
                  FROM (SELECT bu.ID, bu.Username, bi.BanDate, bu.IP
                        FROM users_info as bi
                        JOIN users_main as bu ON bi.UserID=bu.ID
                        WHERE bu.Enabled='2' AND bi.Banreason='$BanReason' AND bi.BanDate > (NOW() - INTERVAL $Weeks WEEK)
                            ) AS b
                  JOIN (SELECT nu.ID, nu.Username, ni.JoinDate, nu.IP
                        FROM users_info as ni
                        JOIN users_main as nu ON ni.UserID=nu.ID
                        WHERE nu.Enabled='1'
                            ) AS n
                            ON n.IP=b.IP AND n.ID!=b.ID AND n.JoinDate>b.BanDate
              ORDER BY $OrderBy $OrderWay
                 LIMIT $Limit ;");

    $DupeRecords = $DB->to_array();
    $DB->query("SELECT FOUND_ROWS()");
    list($NumResults) = $DB->next_record();
    $CachedDupeResults = array($NumResults, $DupeRecords);
    $Cache->cache_value("dupeip_users_{$BanReason}_{$Weeks}_$OrderBy{$OrderWay}_$Page", $CachedDupeResults, 3600*24);
} else {
    list($NumResults, $DupeRecords) = $CachedDupeResults;
}

$Pages=get_pages($Page,$NumResults,25,9);

show_header('Dupe IPs','dupeip');

?>
<div class="thin">
    <h2>Returning Dupe IP's</h2>
    <div class="linkbox">
        <a href="tools.php?action=dupe_ips">[Dupe IP's]</a>
        <strong><a href="tools.php?action=banned_ip_users">[Returning Dupe IP's]</a></strong>
    </div>

    <div class="head">view settings</div>
    <table width="100%">
        <tr>
           <td class="colhead center" colspan="2">
                Viewing: banned for <?=$Reasons[$BanReason]?> in the last <?=$Weeks?> weeks &nbsp; (order: <?="$OrderBy $OrderWay"?>)
            </td>
        </tr>
        <tr>
            <td class="center">
                <label for="ban_reason" title="View Speed">Ban Reason </label>&nbsp;
                <select id="ban_reason" name="ban_reason" title="" onchange="change_view(<?="'$OrderBy','$OrderWay'"?>)">
<?php                   foreach ($Reasons as $Key=>$Reason) {   ?>
                        <option value="<?=$Key?>" <?=($Key==$BanReason?' selected="selected"':'');?>>&nbsp;<?=$Reason;?> &nbsp;</option>
<?php                   } ?>
                </select>
            </td>
            <td class="center">
                <label for="weeks" title="include where ban was >= weeks">banned within last </label>&nbsp;
                <input type="text" size="2" onchange="change_view(<?="'$OrderBy','$OrderWay'"?>)" id="weeks" name="weeks"  value="<?=$Weeks?>" />
                weeks
            </td>
        </tr>
    </table>
    <br/>

    <div class="linkbox"> <?=$Pages; ?> </div>

    <div class="head">Current Users with a Dupe IP from a previously banned account</div>
    <table width="100%">
        <tr class="colhead">
            <td class="center"><a href="<?=header_link('new_name') ?>">User</a></td>
            <td class="center"><a href="<?=header_link('joindate') ?>">Join Date</a></td>
            <td class="center"><a href="<?=header_link('IP') ?>">Shared IP</a></td>
            <td class="center"><a href="<?=header_link('b_name') ?>">Banned User</a></td>
            <td class="center"><a href="<?=header_link('bandate') ?>">Banned Date</a></td>
        </tr>
<?php
        if ($NumResults==0) {
?>
                    <tr class="rowb">
                        <td class="center" colspan="5">no duped users</td>
                    </tr>
<?php       } else {
            $i=0;
            foreach ($DupeRecords as $Record) {
                list($nID, $JoinDate, $IP, $bID, $BanDate) = $Record;
                $Row = ($Row == 'a') ? 'b' : 'a';
                $i++;
                $nInfo = user_info($nID);
                $bInfo = user_info($bID);
?>
                <tr class="row<?=$Row?>">
                    <td><?=format_username($nID, $nInfo['Username'], $nInfo['Donor'], $nInfo['Warned'], $nInfo['Enabled'], $nInfo['PermissionID'], false, false, $nInfo['GroupPermissionID'])?></td>
                    <td class="center"><?=time_diff($JoinDate)?></td>
                    <td><?=display_str($IP)?><span style="float:right;">[<a href="user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($IP)?>" title="User Search on this IP" target="_blank">S</a>]</span></td>
                    <td><?=format_username($bID, $bInfo['Username'], $bInfo['Donor'], $bInfo['Warned'], $bInfo['Enabled'], $bInfo['PermissionID'], false, false, $bInfo['GroupPermissionID'])?></td>

                    <td class="center"><?=time_diff($BanDate)?></td>
                </tr>
<?php           }
        }
?>
    </table>
    <div class="linkbox"> <?=$Pages; ?> </div>
</div>
<?php
show_footer();
