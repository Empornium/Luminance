<?php

function get_donate_deduction($amount_euros)
{
    global $DonateLevels;
    $deduct_bytes = 0;
    $DonateLevelsR = array_reverse($DonateLevels, true);
    foreach ($DonateLevelsR as $level => $rate) {
        if ($amount_euros >= $level) {
            $deduct_bytes = floor($amount_euros) * $rate * 1024 * 1024 * 1024; // rate per gb
            break;
        }
    }

    return $deduct_bytes;
}

function get_donate_credits($amount_euros)
{
    global $DonateLevels;
    $add_credits = 0;
    $DonateLevelsR = array_reverse($DonateLevels, true);
    foreach ($DonateLevelsR as $level => $rate) {
        if ($amount_euros >= $level) {
            $add_credits = floor($amount_euros) * $rate * 1000; // rate per gb
            break;
        }
    }

    return $add_credits;
}

function print_btc_query_now($ID, $eur_rate, $address)
{
?>
    <span style="font-style: italic;" id="btc_button_<?=$ID?>">waiting...
        <script type="text/javascript">
            setTimeout("CheckAddress('<?=$ID?>','<?=$eur_rate?>','<?=$address?>','6')", <?=(int) ($ID*800)?>);
        </script>
    </span>
<?php
}

function print_btc_query_button($eur_rate, $address, $numtransactions = 6)
{
    static $bID = 0;
    ++$bID;
?>
    <span style="font-style: italic;" id="btc_button_<?=$bID?>">
        <a href="#" onclick="CheckAddress('<?=$bID?>','<?=$eur_rate?>','<?=$address?>','<?=$numtransactions?>');return false;">query balance</a>
    </span>
<?php

    return $bID;
}

function validate_btc_address($address)
{
    // just do a cursory check on format
    //  /^[a-zA-Z1-9]{27,35}$/
    //  // starts with a 1 or a 3
    //uppercase letter "O", uppercase letter "I",
    //lowercase letter "l", and the number "0" are never used to prevent visual ambiguity.
    if (preg_match(BTC_ADDRESS_REGEX, $address)) {
        // could/should do a hash check here to validate the internal checksum but.... meh.
        return true;
    } else {
        return false;
    }
}

function get_btc_addresses($UserID)
{
    global $DB;

    // only assign a new address if they dont already have one
    $DB->query("SELECT ID, public, userID FROM bitcoin_addresses ORDER BY ID LIMIT 1");
    if ($DB->record_count() < 1) {
        // no addresses, generate on if we can.
        if (BTC_LOCAL) {
            try {
                $bitcoin  = new Luminance\Legacy\Bitcoin(BTC_USER, BTC_PASS, BTC_HOST, BTC_PORT);
                $public   = $bitcoin->getnewaddress();
                if (!$public) {
                    $Err = "Failed to get an address, if this error persists we probably need to add some addresses, please contact an admin";
                }
            } catch (Exception $e) {
                $Err = "Failed to get an address, if this error persists we probably need to add some addresses, please contact an admin";
            }
            $staffID  = 0;
        } else {
            $Err = "Failed to get an address, if this error persists we probably need to add some addresses, please contact an admin";
        }
    } else {
        // got an unused address
        list($addID, $public, $staffID) = $DB->next_record();

        $DB->query("DELETE FROM bitcoin_addresses WHERE ID=$addID");
        if ($DB->affected_rows()!=1) { // delete succeeded - we can issue this address
            // maybe another user grabbed it at the same time? try again...
            $Err = "Address was already used! - please reload the page, if this error persists please contact an admin";
        } else {
            $DB->query("SELECT COUNT(ID) FROM bitcoin_addresses");
            list($AddressesLeft) = $DB->next_record();
            if ($AddressesLeft == 20) {
                send_staff_pm(db_string("Donation pool low"), db_string("The donation pool is low on addresses, you need to top it up."), LEVEL_SYSOP);
            }
        }
    }

    if (empty($Err)) {
        $time = sqltime();
        $DB->query("INSERT INTO bitcoin_donations (public, time, userID, staffID)
                                        VALUES ( '$public', '$time', '$UserID', '$staffID')");
        $ID = $DB->inserted_id();
        $user_addresses = array( array($ID, $public, $time) );
    }

    return array($Err, $user_addresses);
}

function check_bitcoin_balance($address, $numtransactions = 6)
{
    if (BTC_LOCAL) {
        $bitcoin  = new Luminance\Legacy\Bitcoin(BTC_USER, BTC_PASS);
        $btc = $bitcoin->getreceivedbyaddress($address, $numtransactions);
    } else {
        $satoshis = intval(file_get_contents("https://blockchain.info/q/addressbalance/{$address}?confirmations={$numtransactions}"));
        $btc = $satoshis / 100000000.0;
    }
    if ($btc > 0) {
        return sprintf('%.8F', $btc);
    } else {
        return '0';
    }
}

function check_bitcoin_activation($address)
{
    $timestamp = intval(file_get_contents("https://blockchain.info/q/addressfirstseen/$address"));
    if ($timestamp > 0) {
        return date('Y-m-d H:i:s', $timestamp);
    } else {
        return "Never seen";
    }
}

// https://api.bitcoincharts.com/v1/weighted_prices.json
// https://www.bitstamp.net/api/ticker/

function get_current_btc_rate()
{
    global $DB, $Cache;

    $rate = $Cache->get_value('eur_bitcoin');
    if ($rate===false) {
        $rate = '0';
        $rate = query_eur_rate();
        if (!$rate) {
            $Cache->cache_value('eur_bitcoin', 0, 60); // one minute
        } else {
            $Cache->cache_value('eur_bitcoin', $rate, 3600); // one hour
        }
    }

    return $rate;
}

function query_eur_rate($testing = false)
{
    return get_eur_bitcoinaverage($testing);
}

function get_eur_bitstamp($testing = false)
{
        $bitstampjson = file_get_contents("https://www.bitstamp.net/api/ticker/");

    if ($testing) {
        return $bitstampjson;
    }

        // Decode from an object to array
    if ($bitstampjson) {
        $output_bitstamp = json_decode($bitstampjson, true);
        // something's wrong
        if (!$output_bitstamp) {
            return false;
        }

        $currencyRate = $output_bitstamp['low'];

        return (double)str_replace(',', '', $currencyRate);
    }

        return false;
}

function get_eur_coindesk($testing = flase)
{
        $coindeskjson = file_get_contents("https://api.coindesk.com/v1/bpi/currentprice/EUR.json");

    if ($testing) {
        return $coindeskjson;
    }

        // Decode from an object to array
    if ($coindeskjson) {
        $output_coindesk = json_decode($coindeskjson, true);

        // something's wrong
        if (!$output_coindesk or !isset($output_coindesk['bpi'])) {
            return false;
        }

        $currencyRate = $output_coindesk['bpi']['EUR']['rate'];

        return (double)str_replace(',', '', $currencyRate);
    }

        return false;
}

function get_eur_bitcoinaverage($testing = false)
{
        $currencyRate = json_decode(file_get_contents("https://apiv2.bitcoinaverage.com/indices/global/ticker/BTCEUR"))->averages->day;

    if ($testing || $currencyRate) {
        return (double)str_replace(',', '', $currencyRate);
    }

        return false;
}
