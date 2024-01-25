<?php
//******************************************************************************//
//
//******************************************************************************//

authorize();

enforce_login();

if (!check_perms('users_edit_badges')) {
    error(403);
}

$GroupID = (int) $_POST['groupid'];
if (!$GroupID) error(0);
//$AddBadges = $_POST['addbadge'];
//if (!is_array($AddBadges)) error(0);

$BadgeID = (int) $_POST['addbadge'];
if (!$BadgeID) error(0);

list($GName, $GDescription) = $master->db->rawQuery(
    "SELECT Name,
            Comment
       FROM groups
      WHERE ID = ?",
    [$GroupID]
)->fetch(\PDO::FETCH_NUM);
if ($master->db->foundRows() == 0) error(0);

list($Badge, $Rank, $Name, $Image) = $master->db->rawQuery(
    "SELECT Badge,
            Rank,
            Title,
            Image
       FROM badges
      WHERE ID = ?",
    [$BadgeID]
)->fetch(\PDO::FETCH_NUM);
if ($master->db->foundRows() == 0) error(0);

$userIDs = $master->db->rawQuery(
    "SELECT UserID
       FROM users_groups
      WHERE GroupID = ?
        AND UserID NOT IN
            (
                 SELECT DISTINCT u2.ID
                   FROM users_main AS u2
                   JOIN users_badges AS ub ON u2.ID = ub.UserID
                   JOIN badges AS b ON b.ID=ub.BadgeID
                  WHERE ub.BadgeID = ?
                     OR (b.Badge = ? AND b.Rank >= ?)
            )",
    [$GroupID, $BadgeID, $Badge, $Rank]
)->fetchAll(\PDO::FETCH_COLUMN);
$CountUsers = count($userIDs);

if ($CountUsers > 0) {
    $Description = display_str($_POST['addbadge' . $BadgeID]);
    $inQuery = implode(', ', array_fill(0, count($userIDs), '?'));
    $sqltime = sqltime();
    $comment = "{$sqltime} - Received Award {$Name} {$Description} Given to all members of [url=/groups.php?groupid={$GroupID}]{$GName} group[/url]\n";
    $master->db->rawQuery(
        "UPDATE users_info
            SET AdminComment = CONCAT(?, AdminComment)
          WHERE UserID IN ({$inQuery})",
        [$comment, ...$userIDs]
    );

    $valuesQuery = implode(', ', array_fill(0, count($userIDs), '(?, ?, ?)'));
    $params = [];
    foreach ($userIDs as $userID) {
        $params = array_merge($params, [$userID, $BadgeID, $Description]);
    }
    $master->db->rawQuery(
        "INSERT INTO users_badges (UserID, BadgeID, Description)
              VALUES {$valuesQuery}",
        $params
    );

    // remove lower ranked badges of same badge set
    $params = $userIDs;
    $params[] = $Badge;
    $params[] = $Rank;
    $master->db->rawQuery(
        "DELETE ub
           FROM users_badges AS ub
      LEFT JOIN badges AS b ON b.ID=ub.BadgeID
          WHERE ub.UserID IN ({$inQuery})
            AND b.Badge = ?
            AND b.Rank < ?",
        $params
    );

    foreach ($userIDs as $userID) {
        send_pm($userID, 0, "Congratulations you have been awarded the $Name",
                            "[center][br][br][img]/static/common/badges/{$Image}[/img][br][br][size=5][color=white][bg=#0261a3][br]{$Description}[br][br][/bg][/color][/size][/center]");

        $master->cache->deleteValue('user_badges_'.$userID);
        $master->cache->deleteValue('user_badges_'.$userID.'_limit');
    }

    $Log = sqltime() . " - [color=magenta]Mass Award given[/color] by [user]{$activeUser['ID']}[/user] - award: $Name";
    $master->db->rawQuery(
        "UPDATE groups
            SET Log=CONCAT_WS(CHAR(10 using utf8), ?, Log)
          WHERE ID = ?",
        [$Log, $GroupID]
    );
}

header("Location: groups.php?groupid=$GroupID");
