<?php
//Props to Leto of StC.
use Luminance\Entities\Restriction;
if (
    !check_perms('users_view_invites') &&
    !check_perms('users_disable_users') &&
    !check_perms('users_edit_invites') &&
    !check_perms('users_disable_any')
) { error(404); }
show_header("Manipulate Invite Tree");

if ($_POST['id']) {
    authorize();

    if (!is_integer_string($_POST['id'])) { error(403); }
    if (!$_POST['comment']) { error('Please enter a comment to add to the users affected.');
    } else { $Comment = $_POST['comment']; }
    $userID = $_POST['id'];
    list($TreePosition, $TreeID, $TreeLevel, $MaxPosition) = $master->db->rawQuery("SELECT
        t1.TreePosition,
        t1.TreeID,
        t1.TreeLevel,
        (SELECT
            t2.TreePosition FROM invite_tree AS t2
            WHERE TreeID=t1.TreeID AND TreeLevel=t1.TreeLevel AND t2.TreePosition>t1.TreePosition
            ORDER BY TreePosition LIMIT 1
        ) AS MaxPosition
        FROM invite_tree AS t1
        WHERE t1.UserID = ?",
        [$userID]
    )->fetch(\PDO::FETCH_NUM);
    if (!$MaxPosition) { $MaxPosition = 1000000; } // $MaxPermission is null if the user is the last one in that tree on that level
    if (!$TreeID) { return; }
        $BanList = $master->db->rawQuery("
            SELECT
            UserID
            FROM invite_tree
            WHERE TreeID = ?
            AND TreePosition > ?
            AND TreePosition < ?
            AND TreeLevel > ?
            ORDER BY TreePosition",
        [$TreeID, $TreePosition, $MaxPosition, $TreeLevel]
    )->fetchAll(\PDO::FETCH_COLUMN);

    if ($BanList) {
        foreach ($BanList as $Key => $InviteeID) {
            if ($_POST['perform']=='nothing') {
                $AdminComment = $master->db->rawQuery("SELECT
                        AdminComment
                        FROM users_info
                        WHERE UserID = ?",
                    [$InviteeID]
                )->fetchColumn();
                $DBComment = "{$Comment}\n\n{$AdminComment}";
                $master->db->rawQuery("UPDATE
                        users_info
                        SET AdminComment = ?
                        WHERE UserID = ?",
                    [$DBComment, $InviteeID]
                );
                $Msg = "Successfully commented on entire invite tree!";
            } elseif ($_POST['perform']=='disable') {
                $AdminComment = $master->db->rawQuery("SELECT
                        AdminComment
                        FROM users_info
                        WHERE UserID = ?",
                    [$InviteeID]
                )->fetchColumn();
                $DBComment = "{$Comment}\n\n{$AdminComment}";
                $master->db->rawQuery("UPDATE
                        users_info
                        SET AdminComment = ?
                        WHERE UserID = ?",
                    [$DBComment, $InviteeID]
                );
                $master->db->rawQuery("UPDATE
                        users_main
                        SET Enabled = '2'
                        WHERE ID = ?",
                    [$InviteeID]
                );
                $Msg = "Successfully banned entire invite tree!";
            } elseif ($_POST['perform']=='inviteprivs') {

                $restriction = new Restriction;
                $restriction->setFlags(Restriction::INVITE);
                $restriction->UserID  = $InviteeID;
                $restriction->StaffID = $activeUser['ID'];
                $restriction->Created = new \DateTime();
                $restriction->Comment = $Comment;
                $master->repos->restrictions->save($restriction);

                $AdminComment = $master->db->rawQuery("SELECT
                        AdminComment
                        FROM users_info
                        WHERE UserID = ?",
                    [$InviteeID]
                )->fetchColumn();
                $DBComment = "{$Comment}\n\n{$AdminComment}";
                $master->db->rawQuery("UPDATE
                        users_info
                        SET AdminComment = ?
                        WHERE UserID = ?",
                    [$DBComment, $InviteeID]
                );
                $Msg = "Successfully removed invite privileges from entire tree!";
            } else {
                error(403);
            }
        }
    }
}
?>

<div class="thin">
    <h2>Manage Invite tree</h2>
    <?php  if ($Msg) { ?>
    <div class="center">
        <p style="color: red;text-align:center;"><?=$Msg?></p>
    </div>
    <?php  } ?>
    <form action="" method="post">
        <input type="hidden" id="action" name="action" value="manipulate_tree" />
        <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
        <table>
            <tr>
                <td class="label"><strong>UserID</strong></td>
                <td>
                    <input type="text" size="10" name="id" id="id" />
                </td>
                <td class="label"><strong>Mandatory comment!</strong></td>
                <td>
                    <input type="text" size="40" name="comment" id="comment" />
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Action: </strong></td>
                <td colspan="2">
                    <select name="perform">
                        <option value="nothing"<?php  if ($_POST['perform']==='nothing') {echo ' selected="selected"';}?>>Do nothing</option>
                        <option value="disable"<?php  if ($_POST['perform']==='disable') {echo ' selected="selected"';}?>>Disable entire tree</option>
                        <option value="inviteprivs"<?php  if ($_POST['perform']==='inviteprivs') {echo ' selected="selected"';}?>>Disable invites privileges</option>
                    </select>
                </td><td align="left">
                    <input type="submit" value="Go" />
                </td>
            </tr>
        </table>
    </form>
</div>

<?php
show_footer();
