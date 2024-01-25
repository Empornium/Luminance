<?php
/* * **********************************************************************
 * -------------------- Browse page ---------------------------------------
 * Welcome to one of the most complicated pages in all of gazelle - the
 * browse page.
 *
 * This is the page that is displayed when someone visits torrents.php
 *
 * It offers normal and advanced search, as well as enabled/disabled
 * grouping.
 *
 * Don't blink.
 * Blink and you're dead.
 * Don't turn your back.
 * Don't look away.
 * And don't blink.
 * Good Luck.
 *
 * *********************************************************************** */

include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');


if (isset($activeUser['TorrentsPerPage'])) {
    $TorrentsPerPage = $activeUser['TorrentsPerPage'];
} else {
    $TorrentsPerPage = TORRENTS_PER_PAGE;
}

$userID = $activeUser['ID'];

// Search by infohash
if (!empty($_GET['searchtext'])) {
    $InfoHash = $_GET['searchtext'];

    if ($InfoHash = is_valid_torrenthash($InfoHash)) {
        $InfoHash = pack("H*", $InfoHash);
        list($ID, $GroupID) = $master->db->rawQuery(
            "SELECT ID,
                    GroupID
               FROM torrents
              WHERE info_hash = ?",
            [$InfoHash]
        )->fetch(\PDO::FETCH_NUM);
        if ($master->db->foundRows() > 0) {
            header('Location: torrents.php?id=' . $GroupID . '&torrentid=' . $ID);
            return;
        }
    }
}

// Setting default search options
if (!empty($_GET['setdefault'])) {
    $unsetList = ['page', 'setdefault'];

    $query = $_GET;
    foreach ($unsetList as $key) {
        if (array_key_exists($key, $query)) {
            unset($query[$key]);
        }
    }

    $defaultSearch = http_build_query($query);
    $user = $master->repos->users->load($activeUser['ID']);
    $user->setOption('DefaultSearch', $defaultSearch);

// Clearing default search options
} elseif (!empty($_GET['cleardefault'])) {
    $SiteOptions = $master->db->rawQuery(
        "SELECT SiteOptions
           FROM users_info
          WHERE UserID= ?",
        [$activeUser['ID']]
    )->fetchColumn();
    $SiteOptions = unserialize($SiteOptions);
    $SiteOptions['DefaultSearch'] = '';
    $activeUser['DefaultSearch'] = '';
    $master->db->rawQuery(
        "UPDATE users_info
            SET SiteOptions = ?
          WHERE UserID = ?",
        [serialize($SiteOptions), $activeUser['ID']]
    );
    $master->repos->users->uncache($activeUser['ID']);

// Use default search options
} elseif ((empty($_SERVER['QUERY_STRING']) || (count($_GET) == 1 && isset($_GET['page']))) && !empty($activeUser['DefaultSearch'])) {
    if (!empty($_GET['page'])) {
        $Page = $_GET['page'];
        parse_str($activeUser['DefaultSearch'], $_GET);
        $_GET['page'] = $Page;
    } else {
        parse_str($activeUser['DefaultSearch'], $_GET);
    }
}

$_GET['searchtext']  = trim($_GET['searchtext'] ?? '');
$_GET['title']       = trim($_GET['title']      ?? '');
$_GET['sizeall']     = trim($_GET['sizeall']    ?? '');
$_GET['filelist']    = trim($_GET['filelist']   ?? '');
$_GET['taglist']     = trim($_GET['taglist']    ?? '');

