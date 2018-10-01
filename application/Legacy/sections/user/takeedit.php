<?php
authorize();

$UserID = $_REQUEST['userid'];
if (!is_number($UserID)) {
    error(404);
}

//For the entire of this page we should in general be using $UserID not $LoggedUser['ID'] and $U[] not $LoggedUser[]
$U = user_info($UserID);

if (!$U) {
    error(404);
}

$Permissions = get_permissions($U['PermissionID']);
if ($UserID != $LoggedUser['ID'] && !check_perms('users_edit_profiles', $Permissions['Class'])) {
    send_irc("PRIVMSG ".ADMIN_CHAN." :User ".$LoggedUser['Username']." (http://".SITE_URL."/user.php?id=".$LoggedUser['ID'].") just tried to edit the profile of http://".SITE_URL."/user.php?id=".$_REQUEST['userid']);
    error(403);
}
$whitelistregex = get_whitelist_regex();
$Val->SetFields('stylesheet',1,"number","You forgot to select a stylesheet.");
$Val->SetFields('timezone',1,"inarray","Invalid TimeZone.",array('inarray'=>timezone_identifiers_list()));
$Val->SetFields('postsperpage',1,"inarray","You forgot to select your posts per page option.",array('inarray'=>array(25,50,100)));
$Val->SetFields('torrentsperpage',1,"inarray","You forgot to select your torrents per page option.",array('inarray'=>array(25,50,100)));
//$Val->SetFields('hidecollage',1,"number","You forgot to select your collage option.",array('minlength'=>0,'maxlength'=>1));
$Val->SetFields('collagecovers',1,"number","You forgot to select your collage option.");
$Val->SetFields('showtags',1,"number","You forgot to select your show tags option.",array('minlength'=>0,'maxlength'=>1));
$Val->SetFields('maxtags',1,"number","You forgot to select your maximum tags to show in lists option.",array('minlength'=>0,'maxlength'=>10000));
$Val->SetFields('avatar',0,'image', 'Avatar: The image URL you entered was not valid.',
                 array('regex' => $whitelistregex, 'maxlength' => 255, 'minlength' => 6, 'maxfilesizeKB'=>$master->options->AvatarSizeKiB, 'dimensions'=>array(300,500)));
$Val->SetFields('info',0,'desc','Info',array('regex'=>$whitelistregex,'minlength'=>0,'maxlength'=>20000));
$Val->SetFields('signature',0,'desc','Signature',array('regex'=>$whitelistregex,'minlength'=>0,'maxlength'=>$Permissions['MaxSigLength'], 'dimensions'=>array(SIG_MAX_WIDTH, SIG_MAX_HEIGHT)));
$Val->SetFields('torrentsignature',0,'desc','Signature',array('regex'=>$whitelistregex,'minlength'=>0,'maxlength'=>$Permissions['MaxSigLength']));

$Err = $Val->ValidateForm($_POST);

if ($Err) {
    error($Err);
}

// Begin building $Paranoia
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
$Stats = array('downloaded', 'uploaded', 'ratio');
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

$Paranoia = array();
$Checkboxes = array('downloaded', 'uploaded', 'ratio', 'lastseen', 'requiredratio', 'invitedcount');
foreach ($Checkboxes as $C) {
    if (!isset($_POST['p_'.$C])) {
        $Paranoia[] = $C;
    }
}

$SimpleSelects = array('torrentcomments', 'collages', 'collagecontribs', 'uploads', 'seeding', 'leeching', 'snatched', 'grabbed', 'tags');
foreach ($SimpleSelects as $S) {
    if (!isset($_POST['p_'.$S.'_c']) && !isset($_POST['p_'.$S.'_l'])) {
        // Very paranoid - don't show count or list
        $Paranoia[] = $S . '+';
    } elseif (!isset($_POST['p_'.$S.'_l'])) {
        // A little paranoid - show count, don't show list
        $Paranoia[] = $S;
    }
}

$Bounties = array('requestsfilled', 'requestsvoted');
foreach ($Bounties as $B) {
    if (isset($_POST['p_'.$B.'_list'])) {
        $_POST['p_'.$B.'_count'] = 'on';
        $_POST['p_'.$B.'_bounty'] = 'on';
    }
    if (!isset($_POST['p_'.$B.'_list'])) {
        $Paranoia[] = $B.'_list';
    }
    if (!isset($_POST['p_'.$B.'_count'])) {
        $Paranoia[] = $B.'_count';
    }
    if (!isset($_POST['p_'.$B.'_bounty'])) {
        $Paranoia[] = $B.'_bounty';
    }
}
// End building $Paranoia

