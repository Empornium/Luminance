<?php

function getStaffIDs()
{
    global $master;
    static $staffIDs = null;
    if (!is_array($staffIDs)) $staffIDs = $master->cache->getValue("staff_ids");
    if (!is_array($staffIDs)) {
        $allstaff = $master->db->rawQuery(
            "SELECT u.ID,
                    u.Username
               FROM users AS u
               JOIN users_main AS um ON u.ID = um.ID
               JOIN permissions AS p ON p.ID = um.PermissionID
              WHERE p.DisplayStaff = '1'"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $staffIDs = [];
        foreach ($allstaff as $staff) {
            $staffIDs[$staff['ID']] = $staff['Username'];
        }
        uasort($staffIDs, 'strcasecmp');
        $master->cache->cacheValue("staff_ids", $staffIDs);
    }
    return $staffIDs;
}

function get_shop_items_other() {
    global $master;
    if (($ShopItems = $master->cache->getValue('shop_items_other')) === false) {
        $ShopItems = $master->db->rawQuery(
            "SELECT ID,
                    Title,
                    Description,
                    Action,
                    Value,
                    Cost
               FROM bonus_shop_actions
              WHERE (Action = 'givegb' OR Action = 'givecredits')
                AND Gift = '0'
           ORDER BY Sort"
        )->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('shop_items_other', $ShopItems);
    }

    return $ShopItems;
}

function get_shop_items_gifts() {
    global $master;
    if (($ShopItems = $master->cache->getValue('shop_items_gifts')) === false) {
        $ShopItems = $master->db->rawQuery(
            "SELECT ID,
                    Title,
                    Description,
                    Action,
                    Value,
                    Cost
               FROM bonus_shop_actions
              WHERE Gift = '1'
           ORDER BY Sort"
        )->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('shop_items_gifts', $ShopItems);
    }

    return $ShopItems;
}

function get_shop_items($userID) {
    global $master;
    if (($ShopItems = $master->cache->getValue('shop_items')) === false) {
        $ShopItems = $master->db->rawQuery(
            "SELECT ID,
                    Title,
                    Description,
                    Action,
                    Value,
                    Cost,
                    NULL AS Image,
                    NULL AS Badge,
                    NULL AS Rank,
                    NULL AS MaxRank
               FROM bonus_shop_actions
              WHERE Action != 'badge'
                AND Action != 'ufl'
                AND Gift = '0'
           ORDER BY Sort"
        )->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('shop_items', $ShopItems);
    }
    $userBadges = $master->db->rawQuery(
        "SELECT s.ID,
                s.Title,
                s.Description,
                s.Action,
                s.Value,
                b.Cost,
                b.Image,
                b.Badge,
                b.Rank,
                (
                    SELECT Max(b2.Rank)
                      FROM users_badges AS ub2
                 LEFT JOIN badges AS b2 ON b2.ID = ub2.BadgeID AND ub2.UserID = $userID
                     WHERE b2.Badge = b.Badge
                ) AS MaxRank
           FROM bonus_shop_actions AS s
           JOIN badges AS b ON b.ID = s.Value AND Action = 'badge'
       ORDER BY s.Sort"
    )->fetchAll(\PDO::FETCH_BOTH);
    if ($master->db->foundRows()>0) $ShopItems = array_merge($ShopItems, $userBadges);
    return $ShopItems;
}

function get_shop_item($ItemID) {
    global $master;
    $ItemID = (int) $ItemID;
    if (($ShopItem = $master->cache->getValue('shop_item_'.$ItemID)) === false) {
        $ShopItem = $master->db->rawQuery(
            "SELECT s.ID,
                    s.Title,
                    s.Description,
                    s.Action,
                    s.Value,
                    IF (Action = 'badge', b.Cost, s.Cost) AS Cost
               FROM bonus_shop_actions AS s
          LEFT JOIN badges AS b ON b.ID = s.Value
              WHERE s.ID = ?", [$ItemID]
        )->fetch(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('shop_item_'.$ItemID, $ShopItem);
    }

    return $ShopItem;
}

function shop_has_invites($userID) {
    $items = get_shop_items($userID);
    return in_array('invite', array_column($items, 'Action'));
}

function get_user_stats($userID) {
    global $master;
    $userID = (int) $userID;
    $userStats = $master->cache->getValue('user_stats_'.$userID);
    if (!is_array($userStats)) {
        $userStats = $master->db->rawQuery(
            "SELECT Uploaded AS BytesUploaded,
                    Downloaded AS BytesDownloaded,
                    RequiredRatio
               FROM users_main
              WHERE ID = ?",
            [$userID]
        )->fetch(\PDO::FETCH_ASSOC);
        $master->cache->cacheValue('user_stats_'.$userID, $userStats, 900);
    }

      return $userStats;
}

function get_gift_pm() {
    global $master;
    $PMText = $master->cache->getValue('systempm_template_1');
    if ($PMText === false) {
        $PMText = $master->db->rawQuery(
            "SELECT Body,
                    Help
               FROM systempm_templates
              WHERE ID = 1"
        )->fetch(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('systempm_template_1', $PMText, 86400);
    }
    return $PMText;
}

function blockedGift($toID, $fromID, &$Err = false) {
    global $master;
    $staffIDs = getStaffIDs();
    $Err=false;

    if (!is_integer_string($fromID)) {
        $Err = 0;
    } elseif (!is_integer_string($toID)) {
        $Err = "This recipient does not exist.";
    } else {
         // staff are never blocked from sending
        if (!isset($staffIDs[$fromID])) {
            // check if sender is on recepients blocked list
            $friendType = $master->db->rawQuery(
                "SELECT Type
                   FROM friends
                  WHERE UserID = ?
                    AND FriendID = ?",
                [$toID, $fromID]
            )->fetchColumn();
            $enabled = $master->db->rawQuery(
                "SELECT Enabled
                   FROM users_main
                  WHERE ID = ?",
                [$toID]
            )->fetchColumn();
            if ($friendType == 'blocked')
                $Err = "This user cannot receive Gifts from you.";
            else {
                // check recepients blockPM setting
                $blockGifts = $master->db->rawQuery(
                    "SELECT BlockGifts
                       FROM users_info
                      WHERE UserID = ?",
                    [$toID]
                )->fetchColumn();
                if ($blockGifts == 2) {
                    // all users are blocked to this recepient
                    $Err = "This user cannot receive Gifts from you.";
                } elseif ($blockGifts == 1 && $friendType != 'friends') {
                    // non friends are blocked to this recepient
                    $Err = "This user cannot receive Gifts from you.";
                } elseif ($enabled != 1) {
                    // Disabled users cannot receive gifts
                    $Err = "This user cannot receive Gifts from you.";
                }
            }
        }
    }
    return $Err !== false;
}
