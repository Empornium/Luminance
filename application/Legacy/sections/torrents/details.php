<?php
use Luminance\Entities\TorrentComment;

include(SERVER_ROOT.'/Legacy/sections/tools/managers/mfd_functions.php');
include(SERVER_ROOT.'/Legacy/sections/requests/functions.php');

if (!isset($groupID)) {
    if (is_integer_string($_GET['id'])) {
        $groupID=ceil($_GET['id']);
    } else {
        error(404);
    }
}
include(SERVER_ROOT.'/Legacy/sections/torrents/functions.php');

$group = $master->repos->torrentgroups->load($groupID);
$torrents = $master->repos->torrents->find('GroupID = ?', [$groupID]);

$TorrentCache = get_group_info($groupID, true);

$TorrentList = $TorrentCache[1];
$TorrentTags = $TorrentCache[2];

$ActiveEvents=get_events(array_diff(get_active_events(), get_torrent_events($groupID)));

list(
    , , , , , , , , , , , , , , , , , , , $HasFile,
    , $Ducky,
) = $TorrentList[0];

// update this users last browsed datetime
update_last_browse($activeUser['ID'], $torrents[0]->Time);

list($SeedValue, $SeedValueSeeders, $SeedValueSize) = get_seed_value($torrents[0]->AverageSeeders, $torrents[0]->Size);

$nextRecord = $master->db->rawQuery(
    "SELECT ID
       FROM articles
      WHERE TopicID = 'seedvalue'"
);
if ($nextRecord) {
    $SeedValueText = '<a href="/articles/view/seedvalue">SeedValue</a>';
} else {
    $SeedValueText = 'SeedValue';
}

$tagsort = isset($_GET['tsort'])?$_GET['tsort']:'uses';
if (!in_array($tagsort, ['uses', 'score', 'az', 'added'])) $tagsort = 'uses';

//advance tagsort for link
if ($tagsort=='score') {
    $tagsort2='az';
} else if ($tagsort=='az') {
    $tagsort2='uses';
} else {
    $tagsort2='score';
}

$tokenTorrents = getTokenTorrents($activeUser['ID']);

$Review = get_last_review($groupID);

$CanEdit = false;
if ($group->UserID == $activeUser['ID']) {
    if ($Review['Status'] == 'Okay' && !check_perms('site_edit_override_review')) {
        $CanEdit = false;
    } else {
        $CanEdit = true;
    }
}

if (check_perms('torrent_edit')) {
    $CanEdit = true;
}

$IsBookmarked = has_bookmarked('torrent', $groupID);

$sqltime = sqltime();

$sitewideDoubleseed = $master->options->getSitewideDoubleseed();
$tokenedDoubleSeedTime = $tokenTorrents[$torrents[0]->ID]['DoubleSeed'] ?? '0000-00-00 00:00:00';
$personalDoubleSeedTime = $activeUser['personal_doubleseed'] ?? '0000-00-00 00:00:00';
if ($torrents[0]->DoubleTorrent == '1') {
    $SeedTooltip = "Unlimited Doubleseed"; // a theoretical state?
} elseif ($tokenedDoubleSeedTime > $sqltime) {
    $SeedTooltip = "Personal Doubleseed Slot for ".time_diff($tokenedDoubleSeedTime, 2, false,false,0);
} elseif ($personalDoubleSeedTime > $sqltime) {
    $SeedTooltip = "Personal Doubleseed for ".time_diff($personalDoubleSeedTime, 2, false,false,0);
} elseif ($sitewideDoubleseed) {
    $SeedTooltip = "Sitewide Doubleseed for ".time_diff($sitewideDoubleseed, 2,false,false,0);
}

$sitewideFreeleech = $master->options->getSitewideFreeleech();
$tokenedFreeleechTime = $tokenTorrents[$torrents[0]->ID]['DoubleSeed'] ?? '0000-00-00 00:00:00';
$personalFreeleechTime = $activeUser['personal_doubleseed'] ?? '0000-00-00 00:00:00';
if ($torrents[0]->FreeTorrent == '1') {
    $FreeTooltip = "Unlimited Freeleech";
} elseif ($tokenedFreeleechTime > $sqltime) {
    $FreeTooltip = "Personal Freeleech Slot for ".time_diff($tokenedFreeleechTime, 2, false,false,0);
} elseif ($personalFreeleechTime > $sqltime) {
    $FreeTooltip = "Personal Freeleech for ".time_diff($personalFreeleechTime, 2, false,false,0);
} elseif ($sitewideFreeleech) {
    $FreeTooltip = "Sitewide Freeleech for ".time_diff($sitewideFreeleech, 2,false,false,0);
}

