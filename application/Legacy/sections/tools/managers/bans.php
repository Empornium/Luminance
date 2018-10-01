<?php
if (!check_perms('admin_manage_ipbans')) { error(403); }

if (isset($_POST['submit'])) {
    authorize();

    if ($_POST['submit'] == 'Delete') { //Delete
        if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
        $IP = $master->repos->ips->load($_POST['id']);
        $master->repos->ips->unban($IP);
    } else { //Edit & Create, Shared Validation
        $Val->SetFields('start', '1','cidr','You must inculde an IP address or CIDR range.');
        $Val->SetFields('notes', '1','string','You must inculde a note regarding the reason for the ban.');
        $Err=$Val->ValidateForm($_POST); // Validate the form
        if ($Err) { error($Err); }

        $Address = $_POST['start'];
        $Notes = db_string($_POST['notes']);
        $EndHours = (float)$_POST['endtime'];
        if($EndHours === 0.0) $EndHours = null;

        if ($_POST['submit'] == 'Edit') { //Edit
            if (empty($_POST['id']) || !is_number($_POST['id'])) {
                error(404);
            }
            $IP = $master->repos->ips->load($_POST['id']);
            $master->repos->ips->unban($IP);
        }

        $now = new \DateTime();
        if($EndHours === -1.0) $EndHours = $now->diff($IP->BannedUntil)->format('%h');
        $master->repos->ips->ban($Address, $Notes, $EndHours);
    }
    header('Location: tools.php?action=ip_ban');
    die();
}

define('BANS_PER_PAGE', '50');
include(SERVER_ROOT . '/common/functions.php');

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('StartAddress', 'LastUserID', 'ActingUserID', 'BannedUntil', 'Reason'  ))) {
    $_GET['order_by'] = 'StartAddress';
    $OrderBy = 'StartAddress';
} else {
    $OrderBy = $_GET['order_by'];
}

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'desc') {
    $OrderWay = 'desc';
} else {
    $_GET['order_way'] = 'asc';
    $OrderWay = 'asc';
}

list($Page,$Limit) = page_limit(BANS_PER_PAGE);
$sql = 'Banned = true';
$parameters = [];

if (!empty($_REQUEST['notes'])) {
    $sql .= " AND Reason LIKE :reason";
    $parameters[':reason'] = "%{$_REQUEST['notes']}%";
}

if (!empty($_REQUEST['ip'])) {
    try {
        $range = \IPLib\Factory::rangeFromString($_REQUEST['ip']);
        if(!is_null($range)) {
            $ipSearch = new \Luminance\Entities\IP($range->getStartAddress());
            if (strpos($range, '/') !== false) {
                list(, $ipSearch->Netmask) = explode('/', (string)$range);
            }
            if ($range->getStartAddress() !== $range->getEndAddress) {
                $sql .= " AND StartAddress BETWEEN ? AND ?";
                $sql .= " AND (EndAddress >= ? OR EndAddress IS NULL)";
                $parameters[] = inet_pton($range->getStartAddress());
                $parameters[] = inet_pton($range->getEndAddress());
                $parameters[] = inet_pton($range->getEndAddress());
            } else {
                $sql .= "AND ? BETWEEN StartAddress AND EndAddress";
                $parameters[] = inet_pton($range->getStartAddress());
            }
        } else {
            $master->flasher->error("Invalid search IP!");
        }
    } catch(\Luminance\Errors\InternalError $e) {}
}

$sql .= " ORDER BY {$OrderBy} {$OrderWay}";
$sql .= " LIMIT {$Limit}";

list($Bans, $Results) = $master->repos->ips->find_count($sql, $parameters);

$PageLinks=get_pages($Page,$Results,BANS_PER_PAGE,11);

show_header('IP Bans','bbcode');

$userid = $_GET['userid'];
if ($userid) {
    $UserInfo = user_info($userid);
    $username = $UserInfo['Username'];
}
$startip = display_str($_GET['uip']);
$notes = display_str($_GET['unotes']);
$endtime['uend'] = display_str($_GET['uend']);
if (empty($endtime['uend'])) $endtime['uend'] = '2016';
?>

