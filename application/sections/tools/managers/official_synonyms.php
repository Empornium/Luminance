<?php
if (!check_perms('site_manage_tags')) {
    error(403);
}

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

$UseMultiInterface= true;

$DB->query("SELECT ID, Name, Uses FROM tags WHERE TagType='genre' ORDER BY Name ASC");
$Tags = $DB->to_array();

show_header('Official Synonyms Manager','tagmanager,bbcode');
?>
<div class="thin">
    <h2>Synonyms Manager</h2>

    <div class="linkbox">
        <a href="tools.php?action=official_tags">[Tags Manager]</a>
        <a style="font-weight: bold" href="tools.php?action=official_synonyms">[Synonyms Manager]</a>
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
            <?php  } ?>
                <div style="display:inline-block">
                <form class="tagtable" action="tools.php" method="post">
                    <table class="syntable shadow" style="width:220px;">
                        <input type="hidden" name="action" value="official_synonyms_alter" />
                        <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                        <input type="hidden" name="parenttagid" value="<?= $ParentTagID ?>" />
                        <tr class="colhead" >
                            <td style="width:20px;text-align:right;">&nbsp;</td>
                            <td style="width:170px"><a href="torrents.php?taglist=<?= $ParentTagName ?>" ><?= $ParentTagName ?></a>&nbsp;(<?= $Uses ?>)</td>
                        </tr>
                        <?php
                        $LastParentTagName = $ParentTagName;
                    }
                    $Row = $Row == 'b' ? 'a' : 'b';
                    ?>
                        <tr class="row<?= $Row ?>">
                            <td style="width:20px;text-align:right;"><input type="checkbox" name="oldsyns[]" value="<?= $SnID ?>" /></td>
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
<?php  } ?>

    <br />
    </div>
    <h2>Convert Tag to Synonym</h2>
    <div class="tagtable">

<?php  if (check_perms('site_convert_tags')) { ?>
        <form  class="tagtable" action="tools.php" method="post">
            <div class="box pad  shadow" id="convertbox">
                <div class="pad " style="text-align:left">
                    <h3>Convert Tag to Synonym</h3>
                    This section allows you to add a tag as a synonym for another tag.
                    <br />If the checkbox is unchecked then it will simply add the tag as a synonym for the parent tag and leave the tag and its current associations with torrents as is in the database. This will prevent it being added as a new tag and searches on it will search on the synomyn as expected, but the original tags already present will show up with the torrents.
                    <br /><br />If you check the 'convert' option it will remove the old tag from the database, inserting the tag this is now a synomyn for instead (where it is not already present for that torrent). This might be a preferable state for the database to be in but it is an irreversible operation and you should be certain you want the old tag removed from the torrents it is associated with before proceeding.
                </div>

                <input type="hidden" name="action" value="official_synonyms_alter" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                <input type="checkbox" name="converttag" value="1" checked="checked" />

                <label for="movetag" title="if this is checked then you can select an existing tag to convert into a synonym for another tag">convert tag to synonym</label>&nbsp;&nbsp;&nbsp;

                <br/><br/>
                Select tag(s) to convert to synonyms: (selected tags are listed below)
                <br/>
                Exclude tags with less than this number of uses
                <input type="checkbox" id="excludeuses" value="1" />
                <input type="text" id="numuses" value="5" />
                <br/><br/>
                <span style="font-size:1.5em;"> Tag dropdowns (select tags to convert here):
                    <a href="#" onclick="$('#tagdropdowns').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(View)</a>
                </span>

                <div id="tagdropdowns" class="hidden">

                    <table class="noborder">
<?php
                    $AtoZ = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z','other');
                    foreach ($AtoZ as $char) {
?>
                      <tr>
                        <td style="width:100px"><?=$char?></td>
                        <td style="width:90%;text-align:left;">
                        <select id="movetagid_<?=$char?>" name="movetagid[<?=$char?>]"
                                onclick="Get_Taglist('movetagid_<?=$char?>', '<?=$char?>')"
                                  onchange="Select_Tag('<?=$char?>', this.value, this.options[this.selectedIndex].text );" >
                            <option value="0" selected="selected">tags beginning with <?=$char?>&nbsp;</option>
                        </select>
                        </td>
                      </tr>
<?php
                    }
?>
                    </table>

                </div>

<?php               if ($UseMultiInterface) { // Experts only! ?>
                    <div class="pad" style="text-align:left">
                        <h3>Process Selected tags</h3>
                        <p>When you click the "Convert Tag(s)" button it will convert all the tags listed here to synonyms for the selected tag.</p>

                        <div class="box pad" id="multiNames"></div>
                        <input type="hidden" name="multi" value="multi" />
                        <input type="button" value="clear selection" onclick="Clear_Multi();" />
                        <input type="hidden" id="multiID" name="multiID" value="" />
                        <input type="text" id="showmultiID" value="" class="medium" disabled="disabled" />
                        <br/>
                    </div>
<?php               } ?>

                <label for="parenttagid" title="Select which tag the selected tags will be a synonym for">add these tag(s) as a synonym for: </label>&nbsp;&nbsp;&nbsp;

                <select name="parenttagid" >
<?php                   foreach ($Tags as $Tag) {
                        list($TagID, $TagName, $TagUses) = $Tag; ?>
                        <option value="<?= $TagID ?>"><?= "$TagName ($TagUses)" ?>&nbsp;&nbsp;</option>
<?php                   } ?>
                </select>
                <br/>
                <strong class="important_text">Note: Use With Caution!</strong> Please only use this function if you know what you are doing.
                <input type="submit" name="tagtosynomyn" value="Convert Tag(s)" title="add new synonym" />&nbsp;&nbsp;
            </div>
        </form>
<?php  } ?>
    </div>

