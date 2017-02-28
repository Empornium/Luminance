<?php
define('COLLAGES_PER_PAGE', 25);

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
include_once(SERVER_ROOT.'/common/functions.php');
$Text = new TEXT;

list($Page,$Limit) = page_limit(COLLAGES_PER_PAGE);

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('Name', 'NumTorrents', 'StartDate', 'LastDate', 'Username'  ))) {
    $_GET['order_by'] = 'LastDate';
    $OrderBy = 'LastDate';
} else {
    $OrderBy = $_GET['order_by'];
}

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $OrderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $OrderWay = 'desc';
}

// Are we searching in bodies, or just names?
if (!empty($_GET['type'])) {
    $Type = $_GET['type'];
    if (!in_array($Type, array('c.name', 'description'))) {
        $Type = 'c.name';
    }
} else {
    $Type = 'c.name';
}

if (!empty($_GET['search'])) {
    // What are we looking for? Let's make sure it isn't dangerous.
    $Search = strtr(db_string(trim($_GET['search'])),$SpecialChars);
    // Break search string down into individual words
    $Words = explode(' ', $Search);
}

if (!empty($_GET['tags'])) {
    $Tags = explode(',',db_string(trim($_GET['tags'])));
    foreach ($Tags as $ID=>$Tag) {
        $Tags[$ID] = sanitize_tag($Tag);
    }
}

if (!empty($_GET['cats'])) {
    $Categories = $_GET['cats'];
    foreach ($Categories as $Cat=>$Accept) {
        if (empty($CollageCats[$Cat]) || !$Accept) { unset($Categories[$Cat]); }
    }
    $Categories = array_keys($Categories);
} else {
    $Categories = array(1,2,3,4,5,6);
}

$BookmarkView = !empty($_GET['bookmarks']);

if ($BookmarkView) {
    $BookmarkJoin = 'INNER JOIN bookmarks_collages AS bc ON c.ID = bc.CollageID';
} else {
    $BookmarkJoin = '';
}

$BaseSQL = $SQL = "SELECT SQL_CALC_FOUND_ROWS
    c.ID,
    c.Name,
    Count(ct.GroupID) AS NumTorrents,
    c.TagList,
    c.CategoryID,
    c.UserID,
    um.Username ,
    Min(ct.AddedOn) AS StartDate,
    Max(ct.AddedOn) AS LastDate
    FROM collages AS c
    LEFT JOIN collages_torrents AS ct ON ct.CollageID=c.ID
    $BookmarkJoin
    LEFT JOIN users_main AS um ON um.ID=c.UserID
    WHERE Deleted = '0'";

if ($BookmarkView) {
    $SQL .= " AND bc.UserID = '" . $LoggedUser['ID'] . "'";
}

if (!empty($Search)) {
    $SQL .= " AND $Type LIKE '%";
    $SQL .= implode("%' AND $Type LIKE '%", $Words);
    $SQL .= "%'";
}

if (!empty($Tags)) {
    $SQL.= " AND c.TagList LIKE '%";
    $SQL .= implode("%' AND c.TagList LIKE '%", $Tags);
    $SQL .= "%'";
}

if (!empty($_GET['userid'])) {
    $UserID = $_GET['userid'];
    if (!is_number($UserID)) {
        error(404);
    }
    $User = user_info($UserID);
    $Perms = get_permissions($User['PermissionID']);
    $UserClass = $Perms['Class'];

    $UserLink = '<a href="user.php?id='.$UserID.'">'.$User['Username'].'</a>';
    if (!empty($_GET['contrib'])) {
        if (!check_force_anon($UserID) || !check_paranoia('collagecontribs', $User['Paranoia'], $UserClass, $UserID)) { error(PARANOIA_MSG); }
        $DB->query("SELECT DISTINCT CollageID FROM collages_torrents WHERE UserID = $UserID");
        $CollageIDs = $DB->collect('CollageID');
        if (empty($CollageIDs)) {
            $SQL .= " AND 0";
        } else {
            $SQL .= " AND c.ID IN(".db_string(implode(',', $CollageIDs)).")";
        }
    } else {
        if (!check_paranoia('collages', $User['Paranoia'], $UserClass, $UserID)) { error(PARANOIA_MSG); }
        $SQL .= " AND c.UserID='".$_GET['userid']."'";
    }
    $Categories[] = 0;
}

