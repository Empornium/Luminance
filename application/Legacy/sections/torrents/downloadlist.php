<?php
if (!isset($_GET['torrentid']) || !is_integer_string($_GET['torrentid'])) {
    error(404);
}
if ( !check_perms('site_view_torrent_snatchlist')) {
    error(403);
}
$torrentID = $_GET['torrentid'];

if (isset($activeUser['TorrentsPerPage'])) {
    $torrentsPerPage = $activeUser['TorrentsPerPage'];
} else {
    $torrentsPerPage = TORRENTS_PER_PAGE;
}

list($page, $limit) = page_limit($torrentsPerPage);
if (!$master->options->EnableDownloads) {
    error("Downloads are currently disabled.");
}

$results = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            ud.UserID,
            ud.UserID,
            ud.Time
       FROM users_downloads AS ud
      WHERE ud.TorrentID = ?
   ORDER BY ud.Time DESC
      LIMIT {$limit}",
    [$torrentID]
)->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_OBJ);
$userIDs = array_keys($results);

$NumResults = $master->db->foundRows();

if (count($userIDs) > 0) {
    $inQuery = implode(', ', array_fill(0, count($userIDs), '?'));
    $Snatched = $master->db->rawQuery(
        "SELECT uid
           FROM xbt_snatched
          WHERE fid = ?
            AND uid IN ({$inQuery})",
        array_merge([$torrentID], $userIDs)
    )->fetchAll(\PDO::FETCH_COLUMN);

    $Seeding = $master->db->rawQuery(
        "SELECT uid
           FROM xbt_files_users
          WHERE fid = ?
            AND Remaining = 0
            AND uid IN ({$inQuery})",
        array_merge([$torrentID], $userIDs)
    )->fetchAll(\PDO::FETCH_COLUMN);
}
?>

<?php  if ($NumResults > 100) { ?>
    <div class="linkbox"><?= js_pages('show_downloads', $_GET['torrentid'], $NumResults, $page) ?></div>
<?php  } ?>
<table>
    <?php
    if ($NumResults == 0) {
        ?>
        <tr class="smallhead">
            <td colspan="4">There have been no downloads of this torrent</td>
        </tr>
        <?php
    } else {
        ?>
        <tr class="smallhead">
            <td colspan="4">Downloadlist</td>
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

            foreach ($results as $result) {
                $UserInfo = user_info($result->UserID);

                $User = format_username($result->UserID, $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID']);

                if (!array_key_exists($result->UserID, $Snatched) && $result->UserID != $userID) {
                    $User = '<em>' . $User . '</em>';
                    if (array_key_exists($result->UserID, $Seeding)) {
                        $User = '<strong>' . $User . '</strong>';
                    }
                }
                if ($i % 2 == 0 && $i > 0) {
                    ?>
                </tr>
                <tr>
                    <?php
                }
                ?>
                <td><?= $User ?></td>
                <td><?= time_diff($result->Time) ?></td>
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
    <div class="linkbox"><?= js_pages('show_downloads', $_GET['torrentid'], $NumResults, $page) ?></div>
<?php  }
