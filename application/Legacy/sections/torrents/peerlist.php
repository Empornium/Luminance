<?php
if (!check_perms('site_view_torrent_peerlist')) error(403,true);

if (!isset($_GET['torrentid']) || !is_integer_string($_GET['torrentid'])) {
    error(404, true);
}
$torrentID = $_GET['torrentid'];


list($page, $limit) = page_limit(100);
list($AuthorID, $IsAnon) = $master->db->rawQuery(
    "SELECT UserID,
            Anonymous
       FROM torrents
      WHERE ID = ?",
    [$torrentID]
)->fetch(\PDO::FETCH_NUM);

$records = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            xu.uid,
            t.Size,
            u.Username,
            xu.active,
            IF(ucs.Status IS NULL, 'unset',ucs.Status) AS Status,
            xu.uploaded,
            xu.remaining,
            xu.useragent,
            IF(xu.remaining=0,1,0) AS IsSeeder,
            xu.timespent,
            xu.upspeed,
            xu.downspeed,
            IF(xu.ipv6=\"\", INET6_NTOA(xu.ipv4), INET6_NTOA(xu.ipv6)) AS IP,
            xu.Port
       FROM xbt_files_users AS xu
  LEFT JOIN users AS u ON u.ID = xu.uid
  LEFT JOIN users_main AS um ON um.ID = xu.uid
  LEFT JOIN users_connectable_status AS ucs ON ucs.UserID = xu.uid AND INET6_NTOA(xu.ipv4) = ucs.IP
       JOIN torrents AS t ON t.ID = xu.fid
      WHERE xu.fid = ?
        AND um.Visible = '1'
   ORDER BY IsSeeder DESC,
            xu.uploaded DESC
      LIMIT {$limit}",
    [$torrentID]
)->fetchAll(\PDO::FETCH_NUM);
$NumResults = $master->db->foundRows();
?>

<?php  if ($NumResults > 100) { ?>
    <div class="linkbox"><?= js_pages('show_peers', $_GET['torrentid'], $NumResults, $page) ?></div>
    <?php  } ?>
<table>
    <?php
    if ($NumResults==0) {
            ?>
            <tr class="smallhead">
                <td colspan="11">There are no peers for this torrent</td>
            </tr>
            <?php
    }
    $LastIsSeeder = -1;
    foreach ($records as $record) {
        list($PeerUserID, $Size, $Username, $Active, $Connectable, $Uploaded, $Remaining, $UserAgent,
            $IsSeeder, $timespent, $UpSpeed, $DownSpeed, $IP, $Port) = $record;

        if ($IsSeeder != $LastIsSeeder) {
            ?>

            <tr class="smallhead">
                <td colspan="11"><?= ($IsSeeder ? 'Seeders' : 'Leechers') ?></td>
            </tr>
            <tr class="rowa" style="font-weight: bold;">
                <td>User</td>
                <td>Active</td>

                <td>Conn</td>

                <td>Up</td>
                <td>rate</td>
                <td>Down</td>
                <td>rate</td>
                <td>%</td>
                <td>Ratio</td>
                <td>Time</td>
                <td>Client</td>
            </tr>
            <?php
            $LastIsSeeder = $IsSeeder;
        }
        ?>
        <tr>
            <td><?= torrent_username($PeerUserID, $IsAnon && $PeerUserID == $AuthorID) ?></td>
            <td><?= ($Active) ? '<span style="color:green">Yes</span>' : '<span style="color:red">No</span>' ?></td>

            <td><?php
                if ($Active && $Port && (check_perms('users_mod') || $PeerUserID==$activeUser['ID'])) {
                    $link = 'user.php?action=connchecker&checkuser='.$PeerUserID.'&checkip='.$IP.'&checkport='.$Port;
                    if ($Connectable=='yes') echo '<a href="/'.$link.'" style="color:green">Yes</a>' ;
                    elseif ($Connectable=='no') echo'<a href="/'.$link.'" style="color:red">No</a>';
                    else echo'<a href="/'.$link.'" style="color:darkgrey">?</a>';
                } else {
                    if ($Connectable=='yes') echo '<span style="color:green">Yes</span>' ;
                    elseif ($Connectable=='no') echo'<span style="color:red">No</span>';
                    else echo'<span style="color:darkgrey">?</span>';
                }
                   ?></td>

            <td><?= get_size($Uploaded) ?></td>
            <td><?= get_size($UpSpeed, 2)?>/s</td>
            <td><?= get_size($Size - $Remaining, 2) ?></td>
            <td><?= get_size($DownSpeed, 2)?>/s</td>
            <td><?= number_format(($Size - $Remaining) / $Size * 100, 2) ?></td>
            <td><?= number_format(($Size - $Remaining) > 0 ? $Uploaded / ($Size - $Remaining) : 0, 3) ?></td>
            <td><?= time_span($timespent) ?></td>
            <td><?= display_str($UserAgent) ?></td>
        </tr>
        <?php
    }
    ?>
</table>
<?php  if ($NumResults > 100) { ?>
    <div class="linkbox"><?= js_pages('show_peers', $_GET['torrentid'], $NumResults, $page) ?></div>
<?php  }
