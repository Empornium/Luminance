<?php
function class_list($Selected=0)
{
    global $Classes;
    $Return = '';
    foreach ($Classes as $ID => $Class) {
        if($Class['IsUserClass']=='0') continue;
        $Name = $Class['Name'];
        $Level = $Class['Level'];
        $Return.='<option value="'.$Level.'"';
        if ($Selected == $Level) {
            $Return.=' selected="selected"';
        }
        $Return.='>'.cut_string($Name, 20, 1).'</option>'."\n";
    }
    reset($Classes);

    return $Return;
}

if (!check_perms('admin_manage_forums')) { error(403); }

show_header('Forum Management');
$DB->query('SELECT ID, Name FROM forums ORDER BY Sort');
$ForumArray = $DB->to_array(); // used for generating the 'parent' drop down list

// Replace the old hard-coded forum categories
unset($ForumCats);
$ForumCats = $Cache->get_value('forums_categories');
if ($ForumCats === false) {
    $DB->query("SELECT ID, Name FROM forums_categories ORDER BY Sort");
    $ForumCats = array();
    while (list($ID, $Name) =  $DB->next_record()) {
        $ForumCats[$ID] = $Name;
    }
    $Cache->cache_value('forums_categories', $ForumCats, 0); //Inf cache.
}
?>

<div class="thin">
<h2>Forum control panel</h2>
<table width="150px">
    <tr class="colhead">
        <td>Sort</td>
        <td>Name</td>
        <td>Submit</td>
    </tr>
<?php
$DB->query('SELECT ID, Name, Sort FROM forums_categories ORDER BY Sort');
$Row = 'b';
while (list($ID, $Name, $Sort) = $DB->next_record()) {
    $Row = ($Row === 'a' ? 'b' : 'a');
?>
    <tr class="row<?=$Row?>">
        <form action="" method="post">
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="action" value="forum_categories_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <input type="text" size="3" name="sort" value="<?=$Sort?>" />
            </td>
            <td>
                <input type="text" size="10" name="name" value="<?=$Name?>" />
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" />
            </td>
        </form>
    </tr>
<?php
}
?>
    <tr>
        <td colspan="3" class="colhead">Create category</td>
    </tr>
    <tr class="rowa">
        <form action="" method="post">
            <input type="hidden" name="action" value="forum_categories_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <input type="text" size="3" name="sort" value="" />
            </td>
            <td>
                <input type="text" size="10" name="name" value="" />
            </td>
            <td>
                <input type="submit" name="submit" value="Create" />
            </td>
        </form>
    </tr>
</table>
<br/>
<table width="100%">
    <tr class="colhead">
        <td>Category</td>
        <td>Sort</td>
        <td>Name</td>
        <td>Description</td>
        <td>Min class read</td>
        <td>Min class write</td>
        <td>Min class create</td>
        <td>Autolock</td>
        <td>Submit</td>
    </tr>
<?php
$DB->query('SELECT
    f.ID,
    CategoryID,
    f.Sort,
    f.Name,
    Description,
    MinClassRead,
    MinClassWrite,
    MinClassCreate,
    AutoLock
    FROM forums AS f LEFT JOIN forums_categories AS fc ON f.CategoryID=fc.ID
    ORDER BY fc.Sort, f.Sort ASC');
$Row = 'b';
while (list($ID, $CategoryID, $Sort, $Name, $Description, $MinClassRead, $MinClassWrite, $MinClassCreate, $AutoLock) = $DB->next_record()) {
    $Row = ($Row === 'a' ? 'b' : 'a');
?>
    <tr class="row<?=$Row?>">
        <form action="" method="post">
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="action" value="forum_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <select name="categoryid">
<?php   reset($ForumCats);
    foreach ($ForumCats as $CurCat => $CatName) {
?>
                    <option value="<?=$CurCat?>" <?php  if ($CurCat == $CategoryID) { echo ' selected="selected"'; } ?>><?=$CatName?></option>
<?php   } ?>
                </select>
            </td>
            <td>
                <input type="text" size="3" name="sort" value="<?=$Sort?>" />
            </td>
            <td>
                <input type="text" size="10" name="name" value="<?=$Name?>" />
            </td>
            <td>
                <input type="text" size="20" name="description" value="<?=$Description?>" />
            </td>
            <td>
                <select name="minclassread">
                    <?=class_list($MinClassRead)?>
                </select>
            </td>
            <td>
                <select name="minclasswrite">
                    <?=class_list($MinClassWrite)?>
                </select>
            </td>
            <td>
                <select name="minclasscreate">
                    <?=class_list($MinClassCreate)?>
                </select>
            </td>
            <td>
                <input type="checkbox" name="autolock" <?=($AutoLock == '1')?'checked ':''?>/>
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" />
            </td>

        </form>
    </tr>
<?php
}
?>
    <tr>
        <td colspan="9" class="colhead">Create forum</td>
    </tr>
    <tr class="rowa">
        <form action="" method="post">
            <input type="hidden" name="action" value="forum_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <select name="categoryid">
<?php   reset($ForumCats);
    while (list($CurCat, $CatName) = each($ForumCats)) { ?>
                    <option value="<?=$CurCat?>" <?php  if ($CurCat == $CategoryID) { echo ' selected="selected"'; } ?>><?=$CatName?></option>
<?php   } ?>
                </select>
            </td>
            <td>
                <input type="text" size="3" name="sort" />
            </td>
            <td>
                <input type="text" size="10" name="name" />
            </td>
            <td>
                <input type="text" size="20" name="description" />
            </td>
            <td>
                <select name="minclassread">
                    <?=class_list()?>
                </select>
            </td>
            <td>
                <select name="minclasswrite">
                    <?=class_list()?>
                </select>
            </td>
            <td>
                <select name="minclasscreate">
                    <?=class_list()?>
                </select>
            </td>
            <td>
                <input type="checkbox" name="autolock" checked />
            </td>
            <td>
                <input type="submit" value="Create" />
            </td>

        </form>
    </tr>
</table>
</div>
<?php
show_footer();
