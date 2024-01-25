<?php
/*
 * Slot Machine : calculate actual result + do db stuff, results are passed back to js
 * to show reels spinning etc in the interface
 */

enforce_login();

if (!check_perms('site_play_slots')) ajax_error ("You do not have permission to play the xxx slot machine!");

include(SERVER_ROOT.'/Legacy/sections/bonus/slot_xxx_arrays.php');

header('Content-Type: application/json; charset=utf-8');

$FloodCheck = $master->cache->getValue('slots_floodcheck_'.$activeUser['ID']);
if ($FloodCheck !== false) ajax_error("You must wait 5 secs before playing again");
$master->cache->cacheValue('slots_floodcheck_'.$activeUser['ID'], true, 5);

$BetAmount = (int) $_POST['bet'];
if (!$BetAmount) {
    if ((int) $_GET['bet'] > 0) {
        ajax_error('cheeky! - you have been reported for trying to haxx0r the slot machine!');
    } else {
        ajax_error('No bet');
    }
}

// use a more rigorous check to avoid -ve bets etc
if (!in_array($BetAmount, [1, 10, 100, 1000, 10000]))  ajax_error('You can only bet valid values: 1, 10, 100, 1000, or 10000');

$userID = (int) $activeUser['ID'];
$NumBets = min( max((int) $_POST['numbets'], 1), 3);
$TotalBet = $NumBets * $BetAmount;

$wallet = $master->repos->userWallets->get('UserID = ?', [$userID]);

if ($wallet->Balance<$TotalBet) ajax_error("Not enough credits to bet ".number_format ($TotalBet));

$Pos = [];

// where the reels stop for this spin (at reelC, calculate other positions from this)
$Pos[0] = mt_rand(0, 19);
$Pos[1] = mt_rand(0, 19);
$Pos[2] = mt_rand(0, 19);
$Pos[3] = mt_rand(0, 19);

$Result = '';   // get a result string in format XXXXAAAADDDD for storage
$reels=[];      // how many reels match in each line (for interface)
$Win=0;         // total winnings for this spin

// middle line
get_result($Result, $BetAmount, $Win, $reel, $reels, $payout, ($Pos[0]+1)%20,($Pos[1]+1)%20,($Pos[2]+1)%20,($Pos[3]+1)%20);
if ($NumBets>1) {    // bottom line
    get_result($Result, $BetAmount, $Win, $reel, $reels, $payout, $Pos[0], $Pos[1], $Pos[2], $Pos[3]);
}
if ($NumBets>2) {    // top line
    get_result($Result, $BetAmount, $Win, $reel, $reels, $payout, ($Pos[0]+2)%20,($Pos[1]+2)%20,($Pos[2]+2)%20,($Pos[3]+2)%20);
}

// record spins
$sqltime = sqltime();
$master->db->rawQuery(
    "INSERT INTO sm_results (UserID, Won, Bet, Spins, Time)
          VALUES ( ?, ?, ?, ?, ?)
              ON DUPLICATE KEY
          UPDATE Won = Won + ?,
                 Bet = Bet + ?,
                 Spins = Spins + ?,
                 Time = ?",
    [$userID, $Win, $TotalBet, $NumBets, $sqltime, $Win, $TotalBet, $NumBets, $sqltime]);
$master->cache->deleteValue('sm_sum_history');
$master->cache->deleteValue("sm_sum_history_$userID");

$HighPayout = $master->cache->getValue('sm_lowest_top_payout');
 if ($Win>=$HighPayout) {
     $master->cache->deleteValue('sm_top_payouts');
}

$wallet->adjustBalance($Win - $TotalBet);

$master->cache->deleteValue('user_stats_'.$userID);
$master->cache->deleteValue('_entity_User_legacy_'.$userID);

$Results = [];
$Results[0] = $Pos[0];
$Results[1] = $Pos[1];
$Results[2] = $Pos[2];
$Results[3] = $Pos[3];
$Results[4] = $Win;
$Results[5] = $reels;
$Results[6] = $Result;

echo json_encode($Results);

function ajax_error($Error) {
    echo json_encode($Error);
    die();
}

function get_result(&$Result, $BetAmount, &$Win, &$reel, &$reels, &$payout, $Pos0, $Pos1, $Pos2, $Pos3) {
    if ($reel[0][$Pos0]!='X' && $reel[0][$Pos0]==$reel[1][$Pos1] && $reel[1][$Pos1]==$reel[2][$Pos2]) {

        if ($reel[2][$Pos2]==$reel[3][$Pos3]) {
                // 4 reel match
                $reels[] = 4;
                $Win += ($BetAmount * $payout[$reel[0][$Pos0]][1]);
        } else {
                // 3 reel match
                $reels[] = 3;
                $Win += ($BetAmount * $payout[$reel[0][$Pos0]][0]);
        }

    } elseif ($reel[0][$Pos0]=='A' && $reel[0][$Pos0]==$reel[1][$Pos1]) {
            // 2 reel A
            $reels[] = 2;
            $Win += ($BetAmount * 4);
    } elseif ($reel[0][$Pos0]=='A') {
            // 1 reel A
            $reels[] = 1;
            $Win += ($BetAmount * 2);
    } else {
            $reels[] = 0;
            //$Win += 0;
    }

    $Result .= "{$reel[0][$Pos0]}{$reel[1][$Pos1]}{$reel[2][$Pos2]}{$reel[3][$Pos3]}";
}
