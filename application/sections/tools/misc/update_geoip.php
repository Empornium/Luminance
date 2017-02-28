<?php
ini_set('memory_limit', '5G');
set_time_limit(0);

error("I have disabled this page as automatic geoip processing kills the server - geoip can be manually updated... look at the code in sandbox's 2 and 4");

function get_first_day_current_month($dayofweek = 1)
{
    $current = date('m,Y');
    $current = explode(',', $current);

    return get_first_day($current[0],$current[1],$dayofweek);
}

function get_first_day($month,$year,$dayofweek = 1,$timeformat="Ymd") { // sunday =0

    $dayofweek = $dayofweek % 7; // in case of stupid input

    $num = date("w",mktime(0,0,0,$month,1,$year));

    if($num==$dayofweek)

        return date($timeformat,mktime(0,0,0,$month,1,$year));
    elseif($num>=0)
        return date($timeformat,mktime(0,0,0,$month,1,$year)+(86400*( ($dayofweek+7-$num) % 7 ) ));
    else
      return date($timeformat,mktime(0,0,0,$month,1,$year)+(86400*(1-$num)));

}

// currently updated on the first tuesday of each month. See http://www.maxmind.com/app/geolite for details
$filedate = get_first_day_current_month(2);

if (($Locations = file("GeoLiteCity_".$filedate."/GeoLiteCity-Location.csv", FILE_IGNORE_NEW_LINES)) === false) {
    error("Download or extraction of maxmind database failed<br/>DB: GeoLiteCity_CSV/GeoLiteCity_$filedate.zip");
}

show_header();

array_shift($Locations);
array_shift($Locations);

echo "There are ".count($Locations)." locations";
echo "<br />";

$CountryIDs = array();
foreach ($Locations as $Location) {
    $Parts = explode(",", $Location);
    //CountryIDs[1] = "AP";
    $CountryIDs[trim($Parts[0], '"')] = trim($Parts[1], '"');
}

echo "There are ".count($CountryIDs)." CountryIDs";
echo "<br />";

if (($Blocks = file("GeoLiteCity_".$filedate."/GeoLiteCity-Blocks.csv", FILE_IGNORE_NEW_LINES)) === false) {
    echo "Error";
}
array_shift($Blocks);
array_shift($Blocks);

echo "There are ".count($Blocks)." blocks";
echo "<br />";

//Because 4,000,000 rows is a lot for any server to handle, we split it into manageable groups of 10,000
$SplitOn = 10000;
$DB->query("TRUNCATE TABLE geoip_country");

$Values = array();
foreach ($Blocks as $Index => $Block) {
    list($StartIP, $EndIP, $CountryID) = explode(",", $Block);
    $StartIP = trim($StartIP, '"');
    $EndIP = trim($EndIP, '"');
    $CountryID = trim($CountryID, '"');
    $Values[] = "('".$StartIP."', '".$EndIP."', '".$CountryIDs[$CountryID]."')";
    if ($Index % $SplitOn == 0) {
        $DB->query("INSERT INTO geoip_country (StartIP, EndIP, Code) VALUES ".implode(", ", $Values));
        $Values = array();
    }
}

if (count($Values) > 0) {
    $DB->query("INSERT INTO geoip_country (StartIP, EndIP, Code) VALUES ".implode(", ", $Values));
}

$DB->query("INSERT INTO users_geodistribution (Code, Users)
                       SELECT g.Code, COUNT(u.ID) AS Users
                         FROM geoip_country AS g JOIN users_main AS u ON INET_ATON(u.IP) BETWEEN g.StartIP AND g.EndIP
                        WHERE u.Enabled='1'
                        GROUP BY g.Code
                     ORDER BY Users DESC");

$DB->query("INSERT INTO users_geodistribution (Code, Users)
                       SELECT g.Code, COUNT(u.ID) AS Users
                         FROM geoip_country AS g JOIN users_main AS u ON u.ip_number BETWEEN g.StartIP AND g.EndIP
                        WHERE u.Enabled='1'
                        AND u.ip_number >'0'
                        GROUP BY g.Code
                     ORDER BY Users DESC");

show_footer();

/*
    The following way works perfectly fine, we just foung the APNIC data to be to outdated for us.
*/

/*
if (!check_perms('admin_update_geoip')) { die(); }
enforce_login();

ini_set('memory_limit',1024*1024*1024);
ini_set('max_execution_time', 3600);

header('Content-type: text/plain');
ob_end_clean();
restore_error_handler();

$Registries[] = 'http://ftp.apnic.net/stats/afrinic/delegated-afrinic-latest'; //Africa
$Registries[] = 'http://ftp.apnic.net/stats/apnic/delegated-apnic-latest'; //Asia & Pacific
$Registries[] = 'http://ftp.apnic.net/stats/arin/delegated-arin-latest'; //North America
$Registries[] = 'http://ftp.apnic.net/stats/lacnic/delegated-lacnic-latest'; //South America
$Registries[] = 'http://ftp.apnic.net/stats/ripe-ncc/delegated-ripencc-latest'; //Europe

$Registries[] = 'ftp://ftp.afrinic.net/pub/stats/afrinic/delegated-afrinic-latest'; //Africa
$Registries[] = 'ftp://ftp.apnic.net/pub/stats/apnic/delegated-apnic-latest'; //Asia & Pacific
$Registries[] = 'ftp://ftp.arin.net/pub/stats/arin/delegated-arin-latest'; //North America
$Registries[] = 'ftp://ftp.lacnic.net/pub/stats/lacnic/delegated-lacnic-latest'; //South America
$Registries[] = 'ftp://ftp.ripe.net/ripe/stats/delegated-ripencc-latest'; //Europe

$Query = array();

foreach ($Registries as $Registry) {
    $CountryData = explode("\n",file_get_contents($Registry));
    foreach ($CountryData as $Country) {
        if (preg_match('/\|([A-Z]{2})\|ipv4\|(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\|(\d+)\|/', $Country, $Matches)) {

            $Start = ip2unsigned($Matches[2]);
            if ($Start == 2147483647) { continue; }

            if (!isset($Current)) {
                $Current = array('StartIP' => $Start, 'EndIP' => $Start + $Matches[3],'Code' => $Matches[1]);
            } elseif ($Current['Code'] == $Matches[1] && $Current['EndIP'] == $Start) {
                $Current['EndIP'] = $Current['EndIP'] + $Matches[3];
            } else {
                $Query[] = "('".$Current['StartIP']."','".$Current['EndIP']."','".$Current['Code']."')";
                $Current = array('StartIP' => $Start, 'EndIP' => $Start + $Matches[3],'Code' => $Matches[1]);
            }
        }
    }
}
$Query[] = "('".$Current['StartIP']."','".$Current['EndIP']."','".$Current['Code']."')";

$DB->query("TRUNCATE TABLE geoip_country");
$DB->query("INSERT INTO geoip_country (StartIP, EndIP, Code) VALUES ".implode(',', $Query));
echo $DB->affected_rows();
*/
