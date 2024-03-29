<?php
if (!check_perms('admin_dnu')) { error(403); }

show_header('Manage do not upload list');
$records = $master->db->rawQuery("SELECT
    d.ID,
    d.Name,
    d.Comment,
    d.UserID,
    u.Username,
    d.Time
    FROM do_not_upload as d
    LEFT JOIN users AS u ON u.ID=d.UserID
    ORDER BY d.Time DESC")->fetchAll(\PDO::FETCH_NUM);
?>
<div class="thin">
<h2>Do Not Upload List</h2>
<table>
    <tr>
        <td colspan="4" class="colhead">Add item to Do Not Upload List</td>
    </tr>
    <tr class="colhead">
        <td width="37%">Name</td>
        <td width="49%" colspan="2">Comment</td>
        <td width="14%">Submit</td>
    </tr>
    <tr class="rowa">
          <form action="tools.php" method="post">
                <input type="hidden" name="action" value="dnu_alter" />
                <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                <td>
                      <input class="long"  type="text" name="name" />
                </td>
                <td colspan="2">
                      <input class="long"  type="text" name="comment" />
                </td>
                <td>
                      <input type="submit" value="Create" />
                </td>
          </form>
    </tr>
</table>
<br/>
<table>
    <tr class="colhead">
        <td width="37%">Name</td>
        <td width="37%">Comment</td>
        <td width="12%">Added</td>
        <td width="14%">Submit</td>
    </tr>
<?php  $Row = 'b';
foreach ($records as $record) {
list($ID, $Name, $Comment, $userID, $Username, $DNUTime) = $record;
    $Row = ($Row === 'a' ? 'b' : 'a');
?>
    <tr class="row<?=$Row?>">
        <form action="tools.php" method="post">
            <td>
                <input type="hidden" name="action" value="dnu_alter" />
                <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                <input type="hidden" name="id" value="<?=$ID?>" />
                <input class="long" type="text" name="name" value="<?=display_str($Name)?>" />
            </td>
            <td>
                <input class="long"  type="text" name="comment" value="<?=display_str($Comment)?>" />
            </td>
            <td>
                <?=format_username($userID)?><br />
                <?=time_diff($DNUTime, 1)?></td>
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
