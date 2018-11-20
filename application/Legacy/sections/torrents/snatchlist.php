<?php
if (!isset($_GET['torrentid']) || !is_number($_GET['torrentid'])) {
    error(404);
}
if (!check_perms('site_view_torrent_snatchlist')) {
    error(403);
}
$TorrentID = $_GET['torrentid'];

if (!empty($_GET['page']) && is_number($_GET['page'])) {
    $Page = $_GET['page'];
    $Limit = (string) (($Page - 1) * 100) . ', 100';
} else {
    $Page = 1;
    $Limit = 100;
}

$Result = $DB->query("SELECT SQL_CALC_FOUND_ROWS
    xs.uid,
    xs.tstamp
    FROM xbt_snatched AS xs
    WHERE xs.fid='$TorrentID'
    ORDER BY xs.tstamp DESC
    LIMIT $Limit");
$Results = $DB->to_array('uid', MYSQLI_ASSOC);

$DB->query("SELECT FOUND_ROWS()");
list($NumResults) = $DB->next_record();
?>

<?php  if ($NumResults > 100) { ?>
    <div class="linkbox"><?= js_pages('show_snatches', $_GET['torrentid'], $NumResults, $Page) ?></div>
<?php  } ?>
<table>
    <?php
    if ($NumResults == 0) {
        ?>
        <tr class="smallhead">
            <td colspan="4">There have been no snatches of this torrent</td>
        </tr>
        <?php
    } else {
        ?>
        <tr class="smallhead">
            <td colspan="4">Snatches</td>
        </tr>
        <tr class="rowa" style="font-weight: bold;">
            <td>User</td>
            <td>Time</td>

            <td>User</td>
            <td>Time</td>
        </tr>
        <tr>
            <?php
            $i = 0;

            foreach ($Results as $ID => $Data) {
                list($SnatcherID, $Timestamp) = array_values($Data);

                $UserInfo = user_info($SnatcherID);

                $User = format_username($SnatcherID, $UserInfo['Username'], $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID']);

                if ($i % 2 == 0 && $i > 0) {
                    ?>
                </tr>
                <tr>
                    <?php
                }
                ?>
                <td><?= $User ?></td>
                <td><?= time_diff($Timestamp) ?></td>
                <?php
                $i++;
            }
            ?>
        </tr>
        <?php
    }
    ?>
</table>
<?php  if ($NumResults > 100) { ?>
    <div class="linkbox"><?= js_pages('show_snatches', $_GET['torrentid'], $NumResults, $Page) ?></div>
<?php  }
