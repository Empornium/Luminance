<?php
if (!check_perms('admin_manage_tags')) {
    error(403);
}

$UseMultiInterface= true;

show_header('Official Tags Manager','tagmanager');

printRstMessage();
?>
<div class="thin">
    <h2>Tags Manager</h2>
<?php
    printTagLinks();
?>
    <h2>Official Tags</h2>
    <div class="tagtable center">
        <div>
            <form method="post">
                <input type="hidden" name="action" value="official_tags_alter" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                <input type="hidden" name="doit" value="1" />
                <table class="tagtable shadow">
                    <tr style="font-weight: bold" class="colhead">
                        <td>Remove</td><td>Tag</td><td>Uses</td><td>&nbsp;&nbsp;</td>
                        <td>Remove</td><td>Tag</td><td>Uses</td><td>&nbsp;&nbsp;</td>
                        <td>Remove</td><td>Tag</td><td>Uses</td>
                    </tr>
                    <?php
                    $i = 0;
                    $DB->query("SELECT ID, Name, Uses FROM tags WHERE TagType='genre' ORDER BY Name ASC");
                    $TagCount = $DB->record_count();
                    $Tags = $DB->to_array();
                    for ($i = 0; $i < $TagCount / 3; $i++) {
                        list($TagID1, $TagName1, $TagUses1) = $Tags[$i];
                        list($TagID2, $TagName2, $TagUses2) = $Tags[ceil($TagCount / 3) + $i];
                        list($TagID3, $TagName3, $TagUses3) = $Tags[2 * ceil($TagCount / 3) + $i];
                        ?>
                        <tr class="<?= (($i % 2) ? 'rowa' : 'rowb') ?>">
                            <td><input type="checkbox" name="oldtags[]" value="<?= $TagID1 ?>" /></td>
                            <td><a href="/torrents.php?taglist=<?= $TagName1 ?>" ><?= $TagName1 ?></a></td>
                            <td><?= $TagUses1 ?></td>
                            <td>&nbsp;&nbsp;</td>
                            <td>
    <?php  if ($TagID2) { ?>
                                    <input type="checkbox" name="oldtags[]" value="<?= $TagID2 ?>" />
                                <?php  } ?>
                            </td>
                            <td><a href="/torrents.php?taglist=<?= $TagName2 ?>" ><?= $TagName2 ?></a></td>
                            <td><?= $TagUses2 ?></td>
                            <td>&nbsp;&nbsp;</td>
                            <td>
    <?php  if ($TagID3) { ?>
                                    <input type="checkbox" name="oldtags[]" value="<?= $TagID3 ?>" />
                        <?php  } ?>
                            </td>
                            <td><a href="/torrents.php?taglist=<?= $TagName3 ?>" ><?= $TagName3 ?></a></td>
                            <td><?= $TagUses3 ?></td>
                        </tr>
<?php
                    }
?>
                    <tr class="<?= (($i % 2) ? 'rowa' : 'rowb') ?>">
                        <td colspan="11"><label for="newtag">New official tag: </label><input type="text" name="newtag" /></td>
                    </tr>
                    <tr style="border-top: thin solid #98AAB1">
                        <td colspan="11" style="text-align: center"><input type="submit" value="Submit Changes" /></td>
                    </tr>

                </table>
            </form>
        </div>
    </div>


</div>
<?php
show_footer();
