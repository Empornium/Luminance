<?php

# This code was originally part of script_start.php and class_mysql.php

use Luminance\Errors\UserError;
use Luminance\Errors\InputError;
use Luminance\Errors\LegacyError;
use Luminance\Errors\NotFoundError;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\InternalError;

use Luminance\Entities\User;
use Luminance\Entities\Article;
use Luminance\Entities\Forum;
use Luminance\Entities\ForumPost;
use Luminance\Entities\ForumThread;
use Luminance\Entities\Restriction;
use Luminance\Entities\WikiArticle;
use Luminance\Legacy\Alias;

/**
 * Initiate a staffPM conversation to a user (from a staff)
 * @param $toID int The UserID of the recipient
 * @param $subject string, the subject of the staffPM
 * @param $message string, message to start the conversation
 * @param $level int Optional, the permissions level for this message, defaults to 0

 * @return int The ID for the new conversation
 */
function startStaffConversation($toID, $subject, $message, $level = 0) {
    global $master;

    $user = $master->request->user;

    if (empty($toID) || !is_integer_string($toID)) error(0);
    if (!is_integer_string($level)) error(0);

    if (empty($message) || $message == '') error("No message!");
    if (empty($subject) || $subject == '') error("No Subject!");

    $Text = new Luminance\Legacy\Text;
    $Text->validate_bbcode($message, get_permissions_advtags($user->ID));

    $sqltime = sqltime();

    $master->db->rawQuery(
        "INSERT INTO staff_pm_conversations (Subject, Status, Level, UserID, Date, Unread)
              VALUES (?, 'Open', ?, ?, ?, true)",
        [$subject, $level, $toID, $sqltime]
    );
    # New message
    $convID = $master->db->lastInsertID();

    $master->db->rawQuery(
        'INSERT INTO staff_pm_messages (UserID, SentDate, Message, ConvID)
              VALUES (?, ?, ?, ?)',
        [$user->ID, $sqltime, $message, $convID]
    );

    $master->cache->deleteValue('staff_pm_new_' . $toID);

    return $convID;
}


function getNumArrayFromString($arrayAsString, $throwerror = true, $allowzero = false) {
    $arrayAsString = trim($arrayAsString, " ,");
    if (!$arrayAsString) return [];
    $numArray = explode(',', $arrayAsString);
    foreach ($numArray as $key => &$num) {
        $num = trim($num);
        if ($num==='' || ($num==='0' && !$allowzero) || !is_integer_string($num)) {
            if ($num!=='' && $throwerror) error(0);
            else unset($numArray[$key]);
        }
    }
    return $numArray;
}

# Get cached user info, is used for the user loading the page and usernames all over the site
# AND for looking up advanced tags permissions
function user_info($UserID) {
    global $master;
    $User = $master->repos->users->load($UserID);
    if (!$User) {
        # No clue what this is is actually needed for...
        # - answer: in the case of a deleted user (and we have some from early on) this stops the interface from breaking!
        $UserInfo = ['ID'=>'','Username'=>'','PermissionID'=>0,'Paranoia'=>[],'Donor'=>false,'Warned'=>'0000-00-00 00:00:00',
                'Avatar'=>'','Enabled'=>0,'Title'=>'', 'CatchupTime'=>0, 'Visible'=>'1','Signature'=>'','TorrentSignature'=>'',
                'GroupPermissionID'=>0,'ipcc'=>'??'];
        return $UserInfo;
    }
    $UserInfo = $User->info();
    return $UserInfo;
}

# Only used for current user
function user_heavy_info($UserID) {
    global $master;
    $User = $master->repos->users->load($UserID);
    if (!$User) return null;
    $HeavyInfo = $User->heavyInfo();
    return $HeavyInfo;
}

/**
 * update a users last browsed torrent field with a new datetime
 *
 * @param int $userID
 * @param datetime $time value to set LastBrowse field to
 */
function update_last_browse($userID, $time) {
    global $master, $activeUser;
    if (!is_integer_string($userID)) {
        error(0);
    }

    if ($time instanceof \DateTime) {
        $time = $time->format('Y-m-d H:i:s');
    }

    # Update db
    $master->db->rawQuery(
        'UPDATE users_info
            SET LastBrowse = ?
          WHERE UserID = ?
            AND (LastBrowse < ? OR LastBrowse IS NULL)',
        [$time, $userID, $time]
    );

    # Update cache
    $master->repos->users->uncache($userID);

    # Update $activeUser if current user
    if ($activeUser['ID'] == $userID) {
        $activeUser['LastBrowse'] = $time;
    }
}

/**
 * get the users seed leech info (caches for 15 mins)
 *
 * @param int $UserID
 * @return array Returns ['Seeding'=>$Seeding, 'Leeching'=>$Leeching]
 */
function user_peers($UserID) {
    global $master;
    $PeerInfo = $master->cache->getValue('user_peers_' . $UserID);
    if ($PeerInfo === false) {
        $PeerInfo = $master->db->rawQuery(
            "SELECT IF(remaining = 0, 'Seeding', 'Leeching') AS Type, COUNT(DISTINCT t.ID)
               FROM xbt_files_users AS x
               JOIN torrents AS t ON t.ID = x.fid
              WHERE x.uid = ?
                AND x.active = 1
           GROUP BY Type",
            [$UserID]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        $PeerInfo['Seeding'] = $PeerInfo['Seeding'] ?? 0;
        $PeerInfo['Leeching'] = $PeerInfo['Leeching'] ?? 0;
        $master->cache->cacheValue('user_peers_' . $UserID, $PeerInfo, 900);
    }

    return $PeerInfo;
}

/**
 * update a users site_options field with a new value
 *
 * @param int $UserID
 * @param int $NewOptions options to overwrite in format ['OptionName' => $Value, 'OptionName' => $Value]
 */
function update_site_options($UserID, $NewOptions) {
    global $master, $activeUser;
    if (!is_integer_string($UserID)) {
        error(0);
    }
    if (empty($NewOptions) || !is_array($NewOptions)) {
        return false;
    }

    # Get SiteOptions
    $SiteOptions = $master->db->rawQuery(
        'SELECT SiteOptions
           FROM users_info
          WHERE UserID = ?',
        [$UserID]
    )->fetchColumn();

    # Ensure it's an array
    $SiteOptions = (array)unserialize($SiteOptions);

    # Get HeavyInfo
    $HeavyInfo = user_heavy_info($UserID);

    # Insert new/replace old options
    $SiteOptions = array_merge($SiteOptions, $NewOptions);
    $HeavyInfo = array_merge($HeavyInfo, $NewOptions);

    # Update DB
    $master->db->rawQuery(
        'UPDATE users_info
            SET SiteOptions = ?
          WHERE UserID = ?',
        [serialize($SiteOptions), $UserID]
    );

    # Update cache
    $master->repos->users->uncache($UserID);

    # Update $activeUser if the options are changed for the current
    if ($activeUser['ID'] == $UserID) {
        $activeUser = array_merge($activeUser, $NewOptions);
        $activeUser['ID'] = $UserID; # We don't want to allow userid switching
    }
}

function get_avatar_css($MaxAvatarWidth, $MaxAvatarHeight) {
    global $master;
    if (!isset($MaxAvatarWidth))  $MaxAvatarWidth  = $master->options->AvatarWidth;
    if (!isset($MaxAvatarHeight)) $MaxAvatarHeight = $master->options->AvatarHeight;
    $css = 'max-width:' . $MaxAvatarWidth . 'px; max-height:' . $MaxAvatarHeight . 'px;';
    return $css;
}

function get_permissions($permissionID) {
    global $master;
    $permission = $master->repos->permissions->getLegacyPermission($permissionID);
    return $permission;
}

function get_permissions_for_user($userID, $includeCustom = true, $dummy2 = false) {
    global $master;
    $user = $master->repos->users->load($userID);
    if (!is_null($user)) {
        $permissions = $master->auth->getUserPermissions($user, $includeCustom);
    } else {
        $permissions = [];
    }
    return $permissions;
}

# Get whether this user can use adv tags (pass optional params to reduce lookups)
function get_permissions_advtags($UserID, $CustomPermissions = false, $UserPermission = false) {
      $permissionsValues = get_permissions_for_user($UserID, $CustomPermissions, $UserPermission);
      return isset($permissionsValues['site_advanced_tags']) &&  $permissionsValues['site_advanced_tags'];
}

function get_user_badges($UserID, $LimitRows = true) {
    global $master;

    $UserID = (int) $UserID;

    $extra = "";
    $BarLimit    = "";
    $RibbonLimit = "";
    $MedalLimit  = "";

    if ($LimitRows) {
        $extra = "_limit";
        $BarLimit    = "LIMIT 12";
        $RibbonLimit = "LIMIT 24";
        $MedalLimit  = "LIMIT 12";
    }

    $UserBadges = $master->cache->getValue('user_badges_'.$UserID.$extra);
    if (!is_array($UserBadges)) {
        $UserBadges = $master->db->rawQuery(
            "(SELECT ub.ID, ub.BadgeID,  ub.Description,  b.Title, b.Image,
                     IF(ba.ID IS NULL,FALSE,TRUE) AS Auto, b.Type, b.Display, b.Sort
                FROM users_badges AS ub
                JOIN badges AS b ON b.ID = ub.BadgeID
           LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
               WHERE ub.UserID = ? AND b.Display=0
            ORDER BY b.Sort $BarLimit)
            UNION
                (SELECT
                    ub.ID, ub.BadgeID,  ub.Description,  b.Title, b.Image,
                    IF(ba.ID IS NULL,FALSE,TRUE) AS Auto, b.Type, b.Display, b.Sort
                FROM users_badges AS ub
                JOIN badges AS b ON b.ID = ub.BadgeID
                LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
                WHERE ub.UserID = ? AND b.Display=1
                ORDER BY b.Sort $RibbonLimit)
            UNION
                (SELECT
                    ub.ID, ub.BadgeID,  ub.Description,  b.Title, b.Image,
                    IF(ba.ID IS NULL,FALSE,TRUE) AS Auto, b.Type, b.Display, b.Sort
                FROM users_badges AS ub
                JOIN badges AS b ON b.ID = ub.BadgeID
                LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
                WHERE ub.UserID = ? AND b.Display>1
                ORDER BY b.Sort $MedalLimit)
            ORDER BY Display, Sort",
            [$UserID, $UserID, $UserID]
        )->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('user_badges_'.$UserID.$extra, $UserBadges);
    }

    return $UserBadges;
}

function get_user_shop_badges_ids($UserID) {
    global $master;

    $UserID = (int) $UserID;
    $UserBadges = $master->cache->getValue('user_badges_ids_'.$UserID);
    if (!is_array($UserBadges)) {
        $UserBadges = $master->db->rawQuery(
            "SELECT BadgeID
               FROM users_badges AS ub
          LEFT JOIN badges AS b ON b.ID = ub.BadgeID
              WHERE b.Type = 'Shop'
                AND UserID = ?",
            [$UserID]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $master->cache->cacheValue('user_badges_ids_'.$UserID, $UserBadges);
    }

    return $UserBadges;
}

function print_badges_array($UserBadges, $UserLinkID = false) {
    global $master;
    $LastRow=0;
    $html=null;
    $static_server = $master->settings->main->static_server;
    foreach ($UserBadges as $Badge) {
        list($ID, $BadgeID, $Tooltip, $Name, $Image, $Auto, $Type, $Row ) = $Badge;
        if ($LastRow!==$Row && $html !== null) {
            $html .= "<br/>";
        }
        $LastRow=$Row;
        if ($UserLinkID && is_integer_string($UserLinkID)) {
            $html .= '<div class="badge"><a href="/user.php?id='.$UserLinkID.'#userbadges"><img src="'.$static_server.'common/badges/'.$Image.'" title="The '.$Name.'. '.$Tooltip.'" alt="'.$Name.'" /></a></div>';
        } else {
            $html .= '<div class="badge"><img src="'.$static_server.'common/badges/'.$Image.'" title="The '.$Name.'. '.$Tooltip.'" alt="'.$Name.'" /></div>';
        }
    }
    echo $html;
}

function print_latest_forum_topics() {
    global $master;
    echo $master->render->latestForumThreads();
}

/* --------------------------------
* Returns a regex string in the form '/imagehost.com|otherhost.com|imgbox.com/i'
  for fast whitelist checking
  ----------------------------------- */
function get_whitelist_regex() {
    global $master;

    $pattern = $master->cache->getValue('imagehost_regex');
    if ($pattern===false) {
        $hosts = $master->db->rawQuery(
            "SELECT Imagehost
               FROM imagehost_whitelist"
        )->fetchAll(\PDO::FETCH_COLUMN);
        if ($master->db->foundRows()>0) {
            $pattern = '@';
            $div = '';
            foreach ($hosts as $host) {
                if (substr($host, -1) != "/") $host .= '/';
                $pattern .= $div ."^". preg_quote($host, '@');
                $pattern = str_replace('\*', '.*', $pattern);
                $div = '|';
            }
            $pattern .= '@i';
            $master->cache->cacheValue('imagehost_regex', $pattern);
        } else {
            $pattern = '@nohost.com@i';
        }
    }

    return $pattern;
}

function getValidUrlRegex($Extension = '', $Inline = false) {
    $Regex = '/^';
    $Regex .= '(https?|ftps?|irc):\/\/'; # protocol
    $Regex .= '(\w+(:\w+)?@)?'; # user:pass@
    $Regex .= '(';
    $Regex .= '(([0-9]{1,3}\.){3}[0-9]{1,3})|'; # IP or...
    $Regex .= '(([a-z0-9\-\_]+\.)+\w{2,6})'; # sub.sub.sub.host.com
    $Regex .= ')';
    $Regex .= '(:[0-9]{1,5})?'; # port
    $Regex .= '\/?'; # slash?
    $Regex .= '(\/?[0-9a-z\-_.,&=@~%\/:;()+|!#*?]+)*'; # /file
    if (!empty($Extension)) {
        $Regex.=$Extension;
    }

    # query string
    if ($Inline) {
        $Regex .= '(\?([0-9a-z\-_.,%\/\@~&=:;()+*\^$!#|]|\[\d*\])*)?';
    } else {
        $Regex .= '(\?[0-9a-z\-_.,%\/\@[\]~&=:;()+*\^$!#|]*)?';
    }

    $Regex .= '(#[a-z0-9\-_.,%\/\@[\]~&=:;()+*\^$!]*)?'; # #anchor
    $Regex .= '$/i';

    return $Regex;
}

/**
 * Validates the passed imageurl with the passed parameters, and against an image validating regex:
 * '/^(https?):\/\/([a-z0-9\-\_]+\.)+([a-z]{1,5}[^\.])(\/[^<>]+)*$/i'
 *
 * @param string $Imageurl The url to validate
 * @param int $MinLength The min string length
 * @param int $MaxLength The max string length
 * @param string $WhitelistRegex a regex containing valid imagehosts
 * @return mixed Returns TRUE if it validates and a user readable error message if it fails
 */
function validate_imageurl($Imageurl, $MinLength, $MaxLength, $WhitelistRegex) {
    $ErrorMessage = "'{$Imageurl}' is not a valid URL";

    $URLInfo = parse_url($Imageurl);
    if (!$URLInfo) {
        return "{$ErrorMessage} (Bad URL).";
    }
    if (array_key_exists('scheme', $URLInfo) && array_key_exists('host', $URLInfo)) {
        $ImageHost = $URLInfo['scheme'].'://'.$URLInfo['host'];
    } else {
        return "{$ErrorMessage}.";
    }

    if (strlen($Imageurl)>$MaxLength) {
        return "{$ErrorMessage} (must be < {$MaxLength} characters).";
    } elseif (strlen($Imageurl)<$MinLength) {
        return "{$ErrorMessage} (must be > {$MinLength} characters).";
    } elseif (!preg_match('/^(https?):\/\/([a-z0-9\-\_]+\.)+([a-z]{1,5}[^\.])(\/[^<>]+)*$/i', $Imageurl)) {
        return "{$ErrorMessage}.";
    } elseif (!preg_match($WhitelistRegex, $ImageHost.'/')) {
        return "'{$Imageurl}' is not on an approved imagehost ({$ImageHost}).";
    } else {
        # hooray it validated
        return true;
    }
}

function validate_email($email) {
    global $master;
    if ($master->repos->emails->isBlacklisted($email)) {
        return "$email is on a blacklisted email host.";
    } else { # hooray it validated
        return true;
    }
}

# This is used to determine whether the '[Edit]' link should be shown
function can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime, $Flags = 0) {
    global $master;

    $user = $master->request->user;

    if (check_perms('forum_post_edit')) {
        return true; # moderators can edit anything
    }

    if ($AuthorID != $user->ID || ($EditedUserID && $EditedUserID != $user->ID)) {
        return false;
    }

    # How many seconds ago?
    $AddedTime  = time_ago($AddedTime);
    $EditedTime = time_ago($EditedTime);

    # Make sure time_ago() is not false, while allowing 0 as a valid value.
    $IsAddedBeforeLimit  = is_int($AddedTime) && $AddedTime < $master->settings->site->user_edit_post_time;
    $IsEditedBeforeLimit = is_int($EditedTime) && $EditedTime < $master->settings->site->user_edit_post_time;
    $CanEditOwnPost      = check_perms('site_edit_own_posts');

    return ($Flags & ForumPost::TIMELOCKED == 0) || $CanEditOwnPost || $IsAddedBeforeLimit || $IsEditedBeforeLimit;
}

