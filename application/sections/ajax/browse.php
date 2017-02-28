<?php
authorize(true);

include_once(SERVER_ROOT.'/common/functions.php');
include(SERVER_ROOT.'/sections/bookmarks/functions.php');
include(SERVER_ROOT.'/sections/torrents/functions.php');

$TokenTorrents = $Cache->get_value('users_tokens_'.$UserID);
if (empty($TokenTorrents)) {
    $DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$UserID");
    $TokenTorrents = $DB->to_array('TorrentID');
    $Cache->cache_value('users_tokens_'.$UserID, $TokenTorrents);
}

// Setting default search options
if (!empty($_GET['setdefault'])) {
    $UnsetList = array('page','setdefault');
    $UnsetRegexp = '/(&|^)('.implode('|',$UnsetList).')=.*?(&|$)/i';

    $DB->query("SELECT SiteOptions FROM users_info WHERE UserID='".db_string($LoggedUser['ID'])."'");
    list($SiteOptions)=$DB->next_record(MYSQLI_NUM, false);
    if (!empty($SiteOptions)) {
        $SiteOptions = unserialize($SiteOptions);
    } else {
        $SiteOptions = array();
    }
    $SiteOptions['DefaultSearch'] = preg_replace($UnsetRegexp,'',$_SERVER['QUERY_STRING']);
    $DB->query("UPDATE users_info SET SiteOptions='".db_string(serialize($SiteOptions))."' WHERE UserID='".db_string($LoggedUser['ID'])."'");
    $master->repos->users->uncache($UserID);

// Clearing default search options
} elseif (!empty($_GET['cleardefault'])) {
    $DB->query("SELECT SiteOptions FROM users_info WHERE UserID='".db_string($LoggedUser['ID'])."'");
    list($SiteOptions)=$DB->next_record(MYSQLI_NUM, false);
    $SiteOptions=unserialize($SiteOptions);
    $SiteOptions['DefaultSearch']='';
    $DB->query("UPDATE users_info SET SiteOptions='".db_string(serialize($SiteOptions))."' WHERE UserID='".db_string($LoggedUser['ID'])."'");
    $master->repos->users->uncache($UserID);

// Use default search options
} elseif ((empty($_SERVER['QUERY_STRING']) || (count($_GET) == 1 && isset($_GET['page']))) && !empty($LoggedUser['DefaultSearch'])) {
    if (!empty($_GET['page'])) {
        $Page = $_GET['page'];
        parse_str($LoggedUser['DefaultSearch'],$_GET);
        $_GET['page'] = $Page;
    } else {
        parse_str($LoggedUser['DefaultSearch'],$_GET);
    }
}

$Queries = array();

//Simple search
if (!empty($_GET['searchstr'])) {
    $Words = explode(' ',strtolower($_GET['searchstr']));

    if (!empty($Words)) {
        foreach ($Words as $Key => &$Word) {
            if ($Word[0] == '!' && strlen($Word) >= 3 && count($Words) >= 2) {
                if (strpos($Word,'!',1) === false) {
                    $Word = '!'.$SS->EscapeString(substr($Word,1));
                } else {
                    $Word = $SS->EscapeString($Word);
                }
            } elseif (strlen($Word) >= 2) {
                $Word = $SS->EscapeString($Word);
            } else {
                unset($Words[$Key]);
            }
        }
        unset($Word);
        $Words = trim(implode(' ',$Words));
        if (!empty($Words)) {
            $Queries[]='@(groupname) '.$Words;
        }
    }
}

if (!empty($_GET['taglist'])) {
    $_GET['taglist'] = str_replace('.','_',$_GET['taglist']);
    $TagList = explode(',',$_GET['taglist']);
    $TagListEx = array();
    foreach ($TagList as $Key => &$Tag) {
        $Tag = trim($Tag);
        if (strlen($Tag) >= 2) {
            if ($Tag[0] == '!' && strlen($Tag) >= 3) {
                $TagListEx[] = '!'.$SS->EscapeString(substr($Tag,1));
                unset($TagList[$Key]);
            } else {
                $Tag = $SS->EscapeString($Tag);
            }
        } else {
            unset($TagList[$Key]);
        }
    }
    unset($Tag);
}

