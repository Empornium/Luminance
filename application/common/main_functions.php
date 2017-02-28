<?php

# This code was originally part of script_start.php and class_mysql.php

//Handles escaping
function db_string($String,$DisableWildcards=false)
{
    global $master;
    //Escape
    $String = $master->olddb->escape_str($String);
    //Remove user input wildcards
    if ($DisableWildcards) {
        $String = str_replace(array('%','_'), array('\%','\_'), $String);
    }

    return $String;
}

function db_array($Array, $DontEscape = array(), $Quote = false)
{
    foreach ($Array as $Key => $Val) {
        if (!in_array($Key, $DontEscape)) {
            if ($Quote) {
                $Array[$Key] = '\''.db_string(trim($Val)).'\'';
            } else {
                $Array[$Key] = db_string(trim($Val));
            }
        }
    }

    return $Array;
}

// Get cached user info, is used for the user loading the page and usernames all over the site
// AND for looking up advanced tags permissions

function user_info($UserID)
{
    global $master;
    $User = $master->repos->users->load($UserID);
    if (!$User) {
        # No clue what this is is actually needed for...
		// - answer: in the case of a deleted user (and we have some from early on) this stops the interface from breaking!
        $UserInfo = array('ID'=>'','Username'=>'','PermissionID'=>0,'Paranoia'=>array(),'Donor'=>false,'Warned'=>'0000-00-00 00:00:00',
                'Avatar'=>'','Enabled'=>0,'Title'=>'', 'CatchupTime'=>0, 'Visible'=>'1','Signature'=>'','TorrentSignature'=>'',
                'GroupPermissionID'=>0,'ipcc'=>'??');
        return $UserInfo;
    }
    $UserInfo = $User->info();
    return $UserInfo;
}

// Only used for current user
function user_heavy_info($UserID)
{
    global $master;
    $User = $master->repos->users->load($UserID);
    $HeavyInfo = $User->heavy_info();
    return $HeavyInfo;
}

/**
 * get the users seed leech info (caches for 15 mins)
 *
 * @param int $UserID
 * @return array Returns array('Seeding'=>$Seeding, 'Leeching'=>$Leeching)
 */
function user_peers($UserID)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;
    $PeerInfo = $Cache->get_value('user_peers_' . $UserID);
    if ($PeerInfo===false) {
        $DB->query("SELECT IF(remaining=0,'Seeding','Leeching') AS Type, COUNT(DISTINCT t.ID)
                      FROM xbt_files_users AS x
                      JOIN torrents AS t ON t.ID=x.fid
                     WHERE x.uid='$UserID' AND x.active=1
                  GROUP BY Type");
        $PeerCount = $DB->to_array(0, MYSQLI_NUM, false);
        $Seeding = isset($PeerCount['Seeding'][1]) ? $PeerCount['Seeding'][1] : 0;
        $Leeching = isset($PeerCount['Leeching'][1]) ? $PeerCount['Leeching'][1] : 0;
        $PeerInfo = array('Seeding'=>$Seeding, 'Leeching'=>$Leeching);
        $Cache->cache_value('user_peers_' . $UserID, $PeerInfo, 900);
    }

    return $PeerInfo;
}

function get_userid($Username)
{
    global $master;
    $DB = $master->olddb;
    if(!$Username) return 0;
    $DB->query("SELECT ID FROM users_main WHERE Username = '" . db_string($Username). "'");
    if ($DB->record_count() > 0) {
        list($UserID) = $DB->next_record();

        return $UserID;
   }

   return 0;
}

/**
 * update a users site_options field with a new value
 *
 * @param int $UserID
 * @param int $NewOptions options to overwrite in format array('OptionName' => $Value, 'OptionName' => $Value)
 */
function update_site_options($UserID, $NewOptions)
{
    global $LoggedUser, $master;
    $DB = $master->olddb;
    $Cache = $master->cache;
    if (!is_number($UserID)) {
        error(0);
    }
    if (empty($NewOptions) || !is_array($NewOptions)) {
        return false;
    }

    // Get SiteOptions
    $DB->query("SELECT SiteOptions FROM users_info WHERE UserID = $UserID");
    list($SiteOptions) = $DB->next_record(MYSQLI_NUM, false);
    $SiteOptions = unserialize($SiteOptions);

    // Get HeavyInfo
    $HeavyInfo = user_heavy_info($UserID);

    // Insert new/replace old options
    $SiteOptions = array_merge($SiteOptions, $NewOptions);
    $HeavyInfo = array_merge($HeavyInfo, $NewOptions);

    // Update DB
    $DB->query("UPDATE users_info SET SiteOptions = '" . db_string(serialize($SiteOptions)) . "' WHERE UserID = $UserID");

    // Update cache
    $master->repos->users->uncache($UserID);

    // Update $LoggedUser if the options are changed for the current
    if ($LoggedUser['ID'] == $UserID) {
        $LoggedUser = array_merge($LoggedUser, $NewOptions);
        $LoggedUser['ID'] = $UserID; // We don't want to allow userid switching
    }
}

function get_next_bonus_update($LastBonusTime)
{
    return strftime("%e %b %Y  %r", strtotime("+1 week", $LastBonusTime));
}

function get_avatar_css($MaxAvatarWidth, $MaxAvatarHeight)
{
    $css = 'max-width:' . $MaxAvatarWidth . 'px; max-height:' . $MaxAvatarHeight . 'px;';
    return $css;
}

function get_permissions($permissionID)
{
    global $master;
    $repo = $master->getRepository('PermissionRepository');
    $permission = $repo->getLegacyPermission($permissionID);
    return $permission;
}

function get_permissions_for_user($userID, $dummy1 = false, $dummy2 = false)
{
    global $master;
    $user = $master->auth->users->load($userID);
    $permissions = $master->auth->getUserPermissions($user);
    return $permissions;
}

// Get whether this user can use adv tags (pass optional params to reduce lookups)
function get_permissions_advtags($UserID, $CustomPermissions = false, $UserPermission = false)
{
    $PermissionsValues = get_permissions_for_user($UserID, $CustomPermissions, $UserPermission);

      return isset($PermissionsValues['site_advanced_tags']) &&  $PermissionsValues['site_advanced_tags'];
}

function get_user_badges($UserID, $LimitRows = true)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $UserID = (int) $UserID;
    if ($LimitRows) {
        $extra = "_limit";
        $BarLimit    = "LIMIT 12";
        $RibbonLimit = "LIMIT 24";
        $MedalLimit  = "LIMIT 12";
    }
    $UserBadges = $Cache->get_value('user_badges_'.$UserID.$extra);
    if (!is_array($UserBadges)) {
        $DB->query("
                    (SELECT
                        ub.ID, ub.BadgeID,  ub.Description,  b.Title, b.Image,
                        IF(ba.ID IS NULL,FALSE,TRUE) AS Auto, b.Type, b.Display, b.Sort
                    FROM users_badges AS ub
                    JOIN badges AS b ON b.ID = ub.BadgeID
                    LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
                    WHERE ub.UserID = $UserID AND b.Display=0
                    ORDER BY b.Sort $BarLimit)
                UNION
                    (SELECT
                        ub.ID, ub.BadgeID,  ub.Description,  b.Title, b.Image,
                        IF(ba.ID IS NULL,FALSE,TRUE) AS Auto, b.Type, b.Display, b.Sort
                    FROM users_badges AS ub
                    JOIN badges AS b ON b.ID = ub.BadgeID
                    LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
                    WHERE ub.UserID = $UserID AND b.Display=1
                    ORDER BY b.Sort $RibbonLimit)
                UNION
                    (SELECT
                        ub.ID, ub.BadgeID,  ub.Description,  b.Title, b.Image,
                        IF(ba.ID IS NULL,FALSE,TRUE) AS Auto, b.Type, b.Display, b.Sort
                    FROM users_badges AS ub
                    JOIN badges AS b ON b.ID = ub.BadgeID
                    LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
                    WHERE ub.UserID = $UserID AND b.Display>1
                    ORDER BY b.Sort $MedalLimit)
                ORDER BY Display, Sort
                ");
        $UserBadges = $DB->to_array();
        $Cache->cache_value('user_badges_'.$UserID.$extra, $UserBadges);
    }

    return $UserBadges;
}

function get_user_shop_badges_ids($UserID)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $UserID = (int) $UserID;
    $UserBadges = $Cache->get_value('user_badges_ids_'.$UserID);
    if (!is_array($UserBadges)) {
        $DB->query("SELECT BadgeID
                    FROM users_badges AS ub
                    LEFT JOIN badges AS b ON b.ID = ub.BadgeID
                    WHERE b.Type='Shop' AND UserID = $UserID");
        $UserBadges = $DB->collect('BadgeID');
        $Cache->cache_value('user_badges_ids_'.$UserID, $UserBadges);
    }

    return $UserBadges;
}

function print_badges_array($UserBadges, $UserLinkID = false)
{
    $LastRow='';
    $html='';
    foreach ($UserBadges as $Badge) {
        list($ID,$BadgeID, $Tooltip, $Name, $Image, $Auto, $Type, $Row ) = $Badge;
        if($LastRow=='') $LastRow = $Row;
        if ($LastRow!=$Row && $html != '') {
            $html .= "<br/>";
            $LastRow=$Row;
        }
        if($UserLinkID && is_number($UserLinkID))
            $html .= '<div class="badge"><a href="user.php?id='.$UserLinkID.'#userbadges"><img src="'.STATIC_SERVER.'common/badges/'.$Image.'" title="The '.$Name.'. '.$Tooltip.'" alt="'.$Name.'" /></a></div>';
        else
            $html .= '<div class="badge"><img src="'.STATIC_SERVER.'common/badges/'.$Image.'" title="The '.$Name.'. '.$Tooltip.'" alt="'.$Name.'" /></div>';
    }
    echo $html;
}

function get_latest_individual_forum_topics($ForumID)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $LatestTopics = $Cache->get_value('latest_topics_forum_'.$ForumID);
    if ($LatestTopics === false) {
        $DB->query("SELECT STRAIGHT_JOIN
                        ft.ID AS ThreadID, ft.LastPostID AS PostID, ft.Title,
                        um.Username, ft.LastPostTime AS AddedTime
                    FROM forums_topics AS ft
                    JOIN forums AS f ON ft.ForumID=f.ID
                    JOIN users_main AS um ON um.ID=ft.LastPostAuthorID
                    WHERE ft.ForumID = ".intval($ForumID)."
                    ORDER BY ft.LastPostID DESC
                    LIMIT 6");
        $LatestTopics = $DB->to_array(false, MYSQLI_ASSOC);
        $Cache->cache_value('latest_topics_forum_'.$ForumID, $LatestTopics);
    }

    return $LatestTopics;
}

function get_latest_forum_topics($PermissionID)
{
    global $Classes, $LoggedUser;
    $Level = $Classes[$PermissionID]['Level'];

    $LatestTopics = array();

    // Might not be loaded already depending on where we want to show the latest topics box
    require_once(SERVER_ROOT.'/sections/forums/functions.php');
    $Forums = get_forums_info();

    $disabledLatestTopics = isset($LoggedUser['DisabledLatestTopics']) ? $LoggedUser['DisabledLatestTopics'] : array();
    $RestrictedForums = array_keys($LoggedUser['CustomForums'], 0);
    $PermittedForums = array_keys($LoggedUser['CustomForums'], 1);

    foreach ($Forums as $ForumID => $ForumInfo) {
        // Exclude forums the user is not permitted to see
        if ($ForumInfo['MinClassRead'] > $Level && !in_array($ForumID, $PermittedForums)) continue;

        // User has been explicitely restricted from this forum
        if (in_array($ForumID, $RestrictedForums)) continue;

        // Exclude forums the user is not interested in
        if (in_array($ForumID, $disabledLatestTopics)) continue;

        // Retrieve the forums latest posts
        $LatestTopics = array_merge($LatestTopics, get_latest_individual_forum_topics($ForumID));
    }
    // We need to sort the combined topics by their AddedTime
    usort($LatestTopics, function ($a, $b) {
        $aDate = new DateTime($a['AddedTime']);
        $bDate = new DateTime($b['AddedTime']);

        if ($aDate == $bDate) return 0;

        return $aDate > $bDate ? -1 : 1; // descending order
    });

    return array_slice($LatestTopics, 0, 6);
}

