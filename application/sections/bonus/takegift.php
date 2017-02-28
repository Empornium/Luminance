<?php
enforce_login();
authorize();
if ( !check_perms('site_give_specialgift') ) {
    error(404);
}

/* We should validate these.*/
if (empty($_POST['class']) || !in_array($_POST['class'], array("<= ".$Classes[SMUT_PEDDLER]['Level'], "= ".$Classes[APPRENTICE]['Level'], "<= ".$Classes[PERV]['Level'], "<= ".$Classes[GOOD_PERV]['Level'], ">= ".$Classes[GOOD_PERV]['Level'], ">= ".$Classes[SEXTREME_PERV]['Level']))) {
    $REQUIRED_CLASS    = "<= ".$Classes[SMUT_PEDDLER]['Level'];
} else {
    $REQUIRED_CLASS    = $_POST['class'];
}
if (empty($_POST['ratio']) || !in_array($_POST['ratio'], array('> 0.0', '< 0.5', '< 1.0', '> 1.0', '> 5.0'))) {
    $REQUIRED_RATIO    = '> 0.0';
} else {
    $REQUIRED_RATIO    = $_POST['ratio'];
}
if (empty($_POST['credits']) || !in_array($_POST['credits'], array('>= 0', '< 3000', '< 12000', '> 12000'))) {
    $REQUIRED_CREDITS  = '>= 0';
} else {
    $REQUIRED_CREDITS  = $_POST['credits'];
}
if (empty($_POST['last_seen']) || !in_array($_POST['last_seen'], array('1', '24', '3*24', '7*24'))) {
    $REQUIRED_LASTSEEN = '1';
} else {
    $REQUIRED_LASTSEEN = $_POST['last_seen'];
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
                perm.Level $REQUIRED_CLASS
                AND perm.Level < $Max
                AND IFNULL((um.Uploaded / um.Downloaded), ~0) $REQUIRED_RATIO
                AND um.Credits $REQUIRED_CREDITS
                AND um.LastAccess >= DATE_SUB(NOW(), INTERVAL $REQUIRED_LASTSEEN HOUR)
                AND um.Enabled = '1'
                AND um.ID != $UserID");
$Eligible_Users = array_column($DB->to_array(), 'UserID');
$OtherID = array_rand($Eligible_Users,1);
$OtherID = $Eligible_Users[$OtherID];
if(empty($OtherID)) {
    error("No users match this criteria");
}

// Do this now so we get values before the gift.
$DB->query("SELECT
                PermissionID as Current_Class,
                IFNULL((Uploaded / Downloaded), '&infin;') AS Current_Ratio,
                Credits AS Current_Credits,
                LastAccess AS Current_LastAccess
            FROM
                users_main
            WHERE
                ID = $OtherID");

list($Current_Class, $Current_Ratio, $Current_Credits, $Current_LastAccess) = $DB->next_record();

$DB->query("SELECT Credits From users_main WHERE ID='$UserID'");
list($Credits) = $DB->next_record();

// again lets not trust the check on the previous page as to whether they can afford it
if ($OtherID && ($Cost <= $Credits)) {

    $UpdateSet = array();
    $UpdateSetOther = array();

    Switch($Action){  // atm hardcoded in db:  givecredits, givegb, gb, slot, title, badge
        case 'givecredits':
            $CreditsGiven = $Value;

            $Summary = sqltime().' | +'.ucfirst(number_format($Value)." credits | You received a special gift of ".number_format($Value)." credits from an anonymous perv");
            $UpdateSetOther[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
            $UpdateSetOther[]="m.Credits=(m.Credits+'$Value')";

            $Summary = sqltime().' | - '.ucfirst(number_format($Cost)." credits | You gave a special gift of ".number_format($Value)." credits to an anonymous perv");
            $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";
            $UpdateSet[]="m.Credits=(m.Credits-'$Cost')";

            $ResultMessage="Your gift has been given and gratefully received.\n\n".
                           "The recipient has the rank of ".$Classes[$Current_Class]['Name']." and a ratio of $Current_Ratio,\n".
                           "he had $Current_Credits credits and was last seen at $Current_LastAccess";

            break;

        case 'givegb':  // no test if user had download to remove as this could violate privacy settings
            $GBsGiven = $Value;

            $Summary = sqltime()." | ".ucfirst("you received a special gift of -$Value gb from an anonymous perv");
            $UpdateSetOther[]="i.BonusLog=CONCAT_WS( '\n', '$Summary', i.BonusLog)";

            $Summary = sqltime()." | -$Cost credits | ".ucfirst("you gave a special gift of -$Value gb to an anonymous perv");
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

header("Location: bonus.php?action=msg&". (!empty($ResultMessage) ? "result=" .urlencode($ResultMessage):"")."&retsg");
