<?php
if (!check_perms('admin_manage_permissions')) { error(403); }
show_header('Special Users List');
?>
<div class="thin">
<?php
$DB->query("SELECT
    m.ID,
    m.Username,
    m.PermissionID,
    m.Enabled,
    i.Donor,
    i.Warned,
    m.GroupPermissionID
    FROM users_main AS m
    LEFT JOIN users_info AS i ON i.UserID=m.ID
    WHERE m.CustomPermissions != ''");
if ($DB->record_count()) {
?>
    <table width="100%">
        <tr class="colhead">
            <td>User</td>
            <td>Access</td>
        </tr>
<?php
    while (list($UserID, $Username, $PermissionID, $Enabled, $Donor, $Warned,$GroupPermissionID)=$DB->next_record()) {
?>
        <tr>
            <td><?=format_username($UserID, $Username, $Donor, $Warned, $Enabled, $PermissionID, '', false, $GroupPermissionID, true)?></td>
            <td><a href="user.php?action=permissions&amp;userid=<?=$UserID?>">Manage</a></td>
        </tr>
<?php } ?>
    </table>
<?php } else { ?>
    <h2 align="center">There are no special users.</h2>
<?php  } ?>
</div>
<?php
show_footer();
