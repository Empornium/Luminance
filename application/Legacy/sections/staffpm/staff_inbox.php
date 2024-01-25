<?php
include_once(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

show_header('Staff Inbox');

$View = display_str($_GET['view']);
$user = $this->master->request->user;

list($NumMy, $NumUnanswered, $NumOpen) = get_num_staff_pms($user->ID, $user->class->Level);

$Show = isset($_REQUEST['show'])?($_REQUEST['show']==1?1:0):0;
$Assign = isset($_REQUEST['assign'])?$_REQUEST['assign']:'';
if ($Assign !== '' && !in_array($Assign, ['mod', 'smod', 'admin'])) $Assign = '';
$Subject = isset($_REQUEST['sub'])?$_REQUEST['sub']:'';
$Msg = isset($_REQUEST['msg'])?$_REQUEST['msg']:'';
?>
<div class="thin">
    <?php if (!$master->repos->restrictions->isRestricted($user->ID, Luminance\Entities\Restriction::STAFFPM)):
        if(!$user->class->DisplayStaff) { ?>
            <div class="head">Staff PMs</div>
            <div class="box pad">
              <div class="center">
                    <a href="#" onClick="jQuery('#compose').slideToggle('slow');">[Compose New]</a>
              </div>
            <?php  print_compose_staff_pm(!$Show, $Assign, $Subject, $Msg);  ?>
            </div>
        <?php } ?>
    <?php endif; ?>
<?php
// Setup for current view mode & search
$SortStr = "IF(AssignedToUser = ".$user->ID.",0,1) ASC, ";

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $orderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $orderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], ['Subject', 'UserID', 'Date', 'Level', 'ResolverID', 'Status'])) {
    $orderBy = 'Status';
} else {
    $orderBy = $_GET['order_by'];
}

$WhereCondition = "";
$params = [];
if (!empty($_GET['search']) && $_GET['searchtype'] == "message") {
    $WhereCondition .=     " JOIN staff_pm_messages AS m ON c.ID=m.ConvID";
} elseif (!empty($_GET['search']) && $_GET['searchtype'] == "user") {
    $WhereCondition .=     " JOIN users AS u ON u.ID=c.UserID";
}

$InternalView = $View;

$WhereCondition .= " WHERE ";
if (!empty($_GET['search'])) {
        if (isset($_GET['allfolders']) && $_GET['allfolders'] == '1') {
            $InternalView = 'allfolders';
        }
        $Search = $_GET['search'];
       if ($_GET['searchtype'] == "subject") {
            $Words = explode(' ', $Search);
            $subjectConditions = implode(' AND ', array_fill(0, count($Words), 'c.Subject LIKE ?'));
            $WhereCondition .= "{$subjectConditions} AND ";
            $params = array_merge($params, array_map(function($w) { return "%{$w}%"; }, $Words));
        } elseif ($_GET['searchtype'] == "user") {
            $WhereCondition .= "u.Username LIKE ? AND ";
            $params[] = $Search;
        } elseif ($_GET['searchtype'] == "message") {
            $Words = explode(' ', $Search);
            $messageConditions = implode(' AND ', array_fill(0, count($Words), 'm.Message LIKE ?'));
            $WhereCondition .= "{$messageConditions} AND ";
            $params = array_merge($params, array_map(function($w) { return "%{$w}%"; }, $Words));
        }
}

// Only let people who can stealth resolve view these messages.
if (($View == 'stealthresolved') && !(check_perms('admin_stealth_resolve'))) $View='error';

switch ($InternalView) {
    case 'open':
        $ViewString = "All open";
        $WhereCondition .= "(Level <= ? OR AssignedToUser = ?) AND Status IN ('Open', 'Unanswered', 'User Resolved') AND StealthResolved=False";
        $SortStr = '';
        break;
    case 'resolved':
        $ViewString = "Resolved";
        $WhereCondition .= "(Level <= ? OR AssignedToUser = ?) AND Status='Resolved' AND StealthResolved=False";
        $SortStr = '';
        break;
    case 'stealthresolved':
        $ViewString = "Stealth Resolved";
        $WhereCondition .= "(Level <= ? OR AssignedToUser = ?) AND StealthResolved=True";
        $SortStr = '';
        break;
    case 'my':
        $ViewString = "My unanswered";
        $WhereCondition .= "(Level = ? OR AssignedToUser = ?) AND Status IN ('Unanswered', 'User Resolved') AND StealthResolved=False";
        break;
    case 'unanswered':
        $ViewString = "All unanswered";
        $WhereCondition .= "(Level <= ? OR AssignedToUser = ?) AND Status IN ('Unanswered', 'User Resolved') AND StealthResolved=False";
        break;
    case 'allfolders':
    default:
        $ViewString = "All folders";
        if (!check_perms('admin_stealth_resolve')) {
            $WhereCondition .= "(Level <= ? OR AssignedToUser = ?) AND StealthResolved=False";
        } else {
            $WhereCondition .= "(Level <= ? OR AssignedToUser = ?)";
        }
        break;
}
$params = array_merge($params, [$user->class->Level, $user->ID]);
if (isset($_GET['ResolverID']) && is_integer_string($_GET['ResolverID'])) {
    $WhereCondition .= " AND (c.ResolverID = ?)";
    $params[] = $_GET['ResolverID'];
}

