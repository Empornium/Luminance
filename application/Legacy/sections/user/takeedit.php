<?php
authorize();

use Luminance\Entities\UserHistoryPasskey;

$userID = $_REQUEST['userid'];
if (!is_integer_string($userID)) {
    error(404);
}

//For the entire of this page we should in general be using $userID not $activeUser['ID'] and $U[] not $activeUser[]
$U = user_info($userID);

if (!$U) {
    error(404);
}

$permissions = get_permissions($U['PermissionID']);
if ($userID != $activeUser['ID'] && !check_perms('users_edit_profiles', $permissions['Class'])) {
    $master->irker->announceAdmin("User ".$activeUser['Username']." (http://".SITE_URL."/user.php?id=".$activeUser['ID'].") just tried to edit the profile of http://".SITE_URL."/user.php?id=".$_REQUEST['userid']);
    error(403);
}
$whitelistregex = get_whitelist_regex();
$Val->SetFields('stylesheet', 1, "number", "You forgot to select a stylesheet.");
$Val->SetFields('timezone', 1, "inarray", "Invalid TimeZone.", ['inarray'=>timezone_identifiers_list()]);
$Val->SetFields('messagesperpage', 1, "inarray", "You forgot to select your messages per page option.", ['inarray' => [25, 50, 100]]);
$Val->SetFields('postsperpage', 1, "inarray", "You forgot to select your posts per page option.", ['inarray' => [25, 50, 100]]);
$Val->SetFields('torrentsperpage', 1, "inarray", "You forgot to select your torrents per page option.", ['inarray' => [25, 50, 100]]);
$Val->SetFields('collagesperpage', 1, "inarray", "You forgot to select your collages per page option.", ['inarray' => [25, 50, 100]]);
//$Val->SetFields('hidecollage', 1, "number", " You forgot to select your collage option.", ['minlength' => 0, 'maxlength' => 1]);
//$Val->SetFields('collagecovers', 1, "inarray", "You forgot to select your collage option.", ['inarray' => ["on", "off"]]));
$Val->SetFields('showtags', 1, "number", "You forgot to select your show tags option.", ['minlength' => 0, 'maxlength' => 1]);
$Val->SetFields('maxtags', 1, "number", "You forgot to select your maximum tags to show in lists option.", ['minlength' => 0, 'maxlength' => 10000]);
$Val->SetFields('avatar', 0, 'image', 'Avatar: The image URL you entered was not valid.', [
    'regex'         => $whitelistregex,
    'maxlength'     => 255,
    'minlength'     => 6,
    'maxfilesizeKB' => $master->options->AvatarSizeKiB,
    'dimensions'    => [300, 500]
]);
$Val->SetFields('info', 0, 'desc', 'Info',[
    'regex'         => $whitelistregex,
    'minlength'     => 0,
    'maxlength'     => 20000
]);
$Val->SetFields('signature', 0, 'desc', 'Signature',[
    'regex'             => $whitelistregex,
    'minlength'         => 0,
    'maxlength'         => $permissions['MaxSigLength'],
    'dimensions'        => [SIG_MAX_WIDTH, SIG_MAX_HEIGHT],
    'maximageweightMB'  => 1,
]);
$Val->SetFields('torrentsignature', 0, 'desc', 'Signature', [
    'regex'             => $whitelistregex,
    'minlength'         => 0,
    'maxlength'         => $permissions['MaxSigLength'],
    'maxheight'         => TORRENT_SIG_MAX_HEIGHT,
    'maximageweightMB'  => 1,
]);

$Err = $Val->ValidateForm($_POST);

if ($Err) {
    error($Err);
}

// Begin building $paranoia
// Reduce the user's input paranoia until it becomes consistent

if (isset($_POST['p_collagecontribs_l'])) {
    $_POST['p_collages_l'] = 'on';
    $_POST['p_collages_c'] = 'on';
}

if (isset($_POST['p_snatched_c']) && isset($_POST['p_seeding_c']) && isset($_POST['p_downloaded'])) {
    $_POST['p_requiredratio'] = 'on';
}

// if showing exactly 2 of stats, show all 3 of stats
$StatsShown = 0;
$Stats = ['downloaded', 'uploaded', 'ratio'];
foreach ($Stats as $S) {
    if (isset($_POST['p_'.$S])) {
        $StatsShown++;
    }
}

