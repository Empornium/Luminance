<?php
if (!check_perms('site_torrents_notify')) { error(403); }

# Ugly CSS hack for now
$document = 'notifications';

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $orderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $orderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], ['Title', 'Files', 'Time', 'Size', 'Snatches', 'Seeders', 'Leechers'])) {
    $orderBy = 'Time';
} else {
    $orderBy = $_GET['order_by'];
}


$Bookmarks = all_bookmarks('torrent');

define('NOTIFICATIONS_PER_PAGE', 50);
list($Page, $Limit) = page_limit(NOTIFICATIONS_PER_PAGE);

$Results = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            t.ID,
            g.ID AS GroupID,
            g.Image,
            g.Name as Title,
            g.NewCategoryID,
            g.TagList,
            t.Size as Size,
            t.FileCount as Files,
            t.Snatched as Snatches,
            t.Seeders as Seeders,
            t.Leechers as Leechers,
            t.Time as Time,
            t.FreeTorrent,
            t.DoubleTorrent,
            t.Anonymous,
            u.Username,
            unt.UnRead,
            unt.FilterID,
            unf.Label
       FROM users_notify_torrents AS unt
       JOIN torrents AS t ON t.ID = unt.TorrentID
       JOIN users AS u ON u.ID = t.UserID
       JOIN torrents_group AS g ON g.ID = t.GroupID
  LEFT JOIN users_notify_filters AS unf ON unf.ID = unt.FilterID
      WHERE unt.UserID = ?
   GROUP BY t.ID
   ORDER BY {$orderBy} {$orderWay} LIMIT {$Limit}",
    [$activeUser['ID']]
)->fetchAll(\PDO::FETCH_ASSOC);
$TorrentCount = $master->db->foundRows();

//Clear before header but after query so as to not have the alert bar on this page load
$master->db->rawQuery(
    "UPDATE users_notify_torrents
        SET UnRead = '0'
      WHERE UserID = ?",
    [$activeUser['ID']]
);
$master->cache->deleteValue('notifications_new_'.$activeUser['ID']);
show_header('My notifications', 'notifications,overlib');

$Pages = get_pages($Page, $TorrentCount, NOTIFICATIONS_PER_PAGE, 9);
?>
<div class="thin">
    <h2>Notifications</h2>
    <div  class="linkbox">
            [<a href="/user.php?action=notify" title="Add or edit notification filters">Add/Edit my notification filters</a>]
    </div>
    <div class="linkbox">
          <?=$Pages?>
    </div>
    <div class="head">Latest notifications <a href="/torrents.php?action=notify_clear&amp;auth=<?=$activeUser['AuthKey']?>">(clear all)</a> <a href="javascript:SuperGroupClear()">(clear all selected)</a> </div>
