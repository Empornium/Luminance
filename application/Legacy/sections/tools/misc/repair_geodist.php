<?php
if (!check_perms('admin_update_geoip')) error(403);

set_time_limit(0);

$bbCode = new \Luminance\Legacy\Text;

$num_users = isset($_REQUEST['numusers'])?(int) $_REQUEST['numusers']:10;

if ($num_users<=0)$num_users=1;

$done='';

if ($_REQUEST['submit']=='process') {
    $done = 'done';
    $Users = $master->db->rawQuery(
        "SELECT u.ID, INET6_NTOA(i.StartAddress) AS IP, u.Username
           FROM users AS u
      LEFT JOIN users_main AS um ON u.ID = um.ID
      LEFT JOIN ips AS i ON u.IPID = i.ID
          WHERE Enabled = '1'
            AND ipcc = ''
            AND IP != '0.0.0.0' AND IP != ''
       ORDER BY ID DESC
          LIMIT {$num_users}"
    )->fetchAll(\PDO::FETCH_NUM);

    foreach ($Users as $User) {
        list($userID, $IP, $Username) = $User;

        // auto set if we have an ip to work with and data is missing
        if ($IP) {
            $ipcc = geoip($IP);
            $master->db->rawQuery(
                "UPDATE users_main
                    SET ipcc = ?
                  WHERE ID = ?",
                [$ipcc, $userID]
            );
            $Results[] = "| " . str_pad($userID, 7)."| ". str_pad($Username, 14)."| ".str_pad($ipcc,2)." |"; //  ($IP)
        }
    }

    $ret .= "Got " .count($Users). " users to get country codes for\n";
    $ret .= "Got " .count($Results). " results:\n";
    $ret .= "[br][spoiler=show results]";
    $ret .= "[code]";
    $ret .= "+--------+---------------+----+\n";
    $ret .= "|   ID   |    username   | cc |\n";
    $ret .= "+--------+---------------+----+\n";
    $ret .= implode("\n", $Results)."\n";
    $ret .= "+--------+---------------+----+\n";
    $ret .="[/code][/spoiler]\n";
} elseif ($_REQUEST['submit2']=='fill users_geo_distribution') {

    $done= 'filled users_geo_distribution';

    $master->db->rawQuery("TRUNCATE TABLE users_geodistribution");
    $master->db->rawQuery(
        "INSERT INTO users_geodistribution (Code, Users)
            SELECT ipcc, COUNT(ID) AS NumUsers
              FROM users_main
             WHERE Enabled = '1' AND ipcc != ''
          GROUP BY ipcc
          ORDER BY NumUsers DESC"
    );
    $numinserted = $master->db->foundRows();

    $ret = "[b]inserted {$numinserted} records[/b]";
    $master->cache->deleteValue('geodistribution');
}

show_header('Repair Geo-Distribution', 'bbcode');

?>
<div class="thin">
    <h2>Repair Geo-Distribution</h2>
        <table style="width:100%">
            <tr>
                <td class="center">
                    <p>This first tool goes through the userbase calculating the geoip for users who do not have it set already.<br/>Normally this is updated when a users logs in with a new ip,
                        this tool is useful after updating the site with the old sites membership data. It runs from newest members to oldest. And it takes a while...</p>
                </td>
            </tr>
            <tr>
                <td class="center">
                    <form action="" method="post">
                        <input type="hidden" name="action" value="repair_geoip" />
                        <label for="numusers" >num:</label>
                        <input size="6" type="text" id="numusers" name="numusers" value="<?=$num_users?>" />
                        <input type="submit" name="submit" value="process"/>
                    </form><br/>
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top" class="center">
                    <?php  if ($done) echo $bbCode->full_format( "[size=2][b][color=red]status: $done [/color][/b][/size][br]$ret ");?>
                </td>
            </tr>
            <tr>
                <td class="center">
                    <p>The second tool manually fills the geoip distribution table.<br/>Normally this is updated once a day by the scheduler.
                        Useful if you dont want to wait for the scheduler. (runs very fast)</p>
                </td>
            </tr>
            <tr>
                <td class="center">
                    <form action="" method="post">
                        <input type="hidden" name="action" value="repair_geoip" />
                        <input type="submit" name="submit2" value="fill users_geo_distribution"/>
                    </form><br/>
                </td>
            </tr>
        </table>

</div>

<?php
show_footer();
