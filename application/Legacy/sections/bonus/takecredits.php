<?php

// Redirection
header("Location: /");

global $master, $activeUser;

// Basic authorization check
enforce_login();
authorize();

include_once SERVER_ROOT.'/Legacy/sections/bonus/functions.php';

// Validate user ID
if (empty($_POST['userid']) || !is_integer_string($_POST['userid'])) {
    error('Invalid user ID');
}

// Validate credits amount
if (empty($_POST['credits']) || !is_integer_string($_POST['credits']) || ((int) $_POST['credits'] <= 0)) {
    error('Invalid credits amount');
}

$ReceiverID = (int) $_POST['userid'];
$SenderID   = (int) $activeUser['ID'];
$Credits    = (int) $_POST['credits'];
$Anonymous  = (isset($_POST['anonymous']) && $_POST['anonymous'] === '1');
$SenderName = $Anonymous ? 'someone' : $activeUser['Username'];
$time       = sqltime();
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

$sender = $master->repos->users->load($SenderID);
$receiver = $master->repos->users->load($ReceiverID);
$SenderCredits = $sender->wallet->Balance;

if ($SenderCredits < $Credits) {
    error("You do not have that much credits.");
}

$ReceiverSummary = "{$time} | +{$Cost} credits | You received {$Cost} credits from {$SenderName}";
$SenderSummary   = "{$time} | -{$Cost} credits | You sent {$Cost} credits to {$Receiver->Username}";

$ReceiverStaffNote = null;
$SenderStaffNote   = null;


// Update the credits of sender first
$sender->wallet->adjustBalance(-$Credits);
$sender->wallet->addLog($SenderSummary);

$receiver->wallet->adjustBalance($Credits);
$receiver->wallet->addLog($ReceiverSummary);

// No anonymity for staff!
if ($Anonymous) {
    $ReceiverStaffNote = $time." - User received {$Cost} credits from {$activeUser['Username']}";
    $SenderStaffNote = $time." - User sent {$Cost} credits to {$Receiver->Username}";
}

if (!empty($SenderStaffNote)) {
    $master->db->rawQuery(
        "UPDATE users_info
            SET AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
          WHERE UserID = ?",
        [$SenderStaffNote, $SenderID]
    );
}

if (!empty($ReceiverStaffNote)) {
    $master->db->rawQuery(
        "UPDATE users_info
            SET AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
          WHERE UserID = ?",
        [$ReceiverStaffNote, $ReceiverID]
    );
}

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

send_pm($ReceiverID, 0, $Subject, $Body);

// Uncache
$master->repos->users->uncache($ReceiverID);
$master->repos->users->uncache($SenderID);

// Redirection
header("Location: /user.php?id={$ReceiverID}");
