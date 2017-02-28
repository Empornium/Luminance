<?php

if (!check_perms('site_stats_advanced')) error(403);

include_once(SERVER_ROOT.'/classes/class_charts.php');
$DB->query("SELECT tg.NewCategoryID, COUNT(t.ID) AS Torrents
              FROM torrents AS t JOIN torrents_group AS tg ON tg.ID=t.GroupID
          GROUP BY tg.NewCategoryID ORDER BY Torrents DESC");
$Groups = $DB->to_array();
$Pie = new PIE_CHART(750,400,array('Other'=>0.2,'Percentage'=>1));
foreach ($Groups as $Group) {
    list($NewCategoryID, $Torrents) = $Group;
    //$CategoryName = $NewCategories[$NewCategoryID]['name'];
    $Pie->add($NewCategories[$NewCategoryID]['name'],$Torrents);
}
$Pie->transparent();
$Pie->color('FF33CC');
$Pie->generate();
$TorrentCategories = $Pie->url();

//==========================================================

if (isset($_POST['view']) && check_perms('site_stats_advanced')) {

    $start = date('Y-m-d H:i:s', strtotime( "$_POST[year1]-$_POST[month1]-$_POST[day1]" )  );
    $end = date('Y-m-d H:i:s', strtotime( "$_POST[year2]-$_POST[month2]-$_POST[day2]" )  );
   // error("$start --> $end");
    if($start===false) error("Error in start time input");
    if($end===false) error("Error in end time input");
    if (strtotime($start)<strtotime("2011-02-01")) {
        $start = "2011-02-01 00:00:00";
        $_POST['year1']=2011; $_POST['month1']=02; $_POST['day1']=01;
    }
    if (strtotime($end)>time()) $end = sqltime();
    if ($start>=$end) error("Start date ($start) cannot be after end date ($end)");

    $DB->query("SELECT DATE_FORMAT(Time, '%d %b %y') AS Label, Count(ID) As Torrents
                FROM torrents
                WHERE Time BETWEEN '$start' AND '$end'
                GROUP BY Label
                ORDER BY Time DESC
                LIMIT 365");
    $title = "$_POST[year1]-$_POST[month1]-$_POST[day1] to $_POST[year2]-$_POST[month2]-$_POST[day2]";

    $TorrentStats = array_reverse($DB->to_array());
}

if (!$TorrentStats) {
    $TorrentStats = $Cache->get_value('torrents_byday');
    $title = "last 365 days";
}

if ($TorrentStats === false) {
    $DB->query("SELECT DATE_FORMAT(Time, '%d %b %y') AS Label, Count(ID) As Torrents
                FROM torrents
                WHERE Time < (NOW() - INTERVAL 1 DAY)
                GROUP BY Label
                ORDER BY Time DESC
                LIMIT 365");
    $title = "last 365 days";
    $TorrentStats = $DB->to_array();
    $TorrentStats = array_reverse($TorrentStats);
    $Cache->cache_value('torrents_byday',$TorrentStats, 3600*24 );
}

$startrow=0;
$endrow=0;
$maxrows = count($TorrentStats);

if (count($TorrentStats)>0) {
    $endrow= $maxrows;
    $cols = "cols: [{id: 'date', label: 'Date', type: 'string'},
                    {id: 'torrents', label: 'Torrents Daily', type: 'number'}] ";

    $rows = array();
    //reset($SiteStats);
    foreach ($TorrentStats as $data) {
        list($Label, $Torrents) = $data;
        $rows[] = " {c:[{v: '$Label'}, {v: $Torrents}]} ";
    }
    $rows[] = " {c:[{v: '$Label'}, {v: 0}]} "; // stupid google charts
    $data = " { $cols, rows: [" . implode(",", $rows) . "] }";
}

//=================================================================

show_header('Torrent statistics','charts,jquery');
?>

<div class="thin">
    <h2>Torrent stats</h2>
    <div class="linkbox">
        <a href="stats.php?action=users">[User graphs]</a>
        <a href="stats.php?action=site">[Site stats]</a>
        <strong><a href="stats.php?action=torrents">[Torrent stats]</a></strong>
    </div>
    <br/>

    <div class="head">Uploads daily</div>
    <table class="">
        <tr><td class="box pad center">
<?php
    if ($data) { ?>
        <h1>Uploads daily</h1>
        <span style="position:relative;left:0px;"><?=$title?></span>
        <div id="chart_div"></div>
        <div style="margin:10px auto 10px">
        <input class="chart_button" type="button" value="|<" onclick="gstart(1200)" title="start" />
        <input class="chart_button" type="button" value="<<" onclick="prev(2,1200)" title="back" />
        <input class="chart_button" type="button" value="<" onclick="prev(0.9,800)" title="back" />&nbsp;&nbsp;
        <input class="chart_button" type="button" value="▽" onclick="zoomout()" title="zoom out" />
        <input class="chart_button" type="button" value="△" onclick="zoomin()" title="zoom in" />&nbsp;&nbsp;
        <input class="chart_button" type="button" value=">" onclick="next(0.9,800)" title="forward" />
        <input class="chart_button" type="button" value=">>" onclick="next(2,1200)" title="forward" />
        <input class="chart_button" type="button" value=">|" onclick="gend(1200)" title="end" />
        </div>
        <script type="text/javascript">
            var startrow = <?=$startrow?>;
            var endrow = <?=$endrow?>;
            var chartdata = <?=$data?>;
            Load_Torrentstats();
        </script>

<?php
        if (check_perms('site_debug')) {  ?>
            <span style="float:left">
                <a href="#debuginfo" onclick="$('#databox').toggle(); this.innerHTML=(this.innerHTML=='DEBUG: (Hide chart data)'?'DEBUG: (View chart data)':'DEBUG: (Hide chart data)'); return false;">DEBUG: (View chart data)</a>
            </span>&nbsp;

            <div id="databox" class="box pad hidden">
            <?=$data?>
            </div>
<?php       }  ?>
<?php
    } else { ?>
        <p>No torrent data found</p>
<?php   }  ?>
        </td></tr>

<?php
    if (check_perms('site_stats_advanced')) {
        if (isset($_POST['year1'])) {
            $start = array ($_POST['year1'],$_POST['month1'],$_POST['day1']);
            $end = array ($_POST['year2'],$_POST['month2'],$_POST['day2']);
        } else {
            //$start = array (2011,02,01);
            $start =  date('Y-m-d', time() - (3600*24*365));
            $start = explode('-', $start);
            $end =  date('Y-m-d');
            $end = explode('-', $end);
        }
?>
        <tr><td class="colhead">view options</td></tr>
        <tr><td class="box pad center">
            <form method="post" action="">
                <input type="text" style="width:30px" title="day" name="day1" value="<?=$start[2]?>" />
                <input type="text" style="width:30px" title="month" name="month1"  value="<?=$start[1]?>" />
                <input type="text" style="width:50px" title="year" name="year1"  value="<?=$start[0]?>" />
                &nbsp;&nbsp;To&nbsp;&nbsp;
                <input type="text" style="width:30px" title="day" name="day2"  value="<?=$end[2]?>" />
                <input type="text" style="width:30px" title="month" name="month2"  value="<?=$end[1]?>" />
                <input type="text" style="width:50px" title="year" name="year2"  value="<?=$end[0]?>" />
                &nbsp;&nbsp;&nbsp;&nbsp;
                <input type="submit" name="view" value="View history" />
            </form>
        </td></tr>
<?php   }  ?>

    </table>
    <br/><br/>
    <div class="head">Torrents by category</div>
    <div class="box pad center">
        <h1>Torrents by category</h1>
        <img src="<?=$TorrentCategories?>" />
    </div>
</div>
<?php
show_footer();