# This function is used to check if the user can submit changes to a comment.
# Prints error if not permitted.
function validate_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime, $Flags = ForumPost::TIMELOCKED) {
    global $master;

    $user = $master->request->user;

    if (check_perms('forum_post_edit')) {
        return; # moderators can edit anything
    }

    if ($AuthorID != $user->ID) {
        error(403, true);
    }

    #TODO_Disabling for now. When a mod edits a post it already auto locks from user edits. Thus blocks users from editing at all even with the post re-unlocked rendering future user edits impossible.
    #if ($EditedUserID && $EditedUserID != $user->ID) {
    #    error("You are not allowed to edit a post that has been edited by moderators.", true);
    #}

    if (( (($Flags & ForumPost::TIMELOCKED) === 0)  ||
        ( time_ago($AddedTime) && time_ago($AddedTime)<($master->settings->site->user_edit_post_time+600) ) ||
        ( time_ago($EditedTime) && time_ago($EditedTime)<($master->settings->site->user_edit_post_time+300) )) ||
          check_perms('site_edit_own_posts')) {
        return;
    } else {
        error("Sorry - you only have ". date('i\m s\s', $master->settings->site->user_edit_post_time). "  to edit your post before it is automatically locked.", true);
    }
}

function sendIntroPM($userID) {
    global $master;
    if ($master->options->IntroPMArticle) {
        $siteName = $master->settings->main->site_name;
        $body = get_article($master->options->IntroPMArticle);
        if ($body) {
            send_pm($userID, 0, "Welcome to {$siteName}", $body);
        }
    }
}

# for getting an article to display on some other page
function get_article($TopicID) {
    global $master;

    $article = $master->repos->articles->getByTopic($TopicID);
    if ($article instanceof Article) {
        return $article->Body;
    } else {
        return null;
    }
}

function flood_check($Table = 'forums_posts') {
    global $master;

    $user = $master->request->user;
    $floodPostTime = $master->settings->site->user_flood_post_time;
    $waitTime = 0;

    if (check_perms('site_ignore_floodcheck')) {
        return true;
    }
    if (!in_array($Table, ['forums_posts','requests_comments','torrents_comments','collages_comments','sm_results'])) {
        error(0);
    }

    if ($Table=='sm_results') {
        $waitTime = $master->db->rawQuery(
            "SELECT ((UNIX_TIMESTAMP(Time)+?)-UNIX_TIMESTAMP(UTC_TIMESTAMP()))
               FROM `{$Table}`
              WHERE UserID = ?
                AND UNIX_TIMESTAMP(Time) >= (UNIX_TIMESTAMP(UTC_TIMESTAMP())-?)",
            [$floodPostTime, $user->ID, $floodPostTime]
        )->fetchColumn();
    } else {
        $waitTime = $master->db->rawQuery(
            "SELECT ((UNIX_TIMESTAMP(AddedTime)+?)-UNIX_TIMESTAMP(UTC_TIMESTAMP()))
               FROM `{$Table}`
              WHERE AuthorID = ?
                AND UNIX_TIMESTAMP(AddedTime) >= (UNIX_TIMESTAMP(UTC_TIMESTAMP())-?)",
            [$floodPostTime, $user->ID, $floodPostTime]
        )->fetchColumn();
    }

    if ($master->db->foundRows() == 0) {
        return true;
    } else {
        $master->flasher->error("Flood Control".PHP_EOL."You must wait <strong>{$waitTime}</strong> seconds before posting again.");
    }
}

# Geolocate an IP address.
function geoip($ip) {
    global $master;

    $geoLocation = $master->repos->geolite2s->resolve($ip);
    return $geoLocation->location->ISOCode ?? '??';
}

function display_ip($ip, $cc = '??', $gethost = false, $baniplink = false) {
    global $master;
    return $master->render->geoip($ip, $gethost, $baniplink);
}

function get_host($ip) {
    static $ID = 0;
    ++$ID;

    return '<span id="host_' . $ID . '">Resolving host ' . $ip . '...<script type="text/javascript">document.addEventListener(\'LuminanceLoaded\', function() { ajax.get(\'/tools.php?action=get_host&ip=' . $ip . '\',function (host) {$(\'#host_' . $ID . '\').raw().innerHTML=host;}); })</script></span>';
}

function lookup_ip($ip) {
    global $master;

    if (!$ip) return false;

    $LookUp = $master->cache->getValue('gethost_'.$ip);
    if ($LookUp===false) {
        $Output = [];
        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $Output = explode(' ', shell_exec('host -W 1 ' . escapeshellarg($ip)));
        }
        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $Output = explode(' ', shell_exec('host -t AAAA -W 1 ' . escapeshellarg($ip)));
        }

        if (count($Output) == 1 && empty($Output[0])) {
            # No output at all implies the command failed
            $LookUp = ''; # pass back empty string for error reporting in ajax call
        }
        if (count($Output) != 5) {
            $LookUp = false;
        } else {
            $LookUp = $Output[4];
            $master->cache->cacheValue('gethost_'.$ip, $LookUp, 0);
        }
    }

    return $LookUp;
}

function enforce_login() {
    global $master;
    $master->auth->legacyEnforceLogin();
}

# Make sure $_GET['auth'] is the same as the user's authorization key
# Should be used for any user action that relies solely on GET.
function authorize($Ajax = false) {
    global $master;

    $user = $master->request->user;
    $activeUser = $master->auth->getLegacyLoggedUser();
    $token = $_REQUEST['auth'] ?? '';
    if ($master->secretary->checkToken($token, 'AuthKey') === false) {
        $master->irker->announceLab($user->Username . " just failed authorize on " . $_SERVER['REQUEST_URI'] . " coming from " . $_SERVER['HTTP_REFERER'] ?? 'unknown');
        error('Invalid authorization key. Go back, refresh, and try again.', $Ajax);
        return false;
    }

    return true;
}

# This function is to include the header file on a page.
# $JSIncludes is a comma separated list of js files to be inclides on
# the page, ONLY PUT THE RELATIVE LOCATION WITHOUT .js
# ex: 'somefile,somdire/somefile'
function show_header($pageTitle = '', $scripts = '', $prerender = null) {
    global $master;

    $scripts = (strlen($scripts)) ? explode(',', $scripts) : [];
    $params = [
        'page_title'  => $pageTitle,
        'bscripts'    => $scripts,
        'wrap'        => false,
    ];
    if (!empty($prerender)) {
        $params['prerender'] = $prerender;
    }

    $master->render->addVars($params);
    $master->getPlugin('Legacy')->renderPage = true;
}

function show_footer($params = []) {
    global $master;
    $master->render->addVars($params);
    $master->getPlugin('Legacy')->renderPage = true;
}

function cut_string($Str, $Length, $Hard = 0, $ShowDots = 1) {
    if (mb_strlen($Str, "UTF-8") > $Length) {
        if ($Hard == 0) {
            # Not hard, cut at closest word
            $CutDesc = mb_substr($Str, 0, $Length, "UTF-8");
            $DescArr = explode(' ', $CutDesc);
            $DescArr = array_slice($DescArr, 0, count($DescArr) - 1);
            $CutDesc = implode(' ', $DescArr);
            if ($ShowDots == 1) {
                $CutDesc.='...';
            }
        } else {
            $CutDesc = mb_substr($Str, 0, $Length, "UTF-8");
            if ($ShowDots == 1) {
                $CutDesc.='...';
            }
        }

        return $CutDesc;
    } else {
        return $Str;
    }
}

/**
 * Highlight all instances of string 'term' in string 'text'
 *
 * @param $hlterm string The string to highlight; the actual html used is: '<span class="$css">$hlterm</span>'
 *
 * @param $text string, which result's page we want if no page is specified
 *
 * @param $css string Optional, which css class to use to highlight the term
 * If this parameter is not specified, defaults to search_highlight
 *
 * @return string The text with 'term' highlighted
 */
function highlight_text_css($hlterm, $text, $css = 'search_highlight') {
    return str_replace($hlterm, "<span class=\"$css\">$hlterm</span>", $text);
}

function get_ratio_color($Ratio) {
    if ($Ratio < 0.1) {
        return 'r00';
    }
    if ($Ratio < 0.2) {
        return 'r01';
    }
    if ($Ratio < 0.3) {
        return 'r02';
    }
    if ($Ratio < 0.4) {
        return 'r03';
    }
    if ($Ratio < 0.5) {
        return 'r04';
    }
    if ($Ratio < 0.6) {
        return 'r05';
    }
    if ($Ratio < 0.7) {
        return 'r06';
    }
    if ($Ratio < 0.8) {
        return 'r07';
    }
    if ($Ratio < 0.9) {
        return 'r08';
    }
    if ($Ratio < 1) {
        return 'r09';
    }
    if ($Ratio < 2) {
        return 'r10';
    }
    if ($Ratio < 5) {
        return 'r20';
    }

    return 'r50';
}

function ratio($dividend, $divisor, $color = true) {
    if ($divisor == 0 && $dividend == 0) {
        return '<span>--</span>';
    } elseif ($divisor == 0) {
        return '<span class="r99 infinity">∞</span>';
    }
    $ratio = number_format(max($dividend / $divisor - 0.005, 0), 2); #Subtract .005 to floor to 2 decimals
    if ($color) {
        $class = get_ratio_color($ratio);
        if ($class) {
            $ratio = '<span class="' . $class . '">' . $ratio . '</span>';
        }
    }

    return $ratio;
}

function get_url($Exclude = false) {
    if ($Exclude !== false) {
        $QueryItems = [];
        parse_str($_SERVER['QUERY_STRING'], $QueryItems);

        $Query = [];
        foreach ($QueryItems as $Key => $Val) {
            if (!in_array(strtolower($Key), $Exclude)) {
                $Query[$Key] = $Val;
            }
        }

        if (empty($Query)) {
            return;
        }

        return display_str(http_build_query($Query));
    } else {
        return display_str($_SERVER['QUERY_STRING']);
    }
}

/**
 * Finds what page we're on and gives it to us, as well as the LIMIT clause for SQL
 * Takes in $_GET['page'] as an additional input
 *
 * @param $PerPage int Results to show per page
 *
 * @param $DefaultResult int Optional, which result's page we want if no page is specified
 * If this parameter is not specified, we will default to page 1
 *
 * @return array(int,string) What page we are on, and what to use in the LIMIT section of a query
 * i.e. "SELECT [...] LIMIT $Limit;"
 */
function page_limit($perPage, $defaultResult = 1, $pageGetVar = 'page') {
    if (!isset($_GET[$pageGetVar])) {
        $page = ceil($defaultResult / $perPage);
        if ($page == 0) $page = 1;
    } else {
        if (!is_integer_string($_GET[$pageGetVar])) {
            error(0);
        }
        $page = $_GET[$pageGetVar];
        if ($page == 0) $page = 1;
    }

    $limit = $perPage * $page - $perPage . ', ' . $perPage;
    return [$page, $limit];
}

function get_pages($StartPage, $TotalRecords, $ItemsPerPage, $ShowPages = 11, $Anchor = '') {
    global $master;
    return $master->render->pagelinks($StartPage, $TotalRecords, $ItemsPerPage, $ShowPages, $Anchor);
}

function get_size($bytes, $levels = 2) {
    $units = [' B', ' KiB', ' MiB', ' GiB', ' TiB', ' PiB', ' EiB', ' ZiB', ' YiB'];
    $bytes = (double) $bytes;
    $steps = 0;

    foreach ($units as $k => $v) {
        $multiplier = pow(1024, $k);
        $threshold = $multiplier * 1024;
        if (abs($bytes) < $threshold) {
            $steps = $k;
            break;
        }
    }
    if (func_num_args() == 1 && $steps >= 4) {
        $levels++;
    }

    $size = $bytes / $multiplier;
    return number_format($size, $levels) . $units[$steps];
}

function get_bytes($Size) {
    list($Value, $Unit) = sscanf($Size, "%f%s");
    $Unit = ltrim($Unit);
    if (empty($Unit)) {
        return $Value ? round($Value) : 0;
    }
    switch (strtolower($Unit[0])) {
        case 'k':
            return round($Value * 1024);
        case 'm':
            return round($Value * 1048576);
        case 'g':
            return round($Value * 1073741824);
        case 't':
            return round($Value * 1099511627776);
        default:
            return 0;
    }
}

/**
 * Check if the input is a natural number:
 * we're converting the input to an int,
 * then a string and compare it to the original
 *
 * @param mixed $Str
 * @return bool
 */
function is_integer_string($Str) {
    if (!is_numeric($Str)) return false;
    if ($Str < 0) return false;
    return (string) $Str === (string) (int) $Str;
}

function file_string($EscapeStr) {
    return str_replace(['"', '*', '/', ':', '<', '>', '?', '\\', '|'], '', $EscapeStr);
}

# This is preferable to htmlspecialchars because it doesn't screw up upon a double escape
function display_str($Str) {
    if ($Str === null || $Str === false || is_array($Str)) {
        return '';
    }
    if ($Str != '' && !is_integer_string($Str)) {
        $Str = make_utf8($Str);
        $Str = htmlspecialchars_decode(htmlentities($Str));
        $Str = preg_replace("/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,6};)/m", "&amp;", $Str);

        $Replace = [
            "'", '"', "<", ">",
            '&#128;', '&#130;', '&#131;', '&#132;', '&#133;', '&#134;', '&#135;',
            '&#136;', '&#137;', '&#138;', '&#139;', '&#140;', '&#142;', '&#145;',
            '&#146;', '&#147;', '&#148;', '&#149;', '&#150;', '&#151;', '&#152;',
            '&#153;', '&#154;', '&#155;', '&#156;', '&#158;', '&#159;'
        ];

        $With = [
            '&#39;', '&quot;', '&lt;', '&gt;',
            '&#8364;', '&#8218;', '&#402;', '&#8222;', '&#8230;', '&#8224;', '&#8225;',
            '&#710;', '&#8240;', '&#352;', '&#8249;', '&#338;', '&#381;', '&#8216;',
            '&#8217;', '&#8220;', '&#8221;', '&#8226;', '&#8211;', '&#8212;', '&#732;',
            '&#8482;', '&#353;', '&#8250;', '&#339;', '&#382;', '&#376;'
        ];

        $Str = str_replace($Replace, $With, $Str);
    }

    return $Str;
}

