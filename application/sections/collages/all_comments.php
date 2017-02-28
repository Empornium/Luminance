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
show_header('All collage comments' , 'comments,bbcode,jquery');
?>
<div class="thin">
    <h2>Latest Collage Comments</h2>
<?php

$DB->query("SELECT STRAIGHT_JOIN
                    c.Name, cc.ID, cc.CollageID, cc.UserID, cc.Time,
                    cc.Body, cc.EditedUserID, cc.EditedTime, um.Username
              FROM collages_comments AS cc
         LEFT JOIN collages AS c ON c.ID=cc.CollageID
         LEFT JOIN users_main AS um ON um.ID=cc.EditedUserID
          ORDER BY cc.ID DESC
             LIMIT $Limit");

$Comments = $DB->to_array();
$DB->query("SELECT COUNT(*) FROM collages_comments");
list($NumResults) = $DB->next_record();

?>
    <div class="linkbox">
        [<a href="torrents.php?action=allcomments">Latest Torrent Comments</a>]&nbsp;
        [<a href="requests.php?action=allcomments">Latest Request Comments</a>]&nbsp;
        [<a href="forums.php?action=allposts">Latest Forum Posts</a>]
    </div>
    <div class="linkbox"><a name="comments"></a>
<?php
$Pages=get_pages($Page,$NumResults,$PerPage,9);
echo $Pages;
?>
    </div>
<?php

//---------- Begin printing
foreach ($Comments as $Key => $Post) {
    list($Name, $PostID, $CollageID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername) = $Post ;
    list($AuthorID, $Username, $PermissionID, $Paranoia, $Donor, $Warned, $Avatar, $Enabled, $UserTitle,,,$Signature) = array_values(user_info($AuthorID));
      $AuthorPermissions = get_permissions($PermissionID);
      list($ClassLevel,$PermissionValues,$MaxSigLength,$MaxAvatarWidth,$MaxAvatarHeight)=array_values($AuthorPermissions);
      // we need to get custom permissions for this author
?>
    <div id="post<?=$PostID?>">
    <div class="head"><a class="post_id" href="collages.php?id=<?=$CollageID?>"><?=$Name?></a></div>
<table class="forum_post box vertical_margin<?=$HeavyInfo['DisableAvatars'] ? ' noavatar' : ''?>" >
    <tr class="smallhead">
        <td colspan="2">
            <span style="float:left;"><a class="post_id" href="collages.php?id=<?=$CollageID?>&amp;postid=<?=$PostID?>#post<?=$PostID?>">#<?=$PostID?></a>
                <?=format_username($AuthorID, $Username, $Donor, $Warned, $Enabled, $PermissionID, $UserTitle, true)?> <?=time_diff($AddedTime)?> <a href="reports.php?action=report&amp;type=collages_comment&amp;id=<?=$PostID?>">[Report]</a>

<?php if (can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime)) { ?>
                        - <a href="#post<?=$PostID?>" onclick="Edit_Form('collages','<?=$PostID?>','<?=$Key?>');">[Edit]</a><?php }
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
                <a href="#content<?=$PostID?>" onclick="LoadEdit('collages', <?=$PostID?>, 1); return false;">&laquo;</a>
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
