<?php

$Where = [];
$params = [];

if (!empty($_GET['advanced']) && check_perms('site_advanced_top10')) {
    $Details = 'all';
    $Limit = 10;

    if ($_GET['tags']) {
        $Tags = cleanup_tags($_GET['tags']);
        $Tags = explode(' ', str_replace(".", "_", trim($Tags)));
        foreach ($Tags as $Tag) {
            $Tag = sanitize_tag($Tag);
            if ($Tag != '') {
                $Where[] = "g.TagList REGEXP ?";
                $params[] = "[[:<:]]{$Tag}[[:>:]]";
            }
        }
    }
} else {
    // error out on invalid requests (before caching)
    if (isset($_GET['details'])) {
        if (in_array($_GET['details'], ['day', 'week', 'month', 'year', 'overall', 'snatched', 'data', 'seeded'])) {
            $Details = $_GET['details'];
        } else {
            error(404);
        }
    } else {
        $Details = 'all';
    }

    // defaults to 10 (duh)
    $Limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $Limit = in_array($Limit, [10, 100, 250, 500]) ? $Limit : 10;
}
$Filtered = !empty($Where);
show_header('Top '.$Limit.' Torrents', 'overlib');
?>
<div class="thin">
    <h2> Top <?=$Limit?> Torrents </h2>
    <div class="linkbox">
        <a href="/top10.php?type=torrents"><strong>[Torrents]</strong></a>
        <a href="/top10.php?type=users">[Users]</a>
        <a href="/top10.php?type=tags">[Tags]</a>
        <a href="/top10.php?type=taggers">[Taggers]</a>
    </div>
<?php

if (check_perms('site_advanced_top10')) {
?>
    <div class="head">Search</div>
        <form action="" method="get">
            <input type="hidden" name="advanced" value="1" />
            <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
                <tr>
                    <td class="label">Tags:</td>
                    <td>
                        <input id="taginput" type="text" name="tags" class="long" value="<?php  if (!empty($_GET['tags'])) { echo display_str($_GET['tags']);} ?>" autofocus />
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="center">
                        <input type="submit" value="Filter torrents" />
                    </td>
                </tr>
            </table>
        </form>

<?php
}

// default setting to have them shown
$DisableFreeTorrentTop10 = (isset($activeUser['DisableFreeTorrentTop10']) ? $activeUser['DisableFreeTorrentTop10'] : 0);
// did they just toggle it?
if (isset($_GET['freeleech'])) {
    // what did they choose?
    $NewPref = ($_GET['freeleech'] == 'hide') ? 1 : 0;

    // Pref id different
    if ($NewPref != $DisableFreeTorrentTop10) {
        $DisableFreeTorrentTop10 = $NewPref;
        update_site_options($activeUser['ID'], ['DisableFreeTorrentTop10' => $DisableFreeTorrentTop10]);
    }
}

// Modify the Where query
if ($DisableFreeTorrentTop10) {
    $Where[] = "t.FreeTorrent='0'";
}

// The link should say the opposite of the current setting
$FreeleechToggleName = ($DisableFreeTorrentTop10 ? 'show' : 'hide');
$FreeleechToggleQuery = get_url(['freeleech']);

if (!empty($FreeleechToggleQuery))
    $FreeleechToggleQuery .= '&amp;';

$FreeleechToggleQuery .= 'freeleech=' . $FreeleechToggleName;

?>
    <div class="linkbox" style="text-align: right;">
        <a href="/top10.php?<?=$FreeleechToggleQuery?>">[<?=ucfirst($FreeleechToggleName)?> Freeleech in Top 10]</a>
    </div>
<?php

$Where = implode(' AND ', $Where);

$WhereSum = (empty($Where)) ? '' : md5($master->db->interpolateQuery($Where, $params));

$BaseQuery =
    "SELECT t.ID,
            g.ID AS GroupID,
            g.Name,
            g.NewCategoryID,
            g.TagList,
            t.Snatched,
            t.Seeders,
            t.Leechers,
            ((t.Size * t.Snatched) + (t.Size * 0.5 * t.Leechers)) AS Data,
            t.Size,
            t.UserID,
            u.Username,
            t.FreeTorrent,
            t.DoubleTorrent,
            t.Anonymous,
            g.Image
       FROM torrents AS t
  LEFT JOIN torrents_group AS g ON g.ID = t.GroupID
  LEFT JOIN users AS u ON u.ID = t.UserID ";

