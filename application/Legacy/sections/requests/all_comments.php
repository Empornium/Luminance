<?php
/******************************************************************************/

if (!check_perms('users_fls')) error(403);

//---------- Things to sort out before it can start printing/generating content

$bbCode = new \Luminance\Legacy\Text;

if (isset($activeUser['PostsPerPage'])) {
    $PerPage = $activeUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

list($Page, $Limit) = page_limit($PerPage);

// Start printing
show_header('All request comments' , 'comments,bbcode,jquery,jquery.cookie');
?>
<div class="thin">
    <h2>Latest Request Comments</h2>
<?php

$Comments = $master->db->rawQuery(
    "SELECT STRAIGHT_JOIN
            r.Title,
            rc.ID,
            rc.RequestID,
            rc.AuthorID,
            rc.AddedTime,
            rc.Body,
            rc.EditedUserID,
            rc.EditedTime,
            u.Username
       FROM requests_comments AS rc
  LEFT JOIN requests AS r ON r.ID=rc.RequestID
  LEFT JOIN users AS u ON u.ID=rc.EditedUserID
   ORDER BY rc.ID DESC
      LIMIT {$Limit}"
)->fetchAll(\PDO::FETCH_NUM);

$NumResults = $master->db->rawQuery(
    "SELECT COUNT(*)
       FROM requests_comments"
)->fetchColumn();

?>
    <div class="linkbox">
        [<a href="/torrents.php?action=allcomments">Latest Torrent Comments</a>]&nbsp;
        [<a href="/collage/recent">Latest Collage Comments</a>]&nbsp;
        [<a href="/forums/recent">Latest Forum Posts</a>]
    </div>
    <div class="linkbox"><a name="comments"></a>
<?php
$Pages = get_pages($Page, $NumResults, $PerPage, 9);
echo $Pages;
?>
    </div>
<?php

//---------- Begin printing
foreach ($Comments as $Post) {
    list($RName, $PostID, $RequestID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime, $EditedUsername) = $Post ;
    list($AuthorID, $Username, $PermissionID, $paranoia, $Donor, $Avatar, $enabled, $UserTitle, , , $Signature) = array_values(user_info($AuthorID));
      $AuthorPermissions = get_permissions($PermissionID);
      list($classLevel, $PermissionValues, $MaxSigLength, $MaxAvatarWidth, $MaxAvatarHeight) = array_values($AuthorPermissions);
      // we need to get custom permissions for this author
?>
    <div id="post<?=$PostID?>">
    <div class="head"><a class="post_id" href="/requests.php?action=view&amp;id=<?=$RequestID?>"><?=$RName?></a></div>
<table class="forum_post box vertical_margin<?=$heavyInfo['DisableAvatars'] ? ' noavatar' : ''?>" >
    <tr class="smallhead">
        <td colspan="2">
            <span style="float:left;"><a class="post_id" href="/requests.php?action=view&amp;id=<?=$RequestID?>&amp;postid=<?=$PostID?>#post<?=$PostID?>">#<?=$PostID?></a>
                <?=format_username($AuthorID, $Donor, true, $enabled, $PermissionID, $UserTitle, true)?> <?=time_diff($AddedTime)?> <a href="/reports.php?action=report&amp;type=requests_comment&amp;id=<?=$PostID?>">[Report]</a>

<?php if (can_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime)) { ?>
                        - <a href="#post<?=$PostID?>" onclick="Edit_Form('<?=$PostID?>');">[Edit]</a><?php }
  if (check_perms('forum_post_delete')) { ?>
                        - <a href="#post<?=$PostID?>" onclick="DeletePost('<?=$PostID?>');">[Delete]</a> <?php } ?>
            </span>
            <span id="bar<?=$PostID?>" style="float:right;">
                <a href="#">&uarr;</a>
            </span>
        </td>
    </tr>
    <tr>
<?php if (empty($heavyInfo['DisableAvatars'])) {?>
        <td class="avatar" valign="top" rowspan="2">
    <?php if ($Avatar) { ?>
            <img src="<?=$Avatar?>" class="avatar" style="<?=get_avatar_css($MaxAvatarWidth, $MaxAvatarHeight)?>" alt="<?=$Username ?>'s avatar" />
    <?php } else { ?>
            <img src="<?=STATIC_SERVER?>common/avatars/default.png" class="avatar" style="<?=get_avatar_css(100, 120)?>" alt="Default avatar" />
    <?php
         }
        $UserBadges = get_user_badges($AuthorID);
        if (!empty($UserBadges)) {  ?>
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
                      <div class="post_content"><?=$bbCode->full_format($Body, $AllowTags) ?> </div>
<?php  if ($EditedUserID) { ?>
                        <div class="post_footer">
<?php 	if (check_perms('forum_moderate')) { ?>
                <a href="#content<?=$PostID?>" onclick="LoadEdit(<?=$PostID?>, 1); return false;">&laquo;</a>
<?php  	} ?>
                        <span class="editedby">Last edited by
                <?=format_username($EditedUserID) ?> <?=time_diff($EditedTime,2,true,true)?>
                        </span>
                        </div>
        <?php  }   ?>
            </div>
        </td>
    </tr>
<?php
      if (empty($heavyInfo['DisableSignatures']) && ($MaxSigLength > 0) && !empty($Signature)) { //post_footer

            echo '
      <tr>
            <td class="sig"><div id="sig" style="max-height: '.SIG_MAX_HEIGHT. 'px"><div>' . $bbCode->full_format($Signature, $AllowTags) . '</div></div></td>
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
