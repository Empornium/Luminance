<?php
// perform the back end of subscribing to collages
authorize();

if (!is_integer_string($_GET['collageid'])) {
    error(0);
}

$CollageID = (int) $_GET['collageid'];
$collage = $master->repos->collages->load($CollageID);

if (!($collage instanceof \Luminance\Entities\Collage)) {
    error(404);
}

if (!$UserSubscriptions = $master->cache->getValue('collage_subs_user_'.$activeUser['ID'])) {
    $UserSubscriptions = $master->db->rawQuery(
        'SELECT CollageID
           FROM collages_subscriptions
          WHERE UserID = ?',
        [$activeUser['ID']]
    )->fetchAll(\PDO::FETCH_COLUMN);
    $master->cache->cacheValue('collage_subs_user_'.$activeUser['ID'], $UserSubscriptions,0);
}

if (($Key = array_search($_GET['collageid'], $UserSubscriptions)) !== FALSE) {
    $master->db->rawQuery(
        'DELETE
           FROM collages_subscriptions
          WHERE UserID = ?
            AND CollageID = ?',
        [$activeUser['ID'], $_GET['collageid']]
    );
    unset($UserSubscriptions[$Key]);
    $master->cache->decrementValue('collage_'.$CollageID.'_subscribers');
} else {
    $master->db->rawQuery(
        "INSERT IGNORE INTO collages_subscriptions (UserID, CollageID, LastVisit)
                     VALUES (?, ?, NOW())",
        [$activeUser['ID'], $_GET['collageid']]
    );
    array_push($UserSubscriptions, $_GET['collageid']);
    $master->cache->incrementValue('collage_'.$CollageID.'_subscribers');
}
$master->cache->replaceValue('collage_subs_user_'.$activeUser['ID'], $UserSubscriptions, 0);
$master->cache->deleteValue('collage_subs_user_new_'.$activeUser['ID']);
