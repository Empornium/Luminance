<?php
authorize();
if (!check_perms('admin_donor_addresses'))  error(403);

include(SERVER_ROOT . '/sections/donate/functions.php');

// split on whitespace and commas and nl
$input_addresses = trim($_REQUEST['input_addresses']);
$input_addresses = str_replace(array("\n", "  ", " ", ","), "|", $input_addresses);
$input_addresses = explode("|", $input_addresses);
$sql_addresses = array();
$sql_values = array();
$invalid_addresses = array();

foreach ($input_addresses as $Key => &$address) {
    // just do a cursory check on format
    $address = trim($address);
    $address = trim($address, "'\"");
    if (!$address) {
        unset($input_addresses[$Key]);
    } elseif (validate_btc_address($address)) {
        $sql_addresses[] = db_string($address);
        $sql_values[] = "('". db_string($address)."','$LoggedUser[ID]')";
    } else {  // not in a valid format
        $invalid_addresses[] = $address;
    }
}

if (count($invalid_addresses)==0) {

    $DB->query("SELECT ID FROM bitcoin_addresses WHERE public IN ('"  . implode("','", $sql_addresses)."')");
    $dupes=$DB->record_count();
    if($dupes>0) error("There are $dupes address collisions! Addresses must be unique!");

    $DB->query("INSERT INTO bitcoin_addresses (public, userID) VALUES " . implode(',', $sql_values));

    header("Location: tools.php?action=btc_address_input");
} else {

    show_header("Invalid addresses");
?>
<div class="thin">
    <h2>Invalid addresses</h2>

    <div class="head"></div>
    <div class="box pad">
        Addresses in red have not passed validation!<br/>
        The regex used to validate addresses is: <?=BTC_ADDRESS_REGEX?>   &nbsp; (This can be changed in the config file)<br/>
        <br/>
        <div class="donate_details">
<?php
        foreach ($input_addresses as $baddress) {
            if (in_array($baddress, $invalid_addresses)) {
                echo "<span style=\"color:red\">$baddress</span><br/>";
            } else {
                echo "$baddress<br/>";
            }
        }
?>
        </div>
    </div>

</div>
<?php
    show_footer();
}
