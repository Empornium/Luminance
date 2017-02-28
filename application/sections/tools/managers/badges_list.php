<?php
if (!check_perms('site_manage_badges')) { error(403); }

// get all images in badges directory for drop down
$imagefiles = scandir($master->public_path.'/static/common/badges');
$imagefiles= array_diff($imagefiles, array('.','..'));

function print_select_image($ElementID, $CurrentImage='')
{
    global $imagefiles;
?>
    <select name="image[<?=$ElementID?>]" id="imagesrc<?=$ElementID?>" onchange="Select_Image('<?=$ElementID?>')" title="Select Image">
<?php       foreach ($imagefiles as $image) {    ?>
            <option value="<?=$image?>"<?=($image==$CurrentImage?' selected="selected"':'')?>><?=$image?>&nbsp;</option>
<?php       } ?>
    </select>
<?php
}

show_header('Badges','badges');

?>
<div class="thin">
    <h2>Badges</h2>
    <form action="tools.php" method="post">
        <input type="hidden" name="action" value="badges_alter" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <table>
            <tr>
                <td colspan="10" class="head">Add badge(s)</td>
            </tr>
            <tr class="colhead">
                    <td width="10px" rowspan="2">Add</td>
                    <td width="50px" rowspan="2">Image</td>
                    <td colspan="2">Title</td>
                    <td colspan="5">Description</td>
                    <td width="40px" rowspan="2"></td>
            </tr>
            <tr class="colhead">
                    <td width="260px">Select Image</td>
                    <td width="18%">Badge Set</td>
                    <td width="12%">Rank</td>
                    <td width="30px">Display</td>
                    <td width="60px">Sort</td>
                    <td width="80px">Type</td>
                    <td width="12%">Cost</td>
            </tr>
            <?php

            $numAdds = isset($_REQUEST['numadd'])?(int) $_REQUEST['numadd']:5;
            if ($numAdds<1 || $numAdds > 20) $numAdds = 1;

            foreach ($BadgeTypes as $valtype) {
                $badge_select_html .= '<option value="'.$valtype.'">'.$valtype.'&nbsp;&nbsp;</option>';
            }

            for ($i = 0; $i < $numAdds; $i++) {
                $ID = "new$i";
            ?>
                <tr class="rowb">
                    <td rowspan="2" style="vertical-align: top">
                        <input type="checkbox" id="id_<?=$ID?>" name="id['<?=$ID?>']" value="<?=$ID?>" title="If checked this badge will be created when you click on 'Save changes'" />
                    </td>
                    <td rowspan="2" class="center" id="image<?=$ID?>" style="vertical-align: top;width:40px"> <a id="<?=$ID?>"></a>
                        <img src="<?=STATIC_SERVER.'common/badges/'.$Image?>" title="<?=$Image?>" alt="<?=$Image?>" />
                    </td>
                    <td colspan="2">
                        <input class="long" type="text" name="title[<?=$ID?>]" id="title<?=$ID?>" value="new title" onchange="Set_Edit('<?=$ID?>')" title="Title"/>
                    </td>
                    <td colspan="5">
                        <input class="long" type="text" name="desc[<?=$ID?>]" id="desc<?=$ID?>" value="awarded for XXXXXX. This user has doneY/achievedZ" onchange="Set_Edit('<?=$ID?>')" title="Description"/>
                    </td>
                    <td rowspan="2">
                        <a href="#" onclick="Fill_From(<?=$i?>,['badge','title','image','imagesrc','desc','type','row','rank','sort','cost'])" title="fill other add forms with this forms values">fill</a>
                    </td>
                </tr>
                <tr class="rowb">
                    <td>
<?php                       print_select_image($ID); ?>
                    </td>
                    <td>
                        <input class="medium" type="text" name="badge[<?=$ID?>]" id="badge<?=$ID?>" value="new set" onchange="Set_Edit('<?=$ID?>')" title="Set Name (Users can only have one badge from a set, rank determines which badge replaces which when awarded)"/>
                    </td>
                    <td>
                        <input class="medium" type="text" name="rank[<?=$ID?>]" id="rank<?=$ID?>" value="1" onchange="Set_Edit('<?=$ID?>')" title="Rank (Within a set badges with a higher rank will displace badges with a lower rank)"/>
                    </td>
                    <td>
                        <input style="width:30px" type="text" name="row[<?=$ID?>]" id="row<?=$ID?>" value="0" onchange="Set_Edit('<?=$ID?>')" title="Row to display this badge in (0 is first)"/>
                    </td>
                    <td>
                        <input style="width:60px" type="text" name="sort[<?=$ID?>]" id="sort<?=$ID?>" value="0" onchange="Set_Edit('<?=$ID?>')" title="Sort"/>
                    </td>
                    <td>
                        <select name="type[<?=$ID?>]" id="type<?=$ID?>" onchange="Set_Edit('<?=$ID?>')" title="Badge Type">
                            <?=$badge_select_html; ?>
                        </select>
                    </td>
                    <td>
                        <input class="medium" type="text" name="cost[<?=$ID?>]" id="cost<?=$ID?>" value="" onchange="Set_Edit('<?=$ID?>')" title="Cost (Only used if item is a shop or donor badge)"/>
                    </td>
                </tr>
                <tr class="rowa">
                    <td colspan="10" class="noborder"></td>
                </tr>
<?php           }       ?>
                <tr class="rowb">
                    <td colspan="6" style="text-align: right;">
                        <input type="hidden" id="totalnum" value="<?=$numAdds?>" />
                        <span style="float:left">
                            <a href="#" onclick="reload_num_forms('badges_list')">reload</a>
                            with <input style="width:30px;" type="text" name="numadd" id="numAdds" value="<?=$numAdds?>" title="Number of add forms to show (1 - 20)"/>
                            add forms
                        </span>
                        <input type="submit" name="create" value="Create" title="Create all badges selected" />
                    </td>
                    <td colspan="4" style="text-align: center;">
                        <label for="returntop">return to top</label>
                        <input type="checkbox" name="returntop" value="1" title="If checked you will return to the top of the page after adding (otherwise you will return to where the new badges are in the list)" />
                    </td>
                </tr>
        </table>
    </form>

    <br/><br/>
    <div class="head"> </div>
    <div class="box pad">
        <h3>Image</h3>
        <ul><li>Images are listed from the common/badges/ directory</li></ul>
        <h3>Type</h3>
        <ul>
            <li>Unique   = Can only be awarded to one user on the site at once.</li>
            <li>Single   = Can be awarded once to each user ** Only single type badges can be selected to be awarded automatically.</li>
            <li>Multiple = Can be awarded multiple times to each user.</li>
            <li>Shop     = Can be bought in the shop *** (needs a seperate entry in bonus_shop_actions to appear in shop - this value is used to both build that entry automatically and filters the entry from other actions.</li>
        </ul>
        All badges except those with 'Shop' type can be awarded by staff (who have 'users_edit_badges' permission)<br /><br />
        <h3>Sort</h3>
        <ul><li>the sort order defines what order badges are displayed in on a users profile and posts</li></ul>
        <h3>Please Note</h3>
        <ul>
            <li>Deleting an award will remove it from all the users who currently have the award.</li>
            <li>To set up automatic awards use the <a href="/tools.php?action=awards_auto">Automatic Awards Manager</a></li>
            <li>To add 'shop' type badges to the shop use the <a href="/tools.php?action=shop_list">Bonus Shop Manager</a></li>
        </ul>
    </div><br/>
    <div class="head">available images<span style="float:right;"><a href="#" onclick="$('#badgeimages').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(View)</a></span></div>
    <div id="badgeimages" class="box pad hidden">
<?php       foreach ($imagefiles as $image) {    ?>
            <div style="display: inline-block;margin: 3px;">
                <img src="<?=STATIC_SERVER.'common/badges/'.$image?>" title="<?=$image?>" alt="<?=$image?>" />
                <br/><?=$image?>
            </div>
<?php       } ?>
    </div>
    <br/>

    <div class="head">Manage Badges</div>
    <form id="editbadges" action="tools.php" method="post">
        <input type="hidden" name="action" value="badges_alter" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <table>
            <tr class="colhead">
                <td width="10px" rowspan="2">ID<br/>-<br/>Edit</td>
                <td width="40px" rowspan="2">Image</td>
                <td colspan="2" >Title</td>
                <td colspan="5">Description</td>
                <td width="10px" rowspan="2">Delete</td>
            </tr>
            <tr class="colhead">
                <td width="80px">Select Image</td>
                <td width="18%">Badge Set</td>
                <td width="12%">Rank</td>
                <td width="30px">Display</td>
                <td width="60px">Sort</td>
                <td width="80px">Type</td>
                <td width="12%">Cost</td>
            </tr>
<?php
            $DB->query("SELECT ID, Badge, Rank, Type, Display, Sort, Cost, Title, Description, Image
                  FROM badges ORDER BY Display, Sort, Rank");

            while (list($ID, $Badge, $Rank, $Type, $Display, $Sort, $Cost, $Title, $Description, $Image) = $DB->next_record()) {
                $Row = ($Row === 'a' ? 'b' : 'a');
?>
                <tr class="rowb" style="border-top: 1px solid;">
                    <td rowspan="2" style="vertical-align: top">
                        <a id="<?=$ID?>"></a>#<?=$ID?><br/><br/>
                        <input type="checkbox" id="id_<?=$ID?>" name="id[<?=$ID?>]" value="<?=$ID?>" title="If checked edits to this badge will be saved when you click on 'Save changes'" />
                    </td>
                    <td  rowspan="2" class="center" id="image<?=$ID?>">
                        <img src="<?=STATIC_SERVER.'common/badges/'.$Image?>" title="<?=$Image?>" alt="<?=$Image?>" />
                    </td>
                    <td colspan="2">
                        <input class="long" type="text" name="title[<?=$ID?>]" value="<?=display_str($Title)?>" title="Title" onchange="Set_Edit(<?=$ID?>)"/>
                    </td>
                    <td colspan="5">
                        <input class="long" type="text" name="desc[<?=$ID?>]" value="<?=display_str($Description)?>" title="Description" onchange="Set_Edit(<?=$ID?>)"/>
                    </td>
                    <td rowspan="2">
                        <input type="checkbox" name="deleteids[]" value="<?=$ID?>" title="If checked this badge will be selected for delete" />

                    </td>
                </tr>
                <tr class="rowb">
                    <td >
<?php                       print_select_image($ID, $Image); ?>
                    </td>
                    <td>
                        <input class="medium" type="text" name="badge[<?=$ID?>]" value="<?=display_str($Badge)?>" onchange="Set_Edit(<?=$ID?>)" title="Set Name (Users can only have one badge from a set, rank determines which badge replaces which when awarded)"/>
                    </td>
                    <td>
                        <input class="medium" type="text" name="rank[<?=$ID?>]" value="<?=display_str($Rank)?>" onchange="Set_Edit(<?=$ID?>)" title="Rank (Within a set badges with a higher rank will displace badges with a lower rank)"/>
                    </td>
                    <td>
                        <input style="width:30px" type="text" name="row[<?=$ID?>]" value="<?=display_str($Display)?>" onchange="Set_Edit('<?=$ID?>')" title="Row to display this badge in (0 is first)"/>
                    </td>
                    <td>
                        <input style="width:60px" type="text" name="sort[<?=$ID?>]" value="<?=display_str($Sort)?>" onchange="Set_Edit(<?=$ID?>)" title="Sort"/>
                    </td>
                    <td>
                        <select name="type[<?=$ID?>]" title="Badge Type" onchange="Set_Edit(<?=$ID?>)">
<?php                           foreach ($BadgeTypes as $valtype) {   ?>
                                <option value="<?=$valtype?>"<?=($valtype==$Type?' selected="selected"':'')?>><?=$valtype?>&nbsp;&nbsp;</option>
<?php                           } ?>
                        </select>
                    </td>
                    <td>
                        <input class="medium" type="text" name="cost[<?=$ID?>]" value="<?=display_str($Cost)?>" onchange="Set_Edit(<?=$ID?>)" title="Cost (Only used if item is a shop or donor badge)"/>
                    </td>
                </tr>
                <tr class="rowa">
                    <td colspan="10" class="noborder"></td>
                </tr>
<?php           }   ?>
                <tr class="rowb">
                    <td colspan="6" style="text-align: right;">
                        <input type="submit" name="saveall" value="Save changes" title="Save changes for all badges selected for editing" />
                    </td>
                    <td colspan="4" style="text-align: right;">
                        <input type="submit" name="delselected" value="Delete selected" title="Delete selected badges." />
                    </td>
                </tr>
        </table>
    </form>
</div>
<?php
show_footer();
