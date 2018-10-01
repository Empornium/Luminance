<?php
/*************************************************************************\
//--------------Take moderation -----------------------------------------//

\*************************************************************************/

// Are they being tricky blighters?
if (!$_POST['userid'] || !is_number($_POST['userid'])) {
    error(404);
} elseif (!check_perms('users_mod') && !check_perms('users_add_notes')) {
    error(403);
}
authorize();

include 'functions.php';
use Luminance\Entities\Restriction;
// End checking for moronity

$UserID = $_POST['userid'];

// Variables for database input
$Class = (int) $_POST['Class'];
$GroupPerm = (int) $_POST['GroupPermission'];
$Username = db_string(display_str($_POST['Username']));
$Title = db_string($_POST['Title']);
$AdminComment = db_string(display_str($_POST['AdminComment']));
$Donor = (isset($_POST['Donor']))? 1 : 0;
$Visible = (isset($_POST['Visible']))? 1 : 0;
$Invites = (int) $_POST['Invites'];
$SupportFor = db_string(display_str($_POST['SupportFor']));
$Pass = db_string($_POST['ChangePassword']);
$Pass2 = db_string($_POST['ChangePassword2']);
$Warned = (isset($_POST['Warned']))? 1 : 0;

$HasDucky = (isset($_POST['Ducky']))? 1 : 0;
$AddBadges = $_POST['addbadge'];
$DelBadges = $_POST['delbadge'];

$AdjustUpValue = ($_POST['adjustupvalue']  == "" ? 0 : $_POST['adjustupvalue']);
if ( isset($AdjustUpValue) && $AdjustUpValue[0]=='+') $AdjustUpValue = substr($AdjustUpValue, 1);
if (is_numeric($AdjustUpValue)) {
    $ByteMultiplier = isset($_POST['adjustup']) ? strtolower($_POST['adjustup']) : 'kb';
    $AdjustUpValue = get_bytes($AdjustUpValue.$ByteMultiplier);
} else {
    $AdjustUpValue = 0;
}

$AdjustDownValue = ($_POST['adjustdownvalue']  == "" ? 0 : $_POST['adjustdownvalue']);
if ( isset($AdjustDownValue) && $AdjustDownValue[0]=='+') $AdjustDownValue = substr($AdjustDownValue, 1);
if (is_numeric($AdjustDownValue)) {
    $ByteMultiplier = isset($_POST['adjustdown']) ? strtolower($_POST['adjustdown']) : 'kb';
    $AdjustDownValue = get_bytes($AdjustDownValue.$ByteMultiplier);
} else {
    $AdjustDownValue = 0;
}
// if we use is_number here (a better function really) we get errors with integer overflow with >2b bytes
if (!is_numeric($AdjustUpValue) || !is_numeric($AdjustDownValue)) {
    error(0);
}

$AdjustCreditsValue = ($_POST['adjustcreditsvalue']  == "" ? 0 : $_POST['adjustcreditsvalue']);
if ( isset($AdjustCreditsValue) && $AdjustCreditsValue[0]=='+') $AdjustCreditsValue = substr($AdjustCreditsValue, 1);
if (!is_numeric($AdjustCreditsValue)) {
    error(0);
}

$FLTokens = (int) $_POST['FLTokens'];
$BonusCredits = (float) $_POST['BonusCredits'];
$PersonalFreeLeech = (int) $_POST['PersonalFreeLeech'];
$PersonalDoubleseed = (int) $_POST['PersonalDoubleseed'];

$ExtendWarning = (int) $_POST['ExtendWarning'];
$UserReason = $_POST['UserReason'];
$SuppressConnPrompt = (isset($_POST['ConnCheck']))? 1 : 0;

$CanLeech = (isset($_POST['CanLeech'])) ? 1 : 0;

$RestrictedForums = trim($_POST['RestrictedForums']);
$PermittedForums = trim($_POST['PermittedForums']);

$EnableUser = (int) $_POST['UserStatus'];
$ResetRatioWatch = (isset($_POST['ResetRatioWatch']))? 1 : 0;
$ResetPasskey = (isset($_POST['ResetPasskey']))? 1 : 0;
$ResetAuthkey = (isset($_POST['ResetAuthkey']))? 1 : 0;
$SendHackedMail = (isset($_POST['SendHackedMail']))? 1 : 0;
if ($SendHackedMail && !empty($_POST['HackedEmail'])) {
    $HackedEmail = $_POST['HackedEmail'];
    $EnableUser = 2;  // automatically disable user
} else {
    $SendHackedMail = false;
}
$SendConfirmMail = (isset($_POST['SendConfirmMail']))? 1 : 0;
if ($SendConfirmMail && !empty($_POST['ConfirmEmail'])) {
    $ConfirmEmail = $_POST['ConfirmEmail'];
} else {
    $SendConfirmMail = false;
}
$MergeStatsFrom = db_string($_POST['MergeStatsFrom']);
$Reason = db_string($_POST['Reason']);

