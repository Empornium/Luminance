<?php
if (!check_perms('admin_donor_addresses')) { error(403); }

include(SERVER_ROOT.'/sections/donate/functions.php');
define('DONATIONS_PER_PAGE', 50);

show_header('Bitcoin addresses');
?>
<div class="thin">
    <h2>Bitcoin address pool</h2>

    <div class="linkbox">
        <a href="tools.php?action=btc_address_input">[Unused address pool]</a>
        <?php if (check_perms('admin_donor_log')) { ?>
        <a href="tools.php?action=donation_log&view=issued">[Issued addresses]</a>
        <a href="tools.php?action=donation_log&view=submitted">[Submitted donations]</a>
        <a href="tools.php?action=donation_log&view=cleared">[Cleared donations]</a>
        <?php  } ?>
    </div>

    <div class="head">Add more donation addresses</div>
    <div class="box pad">
        When a user goes to the donate page they can request an address to donate BTC to. Unused addresses are taken from this pool.<br/>
        Make sure you keep the pool well topped up - if it is empty then users will not be able to donate!<br/>
        <br/>
        Input addresses here: &nbsp; <em>separators can be newline, comma, or whitespace. addresses can be quoted (the quotes will be trimmed)</em><br/>
        <span style="color: red; font-weight:bold;">note: ONLY enter the public address. Keep the private key secret - you will need it to gain access to the donated BTC once users have transferred funds to these addresses.</span><br/><br/>
        <form action="tools.php" method="post">
            <input type="hidden" name="action" value="enter_addresses" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey'];?>" />
            <textarea id="input_addresses" name="input_addresses" class="medium" rows="15"></textarea>
            <br/>
            <input type="submit" value="Enter new addresses" />
        </form>
    </div>
<?php

    list($Page,$Limit) = page_limit(DONATIONS_PER_PAGE);

    $DB->query("SELECT SQL_CALC_FOUND_ROWS ba.ID, ba.public , ba.userID, m.Username, m.PermissionID, m.Enabled, i.Donor, i.Warned
                         FROM bitcoin_addresses  AS ba
                         LEFT JOIN users_main AS m ON m.ID=ba.UserID
                         LEFT JOIN users_info AS i ON i.UserID=ba.UserID
                         ORDER BY ba.ID ASC LIMIT $Limit ");

    $Addresses = $DB->to_array(false,MYSQLI_NUM);
    $DB->query("SELECT FOUND_ROWS()");
    list($Results) = $DB->next_record();

?>
    <div class="linkbox">
    <?php
        $Pages=get_pages($Page,$Results,DONATIONS_PER_PAGE,11) ;
        echo $Pages;
    ?>
    </div>

    <div class="head"><?=$Results?> Unused donation addresses</div>
    <div class="box pad">

        <form id="addressform" action="tools.php" method="post" onsubmit="return anyChecks('addressform')">
            <div class="donate_details">
                <input type="hidden" name="action" value="delete_addresses" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <table class="noborder">
                    <tr class="colhead">
                        <td><input type="checkbox" onclick="toggleChecks('addressform',this)" /></td>
                        <td>id</td>
                        <td>address</td>
                        <td>user</td>
                        <td></td>
                    </tr>
    <?php
        foreach ($Addresses as $Address) {
            list($ID, $public, $UserID, $Username, $PermissionID, $Enabled, $Donor, $Warned) = $Address;

                $row = $row=='b'?'a':'b';
    ?>
                    <tr class="row<?=$row?>">
                        <td>
                            <input type="checkbox" name="deleteids[]" value="<?=$ID?>" title="If checked this address will be selected for delete" />
                        </td>
                        <td><?=$ID?></td>
                        <td class="address"><?=$public?></td>
                        <td><?=format_username($UserID, $Username, $Donor, $Warned, $Enabled, $PermissionID)?></td>
                        <td><?=( validate_btc_address($public)?'':'<span class="red">invalid format!</span>');?> </td>
                    </tr>

    <?php 	} ?>
                </table>
            </div>
            <div>
                <input type="submit" value="Delete selected addresses" />
            </div>
        </form>
    </div>

    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php
show_footer();
