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
                 array('regex' => $whitelistregex, 'maxlength' => 255, 'minlength' => 6, 'maxfilesizeKB'=>-1, 'dimensions'=>array(300,500)));
$Val->SetFields('info',0,'desc','Info',array('regex'=>$whitelistregex,'minlength'=>0,'maxlength'=>20000));
$Val->SetFields('signature',0,'desc','Signature',array('regex'=>$whitelistregex,'minlength'=>0,'maxlength'=>$Permissions['MaxSigLength'], 'dimensions'=>array(SIG_MAX_WIDTH, SIG_MAX_HEIGHT)));
$Val->SetFields('torrentsignature',0,'desc','Signature',array('regex'=>$whitelistregex,'minlength'=>0,'maxlength'=>$Permissions['MaxSigLength']));
$Val->SetFields('email',1,"email","You did not enter a valid email address.");
$Val->SetFields('irckey',0,"string","You did not enter a valid IRCKey, must be between 6 and 32 characters long.",array('minlength'=>6,'maxlength'=>32));
$Val->SetFields('cur_pass',0,"string","You did not enter a valid password, must be between 6 and 40 characters long.",array('minlength'=>6,'maxlength'=>40));
$Val->SetFields('new_pass_1',0,"string","You did not enter a valid password, must be between 6 and 40 characters long.",array('minlength'=>6,'maxlength'=>40));
$Val->SetFields('new_pass_2',1,"compare","Your passwords do not match.",array('comparefield'=>'new_pass_1'));
if (check_perms('site_advanced_search')) {
    $Val->SetFields('searchtype',1,"number","You forgot to select your default search preference.",array('minlength'=>0,'maxlength'=>1));
}

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

//Email change
$DB->query("SELECT Email FROM users_main WHERE ID=".$UserID);
list($CurEmail) = $DB->next_record();
if ($CurEmail != $_POST['email']) {
    if (!$master->auth->check_login($UserID, $_POST['cur_pass'])) {
        $Err = "You did not enter the correct password.";
    }
    if (!$Err) {
        $NewEmail = db_string($_POST['email']);
        $this->master->emailManager->newEmail($UserID, $_POST['email']);
        //This piece of code will update the time of their last email change to the current time *not* the current change.
        $DB->query("UPDATE users_history_emails SET Time='".sqltime()."' WHERE UserID='$UserID' AND Time='0000-00-00 00:00:00'");
        $DB->query("INSERT INTO users_history_emails
                (UserID, Email, Time, IP, ChangedbyID) VALUES
                ('$UserID', '$NewEmail', '0000-00-00 00:00:00', '".db_string($_SERVER['REMOTE_ADDR'])."','$LoggedUser[ID]')");
    } else {
        error($Err);
    }
}
//End Email change

if (!$Err && ($_POST['cur_pass'] || $_POST['new_pass_1'] || $_POST['new_pass_2'])) {

    if ($master->auth->check_login($UserID, $_POST['cur_pass'])) {
        if ($_POST['new_pass_1'] && $_POST['new_pass_2']) {
            $ResetPassword = true;
        }
    } else {
        $Err = "You did not enter the correct password.";
    }
}

if (!$Err && ($UserID == $LoggedUser['ID'])) {
    if ($LoggedUser['DisableAvatar'] && $_POST['avatar'] != $U['Avatar']) {
        $Err = "Your avatar rights have been removed.";
    }
    if ($LoggedUser['DisableSignature'] && $_POST['signature'] != $U['Signature']) {
        $Err = "Your signature rights have been removed.";
    }
    if ($LoggedUser['DisableTorrentSig'] && $_POST['torrentsignature'] != $U['TorrentSignature']) {
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
$Options['NotForceLinks'] = (!empty($_POST['forcelinks']) ? 0 : 1);
$Options['MaxTags'] = (isset($_POST['maxtags']) ? (int) $_POST['maxtags'] : 16);
$Options['ShowGames'] = (!empty($_POST['showgames']) ? 1 : 0);

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
if (check_perms('site_advanced_search')) {
    $Options['SearchType'] = $_POST['searchtype'];
} else {
    unset($Options['SearchType']);
}

// Handle the tons of Latest Forum Topics checkboxes
// We store a blacklist rather than a whitelist here, as the majority of the users will
// probably want to see _most_ of the forum sections -> should be more efficient
//
// We don't check Permitted/RestrictedForums or even MinClassRead here. It's not really needed.
require_once(SERVER_ROOT.'/sections/forums/functions.php');
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
$UnseededAlerts = (isset($_POST['unseededalerts']))? 1:0;

// Information on how the user likes to download torrents is stored in cache
if ($DownloadAlt != $LoggedUser['DownloadAlt']) {
    $Cache->delete_value('user_'.$LoggedUser['torrent_pass']);
}
$BlockPMs = (!empty($_POST['blockPMs']) ? (int) $_POST['blockPMs'] : 0);
if (!in_array($BlockPMs,array(0,1,2))) $BlockPMs =0;
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
    i.CommentsNotify='".$CommentsNotify."',
    i.DownloadAlt='$DownloadAlt',
    i.UnseededAlerts='$UnseededAlerts',
    m.Email='".db_string($_POST['email'])."',
    m.IRCKey='".db_string($_POST['irckey'])."',
    m.Flag='".db_string($Flag)."',
    i.TorrentSignature='".db_string($_POST['torrentsignature'])."',
    m.Signature='".db_string($_POST['signature'])."',";

$SQL .= "m.Paranoia='".db_string(serialize($Paranoia))."'";


if ($ResetPassword) {
    $ChangerIP = db_string($LoggedUser['IP']);
    $master->auth->set_password($UserID, $_POST['new_pass_1']);
    $DB->query("INSERT INTO users_history_passwords
        (UserID, ChangerIP, ChangeTime) VALUES
        ('$UserID', '$ChangerIP', '".sqltime()."')");
}

if (isset($_POST['resetpasskey'])) {
    $UserInfo = user_heavy_info($UserID);
    $OldPassKey = db_string($UserInfo['torrent_pass']);
    $NewPassKey = db_string(make_secret());
    $ChangerIP = db_string($LoggedUser['IP']);
    $SQL.=",m.torrent_pass='$NewPassKey'";
    $DB->query("INSERT INTO users_history_passkeys
            (UserID, OldPassKey, NewPassKey, ChangerIP, ChangeTime) VALUES
            ('$UserID', '$OldPassKey', '$NewPassKey', '$ChangerIP', '".sqltime()."')");
    $master->repos->users->uncache($UserID);
    $Cache->delete_value('user_'.$OldPassKey);

    update_tracker('change_passkey', array('oldpasskey' => $OldPassKey, 'newpasskey' => $NewPassKey));
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

if ($ResetPassword) {
    logout();
}

header('Location: user.php?action=edit&userid='.$UserID);