function make_utf8($Str) {
    if ($Str != "") {
        if (is_utf8($Str)) {
            $Encoding = "UTF-8";
        }
        if (empty($Encoding)) {
            $Encoding = mb_detect_encoding($Str, 'UTF-8, ISO-8859-1');
        }
        if (empty($Encoding)) {
            $Encoding = "ISO-8859-1";
        }
        if ($Encoding == "UTF-8") {
            return $Str;
        } else {
            return @mb_convert_encoding($Str, "UTF-8", $Encoding);
        }
    }
}

function is_utf8($Str) {
    return preg_match(
        '%^(?:
            [\x09\x0A\x0D\x20-\x7E]			        # ASCII
            | [\xC2-\xDF][\x80-\xBF]			      # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]		    # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]		    # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}	    # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}		      # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}	    # plane 16
        )*$%xs',
        $Str
    );
}

function str_plural($Str, $Num) {
    if ($Num==1) return "$Num $Str";
    else return "$Num {$Str}s";
}

# Escape an entire array for output
# $Escape is either true, false, or a list of array keys to not escape
function display_array($Array, $Escape = []) {
    if (is_array($Array)) {
        foreach ($Array as $Key => $Val) {
        if (is_array($Val)) {
            $Array[$Key] = display_array($Val);
            continue;
        }

        if ((!is_array($Escape) && $Escape == true) || !in_array($Key, $Escape)) {
            $Array[$Key] = display_str($Val);
        }
    }

    return $Array;
    }
    return display_str($Array);
}

function split_tags($list) {
    global $master, $newCategories;

    $tags = $master->tagManager->splitTags($list);

    // If we've received a category, we can add it to the list
    if (!empty($_POST['category'])) {
        $tags[] = $newCategories[(int) $_POST['category']]['tag'];
    }

    return array_map('strtolower', $tags);
}

# Removes any inconsistencies in the list of tags before they are split into an array.
function cleanup_tags($s) {
    return preg_replace(['/[^A-Za-z0-9.-]/i', '/^\s*/s', '/\s*$/s', '/\s+/s'], [" ", "", "", " ", ""], $s);
}

# Gets a tag ready for database input and display
function sanitize_tag($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9.-_]/', '', $str);
    return $str;
}

function check_tag_input($str) {
    return preg_match('/[^a-z0-9.]/', $str)==0;
}

function get_tag_synonym($Tag, $Sanitise = true) {
    global $master;

    if ($Sanitise) $Tag = sanitize_tag($Tag);

    $TagName = $master->db->rawQuery(
        'SELECT t.Name
           FROM tags_synonyms AS ts
           JOIN tags as t ON t.ID = ts.TagID
          WHERE Synonym LIKE ?',
        [$Tag]
    )->fetchColumn();

    if (!empty($TagName)) {
        return $TagName;
    } else {
        return $Tag;
    }
}


/**
 * Return whether $Tag is a valid tag - more than 2** char long and not a stupid word
 * (** unless is 'hd','dp','bj','ts','sd','69','mf','3d','hj','bi','tv','dv','da','4k')
 *
 * @param string $Tag The prospective tag to be evaluated
 * @return Boolean representing whether the tag is valid format (not banned)
 */
function is_valid_tag($Tag) {
    global $master;

    $len = strlen($Tag);
    if ($len > $master->options->MaxTagLength) {
        return false;
    }
    # check for exceptions with small tags
    if ($len < $master->options->MinTagLength) {
        if (!in_array($Tag, array_column(getGoodTags(), 'Tag'))) {
            return false;
        }
    }
    # check for useless tags
    if (in_array($Tag, array_column(getBadTags(), 'Tag'))) {
        return false;
    }
    # check for reserved keywords
    if (in_array($Tag, ['and', 'or', 'not'])) {
        return false;
    }

    return true;
}

function getGoodTags() {
    return getGoodBadTags('good');
}

function getBadTags() {
    return getGoodBadTags('bad');
}

function getGoodBadTags($type = 'bad') {
    global $master;

    if (!in_array($type, ['bad', 'good'])) $type = 'bad';

    $tags = $master->cache->getValue($type.'_tags');
    if ($tags===false) {
        $tags = $master->db->rawQuery(
            'SELECT ID, Tag
               FROM tags_goodbad
              WHERE TagType = ?',
            [$type]
        )->fetchAll(\PDO::FETCH_ASSOC);
        $master->cache->cacheValue($type.'_tags', $tags, 0);
    }
    return $tags;
}

function printRstMessage() {
    if (isset($_GET['rst']) && is_integer_string($_GET['rst'])) {
        $Result = (int) $_GET['rst'];
        $ResultMessage = display_str($_GET['msg']);
        $AlertClass = '';
        if ($Result !== 1) {
            $AlertClass = ' alert';
        }
        if ($ResultMessage) {
            ?>
                <div class="messagebar<?= $AlertClass ?>"><?= $ResultMessage ?></div>
            <?php
        }
    }
}

function printTagLinks() {
    ?>
        <div class="linkbox">
            <a <?=($_GET['action']=='official_tags')?'style="font-weight:bold;" ':''?>href="/tools.php?action=official_tags">[Tags Manager]</a>
            <a <?=($_GET['action']=='tags_admin')?'style="font-weight:bold;" ':''?>href="/tools.php?action=tags_admin">[Tags Admin]</a>
            <a <?=($_GET['action']=='tags_activity')?'style="font-weight:bold;" ':''?>href="/tools.php?action=tags_activity">[Tags Activity]</a>
            <a <?=($_GET['action']=='tags_goodbad')?'style="font-weight:bold;" ':''?>href="/tools.php?action=tags_goodbad">[Good &amp; Bad Tag lists Manager]</a>
            <a <?=($_GET['action']=='official_synonyms')?'style="font-weight:bold;" ':''?>href="/tools.php?action=official_synonyms">[Synonyms Manager]</a>
            <a <?=($_GET['action']=='synonyms_admin')?'style="font-weight:bold;" ':''?>href="/tools.php?action=synonyms_admin">[Synonyms Admin]</a>
        </div>
    <?php
}

# Generate a random string
function make_secret($Length = 32) {
    $NumBytes = (int) round($Length / 2);
    $Secret = bin2hex(openssl_random_pseudo_bytes($NumBytes));
    return substr($Secret, 0, $Length);
}

function is_anon($IsAnon) {
    if (!check_perms('site_view_uploaders')) return true;
    elseif (check_perms('users_view_anon_uploaders')) return false;
    else return $IsAnon;
}

function anon_username_ifmatch($Username, $UsernameCheck, $IsAnon = false) {
    return anon_username($Username, $IsAnon && $Username===$UsernameCheck);
}

function anon_username($username, $isAnon = false, $allowOverride = true) {
    # if not anon then just return username
    if (!$isAnon && check_perms('site_view_uploaders')) return $username;
    # if anon ...
    if (check_perms('users_view_anon_uploaders') && $allowOverride) {
        return "anon [$username]";
    } else {
        return 'anon';
    }
}

function torrent_username($userID, $isAnon = false) {
    global $master;

    return $master->render->torrentUsername($userID, $isAnon);
}

/*
  Returns a username string for display
  $class and $Title can be omitted for an abbreviated version
  $IsDonor, $IsWarned and $IsEnabled can be omitted for a *very* abbreviated version
 */
function format_username(
    $UserID,
    $IsDonor = false,
    $IsWarned = false,
    $enabled = 1,
    $class = false,
    $Title = false,
    $DrawInBox = false,
    $GroupPerm = false,
    $DropDown = false,
    $Colorname = false
) {
    global $master;

    $options = [
        'drawInBox' => $DrawInBox,
        'colorname' => $Colorname,
        'dropDown'  => $DropDown,
        'useSpan'   => true,
        'noIcons'   => ($IsDonor === false && $IsWarned === false),
        'noGroup'   => $GroupPerm === false,
        'noClass'   => $class === false,
        'noTitle'   => $Title === false,
    ];

    return $master->render->username($UserID, $options);
}

function make_class_string($classID, $Usespan = false) {
    global $classes;

    if (array_key_exists($classID, $classes)) {
        if ($Usespan === false) {
            return $classes[$classID]['Name'];
        } else {
            return '<span alt="' . $classID . '" class="rank" style="color:#'. $classes[$classID]['Color'] . '">' . $classes[$classID]['Name'] . '</span>';
        }
    }
}

# Write to the group log
function write_group_log($GroupID, $torrentID, $UserID, $Message, $Hidden) {
    global $master;
    $master->db->rawQuery(
        'INSERT INTO group_log (GroupID, TorrentID, UserID, Info, Time, Hidden)
              VALUES (?, ?, ?, ?, ?, ?)',
        [$GroupID, $torrentID, $UserID, $Message, sqltime(), $Hidden]
    );
}

# Write a message to the system log
function write_log($Message) {
    global $master;
    $master->db->rawQuery(
        'INSERT INTO log (Message, Time)
              VALUES (?, ?)',
        [$Message, sqltime()]
    );
}

# write to user admincomment
function write_user_log($UserID, $Comment, $Sqltime = null) {
    global $master;
    if (!$Sqltime) $Sqltime = sqltime();
    $master->db->rawQuery(
        'UPDATE users_info
            SET AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
          WHERE UserID = ?',
        ["$Sqltime - $Comment", $UserID]
    );
}

# Send a message to an IRC bot listening on SOCKET_LISTEN_PORT
function send_irc($Raw) {
    $IRCSocket = @fsockopen(SOCKET_LISTEN_ADDRESS, SOCKET_LISTEN_PORT);
    if (is_resource($IRCSocket)) {
        $Raw = str_replace(["\n", "\r"], '', $Raw);
        fwrite($IRCSocket, $Raw);
        fclose($IRCSocket);
    }
}

function getTorrentUFL($torrentID) {
    global $master;
    if (!is_integer_string($torrentID)) {
        error(0);
    }

    $ufl = $master->db->rawQuery(
        "SELECT t.UserID,
                t.Size,
                (
                    SELECT Min(Cost)
                      FROM bonus_shop_actions
                     WHERE Action = 'ufl'
                       AND Gift = '0'
                       AND (Value * 1024 * 1024 * 1024) < t.Size
                ) AS Cost
           FROM group_log AS l JOIN torrents AS t ON l.TorrentID = t.ID
          WHERE l.TorrentID = ?
            AND t.FreeTorrent = '1'
            AND l.Info LIKE '%bought universal freeleech%'
       GROUP BY l.TorrentID
         HAVING Count(l.ID) > 0",
        [$torrentID]
    )->fetch(\PDO::FETCH_ASSOC);

    # Something bad happened in the DB
    if (!is_array($ufl)) {
        $ufl = [];
        $ufl['Cost'] = 0;
        return $ufl;
    }

    # doublecheck the size, insurance in case the ufl shop items are missing a 0 cost item
    if ($ufl['Size'] >= $master->settings->torrents->auto_freeleech_size) {
        $ufl['Cost'] = 0;
    }

    if (!array_key_exists('Cost', $ufl)) {
        $ufl['Cost'] = 0;
    }

    return $ufl;
}

function refundUflCost($torrentID) {
    global $master;

    $ufl = getTorrentUFL($torrentID);

    if ($ufl['Cost']>0) {
        $sqltime = sqltime();
        $user = $master->repos->users->load($ufl['UserID']);
        $wallet = $user->wallet;
        $wallet->adjustBalance($ufl['Cost']);
        $wallet->addLog(' | +'.number_format($ufl['Cost']).' credits | You were refunded the UFL cost for torrent#'.$torrentID);
        $master->repos->users->uncache($ufl['UserID']);
    }
}

function delete_torrent($ID, $GroupID = 0, $UserID = 0, $RefundUFL = false) {
    global $master;

    if (!$GroupID || !$UserID) {
        list($GroupID, $UserID) = $master->db->rawQuery(
            'SELECT GroupID,
                    UserID
               FROM torrents
              WHERE ID = ?',
            [$ID]
        )->fetch(\PDO::FETCH_NUM);
    }

    $RecentUploads = $master->cache->getValue('recent_uploads_'.$UserID);
    if (is_array($RecentUploads)) {
        foreach ($RecentUploads as $Key => $Recent) {
            if ($Recent['ID'] == $GroupID) {
                $master->cache->deleteValue('recent_uploads_'.$UserID);
            }
        }
    }

    if ($RefundUFL) {
        refundUflCost($ID);
    }

    # delete pending awards (but not awarded ones)
    $master->db->rawQuery(
        "DELETE
           FROM torrents_awards
          WHERE Ducky = '0'
            AND TorrentID = ?",
        [$ID]
    );

    $InfoHash = $master->db->rawQuery(
        'SELECT info_hash
           FROM torrents
          WHERE ID = ?',
        [$ID]
    )->fetchColumn();
    $master->db->rawQuery(
        'DELETE
           FROM torrents
          WHERE ID = ?',
        [$ID]
    );

    $master->tracker->deleteTorrent($InfoHash);

    $master->cache->decrementValue('stats_torrent_count');

    $Count = $master->db->rawQuery(
        'SELECT COUNT(ID)
           FROM torrents
          WHERE GroupID = ?',
        [$GroupID]
    )->fetchColumn();

    if ($Count == 0) {
        delete_group($GroupID);
    } else {
        update_hash($GroupID);
    }

    # Torrent notifications
    $userIDs = $master->db->rawQuery(
        'SELECT UserID
           FROM users_notify_torrents
          WHERE TorrentID = ?',
        [$ID]
    )->fetchAll(\PDO::FETCH_COLUMN);

    foreach ($userIDs as $UserID) {
        $master->cache->deleteValue('notifications_new_'.$UserID);
    }

    $master->db->rawQuery(
        'DELETE
           FROM users_notify_torrents
          WHERE TorrentID = ?',
        [$ID]
    );

    $Reports = $master->db->rawQuery(
        "UPDATE reportsv2
            SET Status = 'Resolved',
                LastChangeTime = ?,
                ModComment = 'Report already dealt with (Torrent deleted)'
        WHERE TorrentID = ?
            AND Status != 'Resolved'",
        [sqltime(), $ID]
    )->rowCount();

    if ($Reports) {
        $master->cache->decrementValue('num_torrent_reportsv2', $Reports);
    }

    $master->db->rawQuery('DELETE FROM torrents_files       WHERE TorrentID = ?', [$ID]);
    $master->db->rawQuery('DELETE FROM torrents_bad_tags    WHERE TorrentID = ?', [$ID]);
    $master->db->rawQuery('DELETE FROM torrents_bad_folders WHERE TorrentID = ?', [$ID]);
    $master->db->rawQuery('DELETE FROM torrents_bad_files   WHERE TorrentID = ?', [$ID]);
    $master->cache->deleteValue('torrent_download_' . $ID);
    $master->cache->deleteValue('torrent_group_' . $GroupID);
    $master->cache->deleteValue('torrents_details_' . $GroupID);
}

