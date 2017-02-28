<?php
if (!check_perms('site_manage_tags')) {
    error(403);
}

$UseMultiInterface= true;

show_header('Official Tags Manager','tagmanager');
?>
<div class="thin">
    <h2>Tags Manager</h2>
    <div class="linkbox">
        <a style="font-weight: bold" href="tools.php?action=official_tags">[Tags Manager]</a>
        <a href="tools.php?action=official_synonyms">[Synonyms Manager]</a>
    </div>
<?php
    if (isset($_GET['rst']) && is_number($_GET['rst'])) {
        $Result = (int) $_GET['rst'];
        $ResultMessage = display_str($_GET['msg']);
        if ($Result !== 1)
            $AlertClass = ' alert';

        if ($ResultMessage) {
?>
            <div class="messagebar<?= $AlertClass ?>"><?= $ResultMessage ?></div>
<?php
        }
    }
?>
    <h2>Official Tags</h2>
    <div class="tagtable center">
        <div>
            <form method="post">
                <input type="hidden" name="action" value="official_tags_alter" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                <input type="hidden" name="doit" value="1" />
                <table class="tagtable shadow">
                    <tr class="colhead">
                        <td style="font-weight: bold" style="text-align: center">Remove</td>
                        <td style="font-weight: bold">Tag</td>
                        <td style="font-weight: bold">Uses</td>
                        <td>&nbsp;&nbsp;&nbsp;</td>
                        <td style="font-weight: bold" style="text-align: center">Remove</td>
                        <td style="font-weight: bold">Tag</td>
                        <td style="font-weight: bold">Uses</td>
                        <td>&nbsp;&nbsp;&nbsp;</td>
                        <td style="font-weight: bold" style="text-align: center">Remove</td>
                        <td style="font-weight: bold">Tag</td>
                        <td style="font-weight: bold">Uses</td>
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
                            <td><a href="torrents.php?taglist=<?= $TagName1 ?>" ><?= $TagName1 ?></a></td>
                            <td><?= $TagUses1 ?></td>
                            <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                            <td>
    <?php  if ($TagID2) { ?>
                                    <input type="checkbox" name="oldtags[]" value="<?= $TagID2 ?>" />
                                <?php  } ?>
                            </td>
                            <td><a href="torrents.php?taglist=<?= $TagName2 ?>" ><?= $TagName2 ?></a></td>
                            <td><?= $TagUses2 ?></td>
                            <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                            <td>
    <?php  if ($TagID3) { ?>
                                    <input type="checkbox" name="oldtags[]" value="<?= $TagID3 ?>" />
                        <?php  } ?>
                            </td>
                            <td><a href="torrents.php?taglist=<?= $TagName3 ?>" ><?= $TagName3 ?></a></td>
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

<?php  if (check_perms('site_convert_tags')) { ?>
    <br/>
    <h2>Tags Admin</h2>

    <form  class="tagtable" action="tools.php" method="post">
        <div class="tagtable">
                <input type="hidden" name="action" value="official_tags_alter" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
            <div class="box pad center shadow">
                <div class="pad" style="text-align:left">
                    <h3>Permanently Remove Tag</h3>
                    This section allows you to remove a tag completely from the database. <br/>
                    <strong class="important_text">Note: Use With Caution!</strong> This should only be used to remove things like banned tags,
                    <span style="text-decoration: underline">it irreversibly removes the tag and all instances of it in all torrents.</span>
                </div>

                    <select id="permdeletetagid" name="permdeletetagid" onclick="Get_Taglist_All('permdeletetagid', 'all')" >
                        <option value="0" selected="selected">click to load ALL tags (might take a while)&nbsp;</option>
                    </select>
                    <input type="submit" name="deletetagperm" value="Permanently remove tag " title="permanently remove tag" />&nbsp;&nbsp;
            </div>
        </div>
        <div class="tagtable">
            <div class="box pad center shadow">
                <div class="pad" style="text-align:left">
                    <h3>Recount tag uses</h3>
                    This should never be needed once we go live!<br/>
                    <strong>Note: </strong>  You cannot do any direct harm with this but it may take a while to complete...
                </div>
                <input type="submit" name="recountall" value="Recount all tags " title="recounts the uses for every tag in the database" />&nbsp;&nbsp;
            </div>
        </div>
    </form>
<?php  } ?>
</div>
<?php
show_footer();