list($Page, $Limit) = page_limit(MESSAGES_PER_PAGE);
// Get messages
$StaffPMs = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            c.ID,
            c.Subject,
            c.UserID,
            c.Status,
            c.Level,
            c.AssignedToUser,
            c.Date,
            c.Unread,
            c.ResolverID,
            c.Urgent
       FROM staff_pm_conversations AS c
       {$WhereCondition}
   GROUP BY c.ID
   ORDER BY {$SortStr} {$orderBy} {$orderWay}, Date {$orderWay}
      LIMIT {$Limit}",
    $params
)->fetchAll();

$NumResults = $master->db->foundRows();

$CurURL = get_url();
if (empty($CurURL)) {
    $CurURL = "staffpm.php?";
} else {
    $CurURL = "staffpm.php?".$CurURL."&";
}
$Pages = get_pages($Page, $NumResults, MESSAGES_PER_PAGE, 9);

$Row = 'a';

// Start page
?>
<div class="thin">
    <div class="linkbox">
<?php  	if ($IsStaff) {
            echo view_link($View, 'my',         "<a href='/staffpm.php?view=my'>My unanswered". ($NumMy > 0 ? "($NumMy)":"") ."</a>");
        }
            echo view_link($View, 'unanswered', "<a href='/staffpm.php?view=unanswered'>All unanswered". ($NumUnanswered > 0 ? " ($NumUnanswered)":"") ."</a>");
            echo view_link($View, 'open',       "<a href='/staffpm.php?view=open'>Open" . ($NumOpen > 0 ? " ($NumOpen)":"") ."</a>");
            echo view_link($View, 'resolved',   "<a href='/staffpm.php?view=resolved'>Resolved</a>");
        if (check_perms('admin_stealth_resolve')) {
            echo view_link($View, 'stealthresolved', "<a href='/staffpm.php?view=stealthresolved'>Stealth Resolved</a>");
        }
            echo view_link($Action, 'responses',     "<a href='/staffpm.php?action=responses'>Common Answers</a>");
        if (check_perms('admin_staffpm_stats')) {
            echo view_link($Action, 'stats',         "<a href='/staffpm.php?action=stats'>StaffPM Stats</a>");
        } ?>
        <br />
        <br />
        <?=$Pages?>
    </div>
    <div class="head"><?=$ViewString?> Staff PMs</div>
    <div class="box pad" id="inbox">
<?php

