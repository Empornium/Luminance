<?php

$Includes = ['all', 'own', 'other'];
$Orders = ['TagID', 'TagName', 'AdderID', 'AddedBy', 'IsAdder', 'IsOwner', 'TorrentID', 'TorrentName', 'UploaderID', 'Uploader', 'Votes', 'Way'];
$Ways = ['asc'=>'Ascending', 'desc'=>'Descending'];

if (isset($_GET['userid'])) $userID = $_GET['userid'];
else $userID = $activeUser['ID'];

if (!is_integer_string($userID)) { error(0); }

$User = user_info($userID);
$Perms = get_permissions($User['PermissionID']);
$UserClass = $Perms['Class'];

if (!check_force_anon($userID) || !check_paranoia('tags', $User['Paranoia'], $UserClass, $userID)) { error(PARANOIA_MSG); }

if (!empty($_GET['include']) && in_array($_GET['include'], $Includes)) {
    $Include = $_GET['include'];
} else {
    $Include = 'all';
}

$andWhereParams = [];
if ($Include == 'own') {
    $AND_WHERE = " AND t.UserID = ?";
    $andWhereParams[] = $userID;
} elseif ($Include == 'other') {
    $AND_WHERE = " AND t.UserID != ?";
    $andWhereParams[] = $userID;
} else $AND_WHERE = '';

if (!empty($_GET['search'])) {
    $AND_WHERE .= " AND tags.Name = ?";
    $search = str_replace(['%', '_'], ['\%', '\_'], $_GET['search']);
    $andWhereParams[] = $search;
}


if (isset($activeUser['TorrentsPerPage'])) {
    $torrentsPerPage = $activeUser['TorrentsPerPage'];
} else {
    $torrentsPerPage = TORRENTS_PER_PAGE;
}

list($page, $limit) = page_limit($torrentsPerPage);

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], $Orders)) {
    $orderBy = 'TagName';
} else {
    $orderBy = $_GET['order_by'];;
}

if (!empty($_GET['order_way']) && array_key_exists($_GET['order_way'], $Ways)) {
    $orderWay = $_GET['order_way'];
} else {
    $orderWay = 'desc';
}

if ($_GET['type']=='votes') {
    $TitleEnd = "'s voted on tags";
} else {
    $TitleEnd = "'s added tags";
}

show_header("{$User['Username']}{$TitleEnd}");

?>

<div class="thin">
    <h2><?=format_username($userID).$TitleEnd;?></h2>
    <div class="linkbox">

        [<a href="/userhistory.php?action=tag_history&amp;type=added&amp;<?=get_url(['action', 'type', 'page'])?>" title="View <?=$User['Username']?>'s added tags">Tags added</a>]
  &nbsp;[<a href="/userhistory.php?action=tag_history&amp;type=votes&amp;<?=get_url(['action', 'type', 'page'])?>" title="View <?=$User['Username']?>'s added tags">Tags voted on</a>]
    </div>

    <div class="head">Options</div>
    <table class="noborder">
        <tr>
            <td class="right" title="show all tags">show all tags
                <input type="radio" name="include" onchange="location.href='/userhistory.php?action=tag_history&amp;include=all&amp;<?=get_url(['action', 'include', 'page'])?>'" value="all" <?php if ($Include=='all')echo'checked="checked"';?> />
            </td>
            <td class="center" title="show only tags on other users torrents">show only tags on others torrents
                <input type="radio" name="include" onchange="location.href='/userhistory.php?action=tag_history&amp;include=other&amp;<?=get_url(['action', 'include', 'page'])?>'" value="other" <?php if ($Include=='other')echo'checked="checked"';?>/>
            </td>
            <td class="center" title="show only tags on <?=$User['Username']?>'s torrents">show only tags on own torrents
                <input type="radio" name="include" onchange="location.href='/userhistory.php?action=tag_history&amp;include=own&amp;<?=get_url(['action', 'include', 'page'])?>'" value="own" <?php if ($Include=='own')echo'checked="checked"';?>/>
            </td>
        </tr>
    </table>
    <br/>
    <div class="head">Filters</div>
    <div class="box">
        <form action="userhistory.php">
            <input type="hidden" name="action" value="tag_history">
            <input type="hidden" name="userid" value="<?=$userID?>">
            <input type="hidden" name="type" value="<?=display_str($_GET['type'])?>">
            <input type="hidden" name="include" value="<?=$Include?>">
            <table>
                <tr>
                    <td class="label nobr">Search tag:</td>
                    <td>
                        <input type="text" name="search" value="<?=display_str($_GET['search'] ?? '')?>">
                        <input type="submit" value="Filter">
                    </td>
                </tr>
            </table>
        </form>
    </div>
    <br/>
