<?php
if (!check_perms('users_view_invites')) { error(403); }
show_header('Invite Pool');
define('INVITES_PER_PAGE', 50);
list($Page, $Limit) = page_limit(INVITES_PER_PAGE);

if (!empty($_POST['submit']) && check_perms('users_edit_invites')) {
    authorize();
    if (is_integer_string($_POST['id'])) {
        $invite = $master->repos->invites->load($_POST['id']);

        if (!$invite instanceof Luminance\Entities\Invite) {
            error(0);
        }
    }

    switch($_POST['submit']) {
        case 'Delete':
            $master->repos->invites->delete($invite);
            break;
        case 'Resend':
            $master->emailManager->sendInviteEmail($invite);
            break;
    }
}

if (!empty($_GET['search'])) {
    $Search = $_GET['search'];
} else {
    $Search = "";
}

$sql = "SELECT
    SQL_CALC_FOUND_ROWS
    u.ID,
    u.Username,
    um.PermissionID,
    um.Enabled,
    ui.Donor,
    i.ID,
    i.Anon,
    i.Expires,
    i.Email
    FROM invites as i
    JOIN users AS u ON u.ID=i.InviterID
    JOIN users_main AS um ON um.ID=i.InviterID
    JOIN users_info AS ui ON ui.UserID=um.ID ";
$params = [];
if ($Search) {
    $sql .= "WHERE i.Email LIKE ? ";
    $params[] = "%{$Search}%";
}
$sql .= "ORDER BY i.Expires DESC LIMIT {$Limit}";
$invites = $master->db->rawQuery($sql, $params)->fetchAll(\PDO::FETCH_NUM);

$Results = $master->db->foundRows();
?>
    <div class="box pad">
        <p><?=number_format($Results)?> unused invites have been sent. </p>
    </div>
    <br />
    <div>
        <form action="" method="get">
            <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
                <tr>
                    <td class="label"><strong>Email:</strong></td>
                    <td>
                        <input type="hidden" name="action" value="invite_pool" />
                        <input type="text" name="search" size="60" value="<?=display_str($Search)?>" />
                        &nbsp;
                        <input type="submit" value="Search log" />
                    </td>
                </tr>
            </table>
        </form>
    </div>
    <div class="linkbox">
<?php
    $Pages = get_pages($Page, $Results, INVITES_PER_PAGE, 11);
    echo $Pages;
?>
    </div>
    <table width="100%">
        <tr class="colhead">
            <td>Inviter</td>
            <td>Email</td>
            <td>Anon</td>
            <td>Expires</td>
<?php  if (check_perms('users_edit_invites')) { ?>
            <td>Controls</td>
<?php  } ?>
        </tr>
<?php
    $Row = 'b';
    foreach ($invites AS $invite) {
        list($userID, $Username, $PermissionID, $enabled, $Donor, $InviteID, $Anon, $Expires, $Email) = $invite;
        $Row = ($Row == 'b') ? 'a' : 'b';
?>
        <tr class="row<?=$Row?>">
            <td><?=format_username($userID, $Donor, true, $enabled, $PermissionID)?></td>
            <td><?=display_str($Email)?></td>
            <td><?=display_str($Anon)?></td>
            <td><?=time_diff($Expires)?></td>
<?php  if (check_perms('users_edit_invites')) { ?>
            <td>
                <form action="" method="post">
                    <input type="hidden" name="action" value="invite_pool" />
                    <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                    <input type="hidden" name="id" value="<?=$InviteID?>" />
                    <input type="submit" name="submit" value="Delete" />
                </form>

                <form action="" method="post">
                    <input type="hidden" name="action" value="invite_pool" />
                    <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                    <input type="hidden" name="id" value="<?=$InviteID?>" />
                    <input type="submit" name="submit" value="Resend" />
                </form>
            </td>
<?php  } ?>
        </tr>
<?php 	} ?>
    </table>
    <div class="linkbox">
<?=$Pages; ?>
    </div>
<?php
show_footer();
