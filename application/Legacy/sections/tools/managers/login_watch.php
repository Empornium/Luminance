<?php

include(SERVER_ROOT . '/common/functions.php');

if (!check_perms('admin_login_watch')) { error(403); }

if (isset($_POST['submit'], $_POST['id']) && $_POST['submit'] == 'Unban') {
    authorize();
    $flood = $master->repos->floods->load($_POST['id']);
    $IP = $master->repos->ips->load($flood->IPID);
    $master->repos->floods->delete($flood);
    $master->repos->ips->unban($IP);
}

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $OrderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $OrderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('IP', 'Username', 'LastRequest', 'Requests', 'Type', 'BannedUntil', 'IPBans'))) {
    $_GET['order_by'] = 'LastRequest';
    $OrderBy = 'LastRequest';
} else {
    $OrderBy = $_GET['order_by'];
}

$_GET['searchips'] = isset($_GET['searchips']) ? trim($_GET['searchips']) : '';
$ExtraWhere = '';
$params = [];
if (isset($_GET['submit']) && $_GET['submit'] == 'Search' && !empty($_GET['searchips'])) {
    $ExtraWhere .= "AND ip.StartADDRESS = INET6_ATON(:searchips) AND ip.EndAddress IS NULL";
    $params[':searchips'] = $_GET['searchips'];
}

if (isset($_GET['type']) && in_array($_GET['type'], ['login', 'recover'])) {
    $ExtraWhere .= " AND Type = :type";
    $params[':type'] = $_GET['type'];
}

if (isset($_GET['users_only'])) {
    $ExtraWhere .= " AND flood.UserID IS NOT NULL";
}

$FirstIpSelect = '';
if (isset($_GET['first_ip'])) {
    $FirstIpSelect = ', (SELECT uhi.IP FROM users_history_ips AS uhi WHERE uhi.UserID = flood.UserID ORDER BY StartTime ASC LIMIT 1) AS FirstIP';
}

$LastIpSelect = '';
if (isset($_GET['last_ip'])) {
    $LastIpSelect = ', (SELECT uhi.IP FROM users_history_ips AS uhi WHERE uhi.UserID = flood.UserID ORDER BY StartTime DESC LIMIT 1) AS LastIP';
}

list($Page,$Limit) = page_limit(50);
$RequestFloods = $master->db->raw_query("SELECT SQL_CALC_FOUND_ROWS
                    flood.ID,
                    INET6_NTOA(ip.StartAddress) AS IP,
                    flood.UserID,
                    flood.LastRequest,
                    flood.Requests,
                    flood.Type,
                    ip.BannedUntil,
                    flood.IPBans,
                    m.Username,
                    m.PermissionID,
                    m.Enabled,
                    i.Donor
                    $FirstIpSelect
                    $LastIpSelect
               FROM request_flood AS flood
               JOIN ips AS ip ON flood.IPID=ip.ID
          LEFT JOIN users_main AS m ON m.ID=flood.UserID
          LEFT JOIN users_info AS i ON i.UserID=flood.UserID
              WHERE flood.Requests>0
                    $ExtraWhere
           ORDER BY $OrderBy $OrderWay
              LIMIT $Limit",
                    $params)->fetchAll(\PDO::FETCH_ASSOC);

$NumResults = $master->db->raw_query("SELECT FOUND_ROWS()")->fetchColumn();

$Pages=get_pages($Page,$NumResults,50,9);

show_header('Login Watch');

?>
<div class="thin">
<h2>Login Watch Management</h2>

    <div class="head">Filtering</div>
    <form>
        <input type="hidden" name="action" value="login_watch" />
        <table>
            <tr class="box">
                <td class="label">Search for IP:</td>
                <td>
                        <input name="searchips" type="text" class="text" value="<?=display_str($_GET['searchips'])?>" />
                </td>
            </tr>
            <tr class="box">
                <td class="label">Type of requests:</td>
                <td>
                    <select name="type">
                        <option value="all" <?php selected('type', 'all') ?>>All</option>
                        <option value="login" <?php selected('type', 'login') ?>>Login</option>
                        <option value="recover" <?php selected('type', 'recover') ?>>Recover</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td></td>
                <td><label><input <?php selected('users_only', 'on', 'checked') ?> type="checkbox" name="users_only"> Existing users only</label></td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <label><input <?php selected('first_ip', 'on', 'checked') ?> type="checkbox" name="first_ip"> First IP</label>
                    <label><input <?php selected('last_ip', 'on', 'checked') ?> type="checkbox" name="last_ip"> Last IP</label>
                </td>
            </tr>
            <tr>
                <td></td>
                <td><input type="submit" name="submit" value="Search"></td>
            </tr>
        </table>
    </form>
    <br/>

