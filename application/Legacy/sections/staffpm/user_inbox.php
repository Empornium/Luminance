<?php
include(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

show_header('Staff PMs', 'staffpm,bbcode,inbox,jquery');

// Get messages
$StaffPMs = $DB->query("
    SELECT
        ID,
        Subject,
        UserID,
        Status,
        Level,
        AssignedToUser,
        Date,
        Unread,
        Urgent
    FROM staff_pm_conversations
    WHERE UserID=".$LoggedUser['ID']."
    ORDER BY FIELD(Status, 'Unanswered','Open','User Resolved','Resolved'), Date DESC"
);

// Start page

$Show = isset($_REQUEST['show'])?($_REQUEST['show']==1?1:0):0;
$Assign = isset($_REQUEST['assign'])?$_REQUEST['assign']:'';
if ($Assign !== '' && !in_array($Assign, array('mod','smod','admin'))) $Assign = '';
$Subject = isset($_REQUEST['sub'])?$_REQUEST['sub']:'';
$Msg = isset($_REQUEST['msg'])?$_REQUEST['msg']:'';

?>
<div class="thin">
    <div class="head">Staff PMs</div>
    <?php if (!$master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::STAFFPM)): ?>
    <div class="box pad">
          <div class="center">
                <a href="#" onClick="jQuery('#compose').slideToggle('slow');">[Compose New]</a>
          </div>
        <?php  print_compose_staff_pm(!$Show, $Assign, $Subject, $Msg);  ?>
      </div>
    <?php endif; ?>
    <div class="box pad shadow" id="inbox">
<?php

if ($DB->record_count() == 0) {
    // No messages
?>
        <h2>No messages</h2>
<?php

} else {
    // Messages, draw table
?>
        <h3>Open messages</h3>
        <table>
            <tr class="colhead">
                <td width="50%">Subject</td>
                <td>Date</td>
                <td width="15%">Assigned to</td>
                          <td width="10%">Status</td>
            </tr>
<?php
    // List messages
    $Row = 'a';
    $ShowBox = 1;
    while (list($ID, $Subject, $UserID, $Status, $Level, $AssignedToUser, $Date, $Unread, $Urgent) = $DB->next_record()) {

        if (empty($Urgent)) $Urgent = 'No';
        if ($Status == 'User Resolved') { $Status = 'Resolved'; }
        if ($Status == 'Resolved') { $ShowBox++; }

        if ($ShowBox == 2) {
            // First resolved PM
            // close multiresolve form  , end table, start new table for already resolved staff messages
?>
            </table>

            <br />
            <h3>Resolved messages</h3>
            <table>
                <tr class="colhead">
                    <td width="50%">Subject</td>
                    <td>Date</td>
                    <td width="15%">Assigned to</td>
                    <td width="10%">Status</td>
                </tr>
<?php
        }

        // Get assigned
        $Assigned = ($Level == 0) ? "First Line Support" : $ClassLevels[$Level]['Name'];
        // No + on Sysops
        if ($Assigned != 'Sysop') { $Assigned .= "+"; }

        $UrgentStr='';
        if ($Urgent != 'No') {
            if ($Urgent == 'Read' && $Unread =='1') $UrgentStr = 'Read';
            elseif ($Urgent == 'Respond' && $Status != 'Unanswered' ) $UrgentStr = 'Respond to';
            if ($UrgentStr != '') $UrgentStr = '<span style="float:right;" class="red">You must '.$UrgentStr.' this message</span>';
        }
        if ($Unread == '1' || $UrgentStr != '') {
            $RowClass = 'unreadpm';
        } else {
            $Row = ($Row === 'a') ? 'b' : 'a';
            $RowClass = 'row'.$Row;
        }
        // Table row
?>
            <tr class="<?=$RowClass?>">
                <td><a href="/staffpm.php?action=viewconv&amp;id=<?=$ID?>"><?=display_str($Subject)?><?=$UrgentStr?></a></td>
                <td><?=time_diff($Date, 2, true)?></td>
                <td><?=$Assigned?></td>
                <td><?=$Status?></td>
            </tr>
<?php
        $DB->set_query_id($StaffPMs);
    }

    // Close table
?>
        </table>
<?php
}
?>
    </div>
</div>
<?php
show_footer();
