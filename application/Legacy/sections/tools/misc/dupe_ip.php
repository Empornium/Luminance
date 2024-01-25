<?php
if (!check_perms('users_view_ips')) { error(403); }

define('USERS_PER_PAGE', 50);
define('IP_OVERLAPS', 5);

if (empty($_GET['order_way']) || $_GET['order_way'] == 'asc') {
    $orderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $orderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], ['NumUsers', 'IP', 'StartTime', 'EndTime'])) {
    $_GET['order_by'] = 'NumUsers';
    $orderBy = 'NumUsers';
} else {
    $orderBy = $_GET['order_by'];
}

list($Page, $Limit) = page_limit(USERS_PER_PAGE);

$DupeIPtotals = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            Count(DISTINCT h.UserID) as NumUsers,
            INET6_NTOA(ips.StartAddress) as IP,
            Max(h.StartTime) as StartTime,
            Max(h.EndTime) as EndTime
       FROM users_history_ips AS h
       JOIN ips ON h.IPID=ips.ID
       JOIN users_main AS m ON m.ID=h.UserID
   GROUP BY h.IPID
     HAVING NumUsers>1
   ORDER BY {$orderBy} {$orderWay}
      LIMIT {$Limit}"
)->fetchAll(\PDO::FETCH_BOTH);

$NumResults = $master->db->foundRows();

$Pages = get_pages($Page, $NumResults, USERS_PER_PAGE, 9);

show_header('Dupe IPs', 'dupeip');

?>
<div class="thin">
    <h2>Dupe IPs</h2>
    <div class="linkbox">
        <strong><a href="/tools.php?action=dupe_ips">[Dupe IP's]</a></strong>
        <a href="/tools.php?action=banned_ip_users">[Returning Dupe IP's]</a>
    </div>

    <div class="linkbox"> <?=$Pages; ?> </div>

    <div class="head">Duped IP's</div>
    <table width="100%">
        <tr class="colhead">
            <td><a href="<?=header_link('IP') ?>">IP</a></td>
            <td class="center">Host</td>
            <td class="center"><a href="<?=header_link('NumUsers') ?>">Num Users</a></td>
            <td class="center"><a href="<?=header_link('StartTime') ?>">Last Start Time</a></td>
            <td class="center"><a href="<?=header_link('EndTime') ?>">Last End Time</a></td>
        </tr>
<?php
        if ($NumResults==0) {
?>
                    <tr class="rowb">
                        <td class="center" colspan="5">no duped ips</td>
                    </tr>
<?php       } else {
            $i=0;
            foreach ($DupeIPtotals as $Record) {
                list($NumUsers, $IP, $StartTime, $EndTime) = $Record;
                $Row = ($Row == 'a') ? 'b' : 'a';
                $i++;
?>
                <tr class="row<?=$Row?>">
                    <td><?=display_str($IP)?><span style="float:right;">[<a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($IP)?>" title="User Search on this IP" target="_blank">S</a>]</span></td>
                    <td class="center"><?=get_host($IP)?></td>
                    <td class="center"><?=display_str($NumUsers)?> &nbsp;
                     <span style="float:right;">
                         <a href="#" id="button_<?=$i?>" onclick="get_users('<?=$i?>', '<?=urlencode($IP)?>');return false;">(show)</a>
                     </span>&nbsp;
                    </td>
                    <td class="center"><?=time_diff($StartTime)?></td>
                    <td class="center"><?=time_diff($EndTime)?></td>
                </tr>
                <tr id="users_<?=$i?>" class="hidden"></tr>
<?php           }
        }
?>
    </table>
    <div class="linkbox"> <?=$Pages; ?> </div>
</div>
<?php
show_footer();
