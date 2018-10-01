<?php
if ($Message = db_string($_POST['message'])) {

    include(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');
    $Text = new Luminance\Legacy\Text;
    $Text->validate_bbcode($_POST['message'],  get_permissions_advtags($LoggedUser['ID']));

    if ($_POST['note'] && $IsStaff) {

        // make a staff note
        $ConvID = (int) $_POST['convid'];

        // Is the user allowed to access this StaffPM
        check_access($ConvID);

        $Message = "[b]".sqltime()." - Notes added by ".$LoggedUser['Username'].":[/b] ".$Message;
        make_staffpm_note($Message, $ConvID);

        header("Location: staffpm.php?action=viewconv&id=$ConvID");

    } else if ($Subject = db_string($_POST['subject'])) {

        // New staff pm conversation
        $Level = db_string($_POST['level']);
        $DB->query("
            INSERT INTO staff_pm_conversations
                (Subject, Status, Level, UserID, Date)
            VALUES
                ('{$Subject}', 'Unanswered', '{$Level}', '{$LoggedUser['ID']}', '".sqltime()."')"
        );

        if (isset($_POST['forwardbody'])) {
            $Message = db_string($_POST['forwardbody']) .$Message;
        }
        // New message
        $ConvID = $DB->inserted_id();
        $DB->query("
            INSERT INTO staff_pm_messages
                (UserID, SentDate, Message, ConvID, IsNotes)
            VALUES
                (".$LoggedUser['ID'].", '".sqltime()."', '$Message', $ConvID, FALSE)"
        );

        header('Location: staffpm.php?action=user_inbox');

    } elseif ($ConvID = (int) $_POST['convid']) {

        // Is the user allowed to access this StaffPM
        check_access($ConvID);
        // Respond to existing conversation
        $DB->query("SELECT UserID, Urgent FROM staff_pm_conversations WHERE ID=$ConvID");
        list($TargetUserID, $Urgent) = $DB->next_record();
        if (empty($Urgent)) $Urgent = 'No';

        $DB->query("SELECT ID FROM staff_pm_messages WHERE ConvID=$ConvID ORDER BY ID DESC LIMIT 1");
        list($LastMessageID) = $DB->next_record();
        if ($LastMessageID != $_POST['lastmessageid']) {
            error("There is a message you have not read, please go back and refresh the page.");
        }

        $DB->query("
            INSERT INTO staff_pm_messages
                (UserID, SentDate, Message, ConvID, IsNotes)
            VALUES
                (".$LoggedUser['ID'].", '".sqltime()."', '$Message', $ConvID, FALSE)"
        );

        // Update conversation
        if ($TargetUserID != $LoggedUser['ID']) {
            // FLS/Staff
            $DB->query("UPDATE staff_pm_conversations SET Date='".sqltime()."', Unread=true, Status='Open' WHERE ID=$ConvID");
        } else {
            // User replied
            if ($Urgent == 'Respond') $ExtraSet = ", Urgent='No'";
            $DB->query("UPDATE staff_pm_conversations SET Date='".sqltime()."', Unread=false, Status='Unanswered'$ExtraSet WHERE ID=$ConvID");
        }

        // Clear cache for user
        $Cache->delete_value('staff_pm_new_'.$TargetUserID);
        //$Cache->delete_value('staff_pm_new_'.$LoggedUser['ID']);
        $master->cache->delete_value('staff_pm_urgent_'.$TargetUserID);

        header("Location: staffpm.php?action=viewconv&id=$ConvID");

    } else {
        // Message but no subject or conversation id
        header("Location: staffpm.php?action=viewconv&id=$ConvID");
    }
} elseif ($ConvID = (int) $_POST['convid']) {
    // No message, but conversation id
    header("Location: staffpm.php?action=viewconv&id=$ConvID");

} else {
    // No message or conversation id
    header('Location: staffpm.php?view=open');
}
