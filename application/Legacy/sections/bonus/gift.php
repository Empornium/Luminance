<?php

global $classes, $master;

if (!check_perms('site_give_specialgift')) {
    error(404);
}
$bbCode = new \Luminance\Legacy\Text;

enforce_login();
show_header('Special Gift', 'specialgift,bonus,bbcode,jquery');

$wallet = $master->repos->userWallets->get('UserID = ?', [$activeUser['ID']]);

$classOptions    = ['any', 'Apprentice', 'Perv or lower', 'Good Perv or lower', 'Good Perv or higher', 'Sextreme Perv or higher'];
$RatioOptions    = ['any', 'very low (below 0.5)', 'low (below 1.0)', 'good (above 1.0)', 'excellent (above 5.0)'];
$CreditOptions   = ['any', 'poor (3,000 or less)', 'has some (12,000 or less)', 'rich (12,000 or more)'];
$ActivityOptions = ['now (within the last hour)', 'today (within the last 24 hours)', 'recently (within the last 3 days)', 'not too long ago (within the last week)'];

/* We should validate these.*/
if (empty($_GET['class']) || !in_array($_GET['class'], $classOptions)) {
    $REQUIRED_CLASS    = 'any';
} else {
    $REQUIRED_CLASS    = $_GET['class'];
}
if (empty($_GET['ratio']) || !in_array($_GET['ratio'], $RatioOptions)) {
    $REQUIRED_RATIO    = 'any';
} else {
    $REQUIRED_RATIO    = $_GET['ratio'];
}
if (empty($_GET['credits']) || !in_array($_GET['credits'], $CreditOptions)) {
    $REQUIRED_CREDITS  = 'any';
} else {
    $REQUIRED_CREDITS  = $_GET['credits'];
}
if (empty($_GET['activity']) || !in_array($_GET['activity'], $ActivityOptions)) {
    $REQUIRED_ACTIVITY = 'any';
} else {
    $REQUIRED_ACTIVITY = $_GET['activity'];
}

?>
<div class="thin">
    <h2>Special Gift</h2>
            <div class="box pad shadow">
<?php
                $creditinfo = get_article('creditsinline');
                if ($creditinfo) echo $bbCode->full_format($creditinfo, true);
?>
            </div>
<?php       if (!empty($_REQUEST['result'])) {  ?>
                <div class="box pad shadow">
                    <h3 class="center"><?=display_str($_REQUEST['result'])?></h3>
                </div>
<?php       } ?>

        <div class="head">
            <span style="float:left;">Bonus Credits</span>
        </div>
        <div class="box">
            <div class="pad" id="bonusdiv">
                <h4 class="center">Credits: <?=(!$wallet->Balance ? '0.00' : number_format($wallet->Balance,2))?></h4>
                <span style="float:right;"><a href="#" onclick="$('#bonuslogdiv').toggle(); this.innerHTML=(this.innerHTML=='(Show Log)'?'(Hide Log)':'(Show Log)'); return false;">(Show Log)</a></span>&nbsp;

                <div class="hidden" id="bonuslogdiv" style="padding-top: 10px;">
                    <div id="bonuslog" class="box pad scrollbox">
                        <?=(!$wallet->Log ? 'no bonus history' :$bbCode->full_format($wallet->Log))?>
                    </div>
<?php
                    $UserResults = $master->cache->getValue('sm_sum_history_'.$userID);
                    if ($UserResults === false) {
                        $UserResults = $master->db->rawQuery(
                            "SELECT Spins,
                                    Won,
                                    Bet,
                                    (Won/Bet)
                               FROM sm_results
                              WHERE UserID = ?",
                            [$userID]
                        )->fetch(\PDO::FETCH_BOTH);
                        $master->cache->cacheValue('sm_sum_history_'.$userID, $UserResults, 86400);
                    }
                    if (is_array($UserResults) && $UserResults[0] > 0) {

                        list($NumSpins, $TotalWon, $TotalBet, $TotalReturn) = $UserResults;
?>
                        <div class="box pad" title="<?="spins: $NumSpins | -$TotalBet | +$TotalWon | return: $TotalReturn"?>">
                            <strong>Slot Machine:</strong> <?= ($TotalWon-$TotalBet)?> credits
                        </div>
<?php
                    }
?>
                </div>
           </div>
        </div>
