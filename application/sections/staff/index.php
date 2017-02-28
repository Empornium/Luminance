<?php
enforce_login();

show_header('Staff','bbcode,inbox,jquery');

include(SERVER_ROOT.'/sections/staff/functions.php');
include(SERVER_ROOT.'/sections/staffpm/functions.php');
$SupportStaff = get_support();

list($FrontLineSupport, $Staff, $Admins) = $SupportStaff;

$Show = isset($_REQUEST['show'])?($_REQUEST['show']==1?1:0):0;
$Assign = isset($_REQUEST['assign'])?$_REQUEST['assign']:'';
if ($Assign !== '' && !in_array($Assign, array('mod','admin'))) $Assign = '';
$Subject = isset($_REQUEST['sub'])?$_REQUEST['sub']:'';
$Msg = isset($_REQUEST['msg'])?$_REQUEST['msg']:'';

?>
<div class="thin">
    <h2><?=SITE_NAME?> Staff</h2>
    <div class="head">Contact Staff</div>
    <div class="box pad" style="padding:10px;">
        <div id="below_box">
            <p>If you are looking for help with a general question, we appreciate it if you would only message through the staff inbox, where we can all help you.</p>
                  <p>You can do that by
                              <a href="#"  class="contact_link" onClick="jQuery('#compose').slideToggle('slow');">sending a message to the Staff Inbox</a>
                              <em>Please do not PM individual staff members for support!</em> </p>
            </div>
        <?php  print_compose_staff_pm(!$Show, $Assign, $Subject, $Msg);  ?>
        <br />
    </div>
<?php

if (check_perms('site_staff_page')) {

    if ( count($FrontLineSupport)>0) {
?>
    <div class="head">First-line Support</div>
    <div class="box pad" style="padding:10px;">
        <p><strong>These users are not official staff members</strong> - they're users who have volunteered their time to help people in need. Please treat them with respect and read <a href="articles.php?topic=ranks#fls">this</a> before contacting them. </p>
        <table class="staff" width="100%">
            <tr class="colhead">
                <td width="300px">Username</td>
                <td width="150px">Last seen</td>
                <td colspan="2"><strong>Support For</strong></td>
            </tr>
<?php
            $Row = 'a';
            foreach ($FrontLineSupport as $Support) {
                list($ID, $Class, $Username, $Title, $Paranoia, $LastAccess, $SupportFor) = $Support;
                $Row = ($Row == 'a') ? 'b' : 'a';
?>
            <tr class="row<?=$Row?>">
                <td class="nobr">
                    <?=format_username($ID, $Username, false, false, true, false, $Title, false)?>
                </td>
                <td class="nobr">
                    <?php  if (check_paranoia('lastseen', $Paranoia, $Class)) { echo time_diff($LastAccess,2,true,false,0); } else { echo 'Hidden by user'; }?>
                </td>
                <td class="nobr">
                    <?=$SupportFor?>
                </td>
                <td width="20%" class="nobr">
                    <?=get_user_languages($ID)?>
                </td>
            </tr>
<?php           } ?>
        </table>
    </div>
<?php
      }
?>

    <div class="head">Staff</div>
    <div class="box pad" style="padding:10px;">
<?php
    $CurClass = 0;
    $CloseTable = false;
    foreach ($Staff as $Support) {
        list($ID, $Class, $ClassName, $Username, $Title, $Paranoia, $LastAccess, $Remark) = $Support;
        if ($Class!=$CurClass) { // Start new class of staff members
            $Row = 'a';
            if ($CloseTable) {
                $CloseTable = false;
                echo "\t</table><br/>";
            }
            $CurClass = $Class;
            $CloseTable = true;
            echo '<h3>'.$ClassName.'s</h3>';
?>
        <table class="staff" width="100%">
            <tr class="colhead">
                <td width="300px">Username</td>
                <td width="150px">Last seen</td>
                <td colspan="2"><strong>Remark</strong></td>
            </tr>
<?php
        } // End new class header

        // Display staff members for this class
        $Row = ($Row == 'a') ? 'b' : 'a';
?>
            <tr class="row<?=$Row?>">
                <td class="nobr">
                    <?=format_username($ID, $Username, false, false, true, false, $Title, false)?>
                </td>
                <td class="nobr">
                    <?php  if (check_paranoia('lastseen', $Paranoia, $Class)) { echo time_diff($LastAccess,2,true,false,0); } else { echo 'Hidden by staff member'; }?>
                </td>
                <td class="nobr">
                    <?=$Remark?>
                </td>
                <td width="20%" class="nobr">
                    <?=get_user_languages($ID)?>
                </td>
            </tr>
<?php 	} ?>
        </table><br/>
<?php
    $CurClass = 0;
    $CloseTable = false;
    foreach ($Admins as $StaffMember) {
        list($ID, $Class, $ClassName, $Username, $Title, $Paranoia, $LastAccess, $Remark) = $StaffMember;
        if ($Class!=$CurClass) { // Start new class of staff members
            $Row = 'a';
            if ($CloseTable) {
                $CloseTable = false;
                echo "\t</table><br/>";
            }
            $CurClass = $Class;
            $CloseTable = true;
            echo '<h3>'.$ClassName.'s</h3>';
?>
        <table class="staff" width="100%">
            <tr class="colhead">
                <td width="300px">Username</td>
                <td width="150px">Last seen</td>
                <td colspan="2"><strong>Remark</strong></td>
            </tr>
<?php
        } // End new class header

        // Display staff members for this class
        $Row = ($Row == 'a') ? 'b' : 'a';
?>
            <tr class="row<?=$Row?>">
                <td class="nobr">
                    <?=format_username($ID, $Username, false, false, true, false, $Title, false)?>
                </td>
                <td class="nobr">
                    <?php  if (check_paranoia('lastseen', $Paranoia, $Class)) { echo time_diff($LastAccess,2,true,false,0); } else { echo 'Hidden by staff member'; }?>
                </td>
                <td class="nobr">
                    <?=$Remark?>
                </td>
                <td width="20%" class="nobr">
                    <?=get_user_languages($ID)?>
                </td>
            </tr>
<?php 	} ?>
        </table><br/>

    </div>

<?php
}
?>

</div>
<?php
show_footer();
