<?php
if (!check_perms('admin_manage_ipbans')) { error(403); }

if (isset($_POST['submit'])) {
    authorize();

    if ($_POST['submit'] == 'Delete') { //Delete
        if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
        $DB->query('DELETE FROM ip_bans WHERE ID='.$_POST['id']);
        //$Bans = $Cache->delete_value('ip_bans');
        $Cache->delete_value('ip_bans');
    } else { //Edit & Create, Shared Validation
        $Val->SetFields('start', '1','regex','You must inculde starting IP address.',array('regex'=>'/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i'));
        $Val->SetFields('end', '1','regex','You must inculde ending IP address.',array('regex'=>'/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i'));
        $Val->SetFields('notes', '1','string','You must inculde a note regarding the reason for the ban.');
        $Err=$Val->ValidateForm($_POST); // Validate the form
        if ($Err) { error($Err); }

        $Notes = db_string($_POST['notes']);
        $Start = ip2unsigned($_POST['start']); //Sanitized by Validation regex
        $End = ip2unsigned($_POST['end']); //See above
        $EndHours = db_string($_POST['endtime']);

        $time = '0000-00-00 00:00:00';
        if ($EndHours > 1) {
            $time = time_plus( 3600*$EndHours );
        }

        if ($_POST['submit'] == 'Edit') { //Edit
            if (empty($_POST['id']) || !is_number($_POST['id'])) {
                error(404);
            }
            $DB->query("SELECT Endtime FROM ip_bans WHERE ID='$_POST[id]'");
            list($EndTime) = $DB->next_record();
            if ($EndHours != 1 && $time != $EndTime) {
                $SQL_ENDTIME = "EndTime='$time',";
            } else  $SQL_ENDTIME = '';

            $DB->query("UPDATE ip_bans SET
                FromIP=$Start,
                ToIP='$End',
                $SQL_ENDTIME
                Reason='$Notes'
                WHERE ID='$_POST[id]'");
            $Bans = $Cache->get_value('ip_bans');
            $Cache->begin_transaction();
            $Cache->update_row($_POST['id'], array($_POST['id'], $Start, $End));
            $Cache->commit_transaction();
        } else { //Create
            $Username = trim($_POST['user']);
            if ($Username) {
                $UserID = get_userid($Username);
                if (!$UserID) error("Could not find User '$Username'");
            } else
                $UserID=0;

            $DB->query("SELECT * FROM ip_bans WHERE FromIP = '$Start' AND ToIP = '$End'");
            if ($DB->record_count() > 0) {
                $start = preg_replace('/[^0-9\.]/', '', $_POST['start']);
                $end = preg_replace('/[^0-9\.]/', '', $_POST['end']);
                $master->peon->flasher->warning('Duplicate Entry ('.$start.' - '.$end.')');
            }
            else {
                $DB->query("INSERT INTO ip_bans
                    (FromIP, ToIP, UserID, StaffID, EndTime, Reason) VALUES
                    ('$Start','$End', '$UserID', '$LoggedUser[ID]', '$time', '$Notes')");
                $ID = $DB->inserted_id();
                $Bans = $Cache->get_value('ip_bans');
                $Bans[$ID] = array($ID, $Start, $End);
                $Cache->cache_value('ip_bans', $Bans, 0);
            }
        }
    }
    header('Location: tools.php?action=ip_ban');
    die();
}

define('BANS_PER_PAGE', '50');
include(SERVER_ROOT . '/common/functions.php');

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('FromIP', 'Username', 'Staffname', 'EndTime', 'Notes'  ))) {
    $_GET['order_by'] = 'FromIP';
    $OrderBy = 'FromIP';
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

$sql = "SELECT SQL_CALC_FOUND_ROWS
                i.ID, i.FromIP, i.ToIP, i.UserID, i.StaffID, i.EndTime, i.Reason as Notes, um.Username as Username, um2.Username as Staffname
          FROM ip_bans AS i
     LEFT JOIN users_main as um ON i.UserID=um.ID
     LEFT JOIN users_main as um2 ON i.StaffID=um2.ID";

if (!empty($_REQUEST['notes'])) {
    $sql .= " WHERE Reason LIKE '%".db_string($_REQUEST['notes'])."%' ";
}

if (!empty($_REQUEST['ip']) && preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $_REQUEST['ip'])) {
    if (!empty($_REQUEST['notes'])) {
        $sql .= " AND '".ip2unsigned($_REQUEST['ip'])."' BETWEEN FromIP AND ToIP ";
    } else {
        $sql .= " WHERE '".ip2unsigned($_REQUEST['ip'])."' BETWEEN FromIP AND ToIP ";
    }
}

