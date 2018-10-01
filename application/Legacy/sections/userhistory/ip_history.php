<?php

define('IPS_PER_PAGE', 25);

if (!is_number($_GET['userid'])) {
    error(404);
}

$UserID = (int) $_GET['userid'];
$UsersOnly = (int) $_GET['usersonly'];

// Get selected user
$DB->query("SELECT um.Username, p.Level AS Class FROM users_main AS um LEFT JOIN permissions AS p ON p.ID=um.PermissionID WHERE um.ID = ".$UserID);
list($Username, $Class) = $DB->next_record();

if (!$Username) {
    error(404);
}

if (!check_perms('users_view_ips', $Class)) {
    error(403);
}

list($Page,$Limit) = page_limit(IPS_PER_PAGE);

// Get user's IPs history first
$Query = $master->db->raw_query("SELECT SQL_CALC_FOUND_ROWS
    h1.IP,
    h1.StartTime,
    h1.EndTime
    FROM users_history_ips AS h1
    WHERE h1.UserID = :UserID
    GROUP BY h1.IP, h1.StartTime
    ORDER BY h1.StartTime DESC LIMIT $Limit", [$UserID]);

$IPs = $Query->fetchAll(\PDO::FETCH_ASSOC);

// Get number of rows for latter pagination
$NumResults =  $master->db->raw_query('SELECT FOUND_ROWS()')->fetchColumn();

// The same IP can appear multiple times (different timeframe),
// so we cache dupes for each IP
$Dupes = [];

foreach ($IPs as $Index => &$IP) {
    $CurrentIP = $IP['IP'];

    // No need to query cached dupes,
    // but some processing is still needed
    if (array_key_exists($CurrentIP, $Dupes)) {
        $IP['HasDupes']   = (bool) $Dupes[$CurrentIP];
        $IP['DupesCount'] = count($Dupes[$CurrentIP]);
        $IP['Html']       = getIpHtml($IP, $Index);
        continue;
    }

    $Query = $master->db->raw_query("SELECT
        h1.IP,
        h1.StartTime,
        h1.EndTime,
        h1.UserID,
        um.Username,
        um.Enabled
        FROM users_history_ips AS h1
        LEFT JOIN users_main AS um ON um.ID = h1.UserID
        WHERE h1.UserID!= :UserID AND h1.IP = :IP
        GROUP BY h1.IP, h1.StartTime, h1.UserID
        ORDER BY h1.StartTime DESC LIMIT 50",
        [$UserID, $CurrentIP]);

    $CurrentDupes = $Query->fetchAll(\PDO::FETCH_ASSOC);

    $IP['HasDupes']   = (bool) $CurrentDupes;
    $IP['DupesCount'] = count($CurrentDupes);
    $IP['Html']       = getIpHtml($IP, $Index);

    $Dupes[$CurrentIP] = $CurrentDupes;
}

function getIpHtml($IP, $Index)
{
    global $UserID;

    $CurrentIP = $IP['IP'];

    $html = display_ip($CurrentIP, geoip($CurrentIP));

    // Add admin actions on IP
    if (check_perms('admin_manage_ipbans')) {
        $html .= '[<a href="/tools.php?action=ip_ban&userid='.$UserID.'&uip='.display_str($CurrentIP).'" title="Ban this users IP ('.display_str($CurrentIP).') ?>)">IP Ban</a>]';
    }

    $html .= '<br />'.get_host($CurrentIP).'<br />';

    if ($IP['HasDupes']) {
        $html .= '<a id="toggle'.$Index.'" href="#" onclick="ShowIPs('.$Index.'); return false;">show/hide dupes ('.$IP['DupesCount'] .')</a>';
    }

    return $html;
}

// Recommended safety when $IP is called by reference
unset($IP);

// Show only IPs with dupes
if ($UsersOnly === 1) {
    $IPs = array_filter($IPs, function($IP){
        return $IP['HasDupes'];
    });
}

$Pages = get_pages($Page, $NumResults, IPS_PER_PAGE, 9);

show_header('IP history for '.display_str($Username));
?>

    <script type="text/javascript">
        function ShowIPs(rowname) {
            $('tr[data-name="'+rowname+'"]').toggle();
        }
    </script>

    <div class="thin">
        <div class="linkbox"><?= $Pages ?></div>
        <div class="head">IP history for <a href="/user.php?id=<?= $UserID ?>"><?= display_str($Username) ?></a></div>
        <table>
            <tr class="colhead">
                <td style="width:20%">IP address</td>
                <td style="width:30%">Started</td>
                <td style="width:20%">Ended</td>
                <td>Elapsed</td>
            </tr>
            <?php foreach($IPs as $Index => $IP): ?>
                <tr class="rowa">
                    <td><?= $IP['Html']; ?></td>
                    <td><?= time_diff($IP['StartTime']) ?></td>
                    <td><?= time_diff($IP['EndTime']) ?></td>
                    <td><?= time_diff(strtotime($IP['StartTime']), strtotime($IP['EndTime'])) ?></td>
                </tr>
                <?php foreach($Dupes[$IP['IP']] as $Dupe): ?>
                    <tr class="rowb <?= ($IP['DupesCount'] > 10 ? 'hidden' : '') ?>" data-name="<?= $Index ?>">
                        <td>&nbsp;&#187;&nbsp;<?= format_username($Dupe['UserID'], $Dupe['Username'], false, '0000-00-00 00:00:00', $Dupe['Enabled']) ?></td>
                        <td><?= time_diff($Dupe['StartTime']) ?></td>
                        <td><?= time_diff($Dupe['EndTime']) ?></td>
                        <td><?= time_diff(strtotime($Dupe['StartTime']), strtotime($Dupe['EndTime'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </table>
        <div class="linkbox">
            <?=$Pages?>
        </div>
    </div>

<?php
show_footer();