if ($NumResults == 0) {
    // No messages
?>
        <h2>No messages</h2>
<?php

} else { ?>
        <form action="staffpm.php" method="get" id="searchbox">
            <div>
                <input type="hidden" name="view" value="<?=$View?>" />
                <input type="radio" name="searchtype" value="subject" checked="checked"/> Subject
                <input type="radio" name="searchtype" value="user" /> User
                <input type="radio" name="searchtype" value="message" /> Message
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <input type="checkbox" name="allfolders" value="1" <?php
                         if (isset($_GET['allfolders']) && $_GET['allfolders'])echo' checked="checked"'?>/> All Folders
                <input type="text" name="search" value="<?=($_GET['search'] ?? '')?>" style="width: 98%;"
                    onfocus="if (this.value == 'Search') this.value='';"
                    onblur="if (this.value == '') this.value='Search';"
                />
                <br />
            </div>
        </form>
<?php
    // Messages, draw table
    if ($ViewString != 'Resolved' && $IsStaff) {
        // Open multiresolve form
?>
        <form method="post" action="staffpm.php" id="messageform" onsubmit="return anyChecks('messageform')">
            <input type="hidden" name="action" value="multiresolve" />
            <input type="hidden" name="view" value="<?=strtolower($View)?>" />
<?php
    }

    // Table head
?>
            <table>
                <tr class="colhead">
<?php  				if ($ViewString != 'Resolved' && $IsStaff) { ?>
                    <td width="10"><input type="checkbox" onclick="toggleChecks('messageform',this)" /></td>
<?php  				} ?>
                    <td><a href="<?=header_link('Subject') ?>">Subject</td>
                    <td width="14%"><a href="<?=header_link('UserID') ?>">User</td>
<?php               if (check_perms('users_view_ips')) { ?>
                    <td width="4%">Actions</td>
<?php               } ?>
                    <td width="14%"><a href="<?=header_link('Date') ?>">Date</td>
                    <td width="14%"><a href="<?=header_link('Level') ?>">Assigned to</td>
<?php 				if ($ViewString == 'Resolved') { ?>
                    <td width="14%"><a href="<?=header_link('ResolverID') ?>">Resolved by</td>
<?php 				} else { ?>
                    <td width="14%"><a href="<?=header_link('Status') ?>">Status</td>
<?php 				}  ?>
                </tr>
<?php

    // List messages
    foreach ($StaffPMs as $staffPM) {
        list($ID, $Subject, $userID, $Status, $Level, $AssignedToUser, $Date, $Unread, $ResolverID, $Urgent) = $staffPM;

        if (empty($Urgent)) $Urgent = 'No';
        $Row = ($Row === 'a') ? 'b' : 'a';
        $RowClass = ($Unread==1 ? 'unreadpm' : 'row'.$Row);

        $UserInfo = user_info($userID);
        $UserStr = format_username($userID, $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID']);

        $class = $master->db->rawQuery(
            "SELECT Level
               FROM permissions
              WHERE ID = ?",
            [$UserInfo['PermissionID']]
        )->fetchColumn();

        // Get assigned
        if ($AssignedToUser == '') {
            // Assigned to class
            $Assigned = ($Level == 0) ? '<span class="rank" style="color:#49A5FF">First Line Support</span>' : make_class_string($classLevels[$Level]['ID'], TRUE);
            // No + on Sysops
            if ($Level != 1000) { $Assigned .= "+"; }

        } else {
            // Assigned to user
            $UserInfo = user_info($AssignedToUser);
            $Assigned = format_username($AssignedToUser, $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID']);

        }

        switch ($Status) {
            case 'Open':
                $StatusStr = '<span style="color:green">Open</span>';
                break;
            case 'Unanswered':
                $StatusStr = '<span style="color:red">Unanswered</span>';
                break;
            case 'User Resolved':
                $StatusStr = '<span style="color:blue">User Resolved</span>';
                break;
            case 'Resolved':
                $StatusStr = '<span style="color:green">Resolved</span>';
                break;
            default:
                $StatusStr = '<span style="color:red; font-weight:bold">Error</span>';
                break;
        }
        // Get resolver
        if ($ViewString == 'Resolved') {
            $UserInfo = user_info($ResolverID);
            $StatusStr = format_username($ResolverID, $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID']);
            if ($Urgent !='No') $UserStr .= '<br/><span style="color:red; font-weight:bold;">(User must '.$Urgent.')</span>';
        } else {
            if ($Urgent !='No') $StatusStr .= '<br/><span style="color:red; font-weight:bold;">(User must '.$Urgent.')</span>';
        }



        // Table row
?>
                <tr class="<?=$RowClass?>">
<?php  				if ($ViewString != 'Resolved' && $IsStaff) { ?>
                    <td class="center"><input type="checkbox" name="id[]" value="<?=$ID?>" /></td>
<?php  				} ?>
                    <td><a href="/staffpm.php?action=viewconv&amp;id=<?=$ID?>"><?=display_str($Subject)?></a></td>
                    <td><?=$UserStr?></td>
<?php               if (check_perms('users_view_ips')) {
                        if (check_perms('users_view_ips', $class) && $userID > 0) { ?>
                            <td>[<a href="/userhistory.php?action=ips&userid=<?=$userID?>">IPs</a>]</td>
<?php   				        } else { ?>
                            <td></td>
<?php                   }
                    } ?>
                    <td><?=time_diff($Date, 2, true)?></td>
                    <td><?=$Assigned?></td>
                    <td><?=$StatusStr?></td>
                </tr>
<?php
    }

    // Close table and multiresolve form
?>
            </table>
<?php  		if ($ViewString != 'Resolved' && $IsStaff) { ?>
            <input type="submit" name="MultiResolve" value="Resolve Selected" />
<?php 		} ?>
<?php           if (check_perms('admin_stealth_resolve')) { ?>
            <input type="submit" name="StealthResolve" value="Stealth Resolve Selected" />
<?php           } ?>
        </form>
<?php

}

?>
    </div>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php

show_footer();
