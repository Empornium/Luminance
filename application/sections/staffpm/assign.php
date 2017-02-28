<?php
if (!($IsFLS)) {
    // Logged in user is not FLS or Staff
    error(403);
}

include(SERVER_ROOT.'/sections/staffpm/functions.php');

if ($ConvID = (int) $_GET['convid']) {
    // FLS, check level of conversation
    $DB->query("SELECT Level FROM staff_pm_conversations WHERE ID=$ConvID");
    //list($Level) = $DB->next_record;
    list($Level) = $DB->next_record();

    if ($Level == 0) {
        // FLS conversation, assign to staff (moderator)
        if (!empty($_GET['to'])) {
            $Level = 0;
            switch ($_GET['to']) {
                case 'staff' :  // in this context 'staff' == Mod Pervs
                    $Level = 500; //  650;
                    $ClassName = 'Mods';
                    break;
                case 'admin' :  // in this context 'admin' == Admins+
                    $Level = 600; // 700;
                    $ClassName = 'Admins';
                    break;
                default :
                    error(404);
                    break;
            }

            $DB->query("UPDATE staff_pm_conversations SET Status='Unanswered', Level=".$Level." WHERE ID=$ConvID");
            $Message = sqltime()." - Assigned to ".$ClassName."  by ".$LoggedUser['Username'];
            make_staffpm_note($Message, $ConvID);
            header('Location: staffpm.php?view=open');
        } else {
            error(404);
        }
    } else {
        // FLS trying to assign non-FLS conversation
        error(403);
    }

} elseif ($ConvID = (int) $_POST['convid']) {
    // Staff (via ajax), get current assign of conversation
    $DB->query("SELECT Level, AssignedToUser FROM staff_pm_conversations WHERE ID=$ConvID");
    //list($Level, $AssignedToUser) = $DB->next_record;
    list($Level, $AssignedToUser) = $DB->next_record();

    if ($LoggedUser['Class'] >= $Level || $AssignedToUser == $LoggedUser['ID']) {
        // Staff member is allowed to assign conversation, assign
        list($LevelType, $NewLevel) = explode("_", db_string($_POST['assign']));

        if ($LevelType == 'class') {
            $Status = ($LoggedUser['Class'] == $NewLevel ? "" : "Status='Unanswered',");
            // Assign to class
            $DB->query("UPDATE staff_pm_conversations SET Level=$NewLevel, AssignedToUser=NULL WHERE ID=$ConvID");
            $ClassName = $NewLevel == 0 ? 'FLS' : $ClassLevels[$NewLevel]['Name'];
            $Message = sqltime()." - Assigned to ".$ClassName."  by ".$LoggedUser['Username'];
            make_staffpm_note($Message, $ConvID);
        } else {
            $UserInfo = user_info($NewLevel);
            $Level    = $Classes[$UserInfo['PermissionID']]['Level'];
            if (!$Level) {
                error("Assign to user not found.");
            }

            // Assign to user
            $DB->query("UPDATE staff_pm_conversations SET Status='Unanswered', AssignedToUser=$NewLevel, Level=$Level WHERE ID=$ConvID");
            $Message = sqltime()." - Assigned to ".$UserInfo['Username']."  by ".$LoggedUser['Username'];
            make_staffpm_note($Message, $ConvID);

        }
        echo '1';

    } else {
        // Staff member is not allowed to assign conversation
        echo '-1';
    }

} else {
    // No id
    header('Location: staffpm.php?view=open');
}