if (!$Err && ($UserID == $LoggedUser['ID'])) {
    if ($master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::AVATAR) && $_POST['avatar'] != $U['Avatar']) {
        $Err = "Your avatar rights have been removed.";
    }
    if ($master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::SIGNATURE) && $_POST['signature'] != $U['Signature']) {
        $Err = "Your signature rights have been removed.";
    }
    if ($master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::TORRENTSIGNATURE) && $_POST['torrentsignature'] != $U['TorrentSignature']) {
        $Err = "Your torrent signature rights have been removed.";
    }
}

if ($Err) {
    error($Err);
}

if (!empty($LoggedUser['DefaultSearch'])) {
    $Options['DefaultSearch'] = $LoggedUser['DefaultSearch'];
}

$Options['TorrentsPerPage'] = (int) $_POST['torrentsperpage'];
$Options['PostsPerPage'] = (int) $_POST['postsperpage'];
$Options['TorrentPreviewWidth'] = max(min((int) $_POST['torrentpreviewwidth'], 800), 200);
$Options['TorrentPreviewWidthForced'] = (bool) $_POST['torrentpreviewwidth-forced'];
//$Options['HideCollage'] = (!empty($_POST['hidecollage']) ? 1 : 0);
$Options['CollageCovers'] = empty($_POST['collagecovers']) ? 0 : $_POST['collagecovers'];
$Options['HideCats'] = (!empty($_POST['hidecats']) ? 1 : 0);
$Options['ShowTags'] = (!empty($_POST['showtags']) ? 1 : 0);
$Options['HideTagsInLists'] = (!empty($_POST['hidetagsinlists']) ? 1 : 0);
$Options['AutoSubscribe'] = (!empty($_POST['autosubscribe']) ? 1 : 0);
$Options['DisableLatestTopics'] = (!empty($_POST['disablelatesttopics']) ? 1 : 0);
$Options['DisableSmileys'] = (!empty($_POST['disablesmileys']) ? 1 : 0);
$Options['DisableAvatars'] = (!empty($_POST['disableavatars']) ? 1 : 0);
$Options['DisablePMAvatars'] = (!empty($_POST['disablepmavatars']) ? 1 : 0);
$Options['DisableSignatures'] = (!empty($_POST['disablesignatures']) ? 1 : 0);
$Options['TimeStyle'] = (!empty($_POST['timestyle']) ? 1 : 0);
$Options['NotVoteUpTags'] = (!empty($_POST['voteuptags']) ? 0 : 1);
$Options['ShortTitles'] = (!empty($_POST['shortpagetitles']) ? 1 : 0);
$Options['HideUserTorrents'] = (!empty($_POST['showusertorrents']) ? 0 : 1);
$Options['SplitByDays'] = (!empty($_POST['splitbydays']) ? 1 : 0);
$Options['HideFloat'] = (!empty($_POST['hidefloatinfo']) ? 1 : 0);
$Options['HideDetailsSidebar'] = (!empty($_POST['hidedetailssidebar']) ? 1 : 0);
$Options['NotForceLinks'] = (!empty($_POST['forcelinks']) ? 0 : 1);
$Options['MaxTags'] = (isset($_POST['maxtags']) ? (int) $_POST['maxtags'] : 16);
$Options['ShowGames'] = (!empty($_POST['showgames']) ? 1 : 0);
$Options['NoNewsAlerts'] = (!empty($_POST['nonewsalerts']) ? 1 : 0);
$Options['NoBlogAlerts'] = (!empty($_POST['noblogalerts']) ? 1 : 0);
$Options['NoContestAlerts'] = (!empty($_POST['nocontestalerts']) ? 1 : 0);

if (isset($LoggedUser['DisableFreeTorrentTop10'])) {
    $Options['DisableFreeTorrentTop10'] = $LoggedUser['DisableFreeTorrentTop10'];
}

if (!empty($_POST['hidetypes'])) {
    foreach ($_POST['hidetypes'] as $Type) {
        $Options['HideTypes'][] = (int) $Type;
    }
} else {
    $Options['HideTypes'] = array();
}


// Handle the tons of Latest Forum Topics checkboxes
// We store a blacklist rather than a whitelist here, as the majority of the users will
// probably want to see _most_ of the forum sections -> should be more efficient
//
// We don't check Permitted/RestrictedForums or even MinClassRead here. It's not really needed.
require_once(SERVER_ROOT.'/Legacy/sections/forums/functions.php');
$Forums = get_forums_info();
$DisabledLatestTopics = array();
foreach ($Forums as $Forum) {
    if (empty($_POST['disable_lt_'.$Forum['ID']]))
        $DisabledLatestTopics[] = $Forum['ID'];
}
if (count($DisabledLatestTopics) > 0)
    $Options['DisabledLatestTopics'] = $DisabledLatestTopics;

//TODO: Remove the following after a significant amount of time
unset($Options['ShowQueryList']);
unset($Options['ShowCacheList']);

