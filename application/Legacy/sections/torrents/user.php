<?php


$Type = $_GET['type'] ?? '';
$userID = $_GET['userid'] ?? 0;

if (!check_force_anon($userID)) {
    // then you dont get to see any torrents for any uploader!
     error(403);
}

$Orders = ['Time', 'Name', 'Seeders', 'Leechers', 'Snatched', 'Size'];
$Ways = ['asc'=>'Ascending', 'desc'=>'Descending'];


if (!is_integer_string($userID)) { error(0); }

if (isset($activeUser['TorrentsPerPage'])) {
    $torrentsPerPage = $activeUser['TorrentsPerPage'];
} else {
    $torrentsPerPage = TORRENTS_PER_PAGE;
}

list($page, $limit) = page_limit($torrentsPerPage);
if (!empty($_GET['order_by']) && in_array($_GET['order_by'], $Orders)) {
    $orderBy = $_GET['order_by'];
} else {
    $orderBy = 'Time';
}

if (!empty($_GET['order_way']) && array_key_exists($_GET['order_way'], $Ways)) {
    $orderWay = $_GET['order_way'];
} else {
    $orderWay = 'desc';
}

$SearchWhere = [];
$params = [];

if (!empty($_GET['categories'])) {
    $Cats = [];
    foreach (array_keys($_GET['categories']) as $Cat) {
        if (!is_integer_string($Cat)) {
            error(0);
        }
        $Cats[]="tg.NewCategoryID = ?";
        $params[] = $Cat;
    }
    $SearchWhere[]='('.implode(' OR ', $Cats).')';
}

if (!empty($_GET['tags'])) {
    $Tags = preg_replace('/\s+/', ' ', trim($_GET['tags']));
    $Tags = explode(' ', $Tags);
    $TagList = [];

    foreach ($Tags as $Tag) {
        $Tag = strtolower(trim($Tag)) ;
        $Tag = get_tag_synonym($Tag, false);
        $Tag = trim(str_replace('.', '_', $Tag));

        if (!empty($Tag))
            $TagList[]="tg.TagList LIKE ?"; // Match complete tags only
            $params[] = "%{$Tag}%";
    }
    if (!empty($TagList)) {
        $SearchWhere[]='('.implode(' AND ', $TagList).')';
    }
}

$SearchWhere = implode(' AND ', $SearchWhere);
if (!empty($SearchWhere)) {
    $SearchWhere = ' AND '.$SearchWhere;
}

$User = user_info($userID);
$Perms = get_permissions($User['PermissionID']);
$UserClass = $Perms['Class'];
$ExtraWhere = '';

switch ($Type) {
    case 'snatched':
        if (!check_paranoia('snatched', $User['Paranoia'], $UserClass, $userID)) { error(PARANOIA_MSG); }
        $time = 'xs.tstamp';
        $UserField = 'xs.uid';
        $ExtraWhere = '';
        $From = "xbt_snatched AS xs JOIN torrents AS t ON t.ID=xs.fid";
        break;
    case 'seeding':
        if (!check_paranoia('seeding', $User['Paranoia'], $UserClass, $userID)) { error(PARANOIA_MSG); }
        $time = '(unix_timestamp(now()) - xfu.timespent)';
        $UserField = 'xfu.uid';
        $ExtraWhere = 'AND xfu.active=1 AND xfu.Remaining=0';
        $From = "xbt_files_users AS xfu JOIN torrents AS t ON t.ID=xfu.fid";
        break;
    case 'leeching':
        if (!check_paranoia('leeching', $User['Paranoia'], $UserClass, $userID)) { error(PARANOIA_MSG); }
        $time = '(unix_timestamp(now()) - xfu.timespent)';
        $UserField = 'xfu.uid';
        $ExtraWhere = 'AND xfu.active=1 AND xfu.Remaining>0';
        $From = "xbt_files_users AS xfu JOIN torrents AS t ON t.ID=xfu.fid";
        break;
    case 'uploaded':
        if ((empty($_GET['filter'])) && !check_paranoia('uploads', $User['Paranoia'], $UserClass, $userID)) { error(PARANOIA_MSG); }
        $time = 'unix_timestamp(t.Time)';
        $UserField = 't.UserID';
        $From = "torrents AS t";
        break;
    case 'downloaded':
        if (!check_paranoia('grabbed', $User['Paranoia'], $UserClass, $userID)) { error(PARANOIA_MSG); }
        $time = 'unix_timestamp(ud.Time)';
        $UserField = 'ud.UserID';
        $ExtraWhere = '';
        $From = "users_downloads AS ud JOIN torrents AS t ON t.ID=ud.TorrentID";
        break;
    default:
        error(404);
}

