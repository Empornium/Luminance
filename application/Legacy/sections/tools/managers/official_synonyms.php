<?php
if (!check_perms('admin_manage_tags')) {
    error(403);
}

$Text = new Luminance\Legacy\Text;

$UseMultiInterface= true;

$DB->query("SELECT ID, Name, Uses FROM tags WHERE TagType='genre' ORDER BY Name ASC");
$Tags = $DB->to_array();

show_header('Official Synonyms Manager','tagmanager,bbcode');

printRstMessage();
?>
<div class="thin">
    <h2>Synonyms Manager</h2>
<?php
    printTagLinks();
?>
    <h2>Tag Synonyms</h2>
    <div class="tagtable">
        <div class="box pad center shadow">
            <form  class="tagtable" action="tools.php" method="post">

                <input type="hidden" name="action" value="official_synonyms_alter" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />

                <input type="text" name="newsynname"style="width:200px" />&nbsp;&nbsp;
                <input type="submit" name="addsynomyn" value="Add new synonym for " title="add new synonym" />&nbsp;&nbsp;

                <select name="parenttagid" >
<?php               foreach ($Tags as $Tag) {
                    list($TagID, $TagName) = $Tag; ?>
                        <option value="<?= $TagID ?>"><?= $TagName ?>&nbsp;&nbsp;&nbsp;&nbsp;</option>
<?php               } ?>
                </select>
            </form>
        </div>
        <?php
        $Synomyns = $Cache->get_value('all_synomyns');
        if (!$Synomyns) {
            $DB->query("SELECT ts.ID, Synomyn, TagID, t.Name, Uses
                    FROM tag_synomyns AS ts LEFT JOIN tags AS t ON ts.TagID=t.ID
                    ORDER BY Name ASC");
            $Synomyns = $DB->to_array(false, MYSQLI_BOTH);
            $Cache->cache_value('all_synomyns', $Synomyns);
        }
        $LastParentTagName = '';
        $Row = 'a';

        foreach ($Synomyns as $Synomyn) {
            list($SnID, $SnName, $ParentTagID, $ParentTagName, $Uses) = $Synomyn;

            if ($LastParentTagName != $ParentTagName) {
                if ($LastParentTagName != '') {
                    $Row = $Row == 'b' ? 'a' : 'b';
                    ?>
                    <tr class="row<?= $Row ?>">
                        <td class="tag_add" style="text-align:left"  colspan="2">
                            <input type="submit" name="delsynomyns" value="del selected" title="delete selected synonyms for <?= $LastParentTagName ?>" />
                        </td>
                    </tr>
            <?php  $Row = $Row == 'b' ? 'a' : 'b'; ?>
                    <tr class="row<?= $Row ?>">
                        <td class="tag_add" colspan="2">
                            <input type="text" name="newsynname" size="10" />
                            <input type="submit" name="addsynomyn" value="+" title="add new synonym for <?= $LastParentTagName ?>" />
                        </td>
                    </tr>
                    </table>
                </form></div>
<?php           } ?>
            <div style="display:inline-block">
                <form class="tagtable" id="tt_<?=$ParentTagID?>" action="tools.php" method="post">
                    <table class="syntable shadow" style="width:220px;">
                        <input type="hidden" name="action" value="official_synonyms_alter" />
                        <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                        <input type="hidden" name="parenttagid" value="<?= $ParentTagID ?>" />
                        <tr class="colhead" >
                            <td style="width:20px;text-align:right;"><input type="checkbox" onclick="toggleChecks('tt_<?=$ParentTagID?>',this)"  /></td>
                            <td style="width:170px"><a href="/torrents.php?taglist=<?= $ParentTagName ?>" ><?= $ParentTagName ?></a>&nbsp;(<?= $Uses ?>)</td>
                        </tr>
                        <?php
                        $LastParentTagName = $ParentTagName;
            }
                    $Row = $Row == 'b' ? 'a' : 'b';
                    ?>
                        <tr class="row<?= $Row ?>">
                            <td style="width:20px;text-align:right;"><input type="checkbox" class="<?= $SnID ?>" name="oldsyns[]" value="<?= $SnID ?>" /></td>
                            <td style="width:170px"><?= $SnName ?></td>
                        </tr>
                    <?php
        }

                if ($SnID) { // only finish if something was in list
                    $Row = $Row == 'b' ? 'a' : 'b';
                    ?>
                        <tr class="row<?= $Row ?>">
                            <td class="tag_add" style="text-align:left" colspan="2" >
                                <input type="submit" name="delsynomyns" value="del selected" title="delete selected synonyms for <?= $ParentTagName ?>" />
                            </td>
                        </tr>
    <?php               $Row = $Row == 'b' ? 'a' : 'b'; ?>
                        <tr class="row<?= $Row ?>">
                            <td class="tag_add" colspan="2" >
                                <input type="text" name="newsynname" size="10" />
                                <input type="submit" name="addsynomyn" value="+" title="add new synonym for <?= $ParentTagName ?>" />

                            </td>
                        </tr>
                    </table>
                </form>
            </div>
<?php           } ?>
    </div>
</div>
<?php
show_footer();
