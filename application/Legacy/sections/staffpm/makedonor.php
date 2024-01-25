<?php
if (!is_integer_string($_GET['id'])) {
    error(404);
}

if (!check_perms('users_give_donor')) {
    error(403);
}

$ConvID = (int) $_GET['id'];
$nextRecord = $master->db->rawQuery(
    "SELECT c.Subject,
            c.UserID,
            c.Level,
            c.AssignedToUser,
            c.Unread,
            c.Status,
            u.Donor
       FROM staff_pm_conversations AS c
       JOIN users_info AS u ON u.UserID = c.UserID
      WHERE ID = ?",
    [$ConvID]
)->fetch(\PDO::FETCH_NUM);
list($Subject, $userID, $Level, $AssignedToUser, $Unread, $Status, $Donor) = $nextRecord;
if ($master->db->foundRows() == 0) {
    error(404);
}

$Message = "Thank you for helping to support the site.  It's users like you who make all of this possible.";

if ((int) $Donor === 0) {
    $Msg = sqltime() . " - Donated: /staffpm.php?action=viewconv&id={$ConvID}\n";
    $master->db->rawQuery(
        "UPDATE users_info
            SET Donor = '1',
                AdminComment = CONCAT(?, AdminComment)
          WHERE UserID = ?",
        [$Msg, $userID]
    );
    /* Eh... fuck no!
    $master->db->rawQuery(
        "UPDATE users_main
            SET Invites = Invites + 2
          WHERE ID = ?",
        [$userID]
    );
    */

    $master->repos->users->uncache($userID);
    $Message .= "  Enjoy your new love from us!";
} else {
    $Message .= "  ";
}
$master->db->rawQuery(
    "INSERT INTO staff_pm_messages (UserID, SentDate, Message, ConvID)
          VALUES (?, ?, ?, ?)",
    [$activeUser['ID'], sqltime(), $Message, $ConvID]
);
$master->db->rawQuery(
    "UPDATE staff_pm_conversations
        SET Date = ?,
            Unread = true,
            Status = 'Resolved',
            ResolverID = ?
      WHERE ID = ?",
    [sqltime(), $activeUser['ID'], $ConvID]
);
header('Location: staffpm.php?view=open');
