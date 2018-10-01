<?php

// Redirection
header("Location: /");

global $master, $LoggedUser;

// Basic authorization check
enforce_login();
authorize();

include_once SERVER_ROOT.'/Legacy/sections/bonus/functions.php';

// Validate user ID
if (empty($_POST['userid']) || !is_number($_POST['userid'])) {
    error('Invalid user ID');
}

// Validate credits amount
if (empty($_POST['credits']) || !is_number($_POST['credits']) || ((int) $_POST['credits'] <= 0)) {
    error('Invalid credits amount');
}

$ReceiverID = (int) $_POST['userid'];
$SenderID   = (int) $LoggedUser['ID'];
$Credits    = (int) $_POST['credits'];
$Anonymous  = (isset($_POST['anonymous']) && $_POST['anonymous'] === '1');
$SenderName = $Anonymous ? 'someone' : $LoggedUser['Username'];
$Time       = sqltime();
$Cost       = number_format($Credits);

if ($ReceiverID == $SenderID) {
    error('You cannot send credits to yourself.');
}

$Receiver = $master->repos->users->load($ReceiverID);

if (!$Receiver instanceof \Luminance\Entities\User) {
    error('User not found');
}

// Check if the receiver is enabled and has not blocked the sender
if ($Receiver->legacy['Enabled'] !== '1' || blockedGift($Receiver->ID, $SenderID)) {
    error('This user cannot receive credits from you.');
}

// First safe guard against simultaneous HTTP requests:
// we do not want multiple requests from a same user to be processed at the very same time (credits duplication);
// by setting a little delay, we hope to unsync the requests, so the others have time to finish.
usleep(mt_rand(100000, 2000000));

//$lockName   = "{$master->settings->main->site_name}:credits_transaction:{$LoggedUser['ID']}";
//$lockStatus = (int) $master->db->raw_query("SELECT GET_LOCK('{$lockName}', 2)")->fetchColumn();
//
//if ($lockStatus !== 1) {
//    error('A credits transaction is already running.');
//}

$SenderCredits = $master->db->raw_query("SELECT Credits From users_main WHERE ID = {$SenderID}")->fetchColumn();

if ($SenderCredits < $Credits) {
    error("You do not have that much credits.");
}

$ReceiverSummary = db_string("{$Time} | +{$Cost} credits | You received {$Cost} credits from {$SenderName}");
$SenderSummary   = db_string("{$Time} | -{$Cost} credits | You sent {$Cost} credits to {$Receiver->Username}");

$ReceiverStaffNote = null;
$SenderStaffNote   = null;

// No anonymity for staff!
if ($Anonymous) {
    $ReceiverStaffNote = db_string($Time." - User received {$Cost} credits from {$LoggedUser['Username']}");
    $ReceiverStaffNote = ", i.AdminComment = CONCAT_WS('\n', '{$ReceiverStaffNote}', i.AdminComment)";

    $SenderStaffNote = db_string($Time." - User sent {$Cost} credits to {$Receiver->Username}");
    $SenderStaffNote = ", i.AdminComment = CONCAT_WS('\n', '{$SenderStaffNote}', i.AdminComment)";
}


// Update the credits of sender first
$master->db->raw_query("UPDATE users_main AS m JOIN users_info AS i ON m.ID = i.UserID SET m.Credits = (m.Credits - {$Credits}), i.BonusLog = CONCAT_WS('\n', '$SenderSummary', i.BonusLog) {$SenderStaffNote} WHERE m.ID = {$SenderID}");
$master->db->raw_query("UPDATE users_main AS m JOIN users_info AS i ON m.ID = i.UserID SET m.Credits = (m.Credits + {$Credits}), i.BonusLog = CONCAT_WS('\n', '$ReceiverSummary', i.BonusLog) {$ReceiverStaffNote} WHERE m.ID = {$ReceiverID}");

// Release DB lock
//$master->db->raw_query("SELECT RELEASE_LOCK('{$lockName}')");

// Send notifications
if (!$Anonymous) {
    $Body = "{$SenderName} sent you a gift of {$Cost} credits. Enjoy and be sure to thank them!";
} else {
    $Body = "Someone who wishes to stay anonymous sent you a gift of {$Cost} credits. Enjoy!";
}

if (!empty($_POST['message'])) {
    $Body .= "[br][br]Message from the generous sender:[br][quote]{$_POST['message']}[/quote]";
}

$Subject = "You received {$Cost} credits from {$SenderName}";

send_pm($ReceiverID, 0, db_string($Subject), db_string($Body));

// Uncache
$master->repos->users->uncache($ReceiverID);
$master->repos->users->uncache($SenderID);

// Redirection
header("Location: /user.php?id={$ReceiverID}");