if ($Details=='all' || $Details=='day') {
    if (!$TopTorrentsActiveLastDay = $master->cache->getValue('top10tor_day_'.$Limit.$WhereSum)) {
        $DayAgo = time_minus(86400);
        $Query = $BaseQuery.' WHERE t.Seeders>0 AND ';
        if (!empty($Where)) { $Query .= $Where.' AND '; }
        $Query .= "
            t.Time > ?
            ORDER BY (t.Seeders + t.Leechers) DESC
            LIMIT {$Limit};";
        $TopTorrentsActiveLastDay = $master->db->rawQuery($Query, array_merge($params, [$DayAgo]))->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('top10tor_day_'.$Limit.$WhereSum, $TopTorrentsActiveLastDay,3600*2);
    }
    generate_torrent_table('Most Active Torrents Uploaded in the Past Day', 'day', $TopTorrentsActiveLastDay, $Limit);
}
if ($Details=='all' || $Details=='week') {
    if (!$TopTorrentsActiveLastWeek = $master->cache->getValue('top10tor_week_'.$Limit.$WhereSum)) {
        $WeekAgo = time_minus(604800);
        $Query = $BaseQuery.' WHERE ';
        if (!empty($Where)) { $Query .= $Where.' AND '; }
        $Query .= "
            t.Time > ?
            ORDER BY (t.Seeders + t.Leechers) DESC
            LIMIT {$Limit};";
        $TopTorrentsActiveLastWeek = $master->db->rawQuery($Query, array_merge($params, [$WeekAgo]))->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('top10tor_week_'.$Limit.$WhereSum, $TopTorrentsActiveLastWeek,3600*6);
    }
    generate_torrent_table('Most Active Torrents Uploaded in the Past Week', 'week', $TopTorrentsActiveLastWeek, $Limit);
}
if ($Details=='all' || $Details=='month') {
    if (!$TopTorrentsActiveLastMonth = $master->cache->getValue('top10tor_month_'.$Limit.$WhereSum)) {
        $MonthAgo = time_minus(2592000);
        $Query = $BaseQuery.' WHERE ';
        if (!empty($Where)) { $Query .= $Where.' AND '; }
        $Query .= "
            t.Time > ?
            ORDER BY (t.Seeders + t.Leechers) DESC
            LIMIT {$Limit};";
        $TopTorrentsActiveLastMonth = $master->db->rawQuery($Query, array_merge($params, [$MonthAgo]))->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('top10tor_month_'.$Limit.$WhereSum, $TopTorrentsActiveLastMonth,3600*24);
    }
    generate_torrent_table('Most Active Torrents Uploaded in the Past Month', 'month', $TopTorrentsActiveLastMonth, $Limit);
}
if ($Details=='all' || $Details=='year') {
    if (!$TopTorrentsActiveLastYear = $master->cache->getValue('top10tor_year_'.$Limit.$WhereSum)) {
        $YearAgo = time_minus(31536000);
        $Query = $BaseQuery.' WHERE ';
        if (!empty($Where)) { $Query .= $Where.' AND '; }
        $Query .= "
            t.Time > ?
            ORDER BY (t.Seeders + t.Leechers) DESC
            LIMIT {$Limit};";
        $TopTorrentsActiveLastYear = $master->db->rawQuery($Query, array_merge($params, [$YearAgo]))->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('top10tor_year_'.$Limit.$WhereSum, $TopTorrentsActiveLastYear,3600*24*7);
    }
    generate_torrent_table('Most Active Torrents Uploaded in the Past Year', 'year', $TopTorrentsActiveLastYear, $Limit);
}