if (!empty($_GET['filter'])) {
    if ($_GET['filter'] == "uniquegroup") {
        $GroupBy = "tg.ID";
    }
}

if (empty($GroupBy)) {
    $GroupBy = "t.ID";
}

// if anon ...
// dont show anon uploaded torrents in preview mode either
if (($userID!=$activeUser['ID'] && !check_perms('users_view_anon_uploaders')) or ($Preview ?? false)) {
    $ExtraWhere .= " AND t.Anonymous='0'";
}

if (!empty($_GET['search']) && trim($_GET['search']) != '') {
    $Words = array_unique(explode(' ', $_GET['search']));
    if (!empty($Words)) {
        $nameConditions = implode(' AND ', array_fill(0, count($Words), 'Name LIKE ?'));
        $SearchWhere .= " AND {$nameConditions}";

        $params = array_merge($params, array_map(function($w) { return "%{$w}%"; }, $Words));
    }
}

$SQL_Selection = "FROM {$From}
                  JOIN torrents_group AS tg ON tg.ID=t.GroupID
                  WHERE {$UserField} = ? {$ExtraWhere} {$SearchWhere}";
array_unshift($params, $userID);

$SQL = "SELECT SQL_CALC_FOUND_ROWS t.ID, t.GroupID, t.ID AS TorrentID, {$time} AS Time, tg.NewCategoryID, tg.Image
    {$SQL_Selection}
    GROUP BY {$GroupBy}
    ORDER BY {$orderBy} {$orderWay}
    LIMIT {$limit}";

$TorrentsInfo = $master->db->rawQuery($SQL, $params)->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
$GroupIDs = array_column($TorrentsInfo, 'GroupID');

$TorrentCount = $master->db->foundRows();

$Results = get_groups($GroupIDs);

$Action = display_str($Type);
$User = user_info($userID);

$INLINE = $INLINE ?? false;
$Preview = $Preview ?? false;

if (!$INLINE) show_header($User['Username'].'\'s '.$Action.' torrents', 'overlib');

$pages=get_pages($page, $TorrentCount, $torrentsPerPage,8, '#torrents');

$total_uploaded_size = $master->db->rawQuery("SELECT SUM(Size) {$SQL_Selection}", $params)->fetchColumn();

