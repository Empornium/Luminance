<?php
/*
User topic subscription page
*/

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

if (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}
list($Page,$Limit) = page_limit($PerPage);

show_header('Subscribed topics','subscriptions,bbcode');

if ($LoggedUser['CustomForums']) {
    unset($LoggedUser['CustomForums']['']);
    $RestrictedForums = implode("','", array_keys($LoggedUser['CustomForums'], 0));
    $PermittedForums = implode("','", array_keys($LoggedUser['CustomForums'], 1));
}

$ShowUnread = (!isset($_GET['showunread']) && !isset($HeavyInfo['SubscriptionsUnread']) || isset($HeavyInfo['SubscriptionsUnread']) && !!$HeavyInfo['SubscriptionsUnread'] || isset($_GET['showunread']) && !!$_GET['showunread']);
$ShowCollapsed = (!isset($_GET['collapse']) && !isset($HeavyInfo['SubscriptionsCollapse']) || isset($HeavyInfo['SubscriptionsCollapse']) && !!$HeavyInfo['SubscriptionsCollapse'] || isset($_GET['collapse']) && !!$_GET['collapse']);
$sql = 'SELECT
    SQL_CALC_FOUND_ROWS
    MAX(p.ID) AS ID
    FROM forums_posts AS p
    LEFT JOIN forums_topics AS t ON t.ID = p.TopicID
    JOIN users_subscriptions AS s ON s.TopicID = t.ID
    LEFT JOIN forums AS f ON f.ID = t.ForumID
    LEFT JOIN forums_last_read_topics AS l ON p.TopicID = l.TopicID AND l.UserID = s.UserID
    WHERE s.UserID = '.$LoggedUser['ID'].'
    AND p.ID <= IFNULL(l.PostID,t.LastPostID)
    AND ((f.MinClassRead <= '.$LoggedUser['Class'];
if (!empty($RestrictedForums)) {
    $sql.=' AND f.ID NOT IN (\''.$RestrictedForums.'\')';
}
$sql .= ')';
if (!empty($PermittedForums)) {
    $sql.=' OR f.ID IN (\''.$PermittedForums.'\')';
}
$sql .= ')';
if ($ShowUnread) {
    $sql .= '
    AND IF(l.PostID IS NULL OR (t.IsLocked = \'1\' && t.IsSticky = \'0\'), t.LastPostID, l.PostID) < t.LastPostID';
}
$sql .= '
    GROUP BY t.ID
    ORDER BY t.LastPostID DESC
    LIMIT '.$Limit;
$PostIDs = $DB->query($sql);
$DB->query('SELECT FOUND_ROWS()');
list($NumResults) = $DB->next_record();

if ($NumResults > $PerPage*($Page-1)) {
    $DB->set_query_id($PostIDs);
    $PostIDs = $DB->collect('ID');
    $sql = 'SELECT
        f.ID AS ForumID,
        f.Name AS ForumName,
        p.TopicID,
        t.Title,
        p.Body,
        t.LastPostID,
        t.IsLocked,
        t.IsSticky,
        p.ID,
        p.AuthorID,
        um.Username,
        ui.Avatar,
        p.EditedUserID,
        p.EditedTime,
        ed.Username AS EditedUsername,
            um.PermissionID
        FROM forums_posts AS p
        LEFT JOIN forums_topics AS t ON t.ID = p.TopicID
        LEFT JOIN forums AS f ON f.ID = t.ForumID
        LEFT JOIN users_main AS um ON um.ID = p.AuthorID
        LEFT JOIN users_info AS ui ON ui.UserID = um.ID
        LEFT JOIN users_main AS ed ON ed.ID = um.ID
        WHERE p.ID IN ('.implode(',',$PostIDs).')
        ORDER BY f.Name ASC, t.LastPostID DESC';
    $DB->query($sql);
    $Posts = $DB->to_array(false,MYSQLI_ASSOC);
}
?>
<div class="thin">
    <h2>Subscribed Forum Threads<?= ($ShowUnread ? ' with new additions' : '') ?></h2>
    <?php print_latest_forum_topics(); ?>
    <div class="linkbox">
<?php
if (!$ShowUnread) {
?>
            <br /><br />
            <a href="userhistory.php?action=subscriptions&amp;showunread=1">Only display topics with unread replies</a>&nbsp;&nbsp;&nbsp;
<?php
} else {
?>
            <br /><br />
            <a href="userhistory.php?action=subscriptions&amp;showunread=0">Show all subscribed topics</a>&nbsp;&nbsp;&nbsp;
<?php
}
if ($NumResults) {
?>
            <a href="#" onclick="Collapse();return false;" id="collapselink"><?=$ShowCollapsed?'Show':'Hide'?> post bodies</a>&nbsp;&nbsp;&nbsp;
<?php
}
?>
            <a href="userhistory.php?action=catchup&amp;auth=<?=$LoggedUser['AuthKey']?>">Catch up</a>&nbsp;&nbsp;&nbsp;
            <a href="userhistory.php?action=subscribed_collages">Go to collage subscriptions</a>&nbsp;&nbsp;&nbsp;
            <a href="userhistory.php?action=posts&amp;group=0&amp;showunread=0">Go to post history</a>&nbsp;&nbsp;&nbsp;
            <a href="userhistory.php?action=comments&amp;userid=<?=$LoggedUser['ID']?>">Go to comment history</a>
    </div>
<?php
if (!$NumResults) {
?>
    <p class="center">
        No subscribed topics<?=$ShowUnread?' with unread posts':''?>
    </p>
<?php
} else {
?>
    <div class="linkbox">
<?php
    $Pages=get_pages($Page,$NumResults,$PerPage, 11);
    echo $Pages;
?>
    </div>
<?php

foreach ($Posts as $Post) {
    list($ForumID, $ForumName, $TopicID, $ThreadTitle, $Body, $LastPostID, $Locked, $Sticky, $PostID, $AuthorID, $AuthorName, $AuthorAvatar, $EditedUserID, $EditedTime, $EditedUsername,$PermissionID) = array_values($Post);
      $AuthorPermissions = get_permissions($PermissionID);
          list($ClassLevel,$PermissionValues,$MaxSigLength,$MaxAvatarWidth,$MaxAvatarHeight)=array_values($AuthorPermissions);

?>
    <div class="head"><?='Subscribed topics'.($ShowUnread?' with unread posts':'')?></div>

    <table class='forum_post box vertical_margin<?=$HeavyInfo['DisableAvatars'] ? ' noavatar' : ''?>'>
        <tr class='rowa'>
            <td colspan="2">
                <span style="float:left;">
                    <a href="forums.php?action=viewforum&amp;forumid=<?=$ForumID?>"><?=$ForumName?></a> &gt;
                    <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID?>" title="<?=display_str($ThreadTitle)?>"><?=cut_string($ThreadTitle, 75)?></a>
        <?php  if ($PostID<$LastPostID && !$Locked) { ?>
                    <span class="newstatus">(New!)</span>
        <?php  } ?>
                </span>
                <span style="float:left;" class="last_read" title="Jump to last read">
                    <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID.($PostID?'&amp;postid='.$PostID.'#post'.$PostID:'')?>"></a>
                </span>
                <span id="bar<?=$PostID ?>" style="float:right;">
                    <a href="#" onclick="Subscribe(<?=$TopicID?>);return false;" id="subscribelink<?=$TopicID?>">[Unsubscribe]</a>
                    &nbsp;
                    <a href="#">&uarr;</a>
                </span>
            </td>
        </tr>
        <tr class="row<?=$ShowCollapsed?' hidden':''?>">
        <?php  if (empty($HeavyInfo['DisableAvatars'])) { ?>
            <td class='avatar' valign="top">

    <?php  if ($AuthorAvatar) { ?>
            <img src="<?=$AuthorAvatar?>" class="avatar" style="<?=get_avatar_css($MaxAvatarWidth, $MaxAvatarHeight)?>" alt="<?=$AuthorName?>'s avatar" />
    <?php  } else { ?>
            <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
    <?php  } ?>
            </td>
        <?php  }
$AllowTags= get_permissions_advtags($AuthorID, false, $AuthorPermissions); ?>
            <td class='body' valign="top">
                <div class="content3">
                    <?=$Text->full_format($Body,$AllowTags) ?>
        <?php  if ($EditedUserID) { ?>
                    <br /><br />
                    Last edited by
                    <?=format_username($EditedUserID, $EditedUsername) ?> <?=time_diff($EditedTime)?>
        <?php  } ?>
                </div>
            </td>
        </tr>
    </table>
    <?php  } // while(list(...)) ?>
    <div class="linkbox">
<?=$Pages?>
    </div>
<?php  } ?>
</div>
<?php
show_footer();
