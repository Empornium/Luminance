<?php
authorize();
$FriendID = db_string($_GET['friendid']);
$FType = isset($_REQUEST['type'])?$_REQUEST['type']:'friends';
if(!in_array($FType, array('friends','blocked'))) error(0);
$DB->query("INSERT INTO friends (UserID, FriendID, Type)
                         VALUES ('$LoggedUser[ID]', '$FriendID','$FType')
         ON DUPLICATE KEY UPDATE Type=VALUES(Type)");
$Cache->delete_value('user_friends_'.$LoggedUser['ID']);
header('Location: friends.php?type='.$FType);