$Icons = '';
if (isset($SeedTooltip))
    $Icons .= '<img src="/static/common/symbols/doubleseed.gif" alt="DoubleSeed" title="'.$SeedTooltip.'" />&nbsp;&nbsp;';
if (isset($FreeTooltip))
    $Icons .= '<img src="/static/common/symbols/freedownload.gif" alt="Freeleech" title="'.$FreeTooltip.'" />&nbsp;';
if ($IsBookmarked)
    $Icons .= '<img src="/static/styles/'.$activeUser['StyleName'].'/images/star16.png" alt="bookmarked" title="You have this torrent bookmarked" />&nbsp;';
if ($Ducky)
    $Icons .= '<span class="icon icon_ducky" title="This torrent was awarded a Golden Ducky award!"></span>';

if (!empty($Icons)) {
    $Icons .= '&nbsp;';
}
// For now we feed this function some 'false' information to prevent certain icons from occuring that are already present elsewhere on the page
$ExtraIcons = torrent_icons(['GroupID' => $groupID, 'FreeTorrent'=>$torrents[0]->FreeTorrent, 'DoubleTorrent'=>$torrents[0]->DoubleTorrent, 'Ducky'=>$Ducky], $torrents[0]->ID, $Review, $IsBookmarked);


$alertClass = ' hidden';
$resultMessage = '';
if (isset($_GET['did']) && is_integer_string($_GET['did'])) {
    if ($_GET['did'] == 1) {
        $resultMessage ='Successfully edited description';
        $alertClass = '';
    } elseif ($_GET['did'] == 2) {
        $resultMessage ='Successfully renamed title';
        $alertClass = '';
    } elseif ($_GET['did'] == 3) {
        $resultMessage = "Added {$_GET['addedtag']}";
        if (isset($_GET['synonym'])) {
            $resultMessage .= " as a synonym of {$_GET['synonym']}";
        }
        $alertClass = '';
    } elseif ($_GET['did'] == 4) {
        $resultMessage = "{$_GET['addedtag']} is already added.";
        $alertClass = ' alert';
    } elseif ($_GET['did'] == 5) {
        $resultMessage = "{$_GET['synonym']} is a synonym for {$_GET['addedtag']} which is already added.'";
        $alertClass = ' alert';
    }
}

if ($master->auth->isAllowed('torrent_review')) {
    if (!isset($_GET['checked'])) {
        update_staff_checking("viewing \"".cut_string($group->Name, 32)."\"  #$groupID", true);
    }
}

$isWatchlisted = $master->db->rawQuery(
    'SELECT True
       FROM torrents_watch_list
      WHERE TorrentID = ?',
    [$torrents[0]->ID]
)->fetchColumn();

$canLeech = $activeUser['CanLeech'] == '1';
$hasTokens = $activeUser['FLTokens'] > 0;
$tokenedTorrent = $tokenTorrents[$torrents[0]->ID] ?? null;

$tokenedFreeleech = $tokenedTorrent['FreeLeech'] ?? null;
$tokenedFreeleech = $tokenedFreeleech > $sqltime;
$universalFreeleech  = $torrents[0]->FreeTorrent == '1';
$personalFreeleech = $activeUser['personal_freeleech'] > $sqltime;

$tokenedDoubleseed = $tokenedTorrent['DoubleSeed'] ?? null;
$tokenedDoubleseed = $tokenedDoubleseed > $sqltime;
$universalDoubleseed = $torrents[0]->DoubleTorrent == '1';
$personalDoubleseed = $activeUser['personal_doubleseed'] > $sqltime;

$canFreeleech = $HasFile
                && $hasTokens
                && !$sitewideFreeleech
                && !$universalFreeleech
                && !$personalFreeleech
                && !$tokenedFreeleech
                && $canLeech;

