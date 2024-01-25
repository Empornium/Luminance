<?php
/*************************************************************************\
//--------------Take moderation -----------------------------------------//

\*************************************************************************/
use Luminance\Entities\Email;

// Are they being tricky blighters?
if (!$_POST['userid'] || !is_integer_string($_POST['userid'])) {
    error(404);
} elseif (!check_perms('users_mod') && !check_perms('users_add_notes')) {
    error(403);
}
authorize();

include 'functions.php';
use Luminance\Entities\Restriction;
use Luminance\Entities\UserHistoryPasskey;
use Luminance\Entities\UserHistoryPassword;

// End checking for moronity

$userID = $_POST['userid'];

// Variables for database input
$class = (int) ($_POST['Class'] ?? null);
$GroupPerm = (int) ($_POST['GroupPermission'] ?? null);
$Username = display_str(trim($_POST['Username']));
$Title = trim($_POST['Title'] ?? '');
$AdminComment = display_str($_POST['AdminComment'] ?? '');
$Donor = (isset($_POST['Donor']))? 1 : 0;
$Visible = (isset($_POST['Visible']))? 1 : 0;
$Invites = (int) $_POST['Invites'];
$SupportFor = display_str($_POST['SupportFor'] ?? '');
$FreshEmail = trim($_POST['FreshEmail']);
$Pass = $_POST['ChangePassword'];
$Pass2 = $_POST['ChangePassword2'];

$HasDucky = (isset($_POST['Ducky']))? 1 : 0;
$AddBadges = ($_POST['addbadge'] ?? null);
$DelBadges = ($_POST['delbadge'] ?? null);

$AdjustUpValue = $_POST['adjustupvalue'] ?? '';
$AdjustUpValue = empty($AdjustUpValue) ? '+0' : $AdjustUpValue;
if ($AdjustUpValue[0]=='+') {
    $AdjustUpValue = substr($AdjustUpValue, 1);
}
if (is_numeric($AdjustUpValue)) {
    $ByteMultiplier = isset($_POST['adjustup']) ? strtolower($_POST['adjustup']) : 'kb';
    $AdjustUpValue = get_bytes($AdjustUpValue.$ByteMultiplier);
} else {
    $AdjustUpValue = 0;
}

$AdjustDownValue = $_POST['adjustdownvalue'] ?? '';
$AdjustDownValue = empty($AdjustDownValue) ? '+0' : $AdjustDownValue;
if ($AdjustDownValue[0]=='+') {
    $AdjustDownValue = substr($AdjustDownValue, 1);
}
if (is_numeric($AdjustDownValue)) {
    $ByteMultiplier = isset($_POST['adjustdown']) ? strtolower($_POST['adjustdown']) : 'kb';
    $AdjustDownValue = get_bytes($AdjustDownValue.$ByteMultiplier);
} else {
    $AdjustDownValue = 0;
}
// if we use is_integer_string here (a better function really) we get errors with integer overflow with >2b bytes
if (!is_numeric($AdjustUpValue) || !is_numeric($AdjustDownValue)) {
    error(0);
}

$AdjustCreditsValue = $_POST['adjustcreditsvalue'] ?? '';
$AdjustCreditsValue = (empty($AdjustCreditsValue)) ? '+0' : $AdjustCreditsValue;
if ( isset($AdjustCreditsValue) && $AdjustCreditsValue[0]=='+') $AdjustCreditsValue = substr($AdjustCreditsValue, 1);
if (!is_numeric($AdjustCreditsValue)) {
    error(0);
}

$FLTokens = (int) $_POST['FLTokens'];
$PersonalFreeLeech = (int) $_POST['PersonalFreeLeech'];
$PersonalDoubleseed = (int) $_POST['PersonalDoubleseed'];

$UserReason = $_POST['UserReason'];
$SuppressConnPrompt = (isset($_POST['ConnCheck']))? 1 : 0;

$CanLeech = (isset($_POST['CanLeech'])) ? 1 : 0;

$RestrictedForums = trim($_POST['RestrictedForums']);
$PermittedForums = trim($_POST['PermittedForums']);

$EnableUser = (int) $_POST['UserStatus'];
$LogOut = (isset($_POST['LogOut']))? 1 : 0;
$ResetSession = (isset($_POST['ResetSession']))? 1 : 0;
$ResetAvatar = (isset($_POST['ResetAvatar']))? 1 : 0;
$ResetDownloadList = (isset($_POST['ResetDownloadList']))? 1 : 0;
$ResetSnatchList = (isset($_POST['ResetSnatchList']))? 1 : 0;
$ResetEmailHistory = (isset($_POST['ResetEmailHistory']))? 1 : 0;
$ResetIPHistory = (isset($_POST['ResetIPHistory']))? 1 : 0;
$ResetRatioWatch = (isset($_POST['ResetRatioWatch']))? 1 : 0;
$ResetPasskey = (isset($_POST['ResetPasskey']))? 1 : 0;
$ResetAuthkey = (isset($_POST['ResetAuthkey']))? 1 : 0;
$SendHackedMail = (isset($_POST['SendHackedMail']))? 1 : 0;
if ($SendHackedMail && !empty($_POST['HackedEmail'])) {
    $HackedEmail = $_POST['HackedEmail'];
    $EnableUser = 2;  // automatically disable user
    $ResetPasskey = 1;
    $ResetAuthkey = 1;
} else {
    $SendHackedMail = false;
    $ResetPasskey = 1;
    $ResetAuthkey = 1;
}
$SendConfirmMail = (isset($_POST['SendConfirmMail']))? 1 : 0;
if ($SendConfirmMail && !empty($_POST['ConfirmEmail'])) {
    $ConfirmEmail = $_POST['ConfirmEmail'];
} else {
    $SendConfirmMail = false;
}
$MergeStatsFrom = $_POST['MergeStatsFrom'];
$Reason = $_POST['Reason'];

