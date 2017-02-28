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

$DB->query("SELECT Name, Comment FROM groups WHERE ID=$GroupID");
if ($DB->record_count() == 0) error(0);
list($GName, $GDescription) = $DB->next_record();

$DB->query("SELECT Badge, Rank, Title, Image
              FROM badges
             WHERE ID=$BadgeID");
if ($DB->record_count() == 0) error(0);
list($Badge, $Rank, $Name, $Image) = $DB->next_record();

$DB->query("SELECT UserID FROM users_groups
             WHERE GroupID=$GroupID
               AND UserID NOT IN (SELECT DISTINCT u2.ID
                                        FROM users_main AS u2
                                        JOIN users_badges AS ub ON u2.ID = ub.UserID
                                        JOIN badges AS b ON b.ID=ub.BadgeID
                                       WHERE ub.BadgeID = $BadgeID
                                          OR (b.Badge='$Badge' AND b.Rank>=$Rank))");
$UserIDs = $DB->collect('UserID');
$CountUsers = count($UserIDs);

if ($CountUsers > 0) {

    $Description = db_string(display_str($_POST['addbadge' . $BadgeID]));
    $SQL_IN = implode(',',$UserIDs);
    $DB->query("UPDATE users_info SET AdminComment = CONCAT('".sqltime()." - Received Award ". db_string($Name)." ". db_string($Description).db_string(" Given to all members of [url=/groups.php?groupid=$GroupID]$GName group[/url]") ."\n', AdminComment) WHERE UserID IN ($SQL_IN)");

    $Values = "('".implode("', '".$BadgeID."', '".db_string($Description)."'), ('", $UserIDs)."', '".$BadgeID."', '".db_string($Description)."')";
    $DB->query("INSERT INTO users_badges (UserID, BadgeID, Description) VALUES $Values");

    // remove lower ranked badges of same badge set
    $DB->query("DELETE ub
                      FROM users_badges AS ub
                 LEFT JOIN badges AS b ON b.ID=ub.BadgeID
                     WHERE ub.UserID IN ($SQL_IN)
                       AND b.Badge='$Badge' AND b.Rank<$Rank");

    foreach ($UserIDs as $UserID) {
        send_pm($UserID, 0, "Congratulations you have been awarded the $Name",
                            "[center][br][br][img]http://".SITE_URL.'/'.STATIC_SERVER."common/badges/{$Image}[/img][br][br][size=5][color=white][bg=#0261a3][br]{$Description}[br][br][/bg][/color][/size][/center]");

        $Cache->delete_value('user_badges_'.$UserID);
        $Cache->delete_value('user_badges_'.$UserID.'_limit');
    }

    $Log = sqltime() . " - [color=magenta]Mass Award given[/color] by [user]{$LoggedUser['Username']}[/user] - award: $Name";
    $DB->query("UPDATE groups SET Log=CONCAT_WS( '\n', '$Log', Log) WHERE ID='$GroupID'");
}

header("Location: groups.php?groupid=$GroupID");