function delete_group($GroupID) {
    global $master;

    $master->cache->decrementValue('stats_group_count');

    $master->db->rawQuery('DELETE FROM torrents_reviews WHERE GroupID = ?', [$GroupID]);

    # Collages
    $CollageIDs = $master->db->rawQuery(
        'SELECT CollageID
           FROM collages_torrents
          WHERE GroupID = ?',
        [$GroupID]
    )->fetchAll(\PDO::FETCH_COLUMN);
    if (count($CollageIDs) > 0) {
        $master->db->rawQuery(
            'DELETE FROM collages_torrents WHERE GroupID = ?',
            [$GroupID]
        );

        foreach ($CollageIDs as $CollageID) {
            $master->cache->deleteValue("collage_{$CollageID}");
            $master->cache->deleteValue("collage_torrents_{$CollageID}");
        }
        $master->cache->deleteValue("torrent_collages_{$GroupID}");
    }

    # Requests
    $Requests = $master->db->rawQuery(
        "SELECT ID
           FROM requests
          WHERE GroupID = ?",
        [$GroupID]
    )->fetchAll(\PDO::FETCH_COLUMN);

    $master->db->rawQuery(
        'UPDATE requests
            SET GroupID = NULL
          WHERE GroupID = ?',
        [$GroupID]
    );

    foreach ($Requests as $RequestID) {
        $master->cache->deleteValue('request_'.$RequestID);
    }

    # Decrease the tag count, if it's not in use any longer and not an official tag, delete it from the list.
    $Tags = $master->db->rawQuery(
        "SELECT tt.TagID,
                t.Uses,
                t.TagType,
                t.Name
           FROM torrents_tags AS tt
           JOIN tags AS t ON t.ID = tt.TagID
          WHERE GroupID = ?",
        [$GroupID]
    )->fetchAll(\PDO::FETCH_BOTH);
    foreach ($Tags as $Tag) {
        $Uses = $Tag['Uses'] > 0 ?  $Tag['Uses'] - 1 : 0;
        if ($Tag['TagType'] == 'genre' || $Uses > 0) {
            $master->db->rawQuery(
                'UPDATE tags
                    SET Uses = Uses -1
                  WHERE ID = ?',
                [$Tag['TagID']]
            );
        } else {
            $master->db->rawQuery('DELETE FROM tags WHERE ID = ? AND TagType = ?', [$Tag['TagID'], 'other']);

            # Delete tag cache entry
            $master->cache->deleteValue('tag_id_'.$Tag['Name']);
        }
    }

    $master->db->rawQuery('DELETE FROM group_log WHERE GroupID = ?', [$GroupID]);
    $master->db->rawQuery('DELETE FROM torrents_group WHERE ID = ?', [$GroupID]);
    $master->db->rawQuery('DELETE FROM torrents_tags WHERE GroupID = ?', [$GroupID]);
    $master->db->rawQuery('DELETE FROM torrents_tags_votes WHERE GroupID = ?', [$GroupID]);
    $master->db->rawQuery('DELETE FROM torrents_comments WHERE GroupID = ?', [$GroupID]);
    $master->db->rawQuery('DELETE FROM bookmarks_torrents WHERE GroupID = ?', [$GroupID]);
    # Tell Sphinx that the group is removed
    $master->db->rawQuery('REPLACE INTO sphinx_delta (ID, Time) VALUES (?, UNIX_TIMESTAMP())', [$GroupID]);

    $master->cache->deleteValue('torrents_details_'.$GroupID);
    $master->cache->deleteValue('torrent_group_'.$GroupID);
}

function warn_user($UserID, $Duration, $Reason) {
    global $master;

    $user = $master->request->user;

    $restriction = new Restriction([
        'UserID'  => $UserID,
        'StaffID' => $user->ID,
        'Flags'   => Restriction::WARNED,
        'Created' => new \DateTime,
        'Expires' => new \DateTime("+{$Duration} weeks"),
        'Comment' => $Reason,
    ]);
    $master->repos->restrictions->save($restriction);
}

/* -- update_hash function ------------------------------------------------ */
/* ------------------------------------------------------------------------ */
/* This function is to update the cache and sphinx delta index to keep      */
/* everything up to date                                                    */
/* * ************************************************************************/

function update_hash($GroupID) {
    global $master;

    $master->db->rawQuery(
        "UPDATE torrents_group
            SET TagList=(
         SELECT REPLACE(GROUP_CONCAT(tags.Name ORDER BY  (t.PositiveVotes-t.NegativeVotes) DESC SEPARATOR ' '),'.','_')
           FROM torrents_tags AS t
     INNER JOIN tags ON tags.ID = t.TagID
          WHERE t.GroupID = ?
       GROUP BY t.GroupID)
          WHERE ID = ?",
        [$GroupID, $GroupID]
    );


    $master->db->rawQuery(
        "REPLACE INTO sphinx_delta (ID, GroupName, TagList, NewCategoryID, Image, Time, Size, Snatched, Seeders, Leechers, FreeTorrent, FileList, SearchText)
        SELECT g.ID AS ID,
               g.Name AS GroupName,
               g.TagList,
               g.NewCategoryID,
               g.Image,
               UNIX_TIMESTAMP(g.Time) AS Time,
               MAX(CEIL(t.Size/1024)) AS Size,
               SUM(t.Snatched) AS Snatched,
               SUM(t.Seeders) AS Seeders,
               SUM(t.Leechers) AS Leechers,
               BIT_OR(t.FreeTorrent-1) AS FreeTorrent,
               GROUP_CONCAT(
                  REPLACE(
                      REPLACE(FileList, '|||', '\n '),
                      '_', ' '
                  ) SEPARATOR '\n '
               ) AS FileList,
               g.SearchText
          FROM torrents AS t
          JOIN torrents_group AS g ON g.ID=t.GroupID
         WHERE g.ID = ?
      GROUP BY g.ID",
      [$GroupID]
    );

    $master->cache->deleteValue('torrents_details_'.$GroupID);
    $master->cache->deleteValue('torrent_group_'.$GroupID);
}

# OPTIMISED a bit more for mass sending (only put in an array of numbers if fromID==system (0)
# this function sends a PM to the userid $ToID and from the userid $FromID, sets date to now
# set userid to 0 for a PM from 'system'
# if $ConvID is not set, it auto increments it, ie. starting a new conversation
function send_pm($ToID, $FromID, $Subject, $Body, $ConvID = '') {
    global $master;

    if (!is_array($ToID)) $ToID = [$ToID];

    # Clear the caches of the inbox and sentbox
    foreach ($ToID as $key => $ID) {
        if (!is_integer_string($ID)) return false;
        # Don't allow users to send messages to the system
        if ($ID == 0) unset($ToID[$key]);
        if ($ID == $FromID) unset($ToID[$key]); # or themselves
    }
    if (count($ToID)==0) return false;
    if (count($ToID)>1 && $FromID!==0) return false; # masspms not from the system with the same convID don't work
    $sqltime = sqltime();

    if ($ConvID == '') { # new pm
        $master->db->rawQuery("INSERT INTO pm_conversations (Subject) VALUES (?)", [$Subject]);
        $ConvID = $master->db->lastInsertID();

        $valuesQuery = [];
        if ($FromID != 0) {
            $valuesQuery[] = "(?, ?, '0', '1', '$sqltime', '$sqltime', '0')";
            $params = [$FromID, $ConvID];
        } else {
            $params = [];
        }

        $valuesQuery = array_merge(
            $valuesQuery,
            array_fill(0, count($ToID), "(?, ?, '1', '0', '$sqltime', '$sqltime', '1')")
        );
        foreach ($ToID as $to) {
            $params[] = $to;
            $params[] = $ConvID;
        }

        $valuesQuery = implode(', ', $valuesQuery);

        $master->db->rawQuery(
            "INSERT INTO pm_conversations_users
            (UserID, ConvID, InInbox, InSentbox, SentDate, ReceivedDate, UnRead) VALUES {$valuesQuery}",
            $params
        );
    } else { # responding to exisiting
        $inQuery = implode(',', array_fill(0, count($ToID), '?'));
        $master->db->rawQuery(
            "UPDATE pm_conversations_users SET
                    InInbox = '1',
                    UnRead = '1',
                    ReceivedDate = '$sqltime'
              WHERE UserID IN ({$inQuery})
                AND ConvID = ?",
            array_merge($ToID, [$ConvID])
        );

        $master->db->rawQuery(
            "UPDATE pm_conversations_users SET
                    InSentbox = '1',
                    SentDate = '{$sqltime}'
              WHERE UserID = ?
                AND ConvID = ?",
            [$FromID, $ConvID]
        );
    }

    $master->db->rawQuery(
        "INSERT INTO pm_messages (SenderID, ConvID, SentDate, Body)
              VALUES (?, ?, ?, ?)",
        [$FromID, $ConvID, $sqltime, $Body]
    );

    # Clear the caches of the inbox and sentbox
    foreach ($ToID as $ID) {
        $master->cache->deleteValue('inbox_new_' . $ID);
    }
    if ($FromID != 0) $master->cache->deleteValue('inbox_new_' . $FromID);
    # DEBUG only:
    # write_log("Sent MassPM to ".count($ToID)." users. ConvID: $ConvID  Subject: $Subject");
    return $ConvID;
}

/**
 * Create a new staff PM conversation
 *
 * @param $Subject
 * @param $Message
 * @param int $Level
 * @param int $UserID (0 means System)
 * @return bool
 */
function send_staff_pm($Subject, $Message, $Level = 0, $UserID = 0) {
    global $master;

    $time = sqltime();

    # Create the conversation
    $master->db->rawQuery(
        "INSERT INTO staff_pm_conversations (Subject, Status, Level, UserID, Date)
              VALUES (?, 'Unanswered', ?, ?, ?)",
        [$Subject, $Level, $UserID, $time]
    );

    # Create the message
    $ConvID = $master->db->lastInsertID();
    $master->db->rawQuery(
        'INSERT INTO staff_pm_messages (UserID, SentDate, Message, ConvID)
              VALUES (?, ?, ?, ?)',
        [$UserID, $time, $Message, $ConvID]
    );

    return true;
}

/**
 * Send a PM to a user to notify that staff edited one of their comments/posts.
 *
 * @param $UserID  The original author of the edited comment/post
 * @param $Url     The related URL for the user to get context
 * @return bool
 */
function notify_staff_edit($UserID, $Url) {
    global $master;
    $User = $master->repos->users->load($UserID);
    if ((!(isset($User->heavyInfo()['StaffEditPM']))) || $User->heavyInfo()['StaffEditPM'] === "1") {
        $Subject = 'One of your comments was edited by Staff';
        $Body = "Someone from the Staff team has edited one of your comments, please follow the link below for more details:\n[url={$Url}][/url]";

        send_pm($UserID, 0, $Subject, $Body);
    }

    return true;
}

# Almost all the code is stolen straight from the forums and tailored for new posts only
function create_thread($ForumID, $AuthorID, $Title, $PostBody) {
    global $master;

    if (!$ForumID || !$AuthorID || !is_integer_string($AuthorID) || !$Title || !$PostBody) {
        return -1;
    }

    $author = $master->repos->users->load($AuthorID);
    if (!($author instanceof User)) {
        return -2;
    }

    $thread = new ForumThread([
        'Title'     => $Title,
        'AuthorID'  => $author->ID,
        'ForumID'   => $ForumID,
    ]);
    $master->repos->forumthreads->save($thread);

    $post = new ForumPost([
        'ThreadID'  => $thread->ID,
        'AuthorID'  => $author->ID,
        'AddedTime' => sqltime(),
        'Body'      => $PostBody,
    ]);
    $master->repos->forumposts->save($post);

    # Clear the cache entries
    $master->cache->deleteValue('latest_threads_forum_'.$ForumID);

    return $thread->ID;
}

# Check to see if a user has the permission to perform an action
function check_perms($permissionName, $minClass = 0, $user = null) {
    global $master;

    $user = $master->repos->users->load($user);
    if (!($user instanceof User)) {
        if ($master->request->user instanceof User) {
            $user = $master->repos->users->load($master->request->user->ID);
        } else {
            # No user object means we're not logged in!
            return false;
        }
    }
    return ($master->auth->isAllowed($permissionName, $user) && $user->class->Level >= $minClass);
}

# Check to see if a user has the permission to perform an action
function check_perms_here($Preview, $PermissionName, $MinClass = 0) {
    global $master;
    if ($Preview) {
        return ($master->auth->isAllowedByMinUser($PermissionName) && $master->repos->permissions->getMinUserLevel() >= $MinClass);
    } else {
        return check_perms($PermissionName, $MinClass);
    }
}

function check_force_anon($UserID) {
    global $master;

    $user = $master->request->user;

    return $UserID == $user->ID || check_perms('site_view_uploaders');
}

const CACHE_VERSION = 9;
# Function to get data and torrents for an array of GroupIDs.
# In places where the output from this is merged with sphinx filters, it will be in a different order.
function get_groups($GroupIDs, $Return = true, $fetchTorrents = true, $loadImage = false) {
    global $master;

    $Found = array_flip($GroupIDs);
    $NotFound = array_flip($GroupIDs);
    $Key = $fetchTorrents ? 'torrent_group_' : 'torrent_group_light_';

    foreach ($GroupIDs as $GroupID) {
        $Data = $master->cache->getValue($Key.$GroupID);
        $HasImage = !$loadImage || isset($Data['d']['Image']);
        if (!empty($Data) && (@$Data['ver'] >= CACHE_VERSION) && $HasImage) {
            unset($NotFound[$GroupID]);

            if (!$loadImage) {
                unset($Data['d']['Image']);
            }

            $Found[$GroupID] = $Data['d'];
            if ($fetchTorrents) {
                foreach ($Found[$GroupID]['Torrents'] as $TID => &$TData) {
                    $TorrentPeerInfo = get_peers($TID);
                    $TData[3]=$TorrentPeerInfo['Seeders'];
                    $TData[4]=$TorrentPeerInfo['Leechers'];
                    $TData[5]=$TorrentPeerInfo['Snatched'];
                    $TData['Seeders']=$TorrentPeerInfo['Seeders'];
                    $TData['Leechers']=$TorrentPeerInfo['Leechers'];
                    $TData['Snatched']=$TorrentPeerInfo['Snatched'];
                }
            }
        }
    }

    $IDs = array_flip($NotFound);

    /*
    Changing any of these attributes returned will cause very large, very dramatic site-wide chaos.
    Do not change what is returned or the order thereof without updating:
    torrents, collages, bookmarks, better, the front page,
    and anywhere else the get_groups function is used.
    */
    if (!empty($IDs)) {
        foreach ($IDs as $ID) {
            $Group = $master->db->rawQuery(
                'SELECT ID,
                        Name,
                        TagList,
                        Image,
                        NewCategoryID AS Category
                   FROM torrents_group
                  WHERE ID = ?',
                [$ID]
            )->fetch(\PDO::FETCH_ASSOC);

            # In rare cases, the torrent may not be found (e.g. deleted in between)
            if (empty($Group)) {
                unset($Found[$ID]);
                continue;
            }

            if (!$loadImage) {
                unset($Group['Image']);
            }

            unset($NotFound[$Group['ID']]);
            $Found[$Group['ID']] = $Group;
            $Found[$Group['ID']]['Torrents'] = [];

            if ($fetchTorrents) {
                $Torrents = $master->db->rawQuery(
                    "SELECT t.ID,
                            t.UserID,
                            u.Username,
                            t.GroupID,
                            FileCount,
                            FreeTorrent,
                            DoubleTorrent,
                            Size,
                            Leechers,
                            Seeders,
                            Snatched,
                            t.Time,
                            t.ID AS HasFile,
                            r.ReportCount,
                            t.Anonymous,
                            ta.Ducky
                       FROM torrents AS t
                  LEFT JOIN users AS u ON t.UserID=u.ID
                  LEFT JOIN (SELECT TorrentID, count(*) as ReportCount
                               FROM reportsv2
                              WHERE Type != 'edited'
                                AND Status != 'Resolved'
                           GROUP BY TorrentID) AS r ON r.TorrentID=t.ID
                  LEFT JOIN torrents_awards AS ta ON ta.TorrentID=t.ID
                      WHERE t.GroupID = ?
                   ORDER BY t.ID",
                    [$ID]
                )->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($Torrents as $Torrent) {
                    $Found[$Torrent['GroupID']]['Torrents'][$Torrent['ID']] = $Torrent;

                    $CacheTime = $Torrent['Seeders']==0 ? 120 : 900;
                    $TorrentPeerInfo = ['Seeders'=>$Torrent['Seeders'],'Leechers'=>$Torrent['Leechers'],'Snatched'=>$Torrent['Snatched']];
                    $master->cache->cacheValue('torrent_peers_'.$Torrent['ID'], $TorrentPeerInfo, $CacheTime);

                    $master->cache->cacheValue('torrent_group_'.$Torrent['GroupID'], ['ver'=>CACHE_VERSION, 'd'=>$Found[$Torrent['GroupID']]], 0);
                    $master->cache->cacheValue('torrent_group_light_'.$Torrent['GroupID'], ['ver'=>CACHE_VERSION, 'd'=>$Found[$Torrent['GroupID']]], 0);
                }
            } else {
                foreach ($Found as $Group) {
                    $master->cache->cacheValue('torrent_group_light_'.$Group['ID'], ['ver'=>CACHE_VERSION, 'd'=>$Found[$Group['ID']]], 0);
                }
            }
        }
    }

    if ($Return) { # If we're interested in the data, and not just caching it
        $matches = ['matches'=>$Found, 'notfound'=>array_flip($NotFound)];

        return $matches;
    }
}