$Queries = [];
$UseCache = true;


    // Advanced search, and yet so much simpler in code.
    if (!empty($_GET['searchtext'])) {
        $UseCache = false;
        $SearchText = ' ' . trim($_GET['searchtext']);
        $SearchText = preg_replace(['/ not /i', '/ or /i', '/ and /i', '/\(/', '/\)/'], [' -', ' | ', ' & ', ' ( ', ' ) '], $SearchText);
        $SearchText = trim($SearchText);

        $Queries[] = '@searchtext ' . $SearchText; // *
    }

    if (!empty($_GET['taglist'])) {
        $UseCache = false;
        // Keep extended search signs.
        $TagList =  $_GET['taglist'];
        // add whitespace around brackets to stop sphinx getting confused (do it before splitting and rebuilding taglist to avoid extra whitespace in final string)
        $TagList = preg_replace(['/\(/', '/\)/'], [' ( ', ' ) '], $TagList);
        $TagList = preg_replace(['/ not /i', '/ or /i', '/ and /i'], [' -', ' | ', ' & '], $TagList);
        $TagList = preg_split("/([!&|]|^-| -| )/", $TagList, NULL, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($TagList as $Key => &$Tag) {
            $Tag = strtolower(trim($Tag)) ;

            if (in_array($Tag, ['-', '!', '|', '&', '+', '(', ')'])) {
                continue;
            }
            // do synonym replacement and skip <2 length tags
            if (strlen($Tag) >= 2) {
                $Tag = get_tag_synonym($Tag, false);
                $Tag = str_replace('.', '_', $Tag);
                $Tag = $search->escapeString($Tag);
            } else {
                unset($TagList[$Key]);
            }
        }
        $searchTagList = implode(' ', $TagList);
        $searchTagList = trim($searchTagList);
        $Queries[] = '@taglist ' . $searchTagList;

        // Start again this time targetting SQL search string
        //$SQLTagList = parse_tag_search($_GET['taglist']);

        /*
         *  $master->db->rawQuery(
         *      "SELECT COUNT(*)
         *         FROM torrents_taglists
         *        WHERE MATCH(taglist) AGAINST(? IN BOOLEAN MODE)",
         *      [$SQLTagList]
         *  );
         */

    }


foreach (['title'=>'groupname'] as $Search=>$Queryname) {

    if (!empty($_GET[$Search])) {
        $UseCache = false;
        $_GET[$Search] = str_replace(['%'], '', $_GET[$Search]);
        $Words = explode(' ', $_GET[$Search]);
        foreach ($Words as $Key => &$Word) {
            if (substr($Word, 0, 1) === '-' && strlen($Word) >= 3 && count($Words) >= 2) {
                $Word = '!' . $search->escapeString(substr($Word, 1));
            } elseif (strlen($Word) >= 2) {
                $Word = $search->escapeString($Word);
            } else {
                unset($Words[$Key]);
            }
        }
        $Words = trim(implode(' ', $Words));
        if (!empty($Words)) {
            $Queries[] = "@{$Queryname} " . $Words;
        }
    }
}

if (!empty($_GET['filelist'])) {
    $UseCache = false;
    $FileList = ' ' . trim($_GET['filelist']);
    $FileList = str_replace('_', ' ', $FileList);
    $FileList = str_replace(['%'], '', $FileList);
    $FileList = trim($FileList);

    $Queries[] = '@filelist "' . $search->escapeString($FileList) . '"~20';
}

if (!empty($_GET['filter_freeleech']) && $_GET['filter_freeleech'] == 1) {
    $UseCache = false;
    $search->setFilter('FreeTorrent', [1]);
}

if (!empty($_GET['filter_cat'])) {
    $UseCache = false;
    $search->setFilter('newcategoryid', array_keys($_GET['filter_cat']));
}


if (!isset($_GET['sizerange'])) $_GET['sizerange'] = 0.01;
if (!isset($_GET['sizetype'])) $_GET['sizetype'] = 'gb';

if (!empty($_GET['sizeall'])) {
    $UseCache = false;
    if ($_GET['sizetype']=='tb') {
        $mul = 1024 * 1024 * 1024;
    } elseif ($_GET['sizetype']=='gb') {
        $mul = 1024 * 1024;
    } elseif ($_GET['sizetype']=='mb') {
        $mul = 1024;
    } else {
        $mul = 1;
    }
    $totalsize = (float) $_GET['sizeall'] * $mul;
    $rangemod = (float) $_GET['sizerange'];
    $range = (float) ($mul * $rangemod);
    $min_sizekb = (int) ceil($totalsize - $range);
    $max_sizekb = (int) ceil($totalsize + $range);
    $search->setFilterRange('size', $min_sizekb, $max_sizekb);
}


if (!empty($_GET['page']) && is_integer_string($_GET['page'])) {
    if (check_perms('site_search_many')) {
        $Page = $_GET['page'];
    } else {
        $Page = min(SPHINX_MAX_MATCHES / $TorrentsPerPage, $_GET['page']);
    }
    $MaxMatches = min(SPHINX_MAX_MATCHES, SPHINX_MATCHES_START + SPHINX_MATCHES_STEP * floor(($Page - 1) * $TorrentsPerPage / SPHINX_MATCHES_STEP));
    $MaxMatches = $search->limit(($Page - 1) * $TorrentsPerPage, $TorrentsPerPage, $MaxMatches);
} else {
    $Page = 1;
    $MaxMatches = SPHINX_MATCHES_START;
    $MaxMatches = $search->limit(0, $TorrentsPerPage);
}

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $Way = SPH_SORT_ATTR_ASC;
    $orderWay = 'asc'; // For header links
} else {
    $Way = SPH_SORT_ATTR_DESC;
    $_GET['order_way'] = 'desc';
    $orderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], ['time', 'size', 'seeders', 'leechers', 'snatched', 'random'])) {
    $_GET['order_by'] = 'time';
    $orderBy = 'time'; // For header links
} elseif ($_GET['order_by'] == 'random') {
    $orderBy = '@random';
    $Way = SPH_SORT_EXTENDED;
    $search->limit(0, $TorrentsPerPage, $TorrentsPerPage);
} else {
    $orderBy = $_GET['order_by'];
}

