<?php

function link_users($userID, $TargetID)
{
    global $master, $activeUser;

    authorize();
    if (!check_perms('users_mod')) {
        error(403);
    }

    if (!is_integer_string($userID) || !is_integer_string($TargetID)) {
        error(403);
    }
    if ($userID == $TargetID) {
        return;
    }

    $master->db->rawQuery(
        "SELECT 1
           FROM users_main
          WHERE ID IN (?, ?)",
        [$userID, $TargetID]
    );
    if ($master->db->foundRows() != 2) {
        error(403);
    }

    $targetGroupID = $master->db->rawQuery(
        "SELECT GroupID
           FROM users_dupes
          WHERE UserID = ?",
        [$TargetID]
    )->fetchColumn();

    list($userGroupID, $comments) = $master->db->rawQuery(
        "SELECT u.GroupID,
                d.Comments
           FROM users_dupes AS u
           JOIN dupe_groups AS d ON d.ID = u.GroupID
          WHERE UserID = ?",
        [$userID]
    )->fetch(\PDO::FETCH_NUM);

    $UserInfo = user_info($userID);
    $TargetInfo = user_info($TargetID);
    if (!$UserInfo || !$TargetInfo) {
        return;
    }

    if ($targetGroupID) {
        if ($targetGroupID == $userGroupID) {
            return;
        }
        if ($userGroupID) {
            $master->db->rawQuery(
                "UPDATE users_dupes
                    SET GroupID = ?
                  WHERE GroupID = ?",
                [$targetGroupID, $userGroupID]
            );
            $master->db->rawQuery(
                "UPDATE dupe_groups
                    SET Comments = CONCAT(?, Comments)
                  WHERE ID = ?",
                ["{$comments}\n", $targetGroupID]
            );
            $master->db->rawQuery(
                "DELETE
                   FROM dupe_groups
                  WHERE ID = ?",
                [$userGroupID]
            );
            $GroupID = $userGroupID;
        } else {
            $master->db->rawQuery(
                "INSERT INTO users_dupes (UserID, GroupID)
                      VALUES (?, ?)",
                [$userID, $targetGroupID]
            );
            $GroupID = $targetGroupID;
        }
    } elseif ($userGroupID) {
        $master->db->rawQuery(
            "INSERT INTO users_dupes (UserID, GroupID)
                  VALUES (?, ?)",
            [$TargetID, $userGroupID]
        );
        $GroupID = $userGroupID;
    } else {
        $master->db->rawQuery("INSERT INTO dupe_groups () VALUES ()");
        $GroupID = $master->db->lastInsertID();
        $master->db->rawQuery(
            "INSERT INTO users_dupes (UserID, GroupID)
                  VALUES (?, ?)",
            [$TargetID, $GroupID]
        );
        $master->db->rawQuery(
            "INSERT INTO users_dupes (UserID, GroupID)
                  VALUES (?, ?)",
            [$userID, $GroupID]
        );
    }

    $AdminComment = sqltime()." - Linked accounts updated: [user]".$UserInfo['ID']."[/user] and [user]".$TargetInfo['ID']."[/user] linked by ".$activeUser['Username'];
    $master->db->rawQuery(
        "UPDATE users_info AS i
           JOIN users_dupes AS d
             ON d.UserID = i.UserID
            SET i.AdminComment = CONCAT(?, i.AdminComment)
          WHERE d.GroupID = ?",
        ["{$AdminComment}\n", $GroupID]
    );
}

function unlink_user($userID)
{
    global $master, $activeUser;

    authorize();
    if (!check_perms('users_mod')) {
        error(403);
    }

    if (!is_integer_string($userID)) {
        error(403);
    }
    $UserInfo = user_info($userID);
    if ($UserInfo === FALSE) {
        return;
    }
    $AdminComment = sqltime()." - Linked accounts updated: [user]".$UserInfo['ID']."[/user] unlinked by ".$activeUser['Username'];
    $master->db->rawQuery(
        "UPDATE users_info AS i
           JOIN users_dupes AS d1
             ON d1.UserID = i.UserID
           JOIN users_dupes AS d2
             ON d2.GroupID = d1.GroupID
            SET i.AdminComment = CONCAT(?, i.AdminComment)
          WHERE d2.UserID = ?",
        ["{$AdminComment}\n", $userID]
    );
    $master->db->rawQuery(
        "DELETE
           FROM users_dupes
          WHERE UserID = ?",
        [$userID]
    );

    $master->db->rawQuery(
        "DELETE g
           FROM dupe_groups AS g
      LEFT JOIN users_dupes AS u ON u.GroupID = g.ID
          WHERE u.GroupID IS NULL"
    );
}

function delete_dupegroup($GroupID)
{
    global $master;

    authorize();
    if (!check_perms('users_mod')) {
        error(403);
    }

    if (!is_integer_string($GroupID)) {
        error(403);
    }

    $master->db->rawQuery(
        "DELETE
           FROM dupe_groups
          WHERE ID = ?",
        [$GroupID]
    );
}

