<?php
if (!check_perms('site_torrents_notify')) { error(403); }

include(SERVER_ROOT . '/common/functions.php');
include(SERVER_ROOT . '/sections/bookmarks/functions.php');

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $OrderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $OrderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('Title', 'Files', 'Time', 'Size', 'Snatched', 'Seeders', 'Leechers'))) {
    $OrderBy = 'Time';
} else {
    $OrderBy = $_GET['order_by'];
}


$Bookmarks = all_bookmarks('torrent');

define('NOTIFICATIONS_PER_PAGE', 50);
list($Page,$Limit) = page_limit(NOTIFICATIONS_PER_PAGE);

$TokenTorrents = $Cache->get_value('users_tokens_'.$UserID);
if (empty($TokenTorrents)) {
    $DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$UserID");
    $TokenTorrents = $DB->to_array('TorrentID');
    $Cache->cache_value('users_tokens_'.$UserID, $TokenTorrents);
}

$Results = $DB->query("SELECT SQL_CALC_FOUND_ROWS
        t.ID,
        g.ID,
        g.Image,
        g.Name as Title,
        g.NewCategoryID,
        g.TagList,
        t.Size as Size,
        t.FileCount as Files,
        t.Snatched as Snatched,
        t.Seeders as Seeders,
        t.Leechers as Leechers,
        t.Time as Time,
        t.FreeTorrent,
        t.double_seed,
        t.Anonymous,
        u.Username,
        tln.TorrentID AS LogInDB,
        unt.UnRead,
        unt.FilterID,
        unf.Label
        FROM users_notify_torrents AS unt
        JOIN torrents AS t ON t.ID=unt.TorrentID
	JOIN users_main AS u ON u.ID = t.UserID
        JOIN torrents_group AS g ON g.ID = t.GroupID
        LEFT JOIN users_notify_filters AS unf ON unf.ID=unt.FilterID
        LEFT JOIN torrents_logs_new AS tln ON tln.TorrentID=t.ID
        WHERE unt.UserID='$LoggedUser[ID]'
        GROUP BY t.ID
        ORDER BY $OrderBy $OrderWay LIMIT $Limit");
$DB->query('SELECT FOUND_ROWS()');
list($TorrentCount) = $DB->next_record();

//Clear before header but after query so as to not have the alert bar on this page load
$DB->query("UPDATE users_notify_torrents SET UnRead='0' WHERE UserID=".$LoggedUser['ID']);
$Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
show_header('My notifications','notifications,overlib');

$DB->set_query_id($Results);

$Pages=get_pages($Page,$TorrentCount,NOTIFICATIONS_PER_PAGE,9);
?>
<div class="thin">
    <h2>Notifications</h2>
    <div  class="linkbox">
            [<a href="user.php?action=notify" title="Add or edit notification filters">Add/Edit my notification filters</a>]
    </div>
    <div class="linkbox">
          <?=$Pages?>
    </div>
    <div class="head">Latest notifications <a href="torrents.php?action=notify_clear&amp;auth=<?=$LoggedUser['AuthKey']?>">(clear all)</a> <a href="javascript:SuperGroupClear()">(clear all selected)</a> </div>
<?php
    $NumNotices = $DB->record_count();
    if ($NumNotices==0) { ?>
    <div class="box pad center">
           <strong>   No new notifications!  </strong>
    </div>
<?php  } else {
    $FilterGroups = array();
    while ($Result = $DB->next_record()) {
        if (!$Result['FilterID']) {
            $Result['FilterID'] = 0;
        }
        if (!isset($FilterGroups[$Result['FilterID']])) {
            $FilterGroups[$Result['FilterID']] = array();
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
        Matches for filter <?=$FilterResults['FilterLabel']?> (<a href="torrents.php?action=notify_cleargroup&amp;filterid=<?=$ID?>&amp;auth=<?=$LoggedUser['AuthKey']?>">Clear</a>) <a href="javascript:GroupClear($('#notificationform_<?=$ID?>').raw())">(clear selected)</a></h3>
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
                <td class="sign"><a href="<?=header_link('Snatches') ?>"><img src="static/styles/<?=$LoggedUser['StyleName']?>/images/snatched.png" alt="Snatches" title="Snatches" /></a></td>
                <td class="sign"><a href="<?=header_link('Seeders') ?>"><img src="static/styles/<?=$LoggedUser['StyleName']?>/images/seeders.png" alt="Seeders" title="Seeders" /></a></td>
                <td class="sign"><a href="<?=header_link('Leechers') ?>"><img src="static/styles/<?=$LoggedUser['StyleName']?>/images/leechers.png" alt="Leechers" title="Leechers" /></a></td>
          </tr>
<?php
        unset($FilterResults['FilterLabel']);
        foreach ($FilterResults as $Result) {
            list($TorrentID, $GroupID, $Image, $GroupName, $GroupCategoryID, $TorrentTags, $Size, $FileCount,
                $Snatched, $Seeders,
                $Leechers, $NotificationTime, $FreeTorrent, $DoubleSeed, $IsAnon, $Username, $LogInDB, $UnRead, $FilterLabel, $FilterLabel) = $Result;

            $Review = get_last_review($GroupID);

            $DisplayName = '<a href="torrents.php?id='.$GroupID.'" onmouseover="return overlib(overlay'.$GroupID.', FULLHTML);" onmouseout="return nd();">'.$GroupName.'</a>'; // &amp;torrentid='.$TorrentID.'

            $TagLinks=array();
            if ($TorrentTags!='') {
                $TorrentTags=explode(' ',$TorrentTags);
                foreach ($TorrentTags as $TagKey => $TagName) {
                    $TagName = str_replace('_','.',$TagName);
                    $TagLinks[]='<a href="torrents.php?taglist='.$TagName.'">'.$TagName.'</a>';
                }
                $TagLinks = implode(', ', $TagLinks);
                $TorrentTags='<br /><div class="tags">'.$TagLinks.'</div>';
            }

            $IsBookmarked = in_array($TorrentID, $Bookmarks);
            $Icons = torrent_icons(array('FreeTorrent'=>$FreeTorrent,'double_seed'=>$DoubleSeed), $TorrentID, $Review, $IsBookmarked);

            $IsMarkedForDeletion = $Review['Status'] == 'Warned' || $Review['Status'] == 'Pending';
        // print row
          $row = $row == 'a' ? 'b' : 'a';
?>
          <tr class="torrent <?=($IsMarkedForDeletion?'redbar':"row$row")?>" id="torrent<?=$TorrentID?>">
                <td style="text-align: center"><input type="checkbox" value="<?=$TorrentID?>" id="clear_<?=$TorrentID?>" /></td>
                <td class="center cats_cols">
                <div title="<?=$NewCategories[$GroupCategoryID]['tag']?>"><img src="<?='static/common/caticons/'.$NewCategories[$GroupCategoryID]['image']?>" /></div>
                </td>
                <td>
<?php               $TorrentUsername = anon_username($Username, $IsAnon);
                    if (!$LoggedUser['HideFloat']) {
                        $Overlay = get_overlay_html($GroupName, $TorrentUsername, $Image, $Seeders, $Leechers, $Size, $Snatched); ?>
                        <script>
                            var overlay<?=$GroupID?> = <?=json_encode($Overlay)?>
                        </script>
<?php               } ?>
                    <?=$Icons?>
                    <?=$DisplayName?>
                    <?php  if ($UnRead) { echo '<strong>New!</strong>'; } ?>

                <?php  if ($LoggedUser['HideTagsInLists'] !== 1) { ?>
                      <?=$TorrentTags?>
                <?php  } ?>
                </td>
                <td class="center"><?=number_format($FileCount)?></td>
                <td class="nobr"><?=time_diff($NotificationTime, 1)?></td>
                <td class="nobr"><?=get_size($Size)?></td>
                <td><?=number_format($Snatched)?></td>
                <td<?=($Seeders==0)?' class="r00"':''?>><?=number_format($Seeders)?></td>
                <td><?=number_format($Leechers)?></td>
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
