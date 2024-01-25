<?php
$bbCode = new \Luminance\Legacy\Text;

if (!empty($_REQUEST['userid']) && is_numeric($_REQUEST['userid'])) {
    $userID = $_REQUEST['userid'];
} else {
    $userID = $activeUser['ID'];
}

$OwnProfile = $userID == $activeUser['ID'];

if (!$OwnProfile && !check_perms('users_view_donor')) error(403);

$eur_rate = get_current_btc_rate();

$Title = "My Donations";

if (!$OwnProfile) {
    $UserInfo = user_info($userID);
    $Title .= " " . format_username($userID, $UserInfo['Donor'], true, $UserInfo['Enabled'], $UserInfo['PermissionID'], false, true);
}

show_header('My Donations', 'bitcoin,bbcode');

?>
<!-- Donate -->
<div class="thin">
    <h2><?=$Title?></h2>
<?php
    // manually enter a donation (for paypal etc)
    if (check_perms('users_give_donor')) {
?>
    <div class="head">Manually enter a donation</div>
    <div class="box pad">
        <form action="donate.php" method="post" >
            <input type="hidden" name="action" value="submit_donate_manual" />
            <input type="hidden" name="userid" value="<?=$userID?>" />
            <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />

            Amount: <strong style="font-size:19px;">&euro; </strong><input type="text" name="amount" value="" /> &nbsp; &nbsp; &nbsp;
            <input type="submit" name="donategb" value="donate for -GB" />
            <input type="submit" name="donatelove" value="donate for love" />
        </form>
    </div>
<?php
    }
