<?php
if (!check_perms('users_view_ips')) { error(403); }

show_header('Multiple IP search');
if ($_POST['ips']) {
    $ips = preg_split('/[\s]+/', trim($_POST['ips']));
    $ips = array_unique($ips);
    $inQuery = implode(', ', array_fill(0, count($ips), '?'));
    $ipsSerialized = array_map('inet_pton', $ips);

    $ipids = $master->db->rawQuery(
        "SELECT ID
           FROM ips
          WHERE StartAddress IN ({$inQuery})",
        $ipsSerialized
    )->fetchAll(\PDO::FETCH_COLUMN);

    $results = [];
    if (!empty($ipids)) {
        $inQuery = implode(', ', array_fill(0, count($ipids), '?'));
        $params = $ipids;

        if (isset($_POST['ip_history'])) {
            $ipHistoryQuery = "UNION DISTINCT
                     SELECT uhi.UserID
                       FROM users_history_ips AS uhi
                      WHERE uhi.IPID IN ({$inQuery})";
            $params = array_merge($params, $ipids);
        } else {
            $ipHistoryQuery = "";
        }
        $userIDs = $master->db->rawQuery(
            "SELECT u.ID
               FROM users AS u
              WHERE u.IPID IN ({$inQuery})
             {$ipHistoryQuery}",
            $params
        )->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($userIDs)) {
            $inQuery = implode(', ', array_fill(0, count($userIDs), '?'));

            $results = $master->db->rawQuery(
                "SELECT DISTINCT SQL_CALC_FOUND_ROWS
                        um1.ID,
                        u.Username,
                        u.IRCNick,
                        um1.Uploaded,
                        um1.Downloaded,(SELECT COUNT(uid) FROM xbt_snatched AS xs WHERE xs.uid=um1.ID) AS Snatches,um1.PermissionID,
                        e1.Address,
                        um1.Enabled,
                        INET6_NTOA(ip.StartAddress), '' AS TrackerIP1, um1.Invites,
                        ui1.Donor,
                        ui1.JoinDate,
                        um1.LastAccess
                   FROM users_main AS um1
                   JOIN users_info AS ui1 ON ui1.UserID=um1.ID
                   JOIN users AS u ON u.ID=um1.ID
              LEFT JOIN ips AS ip ON ip.ID=u.IPID
              LEFT JOIN emails AS e1 ON e1.ID=u.EmailID
                  WHERE u.ID IN ({$inQuery}) ORDER BY ui1.JoinDate DESC",
                $userIDs
            )->fetchAll();
        }
    }

    $ipUserCount = array_count_values(array_column($results, 'INET6_NTOA(ip.StartAddress)'));
    foreach ($ips as $ip) {
      if (!array_key_exists($ip, $ipUserCount)) {
          $ipUserCount[$ip] = 0;
      }
    }
?>
    <div class="box pad center">
        <td>
        <b></b>Total IPs Searched:</b> <?= count($ips) ?><br>
        <b>Addresses:</b><br>
        <?php foreach ($ipUserCount as $ip => $userCount) { ?>
            <?= $ip ?> (<?= $userCount ?> user<?= (!($userCount === 1)) ? 's': '' ?> found)</br>
        <?php } ?>
        </td>
        <td><br><br>
        The accounts listed below match at least one of the IPs searched.
        </td>
        <table width="100%">
            <tr class="colhead">
                <td>Username</td>
                <!--Do we want IRC Nick to also display in the list -->
                <?php if ($master->options->AuthUserEnable) {?>
                <td>IRC Nick</td>
                <? } ?>
                <?php } ?>
                <td>Ratio</td>
<?php if (check_perms('users_view_ips')) { ?>
                <td>IP</td>
<?php } ?>
<?php if (check_perms('users_view_email')) { ?>
                <td>Email</td>
<?php } ?>
                <td>Joined</td>
                <td>Last Seen</td>
                <td>Upload</td>
                <td>Download</td>
                <td title="downloads (number of torrent files downloaded)">Dlds</td>
                <td title="snatched (number of torrents completed)">Sn'd</td>
                <td title="invites">Inv's</td>
            </tr>
<?php
foreach ($results as $result) {
    list($userID, $Username, $IRCNick, $Uploaded, $Downloaded, $Snatched, $class, $Email, $enabled,
         $IP, $trackerIP1, $Invites, $Donor, $JoinDate, $LastAccess) = $result;
?>
            <tr>
                <td><?=format_username($userID, $Donor, true, $enabled, $class)?></td>
                <!--Do we want IRC Nick to also display in the list -->
                <?php if ($master->options->AuthUserEnable) {?>
                <td><?=$IRCNick?></td>
                <?php } ?>
                <td><?=ratio($Uploaded, $Downloaded)?></td>
<?php if (check_perms('users_view_ips')) { ?>
                <td><?="<span title=\"account ip\">".display_ip($IP)."</span>";
                  if ($trackerIP1) echo "<br/><span title=\"current tracker ip\">".display_ip($trackerIP1)."</span>";
                  //if ($trackerIP2) echo "<br/><span title=\"tracker ip history\">".display_ip($trackerIP2)."</span>";
            ?>
                </td>
<?php } ?>
<?php if (check_perms('users_view_email')) { ?>
                <td><?=display_str($Email)?></td>
<?php } ?>
                <td><?=time_diff($JoinDate)?></td>
                <td><?=time_diff($LastAccess)?></td>
                <td><?=get_size($Uploaded)?></td>
                <td><?=get_size($Downloaded)?></td>
<?php $Downloads = $master->db->rawQuery("SELECT COUNT(ud.UserID) FROM users_downloads AS ud JOIN torrents AS t ON t.ID = ud.TorrentID WHERE ud.UserID = ?", [$userID])->fetchColumn();
?>
                <td><?=(int) $Downloads?></td>
                <td><?=is_integer_string($Snatched) ? number_format($Snatched) : display_str($Snatched)?></td>
                <td><?=is_integer_string($Invites) ? number_format($Invites) : display_str($Invites)?></td>
            </tr>
<?php
    }
?>
        </table>
    </div>
<?php
} else {
?>
    <div class="box pad left">
        <form method="post">
            <textarea name="ips" cols=32 rows=30 placeholder="Enter each IP on a separate line."></textarea>
            <br/>
            <input type="checkbox" name="ip_history" id="ip_history">
            <label for="ip_history">IP History</label>
            <input type="submit" value="Search" />
        </form>
    </div>
<?php
}
show_footer();
