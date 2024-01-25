<?php
$Campaign = 'forumaudio';
if (!$Votes = $master->cache->getValue('support_'.$Campaign)) {
    $Votes = [0, 0];
}
if (!isset($_GET['support'])) {
?>
<h1>Browser Support Campaign: <?=$Campaign?></h1>
<ul>
    <li><?=number_format($Votes[0])?> +</li>
    <li><?=number_format($Votes[1])?> -</li>
    <li><?=number_format(($Votes[0]/($Votes[0]+$Votes[1]))*100,3)?> %</li>
</ul>
<?php
} elseif ($_GET['support'] === 'true') {
    $Votes[0]++;
} elseif ($_GET['support'] === 'false') {
    $Votes[1]++;
}
$master->cache->cacheValue('support_'.$Campaign, $Votes, 0);
