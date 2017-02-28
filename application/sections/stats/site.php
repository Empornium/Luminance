<?php
if (!check_perms('site_view_stats')) error(403);

// helper tool for building old data from exisitng dataset (for switchover) NOTE: this is tailored to our specific data...
if (isset($_POST['builddata']) && check_perms('site_debug')) {

    $date_start = date('Y-m-d H:i:s', strtotime( "$_POST[start_year]-$_POST[start_month]-$_POST[start_day]" )  );
    if($date_start===false) error("Error in Start date input");
    //$date = new DateTime('2011-02-01');
    $date = new DateTime($date_start);

    $deleteend = date('Y-m-d H:i:s', strtotime( "$_POST[end_year]-$_POST[end_month]-$_POST[end_day]" )  );
    if($deleteend===false) error("Error in End date input");
    if (strtotime($deleteend)<strtotime($date_start)) error("End date is before data range ($deleteend < $date_start)");
    //if (strtotime($deleteend)>time()) $deleteend = sqltime();

    $end = new DateTime($deleteend);

    $DB->query("DELETE FROM site_stats_history WHERE TimeAdded <= '".$end->format('Y-m-d H:i:s')."'");

    do {
        $time = $date->format('Y-m-d H:i:s');
        $lastaccess = date('Y-m-d H:i:s', $date->getTimestamp() - (3600*24*30*4));

        $DB->query("INSERT INTO site_stats_history ( TimeAdded, Users, Torrents, Seeders, Leechers )
                       VALUES ('$time',
                                (SELECT Count(UserID) AS Users FROM users_info AS u JOIN users_main AS um ON um.ID=u.UserID
                                    WHERE JoinDate < '$time' AND Enabled='1'
                                      AND ( BanDate > '$time' OR BanDate='0000-00-00 00:00:00' ) ),
                                (SELECT Count(ID) AS NumT FROM torrents WHERE Time < '$time'),
                                '0','0' )");
        $date->add(new DateInterval('PT6H'));
        if ($date>$end) break;
    } while (true);

    $Cache->delete_value('site_stats');
}

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

    $DB->query("SELECT DATE_FORMAT(TimeAdded,'%d %b %y') AS Label,
                       CAST(AVG(Users) AS SIGNED) AS Users,
                       CAST(AVG(Torrents) AS SIGNED) AS Torrents,
                       CAST(AVG(Seeders) AS SIGNED) AS Seeders,
                       CAST(AVG(Leechers) AS SIGNED) AS Leechers
                    FROM site_stats_history
                   WHERE TimeAdded >= '$start' AND TimeAdded <= '$end'
                GROUP BY Label
                  HAVING Count(ID)=4
                ORDER BY TimeAdded DESC");
    $title = "$_POST[year1]-$_POST[month1]-$_POST[day1] to $_POST[year2]-$_POST[month2]-$_POST[day2]";

    $SiteStats = array_reverse($DB->to_array());

}

if (!$SiteStats) {
    $SiteStats = $Cache->get_value('site_stats');
    $title = "last 365 days";
}
if ($SiteStats === false) {

    $DB->query("SELECT DATE_FORMAT(TimeAdded,'%d %b %y') AS Label,
                       CAST(AVG(Users) AS SIGNED) AS Users,
                       CAST(AVG(Torrents) AS SIGNED) AS Torrents,
                       CAST(AVG(Seeders) AS SIGNED) AS Seeders,
                       CAST(AVG(Leechers) AS SIGNED) AS Leechers
                    FROM site_stats_history
                GROUP BY DATE_FORMAT(TimeAdded,'%d %b %y')
                  HAVING Count(ID)=4
                ORDER BY TimeAdded DESC
                   LIMIT 365");
    $title = "last 365 days";

    $SiteStats = array_reverse($DB->to_array());
    //error(print_r($SiteStats,true));
    $Cache->cache_value('site_stats',$SiteStats, 3600*12 );
}

$startrow=0;
$endrow=0;
$maxrows = count($SiteStats)-1;

if (count($SiteStats)>0) {

    $endrow= $maxrows;
    $cols = "cols: [{id: 'date', label: 'Date', type: 'string'},
                    {id: 'users', label: 'Users', type: 'number'},
                    {id: 'torrents', label: 'Torrents', type: 'number'},
                    {id: 'seeders', label: 'Seeders', type: 'number'},
                    {id: 'leechers', label: 'Leechers', type: 'number'}] ";

    $rows = array();
    //reset($SiteStats);
    foreach ($SiteStats as $data) {
        list($Label, $Users, $Torrents, $Seeders, $Leechers) = $data;
        $rows[] = " {c:[{v: '$Label'}, {v: $Users}, {v: $Torrents}, {v: $Seeders}, {v: $Leechers}]} ";
    }
    $rows[] = " {c:[{v: '$Label'}, {v: $Users}, {v: $Torrents}, {v: $Seeders}, {v: $Leechers}]} ";
    //$rows[] = " {c:[{v: '$Label'}, {v: 0}, {v: 0}, {v: 0}, {v: 0}]} "; // stupid google charts
    $data = " { $cols, rows: [" . implode(",", $rows) . "] }";
}

show_header('Site Statistics', 'charts,jquery');

?>
<div class="thin">
    <h2>Site stats</h2>
    <div class="linkbox">
        <a href="stats.php?action=users">[User graphs]</a>
        <strong><a href="stats.php?action=site">[Site stats]</a></strong>
        <a href="stats.php?action=torrents">[Torrent stats]</a>
    </div>
    <br/>
    <div class="head">Site history</div>
    <table class="">
        <tr><td class="box pad center">
<?php
    if ($data) { ?>
        <h1>Site history</h1>
        <span style="position:relative;left:0px;"><?=$title?></span>
        <div id="chart_div"></div>
        <div style="margin:0px auto 10px">
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
            //var maxrows = <?=$maxrows?>;
            var chartdata = <?=$data?>;
            Load_Sitestats();
        </script>
<?php
    } else { ?>
        <p>No site data found</p>
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

<?php
    if (check_perms('site_debug')) {
?>
        <br/>
        <div class="head">debug info</div>
        <div id="debuginfo" class="box pad center">
            <form method="post" action="">
                Start Date:&nbsp;
                <input type="text" style="width:30px" title="day" name="start_day"  value="01" />
                <input type="text" style="width:30px" title="month" name="start_month"  value="02" />
                <input type="text" style="width:50px" title="year" name="start_year"  value="2012" />
                &nbsp;&nbsp;&nbsp;End Date:&nbsp;
                <input type="text" style="width:30px" title="day" name="end_day"  value="01" />
                <input type="text" style="width:30px" title="month" name="end_month"  value="08" />
                <input type="text" style="width:50px" title="year" name="end_year"  value="2012" />
                <br/>
                <input type="submit" name="builddata" value="Create old torrent and user data - CAUTION - deletes data before end date" title="basically this a button marked 'Dont press!' ... please resist temptation" />
            </form>
            <span style="float:left">
                <a href="#debuginfo" onclick="$('#databox').toggle(); this.innerHTML=(this.innerHTML=='DEBUG: (Hide chart data)'?'DEBUG: (View chart data)':'DEBUG: (Hide chart data)'); return false;">DEBUG: (View chart data)</a>
            </span>&nbsp;

            <div id="databox" class="box pad hidden">
            <?=$data?>
            </div>
        </div>
<?php   }  ?>
</div>
<?php
show_footer();
