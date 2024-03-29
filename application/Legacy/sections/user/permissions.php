<?php

// TODO: Redo html

if (!check_perms('admin_manage_permissions'))
    error(403);

if (!isset($_REQUEST['userid']) || !is_integer_string($_REQUEST['userid']))
    error(0);

list($userID, $Username, $PermissionID) = array_values(user_info($_REQUEST['userid']));

if (empty($userID))
    error(404);

$permissionsClass = (new class { use Luminance\Legacy\Permissions; });

$Query = $master->db->rawQuery(
    "SELECT u.CustomPermissions,
            p1.Name,
            p2.Name
       FROM users_main AS u
  LEFT JOIN permissions AS p1 ON p1.ID = u.PermissionID
  LEFT JOIN permissions AS p2 ON p2.ID = u.GroupPermissionID
      WHERE u.ID = ?",
    [$userID]
);

list($Customs, $PermName, $GroupPermName) = $Query->fetch(\PDO::FETCH_NUM);

$defaults = get_permissions_for_user($userID, false);
$Delta    = [];
$Customs  = unserialize($Customs);

if (isset($_POST['action'])) {
    authorize();

    if (!empty($_POST['maxcollages']) && !is_integer_string($_POST['maxcollages']))
        error("Please enter a valid number of extra personal collages");

    if ((int) $_POST['maxcollages'] !== 0)
        $Delta['MaxCollages'] = $_POST['maxcollages'];

    // Compare custom permissions with defaults
    foreach (Luminance\Legacy\Permissions::$permissionsArray as $Perm => $Explaination) {
        $Setting = isset($_POST['perm_'.$Perm]) ? 1 : 0;
        $Default = isset($defaults[$Perm])      ? 1 : 0;

        if ($Setting != $Default)
            $Delta[$Perm] = $Setting;
    }

    // Update custom permissions in DB
    $CustomPermissions = !empty($Delta) ? serialize($Delta) : '';
    $master->db->rawQuery(
        "UPDATE users_main
            SET CustomPermissions = ?
          WHERE ID = ?",
        [$CustomPermissions, $userID]
    );

    $master->repos->users->uncache($userID);

} elseif (!empty($Customs)) {
    $Delta = $Customs;
}

$permissions = array_merge($defaults, $Delta);
$MaxCollages = $Customs['MaxCollages'] + $Delta['MaxCollages'];

function display_perm($Key, $Title, $ToolTip = '') {
    global $defaults, $permissions;

    if (!$ToolTip)
        $ToolTip = $Title;

    $DefaultPermChecked = (isset($defaults[$Key]) && $defaults[$Key]) ? 'checked' : '';
    $UserPermChecked    = (isset($permissions[$Key]) && $permissions[$Key]) ? 'checked' : '';
?>
    <input id="default_<?= $Key ?>" type="checkbox" disabled <?= $DefaultPermChecked ?> />
    <input type="checkbox" name="perm_<?= $Key ?>" id="<?= $Key ?>" value="1" <?= $UserPermChecked ?> />
    <label for="<?= $Key ?>" title="<?= $ToolTip ?>"><?= $Title ?></label><br />
<?php
}





show_header($Username.' &gt; Permissions');
?>

<script type="text/javascript">
    function reset()
    {
        for (i = 0; i < $('#permform').raw().elements.length; i++) {
            element = $('#permform').raw().elements[i];
            if (element.id.substr(0,8) == 'default_') {
                $('#' + element.id.substr(8)).raw().checked = element.checked;
            }
        }
    }
</script>

<div class="thin">
    <h2><?= format_username($userID) ?> &gt; Permissions</h2>

    <div class="linkbox">
        [<a href="#" onclick="reset();return false;">Defaults</a>]
    </div>

    <div class="box pad">
        Before using permissions, please understand that it allows you to both add and remove access to specific features.
        If you think that to add access to a feature, you need to uncheck everything else, <strong>YOU ARE WRONG</strong>.
        The checkmarks on the left, which are grayed out, are the standard permissions granted by their class (and donor/artist status),
        any changes you make to the right side will overwrite this. It's not complicated, and if you screw up, click the defaults link at the top.
        It will reset the user to their respective features granted by class, then you can check or uncheck the one or two things you want to change.
        <strong>DO NOT UNCHECK EVERYTHING.</strong>
    </div>

    <div class="permission_head  box shadow center">
        Class Permissions: <?= make_class_string($PermissionID, true); ?>
        <?php if ($GroupPermName) echo "<br/>Group Permissions: <strong>$GroupPermName</strong>"; ?>
    </div>

    <form name="permform" id="permform" method="post">
        <table class="permission_head">
            <tr>
                <td class="label">Extra personal collages</td>
                <td><input type="text" name="maxcollages" size="5" value="<?= $MaxCollages ?: '0' ?>" /></td>
            </tr>
        </table>
        <input type="hidden" name="action" value="permissions" />
        <input type="hidden" name="auth" value="<?= $activeUser['AuthKey'] ?>" />
        <input type="hidden" name="id" value="<?= $_REQUEST['userid'] ?>" />
    <?php
        $permissionsClass->form();
    ?>
    </form>
</div>

<?php
show_footer();