$sql .= " ORDER BY  $OrderBy $OrderWay";
$sql .= " LIMIT $Limit";

$DB->query($sql);
$Bans = $DB->to_array();

$DB->query('SELECT FOUND_ROWS()');
list($Results) = $DB->next_record();

$PageLinks=get_pages($Page,$Results,BANS_PER_PAGE,11);

show_header('IP Bans','bbcode');

$userid = $_GET['userid'];
if ($userid) {
    $UserInfo = user_info($userid);
    $username = $UserInfo['Username'];
}
$startip = display_str($_GET['uip']);
$endip = display_str($_GET['uip']);
$notes = display_str($_GET['unotes']);
$endtime = display_str($_GET['uend']);
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
                    <input type="text" size="12" name="end"  value="<?=$endip?>"/>
                </td>
                <td class="center" style="width:100px">
                    <input type="text" class="medium" name="user"  value="<?=$username?>"/>
                </td>
                <td class="center">
                    <?=$LoggedUser['Username']?>
                </td>
                <td class="center">
                    <select name="endtime">
                        <option value="24" <?php if($endtime==24)echo'selected="selected" '?>>24 hours</option>
                        <option value="48" <?php if($endtime==48)echo'selected="selected" '?>>48 hours</option>
                        <option value="168" <?php if($endtime==168)echo'selected="selected" '?>>1 week</option>
                        <option value="336" <?php if($endtime==336)echo'selected="selected" '?>>2 weeks</option>
                        <option value="672" <?php if($endtime==672)echo'selected="selected" '?>>4 weeks</option>
                        <option value="2016" <?php if($endtime==2016)echo'selected="selected" '?>>12 weeks</option>
                        <option value="0" <?php if(!$endtime)echo'selected="selected" '?>>Never</option>
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
            <td><a href="<?=header_link('FromIP') ?>">Range</a></td>
            <td class="center" style="width:100px"><a href="<?=header_link('Username') ?>">User</a></td>
            <td class="center"><a href="<?=header_link('Staffname') ?>">Staff</a></td>
            <td class="center"><a href="<?=header_link('EndTime') ?>">Endtime</a></td>
            <td style="width:40%"><a href="<?=header_link('Notes') ?>">Notes</a></td>
            <td>Submit</td>
        </tr>
<?php
    $Row = 'a';
    foreach ($Bans as $Ban) {
        list($ID, $Start, $End, $UserID, $StaffID, $EndTime, $Reason) = $Ban;
        $Row = ($Row === 'a' ? 'b' : 'a');
        $Start=long2ip($Start);
        $End=long2ip($End);
        $UserInfo = user_info($UserID);
        $StaffInfo = user_info($StaffID);
?>
        <tr class="row<?=$Row?>">
            <form action="" method="post">
                <input type="hidden" name="id" value="<?=$ID?>" />
                <input type="hidden" name="action" value="ip_ban" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <td class="nobr">
                    <input type="text" size="12" name="start" value="<?=$Start?>" />
                    <input type="text" size="12" name="end" value="<?=$End?>" />
                </td>
                <td class="nobr center">
 <?=  $UserID ? format_username($UserID, $UserInfo['Username'], $UserInfo['Donor'], $UserInfo['Warned'], $UserInfo['Enabled'], $UserInfo['PermissionID'], false, false, $UserInfo['GroupPermissionID'], true, true) : '-';?>

                </td>
                <td class="nobr center">
  <?= $StaffID ? format_username($StaffID, $StaffInfo['Username'], $StaffInfo['Donor'], $StaffInfo['Warned'], $StaffInfo['Enabled'], $StaffInfo['PermissionID'], false, false, $StaffInfo['GroupPermissionID'], true, true): '-';?>

                </td>
                <td class="center">
                    <select name="endtime">
 <?php  if ($EndTime !='0000-00-00 00:00:00') { ?>
                        <option value="1" selected="selected"><?=time_diff($EndTime, 2, false,false,0)?></option>
 <?php  } ?>
                        <option value="24">24 hours</option>
                        <option value="48">48 hours</option>
                        <option value="168">1 week</option>
                        <option value="336">2 weeks</option>
                        <option value="672">4 weeks</option>
                        <option value="2016">12 weeks</option>
                        <option value="0" <?=(!$EndTime || $EndTime=='0000-00-00 00:00:00')?'selected="seleced"':''?>>Never</option>
                    </select>
                </td>
                <td>
                    <textarea name="notes" id="notes<?=$ID?>" class="long" rows="1" onkeyup="resize('notes<?=$ID?>')" ><?=$Reason?></textarea>
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