function get_peers($torrentID) {
    global $master;

    $TorrentPeerInfo = $master->cache->getValue('torrent_peers_'.$torrentID);
    if ($TorrentPeerInfo===false) {
            # testing with 'dye'
        $TorrentPeerInfo = $master->db->rawQuery("SELECT Seeders, Leechers, Snatched FROM torrents WHERE ID = ?", [$torrentID])->fetch(\PDO::FETCH_ASSOC);
        $CacheTime = $TorrentPeerInfo['Seeders']==0 ? 120 : 900;
        $master->cache->cacheValue('torrent_peers_'.$torrentID, $TorrentPeerInfo, $CacheTime);
    }

    return $TorrentPeerInfo;
}

function get_last_review($GroupID) {
    global $master;

    $LastReviewRow = [];
    $LastReview = $master->cache->getValue('torrent_review_'.$GroupID);
    if ($LastReview===false || $LastReview['ver']<2) {
        $LastReviewRow = $master->db->rawQuery(
            "SELECT tr.ID,
                    tr.Status,
                    tr.Time,
                    tr.KillTime,
                    IF(tr.ReasonID = 0, tr.Reason, rr.Description) AS StatusDescription,
                    tr.ConvID,
                    tr.UserID AS UserID,
                    u.Username AS Username
               FROM torrents_reviews AS tr
          LEFT JOIN review_reasons AS rr ON rr.ID = tr.ReasonID
          LEFT JOIN users AS u ON u.ID=tr.UserID
              WHERE tr.GroupID = ?
           ORDER BY tr.Time DESC
              LIMIT 1",
            [$GroupID]
        )->fetch(\PDO::FETCH_ASSOC);
        $LastReviewRow = (!($LastReviewRow === false)) ? $LastReviewRow : [];
        if (!array_key_exists('ID', $LastReviewRow)) {
            $LastReviewRow['ID'] = 0;
            $LastReviewRow['Status'] = 'Unreviewed';
            $LastReviewRow['StaffID'] = 0;
            $LastReviewRow['Staffname'] = 'System';
            $LastReview = ['ver'=>2, 'd'=>$LastReviewRow];
        } else {
            if ($LastReviewRow['Status']!='Pending') { # if last review log is not from a user
                $LastReviewRow['StaffID']=$LastReviewRow['UserID'];
                $LastReviewRow['Staffname']=$LastReviewRow['Username'];
            } else {
                $LastStaffReview = $master->db->rawQuery(
                    "SELECT tr.UserID AS StaffID,
                            u.Username AS Staffname
                       FROM torrents_reviews AS tr
                  LEFT JOIN users AS u ON u.ID = tr.UserID
                      WHERE tr.GroupID = ?
                        AND tr.Status != 'Pending'
                   ORDER BY tr.Time DESC
                      LIMIT 1 ",
                    [$GroupID]
                )->fetch(\PDO::FETCH_ASSOC);
                $LastReviewRow['StaffID']=$LastStaffReview['StaffID'];
                $LastReviewRow['Staffname']=$LastStaffReview['Staffname'];
            }
            $LastReview = ['ver'=>2, 'd'=>$LastReviewRow];
            $master->cache->cacheValue('torrent_review_'.$GroupID, $LastReview, 0);
        }
    }

    return $LastReview['d'];
}

# moved this here from requests/functions.php as get_requests() is dependent
function get_request_tags($RequestID) {
    global $master;

    $Tags = $master->db->rawQuery(
        "SELECT rt.TagID,
                t.Name
           FROM requests_tags AS rt
           JOIN tags AS t ON rt.TagID=t.ID
          WHERE rt.RequestID = ?
       ORDER BY rt.TagID ASC",
        [$RequestID]
    )->fetchAll(\PDO::FETCH_NUM);
    $Results = [];
    foreach ($Tags as $TagsRow) {
        list($TagID, $TagName) = $TagsRow;
        $Results[$TagID]= $TagName;
    }

    return $Results;
}

# Function to get data from an array of $RequestIDs.
# In places where the output from this is merged with sphinx filters, it will be in a different order.
function get_requests($RequestIDs, $Return = true) {
    global $master;

    $RequestIDs = array_map('intval', $RequestIDs);
    $Found      = array_flip($RequestIDs);
    $NotFound   = array_flip($RequestIDs);

    foreach ($RequestIDs as $RequestID) {
        $Data = $master->cache->getValue('request_' . $RequestID);
        if (!empty($Data)) {
            unset($NotFound[$RequestID]);
            $Found[$RequestID] = $Data;
        }
    }

    $IDs = array_values(array_flip($NotFound));

    /*
     * Don't change without ensuring you change everything else that uses get_requests()
     */
    if (empty($NotFound) === false) {
        $inQuery = implode(',', array_fill(0, count($IDs), '?'));
        $Requests = $master->db->rawQuery(
            "SELECT r.ID AS ID,
                    r.UserID,
                    u.Username,
                    r.TimeAdded,
                    r.LastVote,
                    r.CategoryID,
                    r.Title,
                    r.Image,
                    r.Description,
                    r.FillerID,
                    filler.Username,
                    r.TorrentID,
                    r.TimeFilled,
                    r.GroupID,
                    r.UploaderID,
                    uploader.Username,
                    t.Anonymous
               FROM requests AS r
          LEFT JOIN users AS u
                 ON u.ID = r.UserID
          LEFT JOIN users AS filler
                 ON filler.ID = FillerID
                AND FillerID != 0
          LEFT JOIN users AS uploader
                 ON uploader.ID = r.UploaderID
                AND r.UploaderID != 0
          LEFT JOIN torrents AS t
                 ON t.GroupID = r.TorrentID
              WHERE r.ID IN ({$inQuery})
           ORDER BY ID",
            $IDs
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($Requests as $Request) {
            unset($NotFound[$Request['ID']]);
            $Request['Tags'] = get_request_tags($Request['ID']);
            $Found[$Request['ID']] = $Request;
            $master->cache->cacheValue('request_' . $Request['ID'], $Request, 0);
        }
    }

    if ($Return) { # If we're interested in the data, and not just caching it
        $matches = ['matches' => $Found, 'notfound' => array_flip($NotFound)];

        return $matches;
    }
}

function update_sphinx_requests($RequestID) {
    global $master;

    $master->db->rawQuery("REPLACE INTO sphinx_requests_delta (
                ID, UserID, TimeAdded, LastVote, CategoryID,
                                Title, FillerID, TorrentID,
                TimeFilled, Visible, Votes, Bounty)
            SELECT
                ID, r.UserID, UNIX_TIMESTAMP(TimeAdded) AS TimeAdded,
                UNIX_TIMESTAMP(LastVote) AS LastVote, CategoryID,
                Title, FillerID, TorrentID,
                UNIX_TIMESTAMP(TimeFilled) AS TimeFilled, Visible,
                COUNT(rv.UserID) AS Votes, CEIL(SUM(rv.Bounty)/1024) AS Bounty
            FROM requests AS r LEFT JOIN requests_votes AS rv ON rv.RequestID=r.ID
                wHERE ID = ?
                GROUP BY r.ID",
        [$RequestID]
    );

    $master->cache->deleteValue('request_'.$RequestID);
}

function get_tags($TagNames) {
    global $master;

    $TagIDs = [];
    foreach ($TagNames as $Index => $TagName) {
        $Tag = $master->cache->getValue('tag_id_' . $TagName);
        if (is_array($Tag)) {
            unset($TagNames[$Index]);
            $TagIDs[$Tag['ID']] = $Tag['Name'];
        }
    }
    if (count($TagNames) > 0) {
        $inQuery = implode(',', array_fill(0, count($TagNames), '?'));

        # Using UNION to prevent the query from creating a temporary table, we don't need to join here
        $SQLTagIDs = $master->db->rawQuery("SELECT ID, Name FROM tags WHERE Name IN ({$inQuery})
                    UNION SELECT TagID AS ID, Synonym AS Name FROM tags_synonyms WHERE Synonym IN ({$inQuery})",
            array_merge($TagNames, $TagNames)
        )->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($SQLTagIDs as $Tag) {
            $TagIDs[$Tag['ID']] = $Tag['Name'];
            $master->cache->cacheValue('tag_id_' . $Tag['Name'], $Tag, 0);
        }
    }

    return($TagIDs);
}

function get_overlay_html($GroupName, $Username, $Image, $Seeders, $Leechers, $Size, $Snatched) {
    global $master;

    $OverWidth = (int) ($master->request->user->options('TorrentPreviewWidth') ?? 200);
    $OverWidthForced = $master->request->user->options('TorrentPreviewWidthForced') ? "width='{$OverWidth}px'" : '';
    $OverImage = $Image != '' ? $Image : '/static/common/noartwork/noimage.png';
    $OverImage = fapping_preview($OverImage, $OverWidth);

    $OverName = mb_strlen($GroupName) <= 60 ? $GroupName : mb_substr($GroupName, 0, 56) . '...';
    $SL = ($Seeders == 0 ? "<span class=r00>" . number_format($Seeders) . "</span>" : number_format($Seeders)) . " / " . number_format($Leechers);

    $Overlay = "<table class=overlay><tr><td class=overlay colspan=2><strong>".display_str($OverName)."</strong></td><tr><td class=leftOverlay><img {$OverWidthForced} style='max-width: {$OverWidth}px !important;max-height: unset !important;' src=\"".display_str($OverImage)."\"></td><td class=rightOverlay><strong>Uploader:</strong> $Username<br /><br /><strong>Size:</strong> " . get_size($Size) . "<br /><br /><strong>Snatched:</strong> " . number_format($Snatched) . "<br /><br /><strong>Seeders/Leechers:</strong> " . $SL . "</td></tr></table>";

    return $Overlay;
}

function get_request_overlay_html($RequestTitle, $Username, $Image, $Size, $Votes, $Filled) {
    global $master;

    $OverWidth = (int) ($master->request->user->options('TorrentPreviewWidth') ?? 200);
    $OverWidthForced = $master->request->user->options('TorrentPreviewWidthForced') ? "width='{$OverWidth}px'" : '';
    $OverImage = empty($Image) ? '/static/common/noartwork/noimage.png' : $Image;
    $OverImage = fapping_preview($Image, $OverWidth);

    $Filled = $Filled ? 'Yes' : 'No';

    # Cut request titles that are too long
    $RequestTitle = cut_string($RequestTitle, 60, 1);

    return "<table class=overlay><tr><td class=overlay colspan=2><strong>".display_str($RequestTitle)."</strong></td><tr><td class=leftOverlay><img {$OverWidthForced} style='max-width: {$OverWidth}px !important;max-height: unset !important;' src=\"".display_str($Image)."\"></td><td class=rightOverlay><strong>Requester:</strong> $Username<br /><br /><strong>Bounty:</strong> " . get_size($Size) . "<br /><br /><strong>Votes:</strong> ".$Votes."<br /><br /><strong>Filled:</strong> ".$Filled."</td></tr></table>";
}

function fapping_preview($Image, $Width) {
    # Temporary solution for image load on fapping - TODO proper permanent solution, this is ugly
    if (preg_match('/(fapping.empornium.sx|jerking.empornium.ph)/', $Image)) {
        $Image = preg_replace('|/images/(resize/\d+/)?|', "/images/resize/{$Width}/", $Image);
    }

    return $Image;
}

