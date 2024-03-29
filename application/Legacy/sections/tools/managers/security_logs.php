<?php

// TODO: find a better permission?
if (!check_perms('admin_login_watch')) {
    error(403);
}

$SqlSelects = [];
$SqlConditions = [];
$SqlJoins = [];
$params = [];

if (isset($_REQUEST['logs_per_page']) && in_array((int) $_REQUEST['logs_per_page'], [50, 100, 250, 500])) {
    $LogsPerPage = (int) $_REQUEST['logs_per_page'];
} else {
    $LogsPerPage = 50;
}

if (isset($_REQUEST['username']) && strlen($_REQUEST['username'])) {
    $User = $master->repos->users->getByUsername($_REQUEST['username']);

    if (!$User) {
        error('No user found with that username.');
    }

    $SqlConditions[] = "sl.UserID = ?";
    $params[] = $User->ID;
}

if (isset($_REQUEST['ip']) && strlen($_REQUEST['ip'])) {
    if (!validate_ip($_REQUEST['ip'])) {
        error('The provided IP doesn\'t seem to be a valid IPv4 or IPv6 address.');
    }

    $IP = $master->repos->ips->getOrNew($_REQUEST['ip']);

    if (!$IP instanceof \Luminance\Entities\IP) {
        error('Something went wrong with the provided IP.');
    }

    $SqlConditions[] = "sl.IPID = ?";
    $params[] = $IP->ID;
}

if (isset($_REQUEST['first_ip'])) {
    $SqlSelects[] = '(SELECT uhi.IPID FROM users_history_ips AS uhi WHERE uhi.UserID = sl.UserID ORDER BY StartTime ASC LIMIT 1) AS FirstIP';
}

if (isset($_REQUEST['last_ip'])) {
    $SqlSelects[] = '(SELECT uhi.IPID FROM users_history_ips AS uhi WHERE uhi.UserID = sl.UserID ORDER BY StartTime DESC LIMIT 1) AS LastIP';
}

if (isset($_REQUEST['events'])) {
    $Events = [
        'passkey_change'   => "sl.Event = 'Passkey reset'",
        'password_change'  => "sl.Event IN ('Password reset', 'Password changed')",
        'email_change'     => "sl.Event RLIKE ' (restored|added|removed|deleted)$' AND sl.Event NOT LIKE 'API%'",
        '2fa_change'       => "sl.Event IN ('2-Factor Authentication disabled', '2-Factor Authentication enabled')",
        'apiKey_change'    => "sl.Event LIKE 'API%'",
        'IRC_change'       => "sl.event LIKE 'IRC%'",
        'passkey_reset'    => "sl.Event = 'Passkey reset'",
        'password_reset'   => "sl.Event = 'Password reset'",
        'password_change2' => "sl.Event = 'Password changed'",
        'email_restore'    => "sl.Event LIKE '% restored' AND sl.Event NOT LIKE 'API%'",
        'email_addition'   => "sl.Event LIKE '% added' AND sl.Event NOT LIKE 'API%'",
        'email_removal'    => "sl.Event LIKE '% removed' AND sl.Event NOT LIKE 'API%'",
        'email_deletion'   => "sl.Event LIKE '% deleted' AND sl.Event NOT LIKE 'API%'",
        '2fa_enabling'     => "sl.Event = '2-Factor Authentication enabled'",
        '2fa_disabling'    => "sl.Event = '2-Factor Authentication disabled'",
        'apiKey_add'       => "sl.Event LIKE 'API%' AND sl.Event LIKE '%added'",
        'apiKey_remove'    => "sl.Event LIKE 'API%' AND sl.Event LIKE '%removed'",
        'apiKey_delete'    => "sl.Event LIKE 'API%' AND sl.Event LIKE '%deteted'",
        'apiKey_restore'   => "sl.Event LIKE 'API%' AND sl.Event LIKE '%restored'",
        'IRC_add'          => "sl.Event LIKE 'IRC Authentication added%'",
        'IRC_remove'       => "sl.Event LIKE 'IRC Authentication removed%'"
    ];

    if (array_key_exists($_REQUEST['events'], $Events)) {
        $SqlConditions[] = $Events[$_REQUEST['events']];
    }
}