$commentHash = $_POST['comment_hash'] ?? null;

// Get user info from the database
$Cur = $master->db->rawQuery(
    "SELECT m.PermissionID,
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
            w.Balance AS Credits,
            m.GroupPermissionID,
            ta.Ducky,
            ta.TorrentID AS DuckyTID
       FROM users_main AS m
       JOIN users_info AS i ON i.UserID = m.ID
       JOIN users_wallets AS w ON w.UserID = m.ID
  LEFT JOIN permissions AS p ON p.ID=m.PermissionID
  LEFT JOIN torrents_awards AS ta ON ta.UserID=m.ID
      WHERE m.ID = ?",
    [$userID]
)->fetch(\PDO::FETCH_ASSOC);

if ($master->db->foundRows() == 0) { // If user doesn't exist
    header("Location: log.php?search=User+".$userID);
}

if ($commentHash != $Cur['CommentHash']) {
    error("Somebody else has moderated this user since you loaded it.  Please go back and refresh the page.");
}

$user = $master->repos->users->load($userID);

//NOW that we know the class of the current user, we can see if one staff member is trying to hax0r us.
if (!check_perms('users_mod', $Cur['Class']) && !check_perms('users_add_notes', $Cur['Class'])) {
    //Son of a fucking bitch
    error(403);
    die();
}

// If we're deleting the user, we can ignore all the other crap

if ($_POST['UserStatus']=="delete" && check_perms('users_delete_users')) {
    write_log("User account ".$userID." (".$user->Username.") was deleted by ".$activeUser['Username']);
    delete_user($userID);
    header("Location: log.php?search=User+".$userID);
    die();
}

// User was not deleted. Perform other stuff.

$UpdateSet = [];
$UpdateData = [];
$EditSummary = [];

if ($ResetRatioWatch && check_perms('users_edit_reset_keys')) {
    $master->db->rawQuery(
        "UPDATE users_info
            SET RatioWatchEnds = '0000-00-00 00:00:00',
                RatioWatchDownload = '0',
                RatioWatchTimes = '0'
          WHERE UserID = ?",
        [$userID]
    );
    $EditSummary[]='RatioWatch history reset';
}

if ($ResetIPHistory && check_perms('users_edit_reset_keys')) {

    $queries = [
        "DELETE FROM users_history_ips WHERE UserID = ?",
        "UPDATE xbt_snatched SET ipv4 = '', ipv6 = '' WHERE uid = ?",
        "UPDATE users_history_passkeys SET IPID = NULL WHERE UserID = ?",
        "UPDATE users_history_passwords SET IPID = NULL WHERE UserID = ?",
    ];
    foreach ($queries as $query) {
        $master->db->rawQuery($query, [$userID]);
    }
    $EditSummary[]='IP history cleared';
}

if ($ResetEmailHistory && check_perms('users_edit_reset_keys')) {
    $master->db->rawQuery("DELETE FROM emails WHERE UserID = ?", [$userID]);
    $master->db->rawQuery("DELETE FROM security_logs WHERE Event LIKE '%@%' AND UserID = ?", [$userID]);

    $newEmail = $this->master->emailManager->newEmail($userID, $FreshEmail);
    $newEmail->setFlags(Email::VALIDATED);
    $newEmail->setFlags(Email::IS_DEFAULT);
    if ($ResetIPHistory) {
        $newEmail->IPID = 0;
    }
    $master->repos->emails->save($newEmail);
    $user->EmailID = $newEmail->ID;
    $master->repos->users->save($user);

    $EditSummary[]='Email history cleared';
}

if ($ResetSnatchList && check_perms('users_edit_reset_keys')) {
    $torrentIDs = $master->db->rawQuery(
        "SELECT fid AS TorrentID
           FROM xbt_snatched
          WHERE uid = ?",
        [$userID]
    )->fetchAll(\PDO::FETCH_COLUMN);
    $master->db->rawQuery("DELETE FROM xbt_snatched WHERE uid = ?", [$userID]);
    $EditSummary[]='Snatch List cleared';
    foreach ($torrentIDs as $torrentID) {
        $master->cache->deleteValue("users_torrents_snatched_{$userID}_{$torrentID}");
    }
}

