<?php
enforce_login();

if (!check_perms( 'site_play_slots')) error ("You do not have permission to play the xxx slot machine!");

include(SERVER_ROOT.'/sections/bonus/slot_xxx_arrays.php');

show_header('Slot Machine','slotmachine_xxx');

$BetAmount = 10;

?>
<script type="text/javascript"><?php      // get the reels array from sm_arrays into js
echo "var reelPix = ". json_encode($Reel) . ";\n"; ?>
</script>
<div class="thin">
    <h2>Slot Machine XXX</h2>

    <div style="float:right;width:260px;">

        <div class="head center"> payouts </div>
        <div class="box pad">
            <div class="box pad center"><strong class="important_text">note:</strong> all winning lines must start with the left hand reel</div>
            <span id="payout_table" class="reelsi"><?php print_payout_table($BetAmount) ?></span>
        </div>
        <div class="head center"> reels </div>
        <div class="box pad center">
            <?php
                $Count=array();
                $max=0;
                for ($i=0;$i<4;$i++) {
                    $Count[$i]= count($Reel[$i]);
                    $max = max($max,$Count[$i]);
                }
                for ($i=$max-1;$i>=0;$i--) { ?>
                    <div class="reelsi">
            <?php   for ($j=0;$j<4;$j++) {
                        if ($i<$Count[$j]) { ?>
                        <img src="<?=STATIC_SERVER?>common/casino/icon<?=$Reel[$j][$i]?>.png" />
            <?php       }
                    } ?>
                    </div>
            <?php  } ?>
        </div>
    </div>

    <div class=" " style="position:relative;width:664px;margin:20px 330px 50px auto;">

        <div class="head center"> slot machine </div>
        <div class="box pad">
            <table id="fmtop" class=" fm" >
                <tr>
                    <td class="noborder center"><input type="button" value="Bet" onclick="Change_Bet()" /><br/><input id="betamount" type="text" size="1" value="<?=$BetAmount?>" disabled="disabled" /></td>
                    <td class="noborder center"><input type="button" value="Plays" onclick="Change_NumBets()" /><br/><input id="numbets" type="text" size="1" value="3" disabled="disabled"/></td>
                    <td class="noborder center" style="width:50%;"><h3 id="result" style="color:blue;font-size:2.4em"></h3></td>
                    <td class="label">Credits:</td>
                    <td class="noborder"><span style="float:right;color:#333;font-size: large" id="winnings"><?=number_format($LoggedUser['TotalCredits'])?></span></td>
                </tr>
            </table>
        </div>
        <div class="box pad shadow">
            <div id="rollers" class="center">
                <div id="reelsa" class="reels">
                    <img id="reela0" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[0][2]?>.png" />
                    <img id="reela1" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[1][2]?>.png" />
                    <img id="reela2" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[2][2]?>.png" />
                    <img id="reela3" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[3][2]?>.png" />
            </div>
                <div id="reelsb" class="reels play">
                    <img id="reelb0" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[0][1]?>.png" />
                    <img id="reelb1" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[1][1]?>.png" />
                    <img id="reelb2" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[2][1]?>.png" />
                    <img id="reelb3" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[3][1]?>.png" />
            </div>
                <div id="reelsc" class="reels">
                    <img id="reelc0" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[0][0]?>.png" />
                    <img id="reelc1" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[1][0]?>.png" />
                    <img id="reelc2" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[2][0]?>.png" />
                    <img id="reelc3" src="<?=STATIC_SERVER?>common/casino/<?=$Reel[3][0]?>.png" />
            </div>
            </div>
            <div  style="float:right">
                <span title="if you cannot hear the sound you can try 'forcing' it, this forces the player to be rendered (although it hides it from view) and 'fixes' problems with a bug in FF22 but may make the screen flicker annoyingly... this is not a great solution but its the best I can do right now"><label for="forcesound">(force sound)</label>
                <input id="forcesound" name="forcesound" type="checkbox" value="1" /> </span> &nbsp;&nbsp;&nbsp;
                <label for="playsound">Sound</label>
                <input id="playsound" name="playsound" type="checkbox" value="1" checked="checked" />
            </div>
            <br/>
            <a href="#" style="position:absolute;right:-80px;top:120px;" onclick="Pull_Lever(); return false">
                <img id="lever" src="<?=STATIC_SERVER?>common/casino/leverUp.png" />
            </a>
            <div id="beta" class="chip hidden" style="top:188px;"><span id="betanum">10</span></div>
            <div id="betb" class="chip" style="top:344px;"><span id="betbnum">10</span></div>
            <div id="betc" class="chip hidden" style="top:499px;"><span id="betcnum">10</span></div>
        </div>

        <div class="head center"> your slot machine history </div>
        <div class="box pad">
            <?php
            $UserResults = $Cache->get_value('sm_sum_history_'.$LoggedUser['ID']);
            if ($UserResults === false) {
                $DB->query("SELECT Count(ID), SUM(Spins), SUM(Won),SUM(Bet*Spins),(SUM(Won)/SUM(Bet*Spins))
                          FROM sm_results WHERE UserID = $LoggedUser[ID]");
                $UserResults = $DB->next_record();
                $Cache->cache_value('sm_sum_history_'.$LoggedUser['ID'], $UserResults, 86400);
            }
            list($Num, $NumSpins, $TotalWon, $TotalBet, $TotalReturn) = $UserResults;

            ?>
            <table class="box pad fmresults">
                <tr>
                    <td class="fmheader">Spins</td>
                    <td class="fmheader">Bet</td>
                    <td class="fmheader">Won</td>
                    <td class="fmheader">Net</td>
                    <td class="fmheader">Return</td>
                </tr>
                <tr>
                    <td class=""><?="$NumSpins ($Num)"?></td>
                    <td class=""><?=$TotalBet?></td>
                    <td class=""><?=$TotalWon?></td>
                    <td class=""><?=($TotalWon-$TotalBet)?></td>
                    <td class=""><?=$TotalReturn?></td>
                </tr>
            </table>
        </div>

        <div class="head center"> winners </div>
        <div class="box pad">
            <?php
            $TotalResults = $Cache->get_value('sm_sum_history');
            if ($TotalResults === false) {
                $DB->query("SELECT Count(ID), SUM(Spins), SUM(Won),SUM(Bet*Spins),(SUM(Won)/SUM(Bet*Spins))
                          FROM sm_results");
                $TotalResults = $DB->next_record();
                $Cache->cache_value('sm_sum_history', $TotalResults, 86400);
            }
            list($Num, $NumSpins, $TotalWon, $TotalBet, $TotalReturn) = $TotalResults;

            ?>
            <table class="box pad fmresults">
                <tr>
                    <td class="fmheader">Spins</td>
                    <td class="fmheader">Bet</td>
                    <td class="fmheader">Won</td>
                    <td class="fmheader">Return</td>
                </tr>
                <tr>
                    <td class=""><?="$NumSpins ($Num)"?></td>
                    <td class=""><?=$TotalBet?></td>
                    <td class=""><?=$TotalWon?></td>
                    <td class=""><?=$TotalReturn?></td>
                </tr>
            </table>
            <table class="box pad fmresults">
                <tr>
                    <td class="fmheader"></td>
                    <td class="fmheader">Username</td>
                    <td class="fmheader">Bet</td>
                    <td class="fmheader">Won</td>
                    <td class="fmheader">Result</td>
                    <td class="fmheader">Time</td>
                </tr>
            <?php

            $TopResults = $Cache->get_value('sm_top_payouts');
            if ($TopResults === false) {
                $DB->query("SELECT s.UserID, Username, Won, Bet, Spins, Result, s.Time
                              FROM sm_results as s
                              JOIN users_main as u ON s.UserID=u.ID
                             WHERE Won > 0
                          ORDER BY Won DESC
                             LIMIT 100");
                $TopResults = $DB->to_array(false, MYSQLI_BOTH);
                $Cache->cache_value('sm_top_payouts', $TopResults, 3600*24);
                if (count($TopResults)<100) {
                    $Cache->cache_value('sm_lowest_top_payout', 0, 3600*24);
                } else {
                    list(, , $Won) = end($TopResults);
                    reset($TopResults);
                    $Cache->cache_value('sm_lowest_top_payout', $Won, 3600*24);
                }
            }
            $i=1;
            foreach ($TopResults as $Result) {
                list($UserID, $Username, $Won, $Bet, $Spins, $Reels, $Time) = $Result;
        ?>
                <tr>
                    <td class="noborder center"><strong><?=$i++?></strong></td>
                    <td class="noborder center"><strong><?=$Username?></strong></td>
                    <td class="noborder center"><?="$Spins x $Bet"?></td>
                    <td class="noborder center"><?=$Won?></td>
                    <td class="noborder center"><?=$Reels?></td>
                    <td class="noborder center"><?=time_diff($Time)?></td>
                </tr>
        <?php  }   ?>
            </table>
        </div>
    </div>
    <span id="sound" style="visibility:hidden;height:0px;"></span>
</div>

<?php
show_footer();
