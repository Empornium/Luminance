<?php
authorize();

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class

$Text = new TEXT;

// Quick SQL injection check
if (!$_POST['post'] || !is_number($_POST['post'])) {
    error(404,true);
}
// End injection check

if (empty($_POST['body'])) {
    error('You cannot post a comment with no content.',true);
}

$Text->validate_bbcode($_POST['body'],  get_permissions_advtags($LoggedUser['ID']));

// Variables for database input
$UserID = $LoggedUser['ID'];
$Body = db_string(urldecode($_POST['body']));
$PostID = $_POST['post'];

// Mainly
$DB->query("SELECT cc.Body,
                   cc.UserID,
                   cc.CollageID,
                   cc.Time,
                   (SELECT COUNT(ID) FROM collages_comments WHERE ID <= ".$PostID." AND collages_comments.CollageID = cc.CollageID)
            FROM collages_comments AS cc
           WHERE cc.ID='$PostID'");
if ($DB->record_count()==0) { error(404,true); }
list($OldBody, $AuthorID, $CollageID, $AddedTime, $PostNum) = $DB->next_record();

// Make sure they aren't trying to edit posts they shouldn't

validate_edit_comment($AuthorID, null, $AddedTime, $AddedTime); // FIXME no info about who edited comment or when

// Perform the update
$DB->query("UPDATE collages_comments SET
          Body = '$Body',
          EditedUserID = '$UserID',
          EditedTime = '".sqltime()."'
          WHERE ID='$PostID'");

$Cache->delete_value('collages_edits_'.$PostID);
$Cache->delete_value('collage_'.$CollageID);
$Cache->delete_value('collage_'.$CollageID.'_1');

$DB->query("INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
                                VALUES ('collages', ".$PostID.", ".$UserID.", '".sqltime()."', '".db_string($OldBody)."')");
?>
<div class="post_content">
    <?=$Text->full_format($_POST['body'], isset($PermissionsInfo['site_advanced_tags']) &&  $PermissionsInfo['site_advanced_tags']);?>
</div>
<div class="post_footer">
    <span class="editedby">Last edited by <a href="user.php?id=<?=$LoggedUser['ID']?>"><?=$LoggedUser['Username']?></a> just now</span>
</div>
