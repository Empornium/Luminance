<?php
if (!check_perms('site_view_flow')) { error(403); }
show_header('Upscale Pool');
?>
<div class="thin">
    <h2>Ratio Watch</h2>
<?php
define('USERS_PER_PAGE', 50);
list($Page, $Limit) = page_limit(USERS_PER_PAGE);

$records = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            u.ID,
            u.Username,
            um.Uploaded,
            um.Downloaded,
            um.PermissionID,
            um.Enabled,
            ui.Donor,
            ui.JoinDate,
            ui.RatioWatchEnds,
            ui.RatioWatchDownload,
            um.RequiredRatio
       FROM users AS u
  LEFT JOIN users_main AS um ON um.ID=u.ID
  LEFT JOIN users_info AS ui ON ui.UserID=u.ID
      WHERE ui.RatioWatchEnds != '0000-00-00 00:00:00'
        AND um.Enabled = '1'
   ORDER BY ui.RatioWatchEnds ASC
      LIMIT {$Limit}"
)->fetchAll(\PDO::FETCH_NUM);
$Results = $master->db->foundRows();

$TotalDisabled = $master->db->rawQuery(
    "SELECT COUNT(UserID)
       FROM users_info
      WHERE BanDate != '0000-00-00 00:00:00'
        AND BanReason = '2'"
)->fetchColumn();

if ($Results > 0) {
?>
        <div class="box pad">
        <p>There are currently <?=number_format($Results)?> users queued by the system and <?=number_format($TotalDisabled)?> already disabled.</p>
    </div>
    <div class="linkbox">
<?php
    $Pages = get_pages($Page, $Results, USERS_PER_PAGE, 11);
    echo $Pages;
?>
    </div>
    <table width="100%">
        <tr class="colhead">
            <td>User</td>
            <td>Up</td>
            <td>Down</td>
            <td>Ratio</td>
            <td>Required Ratio</td>
            <td>Defecit</td>
            <td>Gamble</td>
            <td>Registered</td>
            <td>Remaining</td>
        </tr>
<?php
    foreach ($records as $record) {
    list($userID, $Username, $Uploaded, $Downloaded, $PermissionID, $enabled, $Donor, $Joined, $RatioWatchEnds, $RatioWatchDownload, $RequiredRatio) = $record;
    $row = ($row ?? 'a') == 'a' ? 'b' : 'a';

?>
        <tr class="row<?=$row?>">
            <td><?=format_username($userID, $Donor, true, $enabled, $PermissionID)?></td>
            <td><?=get_size($Uploaded)?></td>
            <td><?=get_size($Downloaded)?></td>
            <td><?=ratio($Uploaded, $Downloaded)?></td>
            <td><?=number_format($RequiredRatio, 2)?></td>
            <td><?php  if (($Downloaded*$RequiredRatio)>$Uploaded) { echo get_size(($Downloaded*$RequiredRatio)-$Uploaded);}?></td>
            <td><?=get_size($Downloaded-$RatioWatchDownload)?></td>
            <td><?=time_diff($Joined,2)?></td>
            <td><?=time_diff($RatioWatchEnds)?></td>
        </tr>
<?php 	} ?>
    </table>
    <div class="linkbox">
<?php  echo $Pages; ?>
    </div>
<?php  } else { ?>
    <h2 align="center">There are currently no users on ratio watch.</h2>
<?php  }
?>
</div>
<?php
show_footer();
