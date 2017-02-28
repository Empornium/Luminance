<?php
include(SERVER_ROOT.'/sections/staffpm/functions.php');

if ($ID = (int) ($_GET['id'])) {
    // Check if conversation belongs to user
    $DB->query("SELECT UserID, AssignedToUser FROM staff_pm_conversations WHERE ID=$ID");
    list($UserID, $AssignedToUser) = $DB->next_record();

    if ($UserID == $LoggedUser['ID'] || $IsFLS || $AssignedToUser == $LoggedUser['ID']) {
        // Conversation belongs to user or user is staff, resolve it
        if($UserID == $LoggedUser['ID']) {
            $Resolve = "'User Resolved'";
        } else {
            $Resolve = "'Resolved'";
        }
        $DB->query("UPDATE staff_pm_conversations SET Status=".$Resolve.", ResolverID=".$LoggedUser['ID']." WHERE ID=$ID");
        $Cache->delete_value('staff_pm_new_'.$LoggedUser['ID']);

        // Add a log message to the StaffPM
        $Message = sqltime()." - Resolved by ".$LoggedUser['Username'];
        make_staffpm_note($Message, $ID);

        header('Location: staffpm.php?view=open');
    } else {
        // Conversation does not belong to user
        error(403);
    }
} else {
    // No id
    header('Location: staffpm.php?view=open');
}