if (!empty($_REQUEST['start_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/i', $_REQUEST['start_date'])) {
    error('Something went wrong with the provided start date.');
}

if (!empty($_REQUEST['end_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/i', $_REQUEST['end_date'])) {
    error('Something went wrong with the provided end date.');
}

if (in_array(($_REQUEST['date_option'] ?? 'on'), ['on', 'before', 'after', 'between']) && !empty($_REQUEST['start_date'])) {
    list($placeholders, $dateParams) = date_compare('Date', $_REQUEST['date_option'], $_REQUEST['start_date'], $_REQUEST['end_date']);
    $SqlConditions = array_merge(
            $SqlConditions,
            $placeholders
    );
    $params = array_merge($params, $dateParams);
}

if (!empty($SqlConditions)) {
    $Where = 'WHERE '.implode(' AND ', $SqlConditions);
} else {
    $Where  = null;
}

if (!empty($SqlSelects)) {
    $Select = ', '.implode(', ', $SqlSelects);
} else {
    $Select = null;
}

// Note: this was taken from advancedsearch.php
function date_compare($Field, $Operand, $Date1, $Date2 = '')
{
    $Return = [];
    $params = [];

    switch ($Operand) {
        case 'on':
            $Return[] = "{$Field} >= ?";
            $Return[] = "{$Field} <= ?";
            $params[] = "{$Date1} 00:00:00";
            $params[] = "{$Date1} 23:59:59";
            break;
        case 'before':
            $Return[] = "{$Field} < ?";
            $params[] = "{$Date1} 00:00:00";
            break;
        case 'after':
            $Return[] = "{$Field} > ?";
            $params[] = "{$Date1} 23:59:59";
            break;
        case 'between':
            $Return[] = "{$Field} >= ?";
            $Return[] = "{$Field} <= ?";
            $params[] = "{$Date1} 00:00:00";
            $params[] = "{$Date2} 00:00:00";
            break;
    }

    return [$Return, $params];
}

list($Page, $Limit) = page_limit($LogsPerPage);

$SecurityLogs = $master->db->rawQuery("SELECT SQL_CALC_FOUND_ROWS sl.* {$Select} FROM security_logs AS sl {$Where} ORDER BY Date DESC LIMIT {$Limit}", $params)->fetchAll(\PDO::FETCH_ASSOC);

$NumResults = $master->db->rawQuery("SELECT FOUND_ROWS()")->fetchColumn();
$Pages      = get_pages($Page, $NumResults, $LogsPerPage);

$options = [
    'useSpan'   => true,
    'noTitle'   => true,
];

show_header('Security logs');
?>
<div class="thin">
    <h2>Security logs (beta)</h2>

    <div class="head">Advanced search</div>
    <div class="box">
        <form action="/tools.php?action=security_logs" method="GET">
            <input type="hidden" name="action" value="security_logs">
            <table>
                <tr>
                    <td class="label nobr">Logs per page:</td>
                    <td>
                        <select name="logs_per_page" id="logs_per_page">
                            <option value="50" <?php selected('logs_per_page', '50') ?>>50</option>
                            <option value="100" <?php selected('logs_per_page', '100') ?>>100</option>
                            <option value="250" <?php selected('logs_per_page', '250') ?>>250</option>
                            <option value="500" <?php selected('logs_per_page', '500') ?>>500</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label nobr">Events:</td>
                    <td>
                        <select name="events" id="events">
                            <optgroup label="Default">
                                <option value="all">All events</option>
                            </optgroup>
                            <optgroup label="Global">
                                <option value="passkey_change" <?php selected('events', 'passkey_change') ?>>Passkey change</option>
                                <option value="password_change" <?php selected('events', 'password_change') ?>>Password change</option>
                                <option value="email_change" <?php selected('events', 'email_change') ?>>Email change</option>
                                <option value="2fa_change" <?php selected('events', '2fa_change') ?>>2FA change</option>
                                <option value="apiKey_change" <?php selected('events', 'apiKey_change') ?>>API Key change</option>
                                <option value="IRC_change" <?php selected('events', 'IRC_change') ?>>IRC Auth change</option>
                            </optgroup>
                            <optgroup label="Specific">
                                <option value="passkey_reset" <?php selected('events', 'passkey_reset') ?>>Passkey reset</option>
                                <option value="password_reset" <?php selected('events', 'password_reset') ?>>Password reset</option>
                                <option value="password_change2" <?php selected('events', 'password_change2') ?>>Password change</option>
                                <option value="email_restore" <?php selected('events', 'email_restore') ?>>Email restore</option>
                                <option value="email_addition" <?php selected('events', 'email_addition') ?>>Email addition</option>
                                <option value="email_removal" <?php selected('events', 'email_removal') ?>>Email removal</option>
                                <option value="email_deletion" <?php selected('events', 'email_deletion') ?>>Email deletion (staff)</option>
                                <option value="2fa_enabling" <?php selected('events', '2fa_enabling') ?>>2FA enabling</option>
                                <option value="2fa_disabling" <?php selected('events', '2fa_disabling') ?>>2FA disabling</option>
                                <option value="apiKey_add" <?php selected('events', 'apiKey_add') ?>>API Key Addition</option>
                                <option value="apiKey_remove" <?php selected('events', 'apiKey_add') ?>>API Key Removeal</option>
                                <option value="apiKey_delete" <?php selected('events', 'apiKey_add') ?>>API Key Deletion</option>
                                <option value="apiKey_restore" <?php selected('events', 'apiKey_add') ?>>API Key Restoration</option>
                                <option value="IRC_add" <?php selected('events', 'IRC_add') ?>>IRC Auth Added</option>
                                <option value="IRC_remove" <?php selected('events', 'IRC_remove') ?>>IRC Auth Removed</option>
                            </optgroup>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label nobr">Username:</td>
                    <td>
                        <input type="text" name="username" value="<?= display_str($_REQUEST['username'] ?? '') ?>">
                    </td>
                </tr>
                <tr>
                    <td class="label nobr">IP:</td>
                    <td>
                        <input type="text" name="ip" value="<?= display_str($_REQUEST['ip'] ?? '') ?>">
                    </td>
                </tr>
                <tr>
                    <td class="label nobr">Timeframe:</td>
                    <td>
                        <select onchange="date_input(this)" name="date_option">
                            <option value="on" <?php selected('date_option', 'on') ?>>On</option>
                            <option value="before" <?php selected('date_option', 'before') ?>>Before</option>
                            <option value="after" <?php selected('date_option', 'after') ?>>After</option>
                            <option value="between" <?php selected('date_option', 'between') ?>>Between</option>
                        </select>
                        <input type="text" name="start_date" value="<?= display_str($_REQUEST['start_date'] ?? '') ?>" placeholder="2018-01-01">
                        <input type="text" <?= ($_REQUEST['date_option'] ?? 'on') !== 'between' ? 'class="hidden"' : '' ?> id="end_date" name="end_date" value="<?= display_str($_REQUEST['end_date'] ?? '') ?>" placeholder="2019-01-01">
                    </td>
                </tr>
                <tr>
                    <td class="label nobr">Include:</td>
                    <td>
                        <label for="first_ip">
                            <input type="checkbox" id="first_ip" name="first_ip" <?php selected('first_ip', 'on', 'checked') ?>> User's first IP
                        </label>
                        <label for="last_ip">
                            <input type="checkbox" id="last_ip" name="last_ip" <?php selected('last_ip', 'on', 'checked') ?>> User's last IP
                        </label>
                    </td>
                </tr>
                <tr>
                    <td colspan="6" class="center">
                        <input type="submit" value="Search logs" />
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <script>
        function date_input(select) {
            if (select.value === 'between') {
                document.getElementById('end_date').className = '';
            } else {
                document.getElementById('end_date').className = 'hidden';
            }
        }
    </script>

    <div class="linkbox pager"><?= $Pages ?></div>
    <div class="head">Security logs</div>
    <div class="box">
        <table cellpadding="6" cellspacing="1" border="0" width="100%" class="border">
            <tbody>
            <tr class="colhead">
                <td><strong>User</strong></td>
                <td><strong>Event</strong></td>
                <td><strong>Date</strong></td>
                <td><strong>IP</strong></td>
                <?php if (isset($_REQUEST['first_ip'])): ?>
                    <td><strong>First IP</strong></td>
                <?php endif; ?>
                <?php if (isset($_REQUEST['last_ip'])): ?>
                    <td><strong>Last IP</strong></td>
                <?php endif; ?>
                <td><strong>By</strong></td>
            </tr>
            <?php foreach ($SecurityLogs as $SecurityLog): ?>
            <?php $SecurityLog['IPStr'] = $SecurityLog['IPID'] ? display_ip((string) $ip = $master->repos->ips->load($SecurityLog['IPID'])) : '-'; ?>
            <?php
                if (isset($SecurityLog['FirstIP'])):
                    $SecurityLog['FirstIPStr'] = $SecurityLog['FirstIP'] ? display_ip((string) $ip = $master->repos->ips->load($SecurityLog['FirstIP'])) : '-';
                endif;
            ?>
                <?php
                if (isset($SecurityLog['LastIP'])):
                    $SecurityLog['LastIPStr'] = $SecurityLog['LastIP'] ? display_ip((string) $ip = $master->repos->ips->load($SecurityLog['LastIP'])) : '-';
                endif;
                ?>
            <tr>
                <td><?php echo $master->render->username($SecurityLog['UserID'], $options); ?></td>
                <td><?php echo display_str($SecurityLog['Event']) ?></td>
                <td><?php echo time_diff($SecurityLog['Date']) ?></td>
                <td><?php echo $SecurityLog['IPStr'] ?></td>
                <?php if (isset($SecurityLog['FirstIP'])): ?>
                <td><?php echo $SecurityLog['FirstIPStr'] ?></td>
                <?php endif; ?>
                <?php if (isset($SecurityLog['LastIP'])): ?>
                    <td><?php echo $SecurityLog['LastIPStr'] ?></td>
                <?php endif; ?>
                <td><?php echo $master->render->username($SecurityLog['AuthorID'], $options); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="linkbox pager"><?= $Pages ?></div>
</div>
<?php
show_footer();