$search->setSortMode($Way, $orderBy);

$didSearch = true;

if (count($Queries) > 0) {
    $Query = implode(' ', $Queries);
} else {
    $Query = '';
    if (empty($search->filters)) {
        $didSearch = false;
        $search->setFilter('size', [0], true);
    }
}
if ($UseCache) {
    $RealStart = ($Page - 1) * $TorrentsPerPage;
    $CacheKey = "torrents_{$RealStart}_{$TorrentsPerPage}_{$orderWay}_{$orderBy}_{$MaxMatches}";
    $Results = $master->cache->getValue($CacheKey);
    if (!$Results || !array_key_exists('timestamp', $Results) || (time() - $Results['timestamp'] > 120)) {
        $search->setIndex(SPHINX_INDEX . ' delta');
        $Results = $search->search($Query, '', 0, [], '', '');
        $Results['count'] = $search->totalResults;
        $Results['timestamp'] = time();
    }
    $master->cache->cacheValue($CacheKey, $Results, 120);
    $TorrentCount = $Results['count'];
} else {
    $search->setIndex(SPHINX_INDEX . ' delta');
    $Results = $search->search($Query, '', 0, [], '', '');
    $TorrentCount = $search->totalResults;
}

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
    foreach ($SQLResults['matches'] as $ID => $SQLResult) {
        $Results['matches'][$ID] = array_merge($Results['matches'][$ID], $SQLResult);
        ksort($Results['matches'][$ID]);
    }
}

$matches = [];
if (is_array($Results)) {
    if (array_key_exists('matches', $Results)) {
        $matches = $Results['matches'];
    }
}

$Results = $matches;
unset($matches);

if (check_perms('torrent_review')) {
    update_staff_checking("browsing torrents", true);
    show_header('Browse Torrents', 'jquery,jquery.cookie,browse,status,overlib,bbcode');
} else {
    show_header('Browse Torrents', 'jquery,jquery.cookie,browse,overlib,bbcode');
}

// List of pages
$Pages = get_pages($Page, $TorrentCount, $TorrentsPerPage);
?>

<div class="thin">
    <h2>
        <a style="float:left;margin-top:4px" href="/feeds.php?feed=torrents_all&amp;user=<?=$activeUser['ID']?>&amp;auth=<?=$activeUser['RSS_Auth']?>&amp;passkey=<?=$activeUser['torrent_pass']?>&amp;authkey=<?=$activeUser['AuthKey']?>" title="<?=SITE_NAME?> : All Torrents" ><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
    Browse Torrents</h2>
<?php
    if (check_perms('torrent_review')) {
?>
        <div id="staff_status" class="status_box">
            <span class="status_loading">loading staff checking status...</span>
        </div>
        <br class="clear"/>
<?php
    }
    print_latest_forum_topics();
