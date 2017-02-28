<?php
if (!check_perms('admin_email_blacklist')) { error(403); }

show_header('Manage email blacklist');
$DB->query("SELECT
    eb.ID,
    eb.UserID,
    eb.Time,
    eb.Email,
    eb.Comment,
    um.Username
    FROM email_blacklist AS eb
    LEFT JOIN users_main AS um ON um.ID=eb.UserID
    ORDER BY eb.Email");
?>
<div class="thin">
<h2>Email Blacklist</h2>
    <table>
        <tr>
            <td colspan="4" class="head">Add To Email Blacklist</td>
        </tr>
        <tr class="colhead">
            <td width="35%">Email</td>
            <td width="50%" colspan="2">Comment</td>
            <td width="15%">Submit</td>
        </tr>
        <tr class="rowa">
            <form action="tools.php" method="post">
                <input type="hidden" name="action" value="eb_alter" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <td>
                      <input class="long" type="text" name="email" />
                </td>
                <td colspan="2">
                      <input  class="long" type="text" name="comment" />
                </td>
                <td>
                      <input type="submit" value="Create" />
                </td>
            </form>
        </tr>
    </table>
    <br/>
    <table>
        <tr>
            <td colspan="4" class="head">Manage Email Blacklist</td>
        </tr>
        <tr class="colhead">
        <td width="35%">Email</td>
        <td width="42%">Comment</td>
        <td width="8%">Added</td>
        <td width="15%">Submit</td>
        </tr>
<?php
    $Row = 'a';
    while (list($ID, $UserID, $Time, $Email, $Comment, $Username) = $DB->next_record()) {
        $Row = ($Row === 'a' ? 'b' : 'a');
?>
        <tr class="row<?=$Row?>">
        <form action="tools.php" method="post">
            <td>
                <input type="hidden" name="action" value="eb_alter" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="id" value="<?=$ID?>" />
                <input  class="long" type="text" name="email" value="<?=display_str($Email)?>" />
            </td>
            <td>
                <input  class="long" type="text" name="comment" value="<?=display_str($Comment)?>" />
            </td>
            <td>
                <?=format_username($UserID, $Username)?><br />
                <?=time_diff($Time, 1)?></td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" />
            </td>
        </form>
        </tr>
<?php   } ?>
    </table>
</div>
<?php
show_footer();
