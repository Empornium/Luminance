<?php
/************************************************************************
//------------// Main friends page //----------------------------------//
This page lists a user's friends.

There's no real point in caching this page. I doubt users load it that
much.
************************************************************************/

// Number of users per page
define('FRIENDS_PER_PAGE', '50');

show_header('Friends','jquery');

$UserID = $LoggedUser['ID'];

$FType = isset($_REQUEST['type'])?$_REQUEST['type']:'friends';
if(!in_array($FType, array('friends','blocked'))) error(0);

list($Page,$Limit) = page_limit(FRIENDS_PER_PAGE);

// Main query
$DB->query("SELECT
    SQL_CALC_FOUND_ROWS
    f.FriendID,
    f.Comment,
    m.Username,
    m.Uploaded,
    m.Downloaded,
    m.PermissionID,
    p.Level,
    m.GroupPermissionID,
    m.Enabled,
    m.Paranoia,
    i.Donor,
    i.Warned,
    m.Title,
    m.LastAccess,
    i.Avatar
    FROM friends AS f
    JOIN users_main AS m ON f.FriendID=m.ID
    JOIN users_info AS i ON f.FriendID=i.UserID
    JOIN permissions AS p ON p.ID=m.PermissionID
    WHERE f.UserID='$UserID'
        AND f.Type='$FType'
    ORDER BY Username LIMIT $Limit");
$Friends = $DB->to_array(false, MYSQLI_BOTH, array(9)); # This number should be the proper idx for m.Paranoia!

// Number of results (for pagination)
$DB->query('SELECT FOUND_ROWS()');
list($Results) = $DB->next_record();

// Start printing stuff
?>
<div class="thin">
      <h2><?=( $FType=='friends'?'friends':'blocked users' )?></h2>
    <div class="linkbox">
<?php
// Pagination
$Pages=get_pages($Page,$Results,FRIENDS_PER_PAGE,9);
echo $Pages;

if ($Results > 0) {
?>
        <span style="float:right;">&nbsp;&nbsp;[<a href="#" onclick="Toggle_All(false);">hide all</a>]</span>&nbsp;
        <span style="float:right;">&nbsp;&nbsp;[<a href="#" onclick="Toggle_All(true);">show all</a>]</span>&nbsp;
<?php
}
        $OtherType=( $FType=='friends'?'blocked':'friends' );
?>
        <span style="float:left;">&nbsp;[<a href="friends.php?type=<?=$OtherType?>" ><?=$OtherType?> list</a>]</span>&nbsp;
    </div>
    <div class="head"><?=ucfirst($FType)?> list</div>
    <div class="box pad">
<?php
if ($Results == 0) {
    echo '<p>You have no '.( $FType=='friends'?'friends! :(':'blocked users' ).'</p>';
} else {
    // Start printing out friends
    foreach ($Friends as $Friend) {
          list($FriendID, $Comment, $Username, $Uploaded, $Downloaded, $Class, $Level, $GroupPermID, $Enabled, $Paranoia, $Donor, $Warned, $Title, $LastAccess, $Avatar) = $Friend;
    ?>
    <form action="friends.php" method="post">
          <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
          <input type="hidden" name="type" value="<?=$FType?>" />
          <table class="friends_table vertical_margin">
                <tr>
                      <td class="colhead" colspan="3">
                            <span style="float:left;"><?=format_username($FriendID, $Username, $Donor, $Warned, $Enabled, $Class, $Title, true, $GroupPermID)?>
    <?php 	if (check_paranoia('ratio', $Paranoia, $Level, $FriendID)) { ?>
                            &nbsp;Ratio: <strong><?=ratio($Uploaded, $Downloaded)?></strong>
    <?php 	} ?>
    <?php 	if (check_paranoia('uploaded', $Paranoia, $Level, $FriendID)) { ?>
                            &nbsp;Up: <strong><?=get_size($Uploaded)?></strong>
    <?php 	} ?>
    <?php 	if (check_paranoia('downloaded', $Paranoia, $Level, $FriendID)) { ?>
                            &nbsp;Down: <strong><?=get_size($Downloaded)?></strong>
    <?php 	} ?>
                            </span>

                            <span style="float:right;">&nbsp;&nbsp;<a href="#" class="togglelink" onclick="$('#friend<?=$FriendID?>').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(View)</a></span>&nbsp;

    <?php 	if (check_paranoia('lastseen', $Paranoia, $Level, $FriendID)) { ?>
                            <span style="float:right;"><?=time_diff($LastAccess)?></span>
    <?php 	} ?>
                      </td>
                </tr>
                <tr id="friend<?=$FriendID?>" class="hidden friendinfo">
                      <td width="50px" valign="top">
    <?php
          if (empty($HeavyInfo['DisableAvatars'])) {
                if (!empty($Avatar)) {
                      if (check_perms('site_proxy_images')) {
                            $Avatar = '//'.SITE_URL.'/image.php?c=1&i='.urlencode($Avatar);
                      }
          ?>
                                  <img src="<?=$Avatar?>" alt="<?=$Username?>'s avatar" width="50px" />
          <?php 	} else { ?>
                                  <img src="<?=STATIC_SERVER?>common/avatars/default.png" width="50px" alt="Default avatar" />
          <?php 	}
          } ?>
                      </td>
                      <td valign="top">
                                  <input type="hidden" name="friendid" value="<?=$FriendID?>" />

                                  <textarea name="comment" rows="4" class="long"><?=display_str($Comment)?></textarea>
                      </td>
                      <td class="left" valign="top" width="100px" >
                                  <input type="submit" name="action" value="Update" /><br />
                                  <input type="submit" name="action" value="<?=($FType=='friends'?'Defriend':'Unblock')?>" /><br />
                                  <input type="submit" name="action" value="Contact" /><br />
                      </td>
                </tr>
          </table>
    </form>
    <?php
    } // while
?>
<script type="text/javascript">
        function Toggle_All(open)
        {
            if (open) {
                $('.friendinfo').show(); // weirdly the $ selector chokes when trying to set multiple elements innerHTML with a class selector
                jQuery('.togglelink').html('(Hide)');
            } else {
                $('.friendinfo').hide();
                jQuery('.togglelink').html('(View)');
            }

            return false;
        }
</script>
<?php
} // end else has results
// close <div class="box pad">
?>
    </div>
    <div class="linkbox">
        <?=$Pages?>
    </div>
<?php  // close <div class="thin">  ?>
</div>
<?php
show_footer();