?>
<form id="search_form" name="filter" method="get" action=''>
    <div id="search_box" class="filter_torrents">
        <div class="head">Search</div>
        <div class="box pad">
            <table id="cat_list" class="cat_list on_cat_change <?php  if (!empty($activeUser['HideCats'])) { ?>hidden<?php  } ?>">
                <?php
                $row = 'a';
                $x = 0;
                reset($newCategories);
                foreach ($newCategories as $Cat) {
                    if ($x % 7 == 0) {
                        if ($x > 0) {
                            ?>
                            </tr>
                        <?php  } ?>
                        <tr class="row<?=$row?>">
                            <?php
                            $row = $row == 'a' ? 'b' : 'a';
                        }
                        $x++;
                        ?>
                        <td>
                            <input type="checkbox" name="filter_cat[<?= ($Cat['id']) ?>]" id="cat_<?= ($Cat['id']) ?>" value="1" <?php  if (isset($_GET['filter_cat'][$Cat['id']])) { ?>checked="checked"<?php  } ?>/>
                            <label for="cat_<?= ($Cat['id']) ?>" class="cat_label"><span><a href="/torrents.php?filter_cat[<?=$Cat['id']?>]=1"><?= $Cat['name'] ?></a></span></label>
                        </td>
                    <?php  } ?>
                    <td colspan="<?= 7 - ($x % 7) ?>"></td>
                </tr>
            </table>
            <table class="noborder">
                <tr class="on_cat_change <?php  if (!empty($activeUser['HideCats'])) { ?>hidden<?php  } ?>"><td colspan="4">&nbsp;</td></tr>
                <tr>
                    <td class="label" style="width:140px">Order by:</td>
                    <td colspan="4">
                        <span style="float:left">
                            <select name="order_by" style="width:auto;">
                                <option value="time"<?php  selected('order_by', 'time') ?>>Time added</option>
                                <option value="size"<?php  selected('order_by', 'size') ?>>Size</option>
                                <option value="snatched"<?php  selected('order_by', 'snatched') ?>>Snatched</option>
                                <option value="seeders"<?php  selected('order_by', 'seeders') ?>>Seeders</option>
                                <option value="leechers"<?php  selected('order_by', 'leechers') ?>>Leechers</option>
                                <option value="random"<?php  selected('order_by', 'random') ?>>Random</option>
                            </select>
                            <select name="order_way">
                                <option value="desc"<?php  selected('order_way', 'desc') ?>>Descending</option>
                                <option value="asc" <?php  selected('order_way', 'asc') ?>>Ascending</option>
                            </select>
                        </span>
                        <span style="float:left">
                            <label style="margin-left: 20px;" for="filter_freeleech" title="Limit results to ONLY freeleech torrents"><strong>Include only freeleech torrents.</strong></label>
                            <input type="checkbox" id="filter_freeleech" name="filter_freeleech" value="1" <?php  selected('filter_freeleech', 1, 'checked') ?>/>
                    <?php  if (check_perms('site_search_many')) { ?>
                            <label style="margin-left:20px;" for="limit_matches" title="Limit results to the first 100 matches"><strong>Limit results (max 100):</strong></label>
                            <input type="checkbox" value="1" id="limit_matches" name="limit_matches" <?php  selected('limit_matches', 1, 'checked') ?> />
                    <?php  } ?>
                    <?php  if (check_perms('torrent_review')) { ?>
                            <br />
                            <label style="margin-left:16px;" for="filter_unmarked" title="Limit results to unmarked torrents only"><strong>Include only unmarked torrents.</strong></label>
                            <input type="checkbox" value="1" id="filter_unmarked" name="filter_unmarked" <?php  selected('filter_unmarked', 1, 'checked') ?> />
                    <?php  } ?>
                        </span>
                        <span style="float:right">
                            <a href="#" onclick="$('.on_cat_change').toggle(); if (this.innerHTML=='(View Categories)') {this.innerHTML='(Hide Categories)';} else {this.innerHTML='(View Categories)';}; return false;"><?= (!empty($activeUser['HideCats'])) ? '(View Categories)' : '(Hide Categories)' ?></a>
                        </span>
                    </td>
                </tr>
                    <tr>
                        <td class="label" style="width:140px"></td>
                        <td colspan="3">
                            Search supports full boolean search, click here: <a href="/articles/view/search" style="font-weight:bold">Article on Searching</a> for more information.
                        </td>
                        <td rowspan="6" class="search_buttons">
                            <span>
                            <input type="submit" class="hidden" value="Search" />
