<?php
error("dont press that!");

set_time_limit(0);

$start = isset($_REQUEST['start'])?(int) $_REQUEST['start']:0;

$range = 100000;

$DB->query("CREATE TABLE IF NOT EXISTS `geoip_country_condensed` (
              `StartIP` int(11) unsigned NOT NULL,
              `EndIP` int(11) unsigned NOT NULL,
              `Code` varchar(2) NOT NULL,
              PRIMARY KEY (`StartIP`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

$DB->query("SELECT Max(StartIP) FROM geoip_country_condensed");
list($laststartip) = $DB->next_record();
if(!$laststartip) $laststartip ="0";

$DB->query("SELECT StartIP, EndIP, Code
              FROM geoip_country
             WHERE StartIP>'$laststartip'
          ORDER BY StartIP
             LIMIT $range ");

$rawnum = $DB->record_count();

$Values= array();
$startrange='';
$endrange='';
$lastcode='';
while (list($startip, $endip, $code) = $DB->next_record()) {

    if ($code != $lastcode) {
        if ($lastcode != '') {
            if (!$endrange)$endrange='0';
            $Values[] = "( '$startrange', '$endrange', '$lastcode' )";
        }
        $startrange = $startip;
        $endrange = $endip;
        $lastcode = $code ;

    } else {

        $endrange = $endip;
    }
}

if ($code == $lastcode) {
    if ($lastcode != '') {
        if (!$endrange)$endrange='0';
        $Values[] = "( '$startrange', '$endrange', '$lastcode' )";
    }
}

$DB->query( "INSERT IGNORE INTO geoip_country_condensed VALUES ".implode(',', $Values));

$num = count($Values);

$DB->query("SELECT Count(*) FROM geoip_country_condensed");
list($total) = $DB->next_record();

show_header('sandbox2');

?>

<div class="thin">
    <h2>sandbox two</h2>
    <div class="box pad shadow">
        <p id="start">Start: <?=$start?></p>
        <p >Last Start IP: <?=$laststartip?></p>
        <p>Condensed <?=$rawnum?> --> <?=$num?>  &nbsp (<?="$start->".($start+$range)?>)</p>
        <p >Total: <?=$total?></p> <br/>
        <a href="?action=sandbox2&start=<?=$start+$range?>">Process next <?=number_format($range)?> records after startip=<?=$laststartip?></a>
    </div>

    <div class="box pad shadow" id="results">
         <?=implode(',<br/>', $Values)?>
    </div>
</div>
<?php
show_footer();
