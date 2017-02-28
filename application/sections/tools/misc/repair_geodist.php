<?php
if (!check_perms('admin_update_geoip')) error(403);

set_time_limit(0);

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

$num_users = isset($_REQUEST['numusers'])?(int) $_REQUEST['numusers']:10;

if($num_users<=0)$num_users=1;

$done='';

if ($_REQUEST['submit']=='process') {
    $done= 'done';
    $DB->query("SELECT ID, IP, Username FROM users_main
                 WHERE Enabled='1'
                   AND ipcc=''
                   AND IP!='0.0.0.0' AND IP!=''
              ORDER BY ID DESC
                 LIMIT $num_users");
    $Users = $DB->to_array();

    foreach ($Users as $User) {
        list($UserID, $IP, $Username) = $User;

        // auto set if we have an ip to work with and data is missing
        if ($IP) {
            $ipcc = geoip($IP);
            $DB->query("UPDATE users_main SET ipcc='$ipcc' WHERE ID='$UserID'");
            $Results[] = "| " . str_pad($UserID, 7)."| ". str_pad($Username, 14)."| ".str_pad($ipcc,2)." |"; //  ($IP)
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

    $DB->query("TRUNCATE TABLE users_geodistribution");
    $DB->query("INSERT INTO users_geodistribution (Code, Users)
                       SELECT ipcc, COUNT(ID) AS NumUsers
                         FROM users_main
                        WHERE Enabled='1' AND ipcc != ''
                        GROUP BY ipcc
                     ORDER BY NumUsers DESC");

    $numinserted = $DB->affected_rows();
    $ret = "[b]inserted $numinserted records[/b]";
    $Cache->delete_value('geodistribution');
}

show_header('Repair Geo-Distribution','bbcode');

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
                        <input size="6" type="text" name="numusers" value="<?=$num_users?>" />
                        <input type="submit" name="submit" value="process"/>
                    </form><br/>
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top" class="center">
                    <?php  if($done) echo $Text->full_format( "[size=2][b][color=red]status: $done [/color][/b][/size][br]$ret " );?>
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