?>
    <div class="head">Your unique donation address</div>
    <div class="box pad">
    <?php
        $Err=false;
        //usually any user will only have one 'open' address - but list them all just in case
        $user_addresses = $master->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS
                    ID,
                    public,
                    time
               FROM bitcoin_donations
              WHERE state = 'unused'
                AND userID = ?",
            [$userID]
        )->fetchAll(\PDO::FETCH_NUM);
        // existing 'open' (unused) addresses assigned to this user
        $user_address_count = $master->db->foundRows();


        if (($_REQUEST['new'] ?? '0') == '1' && $user_address_count === 0) {
            list($Err, $user_addresses) = get_btc_addresses($userID);
        }

        if ($Err) {
    ?>
            <p><span class="warning"><?=$Err?></span></p>
    <?php
        } elseif ($user_address_count === 0) {

    ?>
            <p><a style="font-weight: bold;" href="/donate.php?action=my_donations&new=1">click here to get a personal donation address</a></p>
    <?php
        } else {
    ?>
        This page queries the balance at the donation address in realtime.<br/>
        Once you have transferred some bitcoin to your donation address it can take a few hours for the transfer to be fully verified on the bitcoin network (6 confirmations).<br/>
        When your transfer is verified you must return to this page and choose how to submit your donation (for -gb or <img src="<?= STATIC_SERVER ?>common/symbols/donor.png" alt="love" />)<br/><br/>
    <?php
        if ($eur_rate=='0') {   ?>
            <span class="red">The site was unable to get an exchange rate - you will not be able to submit a donation at this time</span><br/>
            Hopefully this is a temporary issue with the coindesk webservice, if it persists please
            <a href="/staffpm.php?action=user_inbox&show=1&msg=nobtcrate">message an admin.</a><br/><br/>
    <?php  } else { ?>
            <span style="font-size: 1.1em" title="rate is the https://bitcoinaverage.com daily average: <?=$eur_rate?>">
                                The current bitcoin exchange rate is 1 bitcoin = &euro;<?=number_format($eur_rate,2);?></span><br/><br/>
    <?php  }
            foreach ($user_addresses as $address) {
                list($ID, $public, $time) = $address;
                $balance1 = check_bitcoin_balance($public, 1);
                if ($balance1>0) {
                    $amount_euro = number_format($balance1*$eur_rate,2);
                    $balance2 = check_bitcoin_balance($public, 6);
                    $activetime = check_bitcoin_activation($public);
                } else {
                    $amount_euro = 0;
                    $balance2 = 0;
                    $activetime = '';
                }
                $verified = $balance2>0 && $balance2==$balance1;
                $can_submit =  $verified && $eur_rate!=='0';
                $disabled_html = $can_submit ? '' : 'disabled="disabled" ';
    ?>
            <div class="donate_details<?=($can_submit?' green':'')?>">
                <table class="noborder">
                    <tr>
                        <td>address</td><td><?=($activetime?'time activated':'')?></td>
                        <td>bc amount deposited</td><td>&euro; <em>(estimated)</em></td>
                    </tr>
                    <tr>
                        <td><?=$bbCode->full_format("[font=Courier New][b]{$public}[/b][/font]", true)?></td><td><?=($activetime?time_diff($activetime):'')?></td>
                        <td><?=$balance1; if ($balance1>0) echo ($verified?' (verified)':' (pending)')?></td><td><?=$amount_euro?></td>
                    </tr>
                    <?php
                if ($balance1>0) {
                    ?>
                    <tr>
                        <td colspan="3" style="text-align:right;">
                        <?php echo "&euro;$amount_euro => -". get_size(get_donate_deduction($amount_euro) ,2); ?>
                        </td>
                        <td style="width:100px" rowspan="2">
                            <?php
                            if ($activeUser['ID']==$userID || check_perms('admin_donor_log')) {
                                ?>
                                <form action="donate.php" method="post" <?php
                                    if ($activeUser['ID']!=$userID)
                                    echo "onsubmit=\"return confirm('Are you sure you want to submit this users donation for them?\\n(usually this should be done by the user)');\" "; ?> >
                                    <input type="hidden" name="action" value="submit_donate" />
                                    <input type="hidden" name="donateid" value="<?=$ID?>" />
                                    <input type="hidden" name="userid" value="<?=$userID?>" />
                                    <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                                    <input type="submit" name="donategb" value="donate for -GB" <?=$disabled_html?>/><br/>
                                    <input type="submit" name="donatelove" value="donate for love" <?=$disabled_html?>/>
                                </form>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align:right;">
                        <?php
                            $title = $master->db->rawQuery(
                                "SELECT Title
                                   FROM badges
                                  WHERE Type = 'Donor'
                                    AND Cost <= ?
                               ORDER BY Cost DESC
                                  LIMIT 1",
                                [(int) round($amount_euro)]
                            )->fetchColumn();
                            if ($master->db->foundRows()>0) {
                                echo "&euro;$amount_euro => $title";
                            }
                        ?>  <img src="<?= STATIC_SERVER ?>common/symbols/donor.png" alt="Donor" />
                        </td>
                    </tr>
                    <?php
                }
                    ?>
                </table>
            </div>
    <?php
            }
        }
    ?>

    </div>

    <div class="head">Donation history</div>
    <div class="box pad">

    <?php
        $donation_records = $master->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS
                    ID,
                    state,
                    public,
                    time,
                    userID,
                    bitcoin_rate,
                    received,
                    amount_bitcoin,
                    amount_euro,
                    comment
               FROM bitcoin_donations
              WHERE state != 'unused'
                AND userID = ?
           ORDER BY received DESC",
            [$userID]
        )->fetchAll(\PDO::FETCH_NUM);
        $donation_record_count = $master->db->foundRows();

        if ($donation_record_count == 0) {
    ?>
            <p><span style="font-size:1.0em;font-weight: bold;">no records found</span></p>
    <?php
        } else {

            foreach ($donation_records as $record) {
                list($ID, $state, $public, $time, $userID, $bitcoin_rate, $received, $amount_bitcoin, $amount_euro, $comment) = $record;
                $addybbcode = "[font=Courier New]" .substr_replace($public, "#######################", -23)."[/font]" .
                        "[spoiler= ]Do not use this address again, if you want to make another donation please request a new address above[br]your old address: [font=Courier New]{$public}[/font][/spoiler]";
    ?>
            <div class="donate_details green">
                <table class="noborder">
                    <tr>
                        <td>address</td><td>bc rate</td><td>date</td><td>bc amount</td><td></td>
                    </tr>
                    <tr>
                        <td title="Do not reuse old donation addresses. If you want to make a new donation please use a new address (issued above)"><?=$bbCode->full_format($addybbcode, true)?></td><td><?=$bitcoin_rate?></td><td><?=time_diff($received)?></td><td><?=$amount_bitcoin?></td><td>&euro;<?=$amount_euro?></td>
                    </tr>
                    <tr>
                        <td colspan="4"><?=$comment?></td><td colspan="1"><?php if (check_perms('admin_donor_log'))echo "<em>$state</em>";?></td>
                    </tr>
                </table>
            </div>
    <?php
            }
        }
    ?>
    </div>
</div>

<!-- END Donate -->
<?php
show_footer();
