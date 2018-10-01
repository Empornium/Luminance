<?php
if (!($IsStaff)) {
    // Logged in user is not Staff
    error(403, true);
}

if (!in_array($_POST['urgency'], ['No','Read','Respond'])) {
    error(0, true);
}
$urgency = $_POST['urgency'];
if (!is_number($_POST['convid'])) error(0, true);
$ConvID = (int) $_POST['convid'];

include(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');


if ($ConvID) {

    // Staff (via ajax), get current assign of conversation
    $conv = $master->db->raw_query("SELECT Level, AssignedToUser, UserID, Urgent, Status, Unread FROM staff_pm_conversations WHERE ID=:convid",
                                [':convid' => $ConvID])->fetch(\PDO::FETCH_ASSOC);

    if ($LoggedUser['Class'] >= $conv['Level'] || $conv['AssignedToUser'] == $LoggedUser['ID']) {

        $master->db->raw_query("UPDATE staff_pm_conversations
                                   SET Urgent=:urgent".
                                      ($urgency!='No'?", Status='Open', Unread='1'":'') ."
                                 WHERE ID=:id",
                                       [':urgent' => $urgency,
                                        ':id'     => $ConvID]);

        $master->cache->delete_value('staff_pm_new_'.$conv['UserID']);
        $master->cache->delete_value('staff_pm_urgent_'.$conv['UserID']);

        // Add a log message to the StaffPM
        $sqltime = sqltime();
        $message = $sqltime." - Force Response set to '$urgency' by ".$LoggedUser['Username'];
        // log automagic actions so staff can see what is going on
        if ($urgency!='No') {
            if ($conv['Status'] != 'Open') $message .= '[br]'.$sqltime." - (Status automatically set to Open)";
            if ($conv['Unread'] != '1') $message .= '[br]'.$sqltime." - (Message automatically set to Unread)";
        }
        make_staffpm_note(db_string($message), $ConvID);

        echo '1';
    } else {
        // Staff member is not allowed to assign conversation
        echo '-1';
    }

} else {
    // No id
    echo '-1';
}
