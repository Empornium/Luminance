<?php
/*
 * Slot Machine : calculate actual result + do db stuff, results are passed back to js
 * to show reels spinning etc in the interface
 */

enforce_login();

if (!check_perms( 'site_play_slots')) ajax_error ("You do not have permission to play the xxx slot machine!");

include(SERVER_ROOT.'/sections/bonus/slot_xxx_arrays.php');

header('Content-Type: application/json; charset=utf-8');

$FloodCheck = $Cache->get_value('slots_floodcheck_'.$LoggedUser['ID']);
if($FloodCheck !== false) ajax_error("You must wait 5 secs before playing again");
$Cache->cache_value('slots_floodcheck_'.$LoggedUser['ID'], true, 5);

$BetAmount = (int) $_POST['bet'];
if (!$BetAmount) {
    if ( (int) $_GET['bet'] > 0 ) ajax_error('cheeky! - you have been reported for trying to haxx0r the slot machine!');
    else ajax_error('No bet');
}

// use a more rigorous check to avoid -ve bets etc
if(!in_array($BetAmount, array(1,10,100)))  ajax_error('You can only bet valid values: 1, 10, or 100');

$UserID = (int) $LoggedUser['ID'];
$NumBets = min( max((int) $_POST['numbets'], 1), 3);
$TotalBet = $NumBets * $BetAmount;

if($LoggedUser['TotalCredits']<$TotalBet) ajax_error("Not enough credits to bet ".number_format ($TotalBet));

$Pos = array();

// where the reels stop for this spin (at reelC, calculate other positions from this)
$Pos[0] = mt_rand(0, 19);
$Pos[1] = mt_rand(0, 19);
$Pos[2] = mt_rand(0, 19);
$Pos[3] = mt_rand(0, 19);

$Result = '';       // get a result string in format XXXXAAAADDDD for storage
$Reels=array();     // how many reels match in each line (for interface)
$Win=0;             // total winnings for this spin

// middle line
get_result($Result,$BetAmount, $Win,($Pos[0]+1)%20,($Pos[1]+1)%20,($Pos[2]+1)%20,($Pos[3]+1)%20);
if ($NumBets>1) {    // bottom line
    get_result($Result,$BetAmount, $Win,$Pos[0],$Pos[1],$Pos[2],$Pos[3]);
}
if ($NumBets>2) {    // top line
     get_result($Result,$BetAmount, $Win,($Pos[0]+2)%20,($Pos[1]+2)%20,($Pos[2]+2)%20,($Pos[3]+2)%20);
}

// record spins
$DB->query( "INSERT INTO sm_results (UserID, Won, Bet, Spins, Result, Time)
                      VALUES ( '$UserID','$Win','$BetAmount','$NumBets','$Result','".sqltime()."')");
$Cache->delete_value('sm_sum_history');
$Cache->delete_value("sm_sum_history_$UserID");

$HighPayout = $Cache->get_value('sm_lowest_top_payout');
 if ($Win>=$HighPayout) {
     $Cache->delete_value('sm_top_payouts');
}

$DB->query("UPDATE users_main SET Credits=(Credits+$Win-$TotalBet) WHERE ID=$UserID");

$LoggedUser['TotalCredits'] += ($Win-$TotalBet);
$LoggedUser['Credits'] += ($Win-$TotalBet);

$Cache->delete_value('user_stats_'.$UserID);

$Results = array();
$Results[0] = $Pos[0];
$Results[1] = $Pos[1];
$Results[2] = $Pos[2];
$Results[3] = $Pos[3];
$Results[4] = $Win;
$Results[5] = $Reels;
$Results[6] = $Result;

echo json_encode($Results);

function ajax_error($Error)
{
    echo json_encode($Error);
    die();
}

function get_result(&$Result, $BetAmount, &$Win, $Pos0, $Pos1, $Pos2, $Pos3)
{
    global $Reel, $Payout, $Reels;

    if ($Reel[0][$Pos0]!='X' && $Reel[0][$Pos0]==$Reel[1][$Pos1] && $Reel[1][$Pos1]==$Reel[2][$Pos2]) {

        if ($Reel[2][$Pos2]==$Reel[3][$Pos3]) {
                // 4 reel match
                $Reels[] = 4;
                $Win += ($BetAmount * $Payout[$Reel[0][$Pos0]][1]);
        } else {
                // 3 reel match
                $Reels[] = 3;
                $Win += ($BetAmount * $Payout[$Reel[0][$Pos0]][0]);
        }

    } elseif ($Reel[0][$Pos0]=='A' && $Reel[0][$Pos0]==$Reel[1][$Pos1]) {
            // 2 reel A
            $Reels[] = 2;
            $Win += ($BetAmount * 4);
    } elseif ($Reel[0][$Pos0]=='A') {
            // 1 reel A
            $Reels[] = 1;
            $Win += ($BetAmount * 2);
    } else {
            $Reels[] = 0;
            //$Win += 0;
    }

    $Result .= "{$Reel[0][$Pos0]}{$Reel[1][$Pos1]}{$Reel[2][$Pos2]}{$Reel[3][$Pos3]}";
}