if (empty($_GET['tags_type']) && !empty($TagList) && count($TagList) > 1) {
    $_GET['tags_type'] = '0';
    if (!empty($TagListEx)) {
        $Queries[]='@taglist ( '.implode(' | ', $TagList).' ) '.implode(' ', $TagListEx);
    } else {
        $Queries[]='@taglist ( '.implode(' | ', $TagList).' )';
    }
} elseif (!empty($TagList)) {
    $Queries[]='@taglist '.implode(' ', array_merge($TagList,$TagListEx));
} else {
    $_GET['tags_type'] = '1';
}

foreach (array('groupname', 'filelist') as $Search) {
    if (!empty($_GET[$Search])) {
        $_GET[$Search] = str_replace(array('%'), '', $_GET[$Search]);
        if ($Search == 'filelist') {
            $Queries[]='@filelist "'.$SS->EscapeString($_GET['filelist']).'"~20';
        } else {
            $Words = explode(' ', $_GET[$Search]);
            foreach ($Words as $Key => &$Word) {
                if ($Word[0] == '!' && strlen($Word) >= 3 && count($Words) >= 2) {
                    if (strpos($Word,'!',1) === false) {
                        $Word = '!'.$SS->EscapeString(substr($Word,1));
                    } else {
                        $Word = $SS->EscapeString($Word);
                    }
                } elseif (strlen($Word) >= 2) {
                    $Word = $SS->EscapeString($Word);
                } else {
                    unset($Words[$Key]);
                }
            }
            $Words = trim(implode(' ',$Words));
            if (!empty($Words)) {
                $Queries[]="@$Search ".$Words;
            }
        }
    }
}

foreach (array('freetorrent') as $Search) {
    if (isset($_GET[$Search]) && $_GET[$Search]!=='') {
        if ($Search == 'freetorrent') {
            switch ($_GET[$Search]) {
                case 0: $SS->set_filter($Search, array(0)); break;
                case 1: $SS->set_filter($Search, array(1)); break;
                case 2: $SS->set_filter($Search, array(2)); break;
                case 3: $SS->set_filter($Search, array(0), true); break;
            }
        } else {
            $SS->set_filter($Search, array($_GET[$Search]));
        }
    }
}

if (!empty($_GET['filter_cat'])) {
    $SS->set_filter('categoryid', array_keys($_GET['filter_cat']));
}

if (!empty($_GET['page']) && is_number($_GET['page'])) {
    if (check_perms('site_search_many')) {
        $Page = $_GET['page'];
    } else {
        $Page = min(SPHINX_MAX_MATCHES/TORRENTS_PER_PAGE, $_GET['page']);
    }
    $MaxMatches = min(SPHINX_MAX_MATCHES, SPHINX_MATCHES_START + SPHINX_MATCHES_STEP*floor(($Page-1)*TORRENTS_PER_PAGE/SPHINX_MATCHES_STEP));
    $SS->limit(($Page-1)*TORRENTS_PER_PAGE, TORRENTS_PER_PAGE, $MaxMatches);
} else {
    $Page = 1;
    $MaxMatches = SPHINX_MATCHES_START;
    $SS->limit(0, TORRENTS_PER_PAGE);
}

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $Way = SPH_SORT_ATTR_ASC;
    $OrderWay = 'asc'; // For header links
} else {
    $Way = SPH_SORT_ATTR_DESC;
    $_GET['order_way'] = 'desc';
    $OrderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('time','size','seeders','leechers','snatched','random'))) {
    $_GET['order_by'] = 'time';
    $OrderBy = 'time'; // For header links
} elseif ($_GET['order_by'] == 'random') {
    $OrderBy = '@random';
    $Way = SPH_SORT_EXTENDED;
    $SS->limit(0, TORRENTS_PER_PAGE, TORRENTS_PER_PAGE);
} else {
    $OrderBy = $_GET['order_by'];
}

$SS->SetSortMode($Way, $OrderBy);

if (count($Queries)>0) {
    $Query = implode(' ',$Queries);
} else {
    $Query='';
    if (empty($SS->Filters)) {
        $SS->set_filter('size', array(0), true);
    }
}

$SS->set_index(SPHINX_INDEX.' delta');
$Results = $SS->search($Query, '', 0, array(), '', '');
$TorrentCount = $SS->TotalResults;

