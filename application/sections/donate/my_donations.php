<?php
include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

if (!empty($_REQUEST['userid']) && is_numeric($_REQUEST['userid']))
    $UserID = $_REQUEST['userid'];
else
    $UserID = $LoggedUser['ID'];

$OwnProfile = $UserID == $LoggedUser['ID'];

if(!$OwnProfile && !check_perms('users_view_donor')) error(403);

$eur_rate = get_current_btc_rate();

$Title = "My Donations";

if (!$OwnProfile) {
    $UserInfo = user_info($UserID);
    $Title .= " " . format_username($UserID, $UserInfo['Username'], $UserInfo['Donor'], $UserInfo['Warned'], $UserInfo['Enabled'], $UserInfo['PermissionID'], false, true);
}

show_header('My Donations','bitcoin,bbcode');

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
            <input type="hidden" name="userid" value="<?=$UserID?>" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />

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
        $DB->query("SELECT ID, public, time FROM bitcoin_donations WHERE state='unused' AND userID='$UserID'");
        // existing 'open' (unused) addresses assigned to this user
        $user_addresses = $DB->to_array(false, MYSQL_NUM);

        if ($_REQUEST['new']=='1' && count($user_addresses)==0) {
            // only assign a new address if they dont already have one
            $DB->query("SELECT ID, public, userID FROM bitcoin_addresses ORDER BY ID LIMIT 1");
            if ($DB->record_count() < 1) {
                // no addresses!!
                $Err = "Failed to get an address, if this error persists we probably need to add some addresses, please contact an admin";
            } else {
                // got an unused address
                list($addID, $public, $staffID) = $DB->next_record();

                $DB->query("DELETE FROM bitcoin_addresses WHERE ID=$addID");
                if ($DB->affected_rows()==1) { // delete succeeded - we can issue this address
                    $time = sqltime();
                    $DB->query("INSERT INTO bitcoin_donations (public, time, userID, staffID)
                                                    VALUES ( '$public', '$time', '$UserID', '$staffID')");
                    $ID = $DB->inserted_id();
                    $user_addresses = array( array($ID, $public, $time) );
                } else {
                    // maybe another user grabbed it at the same time? try again...
                    $Err = "Address was already used! - please reload the page, if this error persists please contact an admin";
                }
            }
        }

        if ($Err) {
    ?>
            <p><span class="warning"><?=$Err?></span></p>
    <?php
        } elseif (count($user_addresses)==0) {

    ?>
            <p><a style="font-weight: bold;" href="donate.php?action=my_donations&new=1">click here to get a personal donation address</a></p>
    <?php
        } else {
    ?>
        This page queries the balance at the donation address in realtime.<br/>
        Once you have transferred some bitcoin to your donation address it can take a few hours for the transfer to be fully verified on the bitcoin network (6 transactions).<br/>
        When your transfer is verified you must return to this page and choose how to submit your donation (for -gb or <img src="<?= STATIC_SERVER ?>common/symbols/donor.png" alt="love" />)<br/><br/>
    <?php
        if ($eur_rate=='0') {   ?>
            <span class="red">The site was unable to get an exchange rate - you will not be able to submit a donation at this time</span><br/>
            Hopefully this is a temporary issue with the coindesk webservice, if it persists please
            <a href="/staffpm.php?action=user_inbox&show=1&msg=nobtcrate">message an admin.</a><br/><br/>
    <?php  } else { ?>
            <span style="font-size: 1.1em" title="rate is Mt.Gox weighted average: <?=$eur_rate?>">
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
                        <td><?=$Text->full_format("[font=Courier New][b]{$public}[/b][/font]", true)?></td><td><?=($activetime?time_diff($activetime):'')?></td>
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
                            if ($LoggedUser['ID']==$UserID || check_perms('admin_donor_log')) {
                                ?>
                                <form action="donate.php" method="post" <?php
                                    if ($LoggedUser['ID']!=$UserID)
                                    echo "onsubmit=\"return confirm('Are you sure you want to submit this users donation for them?\\n(usually this should be done by the user)');\" "; ?> >
                                    <input type="hidden" name="action" value="submit_donate" />
                                    <input type="hidden" name="donateid" value="<?=$ID?>" />
                                    <input type="hidden" name="userid" value="<?=$UserID?>" />
                                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
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
                            $DB->query("SELECT Title FROM badges WHERE Type='Donor' AND Cost<='".(int) round($amount_euro)."' ORDER BY Cost DESC LIMIT 1");
                            if ($DB->record_count()>0) {
                                list($title) = $DB->next_record();
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
        $DB->query("SELECT ID, state, public, time, userID, bitcoin_rate, received, amount_bitcoin, amount_euro, comment
                        FROM bitcoin_donations
                        WHERE state !='unused' AND userID='$UserID'
                    ORDER BY received DESC");

        $donation_records = $DB->to_array(false, MYSQL_NUM);

        if (count($donation_records)==0) {
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
                        <td title="Do not reuse old donation addresses. If you want to make a new donation please use a new address (issued above)"><?=$Text->full_format($addybbcode, true)?></td><td><?=$bitcoin_rate?></td><td><?=time_diff($received)?></td><td><?=$amount_bitcoin?></td><td>&euro;<?=$amount_euro?></td>
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
