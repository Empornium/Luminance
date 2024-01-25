<?php
$bbCode = new \Luminance\Legacy\Text;

include_once(SERVER_ROOT.'/Legacy/sections/requests/functions.php');
include_once(SERVER_ROOT.'/Legacy/sections/bonus/functions.php');
include_once(SERVER_ROOT.'/Legacy/sections/user/linkedfunctions.php');

if (empty($_REQUEST['id']) || !is_integer_string($_REQUEST['id'])) { error(0); }
$userID = $_REQUEST['id'];

$OwnProfile = $userID == $activeUser['ID'];
$Preview = ($OwnProfile || check_perms('users_mod')) ? ($_GET['preview'] ?? '0') == '1' : '0';

global $user;
$master->repos->users->disableCache();
$user = $master->repos->users->load($userID);
$master->repos->users->enableCache();

if (!$user) { // If user doesn't exist
    header("Location: log.php?search=User+".$userID);
    $uri = $_SERVER['REQUEST_URI'];
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $query = $_SERVER['QUERY_STRING'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $message = ("User ".$activeUser['Username']." https://".SSL_SITE_URL."/user.php?id=".$activeUser['ID']." just tried to view a nonexistent user! URL: ".$url."\n");
    $message .= ("Offending IP: ".$ip." Check the logs!");
    $master->irker->announceDebug($message);
    if ($master->settings->site->debug_mode) {
        $master->flasher->error($message);
    }
    exit;
}

// User is loaded or isn't, don't reference $userID again!
unset($userID);

$user->extra = $master->db->rawQuery(
    "SELECT SHA1(i.AdminComment) AS `CommentHash`,
            ta.Ducky AS `Ducky`,
            ta.TorrentID AS `DuckyTID`,
            t.GroupID AS `DuckyGroupID`
       FROM users_info AS i
  LEFT JOIN torrents_awards AS ta ON ta.UserID = i.UserID
  LEFT JOIN torrents AS t ON t.ID = ta.TorrentID
      WHERE i.UserID = ?",
    [$user->ID]
)->fetch(\PDO::FETCH_ASSOC);

$user->availableBadges = $master->db->rawQuery(
    "SELECT b.ID AS `badgeID`,
            b.Rank AS `rank`,
            b.Type AS `type`,
            b.Title AS `title`,
            b.Description AS `description`,
            b.Image AS `image`,
            IF(b.Type != 'Unique', TRUE,
            (
                SELECT COUNT(*)
                  FROM users_badges
                 WHERE users_badges.BadgeID = b.ID) = 0
            ) AS `available`,
            (
                SELECT Max(b2.Rank)
                  FROM users_badges AS ub2
             LEFT JOIN badges AS b2 ON b2.ID = ub2.BadgeID
                 WHERE b2.Badge = b.Badge
                   AND ub2.UserID = ?
            ) As `maxRank`
       FROM badges AS b
  LEFT JOIN badges_auto AS ba ON b.ID = ba.BadgeID
      WHERE b.Type != 'Shop'
        AND ba.ID IS NULL
   ORDER BY b.Sort",
    [$user->ID]
)->fetchAll(\PDO::FETCH_ASSOC);

$user->watchlisted = $master->db->rawQuery(
    "SELECT UserID
       FROM users_watch_list
      WHERE UserID = ?",
    [$user->ID]
)->fetchColumn();

$user->whitelisted = $master->db->rawQuery(
    "SELECT UserID
       FROM users_not_cheats
      WHERE UserID = ?",
    [$user->ID]
)->fetchColumn();

$user->requests = $master->db->rawQuery(
    "SELECT COUNT(DISTINCT r.ID) AS `count`,
            SUM(rv.Bounty) AS `bounty`
       FROM requests AS r
  LEFT JOIN requests_votes AS rv ON r.ID = rv.RequestID
      WHERE r.FillerID = ?
   ORDER BY r.TimeAdded DESC",
    [$user->ID]
)->fetch(\PDO::FETCH_ASSOC);

$user->requestsVotes = $master->db->rawQuery(
    "SELECT COUNT(rv.RequestID) AS `count`,
            SUM(rv.Bounty) AS `bounty`
       FROM requests_votes AS rv
      WHERE rv.UserID = ?",
    [$user->ID]
)->fetch(\PDO::FETCH_ASSOC);

$user->uploads = $master->db->rawQuery(
    "SELECT COUNT(ID) AS `count`,
            SUM(Size) as `totalSize`
       FROM torrents
      WHERE UserID = ?",
    [$user->ID]
)->fetch(\PDO::FETCH_ASSOC);

$user->IPChanges = $master->db->rawQuery(
    "SELECT COUNT(DISTINCT IPID)
       FROM users_history_ips
      WHERE UserID = ?",
    [$user->ID]
)->fetchColumn();

$user->trackerIPs = $master->db->rawQuery(
    "SELECT COUNT(DISTINCT ipv4)
       FROM xbt_snatched
      WHERE uid = ?
        AND ipv4 != ''",
    [$user->ID]
)->fetchColumn();

$user->emailChanges = $master->db->rawQuery(
    "SELECT COUNT(*)
       FROM emails
      WHERE UserID = ?",
    [$user->ID]
)->fetchColumn();

$user->invitesPending = $master->db->rawQuery(
    "SELECT count(InviterID)
       FROM invites
      WHERE InviterID = ?",
    [$user->ID]
)->fetchColumn();

$user->snatched = $master->db->rawQuery(
    "SELECT COUNT(x.uid) AS `total`,
     COUNT(DISTINCT x.fid) AS `unique`
       FROM xbt_snatched AS x
 INNER JOIN torrents AS t ON t.ID = x.fid
      WHERE x.uid = ?",
    [$user->ID]
)->fetch(\PDO::FETCH_ASSOC);

$user->comments = $master->db->rawQuery(
    "SELECT COUNT(ID)
       FROM torrents_comments
      WHERE AuthorID = ?",
    [$user->ID]
)->fetchColumn();

$user->forumPosts = $master->db->rawQuery(
    "SELECT COUNT(ID)
       FROM forums_posts
      WHERE AuthorID = ?",
    [$user->ID]
)->fetchColumn();

$user->reactivationRequests = $master->repos->publicrequests->getRequestCountByUser($user->ID);

$user->grabbed = $master->db->rawQuery(
    "SELECT COUNT(ud.TorrentID) AS `total`,
            COUNT(DISTINCT ud.TorrentID) AS `unique`
       FROM users_downloads AS ud
 INNER JOIN torrents AS t ON t.ID = ud.TorrentID
      WHERE ud.UserID = ?",
    [$user->ID]
)->fetch(\PDO::FETCH_ASSOC);

$user->unusedDonationAddresses = $master->db->rawQuery(
    "SELECT COUNT(ID)
       FROM bitcoin_donations
      WHERE state='unused'
        AND userID = ?",
    [$user->ID]
)->fetchColumn();

$user->donations = $master->db->rawQuery(
    "SELECT COUNT(ID) AS `count`,
            SUM(amount_euro) AS `total`
       FROM bitcoin_donations
      WHERE state != 'unused'
        AND userID = ?",
    [$user->ID]
)->fetch(\PDO::FETCH_ASSOC);

$user->invitedUsers = $master->db->rawQuery(
    "SELECT COUNT(UserID)
       FROM users_info
      WHERE Inviter = ?",
    [$user->ID]
)->fetchColumn();

if (($user->tags = $master->cache->getValue('user_tag_count_'.$user->ID)) === false) {
    $user->tags = new \stdClass;
    $user->tags->ownTags = $master->db->rawQuery(
        "SELECT COUNT(tt.TagID) FROM torrents_tags AS tt
           JOIN torrents AS t ON t.GroupID=tt.GroupID
          WHERE tt.UserID = ?
            AND t.UserID = ?",
        [$user->ID, $user->ID]
    )->fetchColumn();

    $user->tags->totalTags = $master->db->rawQuery(
        "SELECT COUNT(tt.TagID)
           FROM torrents_tags AS tt
          WHERE tt.UserID = ?",
        [$user->ID]
    )->fetchColumn();

    $user->tags->ownTagVotes = $master->db->rawQuery(
        "SELECT COUNT(ttv.TagID)
           FROM torrents_tags_votes AS ttv
           JOIN torrents AS t ON t.GroupID=ttv.GroupID
          WHERE ttv.UserID = ?
            AND t.UserID = ?",
        [$user->ID, $user->ID]
    )->fetchColumn();

    $user->tags->totalTagVotes = $master->db->rawQuery(
        "SELECT COUNT(ttv.TagID)
           FROM torrents_tags_votes AS ttv
          WHERE ttv.UserID = ?",
        [$user->ID]
    )->fetchColumn();

    # != is expensive on large DB tables
    $user->tags->otherTags = $user->tags->totalTags - $user->tags->ownTags;
    $user->tags->otherTagVotes = $user->tags->totalTagVotes - $user->tags->ownTagVotes;

    $master->cache->cacheValue('user_tag_count_'.$user->ID , $user->tags, 3600);
}

if (($user->slotResults = $master->cache->getValue('_sm_sum_history_'.$user->ID)) === false) {
    $user->slotResults = $master->db->rawQuery(
        "SELECT Spins AS `total`,
                Won AS `won`,
                Bet AS `bet`,
                (Won/Bet) AS `return`
           FROM sm_results WHERE UserID = ?",
        [$user->ID]
    )->fetch(\PDO::FETCH_ASSOC);
    $master->cache->cacheValue('_sm_sum_history_'.$user->ID, $user->slotResults, 86400);
}

if (($user->languages = $master->cache->getValue('user_langs_' .$user->ID)) === false) {
    $user->languages = $master->db->rawQuery(
        "SELECT ul.LangID AS `id`,
                l.code AS `code`,
                l.flag_cc AS `cc`,
                l.language AS `language`
           FROM users_languages AS ul
           JOIN languages AS l ON l.ID = ul.LangID
          WHERE UserID = ?",
       [$user->ID]
    )->fetchAll(\PDO::FETCH_ASSOC);
    $master->cache->cacheValue('user_langs_'.$user->ID, $user->languages);
}

if (($user->recentSnatches = $master->cache->getValue('recent_snatches_'.$user->ID)) === false) {
    $user->recentSnatches = $master->db->rawQuery(
        "SELECT g.ID,
                g.Name,
                g.Image
           FROM xbt_snatched AS s
     INNER JOIN torrents AS t ON t.ID = s.fid
     INNER JOIN torrents_group AS g ON t.GroupID = g.ID
          WHERE s.uid = ?
            AND g.Image <> ''
       GROUP BY g.ID
       ORDER BY s.tstamp DESC
          LIMIT 5",
        [$user->ID]
    )->fetchAll(\PDO::FETCH_ASSOC);
    $master->cache->cacheValue('recent_snatches_'.$user->ID, $user->recentSnatches, 0); //inf cache
}

if (($user->recentUploads = $master->cache->getValue('recent_uploads_'.$user->ID)) === false) {
    $user->recentUploads = $master->db->rawQuery(
        "SELECT g.ID,
                g.Name,
                g.Image
           FROM torrents_group AS g
     INNER JOIN torrents AS t ON t.GroupID = g.ID
          WHERE t.UserID = ?
            AND t.Anonymous = '0'
       GROUP BY g.ID
       ORDER BY t.Time DESC
          LIMIT 5",
        [$user->ID]
    )->fetchAll(\PDO::FETCH_ASSOC);
    $master->cache->cacheValue('recent_uploads_'.$user->ID, $user->recentUploads, 0); //inf cache
}

if (($user->recentRequests = $master->cache->getValue('recent_requests_'.$user->ID)) === false) {
    $user->recentRequests = $master->db->rawQuery(
        "SELECT r.ID AS `id`,
                r.Title AS `title`,
                r.TimeAdded AS `timeAdded`,
                COUNT(rv.UserID) AS `votes`,
                SUM(rv.Bounty) AS `bounty`
           FROM requests AS r
      LEFT JOIN users_main AS u ON u.ID = UserID
      LEFT JOIN requests_votes AS rv ON rv.RequestID = r.ID
          WHERE r.UserID = ?
            AND r.TorrentID = 0
       GROUP BY r.ID
       ORDER BY Votes DESC",
        [$user->ID]
    )->fetchAll(\PDO::FETCH_ASSOC);
    $master->cache->cacheValue('recent_requests_'.$user->ID, $user->recentRequests, 0); //inf cache
}

$user->staffPMs = $master->db->rawQuery(
    "SELECT spc.ID,
            spc.Subject,
            spc.Status,
            spc.Level,
            p.ID AS `PermID`,
            spc.AssignedToUser,
            spc.Date,
            spc.ResolverID
       FROM staff_pm_conversations AS spc
  LEFT JOIN permissions AS p ON spc.Level = p.Level
      WHERE spc.UserID = ? AND (spc.Level <= ? OR spc.AssignedToUser = ?)
   GROUP BY spc.ID
   ORDER BY Date DESC",
   [$user->ID, $activeUser['Class'], $activeUser['ID']]
)->fetchAll(\PDO::FETCH_ASSOC);

$user->reports = $master->db->rawQuery(
    "SELECT r.ID,
            r.ReporterID,
            r.TorrentID,
            tg.Name,
            r.Type,
            r.UserComment,
            r.Status,
            r.ReportedTime,
            r.LastChangeTime,
            r.ModComment,
            r.ResolverID
       FROM reportsv2 as r
  LEFT JOIN torrents AS t ON t.ID = r.TorrentID
  LEFT JOIN torrents_group as tg ON tg.ID = t.GroupID
      WHERE ReporterID = ?
   ORDER BY ReportedTime DESC",
   [$user->ID]
)->fetchAll(\PDO::FETCH_ASSOC);

$user->peers = user_peers($user->ID);

$user->uploadSnatched = $master->db->rawQuery(
    "SELECT SUM(Snatched)
       FROM torrents
      WHERE UserID = ?",
    [$user->ID]
)->fetchColumn();

$user->seedingSize = get_size(get_seeding_size($user->ID));

// Paranoia stuff
if (!check_paranoia_here($Preview, 'requestsfilled_count') && !check_paranoia_here($Preview, 'requestsfilled_bounty')) {
    $user->requests['count'] = 0;
    $user->requests['bounty'] = 0;
}

if (!check_paranoia_here($Preview, 'requestsvoted_count') && !check_paranoia_here($Preview, 'requestsvoted_bounty')) {
    $user->requestsVotes['count'] = 0;
    $user->requestsVotes['bounty'] = 0;
}

if (!check_paranoia_here($Preview, 'uploads+')) {
    $user->uploads['count'] = 0;
    $user->uploads['totalSize'] = 0;
}

$DisplayCustomTitle = $user->legacy['Title'];

$user->paranoiaLevel = 0;
foreach ($user->legacy['Paranoia'] as $P) {
    $user->paranoiaLevel++;
    if (strpos($P, '+')) {
        $user->paranoiaLevel++;
    }
}

function check_paranoia_here($Preview, $Setting)
{
    global $user;
    if ($Preview)
        return check_paranoia($Setting, $user->legacy['Paranoia'], 99999999, false);
    else
        return check_paranoia($Setting, $user->legacy['Paranoia'], $user->class->Level, $user->ID);
}

$Badges=($user->legacy['Donor']) ? '<a href="/donate.php"><img src="'.STATIC_SERVER.'common/symbols/donor.png" alt="Donor" /></a>' : '';

if ($master->repos->restrictions->isWarned($user)) {
    if (check_perms('users_mod')) $Badges.= ' Warned for '.time_diff($master->repos->restrictions->getExpiry($user, 1)).' ';
    $Badges.= '<img src="'.STATIC_SERVER.'common/symbols/warned.png" alt="Warned" />';
}
$Badges.=($user->legacy['Enabled'] == '1' || $user->legacy['Enabled'] == '0' || !$user->legacy['Enabled']) ? '': '<img src="'.STATIC_SERVER.'common/symbols/disabled.png" alt="Banned" />';

$user->badges =$Badges;
$user->userBadges = get_user_badges($user->ID, false);

$Rank = new Luminance\Legacy\UserRank;

$user->rank = new \stdClass;
$user->rank->uploaded = $Rank->get_rank('uploaded', $user->legacy['Uploaded']);
$user->rank->downloaded = $Rank->get_rank('downloaded', $user->legacy['Downloaded']);
$user->rank->uploads = $Rank->get_rank('uploads', $user->uploads['count']);
$user->rank->requests = $Rank->get_rank('requests', $user->requests['count']);
$user->rank->posts = $Rank->get_rank('posts', $user->forumPosts);
$user->rank->bounty = $Rank->get_rank('bounty', $user->requestsVotes['bounty']);

if ($user->legacy['Downloaded'] == 0) {
    $Ratio = 1;
} elseif ($user->legacy['Uploaded'] == 0) {
    $Ratio = 0.5;
} else {
    $Ratio = round($user->legacy['Uploaded']/$user->legacy['Downloaded'], 2);
}
$user->rank->overall = $Rank->overall_score($user->rank, $Ratio);
$user->shopItems = get_shop_items_other();
$user->blockedGift = blockedGift($user->ID, $activeUser['ID']);

if (check_perms('users_mod')) {
    #TODO Freaking ugly! need to fix this.
    ob_start();
    user_dupes_table($user->ID, $user->Username);
    $user->ipDupes = ob_get_clean();
}

if (check_perms('users_view_ips')) {
    $logs = $this->security->getLogs($user->ID);
}

$user->inviteTree = new Luminance\Legacy\InviteTree();

if (check_perms('users_mod') || check_perms('users_view_notes')) {
    $FormattedAdminComments = $bbCode->full_format($user->legacy['AdminComment']);
    list($user->parsedAdminComments, $user->notesFilters) = parse_staff_notes($FormattedAdminComments);
}

$CookieItems   = [];
$CookieItems[] = 'profile';
$CookieItems[] = 'loginwatch';
$CookieItems[] = 'bonus';
$CookieItems[] = 'donate';
$CookieItems[] = 'recentsnatches';
$CookieItems[] = 'recentuploads';
$CookieItems[] = 'linked';
$CookieItems[] = 'iplinked';
$CookieItems[] = 'elinked';
$CookieItems[] = 'invite';
$CookieItems[] = 'requests';
$CookieItems[] = 'staffpms';
$CookieItems[] = 'reports';
$CookieItems[] = 'notes';
$CookieItems[] = 'history';
$CookieItems[] = 'info';
$CookieItems[] = 'privilege';
$CookieItems[] = 'submit';
$CookieItems[] = 'badgesadmin';
$CookieItems[] = 'warn';
$CookieItems[] = 'session';

$user->cookieItems = $CookieItems;

show_header($user->Username, 'overlib,jquery,jquery.cookie,jquery.modal,user,bbcode,requests,watchlist,bonus,tracker_history');
echo $master->render->template(
    '@Legacy/user/user.html.twig',
    [
        'user'       => $user,
        'classes'    => $classes,
        'preview'    => $Preview,
        'ownProfile' => $OwnProfile,
        'logs'       => ($logs ?? [])
    ]
);

if ($activeUser['HideUserTorrents']==0 && check_paranoia_here($Preview, 'uploads') && check_force_anon($user->ID)) {
    $INLINE=true;
    $_GET['userid'] = $user->ID;
    $_GET['type'] = 'uploaded';
    include_once(SERVER_ROOT.'/Legacy/sections/torrents/user.php');
}
?>
    </div>
    <div class="clear"></div>
</div>
<?php
show_footer();
