<?php
if (!($IsFLS)) {
    // Logged in user is not FLS or Staff
    error(403);
}

include_once(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

if ($ConvID = (int) ($_GET['convid'] ?? null)) {
    // FLS, check level of conversation
    $Level = $master->db->rawQuery(
        "SELECT Level
           FROM staff_pm_conversations
          WHERE ID = ?",
        [$ConvID]
    )->fetchColumn();

    if ($Level == 0) {
        // FLS conversation, assign to staff (moderator)
        if (!empty($_GET['to'])) {
            $Level = 0;
            switch ($_GET['to']) {
                case 'staff' :  // in this context 'staff' == Mod Pervs
                    $Level = 500; //  650;
                    $className = 'Mods';
                    break;
                case 'admin' :  // in this context 'admin' == Admins+
                    $Level = 600; // 700;
                    $className = 'Admins';
                    break;
                default :
                    error(404);
                    break;
            }

            $master->db->rawQuery(
                "UPDATE staff_pm_conversations
                    SET Status = 'Unanswered',
                        Level = ?
                  WHERE ID = ?",
                [$Level, $ConvID]
            );
            $Message = sqltime()." - Assigned to ".$className."  by ".$activeUser['Username'];
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
    list($Level, $AssignedToUser) = $master->db->rawQuery(
        "SELECT Level,
                AssignedToUser
           FROM staff_pm_conversations
          WHERE ID = ?",
        [$ConvID]
    )->fetch(\PDO::FETCH_NUM);

    if ($activeUser['Class'] >= $Level || $AssignedToUser == $activeUser['ID']) {
        // Staff member is allowed to assign conversation, assign
        list($LevelType, $NewLevel) = explode("_", $_POST['assign']);

        if ($LevelType == 'class') {
            // Assign to class
            $master->db->rawQuery(
                "UPDATE staff_pm_conversations
                    SET Level = ?,
                        AssignedToUser = NULL
                  WHERE ID = ?",
                [$NewLevel, $ConvID]
            );
            $className = $NewLevel == 0 ? 'FLS' : $classLevels[$NewLevel]['Name'];
            $Message = sqltime()." - Assigned to ".$className."  by ".$activeUser['Username'];
            make_staffpm_note($Message, $ConvID);
        } else {
            $UserInfo = user_info($NewLevel);
            $Level    = $classes[$UserInfo['PermissionID']]['Level'];
            if (!$Level) {
                error("Assign to user not found.");
            }

            // Assign to user
            $master->db->rawQuery(
                "UPDATE staff_pm_conversations
                    SET Status = 'Unanswered',
                        AssignedToUser = ?,
                        Level = ?
                  WHERE ID = ?",
                [$NewLevel, $Level, $ConvID]
            );
            $Message = sqltime()." - Assigned to ".$UserInfo['Username']."  by ".$activeUser['Username'];
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