?>
<?php  if (!$INLINE) {  ?>
<div class="thin">
    <h2><a href="/user.php?id=<?=$userID?>"><?=$User['Username']?></a><?='\'s '.$Action.' torrents'?></h2>

    <div class="head">Search</div>
        <form action="" method="get">
                 <table>
                <tr>
                    <td class="label"><strong>Search for:</strong></td>
                    <td>
                        <input type="hidden" name="type" value="<?=$Type?>" />
                        <input type="hidden" name="userid" value="<?=$userID?>" />
                        <input type="text" name="search" size="60" value="<?php form('search')?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Tags:</strong></td>
                    <td>
                        <textarea id="taginput" name="tags" class="inputtext" title="Search Tags, supports full boolean search" value="<?php form('tags')?>" /><?= str_replace('_', '.', form('taglist', true)) ?></textarea>
                        <label class="checkbox_label" title="Toggle autocomplete mode on or off.&#10;When turned off, you can access your browser's form history.">
                            <input id="autocomplete_toggle" type="checkbox" name="autocomplete_toggle" checked="checked" />
                            Autocomplete tags
                        </label>
                    </td>
                </tr>

                <tr>
                    <td class="label"><strong>Order by</strong></td>
                    <td>
                        <select name="order_by">
<?php  foreach ($Orders as $OrderText) { ?>
                            <option value="<?=$OrderText?>" <?php selected('order_by', $OrderText)?>><?=$OrderText?></option>
<?php  }?>
                        </select>&nbsp;
                        <select name="order_way">
<?php  foreach ($Ways as $WayKey=>$WayText) { ?>
                            <option value="<?=$WayKey?>" <?php selected('order_way', $WayKey)?>><?=$WayText?></option>
<?php  }?>
                        </select>
                    </td>
                </tr>
            </table>

            <table class="cat_list">
<?php
$x=0;
$row = 'a';
reset($newCategories);
foreach ($newCategories as $Cat) {
    if ($x%7==0) {
        if ($x > 0) {
?>
                </tr>
<?php 		} ?>
                <tr class="row<?=$row?>">
<?php
            $row = ($row == 'a') ? 'b' : 'a';
    }
    $x++;
?>
                    <td>
                        <input type="checkbox" name="categories[<?=($Cat['id'])?>]" id="cat_<?=($Cat['id'])?>" value="1" <?php  if (isset($_GET['filter_cat'][$Cat['id']])) { ?>checked="checked"<?php  } ?>/>
                        <label for="cat_<?=($Cat['id'])?>"><a href="/torrents.php?filter_cat[<?=$Cat['id']?>]=1"><?= $Cat['name'] ?></a></label>
                    </td>
<?php
}
?>
                    <td colspan="<?=7-($x%7)?>"></td>
                </tr>
            </table>
            <div class="submit">
                <input type="submit" value="Search torrents" />
            </div>
        </form>

<?php
} // end if !$INLINE
?>

