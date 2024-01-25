<?php
if (!check_perms('users_view_ips')) { error(403); }

if (empty($_GET['order_way']) || $_GET['order_way'] == 'asc') {
    $orderWay = 'desc'; // For header links
} else {
    $_GET['order_way'] = 'asc';
    $orderWay = 'asc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], ['new_id', 'new_name', 'joindate', 'IP', 'b_id', 'b_name', 'bandate'])) {
    $_GET['order_by'] = 'joindate';
    $orderBy = 'joindate';
} else {
    $orderBy = $_GET['order_by'];
}
/*
 * BanReason 0 - Unknown, 1 - Manual, 2 - Ratio, 3 - Inactive, 4 - Cheating.
 */
$Reasons = [
    0 => 'Unknown',
    1 => 'Manual',
    2 => 'Ratio',
    3 => 'Inactive',
    4 => 'Cheating',
];
$BanReason = (isset($_GET['ban_reason']) && is_integer_string($_GET['ban_reason']) && $_GET['ban_reason'] < 5) ? (int) $_GET['ban_reason'] : 4 ;

$Weeks =  (isset($_GET['weeks']) && is_integer_string($_GET['weeks'])) ? (int) $_GET['weeks'] : 1 ;
if ($Weeks > 104) $Weeks = 104;

list($Page, $Limit) = page_limit(25);

$CachedDupeResults = $master->cache->getValue("dupeip_users_{$BanReason}_{$Weeks}_$orderBy{$orderWay}_$Page");
if ($CachedDupeResults===false) {

    $DupeRecords = $master->db->rawQuery("SELECT SQL_CALC_FOUND_ROWS
                       n.ID as new_id,
                       n.JoinDate as joindate,
                       b.IP as IP,
                       b.ID as b_id,
                       b.BanDate as bandate,
                       n.Username as new_name,
                       b.Username as b_name
                  FROM (SELECT bu.ID, bu.Username, bi.BanDate, INET6_NTOA(bip.StartAddress) AS IP
                        FROM users_info AS bi
                        JOIN users_main AS bm ON bi.UserID=bm.ID
                        JOIN users AS bu ON bu.ID=bm.ID
                        JOIN ips AS bip ON bip.ID=bu.IPID
                        WHERE bm.Enabled='2' AND bi.Banreason = ? AND bi.BanDate > (NOW() - INTERVAL ? WEEK)
                            ) AS b
                  JOIN (SELECT nu.ID, nu.Username, ni.JoinDate, INET6_NTOA(nip.StartAddress) AS IP
                        FROM users_info as ni
                        JOIN users_main as nm ON ni.UserID=nm.ID
                        JOIN users AS nu ON nu.ID=nm.ID
                        JOIN ips AS nip ON nip.ID=nu.IPID
                        WHERE nm.Enabled='1'
                            ) AS n
                            ON n.IP=b.IP AND n.ID!=b.ID AND n.JoinDate>b.BanDate
              ORDER BY {$orderBy} {$orderWay}
                 LIMIT {$Limit}",
        [$BanReason, $Weeks]
    )->fetchAll(\PDO::FETCH_BOTH);

    $NumResults = $master->db->foundRows();
    $CachedDupeResults = [$NumResults, $DupeRecords];
    $master->cache->cacheValue("dupeip_users_{$BanReason}_{$Weeks}_$orderBy{$orderWay}_$Page", $CachedDupeResults, 3600*24);
} else {
    list($NumResults, $DupeRecords) = $CachedDupeResults;
}

$Pages = get_pages($Page, $NumResults, 25, 9);

show_header('Dupe IPs', 'dupeip');

?>
<div class="thin">
    <h2>Returning Dupe IP's</h2>
    <div class="linkbox">
        <a href="/tools.php?action=dupe_ips">[Dupe IP's]</a>
        <strong><a href="/tools.php?action=banned_ip_users">[Returning Dupe IP's]</a></strong>
    </div>

    <div class="head">view settings</div>
    <table width="100%">
        <tr>
           <td class="colhead center" colspan="2">
                Viewing: banned for <?=$Reasons[$BanReason]?> in the last <?=$Weeks?> weeks &nbsp; (order: <?="$orderBy $orderWay"?>)
            </td>
        </tr>
        <tr>
            <td class="center">
                <label for="ban_reason" title="View Speed">Ban Reason </label>&nbsp;
                <select id="ban_reason" name="ban_reason" title="" onchange="change_view(<?="'$orderBy', '$orderWay'"?>)">
<?php                   foreach ($Reasons as $Key=>$Reason) {   ?>
                        <option value="<?=$Key?>" <?=($Key==$BanReason?' selected="selected"':'');?>>&nbsp;<?=$Reason;?> &nbsp;</option>
<?php                   } ?>
                </select>
            </td>
            <td class="center">
                <label for="weeks" title="include where ban was >= weeks">banned within last </label>&nbsp;
                <input type="text" size="2" onchange="change_view(<?="'$orderBy', '$orderWay'"?>)" id="weeks" name="weeks"  value="<?=$Weeks?>" />
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
                    <td><?=format_username($nID, $nInfo['Donor'], true, $nInfo['Enabled'], $nInfo['PermissionID'], false, false, $nInfo['GroupPermissionID'])?></td>
                    <td class="center"><?=time_diff($JoinDate)?></td>
                    <td><?=display_str($IP)?><span style="float:right;">[<a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($IP)?>" title="User Search on this IP" target="_blank">S</a>]</span></td>
                    <td><?=format_username($bID, $bInfo['Donor'], true, $bInfo['Enabled'], $bInfo['PermissionID'], false, false, $bInfo['GroupPermissionID'])?></td>

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
