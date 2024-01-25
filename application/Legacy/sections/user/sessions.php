<?php
//TODO: restrict to viewing bellow class, username in h2
if (isset($_GET['userid']) && check_perms('users_view_ips') && check_perms('users_logout')) {
        if (!is_integer_string($_GET['userid'])) { error(404); }
        $userID = $_GET['userid'];
} else {
        $userID = $activeUser['ID'];
}

if (isset($_POST['all'])) {
        authorize();

        $master->db->rawQuery(
            "DELETE
               FROM sessions
              WHERE UserID = ?
                AND ID <> ?",
            [$userID, $master->request->session->ID]
        );
        $master->cache->deleteValue('users_sessions_'.$userID);
}

if (isset($_POST['session'])) {
        authorize();

        $master->db->rawQuery(
            "DELETE
               FROM sessions
              WHERE UserID = ?
                AND ID = ?",
            [$userID, $_POST['session']]
        );
        $master->cache->deleteValue('users_sessions_'.$userID);
}

$UserSessions = $master->cache->getValue('users_sessions_'.$userID);
if (!is_array($UserSessions)) {
    $UserSessions = $master->db->rawQuery(
        "SELECT s.ID,
                s.ID,
                s.Updated,
                ca.Browser,
                ca.Version,
                ca.Platform,
                cs.Width,
                cs.Height,
                cs.ColorDepth,
                c.TimezoneOffset,
                c.TLSVersion,
                ca.String,
                ips.StartAddress
           FROM sessions AS s
      LEFT JOIN clients AS c ON s.ClientID = c.ID
      LEFT JOIN client_user_agents AS ca ON c.ClientUserAgentID = ca.ID
      LEFT JOIN client_screens AS cs ON c.ClientScreenID = cs.ID
      LEFT JOIN ips ON s.IPID=ips.ID
          WHERE s.UserID = ?
       ORDER BY s.Updated DESC",
        [$userID]
    )->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
    $master->cache->cacheValue('users_sessions_'.$userID, $UserSessions, 0);
}

$permissionsInfo = get_permissions_for_user($userID);

list($userID, $Username) = array_values(user_info($userID));
show_header($Username.' &gt; Sessions');
?>
<div class="thin">
<h2><?=format_username($userID)?> &gt; Sessions</h2>
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
                                <td><strong>Timezone</strong></td>
                                <td><strong>TLS Version</strong></td>
                                <td><strong>Last Activity</strong></td>
                                <?php if (check_perms('site_debug') || check_perms('users_view_keys')) { ?>
                                <td><strong>User Agent</strong></td>
                                <?php } ?>
                                <td>
                                        <form action="" method="post">
                                                <input type="hidden" name="action" value="sessions" />
                                                <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                                                <input type="hidden" name="all" value="1" />
                                                <input type="submit" value="Logout All" />
                                        </form>
                                </td>
                        </tr>
<?php
        $Row = 'a';
        foreach ($UserSessions as $Session) {
                $Row = ($Row == 'a') ? 'b' : 'a';
                if (empty($Session['StartAddress'])) {
                    $Session['StartAddress'] = '0.0.0.0';
                } else {
                    $Session['StartAddress'] = inet_ntop($Session['StartAddress']);
                }
?>
                        <tr class="row<?=$Row?>">
                                <td><?=array_key_exists('site_disable_ip_history', $permissionsInfo) ? "127.0.0.1" : $Session['StartAddress'] ?></td>
                                <td><?=$Session['Browser']?> <?=!empty($Session['Version']) ? "({$Session['Version']})" : '' ?></td>
                                <td><?=$Session['Platform']?></td>
                                <td><?=$Session['Width']?>x<?=$Session['Height']?>, <?=$Session['ColorDepth']?> bit</td>
                                <td><?=$Session['TimezoneOffset']?></td>
                                <td><?=$Session['TLSVersion']?></td>
                                <td><?=time_diff($Session['Updated'])?></td>
                                <?php if (check_perms('site_debug') || check_perms('users_view_keys')) { ?>
                                <td><?=$Session['String']?></td>
                                <?php } ?>
                                <td>
                                        <form action="" method="post">
                                                <input type="hidden" name="action" value="sessions" />
                                                <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
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