// These ones were not found in the cache, run SQL
if (!empty($Results['notfound'])) {
    $SQLResults = get_groups($Results['notfound']);

    if (is_array($SQLResults['notfound'])) { // Something wasn't found in the db, remove it from results
        reset($SQLResults['notfound']);
        foreach ($SQLResults['notfound'] as $ID) {
            unset($SQLResults['matches'][$ID]);
            unset($Results['matches'][$ID]);
        }
    }

    // Merge SQL results with sphinx/memcached results
    foreach ($SQLResults['matches'] as $ID=>$SQLResult) {
        $Results['matches'][$ID] = array_merge($Results['matches'][$ID], $SQLResult);
        ksort($Results['matches'][$ID]);
    }
}

$Results = $Results['matches'];

$AdvancedSearch = false;
$Action = 'action=basic';
if (((!empty($_GET['action']) && strtolower($_GET['action'])=="advanced") || (!empty($LoggedUser['SearchType']) && ((!empty($_GET['action']) && strtolower($_GET['action'])!="basic") || empty($_GET['action'])))) && check_perms('site_advanced_search')) {
    $AdvancedSearch = true;
    $Action = 'action=advanced';
}


if (count($Results)==0) {
$DB->query("SELECT
    tags.Name,
    ((COUNT(tags.Name)-2)*(SUM(tt.PositiveVotes)-SUM(tt.NegativeVotes)))/(tags.Uses*0.8) AS Score
    FROM xbt_snatched AS s
    INNER JOIN torrents AS t ON t.ID=s.fid
    INNER JOIN torrents_group AS g ON t.GroupID=g.ID
    INNER JOIN torrents_tags AS tt ON tt.GroupID=g.ID
    INNER JOIN tags ON tags.ID=tt.TagID
    WHERE s.uid='$LoggedUser[ID]'
    AND tt.TagID<>'13679'
    AND tt.TagID<>'4820'
    AND tt.TagID<>'2838'
    AND tags.Uses > '10'
    GROUP BY tt.TagID
    ORDER BY Score DESC
    LIMIT 8");

    $JsonYouMightLike = array();
    while (list($Tag)=$DB->next_record()) {
        $JsonYouMightLike[] = $Tag;
    }

    print
        json_encode(
            array(
                'status' => 'success',
                'response' => array(
                    'results' => array(),
                    'youMightLike' => $JsonYouMightLike
                )
            )
        );
    die();
}

$Bookmarks = all_bookmarks('torrent');

$JsonGroups = array();
foreach ($Results as $GroupID=>$Data) {
    list($GroupID2, $GroupName, $TagList, $Torrents, $FreeTorrent, $DoubleSeed, $TotalLeechers, $TotalSeeders, $MaxSize, $TotalSnatched, $GroupTime) = array_values($Data);

    $TagList = explode(' ',str_replace('_','.',$TagList));

        list($TorrentID, $Data) = each($Torrents);

        $JsonGroups[] = array(
                'groupId' => (int) $GroupID,
                'groupName' => $GroupName,
                'torrentId' => (int) $TorrentID,
                'tags' => $TagList,
                'fileCount' => (int) $Data['FileCount'],
                'groupTime' => $GroupTime,
                'size' => (int) $Data['Size'],
                'snatches' => (int) $TotalSnatched,
                'seeders' => (int) $TotalSeeders,
                'leechers' => (int) $TotalLeechers,
                'isFreeleech' => $Data['FreeTorrent'] == '1',
                'isNeutralLeech' => $Data['FreeTorrent'] == '2',
                'isPersonalFreeleech' => !empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['FreeLeech'] > sqltime(),
                'canUseToken' => ($LoggedUser['FLTokens'] > 0)
                                                        && $Data['HasFile'] && ($Data['Size'] < 1073741824)
                                                        && (empty($TockenTorrents[$TorrentID]) || $TokenTorrents[$TorrentID]['FreeLeech'] > sqltime())
                                                        && empty($Data['FreeTorrent']) && ($LoggedUser['CanLeech'] == '1')
        );
}

print
    json_encode(
        array(
            'status' => 'success',
            'response' => array(
                'currentPage' => intval($Page),
                'pages' => ceil($TorrentCount/TORRENTS_PER_PAGE),
                'results' => $JsonGroups
            )
        )
    );