if ($Details=='all' || $Details=='overall') {
    if (!$TopTorrentsActiveAllTime = $master->cache->getValue('top10tor_overall_'.$Limit.$WhereSum)) {
        // IMPORTANT NOTE - we use WHERE t.Seeders>500 in order to speed up this query. You should remove it!
        $Query = $BaseQuery;
        if ($Details=='all' && !$Filtered) {
            //$Query .= " WHERE t.Seeders>=500 ";
            if (!empty($Where)) { $Query .= ' AND '.$Where; }
        } elseif (!empty($Where)) { $Query .= ' WHERE '.$Where; }
        $Query .= "
            ORDER BY (t.Seeders + t.Leechers) DESC
            LIMIT {$Limit};";
        $TopTorrentsActiveAllTime = $master->db->rawQuery($Query, $params)->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('top10tor_overall_'.$Limit.$WhereSum, $TopTorrentsActiveAllTime,3600*6);
    }
    generate_torrent_table('Most Active Torrents of All Time', 'overall', $TopTorrentsActiveAllTime, $Limit);
}

if (($Details=='all' || $Details=='snatched') && !$Filtered) {
    if (!$TopTorrentsSnatched = $master->cache->getValue('top10tor_snatched_'.$Limit.$WhereSum)) {
        $Query = $BaseQuery;
        if (!empty($Where)) { $Query .= ' WHERE '.$Where; }
        $Query .= "
            ORDER BY t.Snatched DESC
            LIMIT {$Limit};";
        $TopTorrentsSnatched = $master->db->rawQuery($Query, $params)->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('top10tor_snatched_'.$Limit.$WhereSum, $TopTorrentsSnatched,3600*6);
    }
    generate_torrent_table('Most Snatched Torrents', 'snatched', $TopTorrentsSnatched, $Limit);
}

if (($Details=='all' || $Details=='data') && !$Filtered) {
    if (!$TopTorrentsTransferred = $master->cache->getValue('top10tor_data_'.$Limit.$WhereSum)) {
        // IMPORTANT NOTE - we use WHERE t.Snatched>100 in order to speed up this query. You should remove it!
        $Query = $BaseQuery;
        if ($Details=='all') {
            $Query .= " WHERE t.Snatched>=100 ";
            if (!empty($Where)) { $Query .= ' AND '.$Where; }
        }
        $Query .= "
            ORDER BY Data DESC
            LIMIT {$Limit};";
        $TopTorrentsTransferred = $master->db->rawQuery($Query, $params)->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('top10tor_data_'.$Limit.$WhereSum, $TopTorrentsTransferred,3600*6);
    }
    generate_torrent_table('Most Data Transferred Torrents', 'data', $TopTorrentsTransferred, $Limit);
}

if (($Details=='all' || $Details=='seeded') && !$Filtered) {
    $TopTorrentsSeeded = $master->cache->getValue('top10tor_seeded_'.$Limit.$WhereSum);
    if ($TopTorrentsSeeded === FALSE) {
        $Query = $BaseQuery;
        if (!empty($Where)) { $Query .= ' WHERE '.$Where; }
        $Query .= "
            ORDER BY t.Seeders DESC
            LIMIT {$Limit};";
        $TopTorrentsSeeded = $master->db->rawQuery($Query, $params)->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('top10tor_seeded_'.$Limit.$WhereSum, $TopTorrentsSeeded,3600*6);
    }
    generate_torrent_table('Best Seeded Torrents', 'seeded', $TopTorrentsSeeded, $Limit);
}

?>
</div>
<?php
show_footer();

