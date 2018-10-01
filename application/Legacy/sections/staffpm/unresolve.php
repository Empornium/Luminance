<?php
include(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

if ($master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::STAFFPM)) {
    error('Your staff PM rights have been disabled.');
}

if ($ConvID = (int) ($_GET['id'])) {
    // Is the user allowed to access this StaffPM
    check_access($ConvID);

    // Conversation belongs to user or user is staff, unresolve it // changed from Unanswered to Open on unresolve
    $DB->query("UPDATE staff_pm_conversations SET Status='Open' WHERE ID=$ConvID");
    // Clear cache for user
    $Cache->delete_value('num_staff_pms_'.$LoggedUser['ID']);

    // Add a log message to the StaffPM
    $Message = sqltime()." - Unresolved by ".$LoggedUser['Username'];
    make_staffpm_note($Message, $ConvID);

    if (isset($_GET['return']))
        header("Location: staffpm.php?action=viewconv&id=$ConvID");
    else
        header('Location: staffpm.php?view=open');

} else {
    // No id
    header('Location: staffpm.php?view=open');
}
