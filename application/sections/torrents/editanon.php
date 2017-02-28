<?php
/************************************************************************
||------------|| Edit torrent anon page ||------------------------------||
************************************************************************/

$GroupID = $_GET['groupid'];
if (!is_number($GroupID) || !$GroupID) { error(0); }

// Quick SQL injection check
if (!$_REQUEST['groupid'] || !is_number($_REQUEST['groupid'])) {
    error(404);
}
// End injection check
$GroupID = (int) $_REQUEST['groupid'];

$DB->query("SELECT t.UserID, tg.Name, t.Anonymous
                FROM torrents_group AS tg
                JOIN torrents AS t ON t.GroupID = tg.ID
                WHERE t.GroupID='$GroupID'");

list($AuthorID, $Name, $IsAnon) = $DB->next_record();

//check user has permission to edit
$CanEdit = check_perms('torrents_edit');

if (!$CanEdit) {
    $CanEdit = check_perms('site_upload_anon') && $AuthorID == $LoggedUser['ID'];
}

if (!$CanEdit) { error(403); }

show_header('Edit Anonymous status' );

// Start printing form
?>
<div class="thin">
<?php
    if ($Err) { ?>
            <div id="messagebar" class="messagebar alert"><?=$Err?></div>
<?php 	}
// =====================================================

?>
    <h2>Edit Anonymous status for <a href="torrents.php?id=<?=$GroupID?>"><?=$Name?></a></h2>

    <div class="box pad">
        <form action="torrents.php" method="post">
            <div>
                <input type="hidden" name="action" value="takeeditanon" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                <table cellpadding="3" cellspacing="1" border="0" class="border" width="100%">
                    <tr>
                        <td class="label">Show/Hide Uploader name</td>
                        <td>

                            <input name="anonymous" value="0" type="radio"<?php  if($IsAnon!=1) echo ' checked="checked"';?>/> Show uploader name&nbsp;&nbsp;
                            <input name="anonymous" value="1" type="radio"<?php  if($IsAnon==1) echo ' checked="checked"';?>/> Hide uploader name (Anonymous)&nbsp;&nbsp;

                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><br/><em><strong>note:</strong> uploader names are always hidden from lower ranked users, see user classes article in help for more info</em></td>
                    </tr>
                </table>
                <input type="submit" value="Save" />
            </div>
        </form>
    </div>

</div>
<?php
show_footer();