if ($ResetDownloadList && check_perms('users_edit_reset_keys')) {
    $torrentIDs = $master->db->rawQuery(
        "SELECT TorrentID
           FROM users_downloads
          WHERE UserID = ?",
        [$userID]
    )->fetchAll(\PDO::FETCH_COLUMN);
    $master->db->rawQuery("DELETE FROM users_downloads WHERE UserID = ?", [$userID]);
    $EditSummary[]='Download List cleared';
    foreach ($torrentIDs as $torrentID) {
        $master->cache->deleteValue("users_torrents_grabbed_{$userID}_{$torrentID}");
    }
}

if ($ResetAvatar && check_perms('users_edit_reset_keys')) {
    $master->db->rawQuery(
        "UPDATE users_info
            SET Avatar = ''
          WHERE UserID = ?",
        [$userID]
    );
    $EditSummary[]='Avatar Reset';
}

if ((($ResetSession || $LogOut) && check_perms('users_logout')) || ($SendHackedMail && check_perms('users_disable_any'))) {
    $master->repos->users->uncache($userID);

    $EditSummary[]='reset user cache';

    if ($LogOut || $SendHackedMail) {
        $sessionIDs = $master->db->rawQuery(
            "SELECT ID
               FROM sessions
              WHERE UserID = ?",
            [$userID]
        )->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($sessionIDs as $sessionID) {
            $master->cache->deleteValue("_entity_Session_{$sessionID}");
        }

        $master->db->rawQuery("DELETE FROM sessions WHERE UserID = ?", [$userID]);
        $EditSummary[]='forcibly logged out user';
    }
}

// Start building SQL query and edit summary
if ($classes[$class]['Level']!=$Cur['Class'] && (
    ($classes[$class]['Level'] < $activeUser['Class'] && check_perms('users_promote_below', $Cur['Class']))
    || ($classes[$class]['Level'] <= $activeUser['Class'] && check_perms('users_promote_to', $Cur['Class']-1)))) {
    if ($class != 0) {
        $UpdateSet[]='PermissionID = ?';
        $UpdateData[] = $class;
        $EditSummary[]="Class changed to [b][color=".str_replace(" ", "", $classes[$class]['Name'])."]" .make_class_string($class).'[/color][/b]';

        # ToDo Fix for deleted classes!
        $master->db->rawQuery(
            "SELECT DISTINCT DisplayStaff
               FROM permissions
              WHERE ID IN (?, ?)",
            [$class, $classLevels[$Cur['Class']]['ID']]
        );
        if ($master->db->foundRows() == 2) {
            if ($classes[$class]['Level'] < $Cur['Class']) {
                $SupportFor = '';
            }
            $ClearStaffIDCache = true;
        }
    }
}

if ($GroupPerm!=$Cur['GroupPermissionID'] &&
        (check_perms('admin_manage_permissions',  $Cur['Class']) || check_perms('users_group_permissions',  $Cur['Class']))) {

      if ($GroupPerm!=0) {
          $PermName = $master->db->rawQuery(
              "SELECT Name
                 FROM permissions
                WHERE ID = ?
                  AND IsUserClass = '0'",
              [$GroupPerm]
          )->fetchColumn();
          if ($master->db->foundRows() === 0) {
              error("Input Error: Cound not find GroupPerm with ID='{$GroupPerm}'");
          }
      } else {
          $PermName = 'none';
      }
      $UpdateSet[]='GroupPermissionID = ?';
      $UpdateData[] = $GroupPerm;
      $EditSummary[]="secondary class permissions changed to [b]{$PermName}[/b]";
}

// We must use $_POST['Username'] because $Username is escaped,
// which causes issues with old, invalid usernames (i.e. with quotes and whatnot)
if (trim($_POST['Username']) !== $user->Username && check_perms('users_edit_usernames', $Cur['Class']-1)) {
    $UsedUsernameID = $master->db->rawQuery(
        "SELECT ID
           FROM users
          WHERE Username = ?
            AND ID != ?",
        [$Username, $userID]
    )->fetchColumn();
    if ($master->db->foundRows() > 0) {
        error("Username already in use by <a href='/user.php?id={$UsedUsernameID}'>{$Username}</a>");
        //header("Location: user.php?id=".$userID);
        //die();
    } elseif ($Username != '') {
        $EditSummary[]="username changed from {$user->Username} to [b]{$Username}[/b]";
        $user->Username = $Username;
    }
}

if ($Title != $Cur['Title'] && check_perms('users_edit_titles')) {
    // Using the unescaped value for the test to avoid confusion
      $len = mb_strlen($_POST['Title'], "UTF-8");
    if ($len > 32) {
        error("Title length: $len. Custom titles can be at most 32 characters. (max 128 bytes for multibyte strings)");
    } else {
        $UpdateSet[] = 'Title = ?';
        $UpdateData[] = $Title;
        $EditSummary[] = "title changed to $Title";
    }
}

if ($Donor!=$Cur['Donor']  && check_perms('users_give_donor')) {
    $UpdateSet[] = 'Donor = ?';
    $UpdateData[] = $Donor;
    $EditSummary[] = status('donor', ($Donor == 1));
}

