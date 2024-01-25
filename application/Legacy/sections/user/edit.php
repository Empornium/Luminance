<?php
$userID = $_REQUEST['userid'];
if (empty($userID)) {
    $userID = $activeUser['ID'];
}
if (!is_integer_string($userID)) {
    error(404);
}

$nextRecord = $master->db->rawQuery(
    "SELECT um.Paranoia,
            um.Signature,
            um.PermissionID,
            um.CustomPermissions,
            um.track_ipv6,
            ui.Info,
            ui.Avatar,
            ui.Country,
            ui.StyleID,
            ui.SiteOptions,
            ui.UnseededAlerts,
            ui.TimeZone,
            ui.BlockPMs,
            ui.BlockGifts,
            ui.CommentsNotify,
            um.Flag,
            ui.DownloadAlt,
            ui.TorrentSignature,
            ui.LastBrowse,
            ui.RestrictedForums,
            ui.PermittedForums
       FROM users_main AS um
       JOIN users_info AS ui ON ui.UserID = um.ID
  LEFT JOIN permissions AS p ON p.ID=um.PermissionID
      WHERE um.ID = ?",
    [$userID]
)->fetch(\PDO::FETCH_NUM);

list($paranoia, $Signature, $PermissionID, $CustomPermissions, $TrackIPv6, $Info, $Avatar, $Country,
     $StyleID, $UserOptions, $UnseededAlerts, $timeZone, $BlockPms, $BlockGifts, $CommentsNotify, $flag, $DownloadAlt,
     $TorrentSignature, $LastBrowse, $RestrictedForums, $PermittedForums) = $nextRecord;

$permissions = get_permissions($PermissionID);
list($class, $PermissionValues, $MaxSigLength, $MaxAvatarWidth, $MaxAvatarHeight)=array_values($permissions);

if ($userID != $activeUser['ID'] && !check_perms('users_edit_profiles', $class)) {
    error(403);
}

$paranoia = unserialize($paranoia);
if (!is_array($paranoia)) $paranoia = [];

function paranoia_level($Setting)
{
       global $paranoia;
       // 0: very paranoid; 1: stats allowed, list disallowed; 2: not paranoid
       return (in_array($Setting . '+', $paranoia)) ? 0 : (in_array($Setting, $paranoia) ? 1 : 2);
}

function display_paranoia($FieldName)
{
       $Level = paranoia_level($FieldName);
       print '<label><input type="checkbox" name="p_'.$FieldName.'_c" '.checked($Level >= 1).' onChange="AlterParanoia()" /> Show count</label>'."&nbsp;&nbsp;\n";
       print '<label><input type="checkbox" name="p_'.$FieldName.'_l" '.checked($Level >= 2).' onChange="AlterParanoia()" /> Show list</label>';
}

function checked($Checked)
{
    return $Checked ? 'checked="checked"' : '';
}

function sorttz($a, $b)
{
    if ($a[1] == $b[1]) {
        if ($a[0] == $b[0]) {
            return 0;
        } else {
            return ($a[0] < $b[0]) ? -1 : 1;
        }
    } else {
        return ($a[1] < $b[1]) ? -1 : 1;
    }
}

function get_timezones_list()
{
    global $master;
    $zones = $master->cache->getValue('timezones');
    if ($zones !== false) {
        return $zones;
    }

    $zones = [];
    $rawzones = timezone_identifiers_list();
    $Continents = ['Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific'];
    $i = 0;
    foreach ($rawzones AS $szone) {
        $z = explode('/', $szone);
        if ( in_array($z[0], $Continents )) {
            $zones[$i][0] = $szone;
            $zones[$i][1] = -get_timezone_offset($szone);
            $i++;
        }
    }
    usort($zones, "sorttz");
    foreach ($zones AS &$zone) {
        $zone[1] = format_offset($zone[1]);
    }
    $master->cache->cacheValue('timezones', $zones);

    return $zones;
}

function format_offset($offset)
{
        $hours = $offset / 3600;
        $sign = $hours > 0 ? '+' : '-';
        $hour = (int) abs($hours);
        $minutes = (int) abs(($offset % 3600) / 60); // for stupid half hour timezones
        if ($hour == 0 && $minutes == 0) $sign = '&nbsp;';
        return "GMT $sign" . str_pad($hour, 2, '0', STR_PAD_LEFT) .':'. str_pad($minutes,2, '0');
}

$flags = scandir($master->publicPath.'/static/common/flags/64', 0);
$flags= array_diff($flags, ['.', '..']);

$Snatched = $master->db->rawQuery(
    "SELECT COUNT(x.uid)
       FROM xbt_snatched AS x
 INNER JOIN torrents AS t ON t.ID=x.fid
      WHERE x.uid = ?",
    [$userID]
)->fetchColumn();

$DefaultOptions = \Luminance\Entities\User::$defaultUserOptions;
$SiteOptions = $DefaultOptions;
if (is_string($UserOptions) === true && empty($UserOptions) === false) {
    $UserOptions = unserialize($UserOptions);
    if (is_array($UserOptions)) {
        $SiteOptions = array_merge($DefaultOptions, $UserOptions);
    }
}

$bbCode = new \Luminance\Legacy\Text;

// Get Forums information for latest forum topics settings
$Forums = $master->repos->forums->getForumInfo();
$ForumCats = $master->repos->forums->getForumCats();

$Level = $classes[$PermissionID]['Level'];
$RestrictedForums = (array)explode(',', $RestrictedForums);
$PermittedForums  = (array)explode(',', $PermittedForums);

$ForumsCatsInfo = [];
foreach ($ForumCats as $ForumCatID => $ForumCatName)
    $ForumsCatsInfo[$ForumCatID] = ['name' => $ForumCatName, 'forums' => []];
