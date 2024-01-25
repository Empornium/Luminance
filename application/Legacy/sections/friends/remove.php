<?php
$FType = isset($_REQUEST['type'])?$_REQUEST['type']:'friends';
if (!in_array($FType, ['friends', 'blocked'])) error(0);
$master->db->rawQuery(
    "DELETE
       FROM friends
      WHERE UserID = ?
        AND FriendID = ?
        AND Type = ?",
    [$activeUser['ID'], $P['friendid'], $FType]
);
$master->cache->deleteValue("user_friends_{$activeUser['ID']}");
$master->repos->userfriends->uncache([$activeUser['ID'], $P['friendid']]);
header("Location: friends.php?type={$FType}");