if ($SuppressConnPrompt!=$Cur['SuppressConnPrompt']  && check_perms('users_set_suppressconncheck')) {
    $UpdateSet[] = 'SuppressConnPrompt = ?';
    $UpdateData[] = $SuppressConnPrompt;
    $EditSummary[] = status('SuppressConnPrompt', ($SuppressConnPrompt == 1));
}

if ($Visible!=$Cur['Visible']  && check_perms('users_make_invisible')) {
    $UpdateSet[] = 'Visible = ?';
    $UpdateData[] = $Visible;
    $EditSummary[]=status('tracker visibility', ($Visible == 1));
    // factor in IP
    if ($user->IPID == 0) $Visible=0;
    $master->tracker->updateUser($Cur['torrent_pass'], null, $Visible, null);
}

$doDuckyCheck = false; // delay this till later because otherwise the admincomments get messed up
if ($HasDucky=='1' && !$Cur['DuckyTID'] && check_perms('users_edit_badges')) {
    $EditSummary[]=ucfirst("Attempting to give Golden Duck Award...");
    $doDuckyCheck = true;   //award_ducky_check($userID, 0);
}

if ($HasDucky=='0' && $Cur['DuckyTID'] && check_perms('users_edit_badges')) {
    $EditSummary[]=ucfirst("Removing Golden Duck Award");
    remove_ducky($userID);
}

if (is_array($AddBadges) && check_perms('users_edit_badges')) {

      foreach ($AddBadges as &$AddBadgeID) {
            $AddBadgeID = (int) $AddBadgeID;
      }
      $inQuery = implode(', ', array_fill(0, count($AddBadges), '?'));
      $BadgeInfos = $master->db->rawQuery(
          "SELECT ID, Title, Badge, Rank, Image, Description
             FROM badges
            WHERE ID IN ({$inQuery})
         ORDER BY Badge,
                  Rank DESC",
          $AddBadges
      )->fetchAll(\PDO::FETCH_NUM);

      $SQL = ''; $Div = ''; $BadgesAdded = '';
      $Badges = [];
      $badgeParams = [];
      $valuesQuery = implode(', ', array_fill(0, count($BadgeInfos), '(?, ?, ?)'));
      foreach ($BadgeInfos as $BadgeInfo) {
          list($BadgeID, $Name, $Badge, $Rank, $Image, $Description) = $BadgeInfo;

          if (!array_key_exists($Badge, $Badges)) {
              // only the highest rank in any set will be added
              $Badges[$Badge] = $Rank;
              $Tooltip = display_str($_POST['addbadge'.$BadgeID]);
              $badgeParams = array_merge($badgeParams, [$userID, $BadgeID, $Tooltip]);
              $BadgesAdded .= "$Div $Name";
              $Div = ', ';
              send_pm($userID, 0, "Congratulations you have been awarded the {$Name}",
                          "[center][br][br][img]/static/common/badges/{$Image}[/img][br][br][size=5][color=white][bg=#0261a3][br]{$Description}[br][br][/bg][/color][/size][/center]");
          }
      }
      $master->db->rawQuery("INSERT INTO users_badges (UserID, BadgeID, Description) VALUES {$valuesQuery}", $badgeParams);

      foreach ($Badges as $Badge=>$Rank) {
            // remove lower ranked badges of same badge set
            $master->db->rawQuery(
                "DELETE ub
                   FROM users_badges AS ub
                   JOIN badges AS b ON ub.BadgeID = b.ID
                    AND b.Badge = ?
                    AND b.Rank < ?
                  WHERE ub.UserID = ?",
                [$Badge, $Rank, $userID]
            );
      }

      $master->cache->deleteValue('user_badges_ids_'.$userID);
      $master->cache->deleteValue('user_badges_'.$userID);
      $master->cache->deleteValue('user_badges_'.$userID.'_limit');
      $EditSummary[] = 'Badge'.(count($Badges)>1?'s':'')." added: $BadgesAdded";
}

if (is_array($DelBadges) && check_perms('users_edit_badges')) {
      foreach ($DelBadges as &$UserBadgeID) { //
            $UserBadgeID = (int) $UserBadgeID;
      }
      $inQuery = implode(', ', array_fill(0, count($DelBadges), '?'));
      $BadgesRemoved = $master->db->rawQuery(
          "SELECT b.Title
             FROM users_badges AS ub
        LEFT JOIN badges AS b ON ub.BadgeID = b.ID
            WHERE ub.ID IN ({$inQuery})",
          $DelBadges
      )->fetchAll(\PDO::FETCH_COLUMN);
      $BadgesRemoved = implode(', ', $BadgesRemoved);
      $master->db->rawQuery("DELETE FROM users_badges WHERE ID IN ({$inQuery})", $DelBadges);
      $master->cache->deleteValue('user_badges_ids_'.$userID);
      $master->cache->deleteValue('user_badges_'.$userID);
      $master->cache->deleteValue('user_badges_'.$userID.'_limit');
      $EditSummary[] = 'Badge'.(count($DelBadges)>1?'s':'')." removed: $BadgesRemoved";
}