foreach ($Forums as $Forum) {
    if (($Forum['MinClassRead'] <= $Level && !in_array($Forum['ID'], $RestrictedForums)) /* Should we show restricted forums aswell? */
        || in_array($Forum['ID'], $PermittedForums))
        $ForumsCatsInfo[$Forum['CategoryID']]['forums'][] = $Forum;
}

$User = $master->repos->users->load($userID);
show_header($User->Username.' > Settings', 'user,validate,bbcode,jquery,jquery.cookie');
echo $Val->GenerateJS('userform');
?>

<div class="thin">
    <h2>User Settings</h2>
    <div class="head"><?=format_username($userID)?> &gt; Settings</div>
    <form id="userform" name="userform" action="" method="post" onsubmit="return formVal();" autocomplete="off">
        <div>
            <input type="hidden" name="action" value="takeedit" />
            <input type="hidden" name="userid" value="<?=$userID?>" />
            <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
        </div>
        <?php if (check_perms('users_mod')) { ?>
            <table cellpadding='6' cellspacing='1' border='0' width='100%' class='border'>
            <tr class="colhead">
                <td colspan="2">
                    <strong>Staff preferences</strong>
                </td>
            </tr>
            <?php if (check_perms('users_view_ips')) { ?>
                <tr>
                <td class="label"><strong>Dev & Dump Usage</strong></td>
                <td>
                    <input type="radio" name="dumpdata" id="dumpdata" value="0" <?php  if (empty($SiteOptions['DumpData'])||$SiteOptions['DumpData']==0) { ?>checked="checked"<?php  } ?> />
                    <label>Off (Show All)</label><br/>
                    <input type="radio" name="dumpdata" id="dumpdata" value="1" <?php  if ($SiteOptions['DumpData']==1) { ?>checked="checked"<?php  } ?> />
                    <label>On (Hide Buttons)</label><br/>
                </td>
            </tr>
            <tr>
            <td class="label"><strong>IP History - Hide Elapsed</strong></td>
                <td>
                    <input type="radio" name="showelapsed" id="showelapsed" value="0" <?php  if (empty($SiteOptions['ShowElapsed'])||$SiteOptions['ShowElapsed']==0) { ?>checked="checked"<?php  } ?> />
                    <label>Off (Show All)</label><br/>
                    <input type="radio" name="showelapsed" id="showelapsed" value="1" <?php  if ($SiteOptions['ShowElapsed']==1) { ?>checked="checked"<?php  } ?> />
                    <label>On (Hide Elapsed)</label><br/>
                </td>
            </tr>
            <tr>
            <td class="label"><strong>IP Searches - Show Additional</strong></td>
                <td>
                    <input type="radio" name="extendedipsearch" id="extendedipsearch" value="0" <?php  if (empty($SiteOptions['ExtendedIPSearch'])||$SiteOptions['ExtendedIPSearch']==0) { ?>checked="checked"<?php  } ?> />
                    <label>Off (Show Regular)</label><br/>
                    <input type="radio" name="extendedipsearch" id="extendedipsearch" value="1" <?php  if ($SiteOptions['ExtendedIPSearch']==1) { ?>checked="checked"<?php  } ?> />
                    <label>On (Show Extended)</label><br/>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>IPs Per Page</strong></td>
                <td>
                    <select name="ipsperpage" id="ipsperpage">
                        <option value="25"<?php  if ($SiteOptions['IpsPerPage'] == 25) { ?>selected="selected"<?php  } ?>>25 (Default)</option>
                        <option value="100"<?php  if ($SiteOptions['IpsPerPage'] == 100) { ?>selected="selected"<?php  } ?>>100</option>
                        <option value="200"<?php  if ($SiteOptions['IpsPerPage'] == 200) { ?>selected="selected"<?php  } ?>>200</option>
                        <option value="400"<?php  if ($SiteOptions['IpsPerPage'] == 400) { ?>selected="selected"<?php  } ?>>400</option>
                    </select>
                </td>
            </tr>
            <?php } ?>
        <?php } ?>
        <table cellpadding='6' cellspacing='1' border='0' width='100%' class='border'>
            <tr class="colhead">
                <td colspan="2">
                    <strong>Site preferences</strong>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Stylesheet</strong></td>
                <td>
                    <select name="stylesheet" id="stylesheet">
<?php  foreach ($master->repos->stylesheets->getAll() as $Style) { ?>
                        <option value="<?=$Style['ID']?>"<?php  if ($Style['ID'] == $StyleID) { ?>selected="selected"<?php  } ?>><?=$Style['ProperName']?></option>
<?php  } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Time Zone</strong></td>
                <td>
                            <select name="timezone" id="timezone">