if (!empty($Categories)) {
    $SQL.=" AND c.CategoryID IN(".db_string(implode(',',$Categories)).")";
}

if ($_GET['action'] == 'mine') {
    $SQL = $BaseSQL;
    $SQL .= " AND c.UserID='".$LoggedUser['ID']."' AND c.CategoryID=0";
}

$SQL.=" GROUP BY c.ID
        ORDER BY $OrderBy $OrderWay
        LIMIT $Limit ";
$DB->query($SQL);
$Collages = $DB->to_array();
$DB->query("SELECT FOUND_ROWS()");
list($NumResults) = $DB->next_record();

show_header(($BookmarkView)?'Your bookmarked collages':'Browse collages');
?>
<div class="thin">
    <h2>Collages</h2>
<?php if (!$BookmarkView) { ?>
        <div class="head">Search</div>
        <form action="" method="get">
            <input type="hidden" name="action" value="search" />
            <table cellpadding="6" cellspacing="1" border="0" width="100%">
                <tr>
                    <td class="label"><strong>Search for:</strong></td>
                    <td colspan="1">
                        <input type="text" name="search" size="70" value="<?=(!empty($_GET['search']) ? display_str($_GET['search']) : '')?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Tags:</strong></td>
                    <td colspan="1">
                        <input type="text" name="tags" size="70" value="<?=(!empty($_GET['tags']) ? display_str($_GET['tags']) : '')?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Categories:</strong></td>
                    <td colspan="1">
<?php foreach ($CollageCats as $ID=>$Cat) { ?>
                        <input type="checkbox" value="1" name="cats[<?=$ID?>]" id="cats_<?=$ID?>" <?php if (in_array($ID, $Categories)) { echo ' checked="checked"'; }?>>
                        <label for="cats_<?=$ID?>"><?=$Cat?></label>
<?php } ?>
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Search in:</strong></td>
                    <td>
                        <input type="radio" name="type" value="c.name" <?php if ($Type == 'c.name') { echo 'checked="checked" '; }?>/> Names
                        <input type="radio" name="type" value="description" <?php if ($Type == 'description') { echo 'checked="checked" '; }?>/> Descriptions
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="center">
                        <input type="submit" value="Search" />
                    </td>
                </tr>
            </table>
        </form>

<?php }  ?>
    <div class="linkbox">