<?php
    $params = [$userID, $userID, $userID];
    if ($_GET['type']=='votes') {

        $query = "SELECT SQL_CALC_FOUND_ROWS
                            tags.ID AS TagID, tags.Name AS TagName, tt.UserID As AdderID, um1.Username AS AddedBy,
                            IF(tt.UserID = ?, 1, 0) AS IsAdder, IF(t.UserID = ?, 1, 0) AS IsOwner, t.Anonymous,
                            tg.ID AS TorrentID, tg.Name AS TorrentName, um2.ID AS UploaderID, um2.Username AS Uploader,
                            ( tt.PositiveVotes- tt.NegativeVotes) AS Votes, ttv.Way
                      FROM torrents_tags_votes AS ttv
                      JOIN tags ON ttv.TagID=tags.ID
                      JOIN torrents_tags AS tt ON tt.TagID=ttv.TagID AND tt.GroupID=ttv.GroupID
                      JOIN torrents AS t ON t.GroupID=ttv.GroupID
                      JOIN torrents_group AS tg ON t.GroupID=tg.ID
                 LEFT JOIN users AS um1 ON um1.ID=tt.UserID
                 LEFT JOIN users AS um2 ON um2.ID=t.UserID
                     WHERE ttv.UserID = ?
                        {$AND_WHERE}
                        ORDER BY {$orderBy} {$orderWay}
                        LIMIT {$limit}";
    } else {
        $query = "SELECT SQL_CALC_FOUND_ROWS
                            tags.ID AS TagID, tags.Name AS TagName, tt.UserID As AdderID, um1.Username AS AddedBy,
                            IF(tt.UserID = ?, 1, 0) AS IsAdder, IF(t.UserID = ?, 1, 0) AS IsOwner, t.Anonymous,
                            tg.ID AS TorrentID, tg.Name AS TorrentName, um2.ID AS UploaderID, um2.Username AS Uploader,
                            ( tt.PositiveVotes- tt.NegativeVotes) AS Votes, ttv.Way
                      FROM torrents_tags AS tt
                      JOIN tags ON tt.TagID=tags.ID
                      JOIN torrents AS t ON t.GroupID=tt.GroupID
                      JOIN torrents_group AS tg ON tt.GroupID=tg.ID
                 LEFT JOIN users AS um1 ON um1.ID=tt.UserID
                 LEFT JOIN users AS um2 ON um2.ID=t.UserID
                 LEFT JOIN torrents_tags_votes AS ttv ON ttv.TagID=tags.ID AND ttv.GroupID=tt.GroupID AND ttv.UserID = ?
                     WHERE tt.UserID = ?
                        {$AND_WHERE}
                        ORDER BY {$orderBy} {$orderWay}
                        LIMIT {$limit}";
        $params[] = $userID;
    }
    $params = array_merge($params, $andWhereParams);

    $Tags = $master->db->rawQuery($query, $params)->fetchAll(\PDO::FETCH_NUM);

    $TagCount = $master->db->foundRows();

    $pages = get_pages($page, $TagCount, $torrentsPerPage,8);

?>
    <div class="linkbox pager"><?= $pages ?></div>
    <div class="head"><?=$TagCount?> tags</div>
    <table>
        <tr class="colhead">
            <td>Tag <span style="margin-left:60px">
                    <a href="<?=header_link('TagID')?>" title="sort by tag ID">(id)</a> <a href="<?=header_link('TagName', 'asc')?>" title="sort by Tag Name">(az)</a>
                </span>
            </td>
            <td class="right"><a href="<?=header_link('IsAdder')?>" title="sort by is tag adder">adder</a></td>
            <td><a href="<?=header_link('AddedBy')?>" title="sort by added by">Added By</a></td>
            <td><a href="<?=header_link('Way')?>" title="sort by vote direction">Way</a></td>
            <td><a href="<?=header_link('Votes')?>" title="sort by number of votes">Votes</a></td>
            <td>Torrent <span style="margin-left:60px">
                    <a href="<?=header_link('TorrentID')?>" title="sort by torrent ID">(id)</a> <a href="<?=header_link('TorrentName', 'asc')?>" title="sort by torrent name">(az)</a>
                </span>
            </td>
            <td class="right"><a href="<?=header_link('IsOwner')?>" title="sort by is torrent owner">owner</a></td>
            <td><a href="<?=header_link('Uploader')?>" title="sort by uploader">Uploader</a></td>
        </tr>
<?php
    foreach ($Tags as $TagInfo) {
        list($TagID, $TagName, $AdderID, $AddedBy, $IsAdder, $IsOwner, $IsAnon, $GroupID, $TorrentName, $UploaderID, $Uploader, $Votes, $Way) = $TagInfo;
        $row = ($row ?? 'b') == 'a' ? 'b' : 'a';
?>
        <tr class="row<?=$row?>">
            <td><a href="/torrents.php?taglist=<?=$TagName?>"><?=$TagName?></a></td>
            <td class="right"><?php if ($IsAdder && ($AdderID!=$UploaderID || !is_anon($IsAnon)))echo'<img src="/static/common/symbols/tick.png" title="tag was added by '.$AddedBy.'" />'; ?></td>
            <td><?= torrent_username($AdderID, $IsAnon && $AdderID===$UploaderID) ?></td>
            <td>
<?php               if (!$Way) echo '<span class="blue">-</span>';
                elseif ($Way=='up') echo '<span class="green">Up</span>';
                else echo '<span class="red">Down</span>';?>
            </td>
            <td><?=$Votes?></td>
            <td><a href="/torrents.php?id=<?=$GroupID?>" title="<?=$TorrentName?>"><?=cut_string($TorrentName,50)?></a></td>
            <td class="right"><?php
                if ($IsOwner && !is_anon($IsAnon))echo'<img src="/static/common/symbols/tick.png" title="torrent was uploaded by '.anon_username($Uploader, $IsAnon).'" />';?></td>

            <td><?= torrent_username($UploaderID, $IsAnon) ?></td>
        </tr>

<?php   }    ?>
    </table>
    <div class="linkbox pager"><?= $pages ?></div>

</div>
<?php
show_footer();