if ($StatsShown == 2) {
    foreach ($Stats as $S) {
        $_POST['p_'.$S] = 'on';
    }
}

$paranoia = [];
$Checkboxes = ['downloaded', 'uploaded', 'ratio', 'lastseen', 'requiredratio', 'invitedcount'];
foreach ($Checkboxes as $C) {
    if (!isset($_POST['p_'.$C])) {
        $paranoia[] = $C;
    }
}

$SimpleSelects = ['torrentcomments', 'collages', 'collagecontribs', 'uploads', 'seeding', 'leeching', 'snatched', 'grabbed', 'tags'];
foreach ($SimpleSelects as $S) {
    if (!isset($_POST['p_'.$S.'_c']) && !isset($_POST['p_'.$S.'_l'])) {
        // Very paranoid - don't show count or list
        $paranoia[] = $S . '+';
    } elseif (!isset($_POST['p_'.$S.'_l'])) {
        // A little paranoid - show count, don't show list
        $paranoia[] = $S;
    }
}

$Bounties = ['requestsfilled', 'requestsvoted'];
foreach ($Bounties as $B) {
    if (isset($_POST['p_'.$B.'_list'])) {
        $_POST['p_'.$B.'_count'] = 'on';
        $_POST['p_'.$B.'_bounty'] = 'on';
    }
    if (!isset($_POST['p_'.$B.'_list'])) {
        $paranoia[] = $B.'_list';
    }
    if (!isset($_POST['p_'.$B.'_count'])) {
        $paranoia[] = $B.'_count';
    }
    if (!isset($_POST['p_'.$B.'_bounty'])) {
        $paranoia[] = $B.'_bounty';
    }
}
// End building $paranoia

if (!$Err && ($userID == $activeUser['ID'])) {
    if ($master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::AVATAR) && $_POST['avatar'] != $U['Avatar']) {
        $Err = "Your avatar rights have been removed.";
    }
    if ($master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::SIGNATURE) && $_POST['signature'] != $U['Signature']) {
        $Err = "Your signature rights have been removed.";
    }
    if ($master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::TORRENTSIGNATURE) && $_POST['torrentsignature'] != $U['TorrentSignature']) {
        $Err = "Your torrent signature rights have been removed.";
    }
}

if ($Err) {
    error($Err);
}

$Options = [];
if (!empty($activeUser['DefaultSearch'])) {
    $Options['DefaultSearch'] = $activeUser['DefaultSearch'];
}

