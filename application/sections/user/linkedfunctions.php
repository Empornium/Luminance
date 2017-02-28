<?php
include_once(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class

function link_users($UserID, $TargetID)
{
    global $DB, $LoggedUser;

    authorize();
    if (!check_perms('users_mod')) {
        error(403);
    }

    if (!is_number($UserID) || !is_number($TargetID)) {
        error(403);
    }
    if ($UserID == $TargetID) {
        return;
    }

    $DB->query("SELECT 1 FROM users_main WHERE ID IN ($UserID, $TargetID)");
    if ($DB->record_count() != 2) {
        error(403);
    }

    $DB->query("SELECT GroupID FROM users_dupes WHERE UserID = $TargetID");
    list($TargetGroupID) = $DB->next_record();
    $DB->query("SELECT u.GroupID, d.Comments FROM users_dupes AS u JOIN dupe_groups AS d ON d.ID = u.GroupID WHERE UserID = $UserID");
    list($UserGroupID, $Comments) = $DB->next_record();

    $UserInfo = user_info($UserID);
    $TargetInfo = user_info($TargetID);
    if (!$UserInfo || !$TargetInfo) {
        return;
    }

    if ($TargetGroupID) {
        if ($TargetGroupID == $UserGroupID) {
            return;
        }
        if ($UserGroupID) {
            $DB->query("UPDATE users_dupes SET GroupID = $TargetGroupID WHERE GroupID = $UserGroupID");
            $DB->query("UPDATE dupe_groups SET Comments = CONCAT('".db_string($Comments)."\n',Comments) WHERE ID = $TargetGroupID");
            $DB->query("DELETE FROM dupe_groups WHERE ID = $UserGroupID");
            $GroupID = $UserGroupID;
        } else {
            $DB->query("INSERT INTO users_dupes (UserID, GroupID) VALUES ($UserID, $TargetGroupID)");
            $GroupID = $TargetGroupID;
        }
    } elseif ($UserGroupID) {
        $DB->query("INSERT INTO users_dupes (UserID, GroupID) VALUES ($TargetID, $UserGroupID)");
        $GroupID = $UserGroupID;
    } else {
        $DB->query("INSERT INTO dupe_groups () VALUES ()");
        $GroupID = $DB->inserted_id();
        $DB->query("INSERT INTO users_dupes (UserID, GroupID) VALUES ($TargetID, $GroupID)");
        $DB->query("INSERT INTO users_dupes (UserID, GroupID) VALUES ($UserID, $GroupID)");
    }

    $AdminComment = sqltime()." - Linked accounts updated: [user]".$UserInfo['Username']."[/user] and [user]".$TargetInfo['Username']."[/user] linked by ".$LoggedUser['Username'];
    $DB->query("UPDATE users_info  AS i
                JOIN   users_dupes AS d ON d.UserID = i.UserID
                SET i.AdminComment = CONCAT('".db_string($AdminComment)."\n', i.AdminComment)
                WHERE d.GroupID = $GroupID");
}

function unlink_user($UserID)
{
    global $DB, $LoggedUser;

    authorize();
    if (!check_perms('users_mod')) {
        error(403);
    }

    if (!is_number($UserID)) {
        error(403);
    }
    $UserInfo = user_info($UserID);
    if ($UserInfo === FALSE) {
        return;
    }
    $AdminComment = sqltime()." - Linked accounts updated: [user]".$UserInfo['Username']."[/user] unlinked by ".$LoggedUser['Username'];
    $DB->query("UPDATE users_info  AS i
                JOIN   users_dupes AS d1 ON d1.UserID = i.UserID
                JOIN   users_dupes AS d2 ON d2.GroupID = d1.GroupID
                SET i.AdminComment = CONCAT('".db_string($AdminComment)."\n', i.AdminComment)
                WHERE d2.UserID = $UserID");
    $DB->query("DELETE FROM users_dupes WHERE UserID='$UserID'");
    $DB->query("DELETE g.* FROM dupe_groups AS g LEFT JOIN users_dupes AS u ON u.GroupID = g.ID WHERE u.GroupID IS NULL");
}

function delete_dupegroup($GroupID)
{
    global $DB;

    authorize();
    if (!check_perms('users_mod')) {
        error(403);
    }

    if (!is_number($GroupID)) {
        error(403);
    }

    $DB->query("DELETE FROM dupe_groups WHERE ID = '$GroupID'");
}

function dupe_comments($GroupID, $Comments)
{
    global $DB, $Text, $LoggedUser;

    authorize();
    if (!check_perms('users_mod')) error(403);
    if (!is_number($GroupID)) error(0);

    $DB->query("SELECT Comments, SHA1(Comments) AS CommentHash FROM dupe_groups WHERE ID = '$GroupID'");
    list($OldComment, $OldCommentHash) = $DB->next_record();
    if ($OldCommentHash != sha1($Comments)) {
        $AdminComment = sqltime()." - Linked accounts updated: Comments changed from [bg=#f3f3f3]". ($OldComment)."[/bg] to [bg=#f3f3f3]". ($Comments)."[/bg] by ".$LoggedUser['Username'];
        if ($_POST['form_comment_hash'] == $OldCommentHash) {
            $DB->query("UPDATE dupe_groups SET Comments = '".db_string($Comments)."' WHERE ID = '$GroupID'");
        } else {
            $DB->query("UPDATE dupe_groups SET Comments = CONCAT('".db_string($Comments)."\n',Comments) WHERE ID = '$GroupID'");
        }

        $DB->query("UPDATE users_info  AS i
                    JOIN   users_dupes AS d ON d.UserID = i.UserID
                    SET i.AdminComment = CONCAT('".db_string($AdminComment)."\n', i.AdminComment)
                    WHERE d.GroupID = $GroupID");
    }
}

function user_dupes_table($UserID, $Username)
{
    global $DB, $LoggedUser;
    $Text = new TEXT;

    if (!check_perms('users_mod')) {
        error(403);
    }
    if (!is_number($UserID)) {
        error(403);
    }

    $DB->query("SELECT d.ID, d.Comments, SHA1(d.Comments) AS CommentHash
                FROM dupe_groups AS d
                JOIN users_dupes AS u ON u.GroupID = d.ID
                WHERE u.UserID = $UserID");
    if (list($GroupID, $Comments, $CommentHash) = $DB->next_record()) {
        $DB->query("SELECT m.ID
                    FROM users_main AS m
                    JOIN users_dupes AS d ON m.ID = d.UserID
                    WHERE d.GroupID = $GroupID
                    ORDER BY m.ID ASC");
        $DupeCount = $DB->record_count();
        $Dupes = $DB->to_array('ID');
    } else {
        $DupeCount = 0;
        $Dupes = array();
    }

/*  This breaks badly for users with large numbers of IPs
    $DB->query("SELECT uh.UserID AS UserID, uh.IP
                  FROM users_history_ips AS uh
                  JOIN users_history_ips AS me ON uh.IP=me.IP
                 WHERE uh.IP != '127.0.0.1' AND uh.IP !='' AND me.UserID = $UserID AND uh.UserID != $UserID
              GROUP BY UserID, IP
              ORDER BY UserID, IP
                 LIMIT 50"); */

    /* LIMIT results to latest 500 IPs from History */
    $DB->query("SELECT uh.UserID AS UserID, uh.IP
                  FROM users_history_ips AS uh
                  JOIN (SELECT IP, UserID FROM users_history_ips
                 WHERE IP != '127.0.0.1' AND IP !='' AND UserID = $UserID
              ORDER BY StartTime DESC
                 LIMIT 500) AS me ON uh.IP=me.IP
                 WHERE uh.UserID!=$UserID
              GROUP BY UserID, IP
              ORDER BY UserID, IP
                 LIMIT 50");

    $IPDupeCount = $DB->record_count();
    $IPDupes = $DB->to_array();
    if ($IPDupeCount>0) {
?>
        <div class="head">
            <span style="float:left;"><?=$IPDupeCount?> record<?=(($IPDupeCount == 1)?'':'s')?> with the same IP address</span>
            <span style="float:right;"><a href="#" id="iplinkedbutton" onclick="return Toggle_view('iplinked');">(Hide)</a></span>&nbsp;
        </div>
        <div class="box">
            <table width="100%" id="iplinkeddiv" class="shadow">
<?php
            foreach ($IPDupes AS $IPDupe) {
                list($EUserID, $IP) = $IPDupe;
                $DupeInfo = user_info($EUserID);

            $Row = ($Row == 'a') ? 'b' : 'a';
?>
            <tr class="row<?=$Row?>">
                <td align="left">
                    <?=format_username($EUserID, $DupeInfo['Username'], $DupeInfo['Donor'], $DupeInfo['Warned'], $DupeInfo['Enabled'], $DupeInfo['PermissionID'])?>
                </td>
                <td align="left">
                    <?=display_ip($IP, $DupeInfo['ipcc'])?>
                </td>
                <td>
<?php
                    if ( !array_key_exists($EUserID, $Dupes) ) {
?>
                        [<a href="user.php?action=dupes&dupeaction=link&auth=<?=$LoggedUser['AuthKey']?>&userid=<?=$UserID?>&targetid=<?=$EUserID?>">link</a>]
<?php
                    }
?>
                </td>
            </tr>
<?php
            }
?>
            </table>
        </div>
<?php
    }

    $DB->query("SELECT um.UserID, um.Email
                  FROM users_history_emails AS um
                  JOIN users_history_emails AS me ON um.Email=me.Email
                 WHERE um.Email != '' AND me.UserID = $UserID AND um.UserID != $UserID
              ORDER BY UserID, Email
                LIMIT 50");

    $EDupeCount = $DB->record_count();
    $EDupes = $DB->to_array();
    if ($EDupeCount>0) {
?>
        <div class="head">
            <span style="float:left;"><?=$EDupeCount?> record<?=(($EDupeCount == 1)?'':'s')?> with the same email address</span>
            <span style="float:right;"><a href="#" id="elinkedbutton" onclick="return Toggle_view('elinked');">(Hide)</a></span>&nbsp;
        </div>
        <div class="box">
            <table width="100%" id="elinkeddiv" class="shadow">
<?php
            $i = 0;
            foreach ($EDupes AS $EDupe) {
                list($EUserID, $EEmail, $EType1, $EType2) = $EDupe;
                $i++;
                $DupeInfo = user_info($EUserID);

            $Row = ($Row == 'a') ? 'b' : 'a';
?>
            <tr class="row<?=$Row?>">
                <td align="left">
                    <?=format_username($EUserID, $DupeInfo['Username'], $DupeInfo['Donor'], $DupeInfo['Warned'], $DupeInfo['Enabled'], $DupeInfo['PermissionID'])?>
                </td>
                <td align="left">
                    <?=$EEmail?>
                </td>
                <td>
<?php
                    if ( !array_key_exists($EUserID, $Dupes) ) {   ?>
                        [<a href="user.php?action=dupes&dupeaction=link&auth=<?=$LoggedUser['AuthKey']?>&userid=<?=$UserID?>&targetid=<?=$EUserID?>">link</a>]
<?php                   }       ?>
                </td>
            </tr>
<?php
            }
?>
            </table>
        </div>
<?php
    }
?>
        <div class="head">
            <span style="float:left;"><?=$DupeCount?max($DupeCount - 1, 0).' ':''?>Linked Account<?=(($DupeCount == 2)?'':'s')?></span>
            <span style="float:right;"><a href="#" id="linkedbutton" onclick="return Toggle_view('linked');">(Hide)</a></span>&nbsp;
        </div>
       <div class="box">
        <form method="POST" id="linkedform">
            <input type="hidden" name="action" value="dupes" />
            <input type="hidden" name="dupeaction" value="update" />
            <input type="hidden" name="userid" value="<?=$UserID?>" />
            <input type="hidden" id="auth" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <input type="hidden" id="form_comment_hash" name="form_comment_hash" value="<?=$CommentHash?>" />
                 <table width="100%"  id="linkeddiv" class="linkedaccounts shadow">
                    <?=($DupeCount?'<tr >':'')?>
<?php
    $i = 0;
    foreach ($Dupes as $Dupe) {
        $i++;
        list($DupeID) = $Dupe;
        $DupeInfo = user_info($DupeID);
        $Row = ($Row == 'b') ? 'a' : 'b';
?>
                    <td class="row<?=$Row?>" align="left"><?=format_username($DupeID, $DupeInfo['Username'], $DupeInfo['Donor'], $DupeInfo['Warned'], $DupeInfo['Enabled'], $DupeInfo['PermissionID'])?>
                        [<a href="user.php?action=dupes&dupeaction=remove&auth=<?=$LoggedUser['AuthKey']?>&userid=<?=$UserID?>&removeid=<?=$DupeID?>" onClick="return confirm('Are you sure you wish to remove <?=$DupeInfo['Username']?> from this group?');">x</a>]</td>
<?php
        if ($i == 4) {
            $i = 0;
            echo '</tr><tr>';
        }
    }
    if ($DupeCount) {
        for ($j = $i; $j < 4; $j++) {
            echo '<td>&nbsp;</td>';
        }
?>
                    </tr>
                    <tr class="rowa">
                        <td colspan="5" align="left"><strong>Comments:</strong></td>
                    </tr>
                    <tr class="rowa">
                        <td colspan="5" align="left">
                            <div id="dupecomments" class="<?=($DupeCount?'':'hidden')?>"><?=$Text->full_format($Comments);?></div>
                            <div id="editdupecomments" class="<?=$DupeCount?'hidden':''?>">
                                <textarea id="dupecommentsbox" name="dupecomments" onkeyup="resize('dupecommentsbox');" cols="65" rows="5" style="width:98%;"><?=display_str($Comments)?></textarea>
                                                <input type="submit" name="submitcomment" value="Save" />
                            </div>
                            <span style="float:right;"><a href="#" onClick="$('#dupecomments').toggle(); $('#editdupecomments').toggle(); resize('dupecommentsbox');return false;">(Edit comments)</a>
                        </td>
                    </tr>
<?php 	}	?>
                    <tr>
                        <td colspan="5" align="left">
                                        <label for="target">Link this user with: </label>
                                        <input type="text" name="target" id="target" title="Enter the username of the account you wish to link this to" />
                                        <input type="submit" name="submitlink" value="Link" id="submitlink" />
                                    </td>
                    </tr>
                </table>
        </form>
            </div>
<?php
}