<?php   if ($classes[$activeUser['PermissionID']]['Level'] >= LEVEL_ADMIN) {
$PMText = get_gift_pm();
?>
            <div class="head">Gift PM</div>
            <div class="box pad">
                <div class="smallhead"><?=$PMText['Help']?></div>
                <form action="bonus.php" method="post" id="messageform">
                <input type="hidden" name="action" value="takecompose_giftpm" />
                <input type="hidden" name="UserID" value="<?=$activeUser['ID']?>" />
                <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                <?php  $bbCode->display_bbcode_assistant("quickpost", get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions'])); ?>
                <textarea id="quickpost" name="body" class="long" rows="10"><?=$PMText['Body']?></textarea> <br />
                <div id="preview" class="box vertical_space body hidden"></div>
                <div id="buttons" class="center">
                    <input type="button" value="Preview" onclick="Quick_Preview();" />
                    <input type="submit" value="Save PM" />
                </div>
                </form>
            </div>
<?php   }?>
    <div class="head">Special Gift</div>
        <form method="post" action="bonus.php" method="post" class="bonusshop" id="giftform">
            <input type="hidden" name="action" value="givegift" />
            <input type="hidden" name="UserID" value="<?=$activeUser['ID']?>" />
            <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
        <table class="bonusshop">
            <tr class="smallhead">
                <td>Class</td>
                <td>Ratio</td>
                <td>Credits</td>
                <td>Last Seen</td>
            </tr>
            <tr>
                <td>
                    <select name="class">
<?php               foreach ($classOptions as $classOption) { ?>
                        <option value="<?=$classOption?>" <?=($REQUIRED_CLASS==$classOption?' selected="selected"':'');?>>&nbsp;<?=$classOption?> &nbsp;</option>
<?php               } ?>
                    </select>
                </td>
                <td>
                    <select name="ratio">
<?php               foreach ($RatioOptions as $RatioOption) { ?>
                        <option value="<?=$RatioOption?>" <?=($REQUIRED_RATIO==$RatioOption?' selected="selected"':'');?>>&nbsp;<?=$RatioOption?> &nbsp;</option>
<?php               } ?>
                    </select>
                </td>
                <td>
                    <select name="credits">
<?php               foreach ($CreditOptions as $CreditOption) { ?>
                        <option value="<?=$CreditOption?>" <?=($REQUIRED_CREDITS==$CreditOption?' selected="selected"':'');?>>&nbsp;<?=$CreditOption?> &nbsp;</option>
<?php               } ?>
                    </select>
                </td>
                <td>
                    <select name="activity">
<?php               foreach ($ActivityOptions as $ActivityOption) { ?>
                        <option value="<?=$ActivityOption?>" <?=($REQUIRED_ACTIVITY==$ActivityOption?' selected="selected"':'');?>>&nbsp;<?=$ActivityOption?> &nbsp;</option>
<?php               } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td colspan=4, class="center">
                    <br />
                    <strong>Send a gift to a random perv matching your selected criteria</strong>
                </td>
            </tr>
        </table>
        <table>
            <tr class="smallhead">
                <td width="120px">Title</td>
                <td width="530px" colspan="2">Description</td>
                <td width="90px" colspan="2">Price</td>
            </tr>

<?php   $Row = 'b';
$Gifts = get_shop_items_gifts();
        foreach ($Gifts as $Gift) {
            list($ItemID, $Title, $Description, $Action, $Value, $Cost) = $Gift;
            $Row     = ($Row == 'a') ? 'b' : 'a';
            $CanBuy  = is_float((float) $activeUser['TotalCredits']) ? $activeUser['TotalCredits'] >= $Cost: false;
            $BGClass = ($CanBuy?' itembuy' :' itemnotbuy');
?>
            <tr class="row<?=$Row.$BGClass?>">
                <td width="160px"><strong><?=display_str($Title) ?></strong></td>
                <td style="border-right:none;"><?=display_str($Description)?></td>
                <td width="60px" style="text-align: center;"><strong><?=number_format($Cost) ?>c</strong></td>
                <td width="60px" style="text-align: center;">
                        <button class="shopbutton<?=($CanBuy ? ' itembuy' : ' itemnotbuy')?>" type="submit" name="itemid" value="<?=$ItemID?>" <?=($CanBuy ? '' : ' disabled="disabled"')?>><?=($CanBuy?'Buy':'x')?></button>
                </td>
            </tr>
<?php   } ?>
        </table>
        </form>
    </div>
<?php
show_footer();
