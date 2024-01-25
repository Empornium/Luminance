<?php
authorize();
$where = '';
$params = [$activeUser['ID']];
if (array_key_exists('collageid', $_REQUEST)) {
    if (is_integer_string($_REQUEST['collageid'])) {
      $where = ' AND CollageID = ?';
      $params[] = $_REQUEST['collageid'];
    }
}

$master->db->rawQuery(
    "UPDATE collages_subscriptions
        SET LastVisit = NOW()
      WHERE UserID = ?
      {$where}",
    $params
);
$master->cache->deleteValue('collage_subs_user_new_'.$activeUser['ID']);

header('Location: userhistory.php?action=subscribed_collages');