$canDoubleseed = $HasFile
                 && $hasTokens
                 && !$sitewideDoubleseed
                 && !$universalDoubleseed
                 && !$personalDoubleseed
                 && !$tokenedDoubleseed
                 && $canLeech;

$Reviews = [];
if (check_perms('torrent_review')) {
    // get review history
    if ($Review['ID'] && is_integer_string($Review['ID'])) { // if reviewID == null then no history
        $Reviews = $master->db->rawQuery(
            "SELECT r.Status,
                    r.Time,
                    r.ConvID,
                    IF(r.ReasonID = 0, r.Reason, rs.Description) AS StatusDescription,
                    r.UserID,
                    u.Username AS Staffname
               FROM torrents_reviews AS r
          LEFT JOIN users AS u ON u.ID = r.UserID
          LEFT JOIN review_reasons AS rs ON rs.ID = r.ReasonID
              WHERE r.GroupID = ?
                AND r.ID != ?
                ORDER BY Time",
            [$groupID, $Review['ID']]
        )->fetchAll(\PDO::FETCH_ASSOC);
        $NumReviews = count($Reviews);
    } else {
        $NumReviews = 0;
    }
    $reviewReasons = $master->db->rawQuery(
        "SELECT ID,
                Name
           FROM review_reasons
       ORDER BY Sort"
    )->fetchAll(\PDO::FETCH_OBJ);
} else {
    $NumReviews = 0;
    $Reviews = [];
    $reviewReasons = [];
}

$uflItem = null;

if ($torrents[0]->FreeTorrent == '0' && ($group->UserID == $activeUser['ID'])) {
    $itemsUFL = $master->repos->bonusshopitems->getItemsUFL();

    foreach ($itemsUFL as $itemUFL) {
        // skip over the items for smaller
        if ($torrents[0]->Size < get_bytes($itemUFL->Value.'gb')) {
            continue;
        }

        $uflItem = $itemUFL;

        break;
    }
}

// similar to torrent_info()
$ExtraInfo = '';
$AddExtra = ' / ';

if (!empty($TokenTorrents[$torrents[0]->ID]) && $TokenTorrents[$torrents[0]->ID]['Type'] == 'leech') {
    $ExtraInfo.=$AddExtra.'<strong>Personal Freeleech!</strong>';
}

if (!empty($TokenTorrents[$torrents[0]->ID]) && $TokenTorrents[$torrents[0]->ID]['Type'] == 'seed') {
    $ExtraInfo.=$AddExtra.'<strong>Personal Doubleseed!</strong>';
}

if (!empty($torrents[0]->openReports)) {
    $ExtraInfo.=$AddExtra.'<strong>Reported</strong>';
}


// count filetypes
$num = preg_match_all('/\.([^\.]*)\{\{\{/ism', $torrents[0]->FileList, $Extensions);

$TempFileTypes = [];
foreach (array_keys($knownFileTypes) as $type) {
    $TempFileTypes[$type] = 0;
}
$TempFileTypes['unknown'] = 0;

foreach ($Extensions[1] as $ext) { // filetypes arrays defined in config
    $ext = strtolower($ext);
    foreach ($knownFileTypes as $type => $extensions) {
        if (in_array($ext, $extensions)) {
            $TempFileTypes[$type]+=1;
            continue 2;
        }
    }

    # Nothing found
    $TempFileTypes['unknown']+=1;
}
$FileTypes=[];
foreach ($TempFileTypes as $type => $count) {
    if ($count>0) {
        $FileTypes[] = "<span title='{$count} {$type} files'>{$count} ".$master->render->icon('file_icons', "files_{$type}")."</span>";
    }
}

function filelist($Str) {
    return "</td><td>".get_size($Str[1])."</td></tr>";
}
$FileTypes = "<span class=\"grey\" style=\"float:left;\">" . implode(' ', $FileTypes)."</span>";
$FileList = str_replace('|||', '<tr><td>',display_str($torrents[0]->FileList));
$FileList = preg_replace_callback('/\{\{\{([^\{]*)\}\}\}/i', 'filelist', $FileList);