function torrent_icons($Data, $torrentID, $Review, $IsBookmarked) {
    global $master;
    $SeedClass = '';
    $SeedTooltip = '';
    $FreeClass = '';
    $FreeTooltip = '';
    $Icons = '';
    $BonusClasses = [];

    $user = $master->request->user;

    $torrentUserStatus = $master->cache->getValue('torrent_user_status_'.$user->ID);
    if ($torrentUserStatus === false) {
        $torrentUserStatus = $master->db->rawQuery(
            "SELECT fid as TorrentID,
                    IF(xbt.remaining >  '0', 'L', 'S') AS PeerStatus
               FROM xbt_files_users AS xbt
              WHERE active='1'
                AND uid = ?",
            [$user->ID]
        )->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
        $master->cache->cacheValue('torrent_user_status_'.$user->ID, $torrentUserStatus, 600);
    }


    $SnatchedTorrents = $master->cache->getValue("users_torrents_snatched_{$user->ID}_{$torrentID}");
    if ($SnatchedTorrents===false) {
        $SnatchedTorrents = $master->db->rawQuery(
            "SELECT x.fid,
                    x.fid as TorrentID
               FROM xbt_snatched AS x JOIN torrents AS t ON t.ID = x.fid
              WHERE x.uid = ?
                AND x.fid = ?",
            [$user->ID, $torrentID]
        )->fetchAll(\PDO::FETCH_UNIQUE);

        $master->cache->cacheValue("users_torrents_snatched_{$user->ID}_{$torrentID}", $SnatchedTorrents, 600);
    }

    $GrabbedTorrents = $master->cache->getValue("users_torrents_grabbed_{$user->ID}_{$torrentID}");
    if ($GrabbedTorrents===false) {
        $GrabbedTorrents = $master->db->rawQuery(
            "SELECT ud.TorrentID,
                    ud.TorrentID
               FROM users_downloads AS ud
               JOIN torrents AS t ON t.ID = ud.TorrentID
              WHERE ud.UserID = ?
                AND ud.TorrentID = ?",
            [$user->ID, $torrentID]
        )->fetchAll(\PDO::FETCH_UNIQUE);

        $master->cache->cacheValue("users_torrents_grabbed_{$user->ID}_{$torrentID}", $GrabbedTorrents, 600);
    }

    if ($Data['Ducky'] ?? false) {
        $Icons .= '<span class="icon" title="This torrent was awarded a Golden Ducky award!">';
        $Icons .= $master->render->icon('torrent_icons', 'torrent_ducky');
        $Icons .= '</span>';
    }

    if ($Review) {
        $Icons .= get_status_icon($Review) ;
    }

    if (check_perms('torrent_download_override') || ($master->options->EnableDownloads && (!$Review['Status'] || in_array($Review['Status'], ['Unreviewed', 'Okay'])))) {
        if (array_key_exists($torrentID, $torrentUserStatus)) {
            if ($torrentUserStatus[$torrentID]['PeerStatus'] == 'S') {
                $Icons .= '<span class="icon"><a href="/torrents.php?action=download&amp;id='.$torrentID.'&amp;authkey='.$user->legacy['AuthKey'].'&amp;torrent_pass='.$user->legacy['torrent_pass'].'" title="Currently Seeding Torrent">';
                $Icons .= $master->render->icon('torrent_icons clickable seeding', ['torrent_download', 'torrent_seeding']);
                $Icons .= '</a></span>';
            } elseif ($torrentUserStatus[$torrentID]['PeerStatus'] == 'L') {
                $Icons .= '<span class="icon"><a href="/torrents.php?action=download&amp;id='.$torrentID.'&amp;authkey='.$user->legacy['AuthKey'].'&amp;torrent_pass='.$user->legacy['torrent_pass'].'"  title="Currently Leeching Torrent">';
                $Icons .= $master->render->icon('torrent_icons clickable leeching', ['torrent_download', 'torrent_leeching']);
                $Icons .= '</a></span>';
            }
        } elseif (isset($SnatchedTorrents[$torrentID])) {
            $Icons .= '<span class="icon"><a href="/torrents.php?action=download&amp;id='.$torrentID.'&amp;authkey='.$user->legacy['AuthKey'].'&amp;torrent_pass='.$user->legacy['torrent_pass'].'" title="Previously Snatched Torrent">';
            $Icons .= $master->render->icon('torrent_icons clickable snatched', ['torrent_disk', 'torrent_disk_inner']);
            $Icons .= '</a></span>';
        } elseif (isset($GrabbedTorrents[$torrentID])) {
            $Icons .= '<span class="icon"><a href="/torrents.php?action=download&amp;id='.$torrentID.'&amp;authkey='.$user->legacy['AuthKey'].'&amp;torrent_pass='.$user->legacy['torrent_pass'].'"  title="Previously Grabbed Torrent File">';
            $Icons .= $master->render->icon('torrent_icons clickable grabbed', ['torrent_disk', 'torrent_disk_inner']);
            $Icons .= '</a></span>';
        } elseif (empty($torrentUserStatus[$torrentID])) {
            $Icons .= '<span class="icon"><a href="/torrents.php?action=download&amp;id='.$torrentID.'&amp;authkey='.$user->legacy['AuthKey'].'&amp;torrent_pass='.$user->legacy['torrent_pass'].'" title="Download Torrent">';
            $Icons .= $master->render->icon('torrent_icons clickable download', ['torrent_download', 'torrent_leeching']);
            $Icons .= '</a></span>';
        }
    } else {
        if (array_key_exists($torrentID, $torrentUserStatus)) {
            if ($torrentUserStatus[$torrentID]['PeerStatus'] == 'S') {
                $Icons .= '<span class="icon" title="Warning: You are seeding a torrent that is marked for deletion">';
                $Icons .= $master->render->icon('torrent_icons clickable seeding warned', ['torrent_download', 'torrent_seeding']);
                $Icons .= '</span>';
            } elseif ($torrentUserStatus[$torrentID]['PeerStatus'] == 'L') {
                $Icons .= '<span class="icon" title="Warning: You are downloading a torrent that is marked for deletion">';
                $Icons .= $master->render->icon('torrent_icons clickable leeching warned', ['torrent_download', 'torrent_leeching']);
                $Icons .= '</span>';
            }
        } elseif (isset($SnatchedTorrents[$torrentID])) {
            $Icons .= '<span class="icon" title="Previously Snatched Torrent">';
            $Icons .= $master->render->icon('torrent_icons clickable snatched', ['torrent_disk', 'torrent_disk_inner']);
            $Icons .= '</span>';
        } elseif (isset($GrabbedTorrents[$torrentID])) {
            $Icons .= '<span class="icon" title="Previously Grabbed Torrent File">';
            $Icons .= $master->render->icon('torrent_icons clickable grabbed', ['torrent_disk', 'torrent_disk_inner']);
            $Icons .= '</span>';
        } else {
            $Icons .= '<span class="icon" title="You cannot download a marked Torrent">';
            $Icons .= $master->render->icon('torrent_icons download warned', ['torrent_download', 'torrent_leeching']);
            $Icons .= '</span>';
        }
    }

    $sitewideFreeleech = $master->options->getSitewideFreeleech();
    if ($sitewideFreeleech) {
        $FreeTooltip = "Sitewide Freeleech for ".time_diff($sitewideFreeleech, 2, false, false, 0);
        $FreeClass = 'sitewide_leech';
    }

    $sitewideDoubleseed = $master->options->getSitewideDoubleseed();
    if ($sitewideDoubleseed) {
        $SeedTooltip = "Sitewide Doubleseed for ".time_diff($sitewideDoubleseed, 2, false, false, 0);
        $SeedClass = 'sitewide_seed';
    }

    $TokenTorrents = $master->cache->getValue("users_tokens_{$user->ID}");
    if ($TokenTorrents===false) {
        $TokenTorrents = $master->db->rawQuery(
            "SELECT TorrentID,
                    FreeLeech,
                    DoubleSeed
               FROM users_slots
              WHERE UserID=?",
            [$user->ID]
        )->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('users_tokens_' . $user->ID, $TokenTorrents);
    }

    $tokenedTorrent = $TokenTorrents[$torrentID] ?? null;

    if (!empty($tokenedTorrent) || !empty($user->legacy['personal_freeleech'])) {
        $tokenTime = $tokenedTorrent['FreeLeech'] ?? 0;
        $FreeTime = max($tokenTime, $user->legacy['personal_freeleech']);
        if ($FreeTime > sqltime()) {
            $FreeTooltip = "Personal Freeleech for ".time_diff($FreeTime, 2, false, false, 0);
            $FreeClass = 'personal_leech';
        }
    }

    if (!empty($tokenedTorrent) || !empty($user->legacy['personal_doubleseed'])) {
        $tokenTime = $tokenedTorrent['DoubleSeed'] ?? 0;
        $DoubleTime = max($tokenTime, $user->legacy['personal_doubleseed']);
        if ($DoubleTime > sqltime()) {
            $SeedTooltip = "Personal Doubleseed for ".time_diff($DoubleTime, 2, false, false, 0);
            $SeedClass = 'personal_seed';
        }
    }

    if (array_key_exists('FreeTorrent', (array)$Data)) {
        if ($Data['FreeTorrent'] == '1') {
            $FreeTooltip = "Unlimited Freeleech";
            $FreeClass = 'unlimited_leech';
        } elseif ($Data['FreeTorrent'] == '2') {
            $FreeTooltip = "Neutral Freeleech";
            $FreeClass = 'neutral_leech';
        }
    }

    if (array_key_exists('DoubleTorrent', (array)$Data)) {
        if ($Data['DoubleTorrent'] == '1') {
            $SeedTooltip = "Unlimited Doubleseed";
            $SeedClass = 'unlimited_seed';
        }
    }

    if ($SeedTooltip) {
        $BonusClasses[] = 'torrent_seeding';
    }
    if ($FreeTooltip) {
        $BonusClasses[] = 'torrent_leeching';
    }

    $activeClass = '';
    if (!empty($BonusClasses)) {
        $activeClass = 'bonus';
    }

    # Do it this way so that the base icon is the first in array and thus bottom in the stack
    $BonusClasses = array_merge(['torrent_bonus'], $BonusClasses);

    if (!empty($FreeTooltip)) {
        $Tooltip[] = $FreeTooltip;
    }
    if (!empty($SeedTooltip)) {
        $Tooltip[] = $SeedTooltip;
    }
    if (empty($Tooltip)) {
        $Tooltip = ['This torrent has no active bonus'];
    }

    $Tooltip = implode(' & ', $Tooltip);
    $Icons .= '<span class="icon" title="'.$Tooltip.'">';
    $Icons .= $master->render->icon("torrent_icons {$FreeClass} {$SeedClass} {$activeClass}", $BonusClasses);
    $Icons .= '</span>';

    if ($IsBookmarked) {
        $data = [
            'title'                   => "You have this torrent bookmarked",
            'data-action'             => "unbookmark",
            'data-action-confirm'     => null,
        ];
        $classes = 'torrent_icons clickable bookmark bookmarked';
    } else {
        $data = [
            'title'                   => "Bookmark this torrent",
            'data-action'             => "bookmark",
        ];
        $classes = 'torrent_icons clickable bookmark';
    }

    $groupID = $Data['GroupID'];
    $data['data-action-parameters'] = htmlspecialchars(json_encode(['type' => 'torrent', 'id' => $groupID]));
    $Icons .= '<span class="icon">' . $master->render->icon($classes, 'nav_bookmarks', $data) . '</span>';

    return '<span class="torrent_icon_container">'.$Icons.'</span>';
}

function get_status_icon($Review) {
    global $master;
    $Icon = '';
    if ($Review['Status'] == 'Warned' || $Review['Status'] == 'Pending') {
        if (check_perms('torrent_review')) {
            $Icon .= '<span class="icon" title="'.$Review['Status'].' : ['.$Review['StatusDescription'].'] by '.$Review['Staffname'].'">';
        } else {
            $Icon .= '<span class="icon" title="This torrent will be automatically deleted unless the uploader fixes it">';
        }
        $Icon .= $master->render->icon('torrent_icons', ['torrent_warned', 'torrent_warned_inner']);
        $Icon .= '</span>';
    } elseif ($Review['Status'] == 'Okay') {
        if (check_perms('torrent_review')) {
            $Icon .= '<span class="icon" title="This torrent has been checked by staff ('.$Review['Staffname'].') and is okay" class="icon">';
        } else {
            $Icon .= '<span class="icon" title="This torrent has been checked by staff and is okay" class="icon">';
        }
        $Icon .= $master->render->icon('torrent_icons', 'torrent_okay');
        $Icon .= '</span>';
    }
    return $Icon;
}

function get_num_comments($GroupID) {
    global $master;

    $Results = $master->cache->getValue('torrent_comments_'.$GroupID);
    if ($Results === false) {
          $Results = $master->db->rawQuery("SELECT
                      COUNT(c.ID)
                      FROM torrents_comments as c
                      WHERE c.GroupID = ?",
              [$GroupID]
          )->fetchColumn();
          $master->cache->cacheValue('torrent_comments_'.$GroupID, $Results, 0);
    }

    return $Results;
}

# Echo data sent in a form, typically a text area
function form($Index, $Return = false) {
    $return = '';
    if (!empty($_GET[$Index])) {
        if ($Return) {
            $return = display_str($_GET[$Index]);
        } else {
            echo display_str($_GET[$Index]);
            return;
        }
    }
    return $return;
}

# Check/select tickboxes and <select>s
function selected($Name, $Value, $Attribute = 'selected', $Array = []) {
    # Default to the $_REQUEST array
    if (empty($Array)) $Array = $_REQUEST;
    if (isset($Array[$Name]) && $Array[$Name] !== '') {
        if ($Array[$Name] == $Value) {
            echo ' ' . $Attribute . '="' . $Attribute . '"';
        }
    }
}

function error($status, $ajax = false) {
    if (is_integer_string($status)) {
        switch ($status) {
            case '403':
                $error = new ForbiddenError;
                break;
            case '404':
                $error = new NotFoundError;
                break;
            case '0':
                $error = new InputError;
                break;
            case '-1':
                $error = new UserError(
                    'Invalid Request',
                    'Something was wrong with your request and the server is refusing to fulfill it'
                );
                break;
            default:
                $error = new InternalError(
                    'Unexpected Error',
                    'You have encountered an unexpected error'
                );
                $error->httpStatus = $status;
        }
    } else {
        $error = new LegacyError(
            'Error: Something Went Wrong',
            $status
        );
        $error->httpStatus = 200;
    }

    if ($ajax) {
        $error->returnJSON(true);
    }

    throw $error;
}


function getUserEnabled($userID) {
    global $master;
    $enabled = $master->cache->getValue('enabled_'.$userID);
    if ($enabled===false) {
        $enabled = $master->db->rawQuery(
            'SELECT Enabled
               FROM users_main
              WHERE ID = ?',
            [$userID]
        )->fetchColumn();
        $master->cache->cacheValue('enabled_'.$userID, $enabled, 0);
    }
    return $enabled;
}

/**
 * @param BanReason 0 - Unknown, 1 - Manual, 2 - Ratio, 3 - Inactive, 4 - Cheating.
 */
function disable_users($UserIDs, $AdminComment, $BanReason = 1, $State = 2) {
    global $master;

    if (!is_array($UserIDs)) {
        $UserIDs = [$UserIDs];
    }

    $inQuery = implode(',', array_fill(0, count($UserIDs), '?'));

    $enabledCount = $master->db->rawQuery(
        "SELECT COUNT(*)
           FROM users_main
          WHERE Enabled = '1'
            AND ID IN ({$inQuery})",
        $UserIDs
    )->fetchColumn();

    $sqltime = sqltime();
    $comment = $AdminComment ?? 'Disabled by system';
    $setDownload = $BanReason == 2 ? 'm.Downloaded' : '0';

    $master->db->rawQuery(
        "UPDATE users_info AS i
           JOIN users_main AS m ON m.ID = i.UserID
            SET m.Enabled = ?,
                m.can_leech = '0',
                i.AdminComment = CONCAT(?, i.AdminComment),
                i.BanDate = ?,
                i.BanReason = ?,
                i.RatioWatchDownload = {$setDownload}
          WHERE m.ID IN ({$inQuery})",
        array_merge([$State, "{$sqltime} - {$comment}\n", sqltime(), $BanReason], $UserIDs)
    );

    # Only decrement by the number of users who were enabled
    # but will be disabled now.
    $master->cache->decrementValue('stats_user_count', $enabledCount);
    foreach ($UserIDs as $UserID) {
        $master->cache->deleteValue('enabled_' . $UserID);
        $master->cache->deleteValue('user_stats_' . $UserID);
        $user = $master->repos->users->load($UserID);
        if (is_null($user->IRCNick) === false) {
            $master->irker->deauthUser($user->IRCNick);
            $master->repos->securityLogs->ircDeauth($user->ID, $user->IRCNick);
            $user->IRCNick = null;
            $master->repos->users->save($user);
        }
        $master->repos->users->uncache($UserID);

        $SessionID = $master->db->rawQuery("SELECT ID FROM sessions WHERE UserID = ? AND Active = 1", [$UserID])->fetchColumn();
        if (!($SessionID === false)) {
            $master->cache->deleteValue('_entity_Session_' . $SessionID);
        }

        $master->db->rawQuery('DELETE FROM sessions WHERE UserID = ?', [$UserID]);
    }

    $PassKeys = $master->db->rawQuery(
        "SELECT torrent_pass
           FROM users_main
          WHERE ID in ({$inQuery})",
        $UserIDs
    )->fetchAll(\PDO::FETCH_COLUMN);
    $Concat = "";
    foreach ($PassKeys as $PassKey) {
        if (strlen($Concat) > 4000) {
            $master->tracker->removeUsers($Concat);
            $Concat = $PassKey;
        } else {
            $Concat .= $PassKey;
        }
    }
    $master->tracker->removeUsers($Concat);
}

/** This ends_with is slightly slower when the string is found, but a lot faster when it isn't.
 */
function ends_with($Haystack, $Needle) {
    return substr($Haystack, strlen($Needle) * -1) == $Needle;
}

# amazingly fmod() does not return remainder when var2<var1... this one does
function modulos($var1, $var2) {
    $tmp = $var1/$var2;
    return (float) ( $var1 - ( ( (int) ($tmp) ) * $var2 ) );
}

/**
 * Will freeleech / neutralleech / normalise a set of torrents
 * @param array $torrentIDs An array of torrents IDs to iterate over
 * @param int $FreeNeutral 0 = normal, 1 = fl, 2 = nl
 * @param int $FreeLeechType 0 = Unknown, 1 = Staff picks, 2 = Perma-FL (Toolbox, etc.), 3 = Vanity House
 */
