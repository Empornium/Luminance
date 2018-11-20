<?php
/************************************************************************
||------------|| User IP history page ||---------------------------||

This page lists previous IPs a user has connected to the site with. It
gets called if $_GET['action'] == 'ips'.

It also requires $_GET['userid'] in order to get the data for the correct
user.

************************************************************************/

define('IPS_PER_PAGE', 25);

if (!check_perms('users_mod')) {
    error(403);
}

$UserID = $_GET['userid'];
if (!is_number($UserID)) {
    error(404);
}

$DB->query("SELECT um.Username, p.Level AS Class FROM users_main AS um LEFT JOIN permissions AS p ON p.ID=um.PermissionID WHERE um.ID = ".$UserID);
list($Username, $Class) = $DB->next_record();

if (!check_perms('users_view_ips', $Class)) {
    error(403);
}

$UsersOnly = $_GET['usersonly'];

show_header("Tracker IP history for $Username");
?>
<script type="text/javascript">
function ShowIPs(rowname)
{
    $('tr[name="'+rowname+'"]').toggle();
}
</script>
<div class="thin">
<?php
list($Page,$Limit) = page_limit(IPS_PER_PAGE);

$TrackerIps = $DB->query("SELECT IF(INET6_NTOA(ipv6) IS NULL, INET6_NTOA(ipv4), INET6_NTOA(ipv6)) AS IP, fid, tstamp FROM xbt_snatched WHERE uid = ".$UserID." AND (ipv4!='' OR ipv6!='') ORDER BY tstamp DESC LIMIT $Limit");

$DB->query("SELECT FOUND_ROWS()");
list($NumResults) = $DB->next_record();
$DB->set_query_id($TrackerIps);

$Pages=get_pages($Page, $NumResults, IPS_PER_PAGE, 9);

?>
    <div class="linkbox"><?=$Pages?></div>
        <div class="head">Tracker IP history for <a href="/user.php?id=<?=$UserID?>"><?=$Username?></a></div>
    <table>
        <tr class="colhead">
            <td>IP address</td>
            <td>Torrent</td>
            <td>Time</td>
        </tr>
<?php
$Results = $DB->to_array();
foreach ($Results as $Index => $Result) {
    list($IP, $TorrentID, $Time) = $Result;

?>
    <tr class="rowa">
        <td>
                <?=display_ip($IP, geoip($IP))?><br />
                <?=get_host($IP)?>
        </td>
        <td><a href="/details.php?id=<?=$TorrentID?>"><?=$TorrentID?></a></td>
        <td><?=date("Y-m-d g:i:s", $Time)?></td>
    </tr>
<?php
}
?>
</table>
<div class="linkbox">
    <?=$Pages?>
</div>
</div>

<?php
show_footer();