<?php
                                    $zones = get_timezones_list();
                                    foreach ($zones as $tzone) {
                                        list($zone, $offset)=$tzone;
                                        //$offset = format_offset($offset);
?>                              <option value="<?=$zone?>"<?php  if ($zone == $timeZone) { ?>selected="selected"<?php  } ?>><?="$offset &nbsp;&nbsp;".str_replace(['_', '/'], [' ', ' / '], $zone)?></option>
<?php                                   } ?>
                            </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Time style</strong></td>
                <td>
                    <input type="radio" name="timestyle" value="0" <?php  if (empty($SiteOptions['TimeStyle'])||$SiteOptions['TimeStyle']==0) { ?>checked="checked"<?php  } ?> />
                    <label>Display times as time since (date and time is displayed as tooltip)</label><br/>
                    <input type="radio" name="timestyle" value="1" <?php  if ($SiteOptions['TimeStyle']==1) { ?>checked="checked"<?php  } ?> />
                    <label>Display times as date and time (time since is displayed as tooltip)</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Torrents per page</strong></td>
                <td>
                    <select name="torrentsperpage" id="torrentsperpage">
                        <option value="25"<?php  if ($SiteOptions['TorrentsPerPage'] == 25) { ?>selected="selected"<?php  } ?>>25<?php if ($master->settings->pagination->torrents == 25) {?> (Default)<?php } ?></option>
                        <option value="50"<?php  if ($SiteOptions['TorrentsPerPage'] == 50) { ?>selected="selected"<?php  } ?>>50<?php if ($master->settings->pagination->torrents == 50) {?> (Default)<?php } ?></option>
                        <option value="100"<?php  if ($SiteOptions['TorrentsPerPage'] == 100) { ?>selected="selected"<?php  } ?>>100<?php if ($master->settings->pagination->torrents == 100) {?> (Default)<?php } ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Collages per page</strong></td>
                <td>
                    <select name="collagesperpage" id="collagesperpage">
                        <option value="25"<?php  if ($SiteOptions['CollagesPerPage'] == 25) { ?>selected="selected"<?php  } ?>>25<?php if ($master->settings->pagination->collages == 25) {?> (Default)<?php } ?></option>
                        <option value="50"<?php  if ($SiteOptions['CollagesPerPage'] == 50) { ?>selected="selected"<?php  } ?>>50<?php if ($master->settings->pagination->collages == 50) {?> (Default)<?php } ?></option>
                        <option value="100"<?php  if ($SiteOptions['CollagesPerPage'] == 100) { ?>selected="selected"<?php  } ?>>100<?php if ($master->settings->pagination->collages == 100) {?> (Default)<?php } ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Posts per page (Forum)</strong></td>
                <td>
                    <select name="postsperpage" id="postsperpage">
                        <option value="25"<?php  if ($SiteOptions['PostsPerPage'] == 25) { ?>selected="selected"<?php  } ?>>25<?php if ($master->settings->pagination->posts == 25) {?> (Default)<?php } ?></option>
                        <option value="50"<?php  if ($SiteOptions['PostsPerPage'] == 50) { ?>selected="selected"<?php  } ?>>50<?php if ($master->settings->pagination->posts == 50) {?> (Default)<?php } ?></option>
                        <option value="100"<?php  if ($SiteOptions['PostsPerPage'] == 100) { ?>selected="selected"<?php  } ?>>100<?php if ($master->settings->pagination->posts == 100) {?> (Default)<?php } ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Private Messages Per Page</strong></td>
                <td>
                    <select name="messagesperpage" id="messagesperpage">
                        <option value="25"<?php  if ($SiteOptions['MessagesPerPage'] == 25) { ?>selected="selected"<?php  } ?>>25<?php if ($master->settings->pagination->messages == 25) {?> (Default)<?php } ?></option>
                        <option value="50"<?php  if ($SiteOptions['MessagesPerPage'] == 50) { ?>selected="selected"<?php  } ?>>50<?php if ($master->settings->pagination->messages == 50) {?> (Default)<?php } ?></option>
                        <option value="100"<?php  if ($SiteOptions['MessagesPerPage'] == 100) { ?>selected="selected"<?php  } ?>>100<?php if ($master->settings->pagination->messages == 100) {?> (Default)<?php } ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Torrent Grid</strong></td>
                <td>
                    <input type="checkbox" name="collagecovers" id="collagecovers" <?php  if (!empty($SiteOptions['CollageCovers'])) { ?>checked="checked"<?php  } ?> />
                    <label for="collagecovers">Show collage and bookmark torrent covers</label>
                </td>
            </tr>

            <tr>
                <td class="label"><strong>Torrents previews width</strong></td>
                <td>
                    <input type="number" min="200" max="800" step="25" name="torrentpreviewwidth" value="<?= $SiteOptions['TorrentPreviewWidth'] ?? 200 ?>">
                    <label for="torrentpreviewwidth-forced"><input id="torrentpreviewwidth-forced" type="checkbox" name="torrentpreviewwidth-forced" value="1" <?php selected('TorrentPreviewWidthForced', true, 'checked', $SiteOptions) ?>> <span title="Force this width on all previews">Forced</span></label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Split Torrents by Days</strong></td>
                <td>
                    <input type="checkbox" name="splitbydays" id="splitbydays" <?php  if (!empty($SiteOptions['SplitByDays'])) { ?>checked="checked"<?php  } ?> />
                    <label for="splitbydays">display new day header in browse torrents list</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Category list in torrent search</strong></td>
                <td>
                    <select name="hidecats" id="hidecats">
                        <option value="0"<?php  if ($SiteOptions['HideCats'] == 0) { ?>selected="selected"<?php  } ?>>Open by default.</option>
                        <option value="1"<?php  if ($SiteOptions['HideCats'] == 1) { ?>selected="selected"<?php  } ?>>Closed by default.</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Tag list in torrent search</strong></td>
                <td>
                    <select name="showtags" id="showtags">
                        <option value="1"<?php  if ($SiteOptions['ShowTags'] == 1) { ?>selected="selected"<?php  } ?>>Open by default.</option>
                        <option value="0"<?php  if ($SiteOptions['ShowTags'] == 0) { ?>selected="selected"<?php  } ?>>Closed by default.</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Tags in lists</strong></td>
                <td>
                    <input type="checkbox" name="hidetagsinlists" id="hidetagsinlists" <?php  if (!empty($SiteOptions['HideTagsInLists'])) { ?>checked="checked"<?php  } ?> />
                    <label for="hidetagsinlists">Hide tags in lists</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Max Tags in lists</strong></td>
                <td>
                    <input type="text" name="maxtags" id="maxtags" size="8" value="<?=((int) $SiteOptions['MaxTags'])?>" />
                    <label for="maxtags">The maximum number of tags to show in a torrent list</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Hover info window</strong></td>
                <td>
                    <input type="checkbox" name="hidefloatinfo" id="hidefloatinfo" <?php  if (!empty($SiteOptions['HideFloat'])) { ?>checked="checked"<?php  } ?> />
                    <label for="hidefloatinfo">Hide floating info window on browse torrents page</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Floating Torrent Header</strong></td>
                <td>
                    <input type="checkbox" name="hidedetailssidebar" id="hidedetailssidebar" <?php  if (!empty($SiteOptions['HideDetailsSidebar'])) { ?>checked="checked"<?php  } ?> />
                    <label for="hidedetailssidebar">Hide the floating header in torrents</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Floating Forum Header</strong></td>
                <td>
                    <input type="checkbox" name="hideforumsidebar" id="hideforumsidebar" <?php  if (!empty($SiteOptions['HideForumSidebar'])) { ?>checked="checked"<?php  } ?> />
                    <label for="hideforumsidebar">Hide the floating header in forum</label>
                </td>
            </tr>
            <?php if (check_perms('torrent_review')) { ?>
            <tr>
                <td class="label"><strong>Torrent checker</strong></td>
                <td>
                    <input type="checkbox" name="showtorrentchecker" id="showtorrentchecker" <?php  if (!empty($SiteOptions['ShowTorrentChecker'])) { ?>checked="checked"<?php  } ?> />
                    <label for="showtorrentchecker">Show the username of the staff member who did the last torrent review</label>
                </td>
            </tr>
          <?php } ?>
            <tr>
                <td class="label"><strong>External link behaviour</strong></td>
                <td>
                    <input type="checkbox" name="forcelinks" id="forcelinks" <?php  if (empty($SiteOptions['NotForceLinks'])) { ?>checked="checked"<?php  } ?> />
                    <label for="forcelinks">Force external links to open in a new page</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Add tag behaviour</strong></td>
                <td>
                    <input type="checkbox" name="voteuptags" id="voteuptags" <?php  if (empty($SiteOptions['NotVoteUpTags'])) { ?>checked="checked"<?php  } ?> />
                    <label for="voteuptags">Automatically vote up my added tags</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Accept PMs</strong></td>
                <td>
                    <input type="radio" name="blockPMs" id="blockPMs" value="0" <?php  if (empty($BlockPms)||$BlockPms==0) { ?>checked="checked"<?php  } ?> />
                    <label>All (except blocks)</label><br/>
                    <input type="radio" name="blockPMs" id="blockPMs" value="1" <?php  if ($BlockPms==1) { ?>checked="checked"<?php  } ?> />
                    <label>Friends only</label><br/>
                    <input type="radio" name="blockPMs" id="blockPMs" value="2"  <?php  if ($BlockPms==2) { ?>checked="checked"<?php  } ?> />
                    <label>Staff only</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Accept Gifts</strong></td>
                <td>
                    <input type="radio" name="blockgifts" id="blockgifts" value="0" <?php  if (empty($BlockGifts)||$BlockGifts==0) { ?>checked="checked"<?php  } ?> />
                    <label>All (except blocks)</label><br/>
                    <input type="radio" name="blockgifts" id="blockgifts" value="1" <?php  if ($BlockGifts==1) { ?>checked="checked"<?php  } ?> />
                    <label>Friends only</label><br/>
                    <input type="radio" name="blockgifts" id="blockgifts" value="2"  <?php  if ($BlockGifts==2) { ?>checked="checked"<?php  } ?> />
                    <label>Staff only</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Comments PM</strong></td>
                <td>
                    <input type="checkbox" name="commentsnotify" id="commentsnotify" <?php  if (!empty($CommentsNotify)) { ?>checked="checked"<?php  } ?> />
                    <label for="commentsnotify">Notify me by PM when I receive a comment on one of my torrents or requests</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Subscription</strong></td>
                <td>
                    <input type="checkbox" name="autosubscribe" id="autosubscribe" <?php  if (!empty($SiteOptions['AutoSubscribe'])) { ?>checked="checked"<?php  } ?> />
                    <label for="autosubscribe">Subscribe to topics when posting</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Page Titles</strong></td>
                <td>
                    <input type="checkbox" name="shortpagetitles" id="shortpagetitles" <?php  if (!empty($SiteOptions['ShortTitles'])) { ?>checked="checked"<?php  } ?> />
                    <label for="shortpagetitles">Use short page titles (ie. instead of Forums > Forum-name > Thread-title use just Thread-Title)</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>User Torrents</strong></td>
                <td>
                    <input type="checkbox" name="showusertorrents" id="showusertorrents" <?php  if (empty($SiteOptions['HideUserTorrents']) || $SiteOptions['HideUserTorrents']==0) { ?>checked="checked"<?php  } ?> />
                    <label for="showusertorrents">Show users uploaded torrents on user page (if allowed by that users paranoia settings)</label>
                </td>
            </tr>
                  <tr>
                <td class="label"><strong>Smileys</strong></td>
                <td>
                    <input type="checkbox" name="disablesmileys" id="disablesmileys" <?php  if (!empty($SiteOptions['DisableSmileys'])) { ?>checked="checked"<?php  } ?> />
                    <label for="disablesmileys">Disable smileys</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Avatars</strong></td>
                <td>
                    <input type="checkbox" name="disableavatars" id="disableavatars" <?php  if (!empty($SiteOptions['DisableAvatars'])) { ?>checked="checked"<?php  } ?> />
                    <label for="disableavatars">Disable avatars (disabling avatars also hides user badges)</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Signatures</strong></td>
                <td>
                    <input type="checkbox" name="disablesignatures" id="disablesignatures" <?php  if (!empty($SiteOptions['DisableSignatures'])) { ?>checked="checked"<?php  } ?> />
                    <label for="disablesignatures">Disable Signatures</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Download torrents as text files</strong></td>
                <td>
                    <input type="checkbox" name="downloadalt" id="downloadalt" <?php  if ($DownloadAlt) { ?>checked="checked"<?php  } ?> />
                    <label for="downloadalt">For users whose ISP block the downloading of torrent files</label>
                </td>
            </tr>
        <?php if ($master->options->EnableIPv6Tracker) { ?>
            <tr>
                <td class="label"><strong>Use IPv6 Tracker</strong></td>
                <td>
                    <input type="checkbox" name="trackipv6" id="trackipv6" <?php  if ($TrackIPv6) { ?>checked="checked"<?php  } ?> />
                    <label for="trackipv6">Allow the tracker to broadcast your IPv6 address to the swarm (if you have one)</label>
                </td>
            </tr>
        <?php } ?>
            <tr>
                <td class="label"><strong>Unseeded torrent alerts</strong></td>
                <td>
                    <input type="checkbox" name="unseededalerts" id="unseededalerts" <?=checked($UnseededAlerts)?> />
                    <label for="unseededalerts">Receive a PM alert before your uploads are deleted for being unseeded</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>New torrent indicator</strong></td>
                <td>
                    <input type="checkbox" name="resetlastbrowse" id="resetlastbrowse" />
                    <label for="resetlastbrowse" title="[Current LastBrowsed: <?=$LastBrowse?>] check this option to reset.">Clear the Last Browsed field (will make all torrents appear as (New!) again)</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Disable alerts for forum subscriptions</strong></td>
                <td>
                    <input type="checkbox" name="noforumalerts" id="noforumalerts" <?php  if (!empty($SiteOptions['NoForumAlerts'])) { ?>checked="checked"<?php  } ?>/>
                    <label for="noforumalerts" title="Disable alerts at the top of the page when a new subscribed forum post is made">Disable alerts for forum subscriptions</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Disable alerts for news</strong></td>
                <td>
                    <input type="checkbox" name="nonewsalerts" id="nonewsalerts" <?php  if (!empty($SiteOptions['NoNewsAlerts'])) { ?>checked="checked"<?php  } ?>/>
                    <label for="nonewsalerts" title="Disable alerts at the top of the page when a new news post is made">Disable alerts for news</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Disable alerts for blog</strong></td>
                <td>
                    <input type="checkbox" name="noblogalerts" id="noblogalerts" <?php  if (!empty($SiteOptions['NoBlogAlerts'])) { ?>checked="checked"<?php  } ?>/>
                    <label for="noblogalerts" title="Disable alerts at the top of the page when a new blog post is made">Disable alerts for blog</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Disable alerts for contests</strong></td>
                <td>
                    <input type="checkbox" name="nocontestalerts" id="nocontestalerts" <?php  if (!empty($SiteOptions['NoContestAlerts'])) { ?>checked="checked"<?php  } ?>/>
                    <label for="nocontestalerts" title="Disable alerts at the top of the page when a new contests post is made">Disable alerts for contests</label>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="right">
                    <input type="submit" value="Save Profile" title="Save all changes" />
                </td>
            </tr>
            <tr class="colhead">
                <td colspan="2">
                    <strong>User info</strong>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Avatar URL</strong></td>
                <td>
                    <?php
                        /* -------  Draw a box with imagehost whitelist  ------- */
                        $Whitelist = $master->repos->imagehosts->find("Hidden='0'", null, 'Time DESC', null, 'imagehost_whitelist');
                        list($Updated, $NewWL) = $master->db->rawQuery(
                            "SELECT MAX(iw.Time),
                                    IF(MAX(t.Time) < MAX(iw.Time) OR MAX(t.Time) IS NULL, 1, 0)
                               FROM imagehost_whitelist as iw
                          LEFT JOIN torrents AS t ON t.UserID = ?",
                            [$activeUser['ID']]
                        )->fetch(\PDO::FETCH_NUM);
                        // test $HideWL first as it may have been passed from upload_handle
                        if (!($HideWL ?? false)) {
                            $HideWL = check_perms('torrent_hide_imagehosts') || !$NewWL;
                        } else {
                            $HideWL = false;
                        }
                        ?>
                        <div class="head">Approved Imagehosts</div>
                        <div class="box pad">
                            <span style="float:right;clear:right"><p><?=$NewWL ? '<strong class="important_text">' : '' ?>Last Updated: <?= time_diff($Updated) ?><?= $NewWL ? '</strong>' : '' ?></p></span>

                            <p>You must use one of the following approved imagehosts for all images.
                            <?php  if ($HideWL) { ?>
                                <span><a href="#" onclick="$('#whitelist').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(Show)':'(Hide)'); return false;">(Show)</a></span>
                            <?php  } ?>
                            </p>
                            <table id="whitelist" class="<?= ($HideWL ? 'hidden' : '') ?>" style="">
                                <tr class="colhead_dark">
                                    <td width="50%"><strong>Imagehost</strong></td>
                                    <td><strong>Comment</strong></td>
                                </tr>
                            <?php
                                foreach ($Whitelist as $ImageHost) { ?>
                                        <tr>
                                            <td><?=$bbCode->full_format($ImageHost->Imagehost)?>
                                            <?php
                                                // if a goto link is supplied and is a validly formed url make a link icon for it
                                                if (!empty($ImageHost->Link) && $bbCode->valid_url($ImageHost->Link)) {
                                                    ?><a href="<?= $ImageHost->Link ?>"  target="_blank"><img src="<?=STATIC_SERVER?>common/symbols/offsite.gif" width="16" height="16" style="" alt="Goto <?= $ImageHost->Imagehost ?>" /></a>
                                            <?php  } // endif has a link to imagehost  ?>
                                        </td>
                                        <td><?=$bbCode->full_format($ImageHost->Comment)?></td>
                                    </tr>
                                <?php  } ?>
                            </table>
                        </div>
                    <input class="long" type="text" name="avatar" id="avatar" value="<?=display_str($Avatar)?>" />
                    <p class="min_padding">Maximum Size: <?=$MaxAvatarWidth?>x<?=$MaxAvatarHeight?> pixels and <?=get_size($master->options->AvatarSizeKiB*1024)?></p>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Flag</strong></td>
                <td style="">
                    <span id="flag_image" >
                        <?php  if ($flag && $flag != '??') { ?>
                            <img src="/static/common/flags/64/<?=$flag?>.png" />
                        <?php  } ?>
                    </span>
                    <div style="display:inline-block;vertical-align: top;">
                        <select id="flag" name="flag" onchange="change_flag();" style="margin-top: 25px">
                            <option value="" <?=($flag == '' || $flag == '??') ? 'selected="selected"' : '';?>>none</option>
                   <?php        foreach ($flags as $value) {
                                $value = substr($value, 0, strlen($value) - 4); // remove .png extension
                    ?>
                            <option value="<?=display_str($value)?>" <?=($flag == $value) ? 'selected="selected"' : '';?>><?=$value?></option>
                    <?php       }  ?>
                        </select>
                    </div>
                </td>
            </tr>