function freeleech_torrents($torrentIDs, $FreeNeutral = 1, $FromShop = false, $Event = '') {
    global $master;

    $user = $master->request->user;

    if (!is_array($torrentIDs)) {
        $torrentIDs = [$torrentIDs];
    }
    $FreeNeutral = (int)$FreeNeutral;

    foreach ($torrentIDs as $torrentID) {
        $torrent = $master->repos->torrents->load($torrentID);
        $torrent->FreeTorrent = $FreeNeutral;
        $master->repos->torrents->save($torrent);

        $master->tracker->updateTorrent($torrent->info_hash, $FreeNeutral);
        $master->cache->deleteValue('torrent_download_' . $torrent->ID);
        if ($FromShop) {
            $verb = $FreeNeutral==0?'removed':'bought';
            write_log($user->Username . " $verb universal freeleech for torrent " . $torrent->ID." (fl=$FreeNeutral)");
            write_group_log($torrent->GroupID, $torrent->ID, $user->ID, "$verb universal freeleech (fl=$FreeNeutral)", 0);
        } elseif ($Event != Null) {
            $verb = $FreeNeutral==0?'removed':'marked';
            write_log($user->Username . " has awarded " . $Event . " to torrent " . $torrent->ID . " (fl=$FreeNeutral)");
            write_group_log($torrent->GroupID, $torrent->ID, $user->ID, "$verb as freeleech (fl=$FreeNeutral)", 0);
        } else {
            $verb = $FreeNeutral==0?'removed':'marked';
            write_log($user->Username . " $verb torrent " . $torrent->ID . " as freeleech (fl=$FreeNeutral)");
            write_group_log($torrent->GroupID, $torrent->ID, $user->ID, "$verb as freeleech (fl=$FreeNeutral)", 0);
        }

        update_hash($torrent->GroupID);
    }
}

/**
 * Convenience function to allow for passing groups to freeleech_torrents()
 */
function freeleech_groups($GroupIDs, $FreeNeutral = 1, $FromShop = false, $Event = '') {
    global $master;

    if (!is_array($GroupIDs)) {
        $GroupIDs = [$GroupIDs];
    }

    $inQuery = implode(',', array_fill(0, count($GroupIDs), '?'));
    $torrentIDs = $master->db->rawQuery("SELECT ID from torrents WHERE GroupID IN ({$inQuery})",
        $GroupIDs
    )->fetchAll(\PDO::FETCH_COLUMN);
    if ($master->db->foundRows()) {
        freeleech_torrents($torrentIDs, $FreeNeutral, $FromShop, $Event);
    }
}

/* Just a way to get a image url from the symbols folder */

function get_symbol_url($image) {
    global $master;
    return $master->settings->main->static_server . 'common/symbols/' . $image;
}

/**
 * Send credits and a PM to this user congratulating them on getting a ducky award, unsets applicable torrent cache also
 * @param $userID
 */
function send_ducky_reward($userID, $torrentID) {
    global $master, $activeUser;

    $user = $master->repos->users->load($userID);

    $reward = 4000;  # could be a site option
    $rewardtext = number_format($reward);

    #TODO: this text can be read from system_pms when we have new system up
    send_pm(
        $userID,
        0,
        "You have been awarded the Golden Ducky award!",
        "[center][br][br][img]".SSL_SITE_URL."/resources/icons/svg/torrent_ducky.svg[/img][br]".
        "[size=4][color=white][bg=#0261a3][br]The Golden Ducky, awarded for uploading your first torrent.[br]".
        "[torrent]{$torrentID}[/torrent][br][/bg][/color][/size][/center][br][br]".
        "[size=2]The award of the Golden Ducky gives you a ducky icon on your first torrent, ".
        "and as a little thank-you you have been given a gift of {$rewardtext} credits :emplove:[/size]"
    );


    $sqltime = sqltime();
    $bonuslog = ' | +'.ucfirst("$rewardtext credits | You received $rewardtext credits on the award of the Golden Duck");
    $userlog  = $sqltime.' - '.ucfirst("Awarded Golden Duck and $rewardtext credits for:\n[torrent]{$torrentID}[/torrent]");

    $wallet = $user->wallet;

    $wallet->adjustBalance($reward);
    $wallet->addLog($bonuslog);

    $master->db->rawQuery(
        'UPDATE users_info
            SET AdminComment=CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
          WHERE userID = ?',
        [$userlog, $userID]
    );

    $master->cache->deleteValue("torrents_details_{$torrentID}");
    $master->cache->deleteValue("torrent_group_{$torrentID}");
    $master->cache->deleteValue("torrent_group_light_{$torrentID}");
    $master->repos->users->uncache($userID);
}

/**
 * Remove any ducky award record (pending or awarded) this user has, unsets applicable torrent cache also
 * @param $userID
 */
function remove_ducky($userID) {
    global $master;
    $torrentID = $master->db->rawQuery(
        'SELECT TorrentID
           FROM torrents_awards
          WHERE UserID = ?',
        [$userID]
    )->fetchColumn();
    $master->db->rawQuery('DELETE FROM torrents_awards WHERE UserID = ?', [$userID]);
    if ($torrentID) {
        $master->cache->deleteValue('torrents_details_'.$torrentID);
        $master->cache->deleteValue('torrent_group_'.$torrentID);
        $master->cache->deleteValue('torrent_group_light_'.$torrentID);
    }
}

/**
 * called on staff setting as okay - check if this user has a first torrent uploaded and give award if so (and meets criteria)
 * @param $userID - the user to check against
 * @param $checkTorrentID - a torrent that has been marked okay by staff. If a torrentID is passed the first torrent must match the parameter, if $checkTorrentID === 0 then it ignores this criteria and just awards/makes pending the users first torrent
 */
function award_ducky_check($userID, $checkTorrentID) {
    global $master;
    $minSnatched = 1; # could be a site option

    # check if there is any awarded or pending award already
    $hasAward = $master->db->rawQuery(
        'SELECT TorrentID
           FROM torrents_awards
          WHERE UserID = ?',
        [$userID]
    )->fetchColumn();
    if (!$hasAward) {
        $firstTorrent = $master->db->rawQuery(
            'SELECT ID,
                    Snatched
               FROM torrents
              WHERE UserID = ?
           ORDER BY Time ASC
              LIMIT 1',
            [$userID]
        )->fetch(\PDO::FETCH_ASSOC);
        # if $checkTorrentID is set then check the checked id is the same as the first torrent
        if ($firstTorrent && ($checkTorrentID===0 || $checkTorrentID==$firstTorrent['ID'])) {
            $ducky = $firstTorrent['Snatched']>=$minSnatched?'1':'0';
            # inserts a record, if ducky=0 the award will not be given now but will be checked from scheduler
            $master->db->rawQuery(
                'INSERT INTO torrents_awards (UserID, TorrentID, Ducky)
                      VALUES (?, ?, ?)
                          ON DUPLICATE KEY
                      UPDATE TorrentID=VALUES(TorrentID),
                             Ducky=VALUES(Ducky)',
                [$userID, $firstTorrent['ID'], $ducky]
            );
            if ($ducky) send_ducky_reward($userID, $firstTorrent['ID']);
        }
    }
}

/**
 * called from the scheduler - check entries in the ducky table that have not been awarded yet
 */
function award_ducky_pending() {
    global $master;
    $minSnatched = 1; # could be a site option

    # in theory this doesnt happen but if somehow we end up with a pending award for a deleted torrent this cleans it up
    $master->db->rawQuery(
        "DELETE ta FROM torrents_awards AS ta
      LEFT JOIN torrents AS t ON ta.UserID = t.UserID AND ta.TorrentID = t.ID
          WHERE ta.Ducky = '0'
            AND t.ID IS NULL"
    );

    # get all the users who have a pending torrent ducky award - torrents that have been okayed that now have Snatched>1
    $pending = $master->db->rawQuery(
        "SELECT t.ID, t.GroupID, t.UserID
           FROM torrents AS t
           JOIN torrents_awards AS ta ON ta.TorrentID = t.ID
          WHERE ta.Ducky = '0'
            AND t.Snatched >= ?",
        [$minSnatched]
    )->fetchAll(\PDO::FETCH_ASSOC);
    $torrents = [];
    foreach ($pending as $ducky) {
        # send creds & a pm to all awardees
        send_ducky_reward($ducky['UserID'], $ducky['ID']);
        $torrents[] = $ducky['ID'];
    }
    # update ducky table
    if (count($torrents)>0) {
        $inQuery = implode(',', array_fill(0, count($torrents), '?'));
        $master->db->rawQuery(
            "UPDATE torrents_awards
                SET Ducky='1'
              WHERE TorrentID IN ({$inQuery})",
            $torrents
        );
    }
    # returning this for debugging from sandbox
    return $pending;
}

function getCollageName(int $collageID): string {
    global $master;

    $collage = $master->repos->collages->load($collageID);
    if ($collage instanceof \Luminance\Entities\Collage) {
        return $collage->Name;
    }

    return '';
}

function getArticleTitle(string $topicID): string|null {
    global $master;

    $article = $master->repos->articles->getByTopic($topicID);
    if ($article instanceof \Luminance\Entities\Article) {
        $user = $master->request->user;
        if ($article->MinClass <= $user->class->Level) {
            return $article->Title;
        }
    }

    return null;
}

function getThreadName(int $threadID): string {
    global $master;

    $thread = $master->repos->forumThreads->load($threadID);
    if ($thread instanceof ForumThread) {
        if ($thread->forum instanceof Forum) {
            $permitted = $thread->forum->canRead($master->request->user);
            if ($permitted) {
                return $thread->Title;
            }
        }
    }

    return '';
}

function getForumName(int $forumID): string {
    global $master;

    $forum = $master->repos->forums->load($forumID);
    if ($forum instanceof Forum) {
        $permitted = $forum->canRead($master->request->user);
        if ($permitted) {
            return $forum->Name;
        }
    }

    return '';
}

function getUserName(int $userID): string {
    global $master;

    $user = $master->repos->users->load($userID);
    if ($user instanceof User) {
        return $user->Username;
    } else {
        return 'System';
    }
}

function getStaffPMSubject(int $staffPMID): string|null {
    global $master;

    $staffPM = $master->db->rawQuery(
        'SELECT Subject,
                Level,
                UserID,
                AssignedToUser
           FROM staff_pm_conversations
          WHERE ID = ?',
        [$staffPMID]
    )->fetch(\PDO::FETCH_ASSOC);

    if ($staffPM === null) {
        return null;
    }

    $userID      = $master->request->user->ID;
    $userLevel   = $master->request->user->class->Level;
    $userStaff   = $master->request->user->class->DisplayStaff;
    $userSupport = !empty($master->request->user->legacy['SupportFor']);

    # Basic StaffPM access.
    if ($userLevel >= $staffPM['Level'] && $userStaff) {
        return $staffPM['Subject'];
    }

    # For assigned to a specific staffer (does level change also?)
    if ($userID == $staffPM['AssignedToUser']) {
        return $staffPM['Subject'];
    }

    # For the user rather than staff
    if ($userID == $staffPM['UserID']) {
        return $staffPM['Subject'];
    }

    # For FLS access
    if ($userSupport && $staffPM['Level'] == 0) {
        return $staffPM['Subject'];
    }

    return null;
}

function getTorrentFile($torrentID, $Passkey) {
    global $master;
    # Override the GroupID passed to function.
    $groupID = $master->db->rawQuery(
        'SELECT GroupID
           FROM torrents
          WHERE ID = ?',
        [$torrentID]
    )->fetchColumn();

    $contents = $master->db->rawQuery(
        'SELECT File
           FROM torrents_files
          WHERE TorrentID = ?',
        [$torrentID]
    )->fetchColumn();

    $metadata = $master->db->rawQuery(
        'SELECT *
           FROM torrents_group
          WHERE ID = ?',
        [$groupID]
    )->fetch(\PDO::FETCH_ASSOC);

    $contents = unserialize(base64_decode($contents));
    $TorrentFile = new Luminance\Legacy\Torrent($contents, true); # new Torrent object
    # Set torrent announce URL
    $TorrentFile->set_announce_url($master->settings->main->announce_url.'/'.$Passkey.'/announce');

    $TorrentFile->set_comment('http'.($master->request->ssl ? 's' : '').'://'.$master->settings->main->site_url."/torrents.php?id=$groupID");

    if ($metadata !== false) {
        $TorrentFile->set_metadata($metadata);
    }

    if ($master->settings->main->announce_urls) {
        $announce_urls = [];
        foreach (explode('|', $master->settings->main->announce_urls) as $u) {
            $announce_urls[] = $u.'/'.$Passkey.'/announce';
        }
        $TorrentFile->set_multi_announce($announce_urls);
    } else {
        unset($TorrentFile->Val['announce-list']);
    }

    # Remove web seeds (put here for old torrents not caught by previous commit
    unset($TorrentFile->Val['url-list']);
    # Remove libtorrent resume info
    unset($TorrentFile->Val['libtorrent_resume']);

    return $TorrentFile;
}

function trim_filter($Filter) {
    return preg_replace('/\W/', '', $Filter);
}

/**
 * Get enabled users count
 *
 * @return int
 */
function user_count() {
    global $master;
    $userCount = $master->cache->getValue('stats_user_count');

    if ($userCount === false) {
        $userCount = $master->db->rawQuery("SELECT COUNT(ID) FROM users_main WHERE Enabled = '1'")->fetchColumn();
        $master->cache->cacheValue('stats_user_count', $userCount, 0);
    }

    return (int) $userCount;
}

/**
 * Wrapper function used early in entry.php
 *
 * @param $errno int, the level of the error raised, as an integer.
 * @param $errstr string, the error message, as a string.
 */
function composer_unfound($errno, $errstr) {
    print("You must install composer to use this software, please read INSTALL.md for details\n");
    die();
}

/**
 * Function to parse a tag search string and convert it into a fulltext boolean search query.
 */
function parse_tag_search($tagList) {

    # convert long operators to short
    $tagList = preg_replace(['/\bnot\b/', '/\bor\b/', '/\band\b/'], [' - ', ' | ', ' & '], $tagList);

    # Break up the list into bracket sets.
    $tagList = preg_split("/([!&|()]|^-| -| )/", $tagList, null, PREG_SPLIT_DELIM_CAPTURE);

    # pre-process the tags
    foreach ($tagList as $key => &$tag) {
        $tag = strtolower(trim($tag)) ;

        # skip operators
        if (in_array($tag, ['-', '!', '|', '&', '+', '(', ')'])) {
            continue;
        }

        # do synonym replacement and skip <2 length tags
        if (strlen($tag) >= 2) {
            $tag = get_tag_synonym($tag, false);
            $tag = str_replace('.', '_', $tag);
        } else {
            unset($tagList[$key]);
        }
    }

    # Collapse translated synonyms array into search string
    $tagList = implode(' ', $tagList);

    # Parse it into grouped arrays
    $tagList = parse_search($tagList);

    # Return SQL tag search string
    return $tagList;
}

/**
 * parse_search will parse a string into an array heirarchy and walk the
 * heirarchy translating boolean expressions from sphinx syntax into SQL
 * @param  string $searchTerms search terms to be translated.
 * @return string              translated search terms.
 */
