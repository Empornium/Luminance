<?php
set_time_limit(0);
//~~~~~~~~~~~ Main bookmarks page ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//

function compare($X, $Y)
{
    return($Y['count'] - $X['count']);
}

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

$Data = $Cache->get_value('bookmarks_torrent_'.$UserID.'_full');

if ($Data) {
    $Data = unserialize($Data);
    list($K, list($TorrentList, $CollageDataList)) = each($Data);
} else {
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
        ORDER BY $OrderBy $OrderWay");

    $GroupIDs = $DB->collect('GroupID');
    $CollageDataList=$DB->to_array('GroupID', MYSQLI_ASSOC);
    if (count($GroupIDs)>0) {
        $TorrentList = get_groups($GroupIDs);
        $TorrentList = $TorrentList['matches'];
    } else {
        $TorrentList = array();
    }
}

$TokenTorrents = $Cache->get_value('users_tokens_'.$LoggedUser['ID']);
if (empty($TokenTorrents)) {
    $DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$LoggedUser[ID]");
    $TokenTorrents = $DB->to_array('TorrentID');
    $Cache->cache_value('users_tokens_'.$LoggedUser['ID'], $TokenTorrents);
}

$Title = ($Sneaky)?"$Username's bookmarked torrents":'Your bookmarked torrents';

// Loop through the result set, building up $Collage and $TorrentTable
// Then we print them.
$Collage = array();
$TorrentTable = '';

$NumGroups = 0;
$Tags = array();

$CollageDataList = array_sort($CollageDataList, $OrderBy, $OrderWay);

foreach ($CollageDataList as $GroupID=>$Group) {
    list($GroupID, $GroupName, $TagList, $Torrents) = array_values($TorrentList[$GroupID]);
    list($GroupID2, $Image, $NewCategoryID, $BookmarkDate, $UploadDate) = array_values($CollageDataList[$GroupID]);

    // Handle stats and stuff
    $NumGroups++;

    $TagList = explode(' ',str_replace('_','.',$TagList));

    $TorrentTags = array();
    $numtags=0;
    foreach ($TagList as $Tag) {
        if ($numtags++>=$LoggedUser['MaxTags'])  break;
        if (!isset($Tags[$Tag])) {
            $Tags[$Tag] = array('name'=>$Tag, 'count'=>1);
        } else {
            $Tags[$Tag]['count']++;
        }
        $TorrentTags[]='<a href="torrents.php?taglist='.$Tag.'">'.$Tag.'</a>';
    }
    $PrimaryTag = $TagList[0];
    $TorrentTags = implode(' ', $TorrentTags);
    $TorrentTags='<br /><div class="tags">'.$TorrentTags.'</div>';

    $DisplayName = '<a href="torrents.php?id='.$GroupID.'" title="View Torrent">'.$GroupName.'</a>';

    // Start an output buffer, so we can store this output in $TorrentTable
    ob_start();

        list($TorrentID, $Torrent) = each($Torrents);

        $Review = get_last_review($GroupID);

        $DisplayName = '<a href="torrents.php?id='.$GroupID.'" title="View Torrent">'.$GroupName.'</a>';

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

    $DisplayName = $GroupName;
?>
        <li class="image_group_<?=$GroupID?>">
            <a href="#group_<?=$GroupID?>" class="bookmark_<?=$GroupID?>">
<?php	if ($Image) {
        if (check_perms('site_proxy_images')) {
            $Image = '//'.SITE_URL.'/image.php?i='.urlencode($Image);
        }
?>
                <img src="<?=$Image?>" alt="<?=$DisplayName?>" title="<?=$DisplayName?>" width="117" />
<?php	} else { ?>
                <div style="width:107px;padding:5px"><?=$DisplayName?></div>
<?php	} ?>
            </a>
        </li>
<?php
    $Collage[]=ob_get_clean();

}

$CollageCovers = isset($LoggedUser['CollageCovers'])?$LoggedUser['CollageCovers']:25;
$CollagePages = array();
for ($i=0; $i < $NumGroups/$CollageCovers; $i++) {
    $Groups = array_slice($Collage, $i*$CollageCovers, $CollageCovers);
    $CollagePage = '';
    foreach ($Groups as $Group) {
        $CollagePage .= $Group;
    }
    $CollagePages[] = $CollagePage;
}

show_header($Title, 'browse,collage');
?>
<div class="thin">
    <h2>Bookmarks &nbsp;<img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/star16.png" alt="star" title="Bookmarked torrents" /></h2>
    <div class="linkbox">
        <a href="bookmarks.php?type=torrents">[Torrents]</a>
        <a href="bookmarks.php?type=collages">[Collages]</a>
        <a href="bookmarks.php?type=requests">[Requests]</a>
