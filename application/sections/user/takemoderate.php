<?php
/*************************************************************************\
//--------------Take moderation -----------------------------------------//

\*************************************************************************/


// Are they being tricky blighters?
if (!$_POST['userid'] || !is_number($_POST['userid'])) {
    error(404);
} elseif (!check_perms('users_mod')) {
    error(403);
}
authorize();
// End checking for moronity

$UserID = $_POST['userid'];

// Variables for database input
$Class = (int) $_POST['Class'];
$GroupPerm = (int) $_POST['GroupPermission'];
$Username = db_string(display_str( $_POST['Username']));
$Title = db_string($_POST['Title']);
$AdminComment = db_string(display_str($_POST['AdminComment']));
$Donor = (isset($_POST['Donor']))? 1 : 0;
$Visible = (isset($_POST['Visible']))? 1 : 0;
$Invites = (int) $_POST['Invites'];
$SupportFor = db_string(display_str($_POST['SupportFor']));
$Pass = db_string($_POST['ChangePassword']);
$Pass2 = db_string($_POST['ChangePassword2']);
$Email = db_string($_POST['ChangeEmail']);
$Warned = (isset($_POST['Warned']))? 1 : 0;

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

$FLTokens = (int) $_POST['FLTokens'];
$BonusCredits = (float) $_POST['BonusCredits'];
$PersonalFreeLeech = (int) $_POST['PersonalFreeLeech'];

$WarnLength = (int) $_POST['WarnLength'];
$ExtendWarning = (int) $_POST['ExtendWarning'];
$WarnReason = $_POST['WarnReason'];
$UserReason = $_POST['UserReason'];
$SuppressConnPrompt = (isset($_POST['ConnCheck']))? 1 : 0;

$DisableAvatar = (isset($_POST['DisableAvatar']))? 1 : 0;
$DisableInvites = (isset($_POST['DisableInvites']))? 1 : 0;
$DisablePosting = (isset($_POST['DisablePosting']))? 1 : 0;
$DisableForums = (isset($_POST['DisableForums']))? 1 : 0;
$DisableTagging = (isset($_POST['DisableTagging']))? 1 : 0;
$DisableUpload = (isset($_POST['DisableUpload']))? 1 : 0;
$DisablePM = (isset($_POST['DisablePM']))? 1 : 0;
$DisableIRC = (isset($_POST['DisableIRC']))? 1 : 0;
$DisableRequests = (isset($_POST['DisableRequests']))? 1 : 0;
$DisableLeech = (isset($_POST['DisableLeech'])) ? 0 : 1;
$DisableSig = (isset($_POST['DisableSignature']))? 1 : 0;
$DisableTorrentSig = (isset($_POST['DisableTorrentSig']))? 1 : 0;

