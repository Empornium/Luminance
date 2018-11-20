<?php
enforce_login();
authorize();
if (!check_perms('site_give_specialgift')) {
    error(404);
}

$ClassOptions = [
    'any'                     => "<= {$Classes[SMUT_PEDDLER]['Level']}",
    'Apprentice'              => " = {$Classes[APPRENTICE]['Level']}",
    'Perv or lower'           => "<= {$Classes[PERV]['Level']}",
    'Good Perv or lower'      => "<= {$Classes[GOOD_PERV]['Level']}",
    'Good Perv or higher'     => ">= {$Classes[GOOD_PERV]['Level']}",
    'Sextreme Perv or higher' => ">= {$Classes[SEXTREME_PERV]['Level']}",
];

$RatioOptions = [
    'any'                     => '> 0.0',
    'very low (below 0.5)'    => '< 0.5',
    'low (below 1.0)'         => '< 1.0',
    'good (above 1.0)'        => '> 1.0',
    'excellent (above 5.0)'   => '> 5.0',
];

$CreditOptions = [
  'any'                       => '>= 0',
  'poor (3,000 or less)'      => '< 3000',
  'has some (12,000 or less)' => '< 12000',
  'rich (12,000 or more)'     => '> 12000'
];

$ActivityOptions = [
  'now (within the last hour)'              => '1',
  'today (within the last 24 hours)'        => '24',
  'recently (within the last 3 days)'       => '3*24',
  'not too long ago (within the last week)' => '7*24'
];

/* We should validate these.*/
if (empty($_POST['class']) || !array_key_exists($_POST['class'], $ClassOptions)) {
    $REQUIRED_CLASS    = 'any';
} else {
    $REQUIRED_CLASS    = $_POST['class'];
}
if (empty($_POST['ratio']) || !array_key_exists($_POST['ratio'], $RatioOptions)) {
    $REQUIRED_RATIO    = 'any';
} else {
    $REQUIRED_RATIO    = $_POST['ratio'];
}
if (empty($_POST['credits']) || !array_key_exists($_POST['credits'], $CreditOptions)) {
    $REQUIRED_CREDITS  = '>= 0';
} else {
    $REQUIRED_CREDITS  = $_POST['credits'];
}
if (empty($_POST['activity']) || !array_key_exists($_POST['activity'], $ActivityOptions)) {
    $REQUIRED_ACTIVITY = '1';
} else {
    $REQUIRED_ACTIVITY = $_POST['activity'];
}

$ItemID = empty($_POST['itemid']) ? '' : $_POST['itemid'];
$ShopItem = get_shop_item($ItemID);

if (!empty($ShopItem) && is_array($ShopItem)) {
    list($ItemID, $Title, $Description, $Action, $Value, $Cost) = $ShopItem;
}

$DB->query("SELECT MIN(Level) FROM permissions WHERE DisplayStaff='1'");
list($Max) = $DB->next_record();

