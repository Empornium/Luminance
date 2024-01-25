<?php
/************************************************************************
//------------// Main friends page //----------------------------------//
This page lists a user's friends.

There's no real point in caching this page. I doubt users load it that
much.
************************************************************************/

// Number of users per page
define('FRIENDS_PER_PAGE', '50');

show_header('Friends', 'jquery');

$userID = $activeUser['ID'];

$FType = isset($_REQUEST['type'])?$_REQUEST['type']:'friends';
if (!in_array($FType, ['friends', 'blocked'])) error(0);

list($Page, $Limit) = page_limit(FRIENDS_PER_PAGE);

// Main query
$friends = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            f.FriendID,
            f.Comment,
            fu.Username,
            fum.Uploaded,
            fum.Downloaded,
            fum.PermissionID,
            fp.Level,
            fum.GroupPermissionID,
            fum.Enabled,
            fum.Paranoia,
            fui.Donor,
            fum.Title,
            fum.LastAccess,
            fui.Avatar
       FROM friends AS f
       JOIN users AS fu ON  f.FriendID=fu.ID
       JOIN users_main AS fum ON f.FriendID=fum.ID
       JOIN users_info AS fui ON f.FriendID=fui.UserID
       JOIN permissions AS fp ON fp.ID=fum.PermissionID
      WHERE f.UserID = ?
        AND f.Type = ?
   ORDER BY Username
      LIMIT $Limit",
    [$userID, $FType]
)->fetchAll(\PDO::FETCH_BOTH);

// Number of results (for pagination)
$results = $master->db->foundRows();

// Start printing stuff
?>
<div class="thin">
      <h2><?=($FType == 'friends' ? 'friends' : 'blocked users')?></h2>
    <div class="linkbox">
<?php
// Pagination
$Pages = get_pages($Page, $results, FRIENDS_PER_PAGE, 9);
echo $Pages;

if ($results > 0) {
?>
        <span style="float:right;">&nbsp;&nbsp;[<a href="#" onclick="Toggle_All(false);">hide all</a>]</span>&nbsp;
        <span style="float:right;">&nbsp;&nbsp;[<a href="#" onclick="Toggle_All(true);">show all</a>]</span>&nbsp;
<?php
}
        $OtherType=($FType == 'friends' ? 'blocked' : 'friends');
?>
        <span style="float:left;">&nbsp;[<a href="/friends.php?type=<?=$OtherType?>" ><?=$OtherType?> list</a>]</span>&nbsp;
    </div>
    <div class="head"><?=ucfirst($FType)?> list</div>
    <div class="box pad">
<?php
if ($results == 0) {
    echo '<p>You have no '.($FType == 'friends' ? 'friends! :(' : 'blocked users').'</p>';
} else {
    // Start printing out friends
    foreach ($friends as $Friend) {
          list($FriendID, $Comment, $Username, $Uploaded, $Downloaded, $class, $Level, $GroupPermID, $enabled, $paranoia, $Donor, $Title, $LastAccess, $Avatar) = $Friend;
    ?>
    <form action="friends.php" method="post">
          <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
          <input type="hidden" name="type" value="<?=$FType?>" />
          <table class="friends_table vertical_margin">
                <tr>
                      <td class="colhead" colspan="3">
                            <span style="float:left;"><?=format_username($FriendID, $Donor, true, $enabled, $class, $Title, true, $GroupPermID)?>
    <?php 	if (check_paranoia('ratio', $paranoia, $Level, $FriendID)) { ?>
                            &nbsp;Ratio: <strong><?=ratio($Uploaded, $Downloaded)?></strong>
    <?php 	} ?>
    <?php 	if (check_paranoia('uploaded', $paranoia, $Level, $FriendID)) { ?>
                            &nbsp;Up: <strong><?=get_size($Uploaded)?></strong>
    <?php 	} ?>
    <?php 	if (check_paranoia('downloaded', $paranoia, $Level, $FriendID)) { ?>
                            &nbsp;Down: <strong><?=get_size($Downloaded)?></strong>
    <?php 	} ?>
                            </span>

                            <span style="float:right;">&nbsp;&nbsp;<a href="#" class="togglelink" onclick="$('#friend<?=$FriendID?>').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(View)</a></span>&nbsp;

    <?php 	if (check_paranoia('lastseen', $paranoia, $Level, $FriendID)) { ?>
                            <span style="float:right;"><?=time_diff($LastAccess)?></span>
    <?php 	} ?>
                      </td>
                </tr>
                <tr id="friend<?=$FriendID?>" class="hidden friendinfo">
                      <td width="50px" valign="top">
    <?php
          if (empty($heavyInfo['DisableAvatars'])) {
                if (!empty($Avatar)) {
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
