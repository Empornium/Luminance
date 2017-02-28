<?php
include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

$CollageID = $_GET['collageid'];
if (!is_number($CollageID)) { error(0); }

$DB->query("SELECT Name, Description, TagList, UserID, CategoryID, Locked, MaxGroups, MaxGroupsPerUser, Featured FROM collages WHERE ID='$CollageID'");
list($Name, $Description, $TagList, $UserID, $CategoryID, $Locked, $MaxGroups, $MaxGroupsPerUser, $Featured) = $DB->next_record();
$TagList = implode(', ', explode(' ', $TagList));

//if ($CategoryID == 0 && $UserID!=$LoggedUser['ID'] && !check_perms('site_collages_delete')) { error(403); }
if (!check_perms('site_collages_manage') && $UserID != $LoggedUser['ID']) {
          error(403);
}

show_header('Edit collage','bbcode,jquery');
?>
<div class="thin">
      <h2>Edit collage <a href="collages.php?id=<?=$CollageID?>"><?=$Name?></a></h2>

    <form action="collages.php" method="post" id="quickpostform" >
        <input type="hidden" name="action" value="edit_handle" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <input type="hidden" name="collageid" value="<?=$CollageID?>" />
        <table id="edit_collage">
<?php if (check_perms('site_collages_manage') || ($CategoryID == 0 && $UserID == $LoggedUser['ID'] && check_perms('site_collages_renamepersonal'))) { ?>
            <tr>
                <td class="label">Name</td>
                <td><input type="text" name="name" class="long" value="<?=$Name?>" /></td>
            </tr>
<?php } ?>
<?php if ($CategoryID>0) { ?>
            <tr>
                <td class="label"><strong>Category</strong></td>
                <td>
                    <select name="category">
<?php
    array_shift($CollageCats);
    foreach ($CollageCats as $CatID=>$CatName) { ?>
                        <option value="<?=$CatID+1?>" <?php if ($CatID+1 == $CategoryID) { echo ' selected="selected"'; }?>><?=$CatName?></option>
<?php	} ?>
                    </select>
                </td>
            </tr>
<?php } ?>
            <tr>
                <td class="label">Description</td>
                <td>
                            <div id="preview" class="box pad hidden"></div>
                            <div  id="editor">
                            <?php $Text->display_bbcode_assistant("description", get_permissions_advtags($UserID)); ?>
                    <textarea name="description" id="description" class="long" rows="10"><?=$Description?></textarea>
                            </div>
                        </td>
            </tr>
            <tr>
                <td class="label">Tags</td>
                <td><input type="text" name="tags" class="long" value="<?=$TagList?>" /></td>
            </tr>
<?php if ($CategoryID == 0) { ?>
            <tr>
                <td class="label">Featured</td>
                <td><input type="checkbox" name="featured" <?=($Featured?'checked':'')?> /></td>
            </tr>
<?php }
   if (check_perms('site_collages_manage')) { ?>
            <tr>
                <td class="label">Locked</td>
                <td><input type="checkbox" name="locked" <?php if ($Locked) { ?>checked="checked" <?php }?>/></td>
            </tr>
            <tr>
                <td class="label">Max groups</td>
                <td><input type="text" name="maxgroups" size="5" value="<?=$MaxGroups?>" /></td>
            </tr>
            <tr>
                <td class="label">Max groups per user</td>
                <td><input type="text" name="maxgroupsperuser" size="5" value="<?=$MaxGroupsPerUser?>" /></td>
            </tr>

<?php } ?>
            <tr>
                <td colspan="2" class="center">
                            <input id="previewbtn" type="button" value="Preview" onclick="Preview_Collage();" />
                            <input type="submit" value="Edit collage" />
                        </td>
            </tr>
        </table>
    </form>
</div>
<?php show_footer();