function print_latest_forum_topics()
{
    global $LoggedUser;
    if (empty($LoggedUser['DisableLatestTopics'])) {
        $LatestTopics = get_latest_forum_topics($LoggedUser['PermissionID']);

        echo '<div class="head latest_topics">Latest forum topics</div>';
        echo '<div class="box pad latest_topics">';
        foreach ($LatestTopics as $Key=>$Value) {
            echo '<span class="sicon unread"></span><a href="forums.php?action=viewthread&threadid='.$Value['ThreadID']."&postid=".$Value['PostID']."#post".$Value['PostID'].'"><strong>'.$Value['Title']."</strong></a> by ".$Value['Username']." (".time_diff($Value['AddedTime'], 1,true,false,0).")&nbsp;&nbsp;";
        }
        echo "</div>";
    }
}

/* --------------------------------
* Returns a regex string in the form '/email.com|otheremail.com|email2.com/i'
  for fast email blacklist checking
  ----------------------------------- */
function get_emailblacklist_regex()
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $pattern = $Cache->get_value('emailblacklist_regex');
    if ($pattern===false) {
        $DB->query("SELECT Email FROM email_blacklist");
        if ($DB->record_count()>0) {
            $pattern = '@';
            $div = '';
            while (list($host)=$DB->next_record()) {
                $pattern .= $div . preg_quote($host, '@');
                $div = '|';
            }
            $pattern .= '@i';
            $Cache->cache_value('emailblacklist_regex', $pattern);
        } else {
            $pattern = '@nohost.non@i';
        }
    }

    return $pattern;
}
/* --------------------------------
* Returns a regex string in the form '/imagehost.com|otherhost.com|imgbox.com/i'
  for fast whitelist checking
  ----------------------------------- */
function get_whitelist_regex()
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $pattern = $Cache->get_value('imagehost_regex');
    if ($pattern===false) {
        $DB->query("SELECT Imagehost FROM imagehost_whitelist");
        if ($DB->record_count()>0) {
            $pattern = '@';
            $div = '';
            while (list($host)=$DB->next_record()) {
                $pattern .= $div . preg_quote($host, '@');
                $div = '|';
            }
            $pattern .= '@i';
            $Cache->cache_value('imagehost_regex', $pattern);
        } else {
            $pattern = '@nohost.com@i';
        }
    }

    return $pattern;
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
function validate_imageurl($Imageurl, $MinLength, $MaxLength, $WhitelistRegex)
{
       $ErrorMessage = "$Imageurl is not a valid url.";

       $URLInfo = parse_url($Imageurl);
       if (!$URLInfo) {
           return "$ErrorMessage (Bad URL)";
       }
       $ImageHost = $URLInfo['scheme'].'://'.$URLInfo['host'];

       if (strlen($Imageurl)>$MaxLength) {
           return "$ErrorMessage (must be < $MaxLength characters)";
       } elseif (strlen($Imageurl)<$MinLength) {
           return "$ErrorMessage (must be > $MinLength characters)";
       } elseif (!preg_match('/^(https?):\/\/([a-z0-9\-\_]+\.)+([a-z]{1,5}[^\.])(\/[^<>]+)*$/i', $Imageurl)) {
           return $ErrorMessage;
       } elseif (!preg_match($WhitelistRegex, $ImageHost)) {
           return "$Imageurl is not on an approved imagehost ($ImageHost).";
       } else { // hooray it validated

           return TRUE;
       }
}

function validate_email($email)
{
       if (preg_match(get_emailblacklist_regex(), $email)) {
           return "$email is on a blacklisted email host.";
       } else { // hooray it validated

           return TRUE;
       }
}

// This is used to determine whether the '[Edit]' link should be shown
function can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime, $TimeLock=true)
{
    global $LoggedUser;

    if (check_perms('site_moderate_forums')) {
        return true; // moderators can edit anything
    }

    if ($AuthorID != $LoggedUser['ID'] || ($EditedUserID && $EditedUserID != $LoggedUser['ID'])) {
        return false;
    }

    return ( !$TimeLock || ( time_ago($AddedTime) && time_ago($AddedTime)<USER_EDIT_POST_TIME ) || ( time_ago($EditedTime) && time_ago($EditedTime)<USER_EDIT_POST_TIME )) || check_perms ('site_edit_own_posts');
}

// This function is used to check if the user can submit changes to a comment.
// Prints error if not permitted.
function validate_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime, $TimeLock=true)
{
    global $LoggedUser;

    if (check_perms('site_moderate_forums')) {
        return; // moderators can edit anything
    }

    if ($AuthorID != $LoggedUser['ID']) {
        error(403, true);
    }

    if ($EditedUserID && $EditedUserID != $LoggedUser['ID']) {
        error("You are not allowed to edit a post that has been edited by moderators.", true);
    }

    if (( !$TimeLock ||
        ( time_ago($AddedTime) && time_ago($AddedTime)<(USER_EDIT_POST_TIME+600) ) ||
        ( time_ago($EditedTime) && time_ago($EditedTime)<(USER_EDIT_POST_TIME+300) )) ||
          check_perms ('site_edit_own_posts')) {
        return;
    } else {
        error("Sorry - you only have ". date('i\m s\s', USER_EDIT_POST_TIME). "  to edit your post before it is automatically locked." ,true);
    }
}

// for getting an article to display on some other page
function get_article($TopicID)
{
    global $master;
    $DB = $master->olddb;

    $TopicID = db_string($TopicID);
    $DB->query("SELECT Body FROM articles WHERE TopicID='$TopicID'");
    list($Body) = $DB->next_record();

    return $Body;
}

