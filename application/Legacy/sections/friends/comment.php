<?php
$master->db->rawQuery(
    "UPDATE friends
        SET Comment = ?
      WHERE UserID = ? AND FriendID = ?",
    [$_POST['comment'], $activeUser['ID'], $_POST['friendid']]
);
$master->repos->userfriends->uncache([$activeUser['ID'], $P['friendid']]);
header('Location: friends.php');