<?php
                                if ($didSearch) { ?>
                                    <input type="submit" name="setdefault" value="Make Default" /><br/>
<?php                           }
                                if (!empty($activeUser['DefaultSearch'])) {   ?>
                                    <input type="submit" name="cleardefault" value="Clear Default" /><br/>
<?php                           } ?>

                                <input type="button" value="Reset" onclick="location.href='/torrents.php?action=search'" /><br/>
                                <br/><br/>
                                <input type="submit" value="Search" />
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="width:140px" title="Searches Title and Description fields, supports full boolean search">Search Terms:</td>
                        <td colspan="3">
                            <input type="text" spellcheck="false" size="40" name="searchtext" class="inputtext" title="Searches Title and Description fields, supports full boolean search" value="<?php  form('searchtext') ?>" />
                            <input type="hidden" name="action" value="advanced" />
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="width:140px" title="Search Title field only">Title:</td>
                        <td colspan="3">
                            <input type="text" spellcheck="false" size="40" name="title" class="inputtext" title="Search Title field only" value="<?php  form('title') ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="width:140px" title="Search Size field only">Size:</td>
                        <td colspan="3">
                            <input type="text" spellcheck="false" size="25" name="sizeall" class="smallish" title="Specify a size, IMPORTANT: because size is rounded from bytes there is a small margin each way - so not all matches will have the exact same number of bytes" value="<?php  form('sizeall') ?>" />
                            <select name="sizetype">
                                    <option value="kb" <?php if ($_GET['sizetype']=='kb')echo'selected="selected"'?> > KB </option>
                                    <option value="mb" <?php if ($_GET['sizetype']=='mb')echo'selected="selected"'?>> MB </option>
                                    <option value="gb" <?php if ($_GET['sizetype']=='gb')echo'selected="selected"'?>> GB </option>
                                    <option value="tb" <?php if ($_GET['sizetype']=='tb')echo'selected="selected"'?>> TB </option>
                            </select>
                            &nbsp; range &plusmn;
                            <input type="text" spellcheck="false" size="10" name="sizerange" class="smallest" title="Advanced users! Specify a range modifier, default is &plusmn;0.01" value="<?php  form('sizerange') ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td class="label" style="width:140px" title="Matches exact filenames, OR exact bytesize">File List:</td>
                        <td colspan="3">
                            <input type="text" spellcheck="false" size="40" name="filelist" class="inputtext" title="Matches exact filenames, OR exact bytesize" value="<?php  form('filelist') ?>" />
                        </td>
                    </tr>
                <tr>
                    <td class="label" style="width:140px" title="Search Tags, supports full boolean search">Tags:</td>
                    <td colspan="3">
                        <textarea id="taginput" name="taglist" class="inputtext" title="Search Tags, supports full boolean search"><?= str_replace('_', '.', form('taglist', true)) ?></textarea>
                        <label class="checkbox_label" title="Toggle autocomplete mode on or off.&#10;When turned off, you can access your browser's form history.">
                            <input id="autocomplete_toggle" type="checkbox" name="autocomplete_toggle" checked="checked" />
                            Autocomplete tags
                        </label>
                    </td>
                </tr>
            </table>

                        <span style="float:right;padding-right:5px">
                            <a href="#" onclick="$('#taglist').toggle(); if (this.innerHTML=='(View Tags)') {this.innerHTML='(Hide Tags)';} else {this.innerHTML='(View Tags)';}; return false;"><?= (empty($activeUser['ShowTags'])) ? '(View Tags)' : '(Hide Tags)' ?></a>
                        </span>

            <table width="100%" class="taglist <?php  if (empty($activeUser['ShowTags'])) { ?>hidden<?php  } ?>" id="taglist">
                <tr class="row<?=$row?>">
                    <?php
                    $GenreTags = $master->cache->getValue('genre_tags');
                    if (!$GenreTags) {
                        $GenreTags = $master->db->rawQuery("(SELECT Name FROM tags WHERE TagType='genre' ORDER BY Uses DESC LIMIT 42) ORDER BY Name")->fetchAll(\PDO::FETCH_COLUMN);
                        $master->cache->cacheValue('genre_tags', $GenreTags, 3600 * 24);
                    }

                    $x = 0;
                    foreach ($GenreTags as $Tag) {
                        ?>
                        <td width="12.5%"><a href="#" onclick="add_tag('<?= $Tag ?>');return false;"><?= $Tag ?></a></td>
                        <?php
                        $x++;
                        if ($x % 7 == 0) {
                            $row = $row == 'a' ? 'b' : 'a';
                            ?>
                        </tr>
                        <tr class="row<?=$row?>">
                            <?php
                        }
                    }
                    if ($x % 7 != 0) { // Padding
                        ?>
                        <td colspan="<?= 7 - ($x % 7) ?>"> </td>
                    <?php  } ?>
                </tr>
            </table>
            <div class="numsearchresults">
                <span><?= number_format($TorrentCount) . ($TorrentCount < SPHINX_MAX_MATCHES && $TorrentCount == $MaxMatches ? '+' : '') ?> Results</span>
            </div>
        </div>
    </div>
