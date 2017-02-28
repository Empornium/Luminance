<?php
include(SERVER_ROOT.'/sections/staffpm/functions.php');

if ($ID = (int) ($_GET['id'])) {
    // Check if conversation belongs to user
    $DB->query("SELECT UserID, Level, AssignedToUser FROM staff_pm_conversations WHERE ID=$ID");
    list($UserID, $Level, $AssignedToUser) = $DB->next_record();

    if (($IsFLS && $Level == 0) || ($IsStaff && $Level <= $LoggedUser['Class'])) {

        // Change from Unanswered to Open
        $DB->query("UPDATE staff_pm_conversations SET Status='Unanswered' WHERE ID=$ID");

        // Add a log message to the StaffPM
        $Message = sqltime()." - Marked as unread by ".$LoggedUser['Username'];
        make_staffpm_note($Message, $ID);

        if (isset($_GET['return']))
            header("Location: staffpm.php?action=viewconv&id=$ID");
        else
            header('Location: staffpm.php?view=open');

    } else {
        // Not Staff
        error(403);
    }
} else {
    // No id
    header('Location: staffpm.php?view=open');
}