$RestrictedForums = db_string(trim($_POST['RestrictedForums']));
$PermittedForums = db_string(trim($_POST['PermittedForums']));
$EnableUser = (int) $_POST['UserStatus'];
$ResetRatioWatch = (isset($_POST['ResetRatioWatch']))? 1 : 0;
$ResetPasskey = (isset($_POST['ResetPasskey']))? 1 : 0;
$ResetAuthkey = (isset($_POST['ResetAuthkey']))? 1 : 0;
$SendHackedMail = (isset($_POST['SendHackedMail']))? 1 : 0;
if ($SendHackedMail && !empty($_POST['HackedEmail'])) {
    $HackedEmail = $_POST['HackedEmail'];
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

$HeavyUpdates = array();
$LightUpdates = array();

// Get user info from the database
$DB->query("SELECT
    m.Username,
    m.IP,
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
    i.AdminComment,
    m.torrent_pass,
    i.Donor,
    i.Warned,
    i.SupportFor,
    i.RestrictedForums,
    i.PermittedForums,
    i.SuppressConnPrompt,
    DisableAvatar,
    DisableInvites,
    DisablePosting,
    DisableForums,
    DisableTagging,
    DisableUpload,
    DisablePM,
    DisableIRC,
    DisableRequests,
    DisableSignature,
    DisableTorrentSig,
    m.RequiredRatio,
    m.FLTokens,
    m.personal_freeleech,
    i.RatioWatchEnds,
    SHA1(i.AdminComment) AS CommentHash,
    m.Credits,
    m.GroupPermissionID
    FROM users_main AS m
    JOIN users_info AS i ON i.UserID = m.ID
    LEFT JOIN permissions AS p ON p.ID=m.PermissionID
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
if (!check_perms('users_mod', $Cur['Class'])) {
    //Son of a fucking bitch
    error(403);
    die();
}

// lets put an error trap here for the mysterious 'random' error that wipes a users account...
// important: do not remove this without trapping for blank username somewhere else!
if ($Class==0 || $Username=='') {
    ksort($_POST, SORT_FLAG_CASE | SORT_STRING);ksort($Cur, SORT_FLAG_CASE | SORT_STRING);
    $_POST['AdminComment'] = "[spoiler=staff notes]$_POST[AdminComment][/spoiler]";
    $Cur['AdminComment'] = "[spoiler=staff notes]$Cur[AdminComment][/spoiler]";
    $ErrBody = "This is an automated error report sent to all admins+ for an unsolved error - the error was caught and no changes to the user account were made.
        \nPost details:\n" . print_r($_POST, true) . "\n\nCurrent User Info:\n". print_r($Cur, true) ;
    // send the admins a pm with the post & user details... then maybe we can solve this?
    include(SERVER_ROOT.'/sections/staff/functions.php');
    $Admins = get_admins();
    $ToID = array();
    foreach ($Admins as $Admin) {
        list($ID) = $Admin;
        $ToID[]=$ID;
    }
    send_pm($ToID, 0, "Account Wipe Error: id=$UserID", $ErrBody);
    error(0);
}

// If we're deleting the user, we can ignore all the other crap

if ($_POST['UserStatus']=="delete" && check_perms('users_delete_users')) {
    write_log("User account ".$UserID." (".$Cur['Username'].") was deleted by ".$LoggedUser['Username']);

    # Luminance Tables
    $DB->query("DELETE FROM users    WHERE     ID=".$UserID);
    $DB->query("DELETE FROM emails   WHERE UserID=".$UserID);
    $DB->query("DELETE FROM sessions WHERE UserID=".$UserID);

    # Main User Tables
    $DB->query("DELETE FROM users_main WHERE     ID=".$UserID);
    $DB->query("DELETE FROM users_info WHERE UserID=".$UserID);

    # Other User Tables
    $DB->query("DELETE FROM users_badges                 WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_collage_subs           WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_connectable_status     WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_downloads              WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_dupes                  WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_freeleeches            WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_groups                 WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_history_emails         WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_history_ips            WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_history_passkeys       WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_history_passwords      WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_info                   WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_languages              WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_not_cheats             WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_notify_filters         WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_notify_torrents        WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_seedhours_history      WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_slots                  WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_subscriptions          WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_torrent_history        WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_torrent_history_snatch WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_torrent_history_temp   WHERE UserID=".$UserID);
    $DB->query("DELETE FROM users_watch_list             WHERE UserID=".$UserID);

    # Tracker Tables
    $DB->query("DELETE FROM xbt_snatched                 WHERE uid=".$UserID);
    $DB->query("DELETE FROM xbt_files_users              WHERE uid=".$UserID);

    # Cache keys
    $Cache->delete_value('enabled_'                .$UserID);
    $Cache->delete_value('user_stats_'             .$UserID);
    $Cache->delete_value('user_info_heavy_'        .$UserID);
    $Cache->delete_value('notify_filters_'         .$UserID);
    $Cache->delete_value('torrent_user_status_'    .$UserID);
    $Cache->delete_value('staff_pm_new_'           .$UserID);
    $Cache->delete_value('ibox_new_'               .$UserID);
    $Cache->delete_value('notifications_new_'      .$UserID);
    $Cache->delete_value('collage_subs_user_new_'  .$UserID);
    $Cache->delete_value('user_peers_'             .$UserID);
    $Cache->delete_value('user_langs_'             .$UserID);
    $Cache->delete_value('bookmarks_torrent_'      .$UserID);
    $Cache->delete_value('user_tokens_'            .$UserID);
    $Cache->delete_value('user_torrents_snatched_' .$UserID);
    $Cache->delete_value('user_torrents_grabbed_'  .$UserID);

    $master->repos->users->uncache($UserID);

    update_tracker('remove_user', array('passkey' => $Cur['torrent_pass']));

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
    $DB->query("UPDATE users_main SET IP='127.0.0.1' WHERE ID='$UserID'");
    $DB->query("UPDATE xbt_snatched SET IP = '' WHERE uid='$UserID'");
    $DB->query("UPDATE users_history_passwords SET ChangerIP = '' WHERE UserID = ".$UserID);
    $EditSummary[]='IP history cleared';
}

if ($_POST['ResetEmailHistory'] && check_perms('users_edit_reset_keys')) {
    $DB->query("DELETE FROM users_history_emails WHERE UserID='$UserID'");
    if ($_POST['ResetIPHistory']) {
        $DB->query("INSERT INTO users_history_emails (UserID, Email, Time, IP) VALUES ('$UserID','$Username@".SITE_URL."','0000-00-00 00:00:00','127.0.0.1')");
    } else {
        $DB->query("INSERT INTO users_history_emails (UserID, Email, Time, IP) VALUES ('$UserID','$Username@".SITE_URL."','0000-00-00 00:00:00','".$Cur['IP']."')");
    }
    $DB->query("UPDATE users_main SET Email='$Username@".SITE_URL."' WHERE ID='$UserID'");
    $EditSummary[]='Email history cleared';
}

if ($_POST['ResetSnatchList'] && check_perms('users_edit_reset_keys')) {
    $DB->query("DELETE FROM xbt_snatched WHERE uid='$UserID'");
    $EditSummary[]='Snatch List cleared';
    $Cache->delete_value('users_torrents_snatched_' . $UserID);
}

if ($_POST['ResetDownloadList'] && check_perms('users_edit_reset_keys')) {
    $DB->query("DELETE FROM users_downloads WHERE UserID='$UserID'");
    $EditSummary[]='Download List cleared';
    $Cache->delete_value('users_torrents_grabbed_' . $UserID);
}

if (($_POST['ResetSession'] || $_POST['LogOut']) && check_perms('users_logout')) {
    $master->repos->users->uncache($UserID);

    $EditSummary[]='reset user cache';

    if ($_POST['LogOut']) {
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
    $UpdateSet[]="PermissionID='$Class'";
    $EditSummary[]="Class changed to [b][color=".str_replace(" ", "", $Classes[$Class]['Name'])."]" .make_class_string($Class).'[/color][/b]';
    $LightUpdates['PermissionID']=$Class;

    $DB->query("SELECT DISTINCT DisplayStaff FROM permissions WHERE ID = $Class OR ID = ".$ClassLevels[$Cur['Class']]['ID']);
    if ($DB->record_count() == 2) {
        if ($Classes[$Class]['Level'] < $Cur['Class']) {
            $SupportFor = '';
        }
        $ClearStaffIDCache = true;
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
      $LightUpdates['GroupPermissionID']=$GroupPerm;
}

if ($Username!=$Cur['Username'] && check_perms('users_edit_usernames', $Cur['Class']-1)) {
    if (!preg_match('/^[A-Za-z0-9_\-\.\!]{1,20}$/i', $Username)) error("You entered an invalid username");
    $DB->query("SELECT ID FROM users_main WHERE Username = '".$Username."' AND ID != $UserID");
    if ($DB->record_count() > 0) {
        list($UsedUsernameID) = $DB->next_record();
        error("Username already in use by <a href='user.php?id=".$UsedUsernameID."'>".$Username."</a>");
        //header("Location: user.php?id=".$UserID);
        //die();
    } else {
        $UpdateSet[]="Username='".$Username."'";
        $User->Username = $Username;
        $EditSummary[]="username changed from ".$Cur['Username']." to [b]{$Username}[/b]";
        $LightUpdates['Username']=$Username;
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
        $LightUpdates['Title']=$_POST['Title'];
    }
}

if ($Donor!=$Cur['Donor']  && check_perms('users_give_donor')) {
    $UpdateSet[]="Donor='$Donor'";
    $EditSummary[]="donor status changed";
    $LightUpdates['Donor']=$Donor;
}

if ($SuppressConnPrompt!=$Cur['SuppressConnPrompt']  && check_perms('users_set_suppressconncheck')) {
    $UpdateSet[]="SuppressConnPrompt='$SuppressConnPrompt'";
    $EditSummary[]="SuppressConnPrompt status changed";
    $HeavyUpdates['SuppressConnPrompt']=$SuppressConnPrompt;
}

if ($Visible!=$Cur['Visible']  && check_perms('users_make_invisible')) {
    $UpdateSet[]="Visible='$Visible'";
    $EditSummary[]="visibility changed";
    $LightUpdates['Visible']=$Visible;
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
                                "[center][br][br][img]http://".SITE_URL.'/'.STATIC_SERVER."common/badges/{$Image}[/img][br][br][size=5][color=white][bg=#0261a3][br]{$Tooltip}[br][br][/bg][/color][/size][/center]");

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
    $HeavyUpdates['FLTokens'] = $FLTokens;
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
    $HeavyUpdates['personal_freeleech'] = $time;
    update_tracker('set_personal_freeleech', array('passkey' => $Cur['torrent_pass'], 'time' => strtotime($time)));
}

if ($BonusCredits!=$Cur['Credits'] && ((check_perms('users_edit_credits') && $UserID != $LoggedUser['ID'])
                        || (check_perms('users_edit_own_credits') && $UserID == $LoggedUser['ID']))) {
        $UpdateSet[]="Credits=".$BonusCredits;
        $Creditschange = $BonusCredits - $Cur['Credits'];
        if ($Creditschange>=0) $Creditschange = "+".number_format ($Creditschange);
        else $Creditschange = number_format ($Creditschange);
        $BonusSummary = sqltime()." | $Creditschange | ".ucfirst("credits set to $BonusCredits from {$Cur['Credits']} by {$LoggedUser['Username']}");
        $UpdateSet[]="i.BonusLog=CONCAT_WS( '\n', '$BonusSummary', i.BonusLog)";

        $EditSummary[]="Bonus Credits changed from ".$Cur['Credits']." to ".$BonusCredits;
      $Cache->delete_value('user_stats_'.$UserID);
        $HeavyUpdates['Credits'] = $BonusCredits;

}

if ($Invites!=$Cur['Invites'] && check_perms('users_edit_invites')) {
    $UpdateSet[]="invites='$Invites'";
    $EditSummary[]="number of invites changed to $Invites";
    $HeavyUpdates['Invites'] = $Invites;
}

if ($Warned == 1 && $Cur['Warned']=='0000-00-00 00:00:00' && check_perms('users_warn')) {
    send_pm($UserID,0,db_string('You have received a warning'),
            db_string("You have been warned for $WarnLength week(s) by [url=/staff.php?]{$LoggedUser['Username']}[/url].\n".
                      "The reason given was: $WarnReason\n\n".
                      "[url=/articles.php?topic=rules]Read site rules here.[/url]"));
    $UpdateSet[]="Warned='".sqltime()."' + INTERVAL $WarnLength WEEK";
    $Msg = "warned for $WarnLength week(s)";
    if ($WarnReason) { $Msg.=" for $WarnReason"; }
    $EditSummary[]= db_string($Msg);
    $LightUpdates['Warned']=time_plus(3600*24*7*$WarnLength);

} elseif ($Warned == 0 && $Cur['Warned']!='0000-00-00 00:00:00' && check_perms('users_warn')) {
    $UpdateSet[]="Warned='0000-00-00 00:00:00'";
    $EditSummary[]="warning removed";
    $LightUpdates['Warned']='0000-00-00 00:00:00';

} elseif ($Warned == 1 && $ExtendWarning!='---' && check_perms('users_warn')) {

    send_pm($UserID,0,db_string('Your warning has been extended'),
            db_string("Your warning has been extended by $ExtendWarning week(s) by [url=/staff.php?]{$LoggedUser['Username']}[/url].\n".
                      "The reason given was: $WarnReason\n\n".
                      "[url=/articles.php?topic=rules]Read site rules here.[/url]"));

    $UpdateSet[]="Warned=Warned + INTERVAL $ExtendWarning WEEK";
    $Msg = "warning extended by $ExtendWarning week(s)";
    if ($WarnReason) { $Msg.=" for $WarnReason"; }
    $EditSummary[]= db_string($Msg);
    $DB->query("SELECT Warned FROM users_info WHERE UserID='$UserID'");
    list($WarnedUntil) = $DB->next_record();
    $LightUpdates['Warned']=$WarnedUntil;
}

if ($SupportFor!=db_string($Cur['SupportFor']) && (check_perms('admin_manage_fls') || (check_perms('users_mod') && $UserID == $LoggedUser['ID']))) {
    $UpdateSet[]="SupportFor='$SupportFor'";
    $EditSummary[]="first-line support status changed to $SupportFor";
    $Cache->delete_value('fls');
}

if ($RestrictedForums != db_string($Cur['RestrictedForums']) && check_perms('users_mod')) {
    $UpdateSet[]="RestrictedForums='$RestrictedForums'";
    $EditSummary[]="restricted forum(s): $RestrictedForums";
}

if ($PermittedForums != db_string($Cur['PermittedForums']) && check_perms('users_mod')) {
    $ForumSet=explode(',',$PermittedForums);
    $ForumList = array();
    foreach ($ForumSet as $ForumID) {
        if ($Forums[$ForumID]['MinClassCreate'] <= $LoggedUser['Class']) {
            $ForumList[] = $ForumID;
        }
    }
    $PermittedForums = implode(',',$ForumSet);
    $UpdateSet[]="PermittedForums='$PermittedForums'";
    $EditSummary[]="permitted forum(s): $PermittedForums";
}

if (empty($RestrictedForums) && empty($PermittedForums)) {
    $HeavyUpdates['CustomForums'] = null;
} else {
    $HeavyUpdates['CustomForums'] = array();
    $Forums = explode(',',$RestrictedForums);
    foreach ($Forums as $Forum) {
        $HeavyUpdates['CustomForums'][$Forum] = 0;
    }
    $Forums = explode(',',$PermittedForums);
    foreach ($Forums as $Forum) {
        $HeavyUpdates['CustomForums'][$Forum] = 1;
    }
}

if ($DisableAvatar!=$Cur['DisableAvatar'] && check_perms('users_disable_any')) {
    $UpdateSet[]="DisableAvatar='$DisableAvatar'";
    $EditSummary[]="avatar status changed";
    $HeavyUpdates['DisableAvatar']=$DisableAvatar;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your avatar privileges have been disabled'),db_string("Your avatar privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($DisableLeech!=$Cur['can_leech'] && check_perms('users_disable_any')) {
    $UpdateSet[]="can_leech='$DisableLeech'";
    $EditSummary[]="leeching status changed (".translateLeechStatus($Cur['can_leech'])." -> ".translateLeechStatus($DisableLeech).")";
    $HeavyUpdates['DisableLeech']=$DisableLeech;
    $HeavyUpdates['CanLeech']=$DisableLeech;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your leeching privileges have been disabled'),db_string("Your leeching privileges have been disabled. The reason given was: $UserReason."));
    }
    update_tracker('update_user', array('passkey' => $Cur['torrent_pass'], 'can_leech' => $DisableLeech));
}

if ($DisableInvites!=$Cur['DisableInvites'] && check_perms('users_disable_any')) {
    $UpdateSet[]="DisableInvites='$DisableInvites'";
    if ($DisableInvites == 1) {
        //$UpdateSet[]="Invites='0'";
        if (!empty($UserReason)) {
            send_pm($UserID, 0, db_string('Your invite privileges have been disabled'),db_string("Your invite privileges have been disabled. The reason given was: $UserReason."));
            }
    }
    $EditSummary[]="invites status changed";
    $HeavyUpdates['DisableInvites']=$DisableInvites;
}

if ($DisablePosting!=$Cur['DisablePosting'] && check_perms('users_disable_posts')) {
    $UpdateSet[]="DisablePosting='$DisablePosting'";
    $EditSummary[]="posting status changed";
    $HeavyUpdates['DisablePosting']=$DisablePosting;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your forum posting privileges have been disabled'),db_string("Your forum posting privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($DisableForums!=$Cur['DisableForums'] && check_perms('users_disable_posts')) {
    $UpdateSet[]="DisableForums='$DisableForums'";
    $EditSummary[]="forums status changed";
    $HeavyUpdates['DisableForums']=$DisableForums;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your forum privileges have been disabled'),db_string("Your forum privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($DisableTagging!=$Cur['DisableTagging'] && check_perms('users_disable_any')) {
    $UpdateSet[]="DisableTagging='$DisableTagging'";
    $EditSummary[]="tagging status changed";
    $HeavyUpdates['DisableTagging']=$DisableTagging;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your tagging privileges have been disabled'),db_string("Your tagging privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($DisableUpload!=$Cur['DisableUpload'] && check_perms('users_disable_any')) {
    $UpdateSet[]="DisableUpload='$DisableUpload'";
    $EditSummary[]="upload status changed";
    $HeavyUpdates['DisableUpload']=$DisableUpload;
    if ($DisableUpload == 1) {
        send_pm($UserID, 0, db_string('Your upload privileges have been disabled'),db_string("Your upload privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($DisablePM!=$Cur['DisablePM'] && check_perms('users_disable_any')) {
    $UpdateSet[]="DisablePM='$DisablePM'";
    $EditSummary[]="PM status changed";
    $HeavyUpdates['DisablePM']=$DisablePM;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your PM privileges have been disabled'),db_string("Your PM privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($DisableIRC!=$Cur['DisableIRC'] && check_perms('users_disable_any')) {
    $UpdateSet[]="DisableIRC='$DisableIRC'";
    $EditSummary[]="IRC status changed";
    $HeavyUpdates['DisableIRC']=$DisableIRC;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your IRC privileges have been disabled'),db_string("Your IRC privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($DisableRequests!=$Cur['DisableRequests'] && check_perms('users_disable_any')) {
    $UpdateSet[]="DisableRequests='$DisableRequests'";
    $EditSummary[]="request status changed";
    $HeavyUpdates['DisableRequests']=$DisableRequests;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your request privileges have been disabled'),db_string("Your request privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($DisableSig!=$Cur['DisableSignature'] && check_perms('users_disable_any')) {
    $UpdateSet[]="DisableSignature='$DisableSig'";
    $EditSummary[]="Signature priviliges status changed";
    $HeavyUpdates['DisableSignature']=$DisableSig;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your Signature privileges have been disabled'),db_string("Your Signature privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($DisableTorrentSig!=$Cur['DisableTorrentSig'] && check_perms('users_disable_any')) {
    $UpdateSet[]="DisableTorrentSig='$DisableTorrentSig'";
    $EditSummary[]="Torrent Signature priviliges status changed";
    $HeavyUpdates['DisableTorrentSig']=$DisableTorrentSig;
    if (!empty($UserReason)) {
        send_pm($UserID, 0, db_string('Your Torrent Signature privileges have been disabled'),db_string("Your Torrent Signature privileges have been disabled. The reason given was: $UserReason."));
    }
}

if ($EnableUser!=$Cur['Enabled'] && check_perms('users_disable_users')) {
    $EnableStr = 'account '.translateUserStatus($Cur['Enabled']).'->'.translateUserStatus($EnableUser);
    if ($EnableUser == '2') {
        $BanReason = (int) $_POST['ban_reason'];
        if($BanReason<0 || $BanReason>4) $BanReason=1;
        disable_users($UserID, '', $BanReason);
    } elseif ($EnableUser == '1') {
        $Cache->increment('stats_user_count');
        update_tracker('add_user', array('id' => $UserID, 'passkey' => $Cur['torrent_pass']));
        if (($Cur['Downloaded'] == 0) || ($Cur['Uploaded']/$Cur['Downloaded'] >= $Cur['RequiredRatio'])) {
            $UpdateSet[]="i.RatioWatchEnds='0000-00-00 00:00:00'";
            $CanLeech = 1;
            $UpdateSet[]="m.can_leech='1'";
            $UpdateSet[]="i.RatioWatchDownload='0'";
        } else {
            $EnableStr .= ' (Ratio: '.number_format($Cur['Uploaded']/$Cur['Downloaded'],2).', RR: '.number_format($Cur['RequiredRatio'],2).')';
            if ($Cur['RatioWatchEnds'] != '0000-00-00 00:00:00') {
                $UpdateSet[]="i.RatioWatchEnds=NOW()";
                $UpdateSet[]="i.RatioWatchDownload=m.Downloaded";
                $CanLeech = 0;
            }
            update_tracker('update_user', array('passkey' => $Cur['torrent_pass'], 'can_leech' => '0'));
        }
        $UpdateSet[]="Enabled='1'";
        $LightUpdates['Enabled'] = 1;
    }
    $EditSummary[]=$EnableStr;
    $Cache->replace_value('enabled_'.$UserID, $EnableUser, 0);
}

if ($ResetPasskey == 1 && check_perms('users_edit_reset_keys')) {
    $Passkey = db_string(make_secret());
    $UpdateSet[]="torrent_pass='$Passkey'";
    $EditSummary[]="passkey reset";
    $HeavyUpdates['torrent_pass']=$Passkey;
    $Cache->delete_value('user_'.$Cur['torrent_pass']);
    //MUST come after the case for updating can_leech.

    $DB->query("INSERT INTO users_history_passkeys
            (UserID, OldPassKey, NewPassKey, ChangerIP, ChangeTime) VALUES
            ('$UserID', '".$Cur['torrent_pass']."', '$Passkey', '0.0.0.0', '".sqltime()."')");
    update_tracker('change_passkey', array('oldpasskey' => $Cur['torrent_pass'], 'newpasskey' => $Passkey));
}

if ($ResetAuthkey == 1 && check_perms('users_edit_reset_keys')) {
    $Authkey = db_string(make_secret());
    $UpdateSet[]="AuthKey='$Authkey'";
    $EditSummary[]="authkey reset";
    $HeavyUpdates['AuthKey']=$Authkey;
}

if ($SendHackedMail && check_perms('users_disable_any')) {
    $EditSummary[]="hacked email sent to ".$HackedEmail;
    send_email($HackedEmail, "Your ".SITE_NAME." account.","Your ".SITE_NAME." account appears to have been compromised. As a security measure we have disabled your account. To resolve this please visit us on IRC.

This is the information to connect to our server:
IRC Server: ".BOT_SERVER."
Port: ".BOT_PORT." (6697 SSL)

Once you are connected to our server you'll need to join our disabled channel.
Type: /join ".BOT_DISABLED_CHAN."

Please visit us soon so we can help you resolve this matter.");
}

if ($SendConfirmMail) {
    $EditSummary[]="confirmation email resent to ".$ConfirmEmail;

    include(SERVER_ROOT.'/classes/class_templates.php');

    // TODO: Use Twig template
    $TPL=NEW TEMPLATE;
    $TPL->open(SERVER_ROOT.'/templates/new_registration.tpl');

    $TPL->set('Username',$_POST['Username']);
    $TPL->set('TorrentKey',$Cur['torrent_pass']);
    $TPL->set('SITE_NAME',SITE_NAME);
    $TPL->set('SITE_URL',SITE_URL);

    send_email($ConfirmEmail,'New account confirmation at '.SITE_NAME,$TPL->get(),'noreply');
}

if ($MergeStatsFrom && check_perms('users_edit_ratio')) {
    $DB->query("SELECT ID, Uploaded, Downloaded, Credits FROM users_main WHERE Username LIKE '".$MergeStatsFrom."'");
    if ($DB->record_count() > 0) {
        list($MergeID, $MergeUploaded, $MergeDownloaded, $MergeCredits) = $DB->next_record();
        $DB->query("UPDATE users_main AS um JOIN users_info AS ui ON um.ID=ui.UserID SET um.Uploaded = 0, um.Downloaded = 0, um.Credits = 0,
            ui.AdminComment = CONCAT('".sqltime()." - Stats merged into http://".SITE_URL."/user.php?id=".$UserID." (".$Cur['Username'].") by ".$LoggedUser['Username'].
            " - Removed ".get_size($MergeUploaded)." uploaded / ".get_size($MergeDownloaded)." downloaded / ".$MergeCredits." credits\n', ui.AdminComment) WHERE ID = ".$MergeID);
        $UpdateSet[]="Uploaded = Uploaded + '$MergeUploaded'";
        $UpdateSet[]="Downloaded = Downloaded + '$MergeDownloaded'";
        $UpdateSet[]="Credits = Credits + '$MergeCredits'";
        $EditSummary[]="stats merged from http://".SITE_URL."/user.php?id=".$MergeID." (".$MergeStatsFrom.") - Added ".get_size($MergeUploaded)." uploaded / ".get_size($MergeDownloaded)." downloaded / ".$MergeCredits." credits";
        $Cache->delete_value('user_stats_'.$UserID);
        $Cache->delete_value('user_stats_'.$MergeID);
        $HeavyUpdates['Credits'] = (isset($HeavyUpdates['Credits']) ? $HeavyUpdates['Credits'] : $Cur['Credits']) + $MergeCredits;
        $master->repos->users->uncache($MergeID);
    }
}

if ($Pass) {
    if (!check_perms('users_edit_password')) error(403);
    if ($Pass !== $Pass2) error("Password1 and Password2 did not match! You must enter the same new password twice to change a users password");
    $master->auth->set_user_password($User, $Pass);
    $EditSummary[]='password reset';

    $master->repos->users->uncache($UserID);

    $DB->query("SELECT ID FROM sessions WHERE UserID='$UserID'");
    while (list($SessionID) = $DB->next_record()) {
        $Cache->delete_value('_entity_Session_'.$SessionID);
    }

    $DB->query("DELETE FROM sessions WHERE UserID='$UserID'");
}

if ($Email) {
    if (!check_perms('users_edit_email')) error(403);

    if ($Email > 255 ) error ("Email field is too long");
    if (!preg_match("/^".EMAIL_REGEX."$/i", $Email)) error("You did not enter a valid email address:<br/>$Email");
    $UpdateSet[]="Email='$Email'";
    $EditSummary[]="email changed from $Cur[Email] to $Email";

    $this->master->emailManager->newEmail($UserID, $Email);
    //This piece of code will update the time of their last email change to the current time *not* the current change.
    $DB->query("UPDATE users_history_emails SET Time='".sqltime()."' WHERE UserID='$UserID' AND Time='0000-00-00 00:00:00'");
    $DB->query("INSERT INTO users_history_emails
                (UserID, Email, Time, IP, ChangedByID) VALUES
                ('$UserID', '$Email', '0000-00-00 00:00:00', '".db_string($_SERVER['REMOTE_ADDR'])."', '$LoggedUser[ID]')");
}

if (empty($UpdateSet) && empty($EditSummary)) {
    if (!$Reason) {
        if (str_replace("\r", '', $Cur['AdminComment']) != str_replace("\r", '', $AdminComment)) {
            if (check_perms('users_admin_notes')) $UpdateSet[]="AdminComment='$AdminComment'";
        } else {
            header("Location: user.php?id=$UserID");
            die();
        }
    } else {
        $EditSummary[]='notes added';
    }
}

$master->repos->users->uncache($UserID);

$Summary = '';
// Create edit summary
if ($EditSummary) {
    $Summary = implode(', ', $EditSummary)." by ".$LoggedUser['Username'];
    $Summary = sqltime().' - '.ucfirst($Summary);

    if ($Reason) {
        $Summary .= "\nReason: ".$Reason;
    }

    $Summary .= "\n".$AdminComment;
} elseif (empty($UpdateSet) && empty($EditSummary) && $Cur['AdminComment']==$_POST['AdminComment']) {
    $Summary = sqltime().' - '.'Comment added by '.$LoggedUser['Username'].': '.$Reason."\n";
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
