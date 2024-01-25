<?php
$bbCode = new \Luminance\Legacy\Text;

// Number of users per page
define('USERS_PER_PAGE', '50');

$SelectUserID = 0;
if (isset($_REQUEST['userid']) && $_REQUEST['userid'] >0) {
    $SelectUserID = (int) $_REQUEST['userid'];
}

$GroupID = (int) $_REQUEST['groupid'];

$nextRecord = $master->db->rawQuery(
    "SELECT Name,
            Comment,
            Log
       FROM groups
      WHERE ID = ?",
    [$GroupID]
)->fetch(\PDO::FETCH_NUM);
if ($master->db->foundRows() == 0) error(0);
list($Name, $Comment, $Log) = $nextRecord;

show_header("User Group : $Name", 'jquery,bbcode,groups');

list($Page, $Limit) = page_limit(USERS_PER_PAGE);

// Main query
$users = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            ug.UserID,
            ug.Comment,
            u.Username,
            um.Uploaded,
            um.Downloaded,
            um.PermissionID,
            p.Level,
            um.GroupPermissionID,
            um.Enabled,
            um.Paranoia,
            ui.Donor,
            um.Title,
            um.LastAccess,
            ui.Avatar
       FROM users_groups AS ug
       JOIN users AS u ON u.ID=ug.UserID
       JOIN users_main AS um ON ug.UserID=um.ID
       JOIN users_info AS ui ON ug.UserID=ui.UserID
       JOIN permissions AS p ON p.ID=um.PermissionID
      WHERE ug.GroupID = ?
   ORDER BY u.Username ASC
      LIMIT $Limit",
    [$GroupID]
)->fetchAll(\PDO::FETCH_BOTH);

// Number of results (for pagination)
$results = $master->db->foundRows();

?>
<div class="thin">
    <h2>User Group <?=$Name?></h2>
    <div class="head"><a href="/groups.php">Groups</a>  &gt; <?=$Name?></div>

    <form action="groups.php" method="post">
          <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
          <input type="hidden" name="groupid" value="<?=$GroupID?>" />
          <input type="hidden" name="applyto" value="group" />
          <table class="friends_table vertical_margin">
                <tr>
                      <td valign="top">
                            <input class="long" type="text" name="name" value="<?=display_str($Name)?>" />
                      </td>
                      <td class="left" valign="top" width="110px" >
                            <input type="submit" name="action" value="change name" title="Update group name" /><br />
                      </td>
                </tr>
          </table>
          <table class="friends_table vertical_margin">
                <tr class="colhead">
                      <td colspan="2">Comment<span style="float:right;"><a href="#" onclick="$('#gcomment').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(Hide)</a></span></td>
                </tr>
                <tr id="gcomment" class="pad">
                      <td valign="top">
                          <div id="showcomment" ><?=$bbCode->full_format($Comment,true)?></div>
                          <textarea id="comment"  class="hidden long" name="comment" rows="4"><?=$Comment?></textarea>
                      </td>
                      <td class="left" valign="top" width="110px" >
                            <input type="button" id="editcombtn" value="edit" onclick="Edit_Comment()" title="Edit comment field" />
                            <input type="submit" id="updatecombtn" name="action" value="update" class="hidden" title="Update comment field" /><br />
                      </td>
                </tr>
          </table>
          <table class="friends_table vertical_margin">
                <tr class="colhead">
                      <td colspan="2">Log<span style="float:right;"><a href="#" onclick="$('#grouplog').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(View)</a></span></td>
                </tr>
                <tr id="grouplog" class="hidden pad">
                      <td valign="top" colspan="2" >
                          <div id="log" class="box pad">
                                <?=(!$Log ? 'no group history' :$bbCode->full_format($Log,true))?>
                          </div>
                      </td>
                </tr>
          </table>
          <table class="friends_table vertical_margin">
                <tr class="colhead">
                      <td colspan="2">Add users<span style="float:right;"><a href="#" onclick="$('#showuserrow').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(Hide)</a></span></td>
                </tr>
                <tr id="showuserrow" class="pad">
                      <td valign="top">
                            <div id="showuserlist" class="hidden"></div>
                            <textarea id="adduserstext" name="adduserstext" rows="1" class="long" title="Enter names or id numbers of users to add to this group"></textarea>
                      </td>
                      <td class="left" valign="top" width="110px" >
                            <input id="checkusersbutton" type="button" value="check users" onclick="Check_Users()" title="Check and validate the list of users before adding" />
                            <input id="editusersbutton" class="hidden" type="button" value="change input" onclick="Edit_Users()" title="Edit the list of users" />
                            <input id="addusersbutton" class="hidden" disabled="disabled" type="submit" name="action" value="add users" title="Add users to group" />
                      </td>
                </tr>
          </table>
          <table class="friends_table vertical_margin">
                <tr class="colhead">
                      <td colspan="5">Actions</td>
                </tr>
                <tr>
                      <td width="15%" class="noborder "></td>
                      <td width="300px" valign="top" class="noborder ">
                            <input type="submit" name="action" value="mass pm" title="Mass PM this group" />
                      </td>
                      <td width="250px" valign="top" class="noborder ">
                            <input type="submit" name="action" value="group award" <?php
                                if (!check_perms('users_edit_badges'))echo 'disabled="disabled" ';
                                ?>title="Give Award to all members of this group" />
                      </td>
                      <td width="300px" valign="top" class="noborder ">
                            <input type="submit" name="action" value="remove all" title="Remove all members from this group" />
                      </td>
                      <td width="15%" class="noborder "></td>
                </tr>
                <tr>
                      <td class="noborder "></td>
                      <td valign="top" class="noborder ">
                            <?php   $disable = check_perms('users_edit_credits')?'':'disabled="disabled"';  ?>
                            <input type="submit" name="action" value="give credits" <?=$disable?> title="Give credits" />
                            <input type="text" name="credits" style="width:80px" value="" <?=$disable?> />
                      </td>
                      <td valign="top" class="noborder " colspan="2">
                            <?php   $disable = check_perms('users_edit_ratio')?'':'disabled="disabled"';  ?>
                            <input type="submit" name="action" value="adjust download" <?=$disable?> title="Adjust download amount" />
                            <input type="text" name="download" style="width:80px" value="" <?=$disable?> />&nbsp; <strong>(GB)</strong>
                      </td>
                      <td class="noborder "></td>
                </tr>
                <tr>
                      <td colspan="5">
                              <strong>Note:</strong>
                              Mass PM is much much slower if it is not sent from the system... practically speaking only send a mass PM from yourself for groups with less than a 100 members<br/>
                              Group Award sends the same PM as you get from any award/badge.<br/>
                              Adjust Credits and Download do not send any PM themselves.<br/>
                              If you want to remove download (or credits) use a '-' before the number and note the download amount is in GB. ie. '-1024' will remove 1 TB.
                      </td>
                </tr>
          </table>
    </form>

    <div class="linkbox">
