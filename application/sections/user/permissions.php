<?php
//TODO: Redo html
if (!check_perms('admin_manage_permissions')) { error(403); }
if (!isset($_REQUEST['userid']) || !is_number($_REQUEST['userid'])) { error(404); }

include(SERVER_ROOT."/classes/permissions_form.php");

list($UserID, $Username, $PermissionID) = array_values(user_info($_REQUEST['userid']));

$DB->query("SELECT
        u.CustomPermissions , p1.Name, p2.Name
    FROM users_main AS u
      LEFT JOIN permissions AS p1 ON p1.ID=u.PermissionID
      LEFT JOIN permissions AS p2 ON p2.ID=u.GroupPermissionID
    WHERE u.ID='$UserID'");

list($Customs, $PermName, $GroupPermName)=$DB->next_record(MYSQLI_NUM, false);

$Defaults = get_permissions_for_user($UserID, array());

$Delta=array();
if (isset($_POST['action'])) {
    authorize();

    foreach ($PermissionsArray as $Perm => $Explaination) {
        $Setting = (isset($_POST['perm_'.$Perm]))?1:0;
        $Default = (isset($Defaults[$Perm]))?1:0;
        if ($Setting != $Default) {
            $Delta[$Perm] = $Setting;
        }
    }
    if (!is_number($_POST['maxcollages']) && !empty($_POST['maxcollages'])) { error("Please enter a valid number of extra personal collages"); }
    if ($_POST['maxcollages'] != 0) { $Delta['MaxCollages'] = $_POST['maxcollages']; }

    $master->repos->users->uncache($UserID);

    // Save on a != WHERE clause in the DB query later on.
    if (!empty($Delta)){
        $DB->query("UPDATE users_main SET CustomPermissions='".db_string(serialize($Delta))."' WHERE ID='$UserID'");
    } else {
        $DB->query("UPDATE users_main SET CustomPermissions='' WHERE ID='$UserID'");
    }
} elseif (!empty($Customs)) {
    $Delta = unserialize($Customs);
}

$Permissions = array_merge($Defaults,$Delta);
$MaxCollages = $Customs['MaxCollages'] + $Delta['MaxCollages'];

function display_perm($Key,$Title,$ToolTip='')
{
    global $Defaults, $Permissions;
    if (!$ToolTip)$ToolTip=$Title;
    $Perm='<input id="default_'.$Key.'" type="checkbox" disabled';
    if (isset($Defaults[$Key]) && $Defaults[$Key]) { $Perm.=' checked'; }
    $Perm.=' /><input type="checkbox" name="perm_'.$Key.'" id="'.$Key.'" value="1"';
    if (isset($Permissions[$Key]) && $Permissions[$Key]) { $Perm.=' checked'; }
    $Perm.=' /> <label for="'.$Key.'" title="'.$ToolTip.'">'.$Title.'</label><br />';
    echo $Perm;
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
<h2><?=format_username($UserID,$Username)?> > Permissions</h2>
<div class="linkbox">
    [<a href="#" onclick="reset();return false;">Defaults</a>]
</div>
<div class="box pad">
    Before using permissions, please understand that it allows you to both add and remove access to specific features. If you think that to add access to a feature, you need to uncheck everything else, <strong>YOU ARE WRONG</strong>. The checkmarks on the left, which are grayed out, are the standard permissions granted by their class (and donor/artist status), any changes you make to the right side will overwrite this. It's not complicated, and if you screw up, click the defaults link at the top. It will reset the user to their respective features granted by class, then you can check or uncheck the one or two things you want to change. <strong>DO NOT UNCHECK EVERYTHING.</strong>
</div>
    <div class="permission_head  box shadow center">
        Class Permissions: <?=  make_class_string($PermissionID,true); // $PermName;
            if ($GroupPermName) echo "<br/>Group Permissions: <strong>$GroupPermName</strong>";  ?>
    </div>
<form name="permform" id="permform" method="post" action="" >
    <table class="permission_head">
        <tr>
            <td class="label">Extra personal collages</td>
            <td><input type="text" name="maxcollages" size="5" value="<?=($MaxCollages?$MaxCollages:'0')?>" /></td>
        </tr>
    </table>
    <input type="hidden" name="action" value="permissions" />
    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
    <input type="hidden" name="id" value="<?=$_REQUEST['userid']?>" />
<?php
permissions_form();
?>
</form>
</div>
<?php
show_footer();