</form>
<div id="filter_slidetoggle"><a href="#" id="search_button" onclick="Panel_Toggle();">Close Search Center</a> </div>

<div class="linkbox"><a href="/torrents.php?action=clear_browse" title="click here to clear the (New!) tags">clear new</a></div>
<div class="linkbox pager"><?= $Pages ?></div>
<div class="head">Torrents</div>
<?php

if ($TorrentCount == 0) {
    $records = $master->db->rawQuery(
        "SELECT tags.Name,
                ((COUNT(tags.Name)-2)*(SUM(tt.PositiveVotes)-SUM(tt.NegativeVotes)))/(tags.Uses*0.8) AS Score
           FROM xbt_snatched AS s
     INNER JOIN torrents AS t ON t.ID=s.fid
     INNER JOIN torrents_group AS g ON t.GroupID=g.ID
     INNER JOIN torrents_tags AS tt ON tt.GroupID=g.ID
     INNER JOIN tags ON tags.ID=tt.TagID
          WHERE s.uid = ?
            AND tags.Uses > '10'
       GROUP BY tt.TagID
       ORDER BY Score DESC
          LIMIT 8",
        [$activeUser['ID']]
    )->fetchAll(\PDO::FETCH_OBJ);
    ?>
    <div class="box pad" align="center">
        <h2>Your search did not match anything.</h2>
        <p>Make sure all names are spelled correctly, or try making your search less specific.</p>
        <p>You might like (Beta): <?php  foreach ($records as $record) { ?><a href="/torrents.php?taglist=<?= $record->Name ?>"><?= $record->Name ?></a> <?php  } ?></p>
    </div></div>
    <?php
    show_footer();
    return;
}

// if no searchtext or tags specified then we are showing all torrents
$AllTorrents = ((!isset($_GET['taglist']) || $_GET['taglist']=='')
             && (!isset($_GET['searchtext']) || $_GET['searchtext']==''))?TRUE:FALSE;
