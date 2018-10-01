<?php

global $master, $LoggedUser;

// TODO: find a better permission?
if (!check_perms('admin_login_watch')) {
    error(403);
}

// Remove a specific entry
if (isset($_POST['submit']) && $_POST['submit'] === 'Delete') {
    authorize();
    $logid = (int) $_POST['logid'];
    $master->db->raw_query("DELETE FROM disabled_hits WHERE ID = {$logid}")->fetchAll(\PDO::FETCH_ASSOC);
}

// Remove all entries for users that are no longer disabled
if (isset($_POST['submit']) && $_POST['submit'] === 'Cleanup') {
    authorize();
    $master->db->raw_query('DELETE d FROM disabled_hits AS d LEFT JOIN users_main AS u ON u.ID = d.UserID WHERE Enabled = "1"')->fetchAll(\PDO::FETCH_ASSOC);
}

$LogsPerPage = 25;
$SqlConditions = [];

if (isset($_REQUEST['username']) && strlen($_REQUEST['username'])) {
    $User = $master->repos->users->get_by_username($_REQUEST['username']);

    if (!$User) {
        error('No user found with that username.');
    }

    $SqlConditions[] = "UserID = {$User->ID}";
}

if (isset($_REQUEST['ip']) && strlen($_REQUEST['ip'])) {
    if (!filter_var($_REQUEST['ip'], FILTER_VALIDATE_IP)) {
        error('The provided IP doesn\'t seem to be a valid IPv4 or IPv6 address.');
    }

    $IP = $master->repos->ips->get_or_new($_REQUEST['ip']);

    if (!$IP instanceof \Luminance\Entities\IP) {
        error('Something went wrong with the provided IP.');
    }

    $SqlConditions[] = "IPID = {$IP->ID}";
}

if (!empty($SqlConditions)) {
    $Where = 'WHERE '.implode(' AND ', $SqlConditions);
} else {
    $Where  = null;
}

list($Page, $Limit) = page_limit($LogsPerPage);

$Logs = $master->db->raw_query("SELECT SQL_CALC_FOUND_ROWS * FROM disabled_hits {$Where} ORDER BY Time DESC LIMIT {$Limit}")->fetchAll(\PDO::FETCH_ASSOC);

$NumResults = $master->db->raw_query("SELECT FOUND_ROWS()")->fetchColumn();
$Pages      = get_pages($Page, $NumResults, $LogsPerPage);

$options = [
    'useSpan'   => true,
    'noTitle'   => true,
];

show_header('Disabled logs');
?>
    <div class="thin">
        <h2>Disabled logs</h2>

        <div class="head">Advanced search</div>
        <div class="box">
            <form action="/tools.php?action=disabled_hits" method="GET">
                <input type="hidden" name="action" value="disabled_hits">
                <table>
                    <tr>
                        <td class="label nobr">Username:</td>
                        <td>
                            <input type="text" name="username" value="<?= display_str($_REQUEST['username']) ?>">
                        </td>
                    </tr>
                    <tr>
                        <td class="label nobr">IP:</td>
                        <td>
                            <input type="text" name="ip" value="<?= display_str($_REQUEST['ip']) ?>">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6" class="center">
                            <input type="submit" value="Search logs" />
                        </td>
                    </tr>
                </table>
            </form>
            <div class="center">
                <form action="/tools.php?action=disabled_hits" method="post" style="display:inline-block">
                    <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                    <input type="hidden" name="action" value="disabled_hits" />
                    <input type="submit" name="submit" title="Remove users that are no longer disabled" value="Cleanup" />
                </form>
            </div>
        </div>

        <div class="linkbox"><?= $Pages ?></div>
        <div class="head">Disabled hits (<?= $NumResults ?>)</div>
        <div class="box">
            <table cellpadding="6" cellspacing="1" border="0" width="100%" class="border">
                <tbody>
                <tr class="colhead">
                    <td><strong>User</strong></td>
                    <td><strong>IP</strong></td>
                    <td><strong>Date</strong></td>
                    <td><strong>Action</strong></td>
                </tr>
                <?php foreach ($Logs as $Log): ?>
                    <tr>
                        <td><?php echo $master->render->username($Log['UserID'], $options); ?></td>
                        <td><?php echo display_ip((string) $ip = $master->repos->ips->load($Log['IPID']), $ip->geoip) ?></td>
                        <td><?php echo time_diff($Log['Time']) ?></td>
                        <td>
                            <form action="/tools.php?action=disabled_hits" method="post" style="display:inline-block">
                                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                                <input type="hidden" name="logid" value="<?= $Log['ID'] ?>" />
                                <input type="hidden" name="action" value="disabled_hits" />
                                <input type="submit" name="submit" title="Remove this log" value="Delete" />
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="linkbox"><?= $Pages ?></div>
    </div>
<?php
show_footer();