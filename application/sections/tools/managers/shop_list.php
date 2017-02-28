<?php
if (!check_perms('site_manage_shop')) { error(403); }

show_header('Manage Shop');

?>
<script type="text/javascript">//<![CDATA[
function Select_Action(element_id)
{
    var action = $('#shopaction'+element_id).raw().value;
    $('#cost'+element_id).disable(action=='badge');
}
//]]></script>
<div class="thin">
<h2>Manage Shop</h2>
<table>
    <tr class="head">
        <td colspan="8">Add shop item</td>
    </tr>
    <tr class="colhead">
        <td width="120px">Name</td>
        <td>Description</td>
        <td width="30px">Sort</td>
        <td width="80px">Action</td>
        <td width="60px">Value</td>
        <td width="70px">Cost</td>
        <td width="70px">S.Gift</td>
        <td width="120px"></td>
    </tr>
    <tr class="rowa">
    <form action="tools.php" method="post">
        <input type="hidden" name="action" value="shop_alter" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <td>
            <input class="medium" type="text" name="name" value="new name" />
        </td>
        <td>
            <input class="long" type="text" name="desc" value="description" />
        </td>
        <td>
            <input class="medium" type="text" name="sort" value="0" />
        </td>
        <td>
                <select name="shopaction" id="shopaction0" onchange="Select_Action(0)">
<?php                   foreach ($ShopActions as $act) {   ?>
                        <option value="<?=$act?>"><?=$act?>&nbsp;&nbsp;</option>
<?php                   } ?>
                </select>
        </td>
        <td>
            <input class="medium" type="text" name="value" value="0" />
        </td>
        <td>
            <input class="medium" type="text" name="cost" id="cost0" value="10000" />
        </td>
        <td>
            <input class="medium" type="checkbox" name="gift" id="gift0" value="1" />
        </td>
        <td>
            <input type="submit" value="Create" />
        </td>
    </form>
    </tr>
</table>
<br/><br/>
<div class="head">Auto Synch Badges Tool</div>
<div class="box pad">
    You can use this tool to automatically update the bonus shop with badge items.<br />
    It will remove all the current badge items from the bonus shop (if the checkbox is checked), then select from the badge table all badges with 'Shop' type set and insert them.<br />
    The cost is always taken from the badge table, Name and description will update from that table but you can alter the values for them here.<br />
    <br />
    <form action="tools.php" method="post">
        <input type="hidden" name="action" value="shop_alter" />
        <input type="hidden" name="autosynch" value="autosynch" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />

        <div style="margin-left: auto; margin-right: auto; max-width: 700px;">
            <span style="float: left">
                <label for="sort">Add sort values starting from this number</label>
                <input size="3" type="text" name="sort" value="100" />
            </span>
            <span style="float: right">
                <input type="checkbox" value="1" name="delete" checked="checked" title="If checked all current badge items will be removed from the shop before adding items from badge table" />
                <label for="delete">Delete current badges before insertion</label>
            </span>
        </div>
        <br /><br />
        <div style="text-align: center;">
            <input type="submit" value="Auto synchronise shop items from badges table" />
        </div>
    </form>
</div><br/>
<div class="head">Manage Shop Items</div>
<table>
    <tr class="colhead">
        <td width="120px">Name</td>
        <td>Description</td>
        <td width="30px">Sort</td>
        <td width="80px">Action</td>
        <td width="60px">Value</td>
        <td width="70px">Cost</td>
        <td width="70px">S.Gift</td>
        <td width="120px"></td>
    </tr>
<?php

$DB->query("SELECT
                        s.ID,
                        s.Title,
                        s.Description,
                        s.Action,
                        s.Value,
                        IF(Action='badge',b.Cost,s.Cost) AS Cost,
                        s.Sort,
                        s.Gift
            FROM bonus_shop_actions AS s
                    LEFT JOIN badges AS b ON b.ID=s.Value
            ORDER BY s.Sort");
$Row = 'b';
while (list($ID, $Title, $Description, $ShopAction, $Value, $Cost, $Sort, $Gift) = $DB->next_record()) {
    $Row = ($Row === 'a' ? 'b' : 'a');
?>
    <tr class="row<?=$Row?>">
      <form action="tools.php" method="post">
                <input type="hidden" name="action" value="shop_alter" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="id" value="<?=$ID?>" />

        <td>
            <input class="medium" type="text" name="name" value="<?=$Title?>" />
        </td>
        <td>
            <input class="long" type="text" name="desc" value="<?=$Description?>" />
        </td>
        <td>
            <input class="medium" type="text" name="sort" value="<?=$Sort?>" />
        </td>
        <td>
                <select name="shopaction" id="shopaction<?=$ID?>" onchange="Select_Action(<?=$ID?>)">
<?php                   foreach ($ShopActions as $act) {   ?>
                        <option value="<?=$act?>"<?=($act==$ShopAction?' selected="selected" ':'')?>><?=$act?>&nbsp;&nbsp;</option>
<?php                   } ?>
                </select>
        </td>
        <td>
            <input class="medium" type="text" name="value" value="<?=$Value?>" />
        </td>
        <td>
            <input class="medium" type="text" name="cost" id="cost<?=$ID?>" value="<?=$Cost?>"<?=($ShopAction=='badge'?' disabled="disabled" title="Cost is defined in badges" ':'')?> />
        </td>
        <td>
            <input class="medium" type="checkbox" name="gift" id="gift<?=$ID?>" value="1" <?= ($Gift == 1)? 'checked' : '' ?>/>
        </td>

        <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" />
        </td>
      </form>
    </tr>
<?php  }  ?>
</table>
</div>
<?php
show_footer();