<?php       if (check_perms('site_set_language')) {  ?>

            <tr>
                <td class="label"><strong>Language(s)</strong></td>
                <td>
<?php

                $Userlangs = $master->cache->getValue('user_langs_' .$userID);
                if ($Userlangs===false) {
                    $Userlangs = $master->db->rawQuery(
                        "SELECT ul.LangID AS id,
                                l.code,
                                l.flag_cc AS cc,
                                l.language
                           FROM users_languages AS ul
                           JOIN languages AS l ON l.ID = ul.LangID
                          WHERE UserID = ?",
                        [$userID]
                    )->fetchAll(\PDO::FETCH_ASSOC);
                    $master->cache->cacheValue('user_langs_'.$userID, $Userlangs);
                }
                if ($Userlangs) {
?>
                    select language to remove it:<br/>
<?php
                    foreach ($Userlangs as $langresult) {
?>
                    <input type="checkbox" name="del_lang[]" value="<?=$langresult['LangID'] ?? null?>" />
                        <img style="vertical-align: bottom" title="<?=$langresult['language']?>" alt="[<?=$langresult['code']?>]" src="//<?=SITE_URL?>/static/common/flags/iso16/<?=$langresult['cc']?>.png" />
<?php
                    }
?>
                     <br/>
<?php
                }

                $SiteLanguages = $master->cache->getValue('site_languages');
                if ($SiteLanguages===false) {
                    $SiteLanguages = $master->db->rawQuery(
                        "SELECT ID,
                                ID,
                                language
                           FROM languages
                          WHERE active = '1'
                       ORDER BY language"
                    )->fetchAll(\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);
                    $master->cache->cacheValue('site_languages', $SiteLanguages);
                }
?>
                    <div style="display:inline-block;vertical-align: top;">
                        add language:
                        <span id="lang_image">
                        </span>
                        <select id="new_lang" name="new_lang" onchange="change_lang_flag();" style="margin-top: 25px">
                            <option value="" selected="selected" >none</option>
                   <?php        foreach ($SiteLanguages as $key=>$value) {
                                if (!array_key_exists($key, $Userlangs)) { ?>
                                    <option value="<?=$key?>"><?=$value['language']?></option>
                   <?php            }
                            }  ?>
                        </select>
                    </div>
                     <br/>(only staff can see your selected language) <br/>
                </td>
            </tr>
<?php
        }