if ($AdjustUpValue != 0 && ((check_perms('users_edit_ratio') && $userID != $activeUser['ID'])
                        || (check_perms('users_edit_own_ratio') && $userID == $activeUser['ID']))) {
      $Uploaded = $Cur['Uploaded'] + $AdjustUpValue;
      if ($Uploaded<0) $Uploaded=0;
    $UpdateSet[] = 'Uploaded = ?';
    $UpdateData[] = $Uploaded;
    $EditSummary[] = "uploaded changed from ".get_size($Cur['Uploaded'])." to ".get_size($Uploaded);
    $master->cache->deleteValue('user_stats_'.$userID);
}

if ($AdjustDownValue != 0 && ((check_perms('users_edit_ratio') && $userID != $activeUser['ID'])
                        || (check_perms('users_edit_own_ratio') && $userID == $activeUser['ID']))) {
      $Downloaded = $Cur['Downloaded'] + $AdjustDownValue;
      if ($Downloaded<0) $Downloaded=0;
    $UpdateSet[] = 'Downloaded = ?';
    $UpdateData[] = $Downloaded;
    $EditSummary[] = "downloaded changed from ".get_size($Cur['Downloaded'])." to ".get_size($Downloaded);
    $master->cache->deleteValue('user_stats_'.$userID);
}

if ($FLTokens!=$Cur['FLTokens'] && ((check_perms('users_edit_tokens')  && $userID != $activeUser['ID'])
                        || (check_perms('users_edit_own_tokens') && $userID == $activeUser['ID']))) {
    $UpdateSet[] = 'FLTokens = ?';
    $UpdateData[] = $FLTokens;
    $EditSummary[]="Freeleech Tokens changed from ".$Cur['FLTokens']." to ".$FLTokens;
}

// $PersonalFreeLeech 1 is current time.
if ($PersonalFreeLeech != 1 && ($PersonalFreeLeech > 1 || ($PersonalFreeLeech == 0 && $Cur['personal_freeleech'] > sqltime())) &&
   ((check_perms('users_edit_pfl') && $userID != $activeUser['ID']) || (check_perms('users_edit_own_pfl') && $userID == $activeUser['ID']))) {
    if ($PersonalFreeLeech == 0) {
        $time = '0000-00-00 00:00:00';
        $after = 'none';
    } else {
        $time = time_plus(60*60*$PersonalFreeLeech);
        $after = time_diff($time, 2, false);
    }
    if ($Cur['personal_freeleech'] < sqltime()) {
        $before = 'none';
    } else {
        $before = time_diff($Cur['personal_freeleech'], 2, false);
    }

    $UpdateSet[] = 'personal_freeleech = ?';
    $UpdateData[] = $time;
    $EditSummary[] = "Personal Freeleech changed from ".$before." to ".$after;
    $master->tracker->setPersonalFreeleech($Cur['torrent_pass'], strtotime($time));
}

// $PersonalDoubleseed 1 is current time.
if ($PersonalDoubleseed != 1 && ($PersonalDoubleseed > 1 || ($PersonalDoubleseed == 0 && $Cur['personal_doubleseed'] > sqltime())) &&
   ((check_perms('users_edit_pfl') && $userID != $activeUser['ID']) || (check_perms('users_edit_own_pfl') && $userID == $activeUser['ID']))) {
    if ($PersonalDoubleseed == 0) {
        $time = '0000-00-00 00:00:00';
        $after = 'none';
    } else {
        $time = time_plus(60*60*$PersonalDoubleseed);
        $after = time_diff($time, 2, false);
    }
    if ($Cur['personal_doubleseed'] < sqltime()) {
        $before = 'none';
    } else {
        $before = time_diff($Cur['personal_doubleseed'], 2, false);
    }

    $UpdateSet[] = 'personal_doubleseed = ?';
    $UpdateData[] = $time;
    $EditSummary[]="Personal Doubleseed changed from ".$before." to ".$after;
    $master->tracker->setPersonalDoubleseed($Cur['torrent_pass'], strtotime($time));
}

if ($AdjustCreditsValue != 0 && ((check_perms('users_edit_credits') && $userID != $activeUser['ID'])
                        || (check_perms('users_edit_own_credits') && $userID == $activeUser['ID']))) {
    $Creditschange = number_format($AdjustCreditsValue);

    if ($AdjustCreditsValue >= 0) {
        $Creditschange = "+".$Creditschange;
    }

    if ($Reason) {
        $BonusSummary = " | $Creditschange | {$Reason} by {$activeUser['Username']}";
    } else {
        $BonusSummary = " | $Creditschange | Manual change by {$activeUser['Username']}";
    }

    $wallet = $user->wallet;

    $wallet->adjustBalance($AdjustCreditsValue);
    $wallet->addLog($BonusSummary);

    $master->cache->deleteValue('user_stats_'.$userID);
}

if ($Invites != $Cur['Invites'] && check_perms('users_edit_invites')) {
    $UpdateSet[] = 'invites = ?';
    $UpdateData[] = $Invites;
    $EditSummary[] = "number of invites changed to $Invites";
    if ($Cur['Invites'] < $Invites) {
        $master->repos->inviteLogs->userInviteGrant($Reason, $userID, $Invites);
    } else {
        $removedInv = $Cur['Invites'] - $Invites;
        $master->repos->inviteLogs->userInviteRemoval($Reason, $userID, $removedInv);
    }
}

