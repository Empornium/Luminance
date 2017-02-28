<?php
include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;
show_header('Create a collage','bbcode,jquery');

if (!check_perms('site_collages_renamepersonal')) {
    $ChangeJS = "OnChange=\"if (this.options[this.selectedIndex].value == '0') { $('#namebox').hide(); $('#personal').show(); } else { $('#namebox').show(); $('#personal').hide(); }\"";
}

$Name        = $_REQUEST['name'];
$Category    = $_REQUEST['cat'];
$Description = $_REQUEST['descr'];
$Tags        = $_REQUEST['tags'];
$Error       = $_REQUEST['err'];

if (!check_perms('site_collages_renamepersonal') && $Category === '0') {
    $NoName = true;
}
?>
<div class="thin">
    <h2>Create Collage</h2>
<?php
if (!empty($Error)) { ?>
    <div class="save_message error"><?=display_str($Error)?></div>
    <br />
<?php }
    /* -------  Draw a box with imagehost whitelist  ------- */
    $Whitelist = $Cache->get_value('imagehost_whitelist');
    if ($Whitelist === FALSE) {
        $DB->query("SELECT
                    Imagehost,
                    Link,
                    Comment,
                    Time,
                    Hidden
                    FROM imagehost_whitelist
                    WHERE Hidden='0'
                    ORDER BY Time DESC");
        $Whitelist = $DB->to_array();
        $Cache->cache_value('imagehost_whitelist', $Whitelist);
    }
    $DB->query("SELECT MAX(iw.Time), IF(MAX(t.Time) < MAX(iw.Time) OR MAX(t.Time) IS NULL,1,0)
                  FROM imagehost_whitelist as iw
             LEFT JOIN torrents AS t ON t.UserID = '$LoggedUser[ID]' ");
    list($Updated, $NewWL) = $DB->next_record();
// test $HideWL first as it may have been passed from upload_handle
    if (!$HideWL)
        $HideWL = check_perms('torrents_hide_imagehosts') || !$NewWL;
    ?>
    <div class="head">Approved Imagehosts</div>
    <div class="box pad">
        <span style="float:right;clear:right"><p><?=$NewWL ? '<strong class="important_text">' : '' ?>Last Updated: <?= time_diff($Updated) ?><?= $NewWL ? '</strong>' : '' ?></p></span>

        <p>You must use one of the following approved imagehosts for all images.
<?php  if ($HideWL) { ?>
                <span><a href="#" onclick="$('#whitelist').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(Show)':'(Hide)'); return false;">(Show)</a></span>
<?php  } ?>
        </p>
        <table id="whitelist" class="<?= ($HideWL ? 'hidden' : '') ?>" style="">
            <tr class="colhead_dark">
                <td width="50%"><strong>Imagehost</strong></td>
                <td><strong>Comment</strong></td>
            </tr>
<?php
foreach ($Whitelist as $ImageHost) {
    list($Host, $Link, $Comment, $Updated) = $ImageHost;
    ?>
                <tr>
                    <td><?=$Text->full_format($Host)?>
    <?php
    // if a goto link is supplied and is a validly formed url make a link icon for it
    if (!empty($Link) && $Text->valid_url($Link)) {
        ?><a href="<?= $Link ?>"  target="_blank"><img src="<?=STATIC_SERVER?>common/symbols/offsite.gif" width="16" height="16" style="" alt="Goto <?= $Host ?>" /></a>
    <?php  } // endif has a link to imagehost  ?>
                    </td>
                    <td><?=$Text->full_format($Comment)?></td>
                </tr>
    <?php  } ?>
        </table>
    </div>
        <div class="head">New collage</div>
    <form action="collages.php" method="post" name="newcollage">
        <input type="hidden" name="action" value="new_handle" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <table>
            <tr id="collagename">
                <td class="label"><strong>Name</strong></td>
                <td>
                    <input type="text" class="long<?=$NoName?' hidden':''?>" name="name" id="namebox" value="<?=display_str($Name)?>" />
                    <span id="personal" class="<?=$NoName?'':'hidden'?>" style="font-style: oblique"><strong><?=$LoggedUser['Username']?>'s personal collage</strong></span>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Category</strong></td>
                <td>
                    <select name="category" <?=$ChangeJS?>>
<?php
array_shift($CollageCats);

foreach ($CollageCats as $CatID=>$CatName) { ?>
                        <option value="<?=$CatID+1?>"<?=(($CatID+1 == $Category)?' selected':'')?>><?=$CatName?></option>
<?php }
$DB->query("SELECT COUNT(ID) FROM collages WHERE UserID='$LoggedUser[ID]' AND CategoryID='0' AND Deleted='0'");
list($CollageCount) = $DB->next_record();
if (($CollageCount < $LoggedUser['Permissions']['MaxCollages']) && check_perms('site_collages_personal')) { ?>
                        <option value="0"<?=(($Category === '0')?' selected':'')?>>Personal</option>
<?php } ?>
                    </select>
                    <br />
                    <ul>
                        <li><strong>Theme</strong> - A collage containing releases that all relate to a certain theme</li>
                        <li><strong>Porn Star</strong> - A collage containing a specific porn star</li>
                        <li><strong>Studio</strong> - A collage with content from a specific studio</li>
                        <li><strong>Staff picks</strong> - A list of recommendations picked by the staff on special occasions</li>
<?php
   if (($CollageCount < $LoggedUser['Permissions']['MaxCollages']) && check_perms('site_collages_personal')) { ?>
                        <li><strong>Personal</strong> - You can put whatever your want here.  It's your personal collage.</li>
<?php } ?>
                    </ul>
                </td>
            </tr>
            <tr>
                <td class="label">Editing Permissions</td>
                <td>
                            who can add/delete torrents <br/>
                            <select name="permission">
<?php
                                $MinUserLevel = $master->auth->permissions->getMinUserLevel();
                                $MinStaffLevel = $master->auth->permissions->getMinStaffLevel();
                                foreach ($ClassLevels as $CurClass) {
                                    if ($CurClass['Level']>=$MinStaffLevel) break;      // dont display  staff levels (and exit loop)
                                    if ($CurClass['Level']<$MinUserLevel) continue;     // dont display gimp like levels
                                    if ($CurClass['IsUserClass']==0) continue;          // dont display non ranks (ie. FLS/group permissions)
?>
                                    <option value="<?=$CurClass['Level']?>"<?=($CurClass['Level']==$MinUserLevel?' selected="selected"':'')?>><?=$CurClass['Name'];?></option>
<?php                           } ?>

                                <option value="0">Only Creator</option>
                            </select>
                </td>
            </tr>
            <tr>
                <td class="label">Description</td>
                <td>
                            <div id="preview" class="box pad hidden"></div>
                            <div  id="editor">
                            <?php $Text->display_bbcode_assistant("description", get_permissions_advtags($UserID)); ?>
                    <textarea name="description" id="description" class="long" rows="10"><?=display_str($Description)?></textarea>
                            </div>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Tags</strong></td>
                <td>
                    <input type="text" id="tags" name="tags" class="long" value="<?=display_str($Tags)?>" />
                                        <p class="min_padding">Space-separated list - eg. <em>hardcore big.tits anal</em></p>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <strong>Please ensure your collage will be allowed under the <a href="articles.php?topic=collages">rules</a></strong>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                            <input id="previewbtn" type="button" value="Preview" onclick="Preview_Collage();" />
                            <input type="submit" value="Create collage" />
                        </td>
            </tr>
        </table>
    </form>
</div>
<?php
show_footer();
