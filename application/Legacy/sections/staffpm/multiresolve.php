<?php
include_once(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');
$user = $this->master->request->user;

if ($ConvIDs = $_POST['id']) {
    $queries = [];
    foreach ($ConvIDs as &$ConvID) {
        $ConvID = (int) $ConvID;
        check_access($ConvID);

        if (isset($_POST['StealthResolve']) && check_perms('admin_stealth_resolve')) {
            $master->db->rawQuery(
                "UPDATE staff_pm_conversations
                    SET StealthResolved = 1
                  WHERE ID = ?",
                [$ConvID]
            );
            // Add a log message to the StaffPM
            $Message = sqltime()." - Stealth Resolved by ".$user->Username;
            make_staffpm_note($Message, $ConvID);
        } else {
            // Conversation belongs to user or user is staff
            $master->db->rawQuery(
                "UPDATE staff_pm_conversations
                    SET Status = 'Resolved',
                        ResolverID = ?
                  WHERE ID = ?",
                [$user->ID, $ConvID]
            );
            // Add a log message to the StaffPM
            $Message = sqltime()." - Resolved by ".$user->Username;
            make_staffpm_note($Message, $ConvID);
        }
    }

    // Clear cache for user
    $master->cache->deleteValue('staff_pm_new_'.$user->ID);

    // Done! Return to inbox
    if (empty($_POST['view']) || !in_array($_POST['view'], ['open', 'resolved', 'stealthresolved', 'my', 'unanswered', 'allfolders'])) {
        header("Location: /staffpm.php");
    } else {
        header("Location: /staffpm.php?view={$_POST['view']}");
    }
} else {
  // No id
  header("Location: /staffpm.php");
}