$Bookmarks = all_bookmarks('torrent');
?>
<table class="torrent_table grouping" id="torrent_table">
    <tr class="colhead">
        <td class="small cats_col"></td>
        <td width="100%">Name</td>
        <td>Files</td>
        <td>Comm</td>
        <td><a href="<?=header_link('time')?>">Time</a></td>
        <td><a href="<?=header_link('size')?>">Size</a></td>
        <td class="sign"><a href="<?=header_link('snatched')?>"><img src="/static/styles/<?= $activeUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /></a></td>
        <td class="sign"><a href="<?=header_link('seeders')?>"><img src="/static/styles/<?= $activeUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /></a></td>
        <td class="sign"><a href="<?=header_link('leechers')?>"><img src="/static/styles/<?= $activeUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /></a></td>
        <td>Uploader</td>
    </tr>
    <?php
    // Start printing torrent list
    $row='a';
    $lastday = 0;
    foreach ((array)$Results as $GroupID => $GData) {
        list(, $GroupID2, $GroupName, $TagList, $Torrents, $FreeTorrent, $Image, $TotalLeechers,
                $NewCategoryID, $SearchText, $TotalSeeders, $MaxSize, $TotalSnatched, $GroupTime) = array_values($GData);

        $Data = array_values($Torrents)[0];
        $torrentID = $Data['ID'];

        $Review = get_last_review($GroupID);
        if (isset($_GET['filter_unmarked']) && $_GET['filter_unmarked'] == 1 && $Review['Status']) {
             continue;
        }

        $day = date('j', strtotime($Data['Time'])  - $activeUser['TimeOffset']);
        if ($AllTorrents && ($activeUser['SplitByDays'] ?? false) && $lastday !== $day) {
?>
    <tr class="colhead">
        <td colspan="10" class="center">
            <?= date('l jS F Y', strtotime($Data['Time']) - $activeUser['TimeOffset'])?>
        </td>
    </tr>
<?php
            $lastday = $day;
        }

        $TagList = explode(' ', str_replace('_', '.', $TagList));

        $TorrentTags = [];
        $numtags=0;
        foreach ($TagList as $Tag) {
            if ($numtags++>=$activeUser['MaxTags'])  break;
            $TorrentTags[] = '<a href="/torrents.php?taglist=' . $Tag . '">' . $Tag . '</a>';
        }
        $TorrentTags = implode(' ', $TorrentTags);

        $TorrentUsername = anon_username($Data['Username'], $Data['Anonymous']);

        $AddExtra = torrent_icons($Data, $torrentID, $Review, in_array($GroupID, $Bookmarks));

        $row = ($row == 'a'? 'b' : 'a');
        $IsMarkedForDeletion = $Review['Status'] == 'Warned' || $Review['Status'] == 'Pending';

        $NumComments = get_num_comments($GroupID);
        ?>
        <tr class="torrent <?=($IsMarkedForDeletion?'redbar':"row$row")?>">
            <td class="center cats_col">
                <?php  $CatImg = 'static/common/caticons/' . $newCategories[$NewCategoryID]['image']; ?>
                <div title="<?= $newCategories[$NewCategoryID]['tag'] ?>"><a href="/torrents.php?filter_cat[<?=$NewCategoryID?>]=1"><img src="<?= $CatImg ?>" /></a></div>
            </td>
            <td>
<?php
                if ($Data['ReportCount'] > 0) {
                    $Title = "This torrent has ".$Data['ReportCount']." active ".($Data['ReportCount'] > 1 ?'reports' : 'report');
                    $Reported = ' /<span class="reported" title="'.$Title.'"> Reported</span>';
                } else {
                    $Reported = '';
                }

                $newtag = '';
                if ($Data['Time'] > $activeUser['LastBrowse']) { $newtag = '<span class="newtorrent">(New!)</span>'; }

                if (check_perms('torrent_review') && ($activeUser['ShowTorrentChecker'] ?? 0) === 1 && !empty($Review['Staffname'])) { ?>
                    <span class="bold"><strong>Checked by <?=$Review['Staffname']?></strong></span>
                    <br />
<?php           }
                if ($activeUser['HideFloat'] ?? false) {?>
                    <?=$AddExtra.$newtag?> <a href="/torrents.php?id=<?=$GroupID?>"><?=$GroupName?></a>
<?php           } else {
                    $Overlay = get_overlay_html($GroupName, $TorrentUsername, $Image, $Data['Seeders'], $Data['Leechers'], $Data['Size'], $Data['Snatched']);
?>
                    <script>
                        var overlay<?=$GroupID?> = <?=json_encode($Overlay)?>
                    </script>
                    <?=$AddExtra.$newtag?>
                    <a href="/torrents.php?id=<?=$GroupID?>" onmouseover="return overlib(overlay<?=$GroupID?>, FULLHTML);" onmouseout="return nd();"><?=display_str($GroupName).$Reported?></a>

<?php           } ?>
                <br />
                <?php  if (($activeUser['HideTagsInLists'] ?? 0) !== 1) { ?>
                <div class="tags">
                   <?= $TorrentTags ?>
                </div>
                <?php  } ?>
            </td>
            <td class="center"><?=number_format($Data['FileCount'])?></td>
            <td class="center"><?=number_format($NumComments)?></td>
            <td class="nobr"><?=time_diff($Data['Time'], 1) ?></td>
            <td class="nobr"><?= get_size($Data['Size']) ?></td>
            <td><?= number_format($Data['Snatched']) ?></td>
            <td<?= ($Data['Seeders'] == 0) ? ' class="r00"' : '' ?>><?= number_format($Data['Seeders']) ?></td>
            <td><?= number_format($Data['Leechers']) ?></td>
            <td class="user"><?=torrent_username($Data['UserID'], $Data['Anonymous']) ?></td>
        </tr>
        <?php
    }
    ?>
</table>
</div>
<div class="linkbox pager"><?= $Pages ?></div>
<?php
show_footer(['disclaimer' => false]);