if ($SupportFor != $Cur['SupportFor'] && (check_perms('admin_manage_fls') || (check_perms('users_mod') && $userID == $activeUser['ID']))) {
    $UpdateSet[] = 'SupportFor = ?';
    $UpdateData[] = $SupportFor;
    $EditSummary[] = "first-line support status changed to $SupportFor";
    $master->cache->deleteValue('fls');
}

if ($RestrictedForums != $Cur['RestrictedForums'] && check_perms('users_mod')) {
    $forumsrestricted = getNumArrayFromString($RestrictedForums);
    $RestrictedForums = implode(',', $forumsrestricted);
    $UpdateSet[] = 'RestrictedForums = ?';
    $UpdateData[] = $RestrictedForums;
    $EditSummary[] = "restricted forum(s): ".$RestrictedForums;
}

if ($PermittedForums != $Cur['PermittedForums'] && check_perms('users_mod')) {
    $forumInfo = $master->repos->forums->getForumInfo();
    $forumspermitted = getNumArrayFromString($PermittedForums);
    foreach ($forumspermitted as $key=>$forumid) {
        if ($forumInfo[$forumid]['MinClassCreate'] > $activeUser['Class']) {
            unset($forumspermitted[$key]);
        }
    }
    $PermittedForums = implode(',', $forumspermitted);
    $UpdateSet[] = 'PermittedForums = ?';
    $UpdateData[] = $PermittedForums;
    $EditSummary[] = "permitted forum(s): ".$PermittedForums;
}

if ($CanLeech!=$Cur['can_leech'] && check_perms('users_disable_any')) {
    $UpdateSet[] = 'can_leech = ?';
    $UpdateData[] = $CanLeech;
    $EditSummary[]=status('leeching', ($CanLeech == 1));
    if (!empty($UserReason)) {
        $Subject = "Your leeching privileges have been disabled";
        $Body = "Your leeching privileges have been disabled.\nThe reason given was: $UserReason.";
        send_pm($userID, 0, $Subject, $Body);
    }
    $master->tracker->updateUser($Cur['torrent_pass'], $CanLeech);
}

$privileges = Restriction::$decode;
$restriction = new Restriction;

if (isset($_POST['DisableInvite'])) {
    $trackerRestricted = strval($master->options->ExtTrackerForums);
    $forumsrestricted = getNumArrayFromString($RestrictedForums);
    $restricted = implode(',', $forumsrestricted);
    $RestrictedForums = ($restricted . ',' . $trackerRestricted);
    $UpdateSet[] = 'RestrictedForums = ?';
    $UpdateData[] = $RestrictedForums;
    $EditSummary[]="restricted forum(s): ".$RestrictedForums;
}

