<?php
if (!check_perms('users_view_ips') || !check_perms('users_view_email')) { error(403); }
show_header('Registration log');
?>
<div class="thin">
    <h2>Registration Log</h2>
<?php
define('USERS_PER_PAGE', 50);
list($Page, $Limit) = page_limit(USERS_PER_PAGE);

$records = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            u.ID,
            u.IPID,
            e.Address,
            u.Username,
            um.PermissionID,
            um.Uploaded,
            um.Downloaded,
            um.Enabled,
            ui.Donor,
            ui.JoinDate,
            iu.ID,
            iu.IPID,
            ie.Address,
            iu.Username,
            ium.PermissionID,
            ium.Uploaded,
            ium.Downloaded,
            ium.Enabled,
            iui.Donor,
            iui.JoinDate
       FROM users AS u
  LEFT JOIN users_main AS um ON um.ID=u.ID
  LEFT JOIN users_info AS ui ON ui.UserID=u.ID
  LEFT JOIN emails AS e ON e.ID=u.EmailID
  LEFT JOIN users AS iu ON iu.ID=ui.Inviter
  LEFT JOIN users_main AS ium ON ium.ID=ui.Inviter
  LEFT JOIN users_info AS iui ON iui.UserID=ui.Inviter
  LEFT JOIN emails AS ie ON ie.ID=iu.EmailID
      WHERE ui.JoinDate > '".time_minus(3600*24*14)."'
   ORDER BY ui.Joindate DESC
      LIMIT {$Limit}"
)->fetchAll(\PDO::FETCH_NUM);
$Results = $master->db->foundRows();

if ($Results > 0) {
?>
    <div class="linkbox">
<?php
    $Pages = get_pages($Page, $Results, USERS_PER_PAGE, 11);
    echo $Pages;
?>
    </div>
    <table width="100%">
        <tr class="colhead">
            <td>User</td>
            <td>Ratio</td>
            <td>Email</td>
            <td style="width: 22%">IP</td>
            <td>Host</td>
            <td>Registered</td>
        </tr>
<?php
    foreach ($records as $record) {
        list(
            $userID,
            $IPID,
            $Email,
            $Username,
            $PermissionID,
            $Uploaded,
            $Downloaded,
            $enabled,
            $Donor,
            $Joined,
            $InviterID,
            $InviterIPID,
            $InviterEmail,
            $InviterUsername,
            $InviterPermissionID,
            $InviterUploaded,
            $InviterDownloaded,
            $InviterEnabled,
            $InviterDonor,
            $InviterJoined
        ) = $record;

        $IP = $master->repos->ips->load($IPID);
        $InviterIP = $master->repos->ips->load($InviterIPID);
        $Row = ($IP == $InviterIP) ? 'a' : 'b';
?>
        <tr class="row<?=$Row?>">
            <td><?=format_username($userID, $Donor, true, $enabled, $PermissionID)?><br /><?=format_username($InviterID, $InviterDonor, true, $InviterEnabled, $InviterPermissionID)?></td>
            <td><?=ratio($Uploaded, $Downloaded)?><br /><?=ratio($InviterUploaded, $InviterDownloaded)?></td>
            <td>
                <span style="float:left;"><?=display_str($Email)?></span>
                <span style="float:right;">[<a href="/userhistory.php?action=email&amp;userid=<?=$userID?>" title="History">H</a>|<a href="/user.php?action=search&email_history=on&email=<?=display_str($Email)?>" title="Search">S</a>]</span><br />
                <span style="float:left;"><?=display_str($InviterEmail)?></span>
                <span style="float:right;">[<a href="/userhistory.php?action=email&amp;userid=<?=$InviterID?>" title="History">H</a>|<a href="/user.php?action=search&amp;email_history=on&amp;email=<?=display_str($InviterEmail)?>" title="Search">S</a>]</span><br />
            </td>
            <td>
                <span style="float:left;"><?= display_ip($IP) ?></span>
                <span style="float:right;">[<a href="/userhistory.php?action=ips&amp;userid=<?=$userID?>" title="History">H</a>|<a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($IP)?>" title="Search">S</a>]</span><br />
                <span style="float:left;"><?= display_ip($InviterIP) ?></span>
                <span style="float:right;">[<a href="/userhistory.php?action=ips&amp;userid=<?=$InviterID?>" title="History">H</a>|<a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($InviterIP)?>" title="Search">S</a>]</span><br />
            </td>
            <td>
                <?=get_host($IP)?><br />
                <?=get_host($InviterIP)?>
            </td>
            <td><?=time_diff($Joined)?><br /><?=time_diff($InviterJoined)?></td>
        </tr>
<?php	} ?>
    </table>
    <div class="linkbox">
<?=$Pages; ?>
    </div>
<?php } else { ?>
    <h2 align="center">There have been no new registrations in the past 336 hours (2 weeks).</h2>
<?php } ?>
</div>
<?php
show_footer();