<?php if (count($TorrentList) > 0) { ?>
        <br /><br />
        <a href="bookmarks.php?action=remove_snatched&amp;auth=<?=$LoggedUser['AuthKey']?>" onclick="return confirm('Are you sure you want to remove the bookmarks for all items you\'ve snatched?');">[Remove Snatched]</a>
<?php } ?>
    </div>
<?php if (count($TorrentList) == 0) { ?>
    <div class="head"><?php if (!$Sneaky) { ?><a href="feeds.php?feed=torrents_bookmarks_t_<?=$LoggedUser['torrent_pass']?>&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;name=<?=urlencode(SITE_NAME.': Bookmarked Torrents')?>"><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>&nbsp;<?php } ?><?=$Title?></div>

    <div class="box pad" align="center">
        <h2>You have not bookmarked any torrents.</h2>
    </div>
</div><!--content-->
<?php
    show_footer();
    die();
} ?>
    <div class="sidebar">
        <div class="head"><strong>Stats</strong></div>
        <div class="box">
            <ul class="stats nobullet">
                <li>Torrents: <?=$NumGroups?></li>
            </ul>
        </div>
        <div class="head"><strong>Top tags</strong></div>
        <div class="box">
            <div class="pad">
                <ol style="padding-left:5px;">
<?php
uasort($Tags, 'compare');
$i = 0;
foreach ($Tags as $TagName => $Tag) {
    $i++;
    if ($i>5) { break; }
?>
                    <li><a href="torrents.php?taglist=<?=$TagName?>"><?=$TagName?></a> (<?=$Tag['count']?>)</li>
<?php
}
?>
                </ol>
            </div>
        </div>
    </div>
    <div class="main_column">
<?php
if ($CollageCovers != 0) { ?>
        <div class="head" id="coverhead"><strong>Cover Art</strong></div>
        <div id="coverart" class="box">
            <ul class="collage_images" id="collage_page0">
<?php
    $Page1 = array_slice($Collage, 0, $CollageCovers);
    foreach ($Page1 as $Group) {
        echo $Group;
}?>
            </ul>
        </div>
<?php	if ($NumGroups > $CollageCovers) { ?>
        <div class="linkbox pager" style="clear: left;" id="pageslinksdiv">
            <span id="firstpage" class="invisible"><a href="#" class="pageslink" onClick="collageShow.page(0, this); return false;">&lt;&lt; First</a> | </span>
            <span id="prevpage" class="invisible"><a href="#" id="prevpage"  class="pageslink" onClick="collageShow.prevPage(); return false;">&lt; Prev</a> | </span>
<?php		for ($i=0; $i < $NumGroups/$CollageCovers; $i++) { ?>
            <span id="pagelink<?=$i?>" class="<?=(($i>4)?'hidden':'')?><?=(($i==0)?' selected':'')?>"><a href="#" class="pageslink" onClick="collageShow.page(<?=$i?>, this); return false;"><?=$CollageCovers*$i+1?>-<?=min($NumGroups,$CollageCovers*($i+1))?></a><?=($i != ceil($NumGroups/$CollageCovers)-1)?' | ':''?></span>
<?php		} ?>
            <span id="nextbar" class="<?=($NumGroups/$CollageCovers > 5)?'hidden':''?>"> | </span>
            <span id="nextpage"><a href="#" class="pageslink" onClick="collageShow.nextPage(); return false;">Next &gt;</a></span>
            <span id="lastpage" class="<?=ceil($NumGroups/$CollageCovers)==2?'invisible':''?>"> | <a href="#" id="lastpage" class="pageslink" onClick="collageShow.page(<?=ceil($NumGroups/$CollageCovers)-1?>, this); return false;">Last &gt;&gt;</a></span>
        </div>
        <script type="text/javascript">
            collageShow.init(<?=json_encode($CollagePages)?>);
        </script>
<?php	}
} ?>
        </div>
        <br />
        <table class="torrent_table" id="torrent_table">
            <tr class="head">
                <td><!-- Category --></td>
                <td width="50%"><a href="<?=header_link('Title', 'asc') ?>">Torrents</a></td>
		<td width="20%"><a href="<?=header_link('BookmarkDate') ?>" style="float:right">Bookmarked</a></td>
		<td><a href="<?=header_link('UploadDate') ?>">Uploaded</a></td>
		<td><a href="<?=header_link('Size') ?>">Size</a></td>
                <td class="sign"><a href="<?=header_link('Snatched') ?>"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /></a></td>
                <td class="sign"><a href="<?=header_link('Seeders') ?>"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /></a></td>
                <td class="sign"><a href="<?=header_link('Leechers') ?>"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /></a></td>
            </tr>
<?=$TorrentTable?>
        </table>
    </div>

<?php
show_footer();
$Cache->cache_value('bookmarks_torrent_'.$UserID.'_full', serialize(array(array($TorrentList, $CollageDataList))), 3600);