function parse_search($searchTerms) {
    $level = 0;
    $bracketStart = null;
    $bracketEnd = null;
    $subGroups = [];
    for ($i = 0; $i < strlen($searchTerms); $i++) {
        switch ($searchTerms[$i]) {
            case '(':
                if (($i > 1) && ($level == 0)) {
                    $subGroups[] = substr($searchTerms, $bracketEnd, $i-$bracketEnd);
                }
                if ($level == 0) {
                    $bracketStart = $i+1;
                }
                $level++;
                break;

            case ')':
                $level--;
                $bracketEnd = $i+1;
                if ($level == 0) {
                    # Process subgroup
                    $subGroups[] = '('.parse_search(substr($searchTerms, $bracketStart, ($i-1)-$bracketStart)).')';
                }
                break;
        }
    }

    # Content after closing bracket
    if (is_int($bracketEnd) && $bracketEnd+1 < strlen($searchTerms)) {
        $subGroups[] = substr($searchTerms, $bracketEnd+1, strlen($searchTerms)-($bracketEnd-1));
    }

    # No sub-group found, return all content
    if (!is_int($bracketStart) && !is_int($bracketEnd)) {
        $subGroups[] = $searchTerms;
    }

    /*
     * Given the search string "(hd or 720p) & !mp4" subgroups will look like this:
     *   1st pass:
     *   array(1) {
     *     [0]=> string(9) "hd | 720p"
     *   }
     *
     *   2nd pass:
     *   array(2) {
     *     [0]=> string(9) "(hd 720p)"
     *     [1]=> string(6) "& !mp4"
     *   }
     */

    $searchTerms = [];

    # Tokenize ungrouped terms and modifiers at this level
    foreach ($subGroups as &$searchTerm) {
        if (substr($searchTerm, 0, 1) === '(') {
            $searchTerms[] = $searchTerm;
            continue;
        }
        $searchTerm = preg_split("/([-!&|])| /", $searchTerm, null, PREG_SPLIT_DELIM_CAPTURE);

        # pre-process search terms
        foreach ($searchTerm as $key => &$term) {
            # skip operators
            if (in_array($term, ['-', '!', '|', '&', '+', '(', ')'])) {
                continue;
            }

            # Strip empty and space tags
            if (empty($term) || $term === ' ') {
                unset($searchTerm[$key]);
            }
        }

        $searchTerms = array_merge($searchTerms, $searchTerm);
    }

    # reindex before final parse pass
    $searchTerms = array_values($searchTerms);

    /*
     * Given the search string "(hd or 720p) & !mp4" searchTerms will look like this:
     *   1st pass:
     *   array(3) {
     *     [0]=> string(2) "hd"
     *     [1]=> string(1) "|"
     *     [2]=> string(4) "720p"
     *   }
     *
     *   2nd pass:
     *   array(4) {
     *     [0]=> string(9) "(hd 720p)"
     *     [1]=> string(1) "&"
     *     [2]=> string(1) "!"
     *     [3]=> string(3) "mp4"
     *   }
     */

    # Translate each modifier and strip invalid modifier combinations
    foreach ($searchTerms as $key => &$tag) {
        switch ($tag) {
            case '|':
                unset($searchTerms[$key]);
                break;

            case '+':
            case '&':
                unset($searchTerms[$key]);
                add_search_modifier($searchTerms, $key -1, '+');
                add_search_modifier($searchTerms, $key +1, '+');
                break;

            case '!':
            case '-':
                unset($searchTerms[$key]);
                add_search_modifier($searchTerms, $key +1, '-');
                break;

            case '"':
                unset($searchTerms[$key]);
                break;

            default:
                continue 2;
        }
    }

    # Collapse this level into an expression and trim
    $searchTerms = implode(' ', $searchTerms);
    $searchTerms = trim($searchTerms);

    /*
     * Given the search string "(hd or 720p) & !mp4" searchTerms will look like this:
     *   1st pass:
     *   string(7) "hd 720p"
     *
     *   2nd pass:
     *   string(15) "+(hd 720p) -mp4"
     */

    return $searchTerms;
}

/**
 * function add_search_modifier adds a modifier to the beginning of a search
 * term while ensuring the expression remains valid.
 * @param array  $searchTerms array of current search terms.
 * @param int    $index       current array index.
 * @param string $modifier    modifier to be applied.
 */
function add_search_modifier(&$searchTerms, $index, $modifier) {
    # Pre-translate modifiers
    $searchTerms[$index] = preg_replace(['/^!/', '/^&/'], ['-', '+'], $searchTerms[$index]);

    # Ensure index is good first.
    if (!array_key_exists($index, $searchTerms)) {
        return;
    }

    # Grab the current modifier and do some checks.
    $currentModifier = substr($searchTerms[$index], 0, 1);


    # These are invalid modifiers at this stage, just drop them
    if (in_array($modifier, ['|', '(', ')'])) {
        return;
    }

    # Dangling modifier, just drop it (except negation)
    if (in_array($searchTerms[$index], ['|', '&', '+', '(', ')', ''])) {
        return;
    }

    if (in_array($currentModifier, ['+', '-'])) {
        # Not overrides and, just overwrite the and
        if ($currentModifier == '+' && $modifier == '-') {
            $searchTerms[$index] = $modifier.substr($searchTerms[$index], 1);
            return;
        }
        # Not overrides and, don't modify
        if ($currentModifier == '-' && $modifier == '+') {
            return;
        }
        # Modifier unchanged, don't prepend twice
        if ($currentModifier == $modifier) {
            return;
        }
    }
    # Prepend new modifier
    $searchTerms[$index] = $modifier.$searchTerms[$index];
}

function get_seeding_size($userID) {
    global $master;

    $seedingSizeTotal = $master->cache->getValue('users_seeding_size_'.$userID);
    if (empty($seedingSizeTotal)) {
        $seedingSizeTotal = $master->db->rawQuery(
            'SELECT SUM(t.Size) as seedingSize
              FROM xbt_files_users AS xfu
                JOIN torrents AS t ON xfu.fid = t.ID
              WHERE xfu.uid = ?
              AND xfu.remaining = 0',
            [$userID]
        )->fetchColumn();
        $master->cache->cacheValue('users_seeding_size_'.$userID, $seedingSizeTotal);
    }
    return $seedingSizeTotal;
}

# The "order by x" links on columns headers
function header_link($SortKey, $DefaultWay = "desc", $Anchor = "") {
    global $master, $orderBy, $orderWay;

    if (empty($orderBy)) {
        $orderBy = $master->request->getString('order_by');
    }

    if (empty($orderWay)) {
        $orderWay = $master->request->getString('order_way');
    }

    if ($SortKey==$orderBy) {
        if ($orderWay=="desc") {
            $NewWay="asc";
        } else {
            $NewWay="desc";
        }
    } else {
        $NewWay=$DefaultWay;
    }
    $location = $master->request->urlParts['path'];
    return "{$location}?order_way={$NewWay}&amp;order_by={$SortKey}&amp;".get_url(['order_way', 'order_by']).$Anchor;
}

function view_link($View, $ViewKey, $LinkCode) {
    $Link  = ($View==$ViewKey)? "<b>":"";
    $Link .= "[$LinkCode] &nbsp";
    $Link .= ($View==$ViewKey)? "</b>":"";
    return $Link;
}

function can_bookmark($Type) {
    return in_array($Type, ['torrent', 'collage', 'request']);
}

# Recommended usage:
# list($Table, $Col) = bookmark_schema('torrent');
function bookmark_schema($Type) {
    switch ($Type) {
        case 'torrent':
            return ['bookmarks_torrents', 'GroupID'];
            break;
        case 'collage':
            return ['bookmarks_collages', 'CollageID'];
            break;
        case 'request':
            return ['bookmarks_requests', 'RequestID'];
            break;
        default:
            die('HAX');
    }
}

function has_bookmarked($Type, $ID) {
    return in_array($ID, all_bookmarks($Type));
}

function all_bookmarks($Type, $UserID = false) {
    global $master;

    $user = $master->request->user;

    if ($UserID === false) {
        $UserID = $user->ID;
    }
    $CacheKey = "bookmarks_{$Type}_{$UserID}";
    if (($Bookmarks = $master->cache->getValue($CacheKey)) === false) {
        list($Table, $Col) = bookmark_schema($Type);
        $Bookmarks = $master->db->rawQuery("SELECT {$Col} FROM {$Table} WHERE UserID = ?", [$UserID])->fetchAll(\PDO::FETCH_COLUMN);
        $master->cache->cacheValue($CacheKey, $Bookmarks, 0);
    }

    return $Bookmarks;
}

/**
 * Make sure we have a valid IPv4 or IPv6 address,
 * that is not in a private or reserved range.
 * @param $ip
 * @return bool
 */
function validate_ip($ip): bool {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

$Filters = [
    [
        'name'  => 'Staff',
        'rule' => implode('|', [
                'Notes? added by ',
                'Account ',
                'Linked accounts updated',
                'Reset ',
                'Warning ',
                'Warned ',
                '[\w ]+ (changed|modified) ',
                'Disabled '
            ]
        )
    ],
    [
        'name'  => 'User actions',
        'rule' => implode('|', [
                'Someone requested'
            ]
        )
    ],
    [
        'name'  => 'Credits & bounty',
        'rule' => implode('|', [
                'User gave a gift of ',
                'User received a gift of ',
                'User received a bounty ',
                'User bought ',
                'Bounty of ',
                'Added +',
                'Removed -'
            ]
        )
    ],
    [
        'name'  => 'Badges',
        'rule' => implode('|', [
                'Badge '
            ]
        )
    ]
];

/**
 * Parse staff notes block into several sections
 * @param string $Notes
 * @return array $ParsedNotes
 */
function parse_staff_notes($Notes) {
    global $Filters;
    $ParsedNotes = [];

    # Gather needed regexes
    $DateRegEx = "\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}"; // 2017-07-11 17:41:30
    // Misc is a fallback filter we initialize last
    $MiscIndex = count($Filters) + 1;
    $Filters[$MiscIndex] = ['name' => 'Misc', 'rule' => null, 'count' => 0];

    # Parsing
    $Delimiter = '_DELIM';
    $Notes = preg_replace("/(?:^|\n)(".$DateRegEx.")/", $Delimiter.'$1', $Notes); // Do not capture inline dates
    $Notes = substr($Notes, strlen($Delimiter)); // Remove first, unnecessary $Delimiter
    $Notes = explode($Delimiter, trim($Notes));

    # Identification
    foreach ($Notes as $Note) {
        if (empty($Note)) continue;

        $Found = false;
        foreach ($Filters as &$Filter) {
            // Init filter count
            if (!isset($Filter['count']))
                $Filter['count'] = 0;

            if (preg_match("/^".$DateRegEx.' - '.$Filter['rule']."/i", $Note)) {
                $ParsedNotes[] = "<span class=\"staffnote-".trim_filter($Filter['name'])."\">$Note</span>";
                $Filter['count']++;
                $Found = true;
                break;
            }
        }

        // Fallback in case no rule matches
        if (!$Found) {
            $ParsedNotes[] = "<span class=\"staffnote-Misc\">$Note</span>";
            $Filters[$MiscIndex]['count']++;
        }
    }

    # Post-processing
    // Remove empty filters
    $Filters = array_filter($Filters, function($Filter) {
            return ($Filter['count'] ?? 0) > 0;
    });

    return [implode('', $ParsedNotes), $Filters];
}

function print_compose_staff_pm($Hidden = true, $Assign = 0, $Subject ='', $Msg = '', $bbCode = false) {
        global $master;

        $user = $master->request->user;

        // forwarding a msg
        $action = $_POST['action'] ?? null;
        if ($action==='forward') {
            $MsgType = 'conversation';
            if (!$_POST['convid'] || !is_integer_string($_POST['convid'])) {
                error(0);
            }
            $convID = (int) $_POST['convid'];
            $posts = $master->db->rawQuery(
                "SELECT pc.Subject, IFNULL(u.Username,'system') AS Username, pm.Body
                   FROM pm_messages as pm
                   JOIN pm_conversations AS pc ON pc.ID = pm.ConvID
                   JOIN pm_conversations_users AS pmu ON pm.ConvID = pmu.ConvID AND pmu.ConvID = ?
              LEFT JOIN users AS u ON u.ID = pm.SenderID
                  WHERE pmu.UserID = ?",
                [$convID, $user->ID]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $Subject = null;
            foreach ($posts as $post) {
                if (!$Subject) {
                    $Subject = $post['Subject'];
                    $Subject = "FWD: {$Subject}";
                    $FwdBody = "[bg=#d3e3f3]FWD: $Subject          [color=grey]conv#{$convID}[/color][/bg]\n";
                }
                $FwdBody .= "[quote={$post['Username']}]{$post['Body']}[/quote]\n";
            }
        }

        $IsStaff = check_perms('site_staff_inbox');
        if (!$bbCode) {
            $bbCode = new \Luminance\Legacy\Text;
        }
        if ($Msg=='changeusername') {
            $Subject='Change Username';
            $Msg="\n\nI would like to change my username to\n\nBecause";
            $Assign='admin';
        } elseif ($Msg=='donategb' || $Msg=='donatelove') {
            $Subject='I would like to donate for ';
            if ($Msg=='donategb') {
                $Subject .= 'GB';
                $Msg="\n\nPlease send me instructions on how to donate to remove gb from my download.";
            } else {
                $Subject .= 'love';
                $Msg="\n\nPlease send me instructions on how to donate to help support the site.";
            }
            $Assign='sysop';
            $AssignDirect = '1000';
        } elseif ($Msg=='nobtcrate') {
            $Subject='Error: No exchange rate for bitcoin';
            $Msg='';
            $Assign='admin';
        }

        ?>
        <div id="compose" class="<?=($Hidden ? 'hide' : '')?>">
<?php       if ($IsStaff) {  ?>
                    <div class="box pad">
                      <strong class="important_text">Are you sure you want to send a message to staff? You are staff yourself you know...</strong>
                    </div>
<?php       }

             if (($FwdBody ?? false)) {
?>
                 <div class="head">
                     <?=$MsgType;?> to be forwarded:
                 </div>
                 <div class="box vertical_space">
                     <div class="body" >
                         <?=$bbCode->full_format($FwdBody, true)?>
                     </div>
                 </div>
<?php
             } else {
                $FwdBody = '';
             }
?>
            <div id="preview" class="hidden"></div>
            <form action="staffpm.php" method="post" id="messageform">
                <div id="quickpost">
                    <input type="hidden" name="action" value="takepost" />
                    <input type="hidden" name="prependtitle" value="Staff PM - " />
                    <input type="hidden" name="forwardbody" value="<?=display_str($FwdBody)?>" />

                    <label for="subject"><h3>Subject</h3></label>
                    <input class="long" type="text" name="subject" id="subject" value="<?=display_str($Subject)?>" />
                    <br />

                    <label for="message"><h3>Message</h3></label>
                                <?php  $bbCode->display_bbcode_assistant("message"); ?>
                    <textarea rows="10" class="long" name="message" id="message"><?=display_str($Msg)?></textarea>
                    <br />
                </div>

                <input type="button" value="Hide" onClick="jQuery('#compose').toggle();return false;" />
                <strong>Send to: </strong>
<?php                   if (($AssignDirect ?? false)) { ?>
                <input type="hidden" name="level" value="<?=$AssignDirect?>" />
                <input type="text" value="<?=$Assign?>" disabled="disabled" />
<?php                   } else { ?>
                <select name="level">
                    <option value="0"<?php if(!$Assign)echo ' selected="selected"';?>>First Line Support</option>
                    <option value="500"<?php if($Assign=='mod')echo ' selected="selected"';?>>Moderators</option>
                    <option value="549"<?php if($Assign=='smod')echo ' selected="selected"';?>>Senior Staff</option>
<?php                       if($IsStaff) { ?>
                    <option value="600"<?php if($Assign=='admin')echo ' selected="selected"';?>>Admin Team</option>
<?php                       } ?>
                </select>
<?php                   } ?>
                <input type="button" id="previewbtn" value="Preview" onclick="Inbox_Preview();" />
                        <input type="submit" value="Send message" />

            </form>
        </div>
<?php  }

function getNewCategories() {
    global $master;

    $newCategories = $master->cache->getValue('new_categories');
    if (empty($newCategories)) {
        $newCategories = $master->db->rawQuery(
            "SELECT id, id,
                    name,
                    image,
                    tag
               FROM categories
           ORDER BY name ASC"
        )->fetchAll(\PDO::FETCH_UNIQUE|\PDO::FETCH_BOTH);

        $master->cache->cacheValue('new_categories', $newCategories);
    }
    return $newCategories;
}

function getOpenCategories() {
    global $master;

    $openCategories = $master->cache->getValue('open_categories');
    if (empty($openCategories)) {
        $openCategories = $master->db->rawQuery(
            "SELECT id, id,
                    name,
                    image,
                    tag
               FROM categories
              WHERE open='1'
           ORDER BY name ASC"
        )->fetchAll(\PDO::FETCH_UNIQUE|\PDO::FETCH_BOTH);

        $master->cache->cacheValue('open_categories', $openCategories);
    }
    return $openCategories;
}