$Options['MessagesPerPage'] = (int) ($_POST['messagesperpage'] ?? 25);
$Options['IpsPerPage'] = (int) ($_POST['ipsperpage'] ?? 25);
$Options['DumpData'] = (int) ($_POST['dumpdata'] ?? 0);
$Options['ShowElapsed'] = (int) ($_POST['showelapsed'] ?? 0);
$Options['ExtendedIPSearch'] = (int) ($_POST['extendedipsearch'] ?? 0);
$Options['TorrentsPerPage'] = (int) ($_POST['torrentsperpage'] ?? 50);
$Options['CollagesPerPage'] = (int) ($_POST['collagesperpage'] ?? 25);
$Options['PostsPerPage'] = (int) ($_POST['postsperpage'] ?? 25);
$Options['TorrentPreviewWidth'] = max(min((int) $_POST['torrentpreviewwidth'] ?? 0, 800), 200);
$Options['TorrentPreviewWidthForced'] = (bool) ($_POST['torrentpreviewwidth-forced'] ?? false);
//$Options['HideCollage'] = (!empty($_POST['hidecollage']) ? 1 : 0);
$Options['CollageCovers'] = (($_POST['collagecovers'] ?? false) && $_POST['collagecovers'] === "on") ? 1 : 0;
$Options['HideCats'] = (!empty($_POST['hidecats']) ? 1 : 0);
$Options['ShowTags'] = (!empty($_POST['showtags']) ? 1 : 0);
$Options['HideTagsInLists'] = (!empty($_POST['hidetagsinlists']) ? 1 : 0);
$Options['AutoSubscribe'] = (!empty($_POST['autosubscribe']) ? 1 : 0);
$Options['DisableLatestTopics'] = (!empty($_POST['disablelatesttopics']) ? 1 : 0);
$Options['DisableSmileys'] = (!empty($_POST['disablesmileys']) ? 1 : 0);
$Options['DisableAvatars'] = (!empty($_POST['disableavatars']) ? 1 : 0);
$Options['DisableSignatures'] = (!empty($_POST['disablesignatures']) ? 1 : 0);
$Options['TimeStyle'] = (!empty($_POST['timestyle']) ? 1 : 0);
$Options['NotVoteUpTags'] = (!empty($_POST['voteuptags']) ? 0 : 1);
$Options['ShortTitles'] = (!empty($_POST['shortpagetitles']) ? 1 : 0);
$Options['HideUserTorrents'] = (!empty($_POST['showusertorrents']) ? 0 : 1);
$Options['SplitByDays'] = (!empty($_POST['splitbydays']) ? 1 : 0);
$Options['HideFloat'] = (!empty($_POST['hidefloatinfo']) ? 1 : 0);
$Options['HideDetailsSidebar'] = (!empty($_POST['hidedetailssidebar']) ? 1 : 0);
$Options['HideForumSidebar'] = (!empty($_POST['hideforumsidebar']) ? 1 : 0);
$Options['ShowTorrentChecker'] = (!empty($_POST['showtorrentchecker']) ? 1 : 0);
$Options['NotForceLinks'] = (!empty($_POST['forcelinks']) ? 0 : 1);
$Options['MaxTags'] = (isset($_POST['maxtags']) ? (int) $_POST['maxtags'] : 16);
$Options['NoForumAlerts'] = (!empty($_POST['noforumalerts']) ? 1 : 0);
$Options['NoNewsAlerts'] = (!empty($_POST['nonewsalerts']) ? 1 : 0);
$Options['NoBlogAlerts'] = (!empty($_POST['noblogalerts']) ? 1 : 0);
$Options['NoContestAlerts'] = (!empty($_POST['nocontestalerts']) ? 1 : 0);

if (isset($activeUser['DisableFreeTorrentTop10'])) {
    $Options['DisableFreeTorrentTop10'] = $activeUser['DisableFreeTorrentTop10'];
}

if (!empty($_POST['hidetypes'])) {
    foreach ($_POST['hidetypes'] as $Type) {
        $Options['HideTypes'][] = (int) $Type;
    }
} else {
    $Options['HideTypes'] = [];
}


// Handle the tons of Latest Forum Topics checkboxes
// We store a blacklist rather than a whitelist here, as the majority of the users will
// probably want to see _most_ of the forum sections -> should be more efficient
//
// We don't check Permitted/RestrictedForums or even MinClassRead here. It's not really needed.
$Forums = $master->repos->forums->getForumInfo();
$DisabledLatestTopics = [];
foreach ($Forums as $Forum) {
    if (empty($_POST['disable_lt_'.$Forum['ID']])) {
        $DisabledLatestTopics[] = $Forum['ID'];
    }
}
if (count($DisabledLatestTopics) > 0) {
    $Options['DisabledLatestTopics'] = $DisabledLatestTopics;
}

$DownloadAlt = (isset($_POST['downloadalt']))? 1:0;
$TrackIPv6 = (isset($_POST['trackipv6']))? 1:0;
$UnseededAlerts = (isset($_POST['unseededalerts']))? 1:0;

// Information on how the user likes to download torrents is stored in cache
if ($DownloadAlt != $activeUser['DownloadAlt']) {
    $master->cache->deleteValue('user_'.$activeUser['torrent_pass']);
}

$BlockPMs = (!empty($_POST['blockPMs']) ? (int) $_POST['blockPMs'] : 0);
if (!in_array($BlockPMs, [0, 1, 2])) {
    $BlockPMs =0;
}

$BlockGifts = (!empty($_POST['blockgifts']) ? (int) $_POST['blockgifts'] : 0);
if (!in_array($BlockGifts, [0, 1, 2])) {
    $BlockGifts =0;
}

$CommentsNotify = (isset($_POST['commentsnotify']))? 1:0;
$timeOffset=get_timezone_offset($_POST['timezone']);
$master->repos->users->uncache($userID);

$Flag = (isset($_POST['flag']))? $_POST['flag']:'';