$FilledRequests = get_group_requests_filled($groupID);
foreach ($FilledRequests as &$Request) {
    $Request['Votes'] = get_votes_array($Request['ID']);
}

$Requests = get_group_requests($groupID);
foreach ($Requests as &$Request) {
    $Request['Votes'] = get_votes_array($Request['ID']);
}

$collageTorrents = $master->repos->collageTorrents->find('GroupID = ?', [$groupID]);
$collages = [];
$personalCollages = [];
foreach ($collageTorrents as $collageTorrent) {
    if ($collageTorrent->collage->isTrashed()) {
        if (!check_perms('collage_moderate')) {
            continue;
        }
    }
    if ($collageTorrent->collage->isPersonal()) {
        $personalCollages[] = $collageTorrent->collage;;
    } else {
        $collages[] = $collageTorrent->collage;
    }
}

$results = get_num_comments($groupID);

$page = 0;
$postsPerPage = $master->request->user->options('PostsPerPage', $master->settings->pagination->torrent_comments);
if (isset($_GET['postid']) && is_integer_string($_GET['postid']) && $results > $postsPerPage) {
    $postNum = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM torrents_comments
          WHERE GroupID = ?
            AND ID <= ?",
        [$groupID, $_GET['postid']]
    )->fetchColumn();
    list($page, $limit) = page_limit($postsPerPage, $postNum);
} else {
    list($page, $limit) = page_limit($postsPerPage, $results);
}

# Is the torrent
if ($this->auth->isAllowed('torrent_post_trash')) {
    $checkFlags = TorrentComment::PINNED;
} else {
    $checkFlags = TorrentComment::PINNED | TorrentComment::TRASHED;
}

# We could implement catalog caching for the comment IDs, but it's not worth it
$comments = $master->repos->torrentcomments->find(
    'GroupID = ? AND Flags & ? = 0',
    [$groupID, $checkFlags],
    'AddedTime',
    $limit
);

$pinnedComments = $master->repos->torrentcomments->find(
    'GroupID = ? AND Flags & ? = ?',
    [$groupID, $checkFlags, TorrentComment::PINNED],
    'AddedTime'
);

$comments = array_merge($pinnedComments, $comments);
$templateVariables = [
    'group'             => $group,
    'editionID'         => 0,
    'torrents'          => $torrents,

    'fileTypes'         => $FileTypes,
    'fileList'          => $FileList,

    'icons'             => $Icons,
    'extraIcons'        => $ExtraIcons,
    'extraInfo'         => $ExtraInfo,

    'canEdit'           => $CanEdit,
    'canFreeleech'      => $canFreeleech,
    'canDoubleseed'     => $canDoubleseed,
    'isBookmarked'      => $IsBookmarked,
    'isWatchlisted'     => $isWatchlisted,

    'review'            => $Review,
    'reviews'           => $Reviews,
    'numReviews'        => $NumReviews,
    'reviewReasons'     => $reviewReasons,

    'resultMessage'     => $resultMessage,
    'alertClass'        => $alertClass,

    'activeEvents'      => $ActiveEvents,
    'newCategories'     => $newCategories,

    'uflItem'           => $uflItem,
    'torrentTags'       => $TorrentTags,

    'tagSort'           => $tagsort,

    'seedValueText'     => $SeedValueText,
    'seedValue'         => $SeedValue,
    'seedValueSeeders'  => $SeedValueSeeders,
    'seedValueSize'     => $SeedValueSize,

    'requests'          => $Requests,
    'filledRequests'    => $FilledRequests,
    'collages'          => $collages,
    'personalCollages'  => $personalCollages,

    'page'              => $page,
    'pageSize'          => $postsPerPage,
    'results'           => $results,
    'comments'          => $comments,
];

// Start output
if (check_perms('torrent_review')) {
    show_header($group->Name, 'jquery,jquery.cookie,hidebar,comments,status,torrent,bbcode,watchlist,details');
} else {
    show_header($group->Name, 'jquery,jquery.cookie,hidebar,comments,torrent,bbcode,watchlist,details');
}

echo $master->render->template('@Legacy/torrents/details/index.html.twig', $templateVariables);

show_footer();
