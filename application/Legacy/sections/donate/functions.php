<?php

function get_donate_deduction($amount_euros) {
    global $donateLevels;
    $deduct_bytes = 0;
    $donateLevelsR = array_reverse($donateLevels, true);
    foreach ($donateLevelsR as $level=>$rate) {
        if ($amount_euros >= $level) {
            $deduct_bytes = floor($amount_euros) * $rate * 1024 * 1024 * 1024; // rate per gb
            break;
        }
    }

    return $deduct_bytes;
}

function get_donate_credits($amount_euros) {
    global $donateLevels;
    $add_credits = 0;
    $donateLevelsR = array_reverse($donateLevels, true);
    foreach ($donateLevelsR as $level=>$rate) {
        if ($amount_euros >= $level) {
            $add_credits = floor($amount_euros) * $rate * 1000; // rate per gb
            break;
        }
    }

    return $add_credits;
}

function print_btc_query_now($ID, $eur_rate, $address) {
?>
    <span style="font-style: italic;" id="btc_button_<?=$ID?>">waiting...
        <script type="text/javascript">
            setTimeout("CheckAddress('<?=$ID?>', '<?=$eur_rate?>', '<?=$address?>', '6')", <?=(int) ($ID*800)?>);
        </script>
    </span>
<?php
}

function print_btc_query_button($eur_rate, $address, $numConfirmations=6) {
    static $bID = 0;
    ++$bID;
?>
    <span style="font-style: italic;" id="btc_button_<?=$bID?>">
        <a href="#" onclick="CheckAddress('<?=$bID?>', '<?=$eur_rate?>', '<?=$address?>', '<?=$numConfirmations?>');return false;">query balance</a>
    </span>
<?php

    return $bID;
}

function validate_btc_address($address) {
    // just do a cursory check on format
    //  /^[a-zA-Z1-9]{27,35}$/
    //  // starts with a 1 or a 3
    //uppercase letter "O", uppercase letter "I",
    //lowercase letter "l", and the number "0" are never used to prevent visual ambiguity.
    if (preg_match(BTC_ADDRESS_REGEX , $address)) {
        // could/should do a hash check here to validate the internal checksum but.... meh.
        return true;
    } else {
        return false;
    }
}

function get_btc_addresses($userID) {
    global $master;
    $Err = null;

    // only assign a new address if they don't already have one
    $nextRecord = $master->db->rawQuery(
        "SELECT ID,
                public,
                userID
           FROM bitcoin_addresses
       ORDER BY ID
          LIMIT 1"
    )->fetch(\PDO::FETCH_NUM);
    if ($master->db->foundRows() < 1) {
        // no addresses, generate one if we can.
        if ($master->settings->bitcoin->auto_generate) {
            try {
                $bitcoin  = new Luminance\Legacy\Bitcoin(
                    $master->settings->bitcoin->username,
                    $master->settings->bitcoin->password,
                    $master->settings->bitcoin->host,
                    $master->settings->bitcoin->port,
                );
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
        list($addID, $public, $staffID) = $nextRecord;

        $issuedAddress = $master->db->rawQuery(
            "DELETE
               FROM bitcoin_addresses
              WHERE ID = ?",
            [$addID]
        );
        if ($issuedAddress->rowCount() != 1) {
            // maybe another user grabbed it at the same time? try again...
            $Err = "Address was already used! - please reload the page, if this error persists please contact an admin";
        } else {
            $AddressesLeft = $master->db->rawQuery(
                "SELECT COUNT(ID)
                   FROM bitcoin_addresses"
            )->fetchColumn();
            if ($AddressesLeft == 20) {
                send_staff_pm("Donation pool low", "The donation pool is low on addresses, you need to top it up.", LEVEL_SYSOP);
            }
        }
    }

    if (empty($Err)) {
        $sqltime = sqltime();
        $master->db->rawQuery(
            "INSERT INTO bitcoin_donations (public, time, userID, staffID)
                  VALUES (?, ?, ?, ?)",
            [$public, $sqltime, $userID, $staffID]
        );
        $ID = $master->db->lastInsertID();
        $user_addresses = [[$ID, $public, $sqltime]];
    }

    return [$Err, $user_addresses];
}

function check_bitcoin_balance($address, $numConfirmations = 6) {
    global $master;
    if ($master->settings->bitcoin->auto_generate) {
        $bitcoin  = new Luminance\Legacy\Bitcoin(
            $master->settings->bitcoin->username,
            $master->settings->bitcoin->password,
            $master->settings->bitcoin->host,
            $master->settings->bitcoin->port,
        );
        $btc = $bitcoin->getreceivedbyaddress($address, $numConfirmations);
    } else {
        $satoshis = intval(file_get_contents("https://blockchain.info/q/addressbalance/{$address}?confirmations={$numConfirmations}"));
        $btc = $satoshis / 100000000.0;
    }
    if ($btc > 0) {
        return sprintf('%.8F', $btc);
    } else {
        return '0';
    }
}

function check_bitcoin_activation($address) {
    $timestamp = intval(file_get_contents("https://blockchain.info/q/addressfirstseen/$address"));
    if ($timestamp > 0) {
        return date('Y-m-d H:i:s', $timestamp);
    } else {
        return "Never seen";
    }
}

// https://api.bitcoincharts.com/v1/weighted_prices.json
// https://www.bitstamp.net/api/ticker/

function get_current_btc_rate() {
    global $master;

    $rate = $master->cache->getValue('eur_bitcoin');
    if ($rate===false) {
        $rate = '0';
        $rate = query_eur_rate();
        if (!$rate) {
            $master->cache->cacheValue('eur_bitcoin', 0, 60); // one minute
        } else {
            $master->cache->cacheValue('eur_bitcoin', $rate, 3600); // one hour
        }
    }

    return $rate;
}

function query_eur_rate($testing=false) {
    // return get_eur_bitstamp($testing);
    return get_eur_coindesk($testing);
    // return get_eur_bitcoinaverage($testing);
}

function get_eur_bitstamp($testing=false) {
    $bitstampjson = file_get_contents("https://www.bitstamp.net/api/v2/ticker/btceur/");

    if ($testing) return $bitstampjson;

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

function get_eur_coindesk($testing=false) {
    $coindeskjson = file_get_contents("https://api.coindesk.com/v1/bpi/currentprice/EUR.json");

    if ($testing) return $coindeskjson;

    // Decode from an object to array
    if ($coindeskjson) {

        $output_coindesk = json_decode($coindeskjson, true);

        // something's wrong
        if (!$output_coindesk OR !isset($output_coindesk['bpi'])) {
            return false;
        }

        $currencyRate = $output_coindesk['bpi']['EUR']['rate'];

        return (double)str_replace(',', '', $currencyRate);
    }

    return false;
}

function get_eur_bitcoinaverage($testing=false) {
        $currencyRate = json_decode(file_get_contents("https://apiv2.bitcoinaverage.com/indices/global/ticker/BTCEUR"))->averages->day;

        if ($testing || $currencyRate) return (double)str_replace(',', '', $currencyRate);

        return false;
}
