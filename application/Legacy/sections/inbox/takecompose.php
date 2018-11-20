<?php
authorize();


if (empty($_POST['toid']) || !is_number($_POST['toid'])) {
    error(404);
}

$StaffIDs = getStaffIDs();

if ($master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::PM) && !isset($StaffIDs[$ToID])) {
    error("Your PM rights have been disabled.");
}

$ToID = $_POST['toid'];
if (blockedPM($ToID, $LoggedUser['ID'], $Err)) {
    error($Err);
}

if (isset($_POST['convid']) && is_number($_POST['convid'])) {
    $ConvID = $_POST['convid'];
    $DB->query("SELECT UserID FROM pm_conversations_users WHERE UserID='$LoggedUser[ID]' AND ConvID='$ConvID'");
    if ($DB->record_count() == 0) {
        error(403);
    }
    $Subject='';
} else {  // new convo
    $ConvID='';
    $Subject = trim($_POST['subject']);
    if (!$Err && empty($Subject)) {
        $Err = "You can't send a message without a subject.";
    }
}
$Body = trim($_POST['body']);
if (!$Err && empty($Body)) {
    $Err = "You can't send a message without a body!";
}
if (!empty($Err)) {
    error($Err);
}

$Text = new Luminance\Legacy\Text;
$Text->validate_bbcode($_POST['body'], get_permissions_advtags($LoggedUser['ID']), true, false);

if (isset($_POST['forwardbody'])) {
    $_POST['body'] = "$_POST[forwardbody]$_POST[body]";
}

$ConvID = send_pm($ToID, $LoggedUser['ID'], db_string($Subject), db_string($_POST['body']), $ConvID);

header('Location: inbox.php');
