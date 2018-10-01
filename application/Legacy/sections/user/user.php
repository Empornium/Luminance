<?php
$Text = new Luminance\Legacy\Text;

use Luminance\Entities\Email;

include_once(SERVER_ROOT.'/Legacy/sections/requests/functions.php');
include_once(SERVER_ROOT.'/Legacy/sections/bonus/functions.php');
include_once(SERVER_ROOT.'/Legacy/sections/inbox/functions.php');
include_once(SERVER_ROOT.'/Legacy/sections/staff/functions.php');
include_once(SERVER_ROOT.'/Legacy/sections/user/linkedfunctions.php');

if (empty($_REQUEST['id']) || !is_numeric($_REQUEST['id'])) { error(0); }
$UserID = $_REQUEST['id'];

$OwnProfile = $UserID == $LoggedUser['ID'];
$Preview = ($OwnProfile || check_perms('users_mod')) ? $_GET['preview']=='1' :'0';

global $user;
$master->repos->users->disable_cache();
$user = $master->repos->users->load($UserID);
$master->repos->users->enable_cache();

if (!$user) { // If user doesn't exist
    header("Location: log.php?search=User+".$UserID);
    exit;
}

// User is loaded or isn't, don't reference $UserID again!
unset($UserID);

// Fill out the rest of the user
$user->email = $master->repos->emails->load($user->EmailID);
$user->emails = $master->repos->emails->find('UserID = ?', [$user->ID]);
$user->perm = $master->repos->permissions->load($user->legacy['PermissionID']);
$user->inviter = $master->repos->users->load($user->legacy['Inviter']);
$user->ip = $master->repos->ips->load($user->IPID);
$user->floods = $master->repos->floods->find('`UserID` = ?', [$user->ID]);
$user->restrictions = $master->repos->restrictions->find('`UserID` = ?', [$user->ID]);

foreach($user->floods AS $flood) {
    $flood->ip = $master->repos->ips->load($flood->IPID);
}

$user->extra = $master->db->raw_query(
    "SELECT SHA1(i.AdminComment) AS `CommentHash`,
            ta.Ducky AS `Ducky`,
            ta.TorrentID AS `DuckyTID`
       FROM users_info AS i
  LEFT JOIN torrents_awards AS ta ON ta.UserID = i.UserID
      WHERE i.UserID = ?",
    [$user->ID])->fetch(\PDO::FETCH_ASSOC);

$user->availableBadges = $master->db->raw_query(
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
                 WHERE users_badges.BadgeID=b.ID)=0
            ) AS `available`,
            (
                SELECT Max(b2.Rank)
                  FROM users_badges AS ub2
             LEFT JOIN badges AS b2 ON b2.ID=ub2.BadgeID
                 WHERE b2.Badge = b.Badge
                   AND ub2.UserID = ?
            ) As `maxRank`
       FROM badges AS b
  LEFT JOIN badges_auto AS ba ON b.ID=ba.BadgeID
      WHERE b.Type != 'Shop'
        AND ba.ID IS NULL
   ORDER BY b.Sort",
    [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);