function flood_check($Table = 'forums_posts')
{
    global $master, $LoggedUser;
    $DB = $master->olddb;

    if (check_perms('site_ignore_floodcheck')) return true;
    if ( !in_array($Table, array('forums_posts','requests_comments','torrents_comments','collages_comments','sm_results'))) error(0);
    if ($Table=='collages_comments' || $Table=='sm_results') {
        $DB->query( "SELECT ( (UNIX_TIMESTAMP( Time)+'".USER_FLOOD_POST_TIME."')-UNIX_TIMESTAMP(  UTC_TIMESTAMP()) )  FROM $Table
                  WHERE UserID = $LoggedUser[ID]
                    AND UNIX_TIMESTAMP( Time)>= ( UNIX_TIMESTAMP(  UTC_TIMESTAMP())-'".USER_FLOOD_POST_TIME."')");
    } else {
        $DB->query( "SELECT ( (UNIX_TIMESTAMP( AddedTime)+'".USER_FLOOD_POST_TIME."')-UNIX_TIMESTAMP(  UTC_TIMESTAMP()) )  FROM $Table
                  WHERE AuthorID = $LoggedUser[ID]
                    AND UNIX_TIMESTAMP( AddedTime)>= ( UNIX_TIMESTAMP(  UTC_TIMESTAMP())-'".USER_FLOOD_POST_TIME."')");
    }
    if ($DB->record_count()==0) return true;
    else {
        list($Secs) = $DB->next_record();
        error("<h3>Flood Control</h3>You must wait <strong>$Secs</strong> seconds before posting again.");
    }
}

function flood_check_slots() {  // gives ajax error
    global $master, $LoggedUser;
    $DB = $master->olddb;

    if (check_perms('site_ignore_floodcheck')) return true;

    $DB->query( "SELECT ( (UNIX_TIMESTAMP( Time)+'5')-UNIX_TIMESTAMP(  UTC_TIMESTAMP()) )  FROM sm_results
                  WHERE UserID = $LoggedUser[ID]
                    AND UNIX_TIMESTAMP( Time)>= ( UNIX_TIMESTAMP(  UTC_TIMESTAMP())-'5')");

    if ($DB->record_count()==0) return true;
    else {
        list($Secs) = $DB->next_record();

        return (int) $Secs;
    }
}

function ip2unsigned($IP)
{
    return sprintf("%u", ip2long($IP));
}

// Geolocate an IP address. Two functions - a database one, and a dns one.
function geoip($IP)
{
    global $master;
    $DB = $master->olddb;
    static $IPs = array();
    if (isset($IPs[$IP])) {
        return $IPs[$IP];
    }
    $Long = ip2unsigned($IP);
    if (!$Long || $Long == 2130706433) { // No need to check cc for 127.0.0.1
        return '??';
    }
    $DB->query("SELECT EndIP,Code FROM geoip_country WHERE $Long >= StartIP ORDER BY StartIP DESC LIMIT 1");
    if ((!list($EndIP, $Country) = $DB->next_record()) || $EndIP < $Long) {
        $Country = '??';
    }
    $IPs[$IP] = $Country;

    return $Country;
}

function old_geoip($IP)
{
    static $Countries = array();
    if (empty($Countries[$IP])) {
        $Country = 0;
        // Reverse IP, so 127.0.0.1 becomes 1.0.0.127
        $ReverseIP = implode('.', array_reverse(explode('.', $IP)));
        $TestHost = $ReverseIP . '.country.netop.org';
        $Return = dns_get_record($TestHost, DNS_TXT);
        if (!empty($Return)) {
            $Country = $Return[0]['txt'];
        }
        if (!$Country) {
            $Return = gethostbyaddr($IP);
            $Return = explode('.', $Return);
            $Return = array_pop($Return);
            if (strlen($Return) == 2 && !is_number($Return)) {
                $Country = strtoupper($Return);
            } else {
                $Country = '??';
            }
        }
        if ($Country == 'UK') {
            $Country = 'GB';
        }
        $Countries[$IP] = $Country;
    }

    return $Countries[$IP];
}

function gethostbyip($ip)
{
    $testar = explode('.', $ip);
    if (count($testar) != 4) {
        return $ip;
    }
    for ($i = 0; $i < 4; ++$i) {
        if (!is_numeric($testar[$i])) {
            return $ip;
        }
    }

    $host = `host -W 1 $ip`;

    return (($host ? end(explode(' ', $host)) : $ip));
}

function get_host($IP)
{
    static $ID = 0;
    ++$ID;

    return '<span id="host_' . $ID . '">Resolving host ' . $IP . '...<script type="text/javascript">ajax.get(\'tools.php?action=get_host&ip=' . $IP . '\',function (host) {$(\'#host_' . $ID . '\').raw().innerHTML=host;});</script></span>';
}

function lookup_ip($IP)
{
    //TODO: use the $Cache
    global $master;
    $Cache = $master->cache;

    if (!$IP) return false;

    $LookUp = $Cache->get_value('gethost_'.$IP);
    if ($LookUp===false) {
        $Output = explode(' ', shell_exec('host -W 1 ' . escapeshellarg($IP)));
        if (count($Output) == 1 && empty($Output[0])) {
            //No output at all implies the command failed
           $LookUp = ''; // pass back empty string for error reporting in ajax call
        }
        if (count($Output) != 5) {
            $LookUp = false;
        } else {
            $LookUp = $Output[4];
            $Cache->cache_value('gethost_'.$IP, $LookUp, 0);
        }
    }

    return $LookUp;
}

function display_ip($IP, $cc = '?', $gethost = false, $baniplink=false)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    if($gethost) $Line = get_host($IP);
    else $Line = display_str($IP);
    if ($cc=='?' || $cc=='') {
        $cc=='?';
        $country = 'unknown';
    } else {
        $country = $Cache->get_value('country_'.$cc);
        if ($country===false) {
            $DB->query("SELECT country FROM countries WHERE cc='$cc'");
            list($country) = $DB->next_record();
            $Cache->cache_value('country_'.$cc, $country, 0);
        }
        if ($cc == '??') {
            $cc = '_world';
        }
        $Line .= ' <span title="'.$country.'">('.$cc.')</span> ' . '<img style="margin-bottom:-3px;" title="'.$country.'" src="static/common/flags/iso16/'. strtolower($cc).'.png" alt="" />';
    }
    $Line .= ' [<a href="user.php?action=search&amp;ip_history=on&amp;ip=' . display_str($IP) . '&amp;matchtype=fuzzy" title="Search IP History">S</a>]';
    $Line .= ' [<a href="user.php?action=search&amp;tracker_ip=' . display_str($IP) . '&amp;matchtype=fuzzy" title="Search Tracker IP\'s">S</a>]';
    if ($baniplink && check_perms('admin_manage_ipbans')) {
        $Line .= ' [<a href="tools.php?action=ip_ban&uip='.display_str($IP).'" title="Ban this users current IP ('.display_str($IP).')">B</a>]';
    }

    return $Line;
}

function logout()
{
    global $master;
    $master->auth->unauthenticate();
}

function enforce_login()
{
    global $master;
    $master->auth->legacy_enforce_login();
}

// Make sure $_GET['auth'] is the same as the user's authorization key
// Should be used for any user action that relies solely on GET.
function authorize($Ajax = false)
{
    global $LoggedUser;
    if (empty($_REQUEST['auth']) || $_REQUEST['auth'] != $LoggedUser['AuthKey']) {
        send_irc("PRIVMSG " . LAB_CHAN . " :" . $LoggedUser['Username'] . " just failed authorize on " . $_SERVER['REQUEST_URI'] . " coming from " . $_SERVER['HTTP_REFERER']);
        error('Invalid authorization key. Go back, refresh, and try again.', $Ajax);

        return false;
    }

    return true;
}

// This function is to include the header file on a page.
// $JSIncludes is a comma separated list of js files to be inclides on
// the page, ONLY PUT THE RELATIVE LOCATION WITHOUT .js
// ex: 'somefile,somdire/somefile'
function show_header($PageTitle='', $JSIncludes='')
{
    global $master, $Document, $Cache, $DB, $LoggedUser, $Mobile, $Classes, $Sitewide_Freeleech_On, $Sitewide_Freeleech;
    $scripts = (strlen($JSIncludes)) ? explode(',', $JSIncludes) : [];
    $master->peon->display_header(['page_title'=>$PageTitle, 'bscripts'=>$scripts]);
}

/* -- show_footer function ------------------------------------------------ */
/* ------------------------------------------------------------------------ */
/* This function is to include the footer file on a page.				 */
/* $Options is an optional array that you can pass information to the	 */
/*  header through as well as setup certain limitations				   */
/*  Here is a list of parameters that work in the $Options array:		 */
/*  ['disclaimer']	= [boolean]		Displays the disclaimer in the footer */
/* 								  Default is false					  */
/* * *********************************************************************** */

function show_footer($Options=array())
{
    global $master, $ScriptStartTime, $LoggedUser, $Cache, $DB, $SessionID, $UserSessions, $Debug, $Time;
    $master->peon->display_footer();
}

function cut_string($Str, $Length, $Hard=0, $ShowDots=1)
{
    if (mb_strlen($Str, "UTF-8") > $Length) {
        if ($Hard == 0) {
            // Not hard, cut at closest word
            $CutDesc = mb_substr($Str, 0, $Length, "UTF-8");
            $DescArr = explode(' ', $CutDesc);
            $DescArr = array_slice($DescArr, 0, count($DescArr) - 1);
            $CutDesc = implode($DescArr, ' ');
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
 * @param $hlterm string The string to highlight; the actual html used is: '<span atyle="color: $color">$hlterm</span>'
 *
 * @param $text string, which result's page we want if no page is specified
 *
 * @param $color string Optional, which color to use to highlight the term (can be any valid css color)
 * If this parameter is not specified, defaults to red
 *
 * @return string The text with 'term' highlighted
 */
function highlight_text_color($hlterm, $text, $color = 'red')
{
    return str_replace($hlterm, "<span style=\"color: $color;\">$hlterm</span>", $text);
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
function highlight_text_css($hlterm, $text, $css = 'search_highlight')
{
    return str_replace($hlterm, "<span class=\"$css\">$hlterm</span>", $text);
}

function get_ratio_color($Ratio)
{
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

function ratio($Dividend, $Divisor, $Color = true)
{
    if ($Divisor == 0 && $Dividend == 0) {
        return '<span>--</span>';
    } elseif ($Divisor == 0) {
        return '<span class="r99 infinity">∞</span>';
    }
    $Ratio = number_format(max($Dividend / $Divisor - 0.005, 0), 2); //Subtract .005 to floor to 2 decimals
    if ($Color) {
        $Class = get_ratio_color($Ratio);
        if ($Class) {
            $Ratio = '<span class="' . $Class . '">' . $Ratio . '</span>';
        }
    }

    return $Ratio;
}

function get_url($Exclude = false)
{
    if ($Exclude !== false) {
        $QueryItems = array();
        parse_str($_SERVER['QUERY_STRING'], $QueryItems);

        foreach ($QueryItems AS $Key => $Val) {
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
 * @param $PerPage Results to show per page
 *
 * @param $DefaultResult Optional, which result's page we want if no page is specified
 * If this parameter is not specified, we will default to page 1
 *
 * @return array(int,string) What page we are on, and what to use in the LIMIT section of a query
 * i.e. "SELECT [...] LIMIT $Limit;"
 */
function page_limit($PerPage, $DefaultResult = 1, $PageGetVar = 'page')
{
    if (!isset($_GET[$PageGetVar])) {
        $Page = ceil($DefaultResult / $PerPage);
        if ($Page == 0)
            $Page = 1;
        $Limit = $PerPage;
    } else {
        if (!is_number($_GET[$PageGetVar])) {
            error(0);
        }
        $Page = $_GET[$PageGetVar];
        if ($Page == 0) {
            $Page = 1;
        }
        $Limit = $PerPage * $_GET[$PageGetVar] - $PerPage . ', ' . $PerPage;
    }

    return array($Page, $Limit);
}

// For data stored in memcached catalogues (giant arrays), eg. forum threads
function catalogue_limit($Page, $PerPage, $CatalogueSize=500)
{
    $CatalogueID = floor(($PerPage * $Page - $PerPage) / $CatalogueSize);
    ;
    $CatalogueLimit = ($CatalogueID * $CatalogueSize) . ', ' . $CatalogueSize;

    return array($CatalogueID, $CatalogueLimit);
}

function catalogue_select($Catalogue, $Page, $PerPage, $CatalogueSize=500)
{
    return array_slice($Catalogue, (($PerPage * $Page - $PerPage) % $CatalogueSize), $PerPage, true);
}

function get_pages($StartPage, $TotalRecords, $ItemsPerPage, $ShowPages=11, $Anchor='')
{
    global $Document, $Method, $Mobile;
    $Location = $Document . '.php';
    /* -- Get pages ---------------------------------------------------------------//
      This function returns a page list, given certain information about the pages.

      Explanation of arguments:
     * $StartPage: The current record the page you're on starts with.
      eg. if you're on page 2 of a forum thread with 25 posts per page, $StartPage is 25.
      If you're on page 1, $StartPage is 0.
     * $TotalRecords: The total number of records in the result set.
      eg. if you're on a forum thread with 152 posts, $TotalRecords is 152.
     * $ItemsPerPage: Self-explanatory. The number of records shown on each page
      eg. if there are 25 posts per forum page, $ItemsPerPage is 25.
      $ShowPages: The number of page links that are shown.
      eg. If there are 20 pages that exist, but $ShowPages is only 11, only 11 links will be shown.
      //---------------------------------------------------------------------------- */
    $StartPage = ceil($StartPage);
    if ($StartPage == 0) {
        $StartPage = 1;
    }
    $TotalPages = 0;
    if ($TotalRecords > 0) {
        if ($StartPage > ceil($TotalRecords / $ItemsPerPage)) {
            $StartPage = ceil($TotalRecords / $ItemsPerPage);
        }

        $ShowPages--;
        $TotalPages = ceil($TotalRecords / $ItemsPerPage);

        if ($TotalPages > $ShowPages) {
            $StartPosition = $StartPage - round($ShowPages / 2);

            if ($StartPosition <= 0) {
                $StartPosition = 1;
            } else {
                if ($StartPosition >= ($TotalPages - $ShowPages)) {
                    $StartPosition = $TotalPages - $ShowPages;
                }
            }

            $StopPage = $ShowPages + $StartPosition;
        } else {
            $StopPage = $TotalPages;
            $StartPosition = 1;
        }

        if ($StartPosition < 1) {
            $StartPosition = 1;
        }

        $QueryString = get_url(array('page', 'post'));
        if ($QueryString != '') {
            $QueryString = '&amp;' . $QueryString;
        }

        $Pages = '';

        if ($StartPage > 1) {
            $Pages.='<a href="' . $Location . '?page=1' . $QueryString . $Anchor . '" class="pager pager_first">&lt;&lt; First</a> ';
            if ($StartPage > 2)
                $Pages.='<a href="' . $Location . '?page=' . ($StartPage - 1) . $QueryString . $Anchor . '" class="pager pager_prev">&lt; Prev</a>';
            $Pages.= ' | ';
        }
        //End change

        if (!$Mobile) {
            for ($i = $StartPosition; $i <= $StopPage; $i++) {
                if ($i != $StartPage) {
                    $Pages.='<a href="' . $Location . '?page=' . $i . $QueryString . $Anchor . '" class="pager pager_page">';
                } else {
                    $Pages.='<span class="pager pager_on">';
                }
                //$Pages.="<strong>"; fuck using strong... added css classes so can be done the right way
                if ($i * $ItemsPerPage > $TotalRecords) {
                    $Pages.=((($i - 1) * $ItemsPerPage) + 1) . '-' . ($TotalRecords);
                } else {
                    $Pages.=((($i - 1) * $ItemsPerPage) + 1) . '-' . ($i * $ItemsPerPage);
                }

                //$Pages.="</strong>";
                if ($i != $StartPage) {
                    $Pages.='</a>';
                } else {
                    $Pages.='</span>';
                }
                if ($i < $StopPage) {
                    $Pages.=" | ";
                }
            }
        } else {
            $Pages .= $StartPage;
        }

        if ($StartPage < $TotalPages) $Pages.=' | ';
        if ($StartPage < $TotalPages-1) {
            $Pages.='<a href="' . $Location . '?page=' . ($StartPage + 1) . $QueryString . $Anchor . '" class="pager pager_next">Next &gt;</a> ';
        }
        if ($StartPage < $TotalPages) {
                $Pages.='<a href="' . $Location . '?page=' . $TotalPages . $QueryString . $Anchor . '" class="pager pager_last"> Last &gt;&gt;</a>';
        }
    }

    if ($TotalPages > 1) {
        return $Pages;
    }
}

function send_email($To, $Subject, $Body, $From='noreply', $ContentType='text/plain')
{
    $Headers = 'MIME-Version: 1.0' . "\r\n";
    $Headers.='Content-type: ' . $ContentType . '; charset=iso-8859-1' . "\r\n";
    $Headers.='From: ' . SITE_NAME . ' <' . $From . '@' . NONSSL_SITE_URL . '>' . "\r\n";
    $Headers.='Reply-To: ' . $From . '@' . NONSSL_SITE_URL . "\r\n";
    $Headers.='X-Mailer: Project Luminance' . "\r\n";
    $Headers.='Message-Id: <' . make_secret() . '@' . NONSSL_SITE_URL . ">\r\n";
    $Headers.='X-Priority: 3' . "\r\n";
    mail($To, $Subject, $Body, $Headers, "-f " . $From . "@" . NONSSL_SITE_URL);
}

function get_size($Size, $Levels = 2)
{
    $Units = array(' B', ' KiB', ' MiB', ' GiB', ' TiB', ' PiB', ' EiB', ' ZiB', ' YiB');
    $Size = (double) $Size;
    for ($Steps = 0; abs($Size) >= 1024; $Size /= 1024, $Steps++) {

    }
    if (func_num_args() == 1 && $Steps >= 4) {
        $Levels++;
    }

    return number_format($Size, $Levels) . $Units[$Steps];
}

function get_bytes($Size)
{
    list($Value, $Unit) = sscanf($Size, "%f%s");
    $Unit = ltrim($Unit);
    if (empty($Unit)) {
        return $Value ? round($Value) : 0;
    }
    switch (strtolower($Unit[0])) {
        case 'k': return round($Value * 1024);
        case 'm': return round($Value * 1048576);
        case 'g': return round($Value * 1073741824);
        case 't': return round($Value * 1099511627776);
        default: return 0;
    }
}

function human_format($Number)
{
    $Steps = 0;
    while ($Number >= 1000) {
        $Steps++;
        $Number = $Number / 1000;
    }
    switch ($Steps) {
        case 0: return round($Number);
            break;
        case 1: return round($Number, 2) . 'k';
            break;
        case 2: return round($Number, 2) . 'M';
            break;
        case 3: return round($Number, 2) . 'G';
            break;
        case 4: return round($Number, 2) . 'T';
            break;
        case 5: return round($Number, 2) . 'P';
            break;
        default:
            return round($Number, 2) . 'E + ' . $Steps * 3;
    }
}

function is_number($Str)
{
    $Return = true;
    if ($Str < 0) {
        $Return = false;
    }
    // We're converting input to a int, then string and comparing to original
    $Return = ($Str == strval(intval($Str)) ? true : false);

    return $Return;
}

function file_string($EscapeStr)
{
    return str_replace(array('"', '*', '/', ':', '<', '>', '?', '\\', '|'), '', $EscapeStr);
}

// This is preferable to htmlspecialchars because it doesn't screw up upon a double escape
function display_str($Str)
{
    if ($Str === NULL || $Str === FALSE || is_array($Str)) {
        return '';
    }
    if ($Str != '' && !is_number($Str)) {
        $Str = make_utf8($Str);
        $Str = mb_convert_encoding($Str, "HTML-ENTITIES", "UTF-8");
        $Str = preg_replace("/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,5};)/m", "&amp;", $Str);

        $Replace = array(
            "'", '"', "<", ">",
            '&#128;', '&#130;', '&#131;', '&#132;', '&#133;', '&#134;', '&#135;', '&#136;', '&#137;', '&#138;', '&#139;', '&#140;', '&#142;', '&#145;', '&#146;', '&#147;', '&#148;', '&#149;', '&#150;', '&#151;', '&#152;', '&#153;', '&#154;', '&#155;', '&#156;', '&#158;', '&#159;'
        );

        $With = array(
            '&#39;', '&quot;', '&lt;', '&gt;',
            '&#8364;', '&#8218;', '&#402;', '&#8222;', '&#8230;', '&#8224;', '&#8225;', '&#710;', '&#8240;', '&#352;', '&#8249;', '&#338;', '&#381;', '&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8226;', '&#8211;', '&#8212;', '&#732;', '&#8482;', '&#353;', '&#8250;', '&#339;', '&#382;', '&#376;'
        );

        $Str = str_replace($Replace, $With, $Str);
    }

    return $Str;
}

// Use sparingly
function undisplay_str($Str)
{
    return mb_convert_encoding($Str, 'UTF-8', 'HTML-ENTITIES');
}

function make_utf8($Str)
{
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

function is_utf8($Str)
{
    return preg_match('%^(?:
        [\x09\x0A\x0D\x20-\x7E]			 // ASCII
        | [\xC2-\xDF][\x80-\xBF]			// non-overlong 2-byte
        | \xE0[\xA0-\xBF][\x80-\xBF]		// excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} // straight 3-byte
        | \xED[\x80-\x9F][\x80-\xBF]		// excluding surrogates
        | \xF0[\x90-\xBF][\x80-\xBF]{2}	 // planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}		 // planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2}	 // plane 16
        )*$%xs', $Str
    );
}

function str_plural($Str, $Num)
{
    if ($Num==1) return "$Num $Str";
    else return "$Num {$Str}s";
}

// Escape an entire array for output
// $Escape is either true, false, or a list of array keys to not escape
function display_array($Array, $Escape = array())
{
    foreach ($Array as $Key => $Val) {
        if ((!is_array($Escape) && $Escape == true) || !in_array($Key, $Escape)) {
            $Array[$Key] = display_str($Val);
        }
    }

    return $Array;
}

// Removes any inconsistencies in the list of tags before they are split into an array.
function cleanup_tags($s)
{
    return preg_replace(array('/[^A-Za-z0-9.-]/i', '/^\s*/s', '/\s*$/s', '/\s+/s'), array(" ", "", "", " ", ""), $s);
}

// Gets a tag ready for database input and display
function sanitize_tag($str)
{
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9.-]/', '', $str);
    //$str = htmlspecialchars($str);
    $str = db_string($str);

    return $str;
}

function check_tag_input($str)
{
    return preg_match('/[^a-z0-9.-]/', $str)==0;
}

function get_tag_synonym($Tag, $Sanitise = true)
{
        global $master;
        $DB = $master->olddb;
        $Cache = $master->cache;

        if ($Sanitise) $Tag = sanitize_tag($Tag);
        $DB->query("SELECT t.Name
                    FROM tag_synomyns AS ts JOIN tags as t ON t.ID = ts.TagID
                    WHERE Synomyn LIKE '".db_string($Tag)."'");
        if ($DB->record_count() > 0) { // should only ever be one but...
            list($TagName) = $DB->next_record();

            return $TagName;
        } else {
            return $Tag;
        }
}


/**
 * Return whether $Tag is a valid tag - more than 2** char long and not a stupid word
 * (** unless is 'hd','dp','bj','ts','sd','69','mf','3d','hj','bi')
 *
 * @param string $Tag The prospective tag to be evaluated
 * @return Boolean representing whether the tag is valid format (not banned)
 */
function is_valid_tag($Tag)
{
    static $Good2charTags;
    $len = strlen($Tag);
    if ( $len < 2 || $len > 32) return false;
    if ($len == 2) {
        if(!$Good2charTags) $Good2charTags = array('hd','dp','bj','ts','sd','69','mf','3d','hj','bi','tv','dv','da', '4k');
        if ( !in_array($Tag, $Good2charTags) ) return false;
    }

    return true;
}




// Generate a random string
function make_secret($Length = 32)
{
    $NumBytes = (int) round($Length / 2);
    $Secret = bin2hex(openssl_random_pseudo_bytes($NumBytes));
    return substr($Secret, 0, $Length);
}

function is_anon($IsAnon)
{
    if(check_perms('site_force_anon_uploaders')) return true;
    else if(check_perms('users_view_anon_uploaders')) return false;
    else return $IsAnon;
}

function anon_username_ifmatch($Username, $UsernameCheck, $IsAnon = false)
{
    return anon_username($Username, $IsAnon && $Username===$UsernameCheck);
}

function anon_username($Username, $IsAnon = false)
{
    // if not anon then just return username
    if (!$IsAnon && !check_perms('site_force_anon_uploaders')) return $Username;
    // if anon ...
    if (check_perms('users_view_anon_uploaders')) {
        return "anon [$Username]";
    } else {
        return 'anon ';
    }
}

function torrent_username($UserID, $Username, $IsAnon = false)
{
    // if not anon then just return username
    if (!$IsAnon && !check_perms('site_force_anon_uploaders')) return format_username($UserID, $Username);
    // if anon ...
    if (check_perms('users_view_anon_uploaders')) {
        return '<span class="anon_name"><a href="user.php?id=' . $UserID . '" title="' . $Username . '">anon</a></span>';
    } elseif (!$IsAnon) {
        return '<span class="anon_name" title="anonymous upload: your userclass is too low to see uploader info">anon</span>';
    } else {
        return '<span class="anon_name" title="anonymous upload: this uploader has chosen to hide their username">anon</span>';
    }
}

/*
  Returns a username string for display
  $Class and $Title can be omitted for an abbreviated version
  $IsDonor, $IsWarned and $IsEnabled can be omitted for a *very* abbreviated version
 */
function format_username($UserID, $Username, $IsDonor = false, $IsWarned = '0000-00-00 00:00:00',
                            $Enabled = 1, $Class = false, $Title = false, $DrawInBox = false, $GroupPerm = false, $DropDown=false, $Colorname=false) {
    global $master, $LoggedUser, $Classes;
    $DB = $master->olddb;
    $Cache = $master->cache;

    if ($UserID == 0) {
        return 'System';
    } elseif ($Username == '') {
        return "Unknown [$UserID]";
    }
    if ($Colorname) $str = '<a href="user.php?id=' . $UserID . '">' . '<span style="color:#'. $Classes[$Class]['Color'] . '">' .$Username . '</span></a>';
    else $str = '<a href="user.php?id=' . $UserID . '">' . $Username . '</a>';
    if ($DropDown && $LoggedUser['ID']!==$UserID) {
        $ddlist = '<li><a href="user.php?id='.$UserID.'" title="View '.$Username.'\'s profile">View profile</a></li>';
        if (check_perms('users_mod')) {
            $ddlist .= '<li><a href="staffpm.php?action=compose&amp;toid='.$UserID.'" title="Start a Staff Conversation with '.$Username.'">Staff Message</a></li>';
        }
        $ddlist .= '<li><a href="inbox.php?action=compose&amp;to='.$UserID.'" title="Send a Private Message to '.$Username.'">Send PM</a></li>';

        $Friends = $Cache->get_value('user_friends_'.$LoggedUser['ID']);
        if ($Friends===false) {
                $DB->query("SELECT FriendID, Type FROM friends WHERE UserID='$LoggedUser[ID]'");
                $Friends = $DB->to_array('FriendID');
                $Cache->cache_value('user_friends_'.$LoggedUser['ID'], $Friends);
        }
        $FType = isset($Friends[$UserID]) ? $Friends[$UserID]['Type'] : false;
        if (!$FType || $FType != 'friends') {
                $ddlist .= '<li><a href="friends.php?action=add&amp;friendid='.$UserID.'&amp;auth='.$LoggedUser['AuthKey'].'" title="Add this user to your friends list">Add to friends</a></li>';
        } elseif ($FType == 'friends') {
                $ddlist .= '<li><a href="friends.php?action=Defriend&amp;friendid='.$UserID.'&amp;auth='.$LoggedUser['AuthKey'].'" title="Remove this user from your friends list">Remove friend</a></li>';
        }
        if (!$FType || $FType != 'blocked') {
                $ddlist .= '<li><a href="friends.php?action=add&amp;friendid='.$UserID.'&amp;type=blocked&amp;auth='.$LoggedUser['AuthKey'].'" title="Add this user to your blocked list (blocks from sending PMs to you)">Block User</a></li>';
        } elseif ($FType == 'blocked') {
                $ddlist .= '<li><a href="friends.php?action=Unblock&amp;friendid='.$UserID.'&amp;type=blocked&amp;auth='.$LoggedUser['AuthKey'].'" title="Remove this user from your blocked list">Remove block</a></li>';
        }

        $str = "<div id=\"user_dropdown\">$str<ul>$ddlist</ul></div>";
    }
    $str.=($IsDonor) ? '<a href="donate.php"><img src="' . STATIC_SERVER . 'common/symbols/donor.png" alt="Donor" title="Donor" /></a>' : '';

    $str.=($IsWarned != '0000-00-00 00:00:00' && $IsWarned !== false) ? '<img src="' . STATIC_SERVER . 'common/symbols/warned.png" alt="Warned" title="Warned" />' : '';

    if ($Enabled != '1' || $Enabled != true) {
        if ($Enabled == '0')
            $str.= '<img src="' . STATIC_SERVER . 'common/symbols/unconfirmed.png" alt="Unconfirmed" title="This user has not confirmed their membership" />' ;
        else
            $str.= '<img src="' . STATIC_SERVER . 'common/symbols/disabled.png" alt="Banned" title="Be good, and you won\'t end up like this user" />' ;
    }

    if($GroupPerm) $str.= make_groupperm_string($GroupPerm, TRUE) ;  // ' (' . make_groupperm_string($GroupPerm, TRUE) . ')' ;
    if($Class && !$Colorname) $str.= ' (' . make_class_string($Class, TRUE) . ')' ;
    if ($Title) {
        if(($Class && !$Colorname) || $GroupPerm) $str.= '&nbsp;<span class="user_title">' . display_str($Title) . '</span>' ;
        else $str.= '&nbsp;(<span class="user_title">' . display_str($Title) . '</span>)' ;
    }
    if ($DrawInBox)
        ( $str = '<span class="user_name">' . $str . '</span>' );

    return $str;
}

function make_groupperm_string($GroupPermID, $Usespan = false)
{
    global $Classes;
    if ($Usespan === false) {
        return $Classes[$GroupPermID]['Name'];
    } else {
        return '<span alt="' . $GroupPermID . '" class="groupperm" title="'. $Classes[$GroupPermID]['Description'] .'" style="color:#' . $Classes[$GroupPermID]['Color'] . '" title="' . $Classes[$GroupPermID]['Description'] . '">' . $Classes[$GroupPermID]['Name'] . '</span>';
    }
}

function make_class_string($ClassID, $Usespan = false)
{
    global $Classes;
    if ($Usespan === false) {
        return $Classes[$ClassID]['Name'];
    } else {
        return '<span alt="' . $ClassID . '" class="rank" style="color:#'. $Classes[$ClassID]['Color'] . '">' . $Classes[$ClassID]['Name'] . '</span>';
    }
}

//Write to the group log
function write_group_log($GroupID, $TorrentID, $UserID, $Message, $Hidden)
{
    global $master, $Time;
    $DB = $master->olddb;
    $DB->query("INSERT INTO group_log (GroupID, TorrentID, UserID, Info, Time, Hidden) VALUES (" . (int) $GroupID . ", " . (int) $TorrentID . ", " . (int) $UserID . ", '" . db_string($Message) . "', '" . sqltime() . "', " . (int) $Hidden . ")");
}

// Write a message to the system log
function write_log($Message)
{
    global $master, $Time;
    $DB = $master->olddb;
    $DB->query('INSERT INTO log (Message, Time) VALUES (\'' . db_string($Message) . '\', \'' . sqltime() . '\')');
}

// write to user admincomment
function write_user_log($UserID, $Comment, $Sqltime = null)
{
    global $master;
    if (!$Sqltime) $Sqltime = sqltime();
    $master->db->raw_query("UPDATE users_info SET AdminComment=CONCAT_WS( '\n', :comment, AdminComment) WHERE UserID=:userid",
                   [':comment' => "$Sqltime - $Comment",
                    ':userid'  => $UserID]);
}

// Send a message to an IRC bot listening on SOCKET_LISTEN_PORT
function send_irc($Raw)
{
    $IRCSocket = fsockopen(SOCKET_LISTEN_ADDRESS, SOCKET_LISTEN_PORT);
    $Raw = str_replace(array("\n", "\r"), '', $Raw);
    fwrite($IRCSocket, $Raw);
    fclose($IRCSocket);
}

function delete_torrent($ID, $GroupID=0, $UserID = 0)
{
    global $master, $LoggedUser;
    $DB = $master->olddb;
    $Cache = $master->cache;
    if (!$GroupID) {
        $DB->query("SELECT GroupID, UserID FROM torrents WHERE ID='$ID'");
        list($GroupID, $UploaderID) = $DB->next_record();

    }
    if (!$UserID) {
        $DB->query("SELECT UserID FROM torrents WHERE ID='$ID'");
        list($UserID) = $DB->next_record();
    }

    $RecentUploads = $Cache->get_value('recent_uploads_'.$UserID);
    if (is_array($RecentUploads)) {
        foreach ($RecentUploads as $Key => $Recent) {
            if ($Recent['ID'] == $GroupID) {
                $Cache->delete_value('recent_uploads_'.$UserID);
            }
        }
    }

    $DB->query("SELECT info_hash FROM torrents WHERE ID = ".$ID);
    list($InfoHash) = $DB->next_record(MYSQLI_BOTH, false);
    $DB->query("DELETE FROM torrents WHERE ID = ".$ID);
    update_tracker('delete_torrent', array('info_hash' => rawurlencode($InfoHash), 'id' => $ID));

    $Cache->decrement('stats_torrent_count');

    $DB->query("SELECT COUNT(ID) FROM torrents WHERE GroupID='$GroupID' AND flags <> 1");
    list($Count) = $DB->next_record();

    if ($Count == 0) {
        delete_group($GroupID);
    } else {
        update_hash($GroupID);
    }

    // Torrent notifications
    $DB->query("SELECT UserID FROM users_notify_torrents WHERE TorrentID='$ID'");
    while (list($UserID) = $DB->next_record()) {
        $Cache->delete_value('notifications_new_'.$UserID);
    }
    $DB->query("DELETE FROM users_notify_torrents WHERE TorrentID='$ID'");

      $DB->query("DELETE FROM torrents_reviews WHERE GroupID='$GroupID'");

    $DB->query("UPDATE reportsv2 SET
            Status='Resolved',
            LastChangeTime='" . sqltime() . "',
            ModComment='Report already dealt with (Torrent deleted)'
        WHERE TorrentID=" . $ID . "
            AND Status != 'Resolved'");
    $Reports = $DB->affected_rows();
    if ($Reports) {
        $Cache->decrement('num_torrent_reportsv2', $Reports);
    }

    $DB->query("DELETE FROM torrents_files WHERE TorrentID='$ID'");
    $DB->query("DELETE FROM torrents_bad_tags WHERE TorrentID = " . $ID);
    $DB->query("DELETE FROM torrents_bad_folders WHERE TorrentID = " . $ID);
    $DB->query("DELETE FROM torrents_bad_files WHERE TorrentID = " . $ID);
    $Cache->delete_value('torrent_download_' . $ID);
    $Cache->delete_value('torrent_group_' . $GroupID);
    $Cache->delete_value('torrents_details_' . $GroupID);
}

function delete_group($GroupID)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $Cache->decrement('stats_group_count');

    // Collages
    $DB->query("SELECT CollageID FROM collages_torrents WHERE GroupID='$GroupID'");
    if ($DB->record_count()>0) {
        $CollageIDs = $DB->collect('CollageID');
        $DB->query("UPDATE collages SET NumTorrents=NumTorrents-1 WHERE ID IN (".implode(', ',$CollageIDs).")");
        $DB->query("DELETE FROM collages_torrents WHERE GroupID='$GroupID'");

        foreach ($CollageIDs as $CollageID) {
            $Cache->delete_value('collage_'.$CollageID);
        }
        $Cache->delete_value('torrent_collages_'.$GroupID);
    }

    // Requests
    $DB->query("SELECT ID FROM requests WHERE GroupID='$GroupID'");
    $Requests = $DB->collect('ID');
    $DB->query("UPDATE requests SET GroupID = NULL WHERE GroupID = '$GroupID'");
    foreach ($Requests as $RequestID) {
        $Cache->delete_value('request_'.$RequestID);
    }

        // Decrease the tag count, if it's not in use any longer and not an official tag, delete it from the list.
        $DB->query("SELECT tt.TagID, t.Uses, t.TagType, t.Name
                    FROM torrents_tags AS tt
                        JOIN tags AS t ON t.ID = tt.TagID
                    WHERE GroupID ='$GroupID'");
        $Tags = $DB->to_array();
        foreach ($Tags as $Tag) {
            $Uses = $Tag['Uses'] > 0 ?  $Tag['Uses'] - 1 : 0;
            if ($Tag['TagType'] == 'genre' || $Uses > 0) {
                $DB->query("UPDATE tags SET Uses=$Uses WHERE ID=".$Tag['TagID']);   //$TagID);
            } else {
                $DB->query("DELETE FROM tags WHERE ID=".$Tag['TagID']." AND TagType='other'");

                // Delete tag cache entry
                $Cache->delete_value('tag_id_'.$Tag['Name']);
            }
        }

    $DB->query("DELETE FROM group_log WHERE GroupID='$GroupID'");
    $DB->query("DELETE FROM torrents_group WHERE ID='$GroupID'");
    $DB->query("DELETE FROM torrents_tags WHERE GroupID='$GroupID'");
    $DB->query("DELETE FROM torrents_tags_votes WHERE GroupID='$GroupID'");
    $DB->query("DELETE FROM torrents_comments WHERE GroupID='$GroupID'");
    $DB->query("DELETE FROM bookmarks_torrents WHERE GroupID='$GroupID'");
    $DB->query("REPLACE INTO sphinx_delta (ID,Time) VALUES ('$GroupID',UNIX_TIMESTAMP())"); // Tells Sphinx that the group is removed

    $Cache->delete_value('torrents_details_'.$GroupID);
    $Cache->delete_value('torrent_group_'.$GroupID);
}

function warn_user($UserID, $Duration, $Reason)
{
    global $LoggedUser, $DB, $Cache, $Time, $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $DB->query("SELECT Warned FROM users_info WHERE UserID=" . $UserID . " AND Warned <> '0000-00-00 00:00:00'");
    if ($DB->record_count() > 0) {
        //User was already warned, appending new warning to old.
        list($OldDate) = $DB->next_record();
        $NewExpDate = date('Y-m-d H:i:s', strtotime($OldDate) + $Duration);

        send_pm($UserID, 0, db_string("You have received multiple warnings."), db_string("When you received your latest warning (Set to expire on " . date("Y-m-d", (time() + $Duration)) . "), you already had a different warning (Set to expire on " . date("Y-m-d", strtotime($OldDate)) . ").\n\n Due to this collision, your warning status will now expire at " . $NewExpDate . "."));

        $AdminComment = date("Y-m-d H:i:s") . ' - Warning (Clash) extended to expire at ' . $NewExpDate . ' by ' . $LoggedUser['Username'] . "\nReason: $Reason\n";

        $DB->query('UPDATE users_info SET
            Warned=\'' . db_string($NewExpDate) . '\',
            WarnedTimes=WarnedTimes+1,
            AdminComment=CONCAT(\'' . db_string($AdminComment) . '\',AdminComment)
            WHERE UserID=\'' . db_string($UserID) . '\'');
    } else {
        //Not changing, user was not already warned
        $WarnTime = time_plus($Duration);

        $AdminComment = date("Y-m-d H:i:s") . ' - Warned until ' . $WarnTime . ' by ' . $LoggedUser['Username'] . "\nReason: $Reason\n";

        $DB->query('UPDATE users_info SET
            Warned=\'' . db_string($WarnTime) . '\',
            WarnedTimes=WarnedTimes+1,
            AdminComment=CONCAT(\'' . db_string($AdminComment) . '\',AdminComment)
            WHERE UserID=\'' . db_string($UserID) . '\'');

        $master->repos->users->uncache($UserID);
    }
}

/* -- update_hash function ------------------------------------------------ */
/* ------------------------------------------------------------------------ */
/* This function is to update the cache and sphinx delta index to keep    */
/* everything up to date                                                  */
/* -- TODO ---------------------------------------------------------------- */
/* Add in tag sorting based on positive negative votes algo   - done mifune -            */
/* * *********************************************************************** */

function update_hash($GroupID)
{
    global $SpecialChars, $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $DB->query("UPDATE torrents_group SET TagList=(SELECT REPLACE(GROUP_CONCAT(tags.Name ORDER BY  (t.PositiveVotes-t.NegativeVotes) DESC SEPARATOR ' '),'.','_')
        FROM torrents_tags AS t
        INNER JOIN tags ON tags.ID=t.TagID
        WHERE t.GroupID='$GroupID'
        GROUP BY t.GroupID)
        WHERE ID='$GroupID'");

    $DB->query("REPLACE INTO sphinx_delta (ID, GroupName, TagList, NewCategoryID, Image, Time, Size, Snatched, Seeders, Leechers, FreeTorrent, FileList, SearchText)
        SELECT
        g.ID AS ID,
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
        GROUP_CONCAT(REPLACE(REPLACE(FileList, '|||', '\n '), '_', ' ') SEPARATOR '\n ') AS FileList,
                g.SearchText
        FROM torrents AS t
        JOIN torrents_group AS g ON g.ID=t.GroupID
        WHERE g.ID=$GroupID
        GROUP BY g.ID");

    $Cache->delete_value('torrents_details_'.$GroupID);
    $Cache->delete_value('torrent_group_'.$GroupID);
}

//  OPTIMISED a bit more for mass sending (only put in an array of numbers if fromID==system (0)
// this function sends a PM to the userid $ToID and from the userid $FromID, sets date to now
// this function no longer uses db_string() so you will need to escape strings before using this function!
// set userid to 0 for a PM from 'system'
// if $ConvID is not set, it auto increments it, ie. starting a new conversation
function send_pm($ToID, $FromID, $Subject, $Body, $ConvID='')
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    if (!is_array($ToID)) $ToID = array($ToID);

    // Clear the caches of the inbox and sentbox
    foreach ($ToID as $key=>$ID) {
        if (!is_number($ID)) return false;
        // Don't allow users to send messages to the system
        if ($ID == 0) unset($ToID[$key]);
        if ($ID == $FromID) unset($ToID[$key]); // or themselves
    }
    if (count($ToID)==0) return false;
    if (count($ToID)>1 && $FromID!==0) return false; // masspms not from the system with the same convID dont work
    $sqltime = sqltime();

    if ($ConvID == '') { // new pm

        $DB->query("INSERT INTO pm_conversations (Subject) VALUES ('" . $Subject . "')");
        $ConvID = $DB->inserted_id();

        if ($FromID != 0) {
            $Values = "('$FromID', '$ConvID', '0','1','$sqltime', '$sqltime', '0'),";
        } else {
            $Values = "";
        }
        $Values .= "('".implode("', '$ConvID', '1','0', '$sqltime', '$sqltime', '1'), ('", $ToID)."', '$ConvID', '1','0', '$sqltime', '$sqltime', '1')";

        $DB->query("INSERT INTO pm_conversations_users
                                        (UserID, ConvID, InInbox, InSentbox, SentDate, ReceivedDate, UnRead) VALUES
                                        $Values");

    } else { // responding to exisiting

        $DB->query("UPDATE pm_conversations_users SET
                InInbox='1',
                UnRead='1',
                ReceivedDate='$sqltime'
                WHERE UserID IN (" . implode(',', $ToID) . ")
                AND ConvID='$ConvID'");

        $DB->query("UPDATE pm_conversations_users SET
                InSentbox='1',
                SentDate='$sqltime'
                WHERE UserID='$FromID'
                AND ConvID='$ConvID'");
    }

    $DB->query("INSERT INTO pm_messages
            (SenderID, ConvID, SentDate, Body) VALUES
            ('$FromID', '$ConvID', '$sqltime', '" . $Body . "')");

    // Clear the caches of the inbox and sentbox
    foreach ($ToID as $ID) {
        $Cache->delete_value('inbox_new_' . $ID);
    }
    if ($FromID != 0) $Cache->delete_value('inbox_new_' . $FromID);
    // DEBUG only:
    //write_log("Sent MassPM to ".count($ToID)." users. ConvID: $ConvID  Subject: $Subject");
    return $ConvID;
}

//Create thread function, things should already be escaped when sent here.
//Almost all the code is stolen straight from the forums and tailored for new posts only
function create_thread($ForumID, $AuthorID, $Title, $PostBody)
{
    global $master, $Time;
    $DB = $master->olddb;
    $Cache = $master->cache;

    if (!$ForumID || !$AuthorID || !is_number($AuthorID) || !$Title || !$PostBody) {
        return -1;
    }

    $DB->query("SELECT Username FROM users_main WHERE ID=" . $AuthorID);
    if ($DB->record_count() < 1) {
        return -2;
    }
    list($AuthorName) = $DB->next_record();

    $ThreadInfo = array();
    $ThreadInfo['IsLocked'] = 0;
    $ThreadInfo['IsSticky'] = 0;

    $DB->query("INSERT INTO forums_topics
        (Title, AuthorID, ForumID, LastPostTime, LastPostAuthorID)
        Values
        ('" . $Title . "', '" . $AuthorID . "', '$ForumID', '" . sqltime() . "', '" . $AuthorID . "')");
    $TopicID = $DB->inserted_id();
    $Posts = 1;

    $DB->query("INSERT INTO forums_posts
            (TopicID, AuthorID, AddedTime, Body)
            VALUES
            ('$TopicID', '" . $AuthorID . "', '" . sqltime() . "', '" . $PostBody . "')");
    $PostID = $DB->inserted_id();

    $DB->query("UPDATE forums SET
                NumPosts          = NumPosts+1,
                NumTopics         = NumTopics+1,
                LastPostID        = '$PostID',
                LastPostAuthorID  = '" . $AuthorID . "',
                LastPostTopicID   = '$TopicID',
                LastPostTime	  = '" . sqltime() . "'
                WHERE ID = '$ForumID'");

    $DB->query("UPDATE forums_topics SET
            NumPosts          = NumPosts+1,
            LastPostID        = '$PostID',
            LastPostAuthorID  = '" . $AuthorID . "',
            LastPostTime      = '" . sqltime() . "'
            WHERE ID = '$TopicID'");

    // Bump this topic to head of the cache
    list($Forum,,, $Stickies) = $Cache->get_value('forums_' . $ForumID);
    if (!empty($Forum)) {
        if (count($Forum) == TOPICS_PER_PAGE && $Stickies < TOPICS_PER_PAGE) {
            array_pop($Forum);
        }
        $DB->query("SELECT f.IsLocked, f.IsSticky, f.NumPosts FROM forums_topics AS f WHERE f.ID ='$TopicID'");
        list($IsLocked, $IsSticky, $NumPosts) = $DB->next_record();
        $Part1 = array_slice($Forum, 0, $Stickies, true); //Stickys
        $Part2 = array(
            $TopicID => array(
                'ID' => $TopicID,
                'Title' => $Title,
                'AuthorID' => $AuthorID,
                'AuthorUsername' => $AuthorName,
                'IsLocked' => $IsLocked,
                'IsSticky' => $IsSticky,
                'NumPosts' => $NumPosts,
                'LastPostID' => $PostID,
                'LastPostTime' => sqltime(),
                'LastPostAuthorID' => $AuthorID,
                'LastPostUsername' => $AuthorName
            )
        ); //Bumped thread
        $Part3 = array_slice($Forum, $Stickies, TOPICS_PER_PAGE, true); //Rest of page
        if ($Stickies > 0) {
            $Part1 = array_slice($Forum, 0, $Stickies, true); //Stickies
            $Part3 = array_slice($Forum, $Stickies, TOPICS_PER_PAGE - $Stickies - 1, true); //Rest of page
        } else {
            $Part1 = array();
            $Part3 = $Forum;
        }
        if (is_null($Part1)) {
            $Part1 = array();
        }
        if (is_null($Part3)) {
            $Part3 = array();
        }
        $Forum = $Part1 + $Part2 + $Part3;
        $Cache->cache_value('forums_' . $ForumID, array($Forum, '', 0, $Stickies), 0);
    }

    //Update the forum root
    $Cache->begin_transaction('forums_list');
    $UpdateArray = array(
        'NumPosts' => '+1',
        'LastPostID' => $PostID,
        'LastPostAuthorID' => $AuthorID,
        'Username' => $AuthorName,
        'LastPostTopicID' => $TopicID,
        'LastPostTime' => sqltime(),
        'Title' => $Title,
        'IsLocked' => $ThreadInfo['IsLocked'],
        'IsSticky' => $ThreadInfo['IsSticky']
    );

    $UpdateArray['NumTopics'] = '+1';

    $Cache->update_row($ForumID, $UpdateArray);
    $Cache->commit_transaction(0);

    $CatalogueID = floor((POSTS_PER_PAGE * ceil($Posts / POSTS_PER_PAGE) - POSTS_PER_PAGE) / THREAD_CATALOGUE);
    $Cache->begin_transaction('thread_' . $TopicID . '_catalogue_' . $CatalogueID);
    $Post = array(
        'ID' => $PostID,
        'AuthorID' => $LoggedUser['ID'],
        'AddedTime' => sqltime(),
        'Body' => $PostBody,
        'EditedUserID' => 0,
        'EditedTime' => '0000-00-00 00:00:00',
        'Username' => ''
    );
    $Cache->insert('', $Post);
    $Cache->commit_transaction(0);

    $Cache->begin_transaction('thread_' . $TopicID . '_info');
    $Cache->update_row(false, array('Posts' => '+1', 'LastPostAuthorID' => $AuthorID));
    $Cache->commit_transaction(0);

    $Cache->delete_value('latest_topics_forum_'.$ForumID);

    return $TopicID;
}

// Check to see if a user has the permission to perform an action
function check_perms($PermissionName, $MinClass = 0)
{
    global $master, $LoggedUser;

    return ($master->auth->isAllowed($PermissionName) && $LoggedUser['Class'] >= $MinClass);
}

function check_force_anon($UserID)
{
    global $LoggedUser;

    return $UserID == $LoggedUser['ID'] || !check_perms('site_force_anon_uploaders')  ;
}

// Function to get data and torrents for an array of GroupIDs.
// In places where the output from this is merged with sphinx filters, it will be in a different order.
function get_groups($GroupIDs, $Return = true, $Torrents = true)
{
    global $master, $LoggedUser;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $Found = array_flip($GroupIDs);
    $NotFound = array_flip($GroupIDs);
    $Key = $Torrents ? 'torrent_group_' : 'torrent_group_light_';

    foreach ($GroupIDs as $GroupID) {
        $Data = $Cache->get_value($Key.$GroupID);
        if (!empty($Data) && (@$Data['ver'] >= 5)) {
            unset($NotFound[$GroupID]);
            $Found[$GroupID] = $Data['d'];
            if ($Torrents) {
                foreach ($Found[$GroupID]['Torrents'] as $TID=>&$TData) {
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

    $IDs = implode(',',array_flip($NotFound));

    /*
    Changing any of these attributes returned will cause very large, very dramatic site-wide chaos.
    Do not change what is returned or the order thereof without updating:
    torrents, collages, bookmarks, better, the front page,
    and anywhere else the get_groups function is used.
    */

    if (count($NotFound)>0) {
        $DB->query("SELECT g.ID, g.Name, g.TagList FROM torrents_group AS g WHERE g.ID IN ($IDs)");

        while ($Group = $DB->next_record(MYSQLI_ASSOC, true)) {
            unset($NotFound[$Group['ID']]);
            $Found[$Group['ID']] = $Group;
            $Found[$Group['ID']]['Torrents'] = array();
        }

        if ($Torrents) {

            $DB->query("SELECT t.ID, t.UserID, um.Username, t.GroupID, FileCount, FreeTorrent, double_seed,
                                        Size, Leechers, Seeders, Snatched, t.Time, t.ID AS HasFile, r.ReportCount, t.Anonymous
                          FROM torrents AS t
                          JOIN users_main AS um ON t.UserID=um.ID
                     LEFT JOIN (SELECT TorrentID, count(*) as ReportCount FROM reportsv2
                                 WHERE Type != 'edited' AND Status != 'Resolved' GROUP BY TorrentID) AS r ON r.TorrentID=t.ID
                         WHERE t.GroupID IN($IDs)
                      ORDER BY GroupID DESC, t.ID");

            while ($Torrent = $DB->next_record(MYSQLI_ASSOC, true)) {
                $Found[$Torrent['GroupID']]['Torrents'][$Torrent['ID']] = $Torrent;

                $CacheTime = $Torrent['Seeders']==0 ? 120 : 900;
                $TorrentPeerInfo = array('Seeders'=>$Torrent['Seeders'],'Leechers'=>$Torrent['Leechers'],'Snatched'=>$Torrent['Snatched']);
                $Cache->cache_value('torrent_peers_'.$Torrent['ID'], $TorrentPeerInfo, $CacheTime);

                $Cache->cache_value('torrent_group_'.$Torrent['GroupID'], array('ver'=>5, 'd'=>$Found[$Torrent['GroupID']]), 0);
                $Cache->cache_value('torrent_group_light_'.$Torrent['GroupID'], array('ver'=>5, 'd'=>$Found[$Torrent['GroupID']]), 0);
            }
        } else {
            foreach ($Found as $Group) {
                $Cache->cache_value('torrent_group_light_'.$Group['ID'], array('ver'=>5, 'd'=>$Found[$Group['ID']]), 0);
            }
        }
    }

    if ($Return) { // If we're interested in the data, and not just caching it
        $Matches = array('matches'=>$Found, 'notfound'=>array_flip($NotFound));

        return $Matches;
    }
}

function get_peers($TorrentID)
{
    global $master, $LoggedUser;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $TorrentPeerInfo = $Cache->get_value('torrent_peers_'.$TorrentID);
    if ($TorrentPeerInfo===false) {
            // testing with 'dye'
        $DB->query("SELECT Seeders, Leechers, Snatched FROM torrents WHERE ID ='$TorrentID'");
        $TorrentPeerInfo = $DB->next_record(MYSQLI_ASSOC) ;
        $CacheTime = $TorrentPeerInfo['Seeders']==0 ? 120 : 900;
        $Cache->cache_value('torrent_peers_'.$TorrentID, $TorrentPeerInfo, $CacheTime);
    }

    return $TorrentPeerInfo;
}

function get_last_review($GroupID)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $LastReview = $Cache->get_value('torrent_review_'.$GroupID);
    if ($LastReview===false || $LastReview['ver']<2) {
        $DB->query("SELECT tr.ID,
                           tr.Status,
                           tr.Time,
                           tr.KillTime,
                           IF(tr.ReasonID = 0, tr.Reason, rr.Description) AS StatusDescription,
                           tr.ConvID,
                           tr.UserID AS UserID,
                           u.Username AS Username
                      FROM torrents_reviews AS tr
                 LEFT JOIN review_reasons AS rr ON rr.ID = tr.ReasonID
                 LEFT JOIN users_main AS u ON u.ID=tr.UserID
                     WHERE tr.GroupID=$GroupID
                  ORDER BY tr.Time DESC
                     LIMIT 1 " );
        $LastReviewRow = $DB->next_record(MYSQLI_ASSOC);
        if (!array_key_exists('ID', $LastReviewRow)) {
            $LastReviewRow['ID'] = 0;
        }
        if ($LastReviewRow['Status']!='Pending') { // if last review log is not from a user
            $LastReviewRow['StaffID']=$LastReviewRow['UserID'];
            $LastReviewRow['Staffname']=$LastReviewRow['Username'];
        } else {
            $DB->query("SELECT tr.UserID AS StaffID, u.Username AS Staffname
                          FROM torrents_reviews AS tr
                     LEFT JOIN users_main AS u ON u.ID=tr.UserID
                     WHERE tr.GroupID=$GroupID AND tr.Status!='Pending'
                  ORDER BY tr.Time DESC
                     LIMIT 1 ");
            $LastStaffReview = $DB->next_record(MYSQLI_ASSOC);
            $LastReviewRow['StaffID']=$LastStaffReview['StaffID'];
            $LastReviewRow['Staffname']=$LastStaffReview['Staffname'];
        }
        $LastReview = array('ver'=>2, 'd'=>$LastReviewRow) ;
        $Cache->cache_value('torrent_review_'.$GroupID, $LastReview, 0);
    }

    return $LastReview['d'];
}

// moved this here from requests/functions.php as get_requests() is dependent
function get_request_tags($RequestID)
{
    global $master;
    $DB = $master->olddb;

    $DB->query("SELECT rt.TagID,
                    t.Name
                FROM requests_tags AS rt
                    JOIN tags AS t ON rt.TagID=t.ID
                WHERE rt.RequestID = ".$RequestID."
                ORDER BY rt.TagID ASC");
    $Tags = $DB->to_array();
    $Results = array();
    foreach ($Tags as $TagsRow) {
        list($TagID, $TagName) = $TagsRow;
        $Results[$TagID]= $TagName;
    }

    return $Results;
}

//Function to get data from an array of $RequestIDs.
//In places where the output from this is merged with sphinx filters, it will be in a different order.
function get_requests($RequestIDs, $Return = true)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $Found = array_flip($RequestIDs);
    $NotFound = array_flip($RequestIDs);

    foreach ($RequestIDs as $RequestID) {
        $Data = $Cache->get_value('request_' . $RequestID);
        if (!empty($Data)) {
            unset($NotFound[$RequestID]);
            $Found[$RequestID] = $Data;
        }
    }

    $IDs = implode(',', array_flip($NotFound));

    /*
      Don't change without ensuring you change everything else that uses get_requests()
     */

    if (count($NotFound) > 0) {
        $DB->query("SELECT
                    r.ID AS ID,
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
                    uploader.Username
                FROM requests AS r
                    LEFT JOIN users_main AS u ON u.ID=r.UserID
                    LEFT JOIN users_main AS filler ON filler.ID=FillerID AND FillerID!=0
                    LEFT JOIN users_main AS uploader ON uploader.ID=r.UploaderID AND r.UploaderID!=0
                WHERE r.ID IN (" . $IDs . ")
                ORDER BY ID");

        $Requests = $DB->to_array();
        foreach ($Requests as $Request) {
            unset($NotFound[$Request['ID']]);
            $Request['Tags'] = get_request_tags($Request['ID']);
            $Found[$Request['ID']] = $Request;
            $Cache->cache_value('request_' . $Request['ID'], $Request, 0);
        }
    }

    if ($Return) { // If we're interested in the data, and not just caching it
        $Matches = array('matches' => $Found, 'notfound' => array_flip($NotFound));

        return $Matches;
    }
}

function update_sphinx_requests($RequestID)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $DB->query("REPLACE INTO sphinx_requests_delta (
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
                wHERE ID = " . $RequestID . "
                GROUP BY r.ID");

    $Cache->delete_value('request_'.$RequestID);
}

function get_tags($TagNames)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $TagIDs = array();
    foreach ($TagNames as $Index => $TagName) {
        $Tag = $Cache->get_value('tag_id_' . $TagName);
        if (is_array($Tag)) {
            unset($TagNames[$Index]);
            $TagIDs[$Tag['ID']] = $Tag['Name'];
        }
    }
    if (count($TagNames) > 0) {
        $implode = implode("', '", $TagNames);
        // Using UNION to prevent the query from creating a temporary table, we don't need to join here
        $DB->query("SELECT ID, Name FROM tags WHERE Name IN ('" . $implode . "')
                    UNION SELECT TagID AS ID, Synomyn AS Name FROM tag_synomyns WHERE Synomyn IN ('" . $implode . "')");
        $SQLTagIDs = $DB->to_array();
        foreach ($SQLTagIDs as $Tag) {
            $TagIDs[$Tag['ID']] = $Tag['Name'];
            $Cache->cache_value('tag_id_' . $Tag['Name'], $Tag, 0);
        }
    }

    return($TagIDs);
}

function get_overlay_html($GroupName, $Username, $Image, $Seeders, $Leechers, $Size, $Snatched)
{
    $OverImage = $Image != '' ? $Image : '/static/common/noartwork/noimage.png';

    # Temporary solution for image load on fapping - TODO proper permanent solution, this is ugly
    $matches = array();
    if (preg_match('#^(http://fapping\.empornium\.sx/images/.*)\.(gif|jpg|png)$#', $OverImage, $matches)) {
        if (substr($matches[1], -3) != '.th') {
            $OverImage = $matches[1].'.th.'.$matches[2];
        }
    }

    $OverName = mb_strlen($GroupName) <= 60 ? $GroupName : mb_substr($GroupName, 0, 56) . '...';
    $SL = ($Seeders == 0 ? "<span class=r00>" . number_format($Seeders) . "</span>" : number_format($Seeders)) . " / " . number_format($Leechers);

    $Overlay = "<table class=overlay><tr><td class=overlay colspan=2><strong>" . $OverName . "</strong></td><tr><td class=leftOverlay><img style='max-width: 150px;' src=" . $OverImage . "></td><td class=rightOverlay><strong>Uploader:</strong> $Username<br /><br /><strong>Size:</strong> " . get_size($Size) . "<br /><br /><strong>Snatched:</strong> " . number_format($Snatched) . "<br /><br /><strong>Seeders/Leechers:</strong> " . $SL . "</td></tr></table>";

    return $Overlay;
}

function torrent_icons($Data, $TorrentID, $Review, $IsBookmarked) {  //  $UserID,
    global $master, $LoggedUser, $TorrentUserStatus, $Sitewide_Freeleech_On, $Sitewide_Freeleech;
    $DB = $master->olddb;
    $Cache = $master->cache;
        $SeedTooltip='';
        $FreeTooltip='';
        if ($Data['FreeTorrent'] == '1') {
            $FreeTooltip = "Unlimited Freeleech";
        } elseif ($Data['FreeTorrent'] == '2') {
            $FreeTooltip = "Neutral Freeleech";
        } elseif ($Sitewide_Freeleech_On) {
            $FreeTooltip = "Sitewide Freeleech for ".time_diff($Sitewide_Freeleech, 2,false,false,0);
        }

        if ($Data['double_seed'] == '1') {
            $SeedTooltip = "Unlimited Doubleseed";
        }

        $UserID = $LoggedUser['ID'];
        $TokenTorrents = $Cache->get_value('users_tokens_' .$UserID );
        if ($TokenTorrents===false) {
            $DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$UserID");
            $TokenTorrents = $DB->to_array('TorrentID');
            $Cache->cache_value('users_tokens_' . $UserID, $TokenTorrents);
        }

        if (!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['FreeLeech'] > sqltime()) {
            $FreeTooltip = "Personal Freeleech for ".time_diff($TokenTorrents[$TorrentID]['FreeLeech'], 2,false,false,0);
        }

        if (!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['DoubleSeed'] > sqltime()) {
            $SeedTooltip = "Personal Doubleseed for ".time_diff($TokenTorrents[$TorrentID]['DoubleSeed'], 2,false,false,0);
        }

        $Icons = '';
        if ($IsBookmarked)
            $Icons .= '<img src="static/styles/'.$LoggedUser['StyleName'].'/images/star16.png" alt="bookmarked" title="You have this torrent bookmarked" />';

        if ($SeedTooltip)
            $Icons .= '&nbsp;<img src="static/common/symbols/doubleseed.gif" alt="DoubleSeed" title="'.$SeedTooltip.'" />';
        if ($FreeTooltip)
            $Icons .= '&nbsp;<img src="static/common/symbols/freedownload.gif" alt="Freeleech" title="'.$FreeTooltip.'" />';

        $SnatchedTorrents = $Cache->get_value('users_torrents_snatched_' .$UserID );
        if ($SnatchedTorrents===false) {
            $DB->query("SELECT DISTINCT x.fid as TorrentID
                          FROM xbt_snatched AS x JOIN torrents AS t ON t.ID=x.fid
                         WHERE x.uid='$UserID' ");

            $SnatchedTorrents = $DB->to_array('TorrentID');
            $Cache->cache_value('users_torrents_snatched_' . $UserID, $SnatchedTorrents, 21600);
        }

        $GrabbedTorrents = $Cache->get_value('users_torrents_grabbed_' .$UserID );
        if ($GrabbedTorrents===false) {
            $DB->query("SELECT DISTINCT ud.TorrentID
                                  FROM users_downloads AS ud JOIN torrents AS t ON t.ID=ud.TorrentID
                                 WHERE ud.UserID='$UserID' ");

            $GrabbedTorrents = $DB->to_array('TorrentID');
            $Cache->cache_value('users_torrents_grabbed_' . $UserID, $GrabbedTorrents);
        }

        //icon_disk_grabbed icon_disk_snatched
        if ( !$Review || !$Review['Status'] ||  $Review['Status'] == 'Okay' || check_perms('torrents_download_override')) {

            if ($TorrentUserStatus[$TorrentID]['PeerStatus'] == 'S') {
                $Icons .= '<a href="torrents.php?action=download&amp;id='.$TorrentID.'&amp;authkey='.$LoggedUser['AuthKey'].'&amp;torrent_pass='.$LoggedUser['torrent_pass'].'" title="Currently Seeding Torrent">';
                $Icons .= '<span class="icon icon_disk_seed"></span>';
                $Icons .= '</a>';
            } elseif ($TorrentUserStatus[$TorrentID]['PeerStatus'] == 'L') {
                $Icons .= '<a href="torrents.php?action=download&amp;id='.$TorrentID.'&amp;authkey='.$LoggedUser['AuthKey'].'&amp;torrent_pass='.$LoggedUser['torrent_pass'].'"  title="Currently Leeching Torrent">';
                $Icons .= '<span class="icon icon_disk_leech"></span>';
                $Icons .= '</a>';
            } elseif (isset($SnatchedTorrents[$TorrentID])) {
                $Icons .= '<a href="torrents.php?action=download&amp;id='.$TorrentID.'&amp;authkey='.$LoggedUser['AuthKey'].'&amp;torrent_pass='.$LoggedUser['torrent_pass'].'" title="Previously Snatched Torrent">';
                $Icons .= '<span class="icon icon_disk_snatched"></span>';
                $Icons .= '</a>';
            } elseif (isset($GrabbedTorrents[$TorrentID] )) {
                $Icons .= '<a href="torrents.php?action=download&amp;id='.$TorrentID.'&amp;authkey='.$LoggedUser['AuthKey'].'&amp;torrent_pass='.$LoggedUser['torrent_pass'].'"  title="Previously Grabbed Torrent File">';
                $Icons .= '<span class="icon icon_disk_grabbed"></span>';
                $Icons .= '</a>';

            } elseif (empty($TorrentUserStatus[$TorrentID])) {
                $Icons .= '<a href="torrents.php?action=download&amp;id='.$TorrentID.'&amp;authkey='.$LoggedUser['AuthKey'].'&amp;torrent_pass='.$LoggedUser['torrent_pass'].'" title="Download Torrent">';
                $Icons .= '<span class="icon icon_disk_none"></span>';
                $Icons .= '</a>';
            }
        } else {

            if ($TorrentUserStatus[$TorrentID]['PeerStatus'] == 'S') {
                $Icons .= '<span class="icon icon_disk_seed" title="Warning: You are seeding a torrent that is marked for deletion"></span> ';
            } elseif ($TorrentUserStatus[$TorrentID]['PeerStatus'] == 'L') {
                $Icons .= '<span class="icon icon_disk_leech" title="Warning: You are seeding a torrent that is marked for deletion"></span> ';
            } elseif (isset($SnatchedTorrents[$TorrentID])) {
                $Icons .= '<span class="icon icon_disk_snatched" title="Previously Snatched Torrent"></span>';
            } elseif (isset($GrabbedTorrents[$TorrentID] )) {
                $Icons .= '<span class="icon icon_disk_grabbed" title="Previously Grabbed Torrent File"></span>';

            }
        }

        if ($Review) {
            if (check_perms('torrents_review')) {
                $Icons .= get_status_icon_staff($Review['Status'], $Review['Staffname'], $Review['StatusDescription']);
            } else {
                $Icons .= get_status_icon($Review['Status']) ;
            }
        }

        return '<span style="float:right">'.$Icons.'</span>';

}

function get_status_icon_staff($Status, $Staffname, $Reason)
{
    if ($Status == 'Warned' || $Status == 'Pending')
        return "<span title=\"$Status: [$Reason] by $Staffname\" class=\"icon icon_warning\"></span>";
    elseif ($Status == 'Okay')
        return '<span title="This torrent has been checked by staff ('.$Staffname.') and is okay" class="icon icon_okay"></span>';
    else return '';
}

function get_status_icon($Status)
{
    if ($Status == 'Warned' || $Status == 'Pending') return '<span title="This torrent will be automatically deleted unless the uploader fixes it" class="icon icon_warning"></span>';
    elseif ($Status == 'Okay') return '<span title="This torrent has been checked by staff and is okay" class="icon icon_okay"></span>';
    else return '';
}

function get_num_comments($GroupID)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    $Results = $Cache->get_value('torrent_comments_'.$GroupID);
    if ($Results === false) {
          $DB->query("SELECT
                      COUNT(c.ID)
                      FROM torrents_comments as c
                      WHERE c.GroupID = '$GroupID'");
          list($Results) = $DB->next_record();
          $Cache->cache_value('torrent_comments_'.$GroupID, $Results, 0);
    }

    return $Results;
}

// Echo data sent in a form, typically a text area
function form($Index, $Return = false)
{
    if (!empty($_GET[$Index])) {
        if ($Return) {
            return display_str($_GET[$Index]);
        } else {
            echo display_str($_GET[$Index]);
        }
    }
}

// Check/select tickboxes and <select>s
function selected($Name, $Value, $Attribute='selected', $Array = array())
{
    if (!empty($Array)) {
        if (isset($Array[$Name]) && $Array[$Name] !== '') {
            if ($Array[$Name] == $Value) {
                echo ' ' . $Attribute . '="' . $Attribute . '"';
            }
        }
    } else {
        if (isset($_GET[$Name]) && $_GET[$Name] !== '') {
            if ($_GET[$Name] == $Value) {
                echo ' ' . $Attribute . '="' . $Attribute . '"';
            }
        }
    }
}

function error($Error, $Ajax=false)
{
    global $Debug;
    require(SERVER_ROOT . '/sections/error/index.php');
    $Debug->profile();
    die();
}

/**
 * @param BanReason 0 - Unknown, 1 - Manual, 2 - Ratio, 3 - Inactive, 4 - Cheating.
 */
function disable_users($UserIDs, $AdminComment, $BanReason = 1)
{
    global $master;
    $DB = $master->olddb;
    $Cache = $master->cache;

    if (!is_array($UserIDs)) {
        $UserIDs = array($UserIDs);
    }
    $DB->query("UPDATE users_info AS i JOIN users_main AS m ON m.ID=i.UserID
        SET m.Enabled='2',
        m.can_leech='0',
        i.AdminComment = CONCAT('" . sqltime() . " - " . ($AdminComment ? $AdminComment : 'Disabled by system') . "\n', i.AdminComment),
        i.BanDate='" . sqltime() . "',
        i.BanReason='" . $BanReason . "',
        i.RatioWatchDownload=" . ($BanReason == 2 ? 'm.Downloaded' : "'0'") . "
        WHERE m.ID IN(" . implode(',', $UserIDs) . ") ");
    $Cache->decrement('stats_user_count', $DB->affected_rows() / 2); # Two tables are affected, meaning affected_rows() is actually twice the number of disabled users.
    foreach ($UserIDs as $UserID) {
        $Cache->delete_value('enabled_' . $UserID);
        $Cache->delete_value('user_stats_' . $UserID);
        $master->repos->users->uncache($UserID);

        $DB->query("SELECT ID FROM sessions WHERE UserID='$UserID' AND Active = 1");
        while (list($SessionID) = $DB->next_record()) {
            $Cache->delete_value('_entity_Session_' . $SessionID);
        }

        $DB->query("DELETE FROM sessions WHERE UserID='$UserID'");
    }
    $DB->query("SELECT torrent_pass FROM users_main WHERE ID in (" . implode(", ", $UserIDs) . ")");
    $PassKeys = $DB->collect('torrent_pass');
    $Concat = "";
    foreach ($PassKeys as $PassKey) {
        if (strlen($Concat) > 4000) {
            update_tracker('remove_users', array('passkeys' => $Concat));
            $Concat = $PassKey;
        } else {
            $Concat .= $PassKey;
        }
    }
    update_tracker('remove_users', array('passkeys' => $Concat));
}

/**
 * Send a GET request over a socket directly to the tracker
 * For example, update_tracker('change_passkey', array('oldpasskey' => OLD_PASSKEY, 'newpasskey' => NEW_PASSKEY)) will send the request:
 * GET /tracker_32_char_secret_code/update?action=change_passkey&oldpasskey=OLD_PASSKEY&newpasskey=NEW_PASSKEY HTTP/1.1
 * @param $Action The action to send
 * @param $Updates An associative array of key->value pairs to send to the tracker
 */
function update_tracker($Action, $Updates, $ToIRC = false)
{
    global $master;
    $Cache = $master->cache;

    // Build request
    $Get = TRACKER_SECRET . "/update?action=$Action";
    foreach ($Updates as $Key => $Value) {
        $Get .= "&$Key=$Value";
    }

    $MaxAttempts = 3;
    $Err = false;
    $Result = send_tracker_request($Get, $MaxAttempts, $Err);
    return ($Result !== false);
}

function send_tracker_request($Get, $MaxAttempts = 1, &$Err = false) {
    global $master;
    $Header  = "GET /$Get HTTP/1.1\r\nHost: ";
    $Header .= parse_url($master->settings->main->announce_url)['host'];
    $Header .= "\r\nConnection: Close\r\n\r\n";
    $Attempts = 0;
    $Sleep = 0;
    $Success = false;
    $Response = '';
    $Data = '';
    $StartTime = microtime(true);
    while (!$Success && $Attempts++ < $MaxAttempts) {
        if ($Sleep) {
            sleep($Sleep);
        }
        $Sleep = 6;

        // Send request - don't throw errors here
        $File = @fsockopen(TRACKER_HOST, TRACKER_PORT, $ErrorNum, $ErrorString);
        if ($File) {
            if (@fwrite($File, $Header) === false) {
                $Err = "Failed to fwrite()";
                $Sleep = 3;
                continue;
            }
        } else {
            $Err = "Failed to fsockopen() - $ErrorNum - $ErrorString";
            continue;
        }

        // Check for response.
        $Response = '';
        while (!feof($File)) {
            $Response .= fread($File, 1024);
        }
        $DataStart = strpos($Response, "\r\n\r\n") + 4;
        $DataEnd = strrpos($Response, "\n");
        if ($DataEnd > $DataStart) {
            $Data = substr($Response, $DataStart, $DataEnd - $DataStart);
        } else {
            $Data = "";
        }
        $Status = substr($Response, $DataEnd + 1);
        if ($Status == "success") {
            $Success = true;
        }
    }
    $Request = array(
        'path' => substr($Get, strpos($Get, '/')),
        'response' => ($Success ? $Data : $Response),
        'status' => ($Success ? 'ok' : 'failed'),
        'time' => 1000 * (microtime(true) - $StartTime)
    );
    // This is broken!
    //self::$Requests[] = $Request;
    if ($Success) {
        return $Data;
    }
    return false;
}

/** This ends_with is slightly slower when the string is found, but a lot faster when it isn't.
 */
function ends_with($Haystack, $Needle)
{
    return substr($Haystack, strlen($Needle) * -1) == $Needle;
}

function starts_with($Haystack, $Needle)
{
    return strpos($Haystack, $Needle) === 0;
}

// amazingly fmod() does not return remanider when var2<var1... this one does
function modulos($var1, $var2)
{
  $tmp = $var1/$var2;

  return (float) ( $var1 - ( ( (int) ($tmp) ) * $var2 ) );
}

/**
 * Variant of in_array() with trailing wildcard support
 * @param string $Needle, array $Haystack
 * @return true if (substring of) $Needle exists in $Haystack
 */
function in_array_partial($Needle, $Haystack)
{
    static $Searches = array();
    if (array_key_exists($Needle, $Searches)) {
        return $Searches[$Needle];
    }
    foreach ($Haystack as $String) {
        if (substr($String, -1) == '*') {
            if (!strncmp($Needle, $String, strlen($String) - 1)) {
                $Searches[$Needle] = true;

                return true;
            }
        } elseif (!strcmp($Needle, $String)) {
            $Searches[$Needle] = true;

            return true;
        }
    }
    $Searches[$Needle] = false;

    return false;
}

/**
 * Will freeleech / neutralleech / normalise a set of torrents
 * @param array $TorrentIDs An array of torrents IDs to iterate over
 * @param int $FreeNeutral 0 = normal, 1 = fl, 2 = nl
 * @param int $FreeLeechType 0 = Unknown, 1 = Staff picks, 2 = Perma-FL (Toolbox, etc.), 3 = Vanity House
 */
function freeleech_torrents($TorrentIDs, $FreeNeutral = 1, $FromShop = false)
{
    global $master, $LoggedUser;
    $DB = $master->olddb;
    $Cache = $master->cache;

    if (!is_array($TorrentIDs)) {
        $TorrentIDs = array($TorrentIDs);
    }

    $DB->query("UPDATE torrents SET FreeTorrent = '" . $FreeNeutral . "' WHERE ID IN (" . implode(", ", $TorrentIDs) . ")");
    $DB->query("SELECT ID, GroupID, info_hash FROM torrents WHERE ID IN (" . implode(", ", $TorrentIDs) . ") ORDER BY GroupID ASC");
    $Torrents = $DB->to_array(false, MYSQLI_NUM, false);
    $GroupIDs = $DB->collect('GroupID');

    foreach ($Torrents as $Torrent) {
        list($TorrentID, $GroupID, $InfoHash) = $Torrent;
        update_tracker('update_torrent', array('info_hash' => rawurlencode($InfoHash), 'freetorrent' => $FreeNeutral));
        $Cache->delete_value('torrent_download_' . $TorrentID);
        if ($FromShop) {
            write_log($LoggedUser['Username'] . " bought universal freeleech for torrent " . $TorrentID);
            write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "bought universal freeleech.", 0);
        } else {
            write_log($LoggedUser['Username'] . " marked torrent " . $TorrentID . " as freeleech.");
            write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "marked as freeleech.", 0);
        }
    }

    foreach ($GroupIDs as $GroupID) {
        update_hash($GroupID);
    }
}

/**
 * Convenience function to allow for passing groups to freeleech_torrents()
 */
function freeleech_groups($GroupIDs, $FreeNeutral = 1, $FromShop = false)
{
    global $master;
    $DB = $master->olddb;

    if (!is_array($GroupIDs)) {
        $GroupIDs = array($GroupIDs);
    }

    $DB->query("SELECT ID from torrents WHERE GroupID IN (" . implode(", ", $GroupIDs) . ")");
    if ($DB->record_count()) {
        $TorrentIDs = $DB->collect('ID');
        freeleech_torrents($TorrentIDs, $FreeNeutral, $FromShop);
    }
}

/* Just a way to get a image url from the symbols folder */

function get_symbol_url($image)
{
    return STATIC_SERVER . 'common/symbols/' . $image;
}
