<?php
/************************************************************************
||------------|| User IP history page ||---------------------------||

This page lists previous IPs a user has connected to the site with. It
gets called if $_GET['action'] == 'ips'.

It also requires $_GET['userid'] in order to get the data for the correct
user.

************************************************************************/

define('IPS_PER_PAGE', 25);

if (!check_perms('users_mod')) { error(403); }

$userID = $_GET['userid'];
if (!is_integer_string($userID)) { error(404); }

$user = $master->repos->users->load($userID);

$grouped = $_GET['grouped'] ?? false;

if (!check_perms('users_view_ips', $user->class->Level)) {
    error(403);
}

show_header("Tracker IP history for $user->Username");
?>
<script type="text/javascript">
function ShowIPs(rowname)
{
    $('tr[name="'+rowname+'"]').toggle();
}
</script>
<div class="thin">
<?php
list($Page, $Limit) = page_limit(IPS_PER_PAGE);

if ($grouped) {
    $results = $master->db->rawQuery(
        "SELECT SQL_CALC_FOUND_ROWS
                IF(INET6_NTOA(ipv6) IS NULL, INET6_NTOA(ipv4), INET6_NTOA(ipv6)) AS IP,
                COUNT(fid) AS torrents,
                tstamp
           FROM xbt_snatched
          WHERE uid = ?
            AND (ipv4!='' OR ipv6!='')
       GROUP BY IP
       ORDER BY torrents DESC
          LIMIT {$Limit}",
        [$user->ID]
    )->fetchAll(\PDO::FETCH_NUM);
} else {
    $results = $master->db->rawQuery(
        "SELECT SQL_CALC_FOUND_ROWS
                IF(INET6_NTOA(ipv6) IS NULL, INET6_NTOA(ipv4), INET6_NTOA(ipv6)) AS IP,
                fid,
                tstamp
           FROM xbt_snatched
          WHERE uid = ?
            AND (ipv4!='' OR ipv6!='')
       ORDER BY tstamp DESC
          LIMIT {$Limit}",
        [$user->ID]
    )->fetchAll(\PDO::FETCH_NUM);
}

$numResults = $master->db->foundRows();
$Pages = get_pages($Page, $numResults, IPS_PER_PAGE, 9);

?>
    <div class="linkbox">
        <?php
        if ($grouped) {
        ?>
            <br /><br />
            [<a href="/userhistory.php?action=tracker_ips&userid=<?=$user->ID?>">Show Individual</a>]&nbsp;&nbsp;&nbsp;
        <?php
        } else {
        ?>
            <br /><br />
            [<a href="/userhistory.php?action=tracker_ips&grouped=1&userid=<?=$user->ID?>">Show Grouped</a>]&nbsp;&nbsp;&nbsp;
        <?php
        }
        ?>
    </div>
    <div class="linkbox pager"><?= $Pages ?></div>
    <div class="head">Tracker IP history for <a href="/user.php?id=<?=$user->ID?>"><?=$user->Username?></a></div>
    <table>
        <tr class="colhead">
            <td>IP address</td>
            <?php
            if ($grouped) {
            ?>
                <td>Torrents</td>
            <?php
            } else {
            ?>
                <td>Torrent</td>
            <?php
            }
            ?>
            <td>Time</td>
        </tr>
<?php
foreach ($results as $result) {
    list($IP, $torrentID, $time) = $result;

?>
    <tr class="rowa">
        <td>
                <?=display_ip($IP)?><br />
                <?=get_host($IP)?>
        </td>
        <?php
        if ($grouped) {
        ?>
            <td><?=$torrentID?></td>
        <?php
        } else {
        ?>
            <td><a href="/torrents.php?id=<?=$torrentID?>"><?=$torrentID?></a></td>
        <?php
        }
        ?>
        <td><?=date("Y-m-d g:i:s", $time)?></td>
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