?>

            <tr>
                <td colspan="2" class="right">
                    <input type="submit" value="Save Profile" title="Save all changes" />
                </td>
            </tr>
            <tr class="colhead">
                <td colspan="2">
                    <strong>User Profile</strong>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="box pad hidden" id="preview_info" style="text-align:left;"></div>
                    <div  class="" id="editor_info" >
                        <?php  $bbCode->display_bbcode_assistant("preview_message_info", get_permissions_advtags($userID, unserialize($CustomPermissions), $permissions)); ?>
                        <textarea id="preview_message_info" name="info" class="long" rows="8"><?=display_str($Info)?></textarea>
                    </div>
                    <input type="button" value="Toggle Preview" onclick="Preview_Toggle('info');" />
                </td>
            </tr>

            <tr>
                <td colspan="2"> &nbsp; </td>
            </tr>
            <tr class="colhead">
                <td colspan="2">
                    <strong>Signature </strong> &nbsp;(max <?=$MaxSigLength?> chars) <span style="text-decoration: underline">max total size</span> (all images and text combined) 1MiB and <?=SIG_MAX_WIDTH?> px * <?=SIG_MAX_HEIGHT?> px
                </td>
            </tr>
            <tr>
                <?php
                $AdvancedTags = get_permissions_advtags($userID, unserialize($CustomPermissions), $permissions);
                ?>
                <td colspan="2">