$DownloadAlt = (isset($_POST['downloadalt']))? 1:0;
$TrackIPv6 = (isset($_POST['trackipv6']))? 1:0;
$UnseededAlerts = (isset($_POST['unseededalerts']))? 1:0;

// Information on how the user likes to download torrents is stored in cache
if ($DownloadAlt != $LoggedUser['DownloadAlt']) {
    $Cache->delete_value('user_'.$LoggedUser['torrent_pass']);
}
$BlockPMs = (!empty($_POST['blockPMs']) ? (int) $_POST['blockPMs'] : 0);
if (!in_array($BlockPMs,array(0,1,2))) $BlockPMs =0;
$BlockGifts = (!empty($_POST['blockgifts']) ? (int) $_POST['blockgifts'] : 0);
if (!in_array($BlockGifts,array(0,1,2))) $BlockGifts =0;
$CommentsNotify = (isset($_POST['commentsnotify']))? 1:0;


$TimeOffset=get_timezone_offset($_POST['timezone']);

$master->repos->users->uncache($UserID);

$Flag = (isset($_POST['flag']))? $_POST['flag']:'';

$SQL="UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID SET
    i.StyleID='".db_string($_POST['stylesheet'])."',
    i.Avatar='".db_string($_POST['avatar'])."',
    i.SiteOptions='".db_string(serialize($Options))."',
    i.Info='".db_string($_POST['info'])."',
    i.TimeZone='".db_string($_POST['timezone'])."',
    i.BlockPMs='".$BlockPMs."',
    i.BlockGifts='".$BlockGifts."',
    i.CommentsNotify='".$CommentsNotify."',
    i.DownloadAlt='$DownloadAlt',
    m.track_ipv6='$TrackIPv6',
    i.UnseededAlerts='$UnseededAlerts',
    m.Flag='".db_string($Flag)."',
    i.TorrentSignature='".db_string($_POST['torrentsignature'])."',
    m.Signature='".db_string($_POST['signature'])."',";

if ($LoggedUser['ID'] != $UserID) {
    $SQL .= 'i.AdminComment = CONCAT("'.sqltime().' - User settings modified by '.$LoggedUser['Username'].'\n", i.AdminComment),';
}

if (!empty($_POST['resetlastbrowse'])) {
    $SQL .= "i.LastBrowse='0000-00-00 00:00:00',";
}

$SQL .= "m.Paranoia='".db_string(serialize($Paranoia))."'";

if (isset($_POST['resetpasskey'])) {
    $UserInfo = user_heavy_info($UserID);
    $OldPassKey = db_string($UserInfo['torrent_pass']);
    $NewPassKey = db_string(make_secret());
    $ChangerIP = db_string($LoggedUser['IP']);
    $SQL.=",m.torrent_pass='$NewPassKey'";

    // Log passkey reset
    $master->security->log->passkeyChange((int) $UserID);

    $DB->query("INSERT INTO users_history_passkeys
            (UserID, OldPassKey, NewPassKey, ChangerIP, ChangeTime) VALUES
            ('$UserID', '$OldPassKey', '$NewPassKey', '$ChangerIP', '".sqltime()."')");
    $master->repos->users->uncache($UserID);
    $Cache->delete_value('user_'.$OldPassKey);

    //update_tracker('change_passkey', array('oldpasskey' => $OldPassKey, 'newpasskey' => $NewPassKey));
    $master->tracker->changePasskey($OldPassKey, $NewPassKey);
}

$SQL.="WHERE m.ID='".db_string($UserID)."'";
$DB->query($SQL);


if (check_perms('site_set_language')) {

    $DelLangs = $_POST['del_lang'];
    if (is_array($DelLangs) ) {

          $Div = '';
          $SQL_IN ='';
          foreach ($DelLangs as $langID) { //
                $SQL_IN .= "$Div " . (int) $langID ;
                $Div = ',';
          }
          if ($SQL_IN) $DB->query("DELETE FROM users_languages WHERE UserID='$UserID' AND LangID IN ( $SQL_IN )");
    }

    if (isset($_POST['new_lang']) && is_number($_POST['new_lang'])) {
        $DB->query("INSERT IGNORE INTO users_languages (UserID, LangID) VALUES ('$UserID', '$_POST[new_lang]' )");
        $Cache->delete_value('user_langs_'.$UserID);
    } elseif ($SQL_IN) {
        $Cache->delete_value('user_langs_'.$UserID);
    }
}

$trackerOptions = $master->db->raw_query("SELECT torrent_pass, can_leech, Visible AS visible, track_ipv6 FROM users_main WHERE ID=:userID", [':userID' => $UserID])->fetch();
$master->tracker->updateUser($trackerOptions['torrent_pass'], $trackerOptions['can_leech'], $trackerOptions['visible'], $trackerOptions['track_ipv6']);

header('Location: user.php?action=edit&userid='.$UserID);