// Get user info from the database
$DB->query("SELECT
    m.Email,
    m.PermissionID,
    p.Level AS Class,
    m.Title,
    m.Enabled,
    m.Uploaded,
    m.Downloaded,
    m.Invites,
    m.can_leech,
    m.Visible,
    m.track_ipv6,
    i.AdminComment,
    m.torrent_pass,
    i.Donor,
    i.SupportFor,
    i.RestrictedForums,
    i.PermittedForums,
    i.SuppressConnPrompt,
    m.RequiredRatio,
    m.FLTokens,
    m.personal_freeleech,
    m.personal_doubleseed,
    i.RatioWatchEnds,
    SHA1(i.AdminComment) AS CommentHash,
    m.Credits,
    m.GroupPermissionID,
    ta.Ducky,
    ta.TorrentID AS DuckyTID
    FROM users_main AS m
    JOIN users_info AS i ON i.UserID = m.ID
    LEFT JOIN permissions AS p ON p.ID=m.PermissionID
    LEFT JOIN torrents_awards AS ta ON ta.UserID=m.ID
    WHERE m.ID = $UserID");

if ($DB->record_count() == 0) { // If user doesn't exist
    header("Location: log.php?search=User+".$UserID);
}

$Cur = $DB->next_record(MYSQLI_ASSOC, false);
if ($_POST['comment_hash'] != $Cur['CommentHash']) {
    error("Somebody else has moderated this user since you loaded it.  Please go back and refresh the page.");
}

$User = $master->repos->users->load($UserID);

//NOW that we know the class of the current user, we can see if one staff member is trying to hax0r us.
if (!check_perms('users_mod', $Cur['Class']) && !check_perms('users_add_notes', $Cur['Class'])) {
    //Son of a fucking bitch
    error(403);
    die();
}

// If we're deleting the user, we can ignore all the other crap

if ($_POST['UserStatus']=="delete" && check_perms('users_delete_users')) {
    write_log("User account ".$UserID." (".$User->Username.") was deleted by ".$LoggedUser['Username']);
    delete_user($UserID);
    header("Location: log.php?search=User+".$UserID);
    die();
}

// User was not deleted. Perform other stuff.

$UpdateSet = array();
$EditSummary = array();

if ($_POST['ResetRatioWatch'] && check_perms('users_edit_reset_keys')) {
    $DB->query("UPDATE users_info SET RatioWatchEnds='0000-00-00 00:00:00', RatioWatchDownload='0', RatioWatchTimes='0' WHERE UserID='$UserID'");
    $EditSummary[]='RatioWatch history reset';
}

if ($_POST['ResetIPHistory'] && check_perms('users_edit_reset_keys')) {

    $DB->query("DELETE FROM users_history_ips WHERE UserID='$UserID'");
    $DB->query("UPDATE users SET IPID='0' WHERE ID='$UserID'");
    $DB->query("UPDATE xbt_snatched SET ipv4 = '', ipv6 = '' WHERE uid='$UserID'");
    $DB->query("UPDATE users_history_passwords SET ChangerIP = '' WHERE UserID = ".$UserID);
    $EditSummary[]='IP history cleared';
}

if ($_POST['ResetEmailHistory'] && check_perms('users_edit_reset_keys')) {
    $DB->query("DELETE FROM emails WHERE UserID='$UserID'");

    $newEmail = $this->master->emailManager->newEmail($UserID, $Email);
    $newEmail->setFlags(Email::VALIDATED);
    $newEmail->setFlags(Email::IS_DEFAULT);
    if ($_POST['ResetIPHistory']) {
        $newEmail->IPID = 0;
    }
    $master->repos->emails->save($newEmail);
    $user = $master->repos->users->load($UserID);
    $user->EmailID = $newEmail->ID;
    $master->repos->users->save($user);

    $EditSummary[]='Email history cleared';
}

if ($_POST['ResetSnatchList'] && check_perms('users_edit_reset_keys')) {
    $DB->query("SELECT fid FROM xbt_snatched WHERE uid='$UserID'");
    $TorrentIDs = $DB->collect('TorrentID');
    $DB->query("DELETE FROM xbt_snatched WHERE uid='$UserID'");
    $EditSummary[]='Snatch List cleared';
    foreach($TorrentIDs as $TorrentID) {
        $Cache->delete_value("users_torrents_snatched_{$UserID}_{$TorrentID}");
    }
}

if ($_POST['ResetDownloadList'] && check_perms('users_edit_reset_keys')) {
    $DB->query("SELECT TorrentID FROM users_downloads WHERE UserID='$UserID'");
    $TorrentIDs = $DB->collect('TorrentID');
    $DB->query("DELETE FROM users_downloads WHERE UserID='$UserID'");
    $EditSummary[]='Download List cleared';
    foreach($TorrentIDs as $TorrentID) {
        $Cache->delete_value("users_torrents_grabbed_{$UserID}_{$TorrentID}");
    }
}

if ((($_POST['ResetSession'] || $_POST['LogOut']) && check_perms('users_logout')) || ($SendHackedMail && check_perms('users_disable_any'))) {
    $master->repos->users->uncache($UserID);

    $EditSummary[]='reset user cache';

    if ($_POST['LogOut'] || $SendHackedMail) {
        $DB->query("SELECT ID FROM sessions WHERE UserID='$UserID'");
        while (list($SessionID) = $DB->next_record()) {
            $Cache->delete_value('_entity_Session_'.$SessionID);
        }

        $DB->query("DELETE FROM sessions WHERE UserID='$UserID'");
        $EditSummary[]='forcibly logged out user';
    }
}

// Start building SQL query and edit summary
if ($Classes[$Class]['Level']!=$Cur['Class'] && (
    ($Classes[$Class]['Level'] < $LoggedUser['Class'] && check_perms('users_promote_below', $Cur['Class']))
    || ($Classes[$Class]['Level'] <= $LoggedUser['Class'] && check_perms('users_promote_to', $Cur['Class']-1)))) {
    if ($Class != 0) {
        $UpdateSet[]="PermissionID='$Class'";
        $EditSummary[]="Class changed to [b][color=".str_replace(" ", "", $Classes[$Class]['Name'])."]" .make_class_string($Class).'[/color][/b]';

        $DB->query("SELECT DISTINCT DisplayStaff FROM permissions WHERE ID = $Class OR ID = ".$ClassLevels[$Cur['Class']]['ID']);
        if ($DB->record_count() == 2) {
            if ($Classes[$Class]['Level'] < $Cur['Class']) {
                $SupportFor = '';
            }
            $ClearStaffIDCache = true;
        }
    }
}

if($GroupPerm!=$Cur['GroupPermissionID'] &&
        (check_perms('admin_manage_permissions',  $Cur['Class']) || check_perms('users_group_permissions',  $Cur['Class']) )) {

      if ($GroupPerm!=0) {
          $DB->query("SELECT Name FROM permissions WHERE ID='$GroupPerm' AND IsUserClass='0'");
          if ($DB->record_count() > 0) {
              list($PermName) = $DB->next_record(MYSQLI_NUM);
          } else
              error("Input Error: Cound not find GroupPerm with ID='$GroupPerm'");
      } else {
          $PermName = 'none';
      }
      $UpdateSet[]="GroupPermissionID='$GroupPerm'";
      $EditSummary[]="group permissions changed to [b]{$PermName}[/b]";
}

// We must use $_POST['Username'] because $Username is escaped,
// which causes issues with old, invalid usernames (i.e. with quotes and whatnot)
if ($_POST['Username'] !== $User->Username && check_perms('users_edit_usernames', $Cur['Class']-1)) {
    if (!preg_match('/^[A-Za-z0-9_\-\.\!]{1,20}$/i', $Username)) error("You entered an invalid username");
    $DB->query("SELECT ID FROM users_main WHERE Username = '".$Username."' AND ID != $UserID");
    if ($DB->record_count() > 0) {
        list($UsedUsernameID) = $DB->next_record();
        error("Username already in use by <a href='user.php?id=".$UsedUsernameID."'>".$Username."</a>");
        //header("Location: user.php?id=".$UserID);
        //die();
    } elseif($Username != '') {
        $UpdateSet[]="Username='".$Username."'";
        $EditSummary[]="username changed from ".$User->Username." to [b]{$Username}[/b]";
        $User->Username = $Username;
    }
}

if ($Title!=db_string($Cur['Title']) && check_perms('users_edit_titles')) {
    // Using the unescaped value for the test to avoid confusion
      $len = mb_strlen($_POST['Title'], "UTF-8");
    if ($len > 32) {
        error("Title length: $len. Custom titles can be at most 32 characters. (max 128 bytes for multibyte strings)");
    } else {
        $UpdateSet[]="Title='$Title'";
        $EditSummary[]="title changed to $Title";
    }
}

if ($Donor!=$Cur['Donor']  && check_perms('users_give_donor')) {
    $UpdateSet[]="Donor='$Donor'";
    $EditSummary[]=status('donor', ($Donor == 1));
}

if ($SuppressConnPrompt!=$Cur['SuppressConnPrompt']  && check_perms('users_set_suppressconncheck')) {
    $UpdateSet[]="SuppressConnPrompt='$SuppressConnPrompt'";
    $EditSummary[]=status('SuppressConnPrompt', ($SuppressConnPrompt == 1));
}

if ($Visible!=$Cur['Visible']  && check_perms('users_make_invisible')) {
    $UpdateSet[]="Visible='$Visible'";
    $EditSummary[]=status('tracker visibility', ($Visible == 1));
    // factor in IP
    if ($user->IPID == 0) $Visible=0;
    $master->tracker->updateUser($Cur['torrent_pass'], null, $Visible, null);
}

$doDuckyCheck = false; // delay this till later because otherwise the admincomments get messed up
if ($HasDucky=='1' && !$Cur['DuckyTID'] && check_perms('users_edit_badges')) {
    $EditSummary[]=ucfirst("Attempting to give Golden Duck Award...");
    $doDuckyCheck = true;   //award_ducky_check($UserID, 0);
}

if ($HasDucky=='0' && $Cur['DuckyTID'] && check_perms('users_edit_badges')) {
    $EditSummary[]=ucfirst("Removing Golden Duck Award");
    remove_ducky($UserID);
}

if (is_array($AddBadges) && check_perms('users_edit_badges')) {

      foreach ($AddBadges as &$AddBadgeID) {
            $AddBadgeID = (int) $AddBadgeID;
      }
      $SQL_IN = implode(',',$AddBadges);
      $DB->query("SELECT ID, Title, Badge, Rank, Image FROM badges WHERE ID IN ( $SQL_IN ) ORDER BY Badge, Rank DESC");
      $BadgeInfos = $DB->to_array();

      $SQL = ''; $Div = ''; $BadgesAdded = '';
      $Badges = array();
      foreach ($BadgeInfos as $BadgeInfo) {
          list($BadgeID, $Name, $Badge, $Rank, $Image) = $BadgeInfo;

          if (!array_key_exists($Badge, $Badges)) {
              // only the highest rank in any set will be added
              $Badges[$Badge] = $Rank;
              $Tooltip = db_string( display_str($_POST['addbadge'.$BadgeID]) );
              $SQL .= "$Div ('$UserID', '$BadgeID', '$Tooltip')";
              $BadgesAdded .= "$Div $Name";
              $Div = ',';
              send_pm($UserID, 0, "Congratulations you have been awarded the $Name",
                          "[center][br][br][img]/static/common/badges/{$Image}[/img][br][br][size=5][color=white][bg=#0261a3][br]{$Description}[br][br][/bg][/color][/size][/center]");
          }
      }
      $DB->query("INSERT INTO users_badges (UserID, BadgeID, Description) VALUES $SQL");

      foreach ($Badges as $Badge=>$Rank) {
            // remove lower ranked badges of same badge set
            $Badge = db_string($Badge);
            $DB->query("DELETE ub
                              FROM users_badges AS ub
                           JOIN badges AS b ON ub.BadgeID=b.ID
                               AND b.Badge='$Badge' AND b.Rank<$Rank
                             WHERE ub.UserID='$UserID'");
      }

      $Cache->delete_value('user_badges_ids_'.$UserID);
      $Cache->delete_value('user_badges_'.$UserID);
      $Cache->delete_value('user_badges_'.$UserID.'_limit');
      $EditSummary[] = 'Badge'.(count($Badges)>1?'s':'')." added: $BadgesAdded";
}

if (is_array($DelBadges) && check_perms('users_edit_badges')) {

      $Div = '';
      $SQL_IN ='';
      foreach ($DelBadges as $UserBadgeID) { //
            $UserBadgeID = (int) $UserBadgeID;
            $SQL_IN .= "$Div $UserBadgeID";
            $Div = ',';
      }
      $BadgesRemoved = '';
      $Div = '';
      $DB->query("SELECT b.Title
                    FROM users_badges AS ub
                    LEFT JOIN badges AS b ON ub.BadgeID=b.ID
                    WHERE ub.ID IN ( $SQL_IN )");
      while (list($Name)=$DB->next_record()) {
            $BadgesRemoved .= "$Div $Name";
            $Div = ',';
      }
      $DB->query("DELETE FROM users_badges WHERE ID IN ( $SQL_IN )");
      $Cache->delete_value('user_badges_ids_'.$UserID);
      $Cache->delete_value('user_badges_'.$UserID);
      $Cache->delete_value('user_badges_'.$UserID.'_limit');
      $EditSummary[] = 'Badge'.(count($DelBadges)>1?'s':'')." removed: $BadgesRemoved";
}

if ($AdjustUpValue != 0 && ((check_perms('users_edit_ratio') && $UserID != $LoggedUser['ID'])
                        || (check_perms('users_edit_own_ratio') && $UserID == $LoggedUser['ID'])) ){
      $Uploaded = $Cur['Uploaded'] + $AdjustUpValue;
      if ($Uploaded<0) $Uploaded=0;
    $UpdateSet[]="Uploaded='".$Uploaded."'";
    $EditSummary[]="uploaded changed from ".get_size($Cur['Uploaded'])." to ".get_size($Uploaded);
    $Cache->delete_value('user_stats_'.$UserID);
}

if ($AdjustDownValue != 0 && ((check_perms('users_edit_ratio') && $UserID != $LoggedUser['ID'])
                        || (check_perms('users_edit_own_ratio') && $UserID == $LoggedUser['ID']))){
      $Downloaded = $Cur['Downloaded'] + $AdjustDownValue;
      if ($Downloaded<0) $Downloaded=0;
    $UpdateSet[]="Downloaded='".$Downloaded."'";
    $EditSummary[]="downloaded changed from ".get_size($Cur['Downloaded'])." to ".get_size($Downloaded);
    $Cache->delete_value('user_stats_'.$UserID);
}

if ($FLTokens!=$Cur['FLTokens'] && ((check_perms('users_edit_tokens')  && $UserID != $LoggedUser['ID'])
                        || (check_perms('users_edit_own_tokens') && $UserID == $LoggedUser['ID']))) {
    $UpdateSet[]="FLTokens=".$FLTokens;
    $EditSummary[]="Freeleech Tokens changed from ".$Cur['FLTokens']." to ".$FLTokens;
}

// $PersonalFreeLeech 1 is current time.
if ($PersonalFreeLeech != 1 && ($PersonalFreeLeech > 1 || ($PersonalFreeLeech == 0 && $Cur['personal_freeleech'] > sqltime())) &&
   ((check_perms('users_edit_pfl') && $UserID != $LoggedUser['ID']) || (check_perms('users_edit_own_pfl') && $UserID == $LoggedUser['ID']))) {
    if ($PersonalFreeLeech == 0) {
        $time = '0000-00-00 00:00:00';
        $after = 'none';
    } else {
        $time = time_plus( 60*60*$PersonalFreeLeech );
        $after = time_diff($time, 2, false);
    }
    if ($Cur['personal_freeleech'] < sqltime()) {
        $before = 'none';
    } else {
        $before = time_diff($Cur['personal_freeleech'], 2, false);
    }

    $UpdateSet[]="personal_freeleech='$time'";
    $EditSummary[]="Personal Freeleech changed from ".$before." to ".$after;
    //update_tracker('set_personal_freeleech', array('passkey' => $Cur['torrent_pass'], 'time' => strtotime($time)));
    $master->tracker->setPersonalFreeleech($Cur['torrent_pass'], strtotime($time));
}

// $PersonalDoubleseed 1 is current time.
if ($PersonalDoubleseed != 1 && ($PersonalDoubleseed > 1 || ($PersonalDoubleseed == 0 && $Cur['personal_doubleseed'] > sqltime())) &&
   ((check_perms('users_edit_pfl') && $UserID != $LoggedUser['ID']) || (check_perms('users_edit_own_pfl') && $UserID == $LoggedUser['ID']))) {
    if ($PersonalDoubleseed == 0) {
        $time = '0000-00-00 00:00:00';
        $after = 'none';
    } else {
        $time = time_plus( 60*60*$PersonalDoubleseed );
        $after = time_diff($time, 2, false);
    }
    if ($Cur['personal_doubleseed'] < sqltime()) {
        $before = 'none';
    } else {
        $before = time_diff($Cur['personal_doubleseed'], 2, false);
    }

    $UpdateSet[]="personal_doubleseed='$time'";
    $EditSummary[]="Personal Doubleseed changed from ".$before." to ".$after;
    //update_tracker('set_personal_doubleseed', array('passkey' => $Cur['torrent_pass'], 'time' => strtotime($time)));
    $master->tracker->setPersonalDoubleseed($Cur['torrent_pass'], strtotime($time));
}

if ($AdjustCreditsValue != 0 && ((check_perms('users_edit_credits') && $UserID != $LoggedUser['ID'])
                        || (check_perms('users_edit_own_credits') && $UserID == $LoggedUser['ID']))){
    $BonusCredits = $Cur['Credits'] + $AdjustCreditsValue;
    if ($BonusCredits<0) $BonusCredits=0;
    $UpdateSet[]="Credits='".$BonusCredits."'";
    $Creditschange = number_format ($AdjustCreditsValue);
    if ($AdjustCreditsValue>=0) $Creditschange = "+".$Creditschange;
    if ($AdjustCreditsValue[0]=='-') {
        $AdjustCreditsSign = '';
    } else {
        $AdjustCreditsSign = '+';
    }
    if ($Reason) {
        $BonusSummary = sqltime()." | $AdjustCreditsSign$AdjustCreditsValue | {$Reason} by {$LoggedUser['Username']}";
    } else {
        $BonusSummary = sqltime()." | $AdjustCreditsSign$AdjustCreditsValue | Manual change by {$LoggedUser['Username']}";
    }
    $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$BonusSummary', i.BonusLog)";
    $Cache->delete_value('user_stats_'.$UserID);
}

if ($Invites!=$Cur['Invites'] && check_perms('users_edit_invites')) {
    $UpdateSet[]="invites='$Invites'";
    $EditSummary[]="number of invites changed to $Invites";
}

if ($SupportFor!=db_string($Cur['SupportFor']) && (check_perms('admin_manage_fls') || (check_perms('users_mod') && $UserID == $LoggedUser['ID']))) {
    $UpdateSet[]="SupportFor='$SupportFor'";
    $EditSummary[]="first-line support status changed to $SupportFor";
    $Cache->delete_value('fls');
}

if ($RestrictedForums != $Cur['RestrictedForums'] && check_perms('users_mod')) {
    $forumsrestricted = getNumArrayFromString($RestrictedForums);
    $RestrictedForums = implode(',', $forumsrestricted);
    $UpdateSet[]="RestrictedForums='".db_string($RestrictedForums)."'";
    $EditSummary[]="restricted forum(s): ".db_string($RestrictedForums);
}

if ($PermittedForums != $Cur['PermittedForums'] && check_perms('users_mod')) {
    include_once SERVER_ROOT.'/Legacy/sections/forums/functions.php';
    $forumInfo = get_forums_info();
    $forumspermitted = getNumArrayFromString($PermittedForums);
    foreach ($forumspermitted as $key=>$forumid) {
        if ($forumInfo[$forumid]['MinClassCreate'] > $LoggedUser['Class']) {
            unset($forumspermitted[$key]);
        }
    }
    $PermittedForums = implode(',', $forumspermitted);
    $UpdateSet[]="PermittedForums='".db_string($PermittedForums)."'";
    $EditSummary[]="permitted forum(s): ".db_string($PermittedForums);
}

if ($CanLeech!=$Cur['can_leech'] && check_perms('users_disable_any')) {
    $UpdateSet[]="can_leech='$CanLeech'";
    $EditSummary[]=status('leeching', ($CanLeech == 1));
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your leeching privileges have been disabled'), db_string("Your leeching privileges have been disabled. The reason given was: $UserReason."));
    }
    $master->tracker->updateUser($Cur['torrent_pass'], $CanLeech);
}

$privs = [
    ['name' => 'avatar',            'flag' => Restriction::AVATAR,           'key' => 'DisableAvatar',     'permission' => 'users_disable_any'],
    ['name' => 'invites',           'flag' => Restriction::INVITE,           'key' => 'DisableInvite',     'permission' => 'users_disable_any'],
    ['name' => 'posting',           'flag' => Restriction::POST,             'key' => 'DisablePost',       'permission' => 'users_disable_posts'],
    ['name' => 'forum',             'flag' => Restriction::FORUM,            'key' => 'DisableForum',      'permission' => 'users_disable_posts'],
    ['name' => 'tagging',           'flag' => Restriction::TAGGING,          'key' => 'DisableTagging',    'permission' => 'users_disable_any'],
    ['name' => 'upload',            'flag' => Restriction::UPLOAD,           'key' => 'DisableUpload',     'permission' => 'users_disable_any'],
    ['name' => 'PM',                'flag' => Restriction::PM,               'key' => 'DisablePM',         'permission' => 'users_disable_any'],
    ['name' => 'StaffPM',           'flag' => Restriction::STAFFPM,          'key' => 'DisableStaffPM',    'permission' => 'users_disable_any'],
    ['name' => 'report',            'flag' => Restriction::REPORT,           'key' => 'DisableReport',     'permission' => 'users_disable_any'],
    ['name' => 'request',           'flag' => Restriction::REQUEST,          'key' => 'DisableRequest',    'permission' => 'users_disable_any'],
    ['name' => 'forum signature',   'flag' => Restriction::SIGNATURE,        'key' => 'DisableSignature',  'permission' => 'users_disable_any'],
    ['name' => 'torrent signature', 'flag' => Restriction::TORRENTSIGNATURE, 'key' => 'DisableTorrentSig', 'permission' => 'users_disable_any'],
];

$restriction = new Restriction;

foreach ($privs as $priv) {
    $status = isset($_POST[$priv['key']])? TRUE : FALSE;
    if (check_perms($priv['permission']) && $status) {
        $restriction->setFlags($priv['flag']);
    }
}

if (isset($_POST['warn']) && check_perms('users_warn')) {
    $restriction->setFlags(Restriction::WARNED);
    $WarnLength = (int) $_POST['WarnLength'];
    if (!empty($WarnLength) && in_array($WarnLength, [1, 2, 4, 8])) {
        $WarnLength = " for {$WarnLength} week(s)";
    } else {
        $WarnLength = '';
    }
    $WarnReason = trim($_POST['WarnReason']);
    if (!empty($WarnReason)) {
        $WarnReason = "The reason given was: $WarnReason\n\n";
    }
    $restrictions = $restriction->get_restrictions();
    $Extra = "";
    if (!empty($restrictions)){
        $Extra .= "During your warning your access to the following will be restricted:\n";
        $Extra .= implode(', ', $restrictions);
        $Extra .= "\n\n";
    }
    send_pm($UserID,0,db_string('You have received a warning'),
            db_string("You have been warned{$WarnLength}.\n".
                      $WarnReason. $Extra.
                      "[url=/articles.php?topic=rules]Read site rules here.[/url]")
    );
}

if ($restriction->Flags != 0) {
    $restriction->UserID  = $UserID;
    $restriction->StaffID = $LoggedUser['ID'];
    $restriction->Created = new \DateTime();
    if (!empty($_POST['WarnLength'])) {
        $WarnLength = (int) $_POST['WarnLength'];
        $restriction->Expires = new \DateTime("+{$WarnLength} weeks");
    }
    $restriction->Comment = $_POST['WarnComment'];
    if (!isset($_POST['warn'])) {
        $restrictions = $restriction->get_restrictions();
        $restrictions = implode(', ', $restrictions);
        $WarnReason   = trim($_POST['WarnReason']);
        if (!empty($WarnReason)) {
          $WarnReason = "The reason given was: {$WarnReason}.";
        }
        send_pm($UserID, 0, db_string("Your privileges have been disabled"), db_string("Your {$restrictions} privileges have been disabled.\n\n{$WarnReason}"));
    }
    $master->repos->restrictions->save($restriction);
}

if ($EnableUser!=$Cur['Enabled'] && check_perms('users_disable_users')) {
    $EnableStr = 'account '.translateUserStatus($Cur['Enabled']).'->'.translateUserStatus($EnableUser);
    if ($EnableUser == '2') {
        $BanReason = (int) $_POST['ban_reason'];
        if($BanReason<0 || $BanReason>4) $BanReason=1;
        disable_users($UserID, '', $BanReason);
    } elseif ($EnableUser == '1') {
        $Cache->increment('stats_user_count');
        //update_tracker('add_user', array('id' => $UserID, 'passkey' => $Cur['torrent_pass']));
        $master->tracker->addUser($Cur['torrent_pass'], $UserID);
        if (($Cur['Downloaded'] == 0) || ($Cur['Uploaded']/$Cur['Downloaded'] >= $Cur['RequiredRatio'])) {
            $UpdateSet[]="i.RatioWatchEnds='0000-00-00 00:00:00'";
            $CanLeech = 1;
            $UpdateSet[]="m.can_leech='1'";
            $UpdateSet[]="i.RatioWatchDownload='0'";
        } else {
            $EnableStr .= ' (Ratio: '.number_format($Cur['Uploaded']/$Cur['Downloaded'], 2).', RR: '.number_format($Cur['RequiredRatio'], 2).')';
            if ($Cur['RatioWatchEnds'] != '0000-00-00 00:00:00') {
                $UpdateSet[]="i.RatioWatchEnds=NOW()";
                $UpdateSet[]="i.RatioWatchDownload=m.Downloaded";
                $CanLeech = 0;
            }
        }
        $Visible=$Cur['Visible'];
        if ($sser->IPID == 0) $Visible=0;
        $track_ipv6=$Cur['track_ipv6'];
        //Ensure the tracker has the correct settings applied
        $master->tracker->updateUser($Cur['torrent_pass'], $CanLeech, $Visible, $track_ipv6);
        $UpdateSet[]="Enabled='1'";
    }
    $EditSummary[]=$EnableStr;
    $Cache->cache_value('enabled_'.$UserID, $EnableUser, 0);
}

if ($ResetPasskey == 1 && check_perms('users_edit_reset_keys')) {
    $Passkey = db_string(make_secret());
    $UpdateSet[]="torrent_pass='$Passkey'";
    $EditSummary[]="passkey reset";
    $Cache->delete_value('user_'.$Cur['torrent_pass']);
    //MUST come after the case for updating can_leech.

    // Log passkey reset
    $master->security->log->passkeyChange((int) $UserID);

    $DB->query("INSERT INTO users_history_passkeys
            (UserID, OldPassKey, NewPassKey, ChangerIP, ChangeTime) VALUES
            ('$UserID', '".$Cur['torrent_pass']."', '$Passkey', '0.0.0.0', '".sqltime()."')");
    //update_tracker('change_passkey', array('oldpasskey' => $Cur['torrent_pass'], 'newpasskey' => $Passkey));
    $master->tracker->changePasskey($Cur['torrent_pass'], $Passkey);
}

if ($ResetAuthkey == 1 && check_perms('users_edit_reset_keys')) {
    $Authkey = db_string(make_secret());
    $UpdateSet[]="AuthKey='$Authkey'";
    $EditSummary[]="authkey reset";
}

if ($SendHackedMail && check_perms('users_disable_any')) {
    $EditSummary[]="hacked email sent to ".db_string($HackedEmail);

    $email = $HackedEmail;

    $subject = 'Unauthorized account access';
    $email_body = [];
    $email_body['settings'] = $master->settings;

    if ($this->settings->site->debug_mode) {
        $body = $master->tpl->render('email/hacked_account.flash.twig', $email_body);
        $master->flasher->notice($body);
    } else {
        $body = $master->tpl->render('email/hacked_account.email.twig', $email_body);
        $master->emailManager->send_email($email, $subject, $body);
        $master->flasher->notice("An e-mail with further instructions has been sent to the provided address.");
    }
}

if ($SendConfirmMail) {
    $EditSummary[]="confirmation email resent to ".db_string($ConfirmEmail);

    $email = $ConfirmEmail;

    $token = $master->secretary->getExternalToken($email, 'users.register');
    $token = $master->crypto->encrypt(['email' => $email, 'token' => $token], 'default', true);

    $subject = 'New account confirmation';
    $email_body = [];
    $email_body['token']    = $token;
    $email_body['settings'] = $master->settings;
    $email_body['scheme']   = $master->request->ssl ? 'https' : 'http';

    if ($this->settings->site->debug_mode) {
        $body = $master->tpl->render('email/new_registration.flash.twig', $email_body);
        $master->flasher->notice($body);
    } else {
        $body = $master->tpl->render('email/new_registration.email.twig', $email_body);
        $master->emailManager->send_email($email, $subject, $body);
        $master->flasher->notice("An e-mail with further instructions has been sent to the provided address.");
    }
}

if ($MergeStatsFrom && check_perms('users_edit_ratio')) {
    $DB->query("SELECT ID, Uploaded, Downloaded, Credits FROM users_main WHERE Username LIKE '".$MergeStatsFrom."'");
    if ($DB->record_count() > 0) {
        list($MergeID, $MergeUploaded, $MergeDownloaded, $MergeCredits) = $DB->next_record();
        $DB->query("UPDATE users_main AS um JOIN users_info AS ui ON um.ID=ui.UserID SET um.Uploaded = 0, um.Downloaded = 0, um.Credits = 0,
            ui.AdminComment = CONCAT('".sqltime()." - Stats merged into http://".SITE_URL."/user.php?id=".$UserID." (".$User->Username.") by ".$LoggedUser['Username'].
            " - Removed ".get_size($MergeUploaded)." uploaded / ".get_size($MergeDownloaded)." downloaded / ".$MergeCredits." credits\n', ui.AdminComment) WHERE ID = ".$MergeID);
        $UpdateSet[]="Uploaded = Uploaded + '$MergeUploaded'";
        $UpdateSet[]="Downloaded = Downloaded + '$MergeDownloaded'";
        $UpdateSet[]="Credits = Credits + '$MergeCredits'";
        $EditSummary[]="stats merged from http://".SITE_URL."/user.php?id=".$MergeID." (".$MergeStatsFrom.") - Added ".get_size($MergeUploaded)." uploaded / ".get_size($MergeDownloaded)." downloaded / ".$MergeCredits." credits";
        $Cache->delete_value('user_stats_'.$UserID);
        $Cache->delete_value('user_stats_'.$MergeID);
        $master->repos->users->uncache($MergeID);
    }
}

if ($Pass) {
    if (!check_perms('users_edit_password')) error(403);
    if ($Pass !== $Pass2) error("Password1 and Password2 did not match! You must enter the same new password twice to change a users password");
    $master->auth->set_password($UserID, $Pass);
    $EditSummary[]='password reset';

    $master->security->log->passwordChange((int) $UserID);
    $master->repos->users->uncache($UserID);

    $DB->query("SELECT ID FROM sessions WHERE UserID='$UserID'");
    while (list($SessionID) = $DB->next_record()) {
        $Cache->delete_value('_entity_Session_'.$SessionID);
    }

    $DB->query("DELETE FROM sessions WHERE UserID='$UserID'");
}

if (empty($UpdateSet) && empty($EditSummary)) {
    if (!$Reason) {
        if (str_replace("\r", '', $Cur['AdminComment']) != str_replace("\r", '', $AdminComment)) {
            if (check_perms('users_edit_notes')) $UpdateSet[]="AdminComment='$AdminComment'";
        } else {
            header("Location: user.php?id=$UserID");
            die();
        }
    }
}

$master->repos->users->uncache($UserID);

$Summary = '';
// Create edit summary
if (!empty($EditSummary)) {
    $Summary = implode(', ', $EditSummary)." by ".$LoggedUser['Username'];
    $Summary = sqltime().' - '.ucfirst($Summary);

    if ($Reason) {
        $Summary .= "\nReason: ".$Reason;
    }

    $Summary .= "\n".$AdminComment;
} elseif (empty($UpdateSet) && empty($EditSummary) && (check_perms('users_add_notes') || check_perms('users_mod'))) {
    $Summary = sqltime().' - '.'Note added by '.$LoggedUser['Username'].': '.$Reason."\n";
    $Summary .= $AdminComment;
}

if (!empty($Summary)) {
    $UpdateSet[]="AdminComment='$Summary'";
} else {
    $UpdateSet[]="AdminComment='$AdminComment'";
}

// Build query

$SET = implode(', ', $UpdateSet);

$sql = "UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID SET $SET WHERE m.ID='$UserID'";

$master->repos->users->save($User);

// Perform update
$DB->query($sql);

// do this now so it doesnt interfere with previous query
if ($doDuckyCheck === true) award_ducky_check($UserID, 0);

if (isset($ClearStaffIDCache)) {
    $Cache->delete_value('staff_ids');
}

// redirect to user page
header("location: user.php?id=$UserID");

function translateUserStatus($status)
{
    switch ($status) {
        case 0:
            return "Unconfirmed";
        case 1:
            return "Enabled";
        case 2:
            return "Disabled";
        default:
            return $status;
    }
}

function translateLeechStatus($status)
{
    switch ($status) {
        case 0:
            return "Disabled";
        case 1:
            return "Enabled";
        default:
            return $status;
    }
}