$DB->query("SELECT
                um.ID AS UserID
            FROM
                users_main as um
            LEFT JOIN
                permissions AS perm ON um.PermissionID=perm.ID
            WHERE
                perm.Level {$ClassOptions[$REQUIRED_CLASS]}
                AND perm.Level < {$Max}
                AND IFNULL((um.Uploaded / um.Downloaded), ~0) {$RatioOptions[$REQUIRED_RATIO]}
                AND um.Credits {$CreditOptions[$REQUIRED_CREDITS]}
                AND um.LastAccess >= DATE_SUB(NOW(), INTERVAL {$ActivityOptions[$REQUIRED_ACTIVITY]} HOUR)
                AND um.Enabled = '1'
                AND um.ID != {$UserID}");
$Eligible_Users = array_column($DB->to_array(), 'UserID');
if (empty($Eligible_Users)) {
    $master->flasher->error("No users match this criteria");
    header("Location: bonus.php?action=gift&class={$REQUIRED_CLASS}&ratio={$REQUIRED_RATIO}&credits={$REQUIRED_CREDITS}&activity={$REQUIRED_ACTIVITY}");
    die();
}
$OtherID = array_rand($Eligible_Users, 1);
$OtherID = $Eligible_Users[$OtherID];

// Do this now so we get values before the gift.
$DB->query("SELECT
                PermissionID as Current_Class,
                IFNULL((Uploaded / Downloaded), '&infin;') AS Current_Ratio,
                Credits AS Current_Credits,
                LastAccess AS Current_LastAccess
            FROM
                users_main
            WHERE
                ID = {$OtherID}");

list($Current_Class, $Current_Ratio, $Current_Credits, $Current_LastAccess) = $DB->next_record();

$DB->query("SELECT Credits From users_main WHERE ID='$UserID'");
list($Credits) = $DB->next_record();

// again lets not trust the check on the previous page as to whether they can afford it
if ($OtherID && ($Cost <= $Credits)) {
    $UpdateSet = array();
    $UpdateSetOther = array();

    switch ($Action) {  // atm hardcoded in db:  givecredits, givegb, gb, slot, title, badge
        case 'givecredits':
            $CreditsGiven = $Value;

            $Summary = sqltime().' | +'.number_format($Value)." credits | You received a special gift of ".number_format($Value)." credits from an anonymous perv";
            $UpdateSetOther[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
            $UpdateSetOther[]="m.Credits=(m.Credits+'$Value')";

            $Summary = sqltime().' | - '.number_format($Cost)." credits | You gave a special gift of ".number_format($Value)." credits to an anonymous perv";
            $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
            $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";

            $ResultMessage="Your gift has been given and gratefully received.\n\n".
                           "The recipient has the rank of ".$Classes[$Current_Class]['Name']." and a ratio of $Current_Ratio,\n".
                           "he had $Current_Credits credits and was last seen at $Current_LastAccess";

            break;

        case 'givegb':  // no test if user had download to remove as this could violate privacy settings
            $GBsGiven = $Value;

            $Summary = sqltime()." | You received a special gift of -$Value gb from an anonymous perv";
            $UpdateSetOther[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";

            $Summary = sqltime()." | -$Cost credits | You gave a special gift of -$Value gb to an anonymous perv";
            $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
            $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";

            $ValueBytes = get_bytes($Value.'gb');
            $UpdateSetOther[]="m.Downloaded=(m.Downloaded-'$ValueBytes')";

            $ResultMessage="Your gift has been given and gratefully received.\n\n".
                           "The recipient has the rank of ".$Classes[$Current_Class]['Name']." and a ratio of $Current_Ratio,\n".
                           "he had $Current_Credits credits and was last seen at $Current_LastAccess";

            break;

        default:
            $Cost = 0;
            $ResultMessage ="No valid action!";
            break;
    }

    if ($UpdateSetOther) {
        $SET = implode(', ', $UpdateSetOther);
        $sql = "UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID SET $SET WHERE m.ID='$OtherID'";
        $DB->query($sql);
        $master->repos->users->uncache($UserID);
    }

    if ($UpdateSet) {
        $SET = implode(', ', $UpdateSet);
        $sql = "UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID SET $SET WHERE m.ID='$UserID'";
        $DB->query($sql);
        $master->repos->users->uncache($UserID);
    }
}

$PMText = get_gift_pm()['Body'];
$PMText = str_replace('[$1]', $Title, $PMText);
send_pm($OtherID, 0, 'You received a Special Gift', $PMText);

$DB->query("INSERT INTO users_special_gifts (UserID, CreditsSpent, CreditsGiven, GBsGiven, Recipient)
                                    VALUES('$UserID', '$Cost', '$CreditsGiven', '$GBsGiven', '$OtherID')");

$master->flasher->notice($ResultMessage);
header("Location: bonus.php?action=gift&class={$REQUIRED_CLASS}&ratio={$REQUIRED_RATIO}&credits={$REQUIRED_CREDITS}&activity={$REQUIRED_ACTIVITY}");
