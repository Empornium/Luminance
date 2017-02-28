<?php
//TODO: restrict to viewing bellow class, username in h2
if (isset($_GET['userid']) && check_perms('users_view_ips') && check_perms('users_logout')) {
        if (!is_number($_GET['userid'])) { error(404); }
        $UserID = $_GET['userid'];
} else {
        $UserID = $LoggedUser['ID'];
}

if (isset($_POST['all'])) {
        authorize();

        $DB->query("DELETE FROM sessions WHERE UserID='$UserID' AND ID<>'$SessionID'");
        $Cache->delete_value('users_sessions_'.$UserID);
}

if (isset($_POST['session'])) {
        authorize();

        $DB->query("DELETE FROM sessions WHERE UserID='$UserID' AND ID='".db_string($_POST['session'])."'");
        $Cache->delete_value('users_sessions_'.$UserID);
}

$UserSessions = $Cache->get_value('users_sessions_'.$UserID);
if (!is_array($UserSessions)) {
        $UserSessions = $master->db->raw_query("
              SELECT
                s.ID,
                s.ID,
                s.Updated,
                ca.Browser,
                ca.Platform,
                cs.Width,
                cs.Height,
                cs.ColorDepth,
                ips.Address
                FROM sessions AS s
           LEFT JOIN clients AS c ON s.ClientID=c.ID
           LEFT JOIN client_user_agents AS ca ON c.ClientUserAgentID=ca.ID
           LEFT JOIN client_screens AS cs ON c.ClientScreenID=cs.ID
           LEFT JOIN ips ON s.IPID=ips.ID
                WHERE UserID=:userid
                ORDER BY Updated DESC", [':userid' => $UserID])->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        $Cache->cache_value('users_sessions_'.$UserID, $UserSessions, 0);
}

$PermissionsInfo = get_permissions_for_user($UserID);

list($UserID, $Username) = array_values(user_info($UserID));
show_header($Username.' &gt; Sessions');
?>
<div class="thin">
<h2><?=format_username($UserID,$Username)?> &gt; Sessions</h2>
        <div class="box pad">
                <p>Note: Clearing cookies can result in ghost sessions which are automatically removed after 30 days.</p>
        </div>
        <div class="box pad">
                <table cellpadding="5" cellspacing="1" border="0" class="border" width="100%">
                        <tr class="colhead">
                                <td><strong>IP</strong></td>
                                <td><strong>Browser</strong></td>
                                <td><strong>Platform</strong></td>
                                <td><strong>Screen Resolution</strong></td>
                                <td><strong>Last Activity</strong></td>
                                <td>
                                        <form action="" method="post">
                                                <input type="hidden" name="action" value="sessions" />
                                                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                                                <input type="hidden" name="all" value="1" />
                                                <input type="submit" value="Logout All" />
                                        </form>
                                </td>
                        </tr>
<?php
        $Row = 'a';
        foreach ($UserSessions as $Session) {
                $Row = ($Row == 'a') ? 'b' : 'a';
                if(empty($Session['Address'])) {
                    $Session['Address'] = '0.0.0.0';
                } else {
                    $Session['Address'] = inet_ntop($Session['Address']);
                }
?>
                        <tr class="row<?=$Row?>">
                                <td><?=$PermissionsInfo['site_disable_ip_history'] ? "127.0.0.1" : $Session['Address'] ?></td>
                                <td><?=$Session['Browser']?></td>
                                <td><?=$Session['Platform']?></td>
                                <td><?=$Session['Width']?>x<?=$Session['Height']?>, <?=$Session['ColorDepth']?> bit</td>
                                <td><?=time_diff($Session['Updated'])?></td>
                                <td>
                                        <form action="" method="post">
                                                <input type="hidden" name="action" value="sessions" />
                                                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                                                <input type="hidden" name="session" value="<?=$Session['ID']?>" />
                                                <input type="submit" value="<?=(($master->request->session->ID === $Session['ID'])?'Current" disabled="disabled':'Logout')?>" />
                                        </form>
                                </td>
                        </tr>
<?php  } ?>
                </table>
        </div>
</div>
<?php
show_footer();