<?php
    $NumNotices = $TorrentCount;
    if ($NumNotices==0) { ?>
    <div class="box pad center">
           <strong>   No new notifications!  </strong>
    </div>
<?php  } else {
    $FilterGroups = [];
    foreach ($Results as $Result) {
        if (!$Result['FilterID']) {
            $Result['FilterID'] = 0;
        }
        if (!isset($FilterGroups[$Result['FilterID']])) {
            $FilterGroups[$Result['FilterID']] = [];
            $FilterGroups[$Result['FilterID']]['FilterLabel'] = ($Result['FilterID'] && !empty($Result['Label']) ? $Result['Label'] : 'unknown filter'.($Result['FilterID']?' ['.$Result['FilterID'].']':''));
        }
        array_push($FilterGroups[$Result['FilterID']], $Result);
    }
    unset($Result);
?>
    <div class="box pad center">
           <strong> <?=$NumNotices?> notifications in <?=count($FilterGroups)?> filters </strong>
    </div>
<?php
    foreach ($FilterGroups as $ID => $FilterResults) {
?>
    <br/>
    <div class="head">
        Matches for filter <?=$FilterResults['FilterLabel']?> (<a href="/torrents.php?action=notify_cleargroup&amp;filterid=<?=$ID?>&amp;auth=<?=$activeUser['AuthKey']?>">Clear</a>) <a href="javascript:GroupClear($('#notificationform_<?=$ID?>').raw())">(clear selected)</a></h3>
    </div>
    <table class="torrent_table">
    <form id="notificationform_<?=$ID?>">
          <tr class="colhead">
                <td style="text-align: center"><input type="checkbox" name="toggle" onClick="ToggleBoxes(this.form, this.checked)" /></td>
                <td class="small cats_col"></td>
                <td style="width:100%;"><a href="<?=header_link('Title', 'asc') ?>">Torrent</a></td>
                <td><a href="<?=header_link('Files') ?>">Files</a></td>
                <td><a href="<?=header_link('Time') ?>">Time</a></td>
                <td><a href="<?=header_link('Size') ?>">Size</a></td>
                <td class="sign"><a href="<?=header_link('Snatches') ?>"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/snatched.png" alt="Snatches" title="Snatches" /></a></td>
                <td class="sign"><a href="<?=header_link('Seeders') ?>"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/seeders.png" alt="Seeders" title="Seeders" /></a></td>
                <td class="sign"><a href="<?=header_link('Leechers') ?>"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/leechers.png" alt="Leechers" title="Leechers" /></a></td>
          </tr>
<?php
        unset($FilterResults['FilterLabel']);
        foreach ($FilterResults as $torrent) {
            $Review = get_last_review($torrent['GroupID']);

            $DisplayName = '<a href="/torrents.php?id='.$torrent['GroupID'].'" onmouseover="return overlib(overlay'.$torrent['GroupID'].', FULLHTML);" onmouseout="return nd();">'.$torrent['Title'].'</a>'; // &amp;torrentid='.$torrentID.'

            $TagLinks = [];
            if (!empty($torrent['TagList'])) {
                $TagList = explode(' ', $torrent['TagList']);
                foreach ($TagList as $TagKey => $TagName) {
                    $TagName = str_replace('_', '.', $TagName);
                    $TagLinks[]='<a href="/torrents.php?taglist='.$TagName.'">'.$TagName.'</a>';
                }
                $TagLinks = implode(', ', $TagLinks);
                $torrent['TagList']='<br /><div class="tags">'.$TagLinks.'</div>';
            }

            $IsBookmarked = in_array($torrent['GroupID'], $Bookmarks);
            $Icons = torrent_icons(['GroupID' => $torrent['GroupID'], 'FreeTorrent'=>$torrent['FreeTorrent'], 'DoubleTorrent'=>$torrent['DoubleTorrent']], $torrent['ID'], $Review, $IsBookmarked);

            $IsMarkedForDeletion = $Review['Status'] == 'Warned' || $Review['Status'] == 'Pending';
        // print row
          $row = ($row ?? 'a') == 'a' ? 'b' : 'a';
?>
          <tr class="torrent <?=($IsMarkedForDeletion?'redbar':"row$row")?>" id="torrent<?=$torrent['ID']?>">
                <td style="text-align: center"><input type="checkbox" value="<?=$torrent['ID']?>" id="clear_<?=$torrent['ID']?>" /></td>
                <td class="center cats_cols">
                <div title="<?=$newCategories[$torrent['NewCategoryID']]['tag']?>"><img src="<?='static/common/caticons/'.$newCategories[$torrent['NewCategoryID']]['image']?>" /></div>
                </td>
                <td>
<?php               $TorrentUsername = anon_username($torrent['Username'], $torrent['Anonymous']);
                    if (!$activeUser['HideFloat'] ?? false) {
                        $Overlay = get_overlay_html($torrent['Title'], $TorrentUsername, $torrent['Image'], $torrent['Seeders'], $torrent['Leechers'], $torrent['Size'], $torrent['Snatches']); ?>
                        <script>
                            var overlay<?=$torrent['GroupID']?> = <?=json_encode($Overlay)?>
                        </script>
<?php               } ?>
                    <?=$Icons?>
                    <?=$DisplayName?>
                    <?php  if ($torrent['UnRead']) { echo '<strong>New!</strong>'; } ?>

                <?php  if (($activeUser['HideTagsInLists'] ?? 0) !== 1) { ?>
                      <?=$torrent['TagList']?>
                <?php  } ?>
                </td>
                <td class="center"><?=number_format($torrent['Files'])?></td>
                <td class="nobr"><?=time_diff($torrent['Time'], 1)?></td>
                <td class="nobr"><?=get_size($torrent['Size'])?></td>
                <td><?=number_format($torrent['Snatches'])?></td>
                <td<?=($torrent['Seeders']==0)?' class="r00"':''?>><?=number_format($torrent['Seeders'])?></td>
                <td><?=number_format($torrent['Leechers'])?></td>
          </tr>
<?php
        }
?>
    </form>
    </table>
<?php
    }
}

?>
    <div class="linkbox">
          <?=$Pages?>
    </div>
</div>

<?php
show_footer();
