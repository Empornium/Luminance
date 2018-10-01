<?php
include(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

if ($master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::STAFFPM)) {
    error('Your staff PM rights have been disabled.');
}

if ($ConvID = (int) ($_GET['id'])) {

    // Is the user allowed to access this StaffPM
    check_access($ConvID);

    // Check if conversation belongs to user
    $DB->query("SELECT UserID, Urgent FROM staff_pm_conversations WHERE ID=$ConvID");
    list($TargetUserID, $Urgent) = $DB->next_record();
    if (empty($Urgent)) $Urgent = 'No';

    // Conversation belongs to user or user is staff, resolve it
    if($TargetUserID == $LoggedUser['ID']) {
        if ($Urgent == 'Respond') error("You cannot resolve this conversation until you respond to it.");
        $Resolve = "'User Resolved'";
    } else {
        $Resolve = "'Resolved'";
    }
    $DB->query("UPDATE staff_pm_conversations SET Status=".$Resolve.", ResolverID=".$LoggedUser['ID']." WHERE ID=$ConvID");
    $Cache->delete_value('staff_pm_new_'.$LoggedUser['ID']);

    // Add a log message to the StaffPM
    $Message = sqltime()." - Resolved by ".$LoggedUser['Username'];
    make_staffpm_note($Message, $ConvID);

    header('Location: staffpm.php?view=open');
} else {
    // No id
    header('Location: staffpm.php?view=open');
}
