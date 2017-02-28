<?php
enforce_login();

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
include(SERVER_ROOT.'/sections/torrents/functions.php'); // Edit report etc.
$Text = new TEXT;

if (!empty($_REQUEST['my_torrents']) && $_REQUEST['my_torrents'] == '1') {
    $MyTorrents = true;
} else {
    $MyTorrents = false;
}

if (isset($_GET['userid'])) {
    $UserID = $_GET['userid'];
    if (!is_number($UserID)) {
        error(404);
    }
    $UserInfo = user_info($UserID);
    $Username = $UserInfo['Username'];
    if ($LoggedUser['ID'] == $UserID) {
        $ViewingOwn = true;
    } else {
        $ViewingOwn = false;
    }
    $Perms = get_permissions($UserInfo['PermissionID']);
    $UserClass = $Perms['Class'];
    if ( !check_force_anon($UserID) ||
            !check_paranoia('torrentcomments', $UserInfo['Paranoia'], $UserClass, $UserID)) { error(PARANOIA_MSG); }
} else {
    $UserID = $LoggedUser['ID'];
    $Username = $LoggedUser['Username'];
    $ViewingOwn = true;
}

show_header($MyTorrents?"Comments left on $Username's torrents":"Comment history for $Username",'comments,bbcode');

if (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

list($Page,$Limit) = page_limit($PerPage);
$OtherLink = '';

if ($MyTorrents) {
    $Conditions = "WHERE t.UserID = $UserID AND tc.AuthorID != t.UserID AND tc.AddedTime > t.Time";
    $Title = 'Comments left on your torrents';
    $Header = 'Comments left on your uploads';
    if($ViewingOwn) $OtherLink = '<a href="userhistory.php?action=comments">Display comments you\'ve made</a>';
} else {
    $Conditions = "WHERE tc.AuthorID = $UserID";
    $Title = 'Comments made by '.($ViewingOwn?'you':$Username);
    $Header = 'Torrent comments left by '.($ViewingOwn?'you':format_username($UserID, $Username)).'';
    if($ViewingOwn) $OtherLink = '<a href="userhistory.php?action=comments&amp;my_torrents=1">Display comments left on your uploads</a>';
}

$Comments = $DB->query("SELECT
    SQL_CALC_FOUND_ROWS
    m.ID AS UserID,
    m.Username,
    m.PermissionID,
    m.GroupPermissionID,
    m.Enabled,
    m.CustomPermissions,

    i.Avatar,
    i.Donor,
    i.Warned,

    t.ID AS TorrentID,
    t.GroupID,

    tg.Name,

    tc.ID AS PostID,
    tc.Body,
    tc.AddedTime,
    tc.EditedTime,

    em.ID as EditorID,
    em.Username as EditorUsername

    FROM torrents as t
    JOIN torrents_comments as tc ON tc.GroupID = t.GroupID
    JOIN users_main as m ON tc.AuthorID = m.ID
    JOIN users_info as i ON i.UserID = m.ID
    JOIN torrents_group as tg ON t.GroupID = tg.ID
    LEFT JOIN users_main as em ON em.ID = tc.EditedUserID

    $Conditions

    GROUP BY tc.ID

    ORDER BY tc.AddedTime DESC

    LIMIT $Limit;
");

$DB->query("SELECT FOUND_ROWS()");
list($Results) = $DB->next_record();

$Pages=get_pages($Page,$Results,$PerPage, 11);

$DB->set_query_id($Comments);

$GroupIDs = $DB->collect('GroupID');

$DB->set_query_id($Comments);

?><div class="thin">
    <h2><?=$Header?></h2>
    <div class="linkbox">
    <?=$OtherLink?>&nbsp;&nbsp;&nbsp;
<?php       if (!$ViewingOwn) { ?>
                <a href="userhistory.php?action=posts&amp;userid=<?=$UserID?>&amp;group=0">Go to post history</a>
<?php       } else { ?>
                <a href="userhistory.php?action=subscriptions">Go to forum subscriptions</a>&nbsp;&nbsp;&nbsp;
                <a href="userhistory.php?action=subscribed_collages">Go to collage subscriptions</a>&nbsp;&nbsp;&nbsp;
                <a href="userhistory.php?action=posts&amp;group=0&amp;showunread=0">Go to post history</a>
<?php       } ?>
    <br /><br />
    <?=$Pages?>
    </div>
<?php

     $Posts = $DB->to_array(false,MYSQLI_ASSOC,array('CustomPermissions'));

foreach ($Posts as $Key => $Post) {
    list($UserID, $Username, $Class, $GroupPermID, $Enabled, $CustomPermissions, $Avatar, $Donor, $Warned, $TorrentID, $GroupID, $Title, $PostID, $Body, $AddedTime, $EditedTime, $EditorID, $EditorUsername) = array_values($Post);

    $AuthorPermissions = get_permissions($Class);
    list($ClassLevel,$PermissionValues,$MaxSigLength,$MaxAvatarWidth,$MaxAvatarHeight)=array_values($AuthorPermissions);
?>
    <table class='forum_post box vertical_margin<?=$HeavyInfo['DisableAvatars'] ? ' noavatar' : ''?>' id="post<?=$PostID?>">
        <tr class='smallhead'>
            <td  colspan="2">
                <span style="float:left;">
<?php               if (!$ViewingOwn) { ?>
                        by <?=format_username($UserID, $Username, $Donor, $Warned, $Enabled, $Class, false, true, $GroupPermID)?>
<?php               } ?>
                    <?=time_diff($AddedTime) ?>
                    on <a href="torrents.php?id=<?=$GroupID?>&amp;postid=<?=$PostID?>#post<?=$PostID?>"><?=cut_string($Title, 75)?></a>
                </span>
                <span style="float:left;" class="last_read" title="Jump to last read">
                    <a href="torrents.php?id=<?=$GroupID?>&amp;postid=<?=$PostID?>#post<?=$PostID?>"></a>
                </span>
                <span style="float:left;padding-left:5px;">
<?php   if (can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime)) { ?>
         <a href="#post<?=$PostID?>" onclick="Edit_Form('comments','<?=$PostID?>','<?=$Key++?>');">[Edit]</a>
<?php   }
        if (check_perms('site_admin_forums')) { ?>
        - <a href="#post<?=$PostID?>" onclick="Delete('<?=$PostID?>');" title="permenantly delete this comment">[Delete]</a>
<?php   } ?>
                </span>

                <span id="bar<?=$PostID?>" style="float:right;">
                    <a href="reports.php?action=report&amp;type=torrents_commenthistory&amp;id=<?=$PostID?>">[Report]</a>
                </span>
            </td>
        </tr>
        <tr>
<?php
if (empty($HeavyInfo['DisableAvatars'])) {
?>
            <td class='avatar' valign="top">
<?php
                    if ($Avatar) {    ?>
                        <img src="<?=$Avatar?>" class="avatar" style="<?=get_avatar_css($MaxAvatarWidth, $MaxAvatarHeight)?>" alt="<?=$Username ?>'s avatar" />
<?php               } else {        ?>
                        <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
<?php               }               ?>
            </td>
<?php } ?>
            <td class='postbody' valign="top">
                <div id="content<?=$PostID?>" class="post_container">
                    <div class="post_content">
                        <?=$Text->full_format($Body, get_permissions_advtags($UserID, unserialize($CustomPermissions), $AuthorPermissions)) ?>
<?php                   if ($EditorID) { ?>
                            <br />
                            <br />
                    </div>
                    <div class="post_footer">
<?php                           if (check_perms('site_moderate_forums')) { ?>
                            <a href="#content<?=$PostID?>" onclick="LoadEdit('torrents', <?=$PostID?>, 1)">&laquo;</a>
<?php                           } ?>
                            <span class="editedby">Last edited by
                                <?=format_username($EditorID, $EditorUsername) ?> <?=time_diff($EditedTime,2,true,true)?>
                            </span>
<?php                    } ?>
                    </div>
                </div>
            </td>
        </tr>
    </table>
<?php
}

?>
    <div class="linkbox">
<?php
echo $Pages;
?>
    </div>
</div>
<?php
show_footer();
