<?php
enforce_login();
authorize();
if (!check_perms('site_give_specialgift')) {
    error(404);
}

$classOptions = [
    'any'                     => "<= {$classes[SMUT_PEDDLER]['Level']}",
    'Apprentice'              => " = {$classes[APPRENTICE]['Level']}",
    'Perv or lower'           => "<= {$classes[PERV]['Level']}",
    'Good Perv or lower'      => "<= {$classes[GOOD_PERV]['Level']}",
    'Good Perv or higher'     => ">= {$classes[GOOD_PERV]['Level']}",
    'Sextreme Perv or higher' => ">= {$classes[SEXTREME_PERV]['Level']}",
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
if (empty($_POST['class']) || !array_key_exists($_POST['class'], $classOptions)) {
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

$Max = $master->db->rawQuery(
    "SELECT MIN(Level)
       FROM permissions
      WHERE DisplayStaff = '1'"
)->fetchColumn();;

$eligibleUsers = $master->db->rawQuery(
    "SELECT um.ID AS UserID
       FROM users_main as um
       JOIN users_wallets AS uw ON um.ID=uw.UserID
  LEFT JOIN permissions AS perm ON um.PermissionID=perm.ID
      WHERE perm.Level {$classOptions[$REQUIRED_CLASS]}
        AND perm.Level < {$Max}
        AND IFNULL((um.Uploaded / um.Downloaded), ~0) {$RatioOptions[$REQUIRED_RATIO]}
        AND uw.Balance {$CreditOptions[$REQUIRED_CREDITS]}
        AND um.LastAccess >= DATE_SUB(NOW(), INTERVAL {$ActivityOptions[$REQUIRED_ACTIVITY]} HOUR)
        AND um.Enabled = '1'
        AND um.ID != ?",
    [$userID]
)->fetchAll(\PDO::FETCH_COLUMN);
if (empty($eligibleUsers)) {
    $master->flasher->error("No users match this criteria");
    header("Location: bonus.php?action=gift&class={$REQUIRED_CLASS}&ratio={$REQUIRED_RATIO}&credits={$REQUIRED_CREDITS}&activity={$REQUIRED_ACTIVITY}");
    die();
}
$OtherID = $eligibleUsers[array_rand($eligibleUsers)];

// Do this now so we get values before the gift.
$nextRecord = $master->db->rawQuery(
    "SELECT pm.Name,
            IFNULL((um.Uploaded / um.Downloaded), '&infin;') AS Current_Ratio,
            uw.balance AS Current_Credits,
            um.LastAccess AS Current_LastAccess
       FROM users_main as um
       JOIN users_wallets AS uw ON um.ID=uw.UserID
       JOIN permissions as pm ON pm.ID = um.PermissionID
      WHERE um.ID = ?",
    [$OtherID]
)->fetch(\PDO::FETCH_NUM);

list($Class_Name, $Current_Ratio, $Current_Credits, $Current_LastAccess) = $nextRecord;

$wallet = $master->repos->userWallets->get('UserID = ?', [$activeUser['ID']]);

$CreditsGiven = 0;
$GBsGiven = 0;

// again lets not trust the check on the previous page as to whether they can afford it
if ($OtherID && ($Cost <= $wallet->Balance)) {

    $sender = $master->repos->users->load($userID);
    $receiver = $master->repos->users->load($OtherID);

    Switch($Action) {  // atm hardcoded in db:  givecredits, givegb, gb, slot, title, badge
        case 'givecredits':
            $CreditsGiven = $Value;
            $GBsGiven = 0;

            $Summary = ' | +'.number_format($Value)." credits | You received a special gift of ".number_format($Value)." credits from an anonymous perv";
            $receiver->wallet->adjustBalance($Value);
            $receiver->wallet->addLog($Summary);

            $Summary = ' | - '.number_format($Cost)." credits | You gave a special gift of ".number_format($Value)." credits to an anonymous perv";
            $sender->wallet->adjustBalance(-$Cost);
            $sender->wallet->addLog($Summary);

            $ResultMessage="Your gift has been given and gratefully received.\n\n".
                           "The recipient has the rank of $Class_Name and a ratio of $Current_Ratio,\n".
                           "he had $Current_Credits credits and was last seen at $Current_LastAccess";

            break;

        case 'givegb':  // no test if user had download to remove as this could violate privacy settings
            $GBsGiven = $Value;
            $CreditsGiven = 0;

            $Summary = " | You received a special gift of -$Value gb from an anonymous perv";
            $receiver->wallet->addLog($Summary);

            $Summary = " | -$Cost credits | You gave a special gift of -$Value gb to an anonymous perv";
            $sender->wallet->adjustBalance(-$Cost);
            $sender->wallet->addLog($Summary);

            $ValueBytes = get_bytes($Value.'gb');
            $master->db->rawQuery(
                "UPDATE users_main
                    SET Downloaded = (Downloaded - ?)
                  WHERE ID = ?",
                [$ValueBytes, $OtherID]
            );

            $ResultMessage="Your gift has been given and gratefully received.\n\n".
                           "The recipient has the rank of $Class_Name and a ratio of $Current_Ratio,\n".
                           "he had $Current_Credits credits and was last seen at $Current_LastAccess";

            break;

       default:
           $Cost = 0;
           $ResultMessage ="No valid action!";
           break;
    }
}

$PMText = get_gift_pm()['Body'];
$PMText = str_replace('[$1]', $Title, $PMText);
send_pm($OtherID, 0, 'You received a Special Gift', $PMText);

$master->db->rawQuery(
    "INSERT INTO users_special_gifts (UserID, CreditsSpent, CreditsGiven, GBsGiven, Recipient)
          VALUES(?, ?, ?, ?, ?)",
    [$userID, $Cost, $CreditsGiven, $GBsGiven, $OtherID]
);

$master->flasher->notice($ResultMessage);
header("Location: bonus.php?action=gift&class={$REQUIRED_CLASS}&ratio={$REQUIRED_RATIO}&credits={$REQUIRED_CREDITS}&activity={$REQUIRED_ACTIVITY}");