<?php if (!$BookmarkView) {
if (check_perms('site_collages_create')) { ?>
        <a href="collages.php?action=new">[New collage]</a>
<?php		} else { ?>
            <em> <a href="articles.php?topic=collagehelp">You must be a Good Perv with a ratio of at least 1.05 to be able to create a collage.</a></em><br/>
<?php          }
if (check_perms('site_collages_personal')) {
    $DB->query("SELECT ID FROM collages WHERE UserID='$LoggedUser[ID]' AND CategoryID='0' AND Deleted='0'");
    $CollageCount = $DB->record_count();

    if ($CollageCount == 1) {
        list($CollageID) = $DB->next_record();
?>
        <a href="collages.php?id=<?=$CollageID?>">[My personal collage]</a>
<?php	} elseif ($CollageCount > 1) { ?>
        <a href="collages.php?action=mine">[My personal collages]</a>
<?php	}
}
if (check_perms('site_collages_subscribe')) { ?>
        <a href="userhistory.php?action=subscribed_collages">[My Subscribed Collages]</a>
<?php }
if (check_perms('site_collages_recover')) { ?>
        <a href="collages.php?action=recover">[Recover collage]</a>
<?php
}
if (check_perms('site_collages_create') || check_perms('site_collages_personal') || check_perms('site_collages_subscribe') || check_perms('site_collages_recover')) {
?>
        <br />
<?php
}
?>
        <a href="collages.php?userid=<?=$LoggedUser['ID']?>">[Collages you started]</a>
        <a href="collages.php?userid=<?=$LoggedUser['ID']?>&amp;contrib=1">[Collages you've contributed to]</a>
<?php } else { ?>
        <a href="bookmarks.php?type=torrents">[Torrents]</a>
        <a href="bookmarks.php?type=collages">[Collages]</a>
        <a href="bookmarks.php?type=requests">[Requests]</a>
<?php }

$Pages=get_pages($Page,$NumResults,COLLAGES_PER_PAGE,9);
echo "<br />$Pages";
?>
    </div>
<?php if ($BookmarkView) { ?>
    <div class="head">Your bookmarked collages</div>
<?php } else { ?>
    <div class="head">Browse collages<?=(!empty($UserLink) ? (isset($CollageIDs) ? ' with contributions by '.$UserLink : ' started by '.$UserLink) : '')?></div>
<?php } ?>
<?php if (count($Collages) == 0) { ?>
<div class="box pad" align="center">
<?php	if ($BookmarkView) { ?>
    <h2>You have not bookmarked any collages.</h2>
<?php	} else { ?>
    <h2>Your search did not match anything.</h2>
    <p>Make sure all names are spelled correctly, or try making your search less specific.</p>
<?php	} ?>
</div><!--box-->
</div><!--content-->
<?php show_footer(); die();
} ?>
<table width="100%">
    <tr class="colhead">
        <td class="center"></td>
        <td><a href="<?=header_link('Name') ?>">Collage</a></td>
        <td class="center"><a href="<?=header_link('NumTorrents') ?>">Torrents</a></td>
        <td><a href="<?=header_link('StartDate') ?>">Started</a></td>
        <td class="nobr"><a href="<?=header_link('LastDate') ?>">Last Added</a></td>
        <td class="center"><a href="<?=header_link('Username') ?>">Author</a></td>
    </tr>
<?php
$Row = 'a'; // For the pretty colours
foreach ($Collages as $Collage) {
    list($ID, $Name, $NumTorrents, $TagList, $CategoryID, $UserID, $Username, $StartDate, $LastDate) = $Collage;
    $Row = ($Row == 'a') ? 'b' : 'a';
    $TagList = explode(' ', $TagList);
    $Tags = array();
    foreach ($TagList as $Tag) {
        $Tags[]='<a href="collages.php?action=search&amp;tags='.$Tag.'">'.$Tag.'</a>';
    }
    $Tags = implode(', ', $Tags);

    //Print results
?>
    <tr class="row<?=$Row?> <?=($BookmarkView)?'bookmark_'.$ID:''?>">
        <td class="center">
            <a href="collages.php?action=search&amp;cats[<?=(int) $CategoryID?>]=1"><img src="static/common/collageicons/<?=$CollageIcons[(int) $CategoryID]?>" alt="<?=$CollageCats[(int) $CategoryID]?>" title="<?=$CollageCats[(int) $CategoryID]?>" /></a>
        </td>
        <td>
            <a href="collages.php?id=<?=$ID?>"><?=$Name?></a>
<?php	if ($BookmarkView) { ?>
            <span style="float:right">
                <a href="#" onclick="Unbookmark('collage', <?=$ID?>,'');return false;">[Remove bookmark]</a>
            </span>
<?php	} ?>
                        <?php if ($LoggedUser['HideTagsInLists'] !== 1) { ?>
            <div class="tags">
                <?=$Tags?>
            </div>
                        <?php } ?>
        </td>
        <td class="center"><?=(int) $NumTorrents?></td>
        <td class=""><?=time_diff($StartDate)?></td>
        <td class=""><?=time_diff($LastDate)?></td>
        <td class="center"><?=format_username($UserID, $Username)?></td>
    </tr>
<?php } ?>
</table>
    <div class="linkbox"><?=$Pages?></div>
</div>
<?php
show_footer();