<?php
        if ($MaxSigLength > 0 || strlen($Signature) > 0) {
 ?>
                    <div class="box pad hidden" id="preview_sig" style="text-align:left;"></div>
                    <div id="editor_sig" >
                        <?php  $bbCode->display_bbcode_assistant("preview_message_sig", $AdvancedTags); ?>
                        <textarea  id="preview_message_sig" name="signature" class="long"  rows="8"><?=display_str($Signature);?></textarea>
                    </div>
                    <input type="button" value="Toggle Preview" onclick="Preview_Toggle('sig');" />
 <?php
        } else {
 ?>
                    <div style="text-align:left;">
                        <?=$bbCode->full_format('You need to get promoted before you can have a signature. see the [url=/articles/view/ranks][b]User Classes[/b][/url] article.', $AdvancedTags) ?>
                    </div>
 <?php
        }
 ?>
                </td>
            </tr>

            <tr>
                <td colspan="2"> &nbsp; </td>
            </tr>
            <tr class="colhead">
                <td colspan="2">
                    <strong>Torrent Footer</strong> &nbsp; <span style="text-decoration: underline">max total size</span> (all images and text combined) 1MiB and <?=TORRENT_SIG_MAX_HEIGHT?> px
                </td>
            </tr>
            <tr>
                <td colspan="2">