$SQL=
    "UPDATE users_main AS m
       JOIN users_info AS i ON m.ID = i.UserID
        SET i.StyleID = ?,
            i.Avatar = ?,
            i.SiteOptions = ?,
            i.Info = ?,
            i.TimeZone = ?,
            i.BlockPMs = ?,
            i.BlockGifts = ?,
            i.CommentsNotify = ?,
            i.DownloadAlt = ?,
            m.track_ipv6 = ?,
            i.UnseededAlerts = ?,
            m.Flag = ?,
            i.TorrentSignature = ?,
            m.Signature = ?,
            m.Paranoia = ?";

$params = [];
$params[] = $_POST['stylesheet'];
$params[] = $_POST['avatar'];
$params[] = serialize($Options);
$params[] = $_POST['info'];
$params[] = $_POST['timezone'];
$params[] = $BlockPMs;
$params[] = $BlockGifts;
$params[] = $CommentsNotify;
$params[] = $DownloadAlt;
$params[] = $TrackIPv6;
$params[] = $UnseededAlerts;
$params[] = $Flag;
$params[] = ($_POST['torrentsignature'] ?? '');
$params[] = ($_POST['signature'] ?? '');
$params[] = serialize($paranoia);

if ($activeUser['ID'] != $userID) {
    $SQL .= ','.PHP_EOL.'i.AdminComment = CONCAT(?, " - User settings modified by ", ?, "\n", i.AdminComment)';
    $params[] = sqltime();
    $params[] = $activeUser['Username'];
}

if (!empty($_POST['resetlastbrowse'])) {
    $SQL .= ','.PHP_EOL."i.LastBrowse='0000-00-00 00:00:00'";
}

if (isset($_POST['resetpasskey'])) {
    $UserInfo = user_heavy_info($userID);
    $OldPassKey = $UserInfo['torrent_pass'];
    $NewPassKey = make_secret();
    $SQL.=','.PHP_EOL.'m.torrent_pass = ?';
    $params[] = $NewPassKey;

    // Log passkey reset
    $master->repos->securityLogs->passkeyChange((int) $userID);

    $passkeyHistory = new UserHistoryPasskey;
    $passkeyHistory->UserID = $userID;
    $passkeyHistory->IPID = $master->request->ip->ID;
    $passkeyHistory->Time = new \DateTime;
    $passkeyHistory->OldPassKey = $OldPassKey;
    $passkeyHistory->NewPassKey = $NewPassKey;
    $master->repos->userhistorypasskeys->save($passkeyHistory);

    $master->repos->users->uncache($userID);
    $master->cache->deleteValue("user_{$OldPassKey}");
    $master->tracker->changePasskey($OldPassKey, $NewPassKey);
}

$SQL .= PHP_EOL."WHERE m.ID = ?";
$params[] = $userID;
$master->db->rawQuery($SQL, $params);


if (check_perms('site_set_language')) {
    $DelLangs = $_POST['del_lang'] ?? [];
    if (is_array($DelLangs)) {
          foreach ($DelLangs as &$langID) { //
                $langID = (int) $langID ;
          }
          if (count($DelLangs) > 0) {
              $inQuery = implode(', ', array_fill(0, count($DelLangs), '?'));
              $params = $DelLangs;
              $params = array_merge([$userID], $params);
              $master->db->rawQuery(
                  "DELETE
                     FROM users_languages
                    WHERE UserID = ?
                      AND LangID IN ({$inQuery})",
                  $params
              );
          }
    }

    if (isset($_POST['new_lang']) && is_integer_string($_POST['new_lang'])) {
        $master->db->rawQuery(
            "INSERT IGNORE INTO users_languages (UserID, LangID)
                         VALUES (?, ?)",
            [$userID, $_POST['new_lang']]
        );
        $master->cache->deleteValue('user_langs_'.$userID);
    } elseif (!empty($inQuery)) {
        $master->cache->deleteValue('user_langs_'.$userID);
    }
}

$trackerOptions = $master->db->rawQuery(
    "SELECT torrent_pass,
            can_leech,
            Visible AS visible,
            track_ipv6
       FROM users_main
      WHERE ID = ?",
    [$userID]
)->fetch();
$master->tracker->updateUser($trackerOptions['torrent_pass'], $trackerOptions['can_leech'], $trackerOptions['visible'], $trackerOptions['track_ipv6']);

header('Location: user.php?action=edit&userid='.$userID);