foreach ($privileges as $privilege) {
    $status = isset($_POST[$privilege['key']])? TRUE : FALSE;
    if (check_perms($privilege['permission']) && $status) {
        $restriction->setFlags($privilege['flag']);
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
    $restrictions = $restriction->getRestrictions();
    $Extra = "";
    if (!empty($restrictions)) {
        $Extra .= "During your warning your access to the following will be restricted:\n";
        $Extra .= implode(', ', $restrictions);
        $Extra .= "\n\n";
    }
    $Subject = "You have received a warning";
    send_pm($userID, 0, $Subject,
            "You have been warned{$WarnLength}.\n".
                      $WarnReason. $Extra.
                      "[url=/articles.php?topic=rules]Read site rules here.[/url]"
    );
}

if ($restriction->Flags != 0) {
    $restriction->UserID  = $userID;
    $restriction->StaffID = $activeUser['ID'];
    $restriction->Created = new \DateTime();
    if (!empty($_POST['WarnLength'])) {
        $WarnLength = (int) $_POST['WarnLength'];
        $restriction->Expires = new \DateTime("+{$WarnLength} weeks");
    }
    $restriction->Comment = $_POST['WarnComment'];
    if (!isset($_POST['warn'])) {
        $restrictions = $restriction->getRestrictions();
        $restrictions = implode(', ', $restrictions);
        $WarnReason   = trim($_POST['WarnReason']);
        if (!empty($WarnReason)) {
          $WarnReason = "The reason given: {$WarnReason}.";
        }
        if ($_POST['disableInform'] === '1') {
            $Subject = "Your privileges have been disabled";
            $Body = "Your {$restrictions} privileges have been disabled.\n\n{$WarnReason}";
            send_pm($userID, 0, $Subject, $Body);
        }
    }
    $master->repos->restrictions->save($restriction);
    if ($restriction->isWarning()) {
        $EditStr = "User warned";
    }

    if (!empty($restrictions)) {
        if (!empty($EditStr)) {
            $EditStr .= " and ";
        }
        $restrictions =  implode(', ', $restriction->getRestrictions());
        $EditStr .= "{$restrictions} privileges disabled";
    }

    if (!empty($_POST['WarnLength'])) {
        $EditStr .= " for {$WarnLength} weeks";
    }

    if (empty($_POST['Reason'])) {
        $Reason = $_POST['WarnComment'];
    }

    $EditSummary[] = $EditStr;
}

if ($EnableUser!=$Cur['Enabled'] && check_perms('users_disable_users')) {
    $EnableStr = 'account '.translateUserStatus($Cur['Enabled']).'->'.translateUserStatus($EnableUser);
    if ($EnableUser == '2') {
        $BanReason = (int) $_POST['ban_reason'];
        if ($BanReason<0 || $BanReason>5) $BanReason=1;
        disable_users($userID, '', $BanReason);
    } elseif ($EnableUser == '3') {
        $BanReason = 5;
        disable_users($userID, '', $BanReason, $EnableUser);
    } elseif ($EnableUser == '1') {
        $master->cache->incrementValue('stats_user_count');
        $master->tracker->addUser($Cur['torrent_pass'], $userID);
        if (($Cur['Downloaded'] == 0) || ($Cur['Uploaded']/$Cur['Downloaded'] >= $Cur['RequiredRatio'])) {
            $UpdateSet[] = 'i.RatioWatchEnds = ?';
            $UpdateData[] = '0000-00-00 00:00:00';
            $CanLeech = 1;
            $UpdateSet[] = 'm.can_leech = ?';
            $UpdateData[] = '1';
            $UpdateSet[] = 'i.RatioWatchDownload = ?';
            $UpdateData[] = '0';
        } else {
            $EnableStr .= ' (Ratio: '.number_format($Cur['Uploaded']/$Cur['Downloaded'], 2).', RR: '.number_format($Cur['RequiredRatio'], 2).')';
            if ($Cur['RatioWatchEnds'] != '0000-00-00 00:00:00') {
                $UpdateSet[] = 'i.RatioWatchEnds=NOW()';
                $UpdateSet[] = 'i.RatioWatchDownload=m.Downloaded';
                $CanLeech = 0;
            }
        }
        $Visible=$Cur['Visible'];
        if ($user->IPID == 0) $Visible=0;
        $track_ipv6=$Cur['track_ipv6'];
        //Ensure the tracker has the correct settings applied
        $master->tracker->updateUser($Cur['torrent_pass'], $CanLeech, $Visible, $track_ipv6);
        $UpdateSet[] = "Enabled='1'";
        $UpdateSet[] = "i.BanReason='0'";
        $UpdateSet[] = "InactivityException=DATE_ADD(NOW(), INTERVAL 3 DAY)";
    }
    $EditSummary[] = $EnableStr;
    $master->cache->cacheValue('enabled_'.$userID, $EnableUser, 0);
}

if ($ResetPasskey == 1 && check_perms('users_edit_reset_keys')) {
    $Passkey = make_secret();
    $UpdateSet[] = 'torrent_pass = ?';
    $UpdateData[] = $Passkey;
    $EditSummary[] = "passkey reset";
    $master->cache->deleteValue('user_'.$Cur['torrent_pass']);
    //MUST come after the case for updating can_leech.

    // Log passkey reset
    $master->repos->securityLogs->passkeyChange((int) $userID);

    $passkeyHistory = new UserHistoryPasskey;
    $passkeyHistory->UserID = $userID;
    $passkeyHistory->IPID = $master->request->ip->ID;
    $passkeyHistory->Time = new \DateTime;
    $passkeyHistory->OldPassKey = $Cur['torrent_pass'];
    $passkeyHistory->NewPassKey = $Passkey;
    $master->repos->userhistorypasskeys->save($passkeyHistory);

    $master->tracker->changePasskey($Cur['torrent_pass'], $Passkey);
}

if ($ResetAuthkey == 1 && check_perms('users_edit_reset_keys')) {
    $Authkey = make_secret();
    $UpdateSet[] = 'AuthKey = ?';
    $UpdateData[] = $Authkey;
    $EditSummary[] = "authkey reset";
}

if ($SendHackedMail && check_perms('users_disable_any')) {
    $EditSummary[]="hacked email sent to {$HackedEmail}";

    $email = $HackedEmail;

    $subject = 'Unauthorized account access';
    $email_body = [];
    $email_body['settings'] = $master->settings;

    if ($this->settings->site->debug_mode) {
        $body = $master->tpl->render('email/hacked_account.email.twig', $email_body);
        $master->flasher->notice($body);
    } else {
        $body = $master->tpl->render('email/hacked_account.email.twig', $email_body);
        $master->emailManager->sendEmail($email, $subject, $body);
        $master->flasher->notice("An e-mail with further instructions has been sent to the provided address.");
    }
}

if ($SendConfirmMail) {
    $EditSummary[]="confirmation email resent to {$ConfirmEmail}";

    $email = $ConfirmEmail;

    $token = $master->secretary->getExternalToken($email, 'user.register');
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
        $master->emailManager->sendEmail($email, $subject, $body);
        $master->flasher->notice("An e-mail with further instructions has been sent to the provided address.");
    }
}

