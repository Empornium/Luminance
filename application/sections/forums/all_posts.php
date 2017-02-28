<?php
/******************************************************************************/

if (!check_perms('users_fls')) error(403);

//---------- Things to sort out before it can start printing/generating content

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

if (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

list($Page, $Limit) = page_limit($PerPage);

// Start printing
show_header('All forum posts' , 'comments,bbcode,jquery');
?>
<div class="thin">
    <h2>Latest Forum Posts</h2>
<?php

if (is_array($ExcludeForums))
        $ANDWHERE = "AND ft.ForumID NOT IN (" . implode(",", $ExcludeForums) .") ";

//var_dump($LoggedUser); die();

$Level = $Classes[$LoggedUser['PermissionID']]['Level'];

$DB->query("SELECT STRAIGHT_JOIN
                    ft.Title, ft.ForumID, fp.TopicID, fp.ID, fp.AuthorID, fp.AddedTime,
                    fp.Body, fp.EditedUserID, fp.EditedTime, um.Username
                      FROM forums_posts as fp
                      JOIN forums_topics AS ft ON fp.TopicID=ft.ID
                      JOIN forums AS f ON ft.ForumID=f.ID
                      JOIN users_main AS um ON um.ID=fp.AuthorID
                     WHERE f.MinClassRead<='$Level' $ANDWHERE
                  ORDER BY fp.ID DESC
                     LIMIT $Limit");

$Posts = $DB->to_array();

$DB->query("SELECT STRAIGHT_JOIN COUNT(*)
                      FROM forums_posts as fp
                      JOIN forums_topics AS ft ON fp.TopicID=ft.ID
                      JOIN forums AS f ON ft.ForumID=f.ID
                     WHERE f.MinClassRead<='$Level' $ANDWHERE");
//$DB->query("SELECT COUNT(*) FROM forums_posts");
list($NumResults) = $DB->next_record();

?>
    <div class="linkbox">
        [<a href="torrents.php?action=allcomments">Latest Torrent Comments</a>]&nbsp;
        [<a href="requests.php?action=allcomments">Latest Request Comments</a>]&nbsp;
        [<a href="collages.php?action=allcomments">Latest Collage Comments</a>]
    </div>
    <div class="linkbox"><a name="posts"></a>
<?php
$Pages=get_pages($Page,$NumResults,$PerPage,9);
echo $Pages;
?>
    </div>
<?php

//---------- Begin printing
foreach ($Posts as $Key => $Post) {
    list($Title, $ForumID, $ThreadID, $PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername) = $Post ;
    list($AuthorID, $Username, $PermissionID, $Paranoia, $Donor, $Warned, $Avatar, $Enabled, $UserTitle,,,$Signature) = array_values(user_info($AuthorID));
      $AuthorPermissions = get_permissions($PermissionID);
      list($ClassLevel,$PermissionValues,$MaxSigLength,$MaxAvatarWidth,$MaxAvatarHeight)=array_values($AuthorPermissions);
      // we need to get custom permissions for this author
    if (!check_forumperm($ForumID)) {
        continue;
    }
?>
    <div id="post<?=$PostID?>">
    <div class="head">
        <a href="forums.php?action=viewforum&amp;forumid=<?=$ForumID?>"><?=$Forums[$ForumID]['Name']?></a> &gt;
        <a class="post_id" href="forums.php?action=viewthread&threadid=<?=$ThreadID?>&postid=<?=$PostID?>#post<?=$PostID?>"><?=$Title?></a>
    </div>
<table class="forum_post box vertical_margin<?=$HeavyInfo['DisableAvatars'] ? ' noavatar' : ''?>" >
    <tr class="smallhead">
        <td colspan="2">
            <span style="float:left;"><a class="post_id" href="forums.php?action=viewthread&threadid=<?=$ThreadID?>&postid=<?=$PostID?>#post<?=$PostID?>">#<?=$PostID?></a>
                <?=format_username($AuthorID, $Username, $Donor, $Warned, $Enabled, $PermissionID, $UserTitle, true)?> <?=time_diff($AddedTime)?> <a href="reports.php?action=report&amp;type=post&amp;id=<?=$PostID?>">[Report]</a>

<?php if (can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime)) { ?>
                        - <a href="#post<?=$PostID?>" onclick="Edit_Form('forums','<?=$PostID?>','<?=$Key?>');">[Edit]</a><?php }
  if (check_perms('site_admin_forums')) { ?>
                        - <a href="#post<?=$PostID?>" onclick="Delete('<?=$PostID?>');">[Delete]</a> <?php } ?>
            </span>
            <span id="bar<?=$PostID?>" style="float:right;">
                <a href="#">&uarr;</a>
            </span>
        </td>
    </tr>
    <tr>
<?php if (empty($HeavyInfo['DisableAvatars'])) {?>
        <td class="avatar" valign="top" rowspan="2">
    <?php if ($Avatar) { ?>
            <img src="<?=$Avatar?>" class="avatar" style="<?=get_avatar_css($MaxAvatarWidth, $MaxAvatarHeight)?>" alt="<?=$Username ?>'s avatar" />
    <?php } else { ?>
            <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
    <?php
         }
        $UserBadges = get_user_badges($AuthorID);
        if ( !empty($UserBadges) ) {  ?>
               <div class="badges">
<?php                   print_badges_array($UserBadges, $AuthorID); ?>
               </div>
<?php       }      ?>
        </td>
<?php
}
$AllowTags= get_permissions_advtags($AuthorID, false, $AuthorPermissions);
?>
        <td class="postbody" valign="top">
            <div id="content<?=$PostID?>" class="post_container">
                      <div class="post_content"><?=$Text->full_format($Body, $AllowTags) ?> </div>
<?php  if ($EditedUserID) { ?>
                        <div class="post_footer">
<?php 	if (check_perms('site_moderate_forums')) { ?>
                <a href="#content<?=$PostID?>" onclick="LoadEdit('forums', <?=$PostID?>, 1); return false;">&laquo;</a>
<?php  	} ?>
                        <span class="editedby">Last edited by
                <?=format_username($EditedUserID, $EditedUsername) ?> <?=time_diff($EditedTime,2,true,true)?>
                        </span>
                        </div>
        <?php  }   ?>
            </div>
        </td>
    </tr>
<?php
      if ( empty($HeavyInfo['DisableSignatures']) && ($MaxSigLength > 0) && !empty($Signature) ) { //post_footer

            echo '
      <tr>
            <td class="sig"><div id="sig" style="max-height: '.SIG_MAX_HEIGHT. 'px"><div>' . $Text->full_format($Signature, $AllowTags) . '</div></div></td>
      </tr>';
           }
?>
</table>
    </div>
<?php 	} ?>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php
show_footer();
