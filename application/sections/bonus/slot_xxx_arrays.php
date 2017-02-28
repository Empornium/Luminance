<?php
/*  from spreadsheet:

     Reel1	Reel2	Reel3	Reel4

  f     1     1     1       1
  e     1     1     2       1
  d     3     3     2       2
  c     4	  3     3       2
  b     4	  4     4       4
  a     5	  3     4       4
  -X-   2	  5     4       6

        20    20	  20	    20

                                                Payout Table for 10 credit game
    3 Reel Odds	Payout	Return			1 	2 	3 	4 matches

  fff	0.000119	100	0.011875              F               	1,000	100,000
  eee	0.000238	50	0.011875              E               	500	10,000
  ddd	0.002025	35	0.070875              D               	350	1,000
  ccc	0.004050	20	0.081000              C               	200	500
  bbb	0.006400	10	0.064000              B               	100	250
  aaa	0.006000	6	0.036000              A       20	40	60	120
  aa	0.030000	4	0.120000			all wins left align only
  a   	0.212500	2	0.425000

  0.26133125	Total	0.820625

        4 Reel Odds	Payout	Return

  ffff	0.00000625	10000	0.06250000
  eeee	0.00001250	1000	0.01250000
  dddd	0.00022500	100	0.02250000
  cccc	0.00045000	50	0.02250000
  bbbb	0.00160000	25	0.04000000
  aaaa	0.00150000	12	0.01800000

  0.00379375	Total	0.178

  chance of hitting a winner each spin:	0.265125

  Expected Value		0.998625

  If Expected Value (EV) is > 1 then over time the bank loses money
  Casino EV is ~ 0.85 -> 0.98, higher min bets typically pay higher EV

 */

        //Payout and Reels for slot machine
$Payout = array();
$Payout['A'] = array(6, 12);
$Payout['B'] = array(10, 25);
$Payout['C'] = array(20, 50);
$Payout['D'] = array(35, 100);
$Payout['E'] = array(50, 1000);
$Payout['F'] = array(100, 10000);

$Reel = array();
$Reel[0] = array('E', 'C', 'A', 'A', 'D', 'A', 'F', 'D', 'B', 'X', 'C', 'B', 'B', 'C', 'C', 'A', 'D', 'B', 'A', 'X');
$Reel[1] = array('C', 'X', 'A', 'A', 'X', 'D', 'D', 'X', 'B', 'B', 'X', 'E', 'B', 'D', 'F', 'B', 'C', 'C', 'X', 'A');
$Reel[2] = array('B', 'X', 'F', 'C', 'B', 'C', 'E', 'C', 'X', 'B', 'B', 'D', 'X', 'A', 'A', 'X', 'A', 'A', 'E', 'D');
$Reel[3] = array('D', 'X', 'B', 'D', 'B', 'X', 'E', 'X', 'A', 'B', 'X', 'C', 'A', 'X', 'A', 'B', 'A', 'F', 'X', 'C');

// draw payout
function print_payout_table($BetAmount)
{
    global $Payout;
    ?>
    <div>
        <span class="payout"><?= number_format($BetAmount * 2) ?></span>
        <img src="<?= STATIC_SERVER ?>common/casino/iconA.png" />
    </div>
    <div>
        <span class="payout"><?= number_format($BetAmount * 4) ?></span>
        <img src="<?= STATIC_SERVER ?>common/casino/iconA.png" />
        <img src="<?= STATIC_SERVER ?>common/casino/iconA.png" />
    </div>
    <?php foreach ($Payout as $Pic => $P) { ?>
        <div>
            <span class="payout"><?= number_format($BetAmount * $P[0]) ?></span>
            <img src="<?= STATIC_SERVER ?>common/casino/icon<?= $Pic ?>.png" />
            <img src="<?= STATIC_SERVER ?>common/casino/icon<?= $Pic ?>.png" />
            <img src="<?= STATIC_SERVER ?>common/casino/icon<?= $Pic ?>.png" />
        </div>
    <?php }
    foreach ($Payout as $Pic => $P) { ?>
        <div>
            <span class="payout"><?= number_format($BetAmount * $P[1]) ?></span>
            <img src="<?= STATIC_SERVER ?>common/casino/icon<?= $Pic ?>.png" />
            <img src="<?= STATIC_SERVER ?>common/casino/icon<?= $Pic ?>.png" />
            <img src="<?= STATIC_SERVER ?>common/casino/icon<?= $Pic ?>.png" />
            <img src="<?= STATIC_SERVER ?>common/casino/icon<?= $Pic ?>.png" />
        </div>
    <?php
    }
}
