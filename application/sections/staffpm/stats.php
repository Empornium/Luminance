<?php
if (!check_perms('admin_staffpm_stats')) { error(403); }

include(SERVER_ROOT.'/sections/staffpm/functions.php');
include(SERVER_ROOT.'/sections/staff/functions.php');
include(SERVER_ROOT.'/common/functions.php');

show_header('Staff Inbox');

$View   = isset($_REQUEST['view'])?$_REQUEST['view']:'staff';
$Action = isset($_REQUEST['action'])?$_REQUEST['action']:'stats';

list($NumMy, $NumUnanswered, $NumOpen) = get_num_staff_pms($LoggedUser['ID'], $LoggedUser['Class']);

?>
    <div class="thin">
        <div class="linkbox" >
<?php   if ($IsStaff) {
            echo view_link($View, 'my',         "<a href='staffpm.php?view=my'>My unanswered". ($NumMy > 0 ? "($NumMy)":"") ."</a>");
        }
            echo view_link($View, 'unanswered', "<a href='staffpm.php?view=unanswered'>All unanswered". ($NumUnanswered > 0 ? " ($NumUnanswered)":"") ."</a>");
            echo view_link($View, 'open',       "<a href='staffpm.php?view=open'>Open" . ($NumOpen > 0 ? " ($NumOpen)":"") ."</a>");
            echo view_link($View, 'resolved',   "<a href='staffpm.php?view=resolved'>Resolved</a>");
        if (check_perms('admin_stealth_resolve')) {
            echo view_link($View, 'stealthresolved', "<a href='staffpm.php?view=stealthresolved'>Stealth Resolved</a>");
        }
            echo view_link($Action, 'responses',     "<a href='staffpm.php?action=responses'>Common Answers</a>");
        if (check_perms('admin_staffpm_stats')) {
            echo view_link($Action, 'stats',         "<a href='staffpm.php?action=stats'>StaffPM Stats</a>");
        } ?>
        <br />
        <br />
<?php
          echo view_link($View, 'staff', "<a href='staffpm.php?action=stats&amp;view=staff'> View Staff Stats </a>");
          echo view_link($View, 'users', "<a href='staffpm.php?action=stats&amp;view=users'> View User Stats </a>");
?>
        <br />

        </div>
        <div class="head">Statistics</div>
        <div class="box pad">
        <table>
        <tr>
            <td style="width: 50%;">
<?php

$SupportStaff = get_support();
list($FrontLineSupport, $Staff, $Admins) = $SupportStaff;
$SupportStaff = array_merge($FrontLineSupport, $Staff, $Admins);
$SupportStaff = array_column($SupportStaff, 'ID');
$SupportStaff = implode(',', $SupportStaff);

if($View!='staff') {
    $IN    = "NOT IN";
    $COL   = "PMs";
    $EXTRA = "(SELECT Count(spc.ID)
                      FROM staff_pm_conversations AS spc
                     WHERE spc.UserID=um.ID
                       AND spc.Date > [TIMECLAUSE] )";
} else {
    $IN    = "IN";
    $COL   = "Resolved";
    $EXTRA = "(SELECT Count(spc.ID)
                      FROM staff_pm_conversations AS spc
                     WHERE spc.ResolverID=um.ID
                       AND spc.Status = 'Resolved'
                       AND spc.Date > [TIMECLAUSE] )";
}

$BaseSQL = "SELECT um.ID,
                   um.Username,
                   COUNT(spm.ID) AS Num ,
                   $EXTRA AS Extra
             FROM staff_pm_messages AS spm
             JOIN users_main AS um ON um.ID=spm.UserID
             WHERE um.ID $IN ($SupportStaff) AND spm.SentDate > [TIMECLAUSE]
          GROUP BY spm.UserID
          ORDER BY Num DESC";

$DB->query(str_replace('[TIMECLAUSE]', 'NOW() - INTERVAL 24 HOUR', $BaseSQL));
$Results = $DB->to_array();

?>
            <strong>Inbox actions in the last 24 hours</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php  foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $Extra) = $Result;
?>
                <tr>
                    <td><a href="reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php  } ?>
            </table>
            <br /><br />
<?php

$DB->query(str_replace('[TIMECLAUSE]', 'NOW() - INTERVAL 1 WEEK', $BaseSQL));
$Results = $DB->to_array();

?>
            <strong>Inbox actions in the last week</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php  foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $Extra) = $Result;
?>
                <tr>
                    <td><a href="reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php  } ?>
            </table>
        </td>
        <td>
<?php

$DB->query(str_replace('[TIMECLAUSE]', 'NOW() - INTERVAL 1 MONTH', $BaseSQL));
$Results = $DB->to_array();

?>
        <strong>Inbox actions in the last month</strong>
        <table class="noborder">
            <tr class="colhead">
                <td>Username</td>
                <td>Replies</td>
                <td><?=$COL?></td>
            </tr>
<?php  foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $Extra) = $Result;
?>
            <tr>
                <td><a href="reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                <td><?=$Num?></td>
                <td><?=$Extra?></td>
            </tr>
<?php  } ?>
        </table>
        <br /><br />
<?php

$DB->query(str_replace('[TIMECLAUSE]', "'0000-00-00 00:00:00'", $BaseSQL));
$Results = $DB->to_array();

?>
            <strong>Inbox actions total</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Replies</td>
                    <td><?=$COL?></td>
                </tr>
<?php  foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $Extra) = $Result;
?>
                <tr>
                    <td><a href="reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$Extra?></td>
                </tr>
<?php  } ?>
            </table>
        </td></tr>
        </table>
        </div>
         <br/>
    </div>

<?php
show_footer();