<?php  if (check_perms('site_convert_tags')) { ?>
    <br />
    <h2>Convert Tag to Synonym - Advanced</h2>
    <div class="tagtable">
        <div class="box pad  shadow" id="convertbox">
            <div class="pad " style="text-align:left">
                <h3>Convert Tags to Synonym</h3>
                You can convert a list of tags into synonyms here. <strong class="important_text">Note: Use With EXTREME Caution!</strong>
                <br/>
                This tool takes a specific format; parent tags are prefixed with a '+' and all following tags are set as synonyms until a new parent tag occurs in the list.
                <br/>
                <?php
                $example = "[spoiler=format example][code]+parent.tag\ntag.to.convert1\ntag.t0.convert2\ntag.t0.convert3\n\n+second.parent.tag\nanother.tag.to.convert[/code][/spoiler]";

                echo $Text->full_format($example);
                ?>
                <br/><br/>
                If a parent tag is not yet an official tag it is automatically set to be one. Tags to be converted cannot be official tags. Neither the parent tag or tags to be converted can already exist as synonyms.
                <br/>
                <strong>note:</strong> converting tags with a high number of uses is an intensive task, although this interface can take a long list it might be best to break multiple parent-tags sections into batches.
            </div>

            <div style="width:48%;display:inline-block;vertical-align: top;">
                <textarea name="tagconvertlist" id="tagconvertlist" class="long" rows="10" onkeyup="Dirty_Taglist()" ></textarea>
            </div>
            <div style="width:48%;display:inline-block;overflow: auto;max-height: 1000px;" id="checkresults" class="pad">
                &nbsp;
            </div>

            <input type="button" value="Check input" onclick="Check_Taglist();" title="check the input before submitting" />
            &nbsp;&nbsp;
            <br/>
            <strong class="important_text">Note: Use With EXTREME Caution!</strong> Please only use this function if you know what you are doing.
            <input type="button" name="taglisttosynomyn" id="taglisttosynomyn" onclick="Process_Taglist();"  value="Convert Taglist" title="add new synonym" disabled="disabled" />&nbsp;&nbsp;

        </div>
    </div>
    <script type="text/javascript">
        addDOMLoadEvent(Dirty_Taglist);
    </script>
<?php  } ?>

</div>
<?php
show_footer();
