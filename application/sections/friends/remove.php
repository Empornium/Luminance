<?php
$FType = isset($_REQUEST['type'])?$_REQUEST['type']:'friends';
if(!in_array($FType, array('friends','blocked'))) error(0);
$DB->query("DELETE FROM friends WHERE UserID='$LoggedUser[ID]' AND FriendID='$P[friendid]' AND Type='$FType'");
$Cache->delete_value('user_friends_'.$LoggedUser['ID']);
header('Location: friends.php?type='.$FType);
