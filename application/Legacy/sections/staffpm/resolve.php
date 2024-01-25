<?php
include_once(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

if ($master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::STAFFPM)) {
    error('Your staff PM rights have been disabled.');
}

if ($ConvID = (int) ($_GET['id'])) {

    // Is the user allowed to access this StaffPM
    check_access($ConvID);

    // Check if conversation belongs to user
    list($TargetUserID, $Urgent) = $master->db->rawQuery(
        "SELECT UserID,
                Urgent
           FROM staff_pm_conversations
          WHERE ID= ?",
        [$ConvID]
    )->fetch(\PDO::FETCH_NUM);
    if (is_null($Urgent)) $Urgent = 'No';

    // Conversation belongs to user or user is staff, resolve it
    if ($TargetUserID == $activeUser['ID']) {
        if ($Urgent == 'Respond') error("You cannot resolve this conversation until you respond to it.");
        $Resolve = "'User Resolved'";
    } else {
        $Resolve = "'Resolved'";
    }
    $master->db->RawQuery(
        "UPDATE staff_pm_conversations
            SET Date= ?,
                Status= ?,
                ResolverID= ?,
          WHERE ID= ?",
        [sqltime(), $Resolve, $activeUser['ID'], $ConvID]
    );
    $master->cache->deleteValue('staff_pm_new_'.$activeUser['ID']);

    // Add a log message to the StaffPM
    $Message = sqltime()." - Resolved by ".$activeUser['Username'];
    make_staffpm_note($Message, $ConvID);

    header('Location: staffpm.php?view=open');
} else {
    // No id
    header('Location: staffpm.php?view=open');
}
