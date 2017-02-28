<?php
function get_shop_items_ufl()
{
    global $Cache, $DB;
    if (($ShopItems = $Cache->get_value('shop_items_ufl')) === false) {
        $DB->query("SELECT ID,
                           Title,
                           Description,
                           Action,
                           Value,
                           Cost
                      FROM bonus_shop_actions
                     WHERE Action = 'ufl' AND Gift = '0'
                  ORDER BY Value DESC");
        $ShopItems = $DB->to_array(false, MYSQLI_BOTH);
        $Cache->cache_value('shop_items_ufl', $ShopItems);
    }

    return $ShopItems;
}

function get_shop_items_other()
{
    global $Cache, $DB;
    if (($ShopItems = $Cache->get_value('shop_items_other')) === false) {
        $DB->query("SELECT ID,
                           Title,
                           Description,
                           Action,
                           Value,
                           Cost
                      FROM bonus_shop_actions
                     WHERE (Action = 'givegb' OR Action = 'givecredits') AND Gift = '0'
                  ORDER BY Sort");
        $ShopItems = $DB->to_array(false, MYSQLI_BOTH);
        $Cache->cache_value('shop_items_other', $ShopItems);
    }

    return $ShopItems;
}

function get_shop_items_gifts()
{
    global $Cache, $DB;
    if (($ShopItems = $Cache->get_value('shop_items_gifts')) === false) {
        $DB->query("SELECT ID,
                           Title,
                           Description,
                           Action,
                           Value,
                           Cost
                      FROM bonus_shop_actions
                     WHERE Gift = '1'
                  ORDER BY Sort");
        $ShopItems = $DB->to_array(false, MYSQLI_BOTH);
        $Cache->cache_value('shop_items_gifts', $ShopItems);
    }

    return $ShopItems;
}

function get_shop_items($UserID)
{
    global $Cache, $DB;
    if (($ShopItems = $Cache->get_value('shop_items')) === false) {
        $DB->query("SELECT
                         ID,
                         Title,
                         Description,
                         Action,
                         Value,
                         Cost
            FROM bonus_shop_actions
                  WHERE Action != 'badge' AND Action != 'ufl' AND Gift = '0'
            ORDER BY Sort");
        $ShopItems = $DB->to_array(false, MYSQLI_BOTH);
        $Cache->cache_value('shop_items', $ShopItems);
    }
    $DB->query("SELECT
                        s.ID,
                        s.Title,
                        s.Description,
                        s.Action,
                        s.Value,
                        b.Cost,
                        b.Image,
                        b.Badge,
                        b.Rank,
                        (SELECT Max(b2.Rank)
                        FROM users_badges AS ub2
                        LEFT JOIN badges AS b2 ON b2.ID=ub2.BadgeID
                        AND ub2.UserID = $UserID
                        WHERE b2.Badge = b.Badge) As MaxRank
                        FROM bonus_shop_actions AS s
                        JOIN badges AS b ON b.ID=s.Value AND Action = 'badge'
                        ORDER BY s.Sort");
  if ($DB->record_count()>0) $ShopItems = array_merge($ShopItems, $DB->to_array(false, MYSQLI_BOTH));
    return $ShopItems;
}

function get_shop_item($ItemID)
{
    global $Cache, $DB;
    $ItemID = (int) $ItemID;
    if (($ShopItem = $Cache->get_value('shop_item_'.$ItemID)) === false) {
        $DB->query("SELECT
                        s.ID,
                        s.Title,
                        s.Description,
                        s.Action,
                        s.Value,
                        IF(Action='badge',b.Cost,s.Cost) AS Cost
             FROM bonus_shop_actions AS s
              LEFT JOIN badges AS b ON b.ID=s.Value
            WHERE s.ID='$ItemID'");
        $ShopItem = $DB->to_array(false, MYSQLI_BOTH);
        $Cache->cache_value('shop_item_'.$ItemID, $ShopItem);
    }

    return $ShopItem[0];
}

function get_user_stats($UserID)
{
    global $Cache, $DB;
    $UserID = (int) $UserID;
    $UserStats = $Cache->get_value('user_stats_'.$UserID);
    if (!is_array($UserStats)) {
        $DB->query("SELECT Uploaded AS BytesUploaded, Downloaded AS BytesDownloaded, RequiredRatio FROM users_main WHERE ID='$UserID'");
        $UserStats = $DB->next_record(MYSQLI_ASSOC);
        $Cache->cache_value('user_stats_'.$UserID, $UserStats, 900);
    }

      return $UserStats;
}

function get_gift_pm()
{
    global $Cache, $DB;
    $PMText = $Cache->get_value('systempm_template_1');
    if ($PMText === false) {
        $DB->query("SELECT Body, Help
                      FROM systempm_templates
                     WHERE ID=1");
        $PMText = $DB->next_record();
        $Cache->cache_value('systempm_template_1', $PMText, 86400);
    }
    return $PMText;
}
