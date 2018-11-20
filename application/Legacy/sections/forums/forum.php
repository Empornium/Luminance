<?php
/**********|| Page to show individual forums || ********************************\

Things to expect in $_GET:
    ForumID: ID of the forum curently being browsed
    page:	The page the user's on.
    page = 1 is the same as no page

********************************************************************************/

//---------- Things to sort out before it can start printing/generating content

// Check for lame SQL injection attempts
$ForumID = $_GET['forumid'];
if (!is_number($ForumID)) {
    error(0);
}

if (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

list($Page,$Limit) = page_limit(TOPICS_PER_PAGE);

//---------- Get some data to start processing

// Caching anything beyond the first page of any given forum is just wasting ram
// users are more likely to search then to browse to page 2
if ($Page==1) {
    list($Forum,,,$Stickies) = $Cache->get_value('forums_'.$ForumID);
}
if (!isset($Forum) || !is_array($Forum)) {
    $DB->query("SELECT
        t.ID,
        t.Title,
        t.AuthorID,
        author.Username AS AuthorUsername,
        t.IsLocked,
        t.IsSticky,
        t.NumPosts,
        t.LastPostID,
        t.LastPostTime,
        t.LastPostAuthorID,
        last_author.Username AS LastPostUsername
        FROM forums_topics AS t
        LEFT JOIN users_main AS last_author ON last_author.ID = t.LastPostAuthorID
        LEFT JOIN users_main AS author ON author.ID = t.AuthorID
        WHERE t.ForumID = '$ForumID'
        ORDER BY t.IsSticky DESC, t.LastPostTime DESC
        LIMIT $Limit"); // Can be cached until someone makes a new post
    $Forum = $DB->to_array('ID', MYSQLI_ASSOC);
    if ($Page==1) {
        $DB->query("SELECT COUNT(ID) FROM forums_topics WHERE ForumID='$ForumID' AND IsSticky='1'");
        list($Stickies) = $DB->next_record();
        $Cache->cache_value('forums_'.$ForumID, array($Forum,'',0,$Stickies), 0);
    }
}

if (!isset($Forums[$ForumID])) {
    error(404);
}
// Make sure they're allowed to look at the page
if (!check_perms('site_moderate_forums')) {
    if (isset($LoggedUser['CustomForums'][$ForumID]) && $LoggedUser['CustomForums'][$ForumID] === 0) {
        error(403);
    }
}
if ($LoggedUser['CustomForums'][$ForumID] != 1 && $Forums[$ForumID]['MinClassRead'] > $LoggedUser['Class']) {
    error(403);
}

// Start printing
$forumName = display_str($Forums[$ForumID][Name]);
show_header(empty($LoggedUser['ShortTitles'])?"Forums > {$forumName}":$forumName);

?>
<div class="thin">
<?php print_latest_forum_topics(); ?>
    <div class="linkbox">
<?php   print_forum_links($ForumID, $LoggedUser, true); ?>
        <div id="searchforum" class="hidden">
            <div style="display: inline-block;">
                            <br />
                <div class="head">Search this forum</div>
                <form action="forums.php" method="get">
                    <table cellpadding="6" cellspacing="1" border="0" class="border">
                        <input type="hidden" name="action" value="search" />
                        <input type="hidden" name="forums[]" value="<?=$ForumID?>" />
                        <tr>
                            <td><strong>Search for:</strong></td><td><input type="text" id="searchbox" name="search" size="70" /></td>
                        </tr>
                        <tr>
                            <td><strong>Search in:</strong></td>
                            <td>
                                <input type="radio" name="type" id="type_title" value="title" checked="checked" />
                                <label for="type_title">Titles</label>
                                <input type="radio" name="type" id="type_body" value="body" />
                                <label for="type_body">Post bodies</label>
                            </td>
                        <tr>
                            <td><strong>Username:</strong></td><td><input type="text" id="username" name="user" size="70" /></td>
                        </tr>
                        <tr><td colspan="2" style="text-align: center"><input type="submit" name="submit" value="Search" /></td></tr>
                    </table>
                </form>
                <br />
            </div>
        </div>
    </div>
<?php if (check_perms('site_moderate_forums')) { ?>
    <div class="linkbox">
        <a href="/forums.php?action=edit_rules&amp;forumid=<?=$ForumID?>">Change specific rules</a>
    </div>
<?php } ?>
<?php if (!empty($Forums[$ForumID]['SpecificRules'])) { ?>
    <div class="head">
        Forum Specific Rules
    </div>
    <div class="box pad center">
<?php foreach ($Forums[$ForumID]['SpecificRules'] as $ThreadIDs) {
    $Thread = get_thread_info($ThreadIDs);
    if ($Thread === false) {
        error(404);
    }
?>
            &nbsp;&nbsp;[<a href="/forums.php?action=viewthread&amp;threadid=<?=$ThreadIDs?>"><?=$Thread['Title']?></a>]&nbsp;&nbsp;
<?php } ?>
    </div>
<?php } ?>
    <div class="linkbox pager">
<?php
$Pages=get_pages($Page, $Forums[$ForumID]['NumTopics'], TOPICS_PER_PAGE, 9);
echo $Pages;
?>
    </div>
        <div class="head"><a href="/forums.php">Forums</a> &gt; <?=display_str($Forums[$ForumID]['Name'])?></div>
    <table class="forum_list" width="100%">
        <tr class="colhead">
            <td style="width:2%;"></td>
            <td>Topic</td>
            <td style="width:5%;">Replies</td>
            <td style="width:5%;">Views</td>
            <td>Latest</td>
        </tr>
<?php
// Check that we have content to process
if (count($Forum) == 0) {
?>
        <tr>
            <td colspan="5">
                No threads to display in this forum!
            </td>
        </tr>
<?php
} else {
    // forums_last_read_topics is a record of the last post a user read in a topic, and what page that was on
    $DB->query('SELECT
        l.TopicID,
        l.PostID,
        CEIL((SELECT COUNT(ID) FROM forums_posts WHERE forums_posts.TopicID = l.TopicID AND forums_posts.ID<=l.PostID)/'.$PerPage.') AS Page
        FROM forums_last_read_topics AS l
        WHERE TopicID IN('.implode(', ', array_keys($Forum)).') AND
        UserID=\''.$LoggedUser['ID'].'\'');

    // Turns the result set into a multi-dimensional array, with
    // forums_last_read_topics.TopicID as the key.
    // This is done here so we get the benefit of the caching, and we
    // don't have to make a database query for each topic on the page
    $LastRead = $DB->to_array('TopicID');

    //---------- Begin printing

    $Row='a';
    foreach ($Forum as $Topic) {
        list($TopicID, $Title, $AuthorID, $AuthorName, $Locked, $Sticky, $PostCount, $LastID, $LastTime, $LastAuthorID, $LastAuthorName) = array_values($Topic);
        $Row = ($Row == 'a') ? 'b' : 'a';

            // Build list of page links
        // Only do this if there is more than one page
        $PageLinks = array();
        $ShownEllipses = false;
        $PagesText = '';
        $TopicPages = ceil($PostCount/$PerPage);

        if ($TopicPages > 1) {
            $PagesText=' (';
            for ($i = 1; $i <= $TopicPages; $i++) {
                if ($TopicPages>4 && ($i > 2 && $i <= $TopicPages-2)) {
                    if (!$ShownEllipses) {
                        $PageLinks[]='-';
                        $ShownEllipses = true;
                    }
                    continue;
                }
                $PageLinks[]='<a href="/forums.php?action=viewthread&amp;threadid='.$TopicID.'&amp;page='.$i.'">'.$i.'</a>';
            }
            $PagesText.=implode(' ', $PageLinks);
            $PagesText.=')';
        }

        $NumViews = get_thread_views($TopicID);

        // handle read/unread posts - the reason we can't cache the whole page
        if ((!$Locked || $Sticky) && ((empty($LastRead[$TopicID]) || $LastRead[$TopicID]['PostID']<$LastID) && strtotime($LastTime)>$LoggedUser['CatchupTime'])) {
            $Read = 'unread';
        } else {
            $Read = 'read';
        }
        if ($Locked) {
            $Read .= "_locked";
        }
        if ($Sticky) {
            $Read .= "_sticky";
        }
?>
    <tr class="row<?=$Row;
    if ($Sticky) {
        echo' sticky';
    }?>">
        <td class="<?=$Read?>" title="<?=ucwords(str_replace('_', ' ', $Read))?>"></td>
        <td>
            <span style="float:left;" class="last_topic">
<?php
        $TopicLength=75-(2*count($PageLinks));
        unset($PageLinks);
?>
                <strong>
                    <a href="/forums.php?action=viewthread&amp;threadid=<?=$TopicID?>" title="<?=display_str($Title)?>"><?=display_str(cut_string($Title, $TopicLength)) ?></a>
                </strong>
                <?=$PagesText?>
            </span>
<?php	  if (!empty($LastRead[$TopicID])) { ?>
            <span style="float: left;" class="last_read" title="Jump to last read">
                <a href="/forums.php?action=viewthread&amp;threadid=<?=$TopicID?>&amp;page=<?=$LastRead[$TopicID]['Page']?>#post<?=$LastRead[$TopicID]['PostID']?>"></a>
            </span>
<?php	  } ?>
            <span style="float: right;" class="first_poster">
                started by <?=format_username($AuthorID, $AuthorName)?>
            </span>
        </td>
        <td style="text-align: center;"><?=number_format($PostCount-1)?></td>
        <td style="text-align: center;"><?=number_format($NumViews)?></td>
        <td>
                <span style="float: left;" class="last_poster">
                    by <?=format_username($LastAuthorID, $LastAuthorName)?> <?=time_diff($LastTime, 1)?>
                </span>
            <span style="float: left;" class="last_post" title="Jump to last post">
                <a href="/forums.php?action=viewthread&amp;threadid=<?=$TopicID?>&amp;postid=<?=$LastID?>#post<?=$LastID?>"></a>
            </span>
            </td>
    </tr>
    <?php	}
} ?>
</table>
    <div class="linkbox pager">
        <?=$Pages?>
    </div>
<?php
    print_forums_goto($Forums, $ForumCats, $ForumID);
?>
    <div class="linkbox">
<?php   print_forum_links($ForumID, $LoggedUser); ?>
    </div>
</div>
<?php
show_footer();