if ($MergeStatsFrom && check_perms('users_edit_ratio')) {
    $nextRecord = $master->db->rawQuery(
        "SELECT m.ID,
                m.Uploaded,
                m.Downloaded,
                w.Balance
           FROM users_main AS m
           JOIN users_wallets AS w ON m.ID = w.UserID
          WHERE Username LIKE ?",
        [$MergeStatsFrom]
    )->fetch(\PDO::FETCH_NUM);

    if ($master->db->foundRows() > 0) {
        list($MergeID, $MergeUploaded, $MergeDownloaded, $MergeCredits) = $nextRecord;

        $MergeSummary  = sqltime()." - Stats merged into http://".SITE_URL."/user.php?id=".$userID." (".$user->Username.") by ".$activeUser['Username'];
        $MergeSummary .= " - Removed ".get_size($MergeUploaded)." uploaded / ".get_size($MergeDownloaded)." downloaded / ".$MergeCredits." credits";

        $master->db->rawQuery(
            "UPDATE users_main AS um
               JOIN users_info AS ui ON um.ID = ui.UserID
               JOIN users_wallets AS uw ON um.ID = uw.UserID
                SET um.Uploaded = 0,
                    um.Downloaded = 0,
                    uw.Balance = 0,
                    ui.AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, ui.AdminComment)
              WHERE ID = ?",
              [$MergeSummary, $MergeID]
        );

        $UpdateSet[]="m.Uploaded = m.Uploaded + '$MergeUploaded'";
        $UpdateSet[]="m.Downloaded = m.Downloaded + '$MergeDownloaded'";
        $UpdateSet[]="w.Balance = w.Balance + '$MergeCredits'";

        $EditSummary[]="stats merged from http://".SITE_URL."/user.php?id=".$MergeID." (".$MergeStatsFrom.") - Added ".get_size($MergeUploaded)." uploaded / ".get_size($MergeDownloaded)." downloaded / ".$MergeCredits." credits";
        $master->cache->deleteValue('user_stats_'.$userID);
        $master->cache->deleteValue('user_stats_'.$MergeID);
        $master->repos->users->uncache($MergeID);
    }
}

if ($Pass) {
    if (!check_perms('users_edit_password')) error(403);
    if ($Pass !== $Pass2) error("Password1 and Password2 did not match! You must enter the same new password twice to change a users password");
    $master->auth->setPassword($userID, $Pass);
    $EditSummary[]='password reset';

    $master->repos->securityLogs->passwordChange((int) $userID);
    $master->repos->users->uncache($userID);

    $sessionIDs = $master->db->rawQuery(
        "SELECT ID
           FROM sessions
          WHERE UserID = ?",
        [$userID]
    )->fetchAll(\PDO::FETCH_NUM);
    foreach ($sessionIDs as $sessionID) {
        $master->cache->deleteValue("_entity_Session_{$sessionID}");
    }

    $master->db->rawQuery("DELETE FROM sessions WHERE UserID = ?", [$userID]);
}

if (empty($UpdateSet) && empty($EditSummary)) {
    if (!$Reason) {
        if (str_replace("\r", '', $Cur['AdminComment']) != str_replace("\r", '', $AdminComment)) {
            if (check_perms('users_edit_notes')) {
                $UpdateSet[]="AdminComment = ?";
                $UpdateData[] = $AdminComment;
            }
        } else {
            header("Location: user.php?id=$userID");
            die();
        }
    }
}

$master->repos->users->uncache($userID);

$Summary = '';
// Create edit summary
if (!empty($EditSummary)) {
    $Summary = implode(', ', $EditSummary)." by ".$activeUser['Username'];
    $Summary = sqltime().' - '.ucfirst($Summary);

    if ($Reason) {
        $Summary .= "\nReason: ".$Reason;
    }

    $Summary .= "\n".$AdminComment;
} elseif (empty($UpdateSet) && empty($EditSummary) && (check_perms('users_add_notes') || check_perms('users_mod'))) {
    $Summary = sqltime().' - '.'Note added by '.$activeUser['Username'].': '.$Reason."\n";
    $Summary .= $AdminComment;
}

if (!empty($Summary)) {
    $UpdateSet[] = 'AdminComment = ?';
    $UpdateData[] = $Summary;
} else {
    $UpdateSet[] = 'AdminComment = ?';
    $UpdateData[] = $AdminComment;
}

// Build query

$SET = implode(', '.PHP_EOL, $UpdateSet);

$master->repos->users->save($user);
$UpdateData[] = $userID;
// Perform update
$master->db->rawQuery(
    "UPDATE users_main AS m
       JOIN users_info AS i ON m.ID = i.UserID
       JOIN users_wallets AS w ON m.ID = w.UserID
        SET {$SET}
      WHERE m.ID = ?",
    $UpdateData
);

// do this now so it doesnt interfere with previous query
if ($doDuckyCheck === true) award_ducky_check($userID, 0);

if (isset($ClearStaffIDCache)) {
    $master->cache->deleteValue('staff_ids');
}

// redirect to user page
header("location: user.php?id=$userID");

function translateUserStatus($status)
{
    switch ($status) {
        case 0:
            return "Unconfirmed";
        case 1:
            return "Enabled";
        case 2:
            return "Disabled";
        case 3:
            return "Retired";
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
