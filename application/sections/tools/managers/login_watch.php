<?php

include(SERVER_ROOT . '/common/functions.php');

if (!check_perms('admin_login_watch')) { error(403); }

if (isset($_POST['submit'], $_POST['ip']) && $_POST['submit'] == 'Unban') {
    authorize();
    $IP = $master->repos->ips->get('`Address` = INET6_ATON(?)', [trim($_POST['ip'])]);
    $flood = $master->repos->floods->get('`IPID` = ?', [$IP->ID]);
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

$_POST['searchips'] = isset($_POST['searchips']) ? trim($_POST['searchips']) : '';
$ExtraWhere = '';
$params = [];
if (isset($_POST['submit']) && $_POST['submit'] == 'Search' && !empty($_POST['searchips'])) {
    $ExtraWhere = "AND ip.ADDRESS = INET6_ATON(:searchips)";
    $params[':searchips'] = $_POST['searchips'];
}

list($Page,$Limit) = page_limit(50);
$RequestFloods = $master->db->raw_query("SELECT
                    flood.ID,
                    INET6_NTOA(ip.Address) AS IP,
                    flood.UserID,
                    flood.LastRequest,
                    flood.Requests,
                    flood.Type,
                    ip.BannedUntil,
                    flood.IPBans,
                    m.Username,
                    m.PermissionID,
                    m.Enabled,
                    i.Donor,
                    i.Warned
               FROM request_flood AS flood
               JOIN ips AS ip ON flood.IPID=ip.ID
          LEFT JOIN users_main AS m ON m.ID=flood.UserID
          LEFT JOIN users_info AS i ON i.UserID=flood.UserID
              WHERE flood.Requests>0
                    $ExtraWhere
           ORDER BY $OrderBy $OrderWay
              LIMIT $Limit",
                    $params)->fetchAll(\PDO::FETCH_ASSOC);

$NumResults = $master->db->found_rows();

$Pages=get_pages($Page,$NumResults,50,9);

show_header('Login Watch');

?>
<div class="thin">
<h2>Login Watch Management</h2>

    <div class="head">Search IP's</div>
    <form method="post" action="tools.php">
        <input type="hidden" name="action" value="login_watch" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <table>
            <tr class="box">
                <td class="label">Search for:</td>
                <td>
                        <input name="searchips" type="text" class="text" value="<?=display_str($_POST['searchips'])?>" />
                                <input type="submit" name="submit" value="Search" />
                </td>
                <td >
                </td>
            </tr>
        </table>
    </form>
    <br/>

<div class="linkbox"><?=$Pages?></div>

<div class="head">
<?php
    if ($ExtraWhere !== '') echo "$NumResults Search Results for '".display_str($_POST['searchips']);
    else echo "$NumResults entries in Login Watch";
?>
</div>
<table width="100%">
    <tr class="colhead">
        <td><a href="/<?=header_link('IP') ?>">IP</a></td>
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
    //list($ID, $IP, $UserID, $LastAttempt, $Attempts, $BannedUntil, $Bans, $Username, $PermissionID, $Enabled, $Donor, $Warned) = $Item;
    $Row = ($Row === 'a' ? 'b' : 'a');
?>
    <tr class="row<?=$Row?>">
            <td>
                <?=display_ip($Flood['IP'])?>
            </td>
            <td>
                <?php  if ($Flood['UserID'] != 0) { echo format_username($Flood['UserID'], $Flood['Username'], $Flood['Donor'], $Flood['Warned'], $Flood['Enabled'], $Flood['PermissionID']); } ?>
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
                    <input type="hidden" name="ip" value="<?=$Flood['IP']?>" />
                    <input type="hidden" name="action" value="login_watch" />
                    <input type="submit" name="submit" title="remove any bans (and reset attempts) from login watch" value="Unban" />
                </form>
<?php  if (check_perms('admin_manage_ipbans')) { ?>
                <form action="" method="post" style="display:inline-block">
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="id" value="<?=$Flood['ID']?>" />
                    <input type="hidden" name="action" value="ip_ban" />
                    <input type="hidden" name="start" value="<?=$Flood['IP']?>" />
                    <input type="hidden" name="end" value="<?=$Flood['IP']?>" />
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
    <div class="linkbox"><?=$Pages?></div>
</div>
<?php
show_footer();
