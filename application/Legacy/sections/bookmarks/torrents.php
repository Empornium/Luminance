<?php
set_time_limit(0);
//~~~~~~~~~~~ Main bookmarks page ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//

if (!empty($_GET['userid'])) {
    if (!check_perms('users_override_paranoia')) {
        error(403);
    }
    $UserID = $_GET['userid'];
    if (!is_number($UserID)) { error(404); }
    $DB->query("SELECT Username FROM users_main WHERE ID='$UserID'");
    list($Username) = $DB->next_record();
} else {
    $UserID = $LoggedUser['ID'];
}

$Sneaky = ($UserID != $LoggedUser['ID']);

$Type = $_GET['type'];

$DB->query("SELECT t.Name AS name, COUNT(tt.GroupID) count
              FROM bookmarks_torrents AS bt
              JOIN torrents_tags AS tt ON bt.GroupID=tt.GroupID
              JOIN tags AS t ON t.ID=tt.TagID
             WHERE bt.UserID='$UserID'
          GROUP BY tt.TagID
          ORDER BY count DESC
          LIMIT 5");
$Tags =  $DB->to_array('name', MYSQLI_ASSOC);


$DB->query("SELECT SUM(t.Size) AS Size
              FROM bookmarks_torrents AS bt
              JOIN torrents AS t ON bt.GroupID=t.GroupID
             WHERE bt.UserID='$UserID'");
list($Size) =  $DB->next_record();

if (isset($LoggedUser['TorrentsPerPage'])) {
    $TorrentsPerPage = $LoggedUser['TorrentsPerPage'];
} else {
    $TorrentsPerPage = TORRENTS_PER_PAGE;
}

$DB->query("SELECT COUNT(*) FROM bookmarks_torrents WHERE UserID=$UserID");
list($NumGroups) = $DB->next_record();

$PageLimit = ceil((float)$NumGroups/(float)$TorrentsPerPage);

if (!empty($_GET['page']) && is_number($_GET['page'])) {
    $Page = $_GET['page'];
    if ($Page > $PageLimit) $Page=$PageLimit;
    $Limit = ($Page-1)*$TorrentsPerPage.', '.$TorrentsPerPage;
} else {
    $Page = 1;
    $Limit = $TorrentsPerPage;
}

$Pages=get_pages($Page, $NumGroups, $TorrentsPerPage, 8, '#torrent_table');

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $OrderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $OrderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('Title', 'Size', 'UploadDate', 'BookmarkDate', 'Snatched', 'Seeders', 'Leechers'))) {
    $OrderBy = 'BookmarkDate';
} else {
    $OrderBy = $_GET['order_by'];
}

//$Data = $Cache->get_value('bookmars_torrent_'.$UserID.'_page_'.$Page);