<?php
        if ($MaxSigLength > 0 && check_perms('site_torrent_signature')) {
 ?>
                    <div class="box pad hidden" id="preview_torrentsig" style="text-align:left;"></div>
                    <div  class="" id="editor_torrentsig" >
                        <?php  $bbCode->display_bbcode_assistant("preview_message_torrentsig", get_permissions_advtags($userID, unserialize($CustomPermissions), $permissions)); ?>
                        <textarea  id="preview_message_torrentsig" name="torrentsignature" class="long"  rows="8"><?=display_str($TorrentSignature);?></textarea>
                    </div>
                    <input type="button" value="Toggle Preview" onclick="Preview_Toggle('torrentsig');" />
 <?php
        } else {
 ?>
                    <div style="text-align:left;">
                        <?=$bbCode->full_format('You need to get promoted before you can have a torrent footer. see the [url=/articles/view/ranks][b]User Classes[/b][/url] article.', $AdvancedTags) ?>
                    </div>
 <?php
        }
 ?>
                </td>
            </tr>

            <tr>
                <td colspan="2" class="right">
                    <input type="submit" value="Save Profile" title="Save all changes" />
                </td>
            </tr>

            <tr id="latest_forum_topics" class="colhead">
                <td colspan="2">
                    <strong>Latest forum topics</strong>
                </td>
            </tr>
            <tr>
                <td class="label">&nbsp;</td>
                <td>
                    <p>Select the forums <strong>you want to show up</strong> in the latest forum threads box. If you tick "Disable latest forum topics", no box will be shown.</p>
                    <label><input type="checkbox" name="disablelatesttopics" id="disablelatesttopics"<?php if (!empty($SiteOptions['DisableLatestTopics'])) { ?> checked="checked"<?php  } ?>/> Disable latest forum topics</label>
                    <div style="float: right;">
                        <button type="button" id="check_all_disable_lt" onclick="SetAllLatestForumTopicsCheckboxes(true)">(check all)</button>
                        <button type="button" id="uncheck_all_disable_lt" onclick="SetAllLatestForumTopicsCheckboxes(false)">(uncheck all)</button>
                    </div>
                </td>
            </tr>
<?php
        foreach ($ForumsCatsInfo as $ForumCatID => $ForumCat) {
            if (count($ForumCat['forums']) == 0) continue;
?>
            <tr>
                <td class="label"><?=$ForumCat['name']?></td>
                <td>
<?php
            foreach ($ForumCat['forums'] as $Forum) {
                $enabled = !in_array($Forum['ID'], $SiteOptions['DisabledLatestTopics']);
                print '<div class="quarter_width_checkbox_container">';
                printf('<input type="checkbox" name="disable_lt_%d" id="disable_lt_%d"%s/>', $Forum['ID'], $Forum['ID'], $enabled ? 'checked="checked"' : '');
                printf('<label for="disable_lt_%d" class="quarter_width_checkbox">%s</label>', $Forum['ID'], display_str($Forum['Name']));
                print '</div>';
            }
?>
                </td>
            </tr>
            <tr class="vertical_space_small">
                <td class="label"></td>
                <td></td>
            </tr>
<?php
        }
?>

            <tr>
                <td colspan="2" class="right">
                    <input type="submit" value="Save Profile" title="Save all changes" />
                </td>
            </tr>
            <tr id="paranoia" class="colhead">
                <td colspan="2">
                    <strong>Paranoia settings</strong>
                </td>
            </tr>
            <tr>
                <td class="label">&nbsp;</td>
                <td>
                    <p><span class="warning">Note: Paranoia has nothing to do with your security on this site, the only thing affected by this setting is other users ability to see your site activity and taste.</span></p>
                    <p>Select the elements <strong>you want to show</strong> on your profile. For example, if you tick "Show count" for "Snatched", users will be able to see that you have snatched <?=number_format($Snatched)?> torrents. If you tick "Show list", they will be able to see the full list of torrents you've snatched.</p>
                    <p><span class="warning">Some information will still be available in the site log.</span></p>
                </td>
            </tr>
            <tr>
                <td class="label">Recent activity</td>
                <td>
                    <label><input type="checkbox" name="p_lastseen" <?=checked(!in_array('lastseen', $paranoia))?>> Last seen</label>
                </td>
            </tr>
            <tr>
                <td class="label">Preset</td>
                <td>
                    <button type="button" onClick="ParanoiaResetOff()">Show everything</button>
                    <button type="button" onClick="ParanoiaResetStats2()">Show all but snatches</button>
                    <button type="button" onClick="ParanoiaResetStats()">Show stats only</button>
                </td>
            </tr>
            <tr>
                <td class="label">Stats</td>
                <td>