<?php
            // Pagination
            $Pages = get_pages($Page, $results, USERS_PER_PAGE, 9);
            echo $Pages;

            if ($results > 0) { ?>
                <span style="float:right;">&nbsp;&nbsp;[<a href="#" onclick="Toggle_All(false);">hide all</a>]</span>&nbsp;
                <span style="float:right;">&nbsp;&nbsp;[<a href="#" onclick="Toggle_All(true);">show all</a>]</span>&nbsp;
<?php           }   ?>
    </div>
    <div class="head">members of <?=$Name?></div>
    <div class="box pad">
<?php
if ($results == 0) {
    echo '<p>There are no users in this group</p>';
} else {
    foreach ($users as $User) {
          list($userID, $Comment, $Username, $Uploaded, $Downloaded, $class, $Level, $GroupPermID, $enabled, $paranoia, $Donor, $Title, $LastAccess, $Avatar) = $User;
    ?>
    <form action="groups.php" method="post">
          <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
          <input type="hidden" name="groupid" value="<?=$GroupID?>" />
          <input type="hidden" name="userid" value="<?=$userID?>" />
          <input type="hidden" name="applyto" value="user" />
          <table class="friends_table vertical_margin">
                <tr>
                      <td class="colhead" colspan="3">
                            <span style="float:left;"><?=format_username($userID, $Donor, true, $enabled, $class, $Title, true, $GroupPermID, true)?>
    <?php 	if (check_paranoia('ratio', $paranoia, $Level, $userID)) { ?>
                            &nbsp;Ratio: <strong><?=ratio($Uploaded, $Downloaded)?></strong>
    <?php 	} ?>
    <?php 	if (check_paranoia('uploaded', $paranoia, $Level, $userID)) { ?>
                            &nbsp;Up: <strong><?=get_size($Uploaded)?></strong>
    <?php 	} ?>
    <?php 	if (check_paranoia('downloaded', $paranoia, $Level, $userID)) { ?>
                            &nbsp;Down: <strong><?=get_size($Downloaded)?></strong>
    <?php 	} ?>
                            </span>

                            <span style="float:right;">&nbsp;&nbsp;<a href="#" class="togglelink" onclick="$('#friend<?=$userID?>').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;"><?=($SelectUserID==$userID?'(Hide)':'(View)')?></a></span>&nbsp;

    <?php 	if (check_paranoia('lastseen', $paranoia, $Level, $userID)) { ?>
                            <span style="float:right;"><?=time_diff($LastAccess)?></span>
    <?php 	} ?>
                      </td>
                </tr>
                <tr id="friend<?=$userID?>" class="<?=$SelectUserID==$userID?'':'hidden '?>friendinfo">
                      <td width="50px" valign="top">
    <?php
          if (empty($heavyInfo['DisableAvatars'])) {
                if (!empty($Avatar)) { ?>
                            <img src="<?=$Avatar?>" alt="<?=$Username?>'s avatar" width="50px" />
          <?php 	} else { ?>
                            <img src="<?=STATIC_SERVER?>common/avatars/default.png" width="50px" alt="Default avatar" />
          <?php 	}
          } ?>
                      </td>
                      <td valign="top">
                                  <textarea name="comment" rows="4" class="long"><?=$Comment?></textarea>
                      </td>
                      <td class="left" valign="top" width="100px" >
                                  <input type="submit" name="action" value="update" title="Update comment field" /><br />
                                  <input type="submit" name="action" value="remove" title="Remove <?=$Username?> from group" /><br />
                                  <input type="submit" name="action" value="pm user" title="Send <?=$Username?> a PM" /><br />

                      </td>
                </tr>
          </table>
    </form>
    <?php
    }
}
?>
    </div>
    <div class="linkbox">
        <?=$Pages?>
    </div>

</div>
<?php
show_footer();
