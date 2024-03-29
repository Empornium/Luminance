<?php
/************************************************************************
||------------|| Edit torrent page ||------------------------------||
************************************************************************/

$GroupID = $_GET['groupid'];
if (!is_integer_string($GroupID) || !$GroupID) {
    error(0);
}

$Review = get_last_review($GroupID);

// may as well use prefilled vars if coming from takegroupedit
if (($HasDescriptionData ?? false) !== true) {
    $nextRecord = $master->db->rawQuery(
        "SELECT tg.NewCategoryID,
                tg.Name,
                tg.Image,
                tg.Body,
                t.UserID,
                t.FreeTorrent,
                tg.Time,
                t.Anonymous
           FROM torrents_group AS tg
           JOIN torrents AS t ON t.GroupID = tg.ID
          WHERE tg.ID = ?",
        [$GroupID]
    )->fetch(\PDO::FETCH_NUM);
    if ($master->db->foundRows() == 0) {
        error(404);
    }
    list($CategoryID, $Name, $Image, $Body, $AuthorID, $Free, $AddedTime, $IsAnon) = $nextRecord;

    $CanEdit = check_perms('torrent_edit');
    if (!$CanEdit) {

        if ($activeUser['ID'] == $AuthorID) {
            if ( check_perms('site_edit_torrents') &&
                (check_perms('site_edit_override_timelock') || time_ago($AddedTime)< TORRENT_EDIT_TIME || $Review['Status'] == 'Warned')) {
                $CanEdit = true;
            } else {
                error("Sorry - you only have ". date('z\d\a\y\s i\m\i\n\s', TORRENT_EDIT_TIME). "  to edit your torrent before it is automatically locked.");
            }
        }

    }
}

if (!$CanEdit) { error(403); }

// prevent a user from editing a torrent once it is marked as "Okay", but let
// staff edit!
if ($Review['Status'] == 'Okay' && !check_perms('torrent_edit')) {
    if (!check_perms('site_edit_override_review')) {
        error("Sorry - once a torrent has been reviewed by staff and passed it is automatically locked.");
    }
}

if (!isset($bbCode)) {
    $bbCode = new \Luminance\Legacy\Text;
}

show_header('Edit torrent', 'bbcode,edittorrent');

// Start printing form
?>
<div class="thin">
<?php
    if ($Err ?? false) { ?>
            <div id="messagebar" class="messagebar alert"><?=$Err?></div>
<?php 	}
// =====================================================
//  Do we want users to be able to edit their own titles??
//  If so then maybe the title edit should be integrated into the main form ?
//if ($CanEdit) {
?>
    <h2>Rename Title</h2>
    <div class="box pad">
        <form action="torrents.php" method="post">
            <div>
                <input type="hidden" name="action" value="rename" />
                <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                <input type="text" name="name" class="long" value="<?=$Name?>" />
                <div style="text-align: center;">
                    <input type="submit" value="Rename" />
                </div>

            </div>
        </form>
    </div>

    <h2>Edit <a href="/torrents.php?id=<?=$GroupID?>"><?=$Name?></a></h2>
    <div class="box pad">
        <form id="edit_torrent" action="torrents.php" method="post">
            <div>
                <input type="hidden" name="action" value="takegroupedit" />
                <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                <input type="hidden" name="authorid" value="<?=$activeUser['ID']?>" />
                <input type="hidden" name="name" value="<?=$Name?>" />
                                <input type="hidden" name="oldcategoryid" value="<?=$CategoryID?>" />

                                <h3>Category</h3>
                                <select name="categoryid">
                                <?php  foreach ($openCategories as $category) { ?>
                                <option <?=$CategoryID==$category['id'] ? 'selected="selected"' : ''?> value="<?=$category['id']?>"><?=$category['name']?></option>
                                <?php  } ?>
                                </select>
                        <br /> <br />
                        <div id="preview" class="hidden"  style="text-align:left;">
                        </div>
                        <div id="editor">
                                <h3 style="display:inline">Cover Image</h3>
                                 &nbsp;&nbsp; (Enter the full url for your image).</strong><br/>

                                <input type="text" name="image" class="long" value="<?=$Image?>" /><br /><br />
                                <h3>Description</h3>
                                    <?php  $bbCode->display_bbcode_assistant("body", get_permissions_advtags($activeUser['ID'])); ?>
                                <textarea id="body" name="body" class="long" rows="20"><?=$Body?></textarea><br /><br />
                        </div>
                        <h3>Edit summary</h3>
                <input type="text" name="summary" class="long" value="<?=($EditSummary ?? '')?>" /><br />
                <div style="text-align: center;">
                                <input id="preview_button" type="button" value="Preview" onclick="Preview_Toggle();" />
                                <input type="submit" value="Submit" />
                        </div>
            </div>
        </form>
    </div>

<?php  if (check_perms('torrent_freeleech')) { ?>
    <h2>Freeleech</h2>
    <div class="box pad">
        <form action="torrents.php" method="post">
            <input type="hidden" name="action" value="nonwikiedit" />
            <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
            <input type="hidden" name="groupid" value="<?=$GroupID?>" />
            <table cellpadding="3" cellspacing="1" border="0" class="border" width="100%">
                <tr>
                    <td class="label">Freeleech</td>
                    <td>
            <input name="freeleech" value="0" type="radio"<?php  if ($Free!=1) echo ' checked="checked"';?>/> None&nbsp;&nbsp;
            <input name="freeleech" value="1" type="radio"<?php  if ($Free==1) echo ' checked="checked"';?>/> Freeleech&nbsp;&nbsp;
                    </td>
                </tr>
            </table>
            <input type="submit" value="Edit" />
        </form>
    </div>
<?php  } ?>
</div>
<?php
show_footer();
