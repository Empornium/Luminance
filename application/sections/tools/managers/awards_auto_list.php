<?php
if (!check_perms('site_manage_awards')) { error(403); }

show_header('Automatic Awards schedule','badges');

$DB->query("SELECT ID, Title
              FROM badges WHERE Type='Single' ORDER BY Sort");
$BadgesArray = $DB->to_array();

$DB->query("SELECT ID, Name
              FROM categories ORDER BY Name");
$CatsArray = $DB->to_array();

function print_badges_select($ElementID, $CurrentBadgeID=-1)
{
    global $BadgesArray;
?>
    <select name="badgeid[<?=$ElementID?>]" id="badgeid<?=$ElementID?>" onchange="Select_Badge('<?=$ElementID?>')">
<?php       foreach ($BadgesArray as $Badge) {
        list($ID, $Name) = $Badge;  ?>
            <option value="<?=$ID?>"<?=($ID==$CurrentBadgeID?' selected="selected"':'')?> >#<?=$ID?> <?=$Name?>&nbsp;&nbsp;</option>
<?php       } ?>
    </select>
<?php
}
function print_categories($ElementID, $SelectedCat=-1)
{
    global $CatsArray;
?>
    <select name="catid[<?=$ElementID?>]" id="catid<?=$ElementID?>" onchange="Set_Edit('<?=$ElementID?>')" title="Category ID: If specified for NumUploaded then only torrents in this cateogry are counted (has no effect on other actions)">
        <option value="0">-none-</option>
<?php       foreach ($CatsArray as $Cat) {
            list($CatID,$CatName)=$Cat;  ?>
            <option value="<?=$CatID?>"<?=($CatID==$SelectedCat?' selected="selected"':'')?>><?=$CatName?>&nbsp;&nbsp;</option>
<?php       } ?>
    </select>
<?php
}
?>
    <div class="thin">
    <h2>Automatic Awards schedule</h2>
    <form action="tools.php" method="post">
        <input type="hidden" name="action" value="awards_alter" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <table>
            <tr class="head">
                <td colspan="9">Add Automatic Award item</td>
            </tr>
            <tr class="colhead">
                <td width="10px" rowspan="2">Add</td>
                <td width="40px" rowspan="2">Image</td>
                <td>Badge</td>
                <td width="80px">For</td>
                <td width="80px">Value</td>
                <td width="80px">Category<br />(NumUploaded only)</td>
                <td width="40px">Send PM</td>
                <td width="40px">Active</td>
                <td width="10px" rowspan="2"></td>
            </tr>
            <tr class="colhead">
                <td colspan="6">Description (from badges)</td>
            </tr>
<?php
            $numAdds = isset($_REQUEST['numadd'])?(int) $_REQUEST['numadd']:5;
            if ($numAdds<1 || $numAdds > 20) $numAdds = 1;

            for ($i = 0; $i < $numAdds; $i++) {
                $ID = "new$i";
?>
                <tr class="rowb">
                    <td rowspan="2" style="vertical-align: top">
                        <input type="checkbox" id="id_<?=$ID?>" name="id[<?=$ID?>]" value="<?=$ID?>" title="If checked edits to this badge will be saved when you click on 'Save changes'" />
                    </td>
                    <td class="center" rowspan="2">
                        <div class="badge">
                            <span id="image<?=$ID?>">
                            </span>
                        </div>
                    </td>
                    <td>
<?php                       print_badges_select($ID); ?>
                    </td>
                    <td>
                        <select name="type[<?=$ID?>]" id="type<?=$ID?>" onchange="Set_Edit('<?=$ID?>')" >
<?php                           foreach ($AutoAwardTypes as $Act) {   ?>
                                <option value="<?=$Act?>"><?=$Act?>&nbsp;&nbsp;&nbsp;&nbsp;</option>
<?php                           } ?>
                    </select>
                    </td>
                    <td>
                          <input class="medium"  type="text" name="value[<?=$ID?>]" id="value<?=$ID?>" onchange="Set_Edit('<?=$ID?>')" />
                    </td>
                    <td>
                        <?php  print_categories($ID) ?>
                    </td>
                    <td class="center">
                          <input class="medium"  type="checkbox" name="sendpm[<?=$ID?>]" id="sendpm<?=$ID?>" value="1" checked="checked" onchange="Set_Edit('<?=$ID?>')" title="If checked then the user is sent a PM telling them when they recieve this award" />
                    </td>
                    <td class="center">
                          <input class="medium"  type="checkbox" name="active[<?=$ID?>]" id="active<?=$ID?>" value="1" checked="checked" onchange="Set_Edit('<?=$ID?>')" title="If checked this award will be automatically distributed to users who meet the specified requirements" />
                    </td>
                    <td rowspan="2">
                        <a href="#" onclick="Fill_From(<?=$i?>,['badgeid','catid','image','type','value','sendpm','active','descr'])" title="fill other add forms with this forms values">fill</a>
                    </td>
                </tr>
                <tr class="rowb">
                    <td colspan="6">
                        <span id="descr<?=$ID?>"></span>
                    </td>
                </tr>
                <tr class="rowa">
                    <td colspan="9"></td>
                </tr>

<?php           }    ?>

            <tr class="rowb">
                <td colspan="5" style="text-align: right;">
        <input type="hidden" id="totalnum" value="<?=$numAdds?>" />
                        <span style="float:left">
                            <a href="#" onclick="reload_num_forms('awards_auto')">reload</a>
                            with <input style="width:30px;" type="text" name="numadd" id="numAdds" value="<?=$numAdds?>" title="Number of add forms to show (1 - 20)"/>
                            add forms
                        </span>
                    <input type="submit" name="create" value="Create" title="Create all badges selected" />
                </td>
                <td colspan="4" style="text-align: center;">
                    <label for="returntop">return to top</label>
                    <input type="checkbox" name="returntop" value="1" title="If checked you will return to the top of the page after adding (otherwise you will return to where the new items are in the list)" />
                </td>
            </tr>
        </table>
    </form>

    <br/><br/>
    <div class="head"></div>
    <div class="box pad">
        When awarding these the system checks for users that do not have this badge, then checks those results against the Parameter and Value settings to determine who should get the award.
        Do not have the same badge being awarded by 2 different active items, or at least be aware the user will only get one and then be blocked from receiving the other.
        <p><strong>note:</strong> Badges are defined in the <a href="/tools.php?action=badges_list">Badges Manager</a></p>

        <form action="tools.php" method="post">
            <input type="hidden" name="action" value="awards_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <input type="submit" name="createcats" value="Automatically Create Category award schedules - CAUTION - deletes all category awards first" title="Automatically create category award schedules" />
        </form>
    </div><br/>

    <div class="head">Manage Automatic Awards</div>
    <form id="editawards" action="tools.php" method="post">
        <input type="hidden" name="action" value="awards_alter" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <table>
            <tr class="colhead">
                <td width="10px" rowspan="2">Edit</td>
                <td width="40px" rowspan="2">Image</td>
                <td width="25%">Badge</td>
                <td width="15%">Parameter</td>
                <td width="60px">Value</td>
                <td width="80px">Category<br />(NumUploaded only)</td>
                <td width="40px">Send PM</td>
                <td width="40px">Active</td>
                <td width="10px" rowspan="2">Delete</td>
            </tr>
            <tr class="colhead">
                <td colspan="6">Description (from badges)</td>
            </tr>
<?php

            $DB->query("SELECT ba.ID, ba.BadgeID, Title, Action, SendPM, Value, CategoryID, Description, Image , Active
                      FROM badges_auto AS ba
                      JOIN badges AS b ON b.ID=ba.BadgeID ORDER BY b.Sort");

            $Row = 'b';
            while (list($ID, $BadgeID, $Name, $Action, $SendPM, $Value, $CategoryID, $Description, $Image, $Active) = $DB->next_record()) {
                  $Row = ($Row === 'a' ? 'b' : 'a');
?>

                <tr class="rowb">
                    <td rowspan="2" style="vertical-align: top">
                        <a id="<?=$ID?>"></a>#<?=$ID?><br/><br/>
                        <input type="checkbox" id="id_<?=$ID?>" name="id[<?=$ID?>]" value="<?=$ID?>" title="If checked edits to this award schedule will be saved when you click on 'Save changes'" />
                    </td>
                    <td class="center" rowspan="2">
                        <div class="badge">
                            <span id="image<?=$ID?>">
                                <img src="<?=STATIC_SERVER.'common/badges/'.$Image?>" title="<?=$Name.'. '.$Description?>" alt="<?=$Name?>" />
                            </span>
                        </div>
                    </td>
                    <td>
<?php                       print_badges_select($ID, $BadgeID); ?>
                    </td>
                    <td>
                        <select name="type[<?=$ID?>]" onchange="Set_Edit('<?=$ID?>')" >
<?php                           foreach ($AutoAwardTypes as $Act) {   ?>
                                <option value="<?=$Act?>"<?=($Act==$Action?' selected="selected"':'')?> ><?=$Act?>&nbsp;&nbsp;&nbsp;&nbsp;</option>
<?php                           } ?>
                        </select>
                    </td>
                    <td>
                          <input class="medium" type="text" name="value[<?=$ID?>]" onchange="Set_Edit('<?=$ID?>')" value="<?=display_str($Value)?>" />
                    </td>
                    <td>
                        <?php  print_categories($ID, $CategoryID) ?>
                    </td>
                    <td class="center">
                          <input type="checkbox" name="sendpm[<?=$ID?>]" value="1" <?=($SendPM?'checked="checked"':'')?> onchange="Set_Edit('<?=$ID?>')" title="If checked then the user is sent a PM telling them when they recieve this award" />
                    </td>
                    <td class="center">
                          <input type="checkbox" name="active[<?=$ID?>]" value="1" <?=($Active?'checked="checked"':'')?> onchange="Set_Edit('<?=$ID?>')" title="If checked this award will be automatically distributed to users who meet the specified requirements" />
                    </td>
                    <td rowspan="2">
                        <input type="checkbox" name="deleteids[]" value="<?=$ID?>" title="If checked this badge will be selected for delete" />
                    </td>
                </tr>
                <tr class="rowb">
                    <td colspan="6">
                          <span id="desc<?=$ID?>"><?=display_str($Description)?></span>
                    </td>
                </tr>
                <tr class="rowa">
                    <td colspan="9">
                    </td>
                </tr>
<?php           }  ?>
            <tr class="rowb">
                <td colspan="5" style="text-align: right;">
                    <input type="submit" name="saveall" value="Save changes" title="Save changes for all selected automatic awards schedules" />
                </td>
                <td colspan="4" style="text-align: right;">
                    <input type="submit" name="delselected" value="Delete selected" title="Delete selected auto award schedules" />
                </td>
            </tr>
        </table>
    </form>
</div>
<?php
show_footer();
