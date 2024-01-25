<?php
//******************************************************************************//
//--------------- Take mass pm -------------------------------------------------//
// This pages handles the backend of the 'send mass pm' function. It checks	 //
// the data, and if it all validates, it sends a pm to everyone who snatched	//
// the torrent.																 //
//******************************************************************************//

authorize();

enforce_login();

$Validate = new \Luminance\Legacy\Validate;

$GroupID = (int) $_POST['groupid'];
$SenderID = isset($_POST['showsender']) ? $activeUser['ID'] : 0;
$Subject = $_POST['subject'];
$Message = $_POST['message'];

//******************************************************************************//
//--------------- Validate data in edit form -----------------------------------//

$Validate->SetFields('groupid', '1', 'number', 'Invalid group ID.', ['maxlength'=>1000000000, 'minlength'=>1]); // we shouldn't have group IDs higher than a billion either
$Validate->SetFields('subject', '1', 'string', 'Invalid subject.',  ['maxlength'=>1000, 'minlength'=>1]);
$Validate->SetFields('message', '1', 'string', 'Invalid message.',  ['maxlength'=>10000, 'minlength'=>1]);
$Err = $Validate->ValidateForm($_POST); // Validate the form

if ($Err) {
    error($Err);
    header('Location: '.$_SERVER['HTTP_REFERER']);
    die();
}

//******************************************************************************//
//--------------- Send PMs to users --------------------------------------------//

$users = $master->db->rawQuery(
    'SELECT UserID
       FROM users_groups
      WHERE GroupID = ?',
    [$GroupID]
)->fetchAll(\PDO::FETCH_COLUMN);

if ($master->db->foundRows()>0) {
    if ($SenderID == 0) { // we only want to send a masspm if from system
        send_pm($users, 0, $Subject, $Message);
    } else {
        foreach ($users as $userID) {
        send_pm($userID,($SenderID==$userID?0:$SenderID), $Subject, $Message);
        }
    }
}

$Log = isset($_POST['showsender']) ? "[user]{$activeUser['ID']}[/user]" : "System ([user]{$activeUser['ID']}[/user])";
$Log = sqltime()." - [color=purple]Mass PM sent[/color] by $Log - subject: $Subject";
$master->db->rawQuery(
    "UPDATE groups
        SET Log=CONCAT_WS(CHAR(10 using utf8), ?, Log)
      WHERE ID = ?",
    [$Log, $GroupID]
);

header("Location: groups.php?groupid={$GroupID}");
