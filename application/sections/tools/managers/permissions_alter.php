<?php
function display_perm($Key,$Title,$ToolTip='')
{
    global $Values;
    if (!$ToolTip)$ToolTip=$Title;
    $Perm='<input type="checkbox" name="perm_'.$Key.'" id="'.$Key.'" value="1"';
    if (!empty($Values[$Key])) { $Perm.=" checked"; }
    $Perm.=' /> <label for="'.$Key.'" title="'.$ToolTip.'">'.$Title.'</label><br />';
    echo $Perm;
}

show_header('Manage Permissions','validate');

echo $Val->GenerateJS('permform');

if(isset($_REQUEST['isclass']) &&  $_REQUEST['isclass']=='1') $IsUserClass = true;
?>
<form name="permform" id="permform" method="post" action="" onsubmit="return formVal();">
    <input type="hidden" name="action" value="permissions" />
    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
    <input type="hidden" name="id" value="<?=display_str($_REQUEST['id'])?>" />
    <input type="hidden" name="isclass" value="<?=($IsUserClass?'1':'0')?>" />
    <div class="linkbox">
        [<a href="tools.php?action=permissions">Back to permission list</a>]
        [<a href="tools.php">Back to Tools</a>]
    </div>
    <table class="permission_head">
<?php if ($IsUserClass) {     ?>
        <tr>
            <td class="label">User Class<!--Permission Name--></td>
            <td><input type="text" name="name" id="name" value="<?=(!empty($Name) ? display_str($Name) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Class Level</td>
            <td><input type="text" name="level" id="level" value="<?=(!empty($Level) ? display_str($Level) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Max Sig length</td>
            <td><input type="text" name="maxsiglength" value="<?=(!empty($MaxSigLength) ? display_str($MaxSigLength) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Max Avatar Size</td>
            <td><input class="wid35" type="text" name="maxavatarwidth" value="<?=(!empty($MaxAvatarWidth) ? display_str($MaxAvatarWidth) : '')?>" />
                      &nbsp;x&nbsp;
                      <input type="text"  class="wid35" name="maxavatarheight" value="<?=(!empty($MaxAvatarHeight) ? display_str($MaxAvatarHeight) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Rank Color</td>
            <td><input type="text" name="color" style="font-weight:bold;color: #<?=display_str($Color)?>" value="<?=(!empty($Color) ? display_str($Color) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Auto Promotion</td>
            <td><input type="checkbox" name="isautopromote" value="1" <?php  if (!empty($isAutoPromote)) { ?>checked<?php  } ?> /></td>
        </tr>
        <tr>
            <td class="label">Req Weeks</td>
            <td><input type="text" name="reqweeks" id="reqweeks" title="Max: 65535" value="<?=(!is_null($reqWeeks) ? display_str($reqWeeks) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label" title="GB">Req Uploaded</td>
            <td><input type="text" name="requploaded" id="requploaded" title="GB" value="<?=(!is_null($reqUploaded) ? display_str(get_size($reqUploaded)) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Req Torrents</td>
            <td><input type="text" name="reqtorrents" id="reqtorrents" title="Max: 65535" value="<?=(!is_null($reqTorrents) ? display_str($reqTorrents) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Req Forum Posts</td>
            <td><input type="text" name="reqforumposts" id="reqforumposts" title="Max: 65535" value="<?=(!is_null($reqForumPosts) ? display_str($reqForumPosts) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Req Ratio</td>
            <td><input type="text" name="reqratio" id="reqratio" title="Ratio for promotion, demotion ratio is this setting minus 0.1,  Max: 99.9" value="<?=(!is_null($reqRatio) ? display_str(number_format($reqRatio, 2, '.', '')) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Show on Staff page</td>
            <td><input type="checkbox" name="displaystaff" value="1" <?php  if (!empty($DisplayStaff)) { ?>checked<?php  } ?> /></td>
        </tr>
        <tr>
            <td class="label">Maximum number of personal collages</td>
            <td><input type="text" name="maxcollages" size="5" value="<?=$Values['MaxCollages']?>" /></td>
        </tr>
<?php   } else {    ?>
        <tr>
            <td class="label">Group Permission</td>
            <td><input type="text" name="name" id="name" value="<?=(!empty($Name) ? display_str($Name) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Group Description</td>
            <td><input type="text" name="description" id="decription" value="<?=(!empty($Description) ? display_str($Description) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Rank Color</td>
            <td><input type="text" name="color" style="font-weight:bold;color: #<?=display_str($Color)?>" value="<?=(!empty($Color) ? display_str($Color) : '')?>" /></td>
        </tr>
        <tr>
            <td class="label">Extra personal collages</td>
            <td><input type="text" name="maxcollages" size="5" value="<?=$Values['MaxCollages']?>" /></td>
        </tr>
<?php       }

if (is_numeric($_REQUEST['id'])) { ?>
        <tr>
            <td class="label">Current users in this class</td>
            <td><?=number_format($UserCount)?></td>
        </tr>
<?php  } ?>
    </table>
<?php
include(SERVER_ROOT."/classes/permissions_form.php");
permissions_form();
?>
</form>
<?php
show_footer();
