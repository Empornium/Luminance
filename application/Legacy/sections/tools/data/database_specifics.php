<?php
if (!check_perms('site_debug')) { error(403); }

//View schemas
if (!empty($_GET['table'])) {
    $Tables = $master->db->rawQuery("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
    if (!in_array($_GET['table'], $Tables)) {
        error(0);
    }
    list(, $Schema) = $master->db->rawQuery("SHOW CREATE TABLE {$_GET['table']}")->fetch(\PDO::FETCH_NUM);

    if (!empty($_GET['json'])) {
        header('Content-type: application/json');
        $parser = new PhpMyAdmin\SqlParser\Parser($Schema);
        $Schema = json_encode($parser->statements[0]);
    } else {
        header('Content-type: text/plain');
    }
    die($Schema);
}

//Cache the tables for 4 hours, makes sorting faster
if (!$Tables = $master->cache->getValue('database_table_stats')) {
    $Tables = $master->db->rawQuery("SHOW TABLE STATUS")->fetchAll();
    $master->cache->cacheValue('database_table_stats', $Tables, 3600 * 4);
}

$Pie = $master->plotly->newPieChart();

//Begin sorting
$Sort = [];
switch (empty($_GET['order_by'])?'':$_GET['order_by']) {
    case 'name':
        foreach ($Tables as $Key => $Value) {
            $Pie->add($Value[0], $Value[6] + $Value[8]);
            $Sort[$Key] = $Value[0];
        }
        break;
    case 'engine':
        foreach ($Tables as $Key => $Value) {
            $Pie->add($Value[0], $Value[6] + $Value[8]);
            $Sort[$Key] = $Value[1];
        }
        break;
    case 'rows':
        foreach ($Tables as $Key => $Value) {
            $Pie->add($Value[0], $Value[4]);
            $Sort[$Key] = $Value[4];
        }
        break;
    case 'rowsize':
        foreach ($Tables as $Key => $Value) {
            $Pie->add($Value[0], $Value[5]);
            $Sort[$Key] = $Value[5];
        }
        break;
    case 'datasize':
        foreach ($Tables as $Key => $Value) {
            $Pie->add($Value[0], $Value[6]);
            $Sort[$Key] = $Value[6];
        }
        break;
    case 'indexsize':
        foreach ($Tables as $Key => $Value) {
            $Pie->add($Value[0], $Value[8]);
            $Sort[$Key] = $Value[8];
        }
        break;
    case 'totalsize':
    default:
        foreach ($Tables as $Key => $Value) {
            $Pie->add($Value[0], $Value[6] + $Value[8]);
            $Sort[$Key] = $Value[6] + $Value[8];
        }
}
$Pie->color('FF9900');
$pieData = $Pie->generate();

if (!empty ($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $SortWay = SORT_ASC;
} else {
    $SortWay = SORT_DESC;
}

array_multisort($Sort, $SortWay, $Tables);
//End sorting

show_header('Database Specifics', 'plotly,charts,jquery');
?>
<h3>Breakdown</h3>
<div class="box pad center">
    <div id="chart_div"></div>
    <script type="text/javascript">
        document.addEventListener('LuminanceLoaded', function() {
            drawChart('chart_div', <?=json_encode($pieData)?>);
        });
    </script>
</div>
<br />
<table>
    <tr class="colhead">
        <td><a href="/tools.php?action=database_specifics&amp;order_by=name&amp;order_way=<?=(!empty($_GET['order_by']) && $_GET['order_by'] == 'name' && !empty($_GET['order_way']) && $_GET['order_way'] == 'desc')?'asc':'desc'?>">Name</a></td>
        <td><a href="/tools.php?action=database_specifics&amp;order_by=engine&amp;order_way=<?=(!empty($_GET['order_by']) && $_GET['order_by'] == 'engine' && !empty($_GET['order_way']) && $_GET['order_way'] == 'desc')?'asc':'desc'?>">Engine</a></td>
        <td><a href="/tools.php?action=database_specifics&amp;order_by=rows&amp;order_way=<?=(!empty($_GET['order_by']) && $_GET['order_by'] == 'rows' && !empty($_GET['order_way']) && $_GET['order_way'] == 'desc')?'asc':'desc'?>">Rows</td>
        <td><a href="/tools.php?action=database_specifics&amp;order_by=rowsize&amp;order_way=<?=(!empty($_GET['order_by']) && $_GET['order_by'] == 'rowsize' && !empty($_GET['order_way']) && $_GET['order_way'] == 'desc')?'asc':'desc'?>">Row Size</a></td>
        <td><a href="/tools.php?action=database_specifics&amp;order_by=datasize&amp;order_way=<?=(!empty($_GET['order_by']) && $_GET['order_by'] == 'datasize' && !empty($_GET['order_way']) && $_GET['order_way'] == 'desc')?'asc':'desc'?>">Data Size</a></td>
        <td><a href="/tools.php?action=database_specifics&amp;order_by=indexsize&amp;order_way=<?=(!empty($_GET['order_by']) && $_GET['order_by'] == 'indexsize' && !empty($_GET['order_way']) && $_GET['order_way'] == 'desc')?'asc':'desc'?>">Index Size</a></td>
        <td><a href="/tools.php?action=database_specifics&amp;order_by=totalsize&amp;order_way=<?=(!empty($_GET['order_by']) && $_GET['order_by'] == 'totalsize' && !empty($_GET['order_way']) && $_GET['order_way'] == 'desc')?'asc':'desc'?>">Total Size</td>
        <td>Tools</td>
    </tr>
<?php
$TotalRows = 0;
$TotalDataSize = 0;
$TotalIndexSize = 0;
$Row = 'a';
foreach ($Tables as $Table) {
    list($Name, $Engine, , , $Rows, $RowSize, $DataSize,, $IndexSize) = $Table;
    $Row = ($Row == 'a') ? 'b' : 'a';

    $TotalRows += $Rows;
    $TotalDataSize += $DataSize;
    $TotalIndexSize += $IndexSize;
?>
    <tr class="row<?=$Row?>">
        <td><?=display_str($Name)?></td>
        <td><?=display_str($Engine)?></td>
        <td><?=number_format($Rows)?></td>
        <td><?=get_size($RowSize)?></td>
        <td><?=get_size($DataSize)?></td>
        <td><?=get_size($IndexSize)?></td>
        <td><?=get_size($DataSize + $IndexSize)?></td>
        <td>[<a href="/tools.php?action=database_specifics&amp;table=<?=display_str($Name)?>">Schema</a>]</td>
    </tr>
<?php
}
?>
    <tr>
        <td></td>
        <td></td>
        <td><?=number_format($TotalRows)?></td>
        <td></td>
        <td><?=get_size($TotalDataSize)?></td>
        <td><?=get_size($TotalIndexSize)?></td>
        <td><?=get_size($TotalDataSize + $TotalIndexSize)?></td>
        <td></td>
    </tr>
</table>
<?php
show_footer();
