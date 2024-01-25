<?php
if (!check_perms('admin_manage_tags')) {
    error(403);
}

$bbCode = new \Luminance\Legacy\Text;

$UseMultiInterface= true;

$Tags = $master->db->rawQuery("SELECT ID, Name, Uses FROM tags WHERE TagType='genre' ORDER BY Name ASC")->fetchAll(\PDO::FETCH_BOTH);

show_header('Official Synonyms Manager', 'tagmanager,bbcode');

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
                <input type="hidden" name="auth" value="<?= $activeUser['AuthKey'] ?>" />

                <input type="text" name="newsynname"style="width:200px" />&nbsp;&nbsp;
                <input type="submit" name="addsynonym" value="Add new synonym for " title="add new synonym" />&nbsp;&nbsp;

                <select name="parenttagid" >
<?php               foreach ($Tags as $Tag) {
                    list($TagID, $TagName) = $Tag; ?>
                        <option value="<?= $TagID ?>"><?= $TagName ?>&nbsp;&nbsp;&nbsp;&nbsp;</option>
<?php               } ?>
                </select>
            </form>
        </div>
        <?php
        $Synonyms = $master->cache->getValue('all_synonyms');
        if (!$Synonyms) {
            $Synonyms = $master->db->rawQuery("SELECT ts.ID, Synonym, TagID, t.Name, Uses
                    FROM tags_synonyms AS ts LEFT JOIN tags AS t ON ts.TagID=t.ID
                    ORDER BY Name ASC")->fetchAll(\PDO::FETCH_BOTH);
            $master->cache->cacheValue('all_synonyms', $Synonyms);
        }
        $LastParentTagName = '';
        $Row = 'a';

        foreach ($Synonyms as $Synonym) {
            list($SnID, $SnName, $ParentTagID, $ParentTagName, $Uses) = $Synonym;

            if ($LastParentTagName != $ParentTagName) {
                if ($LastParentTagName != '') {
                    $Row = $Row == 'b' ? 'a' : 'b';
                    ?>
                    <tr class="row<?= $Row ?>">
                        <td class="tag_add" style="text-align:left"  colspan="2">
                            <input type="submit" name="delsynonyms" value="del selected" title="delete selected synonyms for <?= $LastParentTagName ?>" />
                        </td>
                    </tr>
            <?php  $Row = $Row == 'b' ? 'a' : 'b'; ?>
                    <tr class="row<?= $Row ?>">
                        <td class="tag_add" colspan="2">
                            <input type="text" name="newsynname" size="10" />
                            <input type="submit" name="addsynonym" value="+" title="add new synonym for <?= $LastParentTagName ?>" />
                        </td>
                    </tr>
                    </table>
                </form></div>
<?php           } ?>
            <div style="display:inline-block">
                <form class="tagtable" id="tt_<?=$ParentTagID?>" action="tools.php" method="post">
                    <table class="syntable shadow" style="width:220px;">
                        <input type="hidden" name="action" value="official_synonyms_alter" />
                        <input type="hidden" name="auth" value="<?= $activeUser['AuthKey'] ?>" />
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
                                <input type="submit" name="delsynonyms" value="del selected" title="delete selected synonyms for <?= $ParentTagName ?>" />
                            </td>
                        </tr>
    <?php               $Row = $Row == 'b' ? 'a' : 'b'; ?>
                        <tr class="row<?= $Row ?>">
                            <td class="tag_add" colspan="2" >
                                <input type="text" name="newsynname" size="10" />
                                <input type="submit" name="addsynonym" value="+" title="add new synonym for <?= $ParentTagName ?>" />

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