<div class="thin">
    <h2>IP Bans</h2>
    <div>
        <form action="" method="get">
            <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
                <tr>
                    <td class="label"><label for="ip">IP:</label></td>
                    <td>
                        <input type="hidden" name="action" value="ip_ban" />
                        <input type="text" id="ip" name="ip" size="20" value="<?=(!empty($_GET['ip']) ? display_str($_GET['ip']) : '')?>" />
                    </td>
                    <td class="label"><label for="notes">Notes:</label></td>
                    <td>
                        <input type="text" id="notes" name="notes" size="60" value="<?=(!empty($_GET['notes']) ? display_str($_GET['notes']) : '')?>" />
                    </td>
                    <td>
                        <input type="submit" value="Search" />
                    </td>
                </tr>
            </table>
        </form>
    </div>
    <br />

    <h2>Manage</h2>
    <div class="linkbox"><?=$PageLinks?></div>

    <div class="head">Create new IP ban</div>
    <table width="100%">
        <tr class="colhead">
            <td>Range</td>
            <td class="center" style="width:100px">User</td>
            <td class="center">Staff</td>
            <td class="center">Endtime</td>
            <td style="width:40%">Notes</td>
            <td>Submit</td>
        </tr>
        <tr class="rowa">
            <form action="" method="post">
                <input type="hidden" name="action" value="ip_ban" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <td class="nobr">
                    <input type="text" size="12" name="start" value="<?=$startip?>"/>
                </td>
                <td class="center" style="width:100px">
                    <input type="text" class="medium" name="user"  value="<?=$username?>"/>
                </td>
                <td class="center">
                    <?=$LoggedUser['Username']?>
                </td>
                <td class="center">
                    <select name="endtime">
                        <option value="24"   <?= selected('uend',   '24', 'selected', $endtime)?>>24 hours</option>
                        <option value="48"   <?= selected('uend',   '48', 'selected', $endtime)?>>48 hours</option>
                        <option value="72"   <?= selected('uend',   '72', 'selected', $endtime)?>>72 hours</option>
                        <option value="168"  <?= selected('uend',  '168', 'selected', $endtime)?>>1 week</option>
                        <option value="336"  <?= selected('uend',  '336', 'selected', $endtime)?>>2 weeks</option>
                        <option value="672"  <?= selected('uend',  '672', 'selected', $endtime)?>>4 weeks</option>
                        <option value="2016" <?= selected('uend', '2016', 'selected', $endtime)?>>12 weeks</option>
                        <option value="4032" <?= selected('uend', '4032', 'selected', $endtime)?>>24 weeks</option>
                        <option value="8064" <?= selected('uend', '8064', 'selected', $endtime)?>>48 weeks</option>
                        <option value="0"    <?= selected('uend',    '0', 'selected', $endtime)?>>Never</option>
                    </select>
                </td>
                <td>
                    <textarea name="notes" id="notes0" class="long" rows="1" onkeyup="resize('notes0')" ><?=$notes?></textarea>
                </td>
                <td>
                    <input type="submit" name="submit" value="Create" />
                </td>

            </form>
        </tr>
    </table>

    <br/>
    <div class="head"><?=  str_plural('IP ban', $Results) ?> </div>
    <table width="100%">
        <tr class="colhead">
            <td><a href="/<?=header_link('StartAddress') ?>">Range</a></td>
            <td class="center" style="width:100px"><a href="/<?=header_link('LastUserID') ?>">User</a></td>
            <td class="center"><a href="/<?=header_link('ActingUserID') ?>">Staff</a></td>
            <td class="center"><a href="/<?=header_link('BannedUntil') ?>">Endtime</a></td>
            <td style="width:40%"><a href="/<?=header_link('Reason') ?>">Notes</a></td>
            <td>Submit</td>
        </tr>
<?php
    $Row = 'a';
    foreach ($Bans as $Ban) {
        $Row = ($Row === 'a' ? 'b' : 'a');
        $Start=$Ban->get_range();
        $UserInfo = user_info($Ban->LastUserID);
        $StaffInfo = user_info($Ban->ActingUserID);
?>
        <tr class="row<?=$Row?>">
            <form action="" method="post">
                <input type="hidden" name="id" value="<?=$Ban->ID?>" />
                <input type="hidden" name="action" value="ip_ban" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <td class="nobr">
                    <input type="text" size="12" name="start" value="<?=$Start?>" />
                </td>
                <td class="nobr center">
 <?=  $Ban->LastUserID ? format_username($Ban->LastUserID, $UserInfo['Username'], $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID'], false, false, $UserInfo['GroupPermissionID'], true, true) : '-';?>

                </td>
                <td class="nobr center">
  <?= $Ban->ActingUserID ? format_username($Ban->ActingUserID, $StaffInfo['Username'], $StaffInfo['Donor'], true, $StaffInfo['Enabled'], $StaffInfo['PermissionID'], false, false, $StaffInfo['GroupPermissionID'], true, true): '-';?>

                </td>
                <td class="center">
                    <select name="endtime">
 <?php  if ($Ban->BannedUntil != null) { ?>
                        <option value="-1" selected="selected"><?=time_diff($Ban->BannedUntil, 2, false, false, 0)?></option>
 <?php  } ?>
                        <option value=  "24">24 hours</option>
                        <option value=  "48">48 hours</option>
                        <option value=  "72">72 hours</option>
                        <option value= "168">1 week</option>
                        <option value= "336">2 weeks</option>
                        <option value= "672">4 weeks</option>
                        <option value="2016">12 weeks</option>
                        <option value="4032">24 weeks</option>
                        <option value="8064">48 weeks</option>
                        <option value="0" <?=(!$Ban->BannedUntil || $Ban->BannedUntil=='0000-00-00 00:00:00')?'selected="seleced"':''?>>Never</option>
                    </select>
                </td>
                <td>
                    <textarea name="notes" id="notes<?=$Ban->ID?>" class="long" rows="1" onkeyup="resize('notes<?=$Ban->ID?>')" ><?=$Ban->Reason?></textarea>
                </td>
                <td class="nobr">
                    <input type="submit" name="submit" value="Edit" />
                    <input type="submit" name="submit" value="Delete" />
                </td>

            </form>
        </tr>
<?php
    }
?>
    </table>
    <div class="linkbox"><?=$PageLinks?></div>
</div>
<?php
show_footer();
