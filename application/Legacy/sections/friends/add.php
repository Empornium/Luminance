<?php
authorize();
$friendID = $master->request->getGetInt('friendid');
$friendType = $master->request->getGetString('type', 'friends');
if (!in_array($friendType, ['friends', 'blocked'])) error(0);
if (in_array($friendID, [0, $master->request->user->ID])) error(0);

$isUser = $master->db->rawQuery(
    "SELECT 1 FROM users
             WHERE ID = ?",
    [$friendID])->fetchColumn();

if (!$isUser) error(0);

$master->db->rawQuery(
    "INSERT INTO friends (UserID, FriendID, Type)
          VALUES (?, ?, ?)
              ON DUPLICATE KEY
          UPDATE Type = VALUES(Type)",
    [$master->request->user->ID, $friendID, $friendType]
);
$master->cache->deleteValue("user_friends_{$master->request->user->ID}");
$master->repos->userfriends->uncache([$master->request->user->ID, $friendID]);
header("Location: friends.php?type={$friendType}");
