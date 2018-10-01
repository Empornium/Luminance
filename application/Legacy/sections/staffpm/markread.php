<?php
include(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

if ($ConvID = (int) ($_GET['id'])) {
    // Is the user allowed to access this StaffPM
    check_access($ConvID);

    // Change from Unanswered to Open
    $DB->query("UPDATE staff_pm_conversations SET Status='Open' WHERE ID=$ConvID");

    // Add a log message to the StaffPM
    $Message = sqltime()." - Marked as read (set to Open) by ".$LoggedUser['Username'];
    make_staffpm_note($Message, $ConvID);

    if (isset($_GET['return']))
        header("Location: staffpm.php?action=viewconv&id=$ConvID");
    else
        header('Location: staffpm.php?view=open');

} else {
    // No id
    header('Location: staffpm.php?view=open');
}