<div class="linkbox"><?=$Pages?></div>

<div class="head">
<?php
    if (!empty($_GET['searchips'])) echo "$NumResults Search Results for ".display_str($_GET['searchips']);
    else echo "$NumResults entries in Login Watch";
?>
</div>
    <div class="box">
<table class="border" width="100%" cellspacing="1" cellpadding="6" border="0">
    <tr class="colhead">
        <td><a href="/<?=header_link('IP') ?>">IP</a></td>
        <?php if (isset($_GET['first_ip'])): ?>
            <td><a href="/<?=header_link('FirstIP') ?>">First IP</a></td>
        <?php endif; ?>
        <?php if (isset($_GET['last_ip'])): ?>
            <td><a href="/<?=header_link('LastIP') ?>">Last IP</a></td>
        <?php endif; ?>
        <td><a href="/<?=header_link('Username') ?>">User</a></td>
        <td><a href="/<?=header_link('Requests') ?>">Requests</a></td>
        <td><a href="/<?=header_link('LastRequest') ?>">Last Request</a></td>
        <td><a href="/<?=header_link('Type') ?>">Type</a></td>
        <td><a href="/<?=header_link('IPBans') ?>">IP Bans</a></td>
        <td><a href="/<?=header_link('BannedUntil') ?>">Banned Until</a></td>
        <td style="width:160px">Submit</td>
    </tr>
<?php
$Row = 'b';
foreach ($RequestFloods as $Flood) {
    $Row = ($Row === 'a' ? 'b' : 'a');
?>
    <tr class="row<?=$Row?>">
            <td>
                <?=display_ip((string) $ip = $master->repos->ips->get_or_new($Flood['IP']), geoip((string) $ip))?>
            </td>
            <?php if (isset($_GET['first_ip'])): ?>
                <td><?= $Flood['FirstIP'] ? display_ip((string) $ip = $master->repos->ips->get_or_new($Flood['FirstIP']), geoip((string) $ip)) : '' ?></td>
            <?php endif; ?>
            <?php if (isset($_GET['last_ip'])): ?>
                <td><?= $Flood['LastIP'] ? display_ip((string) $ip = $master->repos->ips->get_or_new($Flood['LastIP']), geoip((string) $ip)) : '' ?></td>
            <?php endif; ?>
            <td>
                <?php  if ($Flood['UserID'] != 0) { echo format_username($Flood['UserID'], $Flood['Username'], $Flood['Donor'], true, $Flood['Enabled'], $Flood['PermissionID']); } ?>
            </td>
            <td>
                <?=$Flood['Requests']?>
            </td>
            <td>
                <?=time_diff($Flood['LastRequest'])?>
            </td>
            <td>
                <?=$Flood['Type']?>
            </td>
            <td>
                <?=$Flood['IPBans']?>
            </td>
            <td>
                <?=time_diff($Flood['BannedUntil'])?>
            </td>
            <td>
                <form action="" method="post" style="display:inline-block">
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="id" value="<?=$Flood['ID']?>" />
                    <input type="hidden" name="action" value="login_watch" />
                    <input type="submit" name="submit" title="remove any bans (and reset attempts) from login watch" value="Unban" />
                </form>
<?php  if (check_perms('admin_manage_ipbans')) { ?>
                <form action="" method="post" style="display:inline-block">
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="id" value="<?=$Flood['ID']?>" />
                    <input type="hidden" name="action" value="ip_ban" />
                    <input type="hidden" name="start" value="<?=$Flood['IP']?>" />
                    <input type="hidden" name="notes" value="Banned per <?=$Flood['IPBans']?> bans on login watch." />
                    <input type="submit" name="submit" title="IP Ban this ip address (use carefully!)" value="IP Ban" />
                </form>
<?php  } ?>
            </td>
    </tr>
<?php
}
?>
</table>
    </div>
    <div class="linkbox"><?=$Pages?></div>
</div>
<?php
show_footer();