<?php
    if (count($GroupIDs) == 0) { ?>
        <br/>
        <div class="head clear"><span style="float:left;">Torrents</span><span style="float:right;"><a id="submitbutton" href="#" onclick="Toggle_view('torrents'); this.innerHTML=(this.innerHTML=='(Hide)'?'(Show)':'(Hide)'); return false;">(Hide)</a></span></div>
    <div class="box pad center">
    <div id="torrentsdiv">
              <h2>No torrents found</h2>
        </div>
    </div>
<?php 	} else { ?>
    <div class="linkbox pager"><?= $pages ?></div>
    <div class="head clear"><span style="float:left;"><?=str_plural('Torrent', $TorrentCount).' - Total Size: '.get_size($total_uploaded_size)?></span><span><a id="submitbutton" href="#" onclick="Toggle_view('torrents'); this.innerHTML=(this.innerHTML=='(Hide)'?'(Show)':'(Hide)'); return false;">(Hide)</a></span></div>
    <table id="torrentsdiv" class="torrent_table">
        <tr class="colhead">
            <td></td>
            <td><a href="<?=header_link('Name', 'asc', '#torrents')?>">Torrent</a></td>
                  <td class="center"><span title="Number of Files">F</span></td>
                  <td class="center"><span title="Number of Comments">c</span></td>
            <td><a href="<?=header_link('Time', 'desc', '#torrents')?>">Time</a></td>
            <td><a href="<?=header_link('Size', 'desc', '#torrents')?>">Size</a></td>
            <td class="sign">
                <a href="<?=header_link('Snatched', 'desc', '#torrents')?>"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/snatched.png" alt="Snatches" title="Snatches" /></a>
            </td>
            <td class="sign">
                <a href="<?=header_link('Seeders', 'desc', '#torrents')?>"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/seeders.png" alt="Seeders" title="Seeders" /></a>
            </td>
            <td class="sign">
                <a href="<?=header_link('Leechers', 'desc', '#torrents')?>"><img src="/static/styles/<?=$activeUser['StyleName']?>/images/leechers.png" alt="Leechers" title="Leechers" /></a>
            </td>
        </tr>
<?php
    $Results = $Results['matches'];
    $row = 'a';
    $Bookmarks = all_bookmarks('torrent');
    foreach ($TorrentsInfo as $torrentID => $Info) {
        list($GroupID,, $time, $NewCategoryID, $Image) = array_values($Info);

        $Torrent = $Results[$GroupID]['Torrents'][$torrentID];

        $Review = get_last_review($GroupID);

        $Results[$GroupID]['TagList'] = explode(' ',str_replace('_', '.', $Results[$GroupID]['TagList']));

        $TorrentTags = [];
        $numtags=0;
        foreach ($Results[$GroupID]['TagList'] as $Tag) {
            if ($numtags++>=$activeUser['MaxTags'])  break;
            $TorrentTags[]='<a href="/torrents.php?type='.$Action.'&amp;userid='.$userID.'&amp;tags='.$Tag.'">'.$Tag.'</a>';
        }
        $TorrentTags = implode(' ', $TorrentTags);

        $ReportInfo = '';
        if ($Torrent['ReportCount'] > 0) {
            $Title = "This torrent has ".$Torrent['ReportCount']." active ".($Torrent['ReportCount'] > 1 ?'reports' : 'report');
            $ReportInfo = ' /<span class="reported" title="'.$Title.'"> Reported</span>';
        }

        $Icons = torrent_icons($Torrent, $torrentID, $Review, in_array($GroupID, $Bookmarks));

        $NumComments = get_num_comments($GroupID);

        $row = $row==='b'?'a':'b';
        $IsMarkedForDeletion = $Review['Status'] == 'Warned' || $Review['Status'] == 'Pending';
?>
        <tr class="torrent <?=($IsMarkedForDeletion?'redbar':"row$row")?>">
            <td class="center cats_col">
                <div title="<?=$newCategories[$NewCategoryID]['tag']?>"><img src="<?='static/common/caticons/'.$newCategories[$NewCategoryID]['image']?>" /></div>
            </td>
            <td>
                <?php
                if ($activeUser['HideFloat'] ?? false) {?>
                    <?=$Icons?> <a href="/torrents.php?id=<?=$GroupID?>"><?=display_str($Results[$GroupID]['Name']).$ReportInfo?></a>
<?php               } else {
                    $Overlay = get_overlay_html($Results[$GroupID]['Name'], anon_username($Torrent['Username'], $Torrent['Anonymous']), $Image, $Torrent['Seeders'], $Torrent['Leechers'], $Torrent['Size'], $Torrent['Snatched']);
                    ?>
                    <script>
                        var overlay<?=$GroupID?> = <?=json_encode($Overlay)?>
                    </script>
                    <?=$Icons?>
                    <a href="/torrents.php?id=<?=$GroupID?>" onmouseover="return overlib(overlay<?=$GroupID?>, FULLHTML);" onmouseout="return nd();"><?=display_str($Results[$GroupID]['Name']).$ReportInfo?></a>
<?php               }  ?>
                <br />
          <?php  if (($activeUser['HideTagsInLists'] ?? 0) !== 1) { ?>
                <div class="tags">
                    <?=$TorrentTags?>
                </div>
          <?php  } ?>
            </td>
            <td class="center"><?=number_format($Torrent['FileCount'])?></td>
            <td class="center"><?=number_format($NumComments)?></td>
            <td class="nobr"><?=time_diff($time,1)?></td>
            <td class="nobr"><?=get_size($Torrent['Size'])?></td>
            <td><?=number_format($Torrent['Snatched'])?></td>
            <td<?=($Torrent['Seeders']==0)?' class="r00"':''?>><?=number_format($Torrent['Seeders'])?></td>
            <td><?=number_format($Torrent['Leechers'])?></td>
        </tr>
<?php
        }

    }
?>
    </table>
    <div class="linkbox pager"><?= $pages ?></div>
<?php
if (!$INLINE) {
?>
</div>
<?php
    show_footer();
}
