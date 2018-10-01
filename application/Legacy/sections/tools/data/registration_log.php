<?php
if (!check_perms('users_view_ips') || !check_perms('users_view_email')) { error(403); }
show_header('Registration log');
?>
<div class="thin">
    <h2>Registration Log</h2>
<?php
define('USERS_PER_PAGE', 50);
list($Page,$Limit) = page_limit(USERS_PER_PAGE);

$RS = $DB->query("SELECT
    SQL_CALC_FOUND_ROWS
    m.ID,
    u.IPID,
    m.Email,
    m.Username,
    m.PermissionID,
    m.Uploaded,
    m.Downloaded,
    m.Enabled,
    i.Donor,
    i.JoinDate,
    im.ID,
    iu.IPID,
    im.Email,
    im.Username,
    im.PermissionID,
    im.Uploaded,
    im.Downloaded,
    im.Enabled,
    ii.Donor,
    ii.JoinDate
    FROM users_main AS m
    LEFT JOIN users_info AS i ON i.UserID=m.ID
    LEFT JOIN users AS u ON u.ID=m.ID
    LEFT JOIN users_main AS im ON i.Inviter = im.ID
    LEFT JOIN users_info AS ii ON i.Inviter = ii.UserID
    LEFT JOIN users AS iu ON iu.ID=im.ID
    WHERE i.JoinDate > '".time_minus(3600*24*7)."'
    ORDER BY i.Joindate DESC LIMIT $Limit");
$DB->query("SELECT FOUND_ROWS()");
list($Results) = $DB->next_record();
$DB->set_query_id($RS);

if ($DB->record_count()) {
?>
    <div class="linkbox">
<?php
    $Pages=get_pages($Page,$Results,USERS_PER_PAGE,11) ;
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
    $Records = $DB->to_array();
    foreach ($Records as $Record) {
        list($UserID, $IPID, $Email, $Username, $PermissionID, $Uploaded, $Downloaded, $Enabled, $Donor, $Joined, $InviterID, $InviterIPID, $InviterEmail, $InviterUsername, $InviterPermissionID, $InviterUploaded, $InviterDownloaded, $InviterEnabled, $InviterDonor, $InviterJoined)=$Record;

        $IP = $master->repos->ips->load($IPID);
        $InviterIP = $master->repos->ips->load($InviterIPID);
        $Row = ($IP == $InviterIP) ? 'a' : 'b';
?>
        <tr class="row<?=$Row?>">
            <td><?=format_username($UserID, $Username, $Donor, true, $Enabled, $PermissionID)?><br /><?=format_username($InviterID, $InviterUsername, $InviterDonor, true, $InviterEnabled, $InviterPermissionID)?></td>
            <td><?=ratio($Uploaded,$Downloaded)?><br /><?=ratio($InviterUploaded,$InviterDownloaded)?></td>
            <td>
                <span style="float:left;"><?=display_str($Email)?></span>
                <span style="float:right;">[<a href="/userhistory.php?action=email&amp;userid=<?=$UserID?>" title="History">H</a>|<a href="/user.php?action=search&email_history=on&email=<?=display_str($Email)?>" title="Search">S</a>]</span><br />
                <span style="float:left;"><?=display_str($InviterEmail)?></span>
                <span style="float:right;">[<a href="/userhistory.php?action=email&amp;userid=<?=$InviterID?>" title="History">H</a>|<a href="/user.php?action=search&amp;email_history=on&amp;email=<?=display_str($InviterEmail)?>" title="Search">S</a>]</span><br />
            </td>
            <td>
                <span style="float:left;"><?php  $CC = geoip($IP);  echo display_ip($IP, $CC); ?></span>
                <span style="float:right;">[<a href="/userhistory.php?action=ips&amp;userid=<?=$UserID?>" title="History">H</a>|<a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?=display_str($IP)?>" title="Search">S</a>]</span><br />
                <span style="float:left;"><?php  $InviterCC = geoip($InviterIP);  echo display_ip($InviterIP, $InviterCC); ?></span>
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
    <h2 align="center">There have been no new registrations in the past 168 hours (1 week).</h2>
<?php } ?>
</div>
<?php
show_footer();
