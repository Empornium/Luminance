<?php
if (!check_perms('users_view_ips')) { error(403); }
show_header('Dupe IPs');
?>
<div class="thin">
    <h2>Dupe IPs</h2>
<?php
define('USERS_PER_PAGE', 50);
define('IP_OVERLAPS', 5);
list($Page, $Limit) = page_limit(USERS_PER_PAGE);

$records = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            um.ID,
            INET6_NTOA(i.StartAddress),
            u.Username,
            um.PermissionID,
            um.Enabled,
            ui.Donor,
            ui.JoinDate,
            (SELECT COUNT(DISTINCT h.UserID) FROM users_history_ips AS h WHERE h.IPID=i.ID) AS Uses
        FROM users AS u
   LEFT JOIN users_main AS um ON um.ID=u.ID
   LEFT JOIN users_info AS ui ON ui.UserID=u.ID
   LEFT JOIN ips AS i ON i.ID=u.IPID
       WHERE (SELECT COUNT(DISTINCT h.UserID) FROM users_history_ips AS h WHERE h.IPID=i.ID) >= ".IP_OVERLAPS."
         AND um.Enabled = '1'
         AND INET6_NTOA(i.StartAddress) != '127.0.0.1'
    ORDER BY Uses DESC LIMIT {$Limit}"
)->fetchAll(\PDO::FETCH_NUM);
$Results = $master->db->foundRows();

if ($Results > 0) {
?>
    <div class="linkbox">
<?php
    $Pages = get_pages($Page, $Results, USERS_PER_PAGE, 11) ;
    echo $Pages;
?>
    </div>
    <table width="100%">
        <tr class="colhead">
            <td>User</td>
            <td>IP</td>
            <td>Dupes</td>
            <td>Registered</td>
        </tr>
<?php
    foreach ($records as $record) {
        list($userID, $IP, $Username, $PermissionID, $enabled, $Donor, $Joined, $Uses) = $record;
    $Row = ($Row == 'b') ? 'a' : 'b';
?>
        <tr class="row<?=$Row?>">
            <td><?=format_username($userID, $Donor, true, $enabled, $PermissionID)?></td>
            <td><span style="float:left;"><?=get_host($IP)." ($IP)"?></span><span style="float:right;">[<a href="/userhistory.php?action=ips&amp;userid=<?=$userID?>" title="History">H</a>|<a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($IP)?>" title="Search">S</a>]</span></td>
            <td><?=display_str($Uses)?></td>
            <td><?=time_diff($Joined)?></td>
        </tr>
<?php 	} ?>
    </table>
    <div class="linkbox">
<?php  echo $Pages; ?>
    </div>
<?php  } else { ?>
    <h2>There are currently no users with more than <?=IP_OVERLAPS?> IP overlaps.</h2>
<?php  }
?>
</div>
<?php
show_footer();