//if ($Data) {
//    $Data = unserialize($Data);
//    list($K, list($TorrentList, $CollageDataList)) = each($Data);
//} else {
    // Build the data for the collage and the torrent list
    $DB->query("SELECT
        bt.GroupID,
        tg.Image,
        tg.NewCategoryID,
        bt.Time as BookmarkDate,
        tg.Time as UploadDate,
        tg.Name as Title,
        t.Snatched as Snatched,
        t.Seeders as Seeders,
        t.Leechers as Leechers,
        t.Size as Size
        FROM bookmarks_torrents AS bt
        JOIN torrents_group AS tg ON tg.ID=bt.GroupID
        JOIN torrents AS t ON t.ID=bt.GroupID
        WHERE bt.UserID='$UserID'
        ORDER BY $OrderBy $OrderWay
        LIMIT $Limit");

    $GroupIDs = $DB->collect('GroupID');
    $CollageDataList=$DB->to_array('GroupID', MYSQLI_ASSOC);
    if (count($GroupIDs)>0) {
        $TorrentList = get_groups($GroupIDs);
        $TorrentList = $TorrentList['matches'];
    } else {
        $TorrentList = array();
    }
//}

$TokenTorrents = $Cache->get_value('users_tokens_'.$LoggedUser['ID']);
if (empty($TokenTorrents)) {
    $DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$LoggedUser[ID]");
    $TokenTorrents = $DB->to_array('TorrentID');
    $Cache->cache_value('users_tokens_'.$LoggedUser['ID'], $TokenTorrents);
}

$PageTitle = ($Sneaky)?"$Username's bookmarked torrents":'Your bookmarked torrents';

// Loop through the result set, building up $Collage and $TorrentTable
// Then we print them.
$Collage = array();
$TorrentTable = '';

$CollageDataList = array_sort($CollageDataList, $OrderBy, $OrderWay);

foreach ($CollageDataList as $GroupID=>$Group) {
    list($GroupID, $GroupName, $TagList, $Torrents) = array_values($TorrentList[$GroupID]);
    list($GroupID2, $Image, $NewCategoryID, $BookmarkDate, $UploadDate) = array_values($CollageDataList[$GroupID]);

    $TagList = explode(' ',str_replace('_','.',$TagList));

    $TorrentTags = array();
    $numtags=0;
    foreach ($TagList as $Tag) {
        if ($numtags++>=$LoggedUser['MaxTags'])  break;
        $TorrentTags[]='<a href="/torrents.php?taglist='.$Tag.'">'.$Tag.'</a>';
    }
    $PrimaryTag = $TagList[0];
    $TorrentTags = implode(' ', $TorrentTags);
    $TorrentTags='<br /><div class="tags">'.$TorrentTags.'</div>';

    $DisplayName = '<a href="/torrents.php?id='.$GroupID.'" title="View Torrent">'.display_str($GroupName).'</a>';

    // Start an output buffer, so we can store this output in $TorrentTable
    ob_start();

        list($TorrentID, $Torrent) = each($Torrents);

        $Review = get_last_review($GroupID);

        $DisplayName = '<a href="/torrents.php?id='.$GroupID.'" title="View Torrent">'.display_str($GroupName).'</a>';

        if (!empty($Torrent['FreeTorrent'])) {
                $DisplayName .=' <strong>/ Freeleech!</strong>';
        } elseif (!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['FreeLeech'] > sqltime()) {
                $DisplayName .= ' <strong>/ Personal Freeleech!</strong>';
        } elseif (!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['DoubleSeed'] > sqltime()) {
                $DisplayName .= ' <strong>/ Personal Doubleseed!</strong>';
        }
    if ($Torrent['ReportCount'] > 0) {
            $Title = "This torrent has ".$Torrent['ReportCount']." active ".($Torrent['ReportCount'] > 1 ?'reports' : 'report');
            $DisplayName .= ' /<span class="reported" title="'.$Title.'"> Reported</span>';
    }

        $AddExtra = torrent_icons($Torrent, $TorrentID, $Review, true );
        $row = ($row == 'a'? 'b' : 'a');
        $IsMarkedForDeletion = $Review['Status'] == 'Warned' || $Review['Status'] == 'Pending';
?>
    <tr class="torrent <?=($IsMarkedForDeletion?'redbar':"row$row")?>" id="group_<?=$GroupID?>">
        <td class="center">
                    <?php $CatImg = 'static/common/caticons/' . $NewCategories[$NewCategoryID]['image']; ?>
                <img src="<?= $CatImg ?>" alt="<?= $NewCategories[$NewCategoryID]['tag'] ?>" title="<?= $NewCategories[$NewCategoryID]['tag'] ?>"/>
        </td>
        <td>
                <strong><?=$DisplayName?></strong>
                <?php if ($LoggedUser['HideTagsInLists'] !== 1) {
                        echo $TorrentTags;
                   } ?>
<?php if (!$Sneaky) { ?>
                <span style="float:left;"><a href="#group_<?=$GroupID?>" onclick="Unbookmark('torrent', <?=$GroupID?>, '');return false;">Remove Bookmark</a></span>
<?php } ?>
	</td>
	<td>
		<?=$AddExtra?>
		<br></br>
		<span style="float:right;"><?=time_diff($BookmarkDate);?></span>
	</td>
	<td>
		<span style="float:right;"><?=time_diff($UploadDate);?></span>
	</td>
        <td class="nobr"><?=get_size($Torrent['Size'])?></td>
        <td><?=number_format($Torrent['Snatched'])?></td>
        <td<?=($Torrent['Seeders']==0)?' class="r00"':''?>><?=number_format($Torrent['Seeders'])?></td>
        <td><?=number_format($Torrent['Leechers'])?></td>
    </tr>
<?php
    $TorrentTable.=ob_get_clean();

    // Album art

    ob_start();

    $DisplayName = display_str($GroupName);
?>
        <li class="image_group_<?=$GroupID?>">
            <a href="/torrents.php?id=<?=$GroupID?>">
<?php	if ($Image) { ?>
                <img src="<?=display_str($Image)?>" alt="<?=$DisplayName?>" title="<?=$DisplayName?>" width="117" />
<?php	} else { ?>
                <div style="width:107px;padding:5px"><?=$DisplayName?></div>
<?php	} ?>
            </a>
        </li>
<?php
    $Collage[]=ob_get_clean();

}

$CollagePages = array();
for ($i=0; $i < $NumGroups/$TorrentsPerPage; $i++) {
    $Groups = array_slice($Collage, $i*$TorrentsPerPage, $TorrentsPerPage);
    $CollagePage = '';
    foreach ($Groups as $Group) {
        $CollagePage .= $Group;
    }
    $CollagePages[] = $CollagePage;
}

show_header($PageTitle, 'browse,collage');
?>
<div class="thin">
    <h2>Bookmarks &nbsp;<img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/star16.png" alt="star" title="Bookmarked torrents" /></h2>
    <div class="linkbox">
        <a href="/bookmarks.php?type=torrents">[Torrents]</a>
        <a href="/bookmarks.php?type=collages">[Collages]</a>
        <a href="/bookmarks.php?type=requests">[Requests]</a>
<?php if (count($TorrentList) > 0) { ?>
        <br /><br />
        <a href="/bookmarks.php?action=remove_snatched&amp;auth=<?=$LoggedUser['AuthKey']?>" onclick="return confirm('Are you sure you want to remove the bookmarks for all items you\'ve snatched?');">[Remove Snatched]</a>
<?php } ?>
    </div>
<?php if (count($TorrentList) == 0) { ?>
    <div class="head"><?php if (!$Sneaky) { ?><a href="/feeds.php?feed=torrents_bookmarks_t_<?=$LoggedUser['torrent_pass']?>&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;name=<?=urlencode(SITE_NAME.': Bookmarked Torrents')?>"><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>&nbsp;<?php } ?><?=$Title?></div>

    <div class="box pad" align="center">
        <h2>You have not bookmarked any torrents.</h2>
    </div>
</div><!--content-->
<?php
    show_footer();
    die();
} ?>
    <div class="sidebar">
<?php
if (check_perms('site_zip_downloader') && (check_perms('torrents_download_override') || $master->options->EnableDownloads)) {
?>
        <div class="head"><strong>Collector</strong></div>
        <div class="box">
            <div class="pad">
                <form action="bookmarks.php" method="post">
                <input type="hidden" name="action" value="download" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="userid" value="<?=$UserID?>" />
                <select name="preference" style="width:210px">
                    <option value="0">Download All</option>
                    <option value="1">At least 1 seeder</option>
                    <option value="2">5 or more seeders</option>
                </select>
                <input type="submit" style="width:210px" value="Download" />
                </form>
            </div>
        </div>
<?php } ?>
        <div class="head"><strong>Stats</strong></div>
        <div class="box">
            <ul class="stats nobullet">
                <li>Torrents: <?=$NumGroups?></li>
                <li>Total Size: <?=get_size($Size)?></li>
            </ul>
        </div>
        <div class="head"><strong>Top tags</strong></div>
        <div class="box">
            <div class="pad">
                <ol style="padding-left:5px;">
<?php
foreach ($Tags as $TagName => $Tag) { ?>
                    <li><a href="/torrents.php?taglist=<?=$TagName?>"><?=$TagName?></a> (<?=$Tag['count']?>)</li>
<?php
}
?>
                </ol>
            </div>
        </div>
    </div>
    <div class="main_column">
<?php
if ($TorrentsPerPage != 0) { ?>
        <div class="head" id="coverhead"><strong>Cover Art</strong></div>
        <div id="coverart" class="box">
            <ul class="collage_images" id="collage_page0">
<?php
    $Page1 = array_slice($Collage, 0, $TorrentsPerPage);
    foreach ($Page1 as $Group) {
        echo $Group;
    }
}?>
            </ul>
        </div>

    </div>
    <br />
    <div class="clear"></div><br />
    <div class="linkbox"><?=$Pages?></div>
    <table class="torrent_table" id="torrent_table">
        <tr class="head">
            <td><!-- Category --></td>
            <td width="50%"><a href="/<?=header_link('Title', 'asc') ?>">Torrents</a></td>
            <td width="20%"><a href="/<?=header_link('BookmarkDate') ?>" style="float:right">Bookmarked</a></td>
            <td><a href="/<?=header_link('UploadDate') ?>">Uploaded</a></td>
            <td><a href="/<?=header_link('Size') ?>">Size</a></td>
            <td class="sign"><a href="/<?=header_link('Snatched') ?>"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /></a></td>
            <td class="sign"><a href="/<?=header_link('Seeders') ?>"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /></a></td>
            <td class="sign"><a href="/<?=header_link('Leechers') ?>"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /></a></td>
        </tr>
        <?=$TorrentTable?>
    </table>
    <div class="linkbox"><?=$Pages?></div>
</div>

<?php
show_footer();
$Cache->cache_value('bookmarks_torrent_'.$UserID.'_page_'.$Page, serialize(array(array($TorrentList, $CollageDataList))), 3600);
