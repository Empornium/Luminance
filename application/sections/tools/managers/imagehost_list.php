<?php
if (!check_perms('admin_imagehosts')) { error(403); }

show_header('Manage imagehost whitelist');
$DB->query("SELECT
    w.ID,
    w.Imagehost,
    w.Link,
    w.Comment,
    w.UserID,
    um.Username,
    w.Time ,
      w.Hidden
    FROM imagehost_whitelist as w
    LEFT JOIN users_main AS um ON um.ID=w.UserID
    ORDER BY w.Time DESC");
?>
<div class="thin">
<h2>Imagehost Whitelist</h2>
<table>
    <tr class="head">
        <td colspan="6">Add Imagehost</td>
    </tr>
    <tr class="colhead">
        <td width="25%"><span title="this field is matched against image urls. displayed on the upload page">
                    Imagehost</span></td>
        <td width="20%"><span title="optional, if a valid url is present then it appears as an icon that can be clicked to take you to the link in a new page">
                    Link</span></td>
        <td width="30%" colspan="2"><span title="displayed in the imagehost whitelist">
                    Comment</span></td>
            <td width="8%"><span title="hidden items will not be displayed to the user but will still be allowed in bbcode">
                    Show in whitelist</span></td>
        <td width="10%"><span title="hidden items will not be displayed to the user but will still be allowed in bbcode">
                    Submit</span></td>
    </tr>
    <tr class="rowa">
    <form action="tools.php" method="post">
        <input type="hidden" name="action" value="iw_alter" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <td>
            <input class="long"  type="text" name="host" />
        </td>
        <td>
            <input class="long"  type="text" name="link" />
        </td>
        <td colspan="2">
            <input class="long"  type="text" name="comment" />
        </td>
        <td>
            <input type="checkbox" name="show"  value="1" />
        </td>
        <td>
            <input type="submit" value="Create" />
        </td>
    </form>
</tr>
</table>
<br/><br/>
<table>
    <tr class="head">
        <td colspan="6">Manage Imagehosts</td>
    </tr>
    <tr class="colhead">
        <td width="25%"><span title="this field is matched against image urls. displayed on the upload page">
                    Imagehost</span></td>
        <td width="20%"><span title="optional, if a valid url is present then it appears as an icon that can be clicked to take you to the link in a new page">
                    Link</span></td>
        <td width="30%"><span title="displayed in the imagehost whitelist">
                    Comment</span></td>
            <td width="8%"><span title="hidden items will not be displayed to the user but will still be allowed in bbcode">
                    Show in whitelist</span></td>
        <td width="10%"><span title="Date added">
                    Added</span></td>
        <td width="10%"><span title="hidden items will not be displayed to the user but will still be allowed in bbcode">
                    Submit</span></td>
    </tr>
<?php  $Row = 'b';
while (list($ID, $Host, $Link, $Comment, $UserID, $Username, $WLTime, $Hide) = $DB->next_record()) {
    $Row = ($Row === 'a' ? 'b' : 'a');
?>
    <tr class="row<?=$Row?>">
        <form action="tools.php" method="post">
            <td>
                <input type="hidden" name="action" value="iw_alter" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="id" value="<?=$ID?>" />
                <input class="long" type="text" name="host" value="<?=display_str($Host)?>" />
            </td>
            <td>
                <input class="long"  type="text" name="link" value="<?=display_str($Link)?>" />
            </td>
            <td>
                <input class="long"  type="text" name="comment" value="<?=display_str($Comment)?>" />
            </td>
        <td>
            <input type="checkbox" name="show" value="1" <?php  if(!$Hide)echo ' checked="checked"';?> />
        </td>
            <td>
                <?=format_username($UserID, $Username)?><br />
                <?=time_diff($WLTime, 1)?>
                  </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" />
            </td>
        </form>
    </tr>
<?php  } ?>
</table>
</div>
<?php
show_footer();
