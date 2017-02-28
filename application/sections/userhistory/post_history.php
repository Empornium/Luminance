<?php
//TODO: replace 24-43 with user_info()
/*
User post history page
*/

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
include_once(SERVER_ROOT.'/sections/forums/functions.php'); // Forum functions
$Text = new TEXT;

$UserID = empty($_GET['userid']) ? $LoggedUser['ID'] : $_GET['userid'];
if (!is_number($UserID)) {
    error(0);
}

if (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

list($Page,$Limit) = page_limit($PerPage);

$UserInfo = user_info($UserID);
list( ,$Username, $PermissionID, $Paranoia, $Donor, $Warned, $Avatar, $Enabled, $Title) = array_values($UserInfo);

$UserPermissions = get_permissions($PermissionID);
$UserClass = $UserPermissions['Class'];

if ( !check_force_anon($UserID) ||
            !check_paranoia('torrentcomments', $Paranoia, $UserClass, $UserID)) { error(PARANOIA_MSG); }

if (check_perms('site_proxy_images') && !empty($Avatar)) {
    $Avatar = '//'.SITE_URL.'/image.php?c=1&i='.urlencode($Avatar);
}

show_header('Post history for '.$Username,'subscriptions,comments,bbcode');

if ($LoggedUser['CustomForums']) {
    unset($LoggedUser['CustomForums']['']);
    $RestrictedForums = implode("','", array_keys($LoggedUser['CustomForums'], 0));
}

$PermissionsInfo = get_permissions_for_user($UserID, false, $UserPermissions);

$ViewingOwn = ($UserID == $LoggedUser['ID']);
$ShowUnread = ($ViewingOwn && (!isset($_GET['showunread']) || !!$_GET['showunread']));
$ShowGrouped = ($ViewingOwn && (!isset($_GET['group']) || !!$_GET['group']));
if ($ShowGrouped) {
    $sql = 'SELECT
        SQL_CALC_FOUND_ROWS
        MAX(p.ID) AS ID
        FROM forums_posts AS p
        LEFT JOIN forums_topics AS t ON t.ID = p.TopicID';
    if ($ShowUnread) {
        $sql.='
        LEFT JOIN forums_last_read_topics AS l ON l.TopicID = t.ID AND l.UserID = '.$LoggedUser['ID'];
    }
    $sql .= '
        LEFT JOIN forums AS f ON f.ID = t.ForumID
        WHERE p.AuthorID = '.$UserID.'
        AND ((f.MinClassRead <= '.$LoggedUser['Class'];
    if (!empty($RestrictedForums)) {
        $sql.='
        AND f.ID NOT IN (\''.$RestrictedForums.'\')';
    }
    $sql .= ')';
    if (!empty($PermittedForums)) {
        $sql.='
        OR f.ID IN (\''.$PermittedForums.'\')';
    }
    $sql .= ')';
    if ($ShowUnread) {
        $sql .= '
        AND ((t.IsLocked=\'0\' OR t.IsSticky=\'1\')
        AND (l.PostID<t.LastPostID OR l.PostID IS NULL))';
    }
    $sql .= '
        GROUP BY t.ID
        ORDER BY p.ID DESC LIMIT '.$Limit;
    $PostIDs = $DB->query($sql);
    $DB->query("SELECT FOUND_ROWS()");
    list($Results) = $DB->next_record();

    if ($Results > $PerPage*($Page-1)) {
        $DB->set_query_id($PostIDs);
        $PostIDs = $DB->collect('ID');
        $sql = 'SELECT
            p.ID,
            p.AddedTime,
            p.Body,
            p.EditedUserID,
            p.EditedTime,
            ed.Username,
            p.TopicID,
            t.ForumID,
            t.Title,
            t.LastPostID,
            l.PostID AS LastRead,
            t.IsLocked,
            t.IsSticky
            FROM forums_posts as p
            LEFT JOIN users_main AS ed ON ed.ID = p.EditedUserID
            JOIN forums_topics AS t ON t.ID = p.TopicID
            JOIN forums AS f ON f.ID = t.ForumID
            LEFT JOIN forums_last_read_topics AS l ON l.UserID = '.$UserID.' AND l.TopicID = t.ID
            WHERE p.ID IN ('.implode(',',$PostIDs).')
            ORDER BY p.ID DESC';
        $Posts = $DB->query($sql);
    }
} else {
    $sql = 'SELECT
        SQL_CALC_FOUND_ROWS';
    if ($ShowGrouped) {
        $sql.=' * FROM (SELECT';
    }
    $sql .= '
        p.ID,
        p.AddedTime,
        p.Body,
        p.EditedUserID,
        p.EditedTime,
        ed.Username,
        p.TopicID,
        t.ForumID,
        t.Title,
        t.LastPostID,';
    if ($UserID == $LoggedUser['ID']) {
        $sql .= '
        l.PostID AS LastRead,';
    }
    $sql .= '
        t.IsLocked,
        t.IsSticky
        FROM forums_posts as p
        LEFT JOIN users_main AS ed ON ed.ID = p.EditedUserID
        JOIN forums_topics AS t ON t.ID = p.TopicID
        JOIN forums AS f ON f.ID = t.ForumID
        LEFT JOIN forums_last_read_topics AS l ON l.UserID = '.$UserID.' AND l.TopicID = t.ID
        WHERE p.AuthorID = '.$UserID.'
        AND f.MinClassRead <= '.$LoggedUser['Class'];

    if (!empty($RestrictedForums)) {
        $sql.='
        AND f.ID NOT IN (\''.$RestrictedForums.'\')';
    }

    if ($ShowUnread) {
        $sql.='
        AND ((t.IsLocked=\'0\' OR t.IsSticky=\'1\') AND (l.PostID<t.LastPostID OR l.PostID IS NULL)) ';
    }

    $sql .= '
        ORDER BY p.ID DESC';

    if ($ShowGrouped) {
        $sql.='
        ) AS sub
        GROUP BY TopicID ORDER BY ID DESC';
    }

    $sql.=' LIMIT '.$Limit;
    $Posts = $DB->query($sql);

    $DB->query("SELECT FOUND_ROWS()");
    list($Results) = $DB->next_record();

    $DB->set_query_id($Posts);
}

?>
<div class="thin">
    <h2>
<?php
    if ($ShowGrouped) {
        echo "Grouped ".($ShowUnread?"unread ":"")."post history for <a href=\"user.php?id=$UserID\">$Username</a>";
    } elseif ($ShowUnread) {
        echo "Unread post history for <a href=\"user.php?id=$UserID\">$Username</a>";
    } else {
        echo "Post history for <a href=\"user.php?id=$UserID\">$Username</a>";
    }
?>
    </h2>

    <div class="linkbox">
<?php
if (($UserSubscriptions = $Cache->get_value('subscriptions_user_'.$LoggedUser['ID'])) === FALSE) {
    $DB->query("SELECT TopicID FROM users_subscriptions WHERE UserID = '$LoggedUser[ID]'");
    $UserSubscriptions = $DB->collect(0);
    $Cache->cache_value('subscriptions_user_'.$LoggedUser['ID'],$UserSubscriptions,0);
    $DB->set_query_id($Posts);
}

if ($ViewingOwn) {
    if (!$ShowUnread) { ?>
        <br /><br />
        <?php  if ($ShowGrouped) { ?>
            <a href="userhistory.php?action=posts&amp;userid=<?=$UserID?>&amp;showunread=0&amp;group=0">Show all posts</a>&nbsp;&nbsp;&nbsp;
        <?php  } else { ?>
            <a href="userhistory.php?action=posts&amp;userid=<?=$UserID?>&amp;showunread=0&amp;group=1">Show all posts (grouped)</a>&nbsp;&nbsp;&nbsp;
        <?php  } ?>
        <a href="userhistory.php?action=posts&amp;userid=<?=$UserID?>&amp;showunread=1&amp;group=1">Only display posts with unread replies (grouped)</a>&nbsp;&nbsp;&nbsp;
<?php 	} else { ?>
        <br /><br />
        <a href="userhistory.php?action=posts&amp;userid=<?=$UserID?>&amp;showunread=0&amp;group=0">Show all posts</a>&nbsp;&nbsp;&nbsp;
<?php
        if (!$ShowGrouped) {
            ?><a href="userhistory.php?action=posts&amp;userid=<?=$UserID?>&amp;showunread=1&amp;group=1">Only display posts with unread replies (grouped)</a>&nbsp;&nbsp;&nbsp;<?php
        } else {
            ?><a href="userhistory.php?action=posts&amp;userid=<?=$UserID?>&amp;showunread=1&amp;group=0">Only display posts with unread replies</a>&nbsp;&nbsp;&nbsp;<?php
        }
    }
?>
            <a href="userhistory.php?action=subscriptions">Go to forum subscriptions</a>&nbsp;&nbsp;&nbsp;
            <a href="userhistory.php?action=subscribed_collages">Go to collage subscriptions</a>&nbsp;&nbsp;&nbsp;
            <a href="userhistory.php?action=comments">Go to comment history</a>
<?php
} else {
?>
            <a href="userhistory.php?action=comments&amp;userid=<?=$UserID?>">Go to comment history</a>
<?php
}
?>
    </div>
<?php
if (empty($Results)) {
?>
    <div class="center">
        No topics<?=$ShowUnread?' with unread posts':''?>
    </div>
<?php
} else {
?>
    <div class="linkbox">
<?php
    $Pages=get_pages($Page,$Results,$PerPage, 11);
    echo $Pages;
?>
    </div>
<?php
    $Key = 0;
    $results = $DB->to_array();
    foreach($results as $result) {
        list($PostID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername, $TopicID, $ForumID, $ThreadTitle, $LastPostID, $LastRead, $Locked, $Sticky) = $result;
?>
    <table class='forum_post vertical_margin<?=$HeavyInfo['DisableAvatars'] ? ' noavatar' : ''?>' id='post<?=$PostID ?>'>
        <tr class='smallhead'>
            <td  colspan="2">
                <span style="float:left;">
                    <?=time_diff($AddedTime) ?>
                    in <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID?>&amp;postid=<?=$PostID?>#post<?=$PostID?>" title="<?=display_str($ThreadTitle)?>"><?=cut_string($ThreadTitle, 75)?></a>
                </span>
<?php
        if ($ViewingOwn) {
            if ((!$Locked  || $Sticky) && (!$LastRead || $LastRead < $LastPostID)) { ?>
                    <span class="newstatus">(New!)</span>
<?php
            }
            if (!empty($LastRead)) { ?>
                <span style="float:left;" class="last_read" title="Jump to last read">
                    <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID?>&amp;postid=<?=$LastRead?>#post<?=$LastRead?>"></a>
                </span>
<?php       } else { ?>
                <span style="float:left;" class="last_read" title="Jump to last read">
                    <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID?>&amp;postid=<?=$PostID?>#post<?=$PostID?>"></a>
                </span>
<?php       }
        } else { ?>
            <span style="float:left;" class="last_read" title="Jump to last read">
                <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID?>&amp;postid=<?=$PostID?>#post<?=$PostID?>"></a>
            </span>
<?php   } ?>
                <span style="float:left;padding-left:5px;">
<?php   if ((((!$ThreadInfo['IsLocked'] && check_forumperm($TopicID, 'Write')) && can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime)) || check_perms('site_moderate_forums')) && !$ShowGrouped) {
?>
         <a href="#post<?=$PostID?>" onclick="Edit_Form('forums','<?=$PostID?>','<?=$Key++?>');">[Edit]</a>
<?php   }
        if ($ForumID != TRASH_FORUM_ID && check_perms('site_moderate_forums') && !$ShowGrouped) {
?>
         - <a href="#post<?=$PostID?>" onclick="Trash('<?=$TopicID?>','<?=$PostID?>');" title="moves this post to the trash forum">[Trash]</a>
<?php   }
        if (check_perms('site_admin_forums') && !$ShowGrouped) {
?>
        - <a href="#post<?=$PostID?>" onclick="Delete('<?=$PostID?>');" title="permenantly delete this post">[Delete]</a>
<?php   } ?>
                </span>

                <span id="bar<?=$PostID?>" style="float:right;">
<?php
        if (!$ShowGrouped) {
?>
                    <a href="reports.php?action=report&amp;type=posthistory&amp;id=<?=$PostID?>">[Report]</a> -
<?php   } ?>
                    <a href="#" onclick="Subscribe(<?=$TopicID?>);return false;" class="subscribelink<?=$TopicID?>">[<?=(in_array($TopicID, $UserSubscriptions) ? 'Unsubscribe' : 'Subscribe')?>]</a>
                    &nbsp;
                    <a href="#">&uarr;</a>
                </span>
            </td>
        </tr>
<?php
        if (!$ShowGrouped) {
?>
        <tr>
<?php
            if (empty($HeavyInfo['DisableAvatars'])) {
?>
            <td class='avatar' valign="top">

    <?php  if ($Avatar) {   ?>
            <img src="<?=$Avatar?>" class="avatar" style="<?=get_avatar_css($UserPermissions['MaxAvatarWidth'], $UserPermissions['MaxAvatarHeight'])?>" alt="<?=$Username ?>'s avatar" />
    <?php  } else { ?>
            <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
    <?php
         }
    ?>
            </td>
<?php
            }
?>
            <td class='postbody' valign="top">
                <div id="content<?=$PostID?>" class="post_container">
                    <div class="post_content">
                        <?=$Text->full_format($Body, isset($PermissionsInfo['site_advanced_tags']) &&  $PermissionsInfo['site_advanced_tags'] );?>
<?php 			if ($EditedUserID) { ?>
                            <br />
                            <br />
                    </div>
                    <div class="post_footer">
<?php 				if (check_perms('site_moderate_forums')) { ?>
                            <a href="#content<?=$PostID?>" onclick="LoadEdit('forums', <?=$PostID?>, 1)">&laquo;</a>
<?php  				} ?>
                            <span class="editedby">Last edited by
                                <?=format_username($EditedUserID, $EditedUsername) ?> <?=time_diff($EditedTime,2,true,true)?>
                            </span>
<?php 			} ?>
                    </div>
                </div>
            </td>
        </tr>
<?php
        }
?>
    </table>
<?php  	} ?>
    <div class="linkbox">
<?=$Pages?>
    </div>
<?php  } ?>
</div>
<?php
show_footer();
