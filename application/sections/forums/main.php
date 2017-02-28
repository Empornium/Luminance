<?php
if (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

//We have to iterate here because if one is empty it breaks the query
$TopicIDs = array();
foreach ($Forums as $Forum) {
    if (!empty($Forum['LastPostTopicID'])) {
        $TopicIDs[]=$Forum['LastPostTopicID'];
    }
}

//Now if we have IDs' we run the query
if (!empty($TopicIDs)) {
    $DB->query("SELECT
        l.TopicID,
        l.PostID,
        CEIL((SELECT COUNT(ID) FROM forums_posts WHERE forums_posts.TopicID = l.TopicID AND forums_posts.ID<=l.PostID)/$PerPage) AS Page
        FROM forums_last_read_topics AS l
        WHERE TopicID IN(".implode(',',$TopicIDs).") AND
        UserID='$LoggedUser[ID]'");
    $LastRead = $DB->to_array('TopicID', MYSQLI_ASSOC);
} else {
    $LastRead = array();
}

show_header('Forums');
?>
<div class="thin">
<?php print_latest_forum_topics(); ?>

    <div class="linkbox">
<?php   print_main_forum_links($LoggedUser, true); ?>
        <div id="searchforum" class="hidden">
            <div style="display: inline-block;">
                            <br />
                <div class="head">Search all forums</div>
                <form action="forums.php" method="get">
                    <table cellpadding="6" cellspacing="1" border="0" class="border">
                        <input type="hidden" name="action" value="search" />
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
<?php
$Row = 'a';
$LastCategoryID=0;
$OpenTable = false;
$DB->query("SELECT RestrictedForums FROM users_info WHERE UserID = ".$LoggedUser['ID']);
list($RestrictedForums) = $DB->next_record();
$RestrictedForums = explode(',', $RestrictedForums);
$PermittedForums = array_keys($LoggedUser['PermittedForums']);
foreach ($Forums as $Forum) {
    list($ForumID, $CategoryID, $ForumName, $ForumDescription, $MinRead, $MinWrite, $MinCreate, $NumTopics, $NumPosts, $LastPostID, $LastAuthorID, $LastPostAuthorName, $LastTopicID, $LastTime, $SpecificRules, $LastTopic, $Locked, $Sticky) = array_values($Forum);
    if ($LoggedUser['CustomForums'][$ForumID] != 1 && ($MinRead>$LoggedUser['Class'] || array_search($ForumID, $RestrictedForums) !== FALSE)) {
        continue;
    }
    $Row = ($Row == 'a') ? 'b' : 'a';
    $ForumDescription = display_str($ForumDescription);

    if ($CategoryID!=$LastCategoryID) {
        $Row = 'b';
        $LastCategoryID=$CategoryID;
        if ($OpenTable) { ?>
    </table>
<?php 		} ?>

<div class="head"><?=$ForumCats[$CategoryID]?></div>
    <table class="forum_index">
        <tr class="colhead">
            <td style="width:2%;"></td>
            <td style="width:30%;" >Forum</td>
            <!--<td style="width:25%;" ></td>-->
            <td>Last Post</td>
            <td style="text-align: center;width:7%;">Topics</td>
            <td style="text-align: center;width:7%;">Posts</td>
        </tr>
<?php
        $OpenTable = true;
    }
    if ( $LastPostID != 0 && ((empty($LastRead[$LastTopicID]) || $LastRead[$LastTopicID]['PostID'] < $LastPostID) && strtotime($LastTime)>$LoggedUser['CatchupTime'])) {
        $Read = 'unread';
    } else {
        $Read = 'read';
    }
?>
    <tr class="row<?=$Row?>">
        <td class="<?=$Read?>" title="<?=ucfirst($Read)?>"></td>
        <td>
            <h4 class="min_padding">
                <a href="forums.php?action=viewforum&amp;forumid=<?=$ForumID?>" title="<?=$ForumDescription?>"><?=display_str($ForumName)?></a>
            </h4>
        </td>
<?php if ($NumPosts == 0) { ?>
        <td colspan="3">
            There are no topics here<?=($MinCreate<=$LoggedUser['Class']) ? ', <a href="forums.php?action=new&amp;forumid='.$ForumID.'">'.'create one'.'</a>' : ''?>.
        </td>
<?php } else { ?>
        <td>
            <span style="float:left;" class="last_topic">
                <a href="forums.php?action=viewthread&amp;threadid=<?=$LastTopicID?>" title="<?=display_str($LastTopic)?>"><?=display_str(cut_string($LastTopic, 50, 0))?></a>
            </span>
<?php if (!empty($LastRead[$LastTopicID])) { ?>
            <span style="float: left;" class="last_read" title="Jump to last read">
                <a href="forums.php?action=viewthread&amp;threadid=<?=$LastTopicID?>&amp;page=<?=$LastRead[$LastTopicID]['Page']?>#post<?=$LastRead[$LastTopicID]['PostID']?>"></a>
            </span>
<?php } ?>
            <span style="float:right;" class="last_poster">by <?=format_username($LastAuthorID, $LastPostAuthorName)?> <?=time_diff($LastTime,1)?></span>
        </td>
        <td style="text-align: center;"><?=number_format($NumTopics)?></td>
        <td style="text-align: center;"><?=number_format($NumPosts)?></td>
<?php } ?>
    </tr>
<?php } ?>
    </table>
    <div class="linkbox">
<?php   print_main_forum_links($LoggedUser); ?>
    </div>
</div>
<?php
show_footer();