// generate a table based on data from most recent query to $DB
function generate_torrent_table($Caption, $Tag, $Details, $Limit)
{
    global $activeUser, $newCategories;
?>
        <div class="head">Top <?=$Limit.' '.$Caption?>
<?php 	if (empty($_GET['advanced'])) { ?>
        <small>
            - [<a href="/top10.php?type=torrents&amp;limit=100&amp;details=<?=$Tag?>">Top 100</a>]
            - [<a href="/top10.php?type=torrents&amp;limit=250&amp;details=<?=$Tag?>">Top 250</a>]
            - [<a href="/top10.php?type=torrents&amp;limit=500&amp;details=<?=$Tag?>">Top 500</a>]
        </small>
<?php 	} ?>
        </div>
    <table class="torrent_table">
    <tr class="colhead">
        <td class="center" style="width:15px;"></td>
        <td style="width:32px;"></td>
        <td>Name</td>
        <td class="top10 statlong">Data</td>
        <td class="top10 statlong">Size</td>
        <td class="top10 stat"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/snatched.png" alt="Snatches" title="Snatches" /></td>
        <td class="top10 stat"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/seeders.png" alt="Seeders" title="Seeders" /></td>
        <td class="top10 stat"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/leechers.png" alt="Leechers" title="Leechers" /></td>
        <td class="top10 stat">Peers</td>
        <td class="top10 statname">Uploader</td>
    </tr>
<?php
    // in the unlikely event that query finds 0 rows...
    if (empty($Details)) {
?>
        <tr class="rowb">
            <td colspan="10" class="center">
                Found no torrents matching the criteria
            </td>
        </tr>
        </table><br />
<?php

        return;
    }
    $Rank = 0;
    foreach ($Details as $Detail) {
        $GroupIDs[] = $Detail[1];
    }

    $Bookmarks = all_bookmarks('torrent');
    foreach ($Details as $Detail) {
        list($torrentID, $GroupID, $GroupName, $NewCategoryID, $TorrentTags,
            $Snatched, $Seeders, $Leechers, $Data, $Size, $UploaderID, $UploaderName,,, $IsAnon, $Image) = $Detail;
        // highlight every other row
        $Rank++;
        $row = ($Rank % 2 ? 'b' : 'a');

        $Review = get_last_review($GroupID);

        $TagList = [];

        $PrimaryTag = '';
        if ($TorrentTags!='') {
            $TorrentTags=explode(' ', $TorrentTags);
            foreach ($TorrentTags as $TagKey => $TagName) {
                $TagName = str_replace('_', '.', $TagName);
                $TagList[]='<a href="/torrents.php?taglist='.$TagName.'">'.$TagName.'</a>';
            }
            $PrimaryTag = $TorrentTags[0];
            $TagList = implode(' ', $TagList);
            $TorrentTags='<br /><div class="tags">'.$TagList.'</div>';
        }

        $Icons = torrent_icons($Detail, $torrentID, $Review, in_array($GroupID, $Bookmarks));

        $IsMarkedForDeletion = $Review['Status'] == 'Warned' || $Review['Status'] == 'Pending';
        // print row
?>
    <tr class="torrent <?=($IsMarkedForDeletion?'redbar':"row$row")?>">
        <td style="padding:8px;text-align:center;"><strong><?=$Rank?></strong></td>
        <td class="center cats_col">
                    <?php  $CatImg = 'static/common/caticons/'.$newCategories[$NewCategoryID]['image']; ?>
                    <div title="<?=$newCategories[$NewCategoryID]['tag']?>"><img src="<?=$CatImg?>" /></div>
                </td>
        <td>
 <?php
                if ($activeUser['HideFloat'] ?? false) {?>
                    <?=$Icons?> <a href="/torrents.php?id=<?=$GroupID?>"><?=$GroupName?></a>
<?php               } else {
                    $Overlay = get_overlay_html($GroupName, anon_username($UploaderName, $IsAnon), $Image, $Seeders, $Leechers, $Size, $Snatched);
                    ?>
                    <script>
                        var overlay<?=$GroupID?> = <?=json_encode($Overlay)?>
                    </script>
                    <?=$Icons?>
                    <a href="/torrents.php?id=<?=$GroupID?>" onmouseover="return overlib(overlay<?=$GroupID?>, FULLHTML);" onmouseout="return nd();"><?=$GroupName?></a>
<?php               }  ?>

    <?php  if (($activeUser['HideTagsInLists'] ?? 0) !== 1) { ?>
            <?=$TorrentTags?>
    <?php  } ?>
        </td>
        <td class="top10 nobr"><?=get_size($Data)?></td>
        <td class="top10"><?=get_size($Size)?></td>
        <td class="top10"><?=number_format((double) $Snatched)?></td>
        <td class="top10"><?=number_format((double) $Seeders)?></td>
        <td class="top10"><?=number_format((double) $Leechers)?></td>
        <td class="top10"><?=number_format($Seeders+$Leechers)?></td>
        <td class="top10"><?=torrent_username($UploaderID, $IsAnon)?></td>
    </tr>
<?php
    }
?>
    </table><br />
<?php
}