function dupe_comments($GroupID, $Comments)
{
    global $master, $bbCode, $activeUser;

    authorize();
    if (!check_perms('users_mod')) error(403);
    if (!is_integer_string($GroupID)) error(0);

    list($oldComment, $oldCommentHash) = $master->db->rawQuery(
        "SELECT Comments,
                SHA1(Comments) AS CommentHash
           FROM dupe_groups
          WHERE ID = ?",
        [$GroupID]
    )->fetch(\PDO::FETCH_NUM);

    if ($oldCommentHash != sha1($Comments)) {
        $AdminComment = sqltime()." - Linked accounts updated: Comments changed from [bg=#aaaaaa40]". ($oldComment)."[/bg] to [bg=#aaaaaa40]". ($Comments)."[/bg] by ".$activeUser['Username'];
        if ($_POST['form_comment_hash'] == $oldCommentHash) {
            $master->db->rawQuery(
                "UPDATE dupe_groups
                    SET Comments = ?
                  WHERE ID = ?",
                [$Comments, $GroupID]
            );
        } else {
            $master->db->rawQuery(
                "UPDATE dupe_groups
                    SET Comments = CONCAT(?, Comments)
                  WHERE ID = ?",
                ["{$Comments}\n", $GroupID]
            );
        }

        $master->db->rawQuery(
            "UPDATE users_info AS i
               JOIN users_dupes AS d
                 ON d.UserID = i.UserID
                SET i.AdminComment = CONCAT(?, i.AdminComment)
              WHERE d.GroupID = ?",
            ["{$AdminComment}\n", $GroupID]
        );
    }
}

function user_dupes_table($userID, $Username)
{
    global $master, $activeUser;
    $bbCode = new \Luminance\Legacy\Text;

    if (!check_perms('users_mod')) {
        error(403);
    }
    if (!is_integer_string($userID)) {
        error(403);
    }

    $dupeGroup = $master->db->rawQuery(
        'SELECT d.ID
           FROM dupe_groups AS d
           JOIN users_dupes AS u ON u.GroupID = d.ID
          WHERE u.UserID = ?',
          [$userID]
    )->fetchColumn();

    if (is_integer_string($dupeGroup)) {
        $Dupes = $master->db->rawQuery(
            'SELECT m.ID
               FROM users_main AS m
               JOIN users_dupes AS d ON m.ID = d.UserID
              WHERE d.GroupID = ?
           ORDER BY m.ID ASC',
           [$dupeGroup]
        )->fetchAll(\PDO::FETCH_COLUMN);
    } else {
        $Dupes = [];
    }

    /* LIMIT results to latest 500 IPs from History */
    $ipids = $master->db->rawQuery(
        'SELECT IPID
           FROM users_history_ips
          WHERE IPID IS NOT NULL
            AND UserID = ?
       GROUP BY IPID
       ORDER BY MAX(StartTime) DESC
          LIMIT 500',
        [$userID]
    )->fetchAll(\PDO::FETCH_COLUMN);

    $IPDupeCount = 0;
    $IPDupes = [];

    /* Search for duplicates in the DB, halt at 50 */
    foreach ($ipids as $ipid) {
        $newIPDupes = $master->db->rawQuery(
            "SELECT uh.UserID AS UserID,
                    INET6_NTOA(ips.StartAddress),
                    e.Address
               FROM users_history_ips AS uh
          LEFT JOIN users AS u ON u.ID = uh.UserID
          LEFT JOIN emails AS e ON e.ID = u.EmailID
          LEFT JOIN ips ON ips.ID = uh.IPID
              WHERE uh.UserID != ?
                AND uh.IPID = ?
           GROUP BY uh.UserID, uh.IPID
              LIMIT 50",
            [$userID, $ipid]
        )->fetchAll(\PDO::FETCH_BOTH);
        $IPDupeCount = $master->db->foundRows();
        $IPDupes = array_merge($IPDupes, $newIPDupes);
        if ($IPDupeCount >= 50) {
            break;
        }
    }

    if ($IPDupeCount>0) {
        if ($IPDupeCount>50) {
            $IPDupeCount = 50;
            $IPDupes = array_slice($IPDupes, 0, 50);
        }
?>
        <div class="head">
            <span style="float:left;"><?=$IPDupeCount?> record<?=(($IPDupeCount == 1)?'':'s')?> with the same IP address</span>
            <span style="float:right;"><a href="#" id="iplinkedbutton" onclick="return Toggle_view('iplinked');">(Hide)</a></span>&nbsp;
        </div>
        <div class="box">
            <table width="100%" id="iplinkeddiv" class="shadow">
<?php
            foreach ($IPDupes AS $IPDupe) {
                list($EUserID, $IP, $Email) = $IPDupe;
                $DupeInfo = user_info($EUserID);

            $row = ($row ?? 'a') == 'a' ? 'b' : 'a';
?>
            <tr class="row<?=$row?>">
                <td align="left">
                    <?=format_username($EUserID, $DupeInfo['Donor'], true, $DupeInfo['Enabled'], $DupeInfo['PermissionID'])?>
                </td>
                <td align="left">
                <?php if (check_perms('users_view_email')) { ?>
                    <?=display_str($Email)?>
                <?php } ?>
                </td>
                <td align="left">
                <?php if (check_perms('users_view_ips')) { ?>
                    <?=display_ip($IP, $DupeInfo['ipcc'])?>
                <?php } ?>
                </td>
                <td>
<?php
                    if (!in_array(intval($EUserID), $Dupes)) {
?>
                        [<a href="/user.php?action=dupes&dupeaction=link&auth=<?=$activeUser['AuthKey']?>&userid=<?=$userID?>&targetid=<?=$EUserID?>">link</a>]
<?php
                    }
?>
                    [<a href="/tools.php?action=compare_users&usera=<?=$userID?>&userb=<?=$EUserID?>">compare</a>]
                </td>
            </tr>
<?php
            }
?>
            </table>
        </div>
<?php
    }
}
