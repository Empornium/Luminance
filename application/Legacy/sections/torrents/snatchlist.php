<?php
if (!isset($_GET['torrentid']) || !is_integer_string($_GET['torrentid'])) {
    error(404);
}

if (!check_perms('site_view_torrent_snatchlist')) {
    error(403);
}

$torrentID = $_GET['torrentid'];


list($page, $limit) = page_limit(100);
$records = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            xs.uid,
            xs.tstamp
       FROM xbt_snatched AS xs
      WHERE xs.fid = ?
   ORDER BY xs.tstamp DESC
      LIMIT {$limit}",
    [$torrentID]
)->fetchAll(\PDO::FETCH_NUM);

$NumResults = $master->db->foundRows();
?>

<?php  if ($NumResults > 100) { ?>
    <div class="linkbox"><?= js_pages('show_snatches', $_GET['torrentid'], $NumResults, $page) ?></div>
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

            foreach ($records as $record) {
                list($SnatcherID, $timestamp) = $record;

                $UserInfo = user_info($SnatcherID);

                $User = format_username($SnatcherID, $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID']);

                if ($i % 2 == 0 && $i > 0) {
                    ?>
                </tr>
                <tr>
                    <?php
                }
                ?>
                <td><?= $User ?></td>
                <td><?= time_diff($timestamp) ?></td>
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
    <div class="linkbox"><?= js_pages('show_snatches', $_GET['torrentid'], $NumResults, $page) ?></div>
<?php  }
