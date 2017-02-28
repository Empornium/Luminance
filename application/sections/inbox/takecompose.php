<?php
authorize();

function blockedPM($ToID, $FromID, &$Error)
{
    global $StaffIDs, $DB;
    $FromID=(int) $FromID;
    $Err=false;
    if (!is_number($ToID)) {
        $Err = "This recipient does not exist.";
    } else {
        $ToID = (int) $ToID;
            if (!isset($StaffIDs[$FromID])) { // staff are never blocked
                // check if this user is blocked from sending
                $DB->query("SELECT Type FROM friends WHERE UserID='$ToID' AND FriendID='$FromID'");
                list($FType)=$DB->next_record();
                if($FType == 'blocked') $Err = "This user cannot recieve PM's from you.";
                else {
                    $DB->query("SELECT BlockPMs FROM users_info WHERE UserID='$ToID'");
                    list($BlockPMs)=$DB->next_record();
                    if($BlockPMs == 2) $Err = "This user cannot recieve PM's from you.";
                    elseif($BlockPMs == 1 && $FType != 'friends')
                        $Err = "This user cannot recieve PM's from you.";
                }
            }
    }
    $Error = $Err;

    return $Err !== false;
}

if (empty($_POST['toid']) || !is_number($_POST['toid'])) { error(404); }

if (!empty($LoggedUser['DisablePM']) && !isset($StaffIDs[$_POST['toid']])) {
    error(403);
}

$ToID = $_POST['toid'];
if (blockedPM($ToID, $LoggedUser[ID], $Err)) error($Err);

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
if(!empty($Err)) error($Err);

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;
$Text->validate_bbcode($_POST['body'],  get_permissions_advtags($LoggedUser['ID']));

if (isset($_POST['forwardbody'])) {
    $_POST['body'] = "$_POST[forwardbody]$_POST[body]";
}

$ConvID = send_pm($ToID,$LoggedUser['ID'],db_string($Subject),db_string($_POST['body']),$ConvID);

header('Location: inbox.php');
