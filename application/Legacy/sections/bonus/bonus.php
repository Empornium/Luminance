<?php
$bbCode = new \Luminance\Legacy\Text;

enforce_login();
show_header('Bonus Shop', 'bonus,bbcode,jquery,jquery.modal');

$wallet = $master->repos->userWallets->get('UserID = ?', [$activeUser['ID']]);

$ShopItems = get_shop_items($activeUser['ID']);
?>
<div class="thin">
    <h2>Bonus shop</h2>
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
<?php       }

?>

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


    <div class="head">Bonus Shop</div>
    <?php if (!check_perms('site_purchase_invites') && shop_has_invites($activeUser['ID'])) {?>
    <div class="warnstrip" id="warnstrip">Your user class can not purchase invites. Please check <a href="/articles/view/ranks">the ranks page</a> for more information.</a></div>
    <?php }
    ?>

        <table class="bonusshop">
            <tr class="smallhead">
                <td width="120px">Title</td>
                <td width="530px" colspan="2">Description</td>
                <td width="90px" colspan="2">Price</td>
            </tr>
<?php
    $Row = 'a';
    $UserBadgeIDs = get_user_shop_badges_ids($activeUser['ID']);
    $LastBadge = null;
    foreach ($ShopItems as $BonusItem) {
        list($ItemID, $Title, $Description, $Action, $Value, $Cost, $Image, $Badge, $Rank, $UserRank) = $BonusItem;
            $IsBadge = $Action=='badge';
            $IsBuyGB = $Action=='gb';
            $DescExtra='';
            // if user already has badge item dont allow buy
            if ($IsBadge && in_array($Value, $UserBadgeIDs)) {
                $CanBuy = false;
                $BGClass= ' itemduplicate';
            } elseif ($IsBuyGB && $activeUser['BytesDownloaded'] <=0) {
                $CanBuy = false;
                $BGClass= ' itemnotbuy';
            } elseif ($Action == 'invite') {
                $CanBuy = check_perms('site_purchase_invites');
            } else {
                $CanBuy = is_float((float) $activeUser['TotalCredits']) ? $activeUser['TotalCredits'] >= $Cost: false;
                $BGClass= ($CanBuy?' itembuy' :' itemnotbuy');
                if ($IsBuyGB && $activeUser['BytesDownloaded'] < get_bytes($Value.'gb')) {
                    $DescExtra = "<br/>(WARNING: will only remove ".get_size($activeUser['BytesDownloaded']) .")";
                }
                if ($IsBadge) {
                    if ($LastBadge==$Badge) {
                        $CanBuy = false;
                        $BGClass = ' itemnotbuy';
                    } elseif ($Rank < $UserRank) {
                        $CanBuy = false;
                        $BGClass = ' itemduplicate';
                    } else
                        $LastBadge=$Badge;
                }
            }
        $Row = ($Row == 'a') ? 'b' : 'a';
?>
            <tr class="row<?=$Row.$BGClass?>">
                <td width="160px"><strong><?=display_str($Title) ?></strong></td>
                <td style="border-right:none;" <?php if (!$Image) { echo 'colspan="2"'; } ?>><?=display_str($Description).$DescExtra?></td>
                    <?php if ($Image) {  ?>
                        <td style="border-left:none;width:160px;text-align:center;">
                            <div class="badge">
                                <img src="<?=STATIC_SERVER.'common/badges/'.$Image?>" title="<?=$Title?>" alt="<?=$Title?>" />
                            </div>
                        </td>
                    <?php   }
                        $IsGift = false;
                        if ($Action == 'givegb' || $Action == 'givecredits') {
                            $IsGift = true;
                        } elseif ($Action == 'title') {
                            $OnSubmit = 'onsubmit="if (confirm(\'Are you sure you want to buy a Custom Title?\')) {
                                return SetTitle(\'title'.$ItemID.'\');
                            } return false;"';
                        } elseif ($Action == 'ufl') {
                            $OnSubmit = 'onsubmit="if (confirm(\'Are you sure you want to buy an Unlimited FreeLeech?\')) {
                                return SetTorrent(\'torrentid'.$ItemID.'\');
                            } return false;"';
                        } elseif ($Action == 'gb') {
                            $OnSubmit = 'onsubmit="return confirm(\'Are you sure you want to take away '.$Value.'GiB from what you\'ve downloaded?\');"';
                        } elseif ($Action == 'slot') {
                            $OnSubmit = 'onsubmit="return confirm(\'Are you sure you want to buy '.$Value.' slot(s)?\');"';
                        } elseif ($Action == 'badge') {
                            $OnSubmit = 'onsubmit="return confirm(\'Are you sure you want to buy the '.$Value.' bling?\');"';
                        } elseif ($Action == 'pfl') {
                            $OnSubmit = 'onsubmit="return confirm(\'Are you sure you want to buy a Personal FreeLeech?\');"';
                        } elseif ($Action == 'invite') {
                            $OnSubmit = 'onsubmit="return confirm(\'Are you sure you want to buy an invite?\');"';
                        } else {
                            $OnSubmit = 'onsubmit="return confirm(\'Are you sure you want to perform this action?\');"';
                        }
                   ?>
                <td width="60px" style="text-align: center;"><strong><?=number_format($Cost) ?>c</strong></td>
                <td width="60px" style="text-align: center;">
                    <?php if (!$IsGift): ?>
                        <form method="post" action="" <?=$OnSubmit?>>
                            <input type="hidden" name="action" value="buy" />
                            <input type="hidden" id="othername<?=$ItemID?>" name="othername" value="" />
                            <input type="hidden" id="message<?=$ItemID?>" name="message" value="" />
                            <input type="hidden" name="shopaction" value="<?=$Action?>" />
                            <input type="hidden" name="userid" value="<?=$activeUser['ID']?>" />
                            <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                            <input type="hidden" name="itemid" value="<?=$ItemID?>" />
                            <input class="shopbutton<?=($CanBuy ? ' itembuy' : ' itemnotbuy')?>" name="submit" value="<?=($CanBuy?'Buy':'x')?>" type="submit"<?=($CanBuy ? '' : ' disabled="disabled"')?> />
                            <?php if ($Action == 'title') echo '<input type="hidden" id="title'.$ItemID.'" name="title" value="" />'; ?>
                            <?php if ($Action == 'ufl') echo '<input type="hidden" id="torrentid'.$ItemID.'" name="torrentid" value="" />'; ?>
                        </form>
                    <?php else: ?>
                        <?php if ($CanBuy): ?>
                            <a href="/bonus.php?action=buygiftuser&itemid=<?=$ItemID?>&shopaction=<?=$Action?>" rel="modal:open">
                                <input type="button" class="shopbutton itembuy" value="Buy" />
                            </a>
                        <?php else: ?>
                            <input type="button" class="shopbutton itemnotbuy" disabled="disabled" value="x" />
                        <?php endif ?>
                    <?php endif ?>
                </td>
            </tr>
<?php	} ?>
        </table>
</div>
<?php
show_footer();