$user->connectable = $master->db->raw_query(
    "SELECT ucs.Status AS `status`,
            ucs.IP AS `ip`,
            xbt.port AS `port`,
            Max(ucs.Time) AS `timeChecked`
       FROM users_connectable_status AS ucs
  LEFT JOIN xbt_files_users AS xbt ON xbt.uid=ucs.UserID AND INET6_NTOA(xbt.ipv4)=ucs.IP AND xbt.Active='1'
      WHERE UserID = ?
   GROUP BY ucs.IP
   ORDER BY Max(ucs.Time) DESC LIMIT 100",
    [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);

$user->friendStatus = $master->db->raw_query(
    "SELECT Type
       FROM friends
      WHERE UserID = ?
        AND FriendID = ?",
    [$LoggedUser['ID'], $user->ID])->fetchColumn();

$user->watchlisted = $master->db->raw_query(
    "SELECT UserID
       FROM users_watch_list
      WHERE UserID = ?",
    [$user->ID])->fetchColumn();

$user->whitelisted = $master->db->raw_query(
    "SELECT UserID
       FROM users_not_cheats
      WHERE UserID = ?",
    [$user->ID])->fetchColumn();

$user->requests = $master->db->raw_query(
    "SELECT COUNT(DISTINCT r.ID) AS `count`,
            SUM(rv.Bounty) AS `bounty`
       FROM requests AS r
  LEFT JOIN requests_votes AS rv ON r.ID=rv.RequestID
      WHERE r.FillerID = ?
   ORDER BY r.TimeAdded DESC",
    [$user->ID])->fetch(\PDO::FETCH_ASSOC);

$user->requestsVotes = $master->db->raw_query(
    "SELECT COUNT(rv.RequestID) AS `count`,
            SUM(rv.Bounty) AS `bounty`
       FROM requests_votes AS rv
      WHERE rv.UserID = ?",
    [$user->ID])->fetch(\PDO::FETCH_ASSOC);

$user->uploads = $master->db->raw_query(
    "SELECT COUNT(ID) AS `count`, SUM(Size) as `totalSize`
       FROM torrents
      WHERE UserID = ?",
    [$user->ID])->fetch(\PDO::FETCH_ASSOC);

$user->passwordChanges = $master->db->raw_query(
    "SELECT COUNT(*)
       FROM users_history_passwords
      WHERE UserID = ?",
    [$user->ID])->fetchColumn();

$user->passkeyChanges = $master->db->raw_query(
    "SELECT COUNT(*)
       FROM users_history_passkeys
      WHERE UserID = ?",
    [$user->ID])->fetchColumn();

$user->IPChanges = $master->db->raw_query(
    "SELECT COUNT(DISTINCT IP)
       FROM users_history_ips
      WHERE UserID = ?",
    [$user->ID])->fetchColumn();

$user->trackerIPs = $master->db->raw_query(
    "SELECT COUNT(DISTINCT ipv4)
       FROM xbt_snatched
      WHERE uid = ?
        AND ipv4 != ''",
    [$user->ID])->fetchColumn();

$user->emailChanges = $master->db->raw_query(
    "SELECT COUNT(*)
       FROM emails
      WHERE UserID = ?",
    [$user->ID])->fetchColumn();

$user->invitesPending = $master->db->raw_query(
    "SELECT count(InviterID)
       FROM invites
      WHERE InviterID = ?",
    [$user->ID])->fetchColumn();

$user->snatched = $master->db->raw_query(
    "SELECT COUNT(x.uid) AS `total`,
     COUNT(DISTINCT x.fid) AS `unique`
       FROM xbt_snatched AS x
 INNER JOIN torrents AS t ON t.ID=x.fid
      WHERE x.uid = ?",
    [$user->ID])->fetch(\PDO::FETCH_ASSOC);

$user->comments = $master->db->raw_query(
    "SELECT COUNT(ID)
       FROM torrents_comments
      WHERE AuthorID = ?",
    [$user->ID])->fetchColumn();

$user->forumPosts = $master->db->raw_query(
    "SELECT COUNT(ID)
       FROM forums_posts
      WHERE AuthorID = ?",
    [$user->ID])->fetchColumn();

$collages = $master->db->raw_query(
    "SELECT ID,
            Name,
            NumTorrents
       FROM collages
      WHERE Deleted='0'
        AND UserID = ?
   ORDER BY Featured DESC, Name ASC",
    [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);

foreach ($collages as $index => $collage) {
    // Get collages torrents
    $collages[$index]['torrents'] = $master->db->raw_query(
        "SELECT ct.GroupID,
                tg.Image,
                tg.NewCategoryID
           FROM collages_torrents AS ct
           JOIN torrents_group AS tg ON tg.ID=ct.GroupID
          WHERE ct.CollageID = ?
       ORDER BY ct.Sort LIMIT 5",
        [$collage['ID']])->fetchAll(\PDO::FETCH_ASSOC);
    // Get last added torrent date
    $collages[$index]['LastUpdate'] = $master->db->raw_query(
        'SELECT MAX(AddedOn)
         FROM collages_torrents
         WHERE CollageID = ?',
        [$collage['ID']])->fetchColumn();
    // Get total torrents size
    $collages[$index]['TotalSize'] = $master->db->raw_query("SELECT SUM(t.Size) AS Size
              FROM collages_torrents AS ct
              JOIN torrents AS t ON ct.GroupID=t.GroupID
             WHERE ct.CollageID= ?", [$collage['ID']])->fetchColumn();
}

$user->collages = $collages;

$user->collageTorrents = $master->db->raw_query(
    "SELECT COUNT(DISTINCT CollageID)
       FROM collages_torrents AS ct
       JOIN collages ON CollageID = ID
      WHERE Deleted='0'
        AND ct.UserID = ?",
    [$user->ID])->fetchColumn();

$user->grabbed = $master->db->raw_query(
    "SELECT COUNT(ud.TorrentID) AS `total`,
            COUNT(DISTINCT ud.TorrentID) AS `unique`
       FROM users_downloads AS ud
 INNER JOIN torrents AS t ON t.ID=ud.TorrentID
      WHERE ud.UserID = ?",
    [$user->ID])->fetch(\PDO::FETCH_ASSOC);

$user->unusedDonationAddresses = $master->db->raw_query(
    "SELECT COUNT(ID)
       FROM bitcoin_donations
      WHERE state='unused'
        AND userID = ?",
    [$user->ID])->fetchColumn();

$user->donations = $master->db->raw_query(
    "SELECT COUNT(ID) AS `count`,
            SUM(amount_euro) AS `total`
       FROM bitcoin_donations
      WHERE state!='unused'
        AND userID = ?",
    [$user->ID])->fetch(\PDO::FETCH_ASSOC);

$user->invitedUsers = $master->db->raw_query(
    "SELECT COUNT(UserID)
       FROM users_info
      WHERE Inviter = ?",
    [$user->ID])->fetchColumn();

$user->torrentClients = $master->db->raw_query(
    "SELECT useragent,
            INET6_NTOA(ipv4) AS `ip`,
            LEFT(peer_id, 8) AS `clientid`
       FROM xbt_files_users WHERE uid = ?
   GROUP BY useragent, ipv4",
    [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);

if (($user->tags = $Cache->get_value('user_tag_count_'.$user->ID)) === false) {
    $user->tags = new \stdClass;
    $user->tags->ownTags = $master->db->raw_query(
        "SELECT COUNT(tt.TagID) FROM torrents_tags AS tt
           JOIN torrents AS t ON t.GroupID=tt.GroupID
          WHERE tt.UserID = ?
            AND t.UserID = ?",
        [$user->ID, $user->ID])->fetchColumn();

    $user->tags->totalTags = $master->db->raw_query(
        "SELECT COUNT(tt.TagID)
           FROM torrents_tags AS tt
          WHERE tt.UserID = ?",
        [$user->ID])->fetchColumn();

    $user->tags->ownTagVotes = $master->db->raw_query(
        "SELECT COUNT(ttv.TagID)
           FROM torrents_tags_votes AS ttv
           JOIN torrents AS t ON t.GroupID=ttv.GroupID
          WHERE ttv.UserID = ?
            AND t.UserID = ?",
        [$user->ID, $user->ID])->fetchColumn();

    $user->tags->totalTagVotes = $master->db->raw_query(
        "SELECT COUNT(ttv.TagID)
           FROM torrents_tags_votes AS ttv
          WHERE ttv.UserID = ?",
        [$user->ID])->fetchColumn();

    # != is expensive on large DB tables
    $user->tags->otherTags = $user->tags->totalTags - $user->tags->ownTags;
    $user->tags->otherTagVotes = $user->tags->totalTagVotes - $user->tags->ownTagVotes;

    $Cache->cache_value('user_tag_count_'.$user->ID , $user->tags, 3600 );
}

if (($user->slotResults = $Cache->get_value('_sm_sum_history_'.$user->ID)) === false) {
    $user->slotResults = $master->db->raw_query(
        "SELECT Spins AS `total`,
                Won AS `won`,
                Bet AS `bet`,
                (Won/Bet) AS `return`
           FROM sm_results WHERE UserID = ?",
        [$user->ID])->fetch(\PDO::FETCH_ASSOC);
    $Cache->cache_value('_sm_sum_history_'.$user->ID, $user->slotResults, 86400);
}

if (($user->languages = $Cache->get_value('user_langs_' .$user->ID)) === false) {
    $user->languages = $master->db->raw_query(
        "SELECT ul.LangID AS `id`,
                l.code AS `code`,
                l.flag_cc AS `cc`,
                l.language AS `language`
           FROM users_languages AS ul
           JOIN languages AS l ON l.ID=ul.LangID
          WHERE UserID = ?",
       [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);
    $Cache->cache_value('user_langs_'.$user->ID, $user->languages);
}

if (($user->recentSnatches = $Cache->get_value('recent_snatches_'.$user->ID)) === false) {
    $user->recentSnatches = $master->db->raw_query(
        "SELECT g.ID,
                g.Name,
                g.Image
           FROM xbt_snatched AS s
     INNER JOIN torrents AS t ON t.ID=s.fid
     INNER JOIN torrents_group AS g ON t.GroupID=g.ID
          WHERE s.uid = ?
            AND g.Image <> ''
       GROUP BY g.ID
       ORDER BY s.tstamp DESC
          LIMIT 5",
        [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);
    $Cache->cache_value('recent_snatches_'.$user->ID, $user->recentSnatches, 0); //inf cache
}

if (($user->recentUploads = $Cache->get_value('recent_uploads_'.$user->ID)) === false) {
    $user->recentUploads = $master->db->raw_query(
        "SELECT g.ID,
                g.Name,
                g.Image
           FROM torrents_group AS g
     INNER JOIN torrents AS t ON t.GroupID=g.ID
          WHERE t.UserID = ?
            AND t.Anonymous = '0'
       GROUP BY g.ID
       ORDER BY t.Time DESC
          LIMIT 5",
        [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);
    $Cache->cache_value('recent_uploads_'.$user->ID, $user->recentUploads, 0); //inf cache
}

if (($user->recentRequests = $Cache->get_value('recent_requests_'.$user->ID)) === false) {
    $user->recentRequests = $master->db->raw_query(
        "SELECT r.ID AS `id`,
                r.Title AS `title`,
                r.TimeAdded AS `timeAdded`,
                COUNT(rv.UserID) AS `votes`,
                SUM(rv.Bounty) AS `bounty`
           FROM requests AS r
      LEFT JOIN users_main AS u ON u.ID=UserID
      LEFT JOIN requests_votes AS rv ON rv.RequestID=r.ID
          WHERE r.UserID = ?
            AND r.TorrentID = 0
       GROUP BY r.ID
       ORDER BY Votes DESC",
        [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);
    $Cache->cache_value('recent_requests_'.$user->ID, $user->recentRequests, 0); //inf cache
}

$user->staffPMs = $master->db->raw_query(
    "SELECT spc.ID,
            spc.Subject,
            spc.Status,
            spc.Level,
            p.ID AS `PermID`,
            spc.AssignedToUser,
            spc.Date,
            spc.ResolverID
       FROM staff_pm_conversations AS spc
  LEFT JOIN permissions AS p ON spc.Level=p.Level
      WHERE spc.UserID = ? AND (spc.Level <= ? OR spc.AssignedToUser=?)
   GROUP BY spc.ID
   ORDER BY Date DESC",
   [$user->ID, $LoggedUser['Class'], $LoggedUser['ID']])->fetchAll(\PDO::FETCH_ASSOC);

$user->reports = $master->db->raw_query(
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
  LEFT JOIN torrents_group as tg ON tg.ID=r.TorrentID
      WHERE ReporterID = ?
   ORDER BY ReportedTime DESC",
   [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);

$user->peers = user_peers($user->ID);


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
    $user->uploads = 0;
}

$DisplayCustomTitle = $user->legacy['Title'];
$user->legacy['Paranoia'] = unserialize($user->legacy['Paranoia']);
if (!is_array($user->legacy['Paranoia'])) {
    $user->legacy['Paranoia'] = array();
}
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
        return check_paranoia($Setting, $user->legacy['Paranoia'], $user->perm->Level, $user->ID);
}

$Badges=($user->legacy['Donor']) ? '<a href="/donate.php"><img src="'.STATIC_SERVER.'common/symbols/donor.png" alt="Donor" /></a>' : '';

if ($master->repos->restrictions->is_warned($user)) {
    if (check_perms('users_mod')) $Badges.= ' Warned for '.time_diff($master->repos->restrictions->get_expiry($user, 1)).' ';
    $Badges.= '<img src="'.STATIC_SERVER.'common/symbols/warned.png" alt="Warned" />';
}
$Badges.=($user->legacy['Enabled'] == '1' || $user->legacy['Enabled'] == '0' || !$user->legacy['Enabled']) ? '': '<img src="'.STATIC_SERVER.'common/symbols/disabled.png" alt="Banned" />';

$user->badges =$Badges;
$user->userBadges = get_user_badges($user->ID, false);

$Rank = new Luminance\Legacy\UserRank;

$user->rank = new \StdClass;
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
$user->blockedPM = blockedPM($user->ID, $LoggedUser['ID']);
$user->blockedGift = blockedGift($user->ID, $LoggedUser['ID']);

if (check_perms('users_mod')) {
    #TODO Freaking ugly! need to fix this.
    ob_start();
    user_dupes_table($user->ID, $user->Username);
    $user->dupes = ob_get_clean();
}

$user->inviteTree = new \Luminance\Legacy\InviteTree($user->ID, ['visible'=>false]);

$FormattedAdminComments = $Text->full_format($user->legacy['AdminComment']);
list($user->parsedAdminComments, $user->notesFilters) = parse_staff_notes($FormattedAdminComments);

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

show_header($user->Username,'overlib,jquery,jquery.cookie,user,bbcode,requests,watchlist,bonus,tracker_history');
echo $master->render->render('@Users/userpage.html.twig', ['user' => $user, 'classes' => $Classes, 'preview' => $Preview, 'ownProfile' => $OwnProfile]);

if ($LoggedUser['HideUserTorrents']==0 && check_paranoia_here($Preview, 'uploads') && check_force_anon($user->ID)) {
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