<?php
$UploadChecked = checked(!in_array('uploaded', $paranoia));
$DownloadChecked = checked(!in_array('downloaded', $paranoia));
$RatioChecked = checked(!in_array('ratio', $paranoia));
?>
                    <label><input type="checkbox" name="p_uploaded" onChange="AlterParanoia()"<?=$UploadChecked?> /> Uploaded</label>&nbsp;&nbsp;
                    <label><input type="checkbox" name="p_downloaded" onChange="AlterParanoia()"<?=$DownloadChecked?> /> Downloaded</label>&nbsp;&nbsp;
                    <label><input type="checkbox" name="p_ratio" onChange="AlterParanoia()"<?=$RatioChecked?> /> Ratio</label>
                </td>
            </tr>
            <tr>
                <td class="label">Torrent comments</td>
                <td>
<?php  display_paranoia('torrentcomments'); ?>
                </td>
            </tr>
            <tr>
                <td class="label">Collages started</td>
                <td>
<?php  display_paranoia('collages'); ?>
                </td>
            </tr>
            <tr>
                <td class="label">Collages contributed to</td>
                <td>
<?php  display_paranoia('collagecontribs'); ?>
                </td>
            </tr>
                <td class="label">Requests filled</td>
                <td>
<?php
$RequestsFilledCountChecked = checked(!in_array('requestsfilled_count', $paranoia));
$RequestsFilledBountyChecked = checked(!in_array('requestsfilled_bounty', $paranoia));
$RequestsFilledListChecked = checked(!in_array('requestsfilled_list', $paranoia));
?>
                    <label><input type="checkbox" name="p_requestsfilled_count" onChange="AlterParanoia()" <?=$RequestsFilledCountChecked?> /> Show count</label>&nbsp;&nbsp;
                    <label><input type="checkbox" name="p_requestsfilled_bounty" onChange="AlterParanoia()" <?=$RequestsFilledBountyChecked?> /> Show bounty</label>&nbsp;&nbsp;
                    <label><input type="checkbox" name="p_requestsfilled_list" onChange="AlterParanoia()" <?=$RequestsFilledListChecked?> /> Show list</label>
                </td>
            </tr>
                <td class="label">Requests voted</td>
                <td>
<?php
$RequestsVotedCountChecked = checked(!in_array('requestsvoted_count', $paranoia));
$RequestsVotedBountyChecked = checked(!in_array('requestsvoted_bounty', $paranoia));
$RequestsVotedListChecked = checked(!in_array('requestsvoted_list', $paranoia));
?>
                    <label><input type="checkbox" name="p_requestsvoted_count" onChange="AlterParanoia()" <?=$RequestsVotedCountChecked?> /> Show count</label>&nbsp;&nbsp;
                    <label><input type="checkbox" name="p_requestsvoted_bounty" onChange="AlterParanoia()" <?=$RequestsVotedBountyChecked?> /> Show bounty</label>&nbsp;&nbsp;
                    <label><input type="checkbox" name="p_requestsvoted_list" onChange="AlterParanoia()" <?=$RequestsVotedListChecked?> /> Show list</label>
                </td>
            </tr>
            <tr>
                <td class="label" title="tags you have added/voted on">Tags</td>
                <td>
<?php  display_paranoia('tags'); ?>
                </td>
            </tr>
            <tr>
                <td class="label" title="uploaded torrents">Uploaded</td>
                <td>
<?php  display_paranoia('uploads'); ?>
                </td>
            </tr>
            <tr>
                <td class="label" title="torrents you are currently seeding">Seeding</td>
                <td>
<?php  display_paranoia('seeding'); ?>
                </td>
            </tr>
            <tr>
                <td class="label" title="torrents you are currently leeching">Leeching</td>
                <td>
<?php  display_paranoia('leeching'); ?>
                </td>
            </tr>
            <tr>
                <td class="label" title="torrents you have downloaded 100% of">Snatched</td>
                <td>
<?php  display_paranoia('snatched'); ?>
                </td>
            </tr>
            <tr>
                <td class="label" title="torrent files you have downloaded">Grabbed</td>
                <td>
<?php  display_paranoia('grabbed'); ?>
                </td>
            </tr>
            <tr>
                <td class="label">Miscellaneous</td>
                <td>
                    <label><input type="checkbox" name="p_requiredratio" <?=checked(!in_array('requiredratio', $paranoia))?>> Required ratio</label>
<?php
$Invited = $master->db->rawQuery(
    "SELECT COUNT(UserID)
       FROM users_info
      WHERE Inviter = ?",
    [$userID]
)->fetchColumn();
?>
                    <br /><label><input type="checkbox" name="p_invitedcount" <?=checked(!in_array('invitedcount', $paranoia))?>> Number of users invited</label>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="right">
                    <input type="submit" value="Save Profile" title="Save all changes" />
                </td>
            </tr>
            <tr class="colhead">
                <td colspan="2">
                    <strong>Reset passkey</strong>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Reset passkey</strong></td>
                <td>
                    <input type="checkbox" id="ResetPasskey" name="resetpasskey" />
                    <label for="ResetPasskey">Any active torrents must be downloaded again to continue leeching/seeding.</label>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="right">
                    <input type="submit" value="Save Profile" title="Save all changes"/>
                </td>
            </tr>
        </table>
    </form>
</div>
<?php
show_footer();
