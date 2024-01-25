<?php
if (!check_perms('admin_manage_permissions')) { error(403); }
show_header('Special Users List');
?>
<div class="thin">
<?php
$records = $master->db->rawQuery(
    "SELECT u.ID,
            u.Username,
            um.PermissionID,
            um.Enabled,
            ui.Donor,
            um.GroupPermissionID
       FROM users AS u
  LEFT JOIN users_main AS um ON um.ID=u.ID
  LEFT JOIN users_info AS ui ON ui.UserID=u.ID
      WHERE um.CustomPermissions != ''"
)->fetchAll(\PDO::FETCH_NUM);
if ($master->db->foundRows()) {
?>
    <table width="100%">
        <tr class="colhead">
            <td>User</td>
            <td>Access</td>
        </tr>
<?php
    foreach ($records as $record) {
        list($userID, $Username, $PermissionID, $enabled, $Donor, $GroupPermissionID) = $record;
?>
        <tr>
            <td><?=format_username($userID, $Donor, true, $enabled, $PermissionID, '', false, $GroupPermissionID, true)?></td>
            <td><a href="/user.php?action=permissions&amp;userid=<?=$userID?>">Manage</a></td>
        </tr>
<?php } ?>
    </table>
<?php } else { ?>
    <h2 align="center">There are no special users.</h2>
<?php  } ?>
</div>
<?php
show_footer();
