<?php
if (!check_perms('site_stats_advanced')) error(403);

if (!list($Countries,$Rank,$CountryUsers,$CountryMax,$CountryMin,$LogIncrements,$CountryUsersNum,$CountryName) = $Cache->get_value('geodistribution')) {
    include_once(SERVER_ROOT.'/classes/class_charts.php');
    $DB->query('SELECT Code, Users, country FROM users_geodistribution AS ug LEFT JOIN countries AS c ON c.cc=ug.Code ORDER BY Users DESC');
    $Data = $DB->to_array();
    $Count = $DB->record_count()-1;

    if ($Count<30) {
        $CountryMinThreshold = $Count;
    } else {
        $CountryMinThreshold = 30;
    }

    $CountryMax = ceil(log(Max(1,$Data[0][1]))/log(2))+1;
    $CountryMin = floor(log(Max(1,$Data[$CountryMinThreshold][1]))/log(2));

    $CountryRegions = array('RS' => array('RS-KM')); // Count Kosovo as Serbia as it doesn't have a TLD
    $i=0;
    foreach ($Data as $Key => $Item) {
        list($Country,$UserCount,$CName) = $Item;
        $Countries[$i] = $Country;
        $CountryUsers[$i] = number_format((((log($UserCount)/log(2))-$CountryMin)/($CountryMax-$CountryMin))*100,2);
        $Rank[$i] = round((1-($Key/$Count))*100);
        $CountryUsersNum[$i] = $UserCount;
        $CountryName[$i] = $CName;
        if (isset($CountryRegions[$Country])) {
            foreach ($CountryRegions[$Country] as $Region) {
                $i++;
                $Countries[$i] = $Region;
                $Rank[$i] = end($Rank);
            }
        }
        $i++;
    }
    reset($Rank);

    for ($i=$CountryMin;$i<=$CountryMax;$i++) {
        $LogIncrements[] = human_format(pow(2,$i));
    }
    $Cache->cache_value('geodistribution',array($Countries,$Rank,$CountryUsers,$CountryMax,$CountryMin,$LogIncrements,$CountryUsersNum,$CountryName),0);
}

if (!$ClassDistribution = $Cache->get_value('class_distribution')) {
    include_once(SERVER_ROOT.'/classes/class_charts.php');
    $DB->query("SELECT p.Name, COUNT(m.ID) AS Users FROM users_main AS m JOIN permissions AS p ON m.PermissionID=p.ID WHERE m.Enabled='1' GROUP BY p.Name ORDER BY Users DESC");
    $ClassSizes = $DB->to_array();
    $Pie = new PIE_CHART(750,400,array('Other'=>0.01,'Percentage'=>1));
    foreach ($ClassSizes as $ClassSize) {
        list($Label,$Users) = $ClassSize;
        $Pie->add($Label,$Users);
    }
    $Pie->transparent();
    $Pie->color('FF11aa');
    $Pie->generate();
    $ClassDistribution = $Pie->url();
    $Cache->cache_value('class_distribution',$ClassDistribution,3600*36); // 24*14
}
if (!$ClassDistributionWeek = $Cache->get_value('class_distribution_wk')) {
    include_once(SERVER_ROOT.'/classes/class_charts.php');
    $DB->query("SELECT p.Name, COUNT(m.ID) AS Users FROM users_main AS m JOIN permissions AS p ON m.PermissionID=p.ID
                WHERE m.Enabled='1' AND m.LastAccess>'".time_minus(3600*24*7, true)."' GROUP BY p.Name ORDER BY Users DESC");
    $ClassSizes = $DB->to_array();
    $Pie = new PIE_CHART(750,400,array('Other'=>0.01,'Percentage'=>1));
    foreach ($ClassSizes as $ClassSize) {
        list($Label,$Users) = $ClassSize;
        $Pie->add($Label,$Users);
    }
    $Pie->transparent();
    $Pie->color('FF11aa');
    $Pie->generate();
    $ClassDistributionWeek = $Pie->url();
    $Cache->cache_value('class_distribution_wk',$ClassDistributionWeek,3600*36); // 24*14
}
if (!$ClassDistributionMonth = $Cache->get_value('class_distribution_month')) {
    include_once(SERVER_ROOT.'/classes/class_charts.php');
    $DB->query("SELECT p.Name, COUNT(m.ID) AS Users FROM users_main AS m JOIN permissions AS p ON m.PermissionID=p.ID
                WHERE m.Enabled='1' AND m.LastAccess>'".time_minus(3600*24*30, true)."' GROUP BY p.Name ORDER BY Users DESC");
    $ClassSizes = $DB->to_array();
    $Pie = new PIE_CHART(750,400,array('Other'=>0.01,'Percentage'=>1));
    foreach ($ClassSizes as $ClassSize) {
        list($Label,$Users) = $ClassSize;
        $Pie->add($Label,$Users);
    }
    $Pie->transparent();
    $Pie->color('FF11aa');
    $Pie->generate();
    $ClassDistributionMonth = $Pie->url();
    $Cache->cache_value('class_distribution_month',$ClassDistributionMonth,3600*36); // 24*14
}
if (!$PlatformDistribution = $Cache->get_value('platform_distribution')) {
    include_once(SERVER_ROOT.'/classes/class_charts.php');

    $DB->query("SELECT cua.Platform, COUNT(s.UserID) AS Users
                  FROM sessions AS s
                  JOIN clients AS c ON s.ClientID=c.ID
                  JOIN client_user_agents AS cua ON c.ClientUserAgentID=cua.ID
              GROUP BY Platform
              ORDER BY Users DESC");

    $Platforms = $DB->to_array();
    $Pie = new PIE_CHART(750,400,array('Other'=>1,'Percentage'=>1));
    foreach ($Platforms as $Platform) {
        list($Label,$Users) = $Platform;
        $Pie->add($Label,$Users);
    }
    $Pie->transparent();
    $Pie->color('8A00B8');
    $Pie->generate();
    $PlatformDistribution = $Pie->url();
    $Cache->cache_value('platform_distribution',$PlatformDistribution,3600*36);
}

if (!$BrowserDistribution = $Cache->get_value('browser_distribution')) {
    include_once(SERVER_ROOT.'/classes/class_charts.php');

    $DB->query("SELECT cua.Browser, COUNT(s.UserID) AS Users
                  FROM sessions AS s
                  JOIN clients AS c ON s.ClientID=c.ID
                  JOIN client_user_agents AS cua ON c.ClientUserAgentID=cua.ID
              GROUP BY Platform
              ORDER BY Users DESC");

    $Browsers = $DB->to_array();
    $Pie = new PIE_CHART(750,400,array('Other'=>1,'Percentage'=>1));
    foreach ($Browsers as $Browser) {
        list($Label,$Users) = $Browser;
        $Pie->add($Label,$Users);
    }
    $Pie->transparent();
    $Pie->color('008AB8');
    $Pie->generate();
    $BrowserDistribution = $Pie->url();
    $Cache->cache_value('browser_distribution',$BrowserDistribution,3600*36);
}

// clients we can get from current peers
if (!$ClientDistribution = $Cache->get_value('client_distribution')) {
    include_once(SERVER_ROOT.'/classes/class_charts.php');

    $DB->query("SELECT useragent, Count(uid) AS Users FROM xbt_files_users GROUP BY useragent ORDER BY Users DESC");

    $Clients = $DB->to_array();
    $Pies = array();
    //we will split the results to get minor/major/client only versions of the pie charts
    $Pies[0]  = new PIE_CHART(750,400,array('Other'=>CLIENT_GRAPH_OTHER_PERCENT,'Percentage'=>1));
    $Pies[1]  = new PIE_CHART(750,400,array('Other'=>CLIENT_GRAPH_OTHER_PERCENT,'Percentage'=>1));
    $Pies[2]  = new PIE_CHART(750,400,array('Other'=>0.1,'Percentage'=>1));
    $Results2=array();
    $Results3=array();
    foreach ($Clients as $Client) {
        list($Label,$Users) = $Client;
        // minor version (ie. the whole client info)
        $Pies[0]->add($Label,$Users);
        // break down versions - matches formats "name/mv22/0101" or "name/v1234(mv4444)" or "name/v2345" or "name v.1.0"
        if (preg_match('#^(?|([^/]*)\/([^/]*)\/([^/]*)|([^/]*)\/([^/\(]*)\((.*)\)|([^/]*)\/([^/]*)|([^\s]*)\s(.*))$#', $Label, $matches)) {
            $Label2 = $matches[1] .'/'.$matches[2];
            $Label3 = $matches[1];
        } else {
            $Label2 = $Label;
            $Label3 = $Label;
        }
        // record users per client/ per major version
        if (!isset($Results2[$Label2])) $Results2[$Label2] = $Users;
        else $Results2[$Label2] += $Users;
        if (!isset($Results3[$Label3])) $Results3[$Label3] = $Users;
        else $Results3[$Label3] += $Users;
    }
    foreach ($Results2 as $Label=>$Users) {
        // major version (ie. client/vXXX)
        $Pies[1]->add($Label,$Users);
    }
    foreach ($Results3 as $Label=>$Users) {
        // client info only (ie. client)
        $Pies[2]->add($Label,$Users);
    }
    $ClientDistribution=array();
    foreach ($Pies as $Pie) {
        $Pie->transparent();
        $Pie->color('00D025');
        $Pie->generate();
        $ClientDistribution[] = $Pie->url();
    }
    $Cache->cache_value('client_distribution',$ClientDistribution,3600*36);
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

    $DB->query("SELECT ((CAST(DATE_FORMAT(JoinDate, '%Y') AS UNSIGNED) * 1000) + CAST(DATE_FORMAT(JoinDate, '%j') AS UNSIGNED)) AS YearDay,
                       DATE_FORMAT(JoinDate, '%d %b %y') AS Label, Count(UserID) As Users
                  FROM users_info
                 WHERE JoinDate BETWEEN '$start' AND '$end'
              GROUP BY Label
              ORDER BY JoinDate DESC
                 LIMIT 365");
    $TimelineIn = $DB->to_array('YearDay');

    $DB->query("SELECT ((CAST(DATE_FORMAT(BanDate, '%Y') AS UNSIGNED) * 1000) + CAST(DATE_FORMAT(BanDate, '%j') AS UNSIGNED)) AS YearDay,
                       DATE_FORMAT(BanDate, '%d %b %y') AS Label, Count(UserID) As Users
                  FROM users_info
                 WHERE BanDate != '0000-00-00 00:00:00'
                   AND BanDate BETWEEN '$start' AND '$end'
              GROUP BY Label
              ORDER BY BanDate DESC
                 LIMIT 365");
    $TimelineOut = $DB->to_array('YearDay');

    $UsersTimeline = array();
    foreach ($TimelineIn as $day) {
        list($Key, $Label, $UsersIn) = $day;
        $UsersOut = $TimelineOut["$Key"][2];
        if(!$UsersOut) $UsersOut = 0;
        $UsersTimeline["$Key"] = array($Label, $UsersIn, $UsersOut);
    }
    foreach ($TimelineOut as $day) {
        list($Key, $Label, $UsersOut) = $day;
        if (!isset($UsersTimeline["$Key"])) {
            $UsersTimeline["$Key"] = array($Label, 0, $UsersOut);
        }
    }
    $title = "$_POST[year1]-$_POST[month1]-$_POST[day1] to $_POST[year2]-$_POST[month2]-$_POST[day2]";
    ksort($UsersTimeline);
}

if (!$UsersTimeline) {
    $UsersTimeline = $Cache->get_value('users_timeline');
    $title = "last 365 days";
}
if ($UsersTimeline === false) {

    $title = "last 365 days";
    $DB->query("SELECT (CAST(DATE_FORMAT(JoinDate, '%Y') AS UNSIGNED) * 1000) + CAST(DATE_FORMAT(JoinDate, '%j') AS UNSIGNED) AS YearDay,
                       DATE_FORMAT(JoinDate, '%d %b %y') AS Label, Count(UserID) As Users
                  FROM users_info
                 WHERE JoinDate < (NOW() - INTERVAL 1 DAY)
              GROUP BY Label
              ORDER BY JoinDate DESC
                 LIMIT 365");
    $TimelineIn = $DB->to_array('YearDay');

    $DB->query("SELECT (CAST(DATE_FORMAT(BanDate, '%Y') AS UNSIGNED) * 1000) + CAST(DATE_FORMAT(BanDate, '%j') AS UNSIGNED) AS YearDay,
                       DATE_FORMAT(BanDate, '%d %b %y') AS Label, Count(UserID) As Users
                  FROM users_info
                 WHERE BanDate != '0000-00-00 00:00:00' AND BanDate < (NOW() - INTERVAL 1 DAY)
              GROUP BY Label
              ORDER BY BanDate DESC
                 LIMIT 365");
    $TimelineOut = $DB->to_array('YearDay');

    $UsersTimeline = array();
    foreach ($TimelineIn as $day) {
        list($Key, $Label, $UsersIn) = $day;
        $UsersOut = $TimelineOut["$Key"][2];
        if(!$UsersOut) $UsersOut = 0;
        $UsersTimeline["$Key"] = array($Label, $UsersIn, $UsersOut);
    }
    foreach ($TimelineOut as $day) {
        list($Key, $Label, $UsersOut) = $day;
        if (!isset($UsersTimeline["$Key"])) {
            $UsersTimeline["$Key"] = array($Label, 0, $UsersOut);
        }
    }
    ksort($UsersTimeline);
    $Cache->cache_value('users_timeline',$UsersTimeline, 3600*12 );
}

$startrow=0;
$endrow=0;
$maxrows = count($UsersTimeline)-1;

if (count($UsersTimeline)>0) {
    $endrow= $maxrows;
    $cols = "cols: [{id: 'date', label: 'Date', type: 'string'},
                    {id: 'users', label: 'New Registrations', type: 'number'},
                    {id: 'disabled', label: 'Disabled Users', type: 'number'}] ";

    $rows = array();
    //reset($SiteStats);
    foreach ($UsersTimeline as $data) {
        list($Label, $UsersIn, $UsersOut) = $data;
        $rows[] = " {c:[{v: '$Label'}, {v: $UsersIn}, {v: $UsersOut}]} ";
    }
    $rows[] = " {c:[{v: '$Label'}, {v: 0}, {v: 0}]} "; // stupid google charts
    $data = " { $cols, rows: [" . implode(",", $rows) . "] }";
}

//End timeline generation

show_header('User Statistics', 'charts,jquery');

?>
<div class="thin">
    <h2>User graphs</h2>
    <div class="linkbox">
        <strong><a href="stats.php?action=users">[User graphs]</a></strong>
        <a href="stats.php?action=site">[Site stats]</a>
        <a href="stats.php?action=torrents">[Torrent stats]</a>
    </div>
    <br/>
    <div class="head">User Flow</div>
    <table class="">
        <tr><td class="box pad center">
<?php
    if ($data) { ?>
        <h1>User Flow</h1>
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
            Load_Userstats();
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
    <br/><br/>

    <div class="head">User Classes</div>
    <div class="box pad center">
        <h1>User Classes</h1>
        <div>
        [<a onclick="$('#classdist2').hide(); $('#classdist3').hide(); $('#classdist1').show(); return false;" href="#" >active</a>]&nbsp;&nbsp;&nbsp;
        [<a onclick="$('#classdist1').hide(); $('#classdist2').hide(); $('#classdist3').show(); return false;" href="#" >last month</a>]&nbsp;&nbsp;&nbsp;
        [<a onclick="$('#classdist1').hide(); $('#classdist3').hide(); $('#classdist2').show(); return false;" href="#" >last week</a>]&nbsp;&nbsp;&nbsp;
        </div>
        <img id="classdist1" src="<?=$ClassDistribution?>" />
        <img id="classdist2" src="<?=$ClassDistributionWeek?>" class="hidden" />
        <img id="classdist3" src="<?=$ClassDistributionMonth?>" class="hidden" />
    </div>
    <br />
    <div class="head">User Platforms</div>
    <div class="box pad center">
        <h1>User Platforms</h1>
          <img src="<?=$PlatformDistribution?>" />
    </div>
    <br />
    <div class="head">User Browsers</div>
    <div class="box pad center">
        <h1>User Browsers</h1>
          <img src="<?=$BrowserDistribution?>" />
    </div>
    <br />
    <div class="head">User Clients</div>
    <div class="box pad center">
        <h1>User Clients</h1>
        <div class=" ">
        [<a onclick="$('#clientdist1').hide(); $('#clientdist2').hide(); $('#clientdist3').show(); return false;" href="#" >clients</a>]&nbsp;&nbsp;&nbsp;
        [<a onclick="$('#clientdist1').hide(); $('#clientdist3').hide(); $('#clientdist2').show(); return false;" href="#" >major version</a>]&nbsp;&nbsp;&nbsp;
        [<a onclick="$('#clientdist2').hide(); $('#clientdist3').hide(); $('#clientdist1').show(); return false;" href="#" >minor version</a>]
        </div>
        <br />
        <img id="clientdist1" src="<?=$ClientDistribution[0]?>" class="hidden" />
        <img id="clientdist2" src="<?=$ClientDistribution[1]?>" class="hidden" />
        <img id="clientdist3" src="<?=$ClientDistribution[2]?>" />
    </div>
    <br />
    <div class="head">Geographical Distribution Map</div>
    <div class="box center">
        <h1>Geographical Distribution Map</h1>
          <br />
          <img src="https://chart.apis.google.com/chart?cht=map:fixed=-55,-180,73,180&chs=720x360&chd=t:<?=implode(',',$Rank)?>&chco=FFFFFF,EDEDED,1F0066&chld=<?=implode('|',$Countries)?>&chf=bg,s,CCD6FF" />
          <br /><br />
          <img src="https://chart.apis.google.com/chart?cht=map:fixed=5,-180,70,9&chs=720x360&chd=t:<?=implode(',',$Rank)?>&chco=FFFFFF,EDEDED,1F0066&chld=<?=implode('|',$Countries)?>&chf=bg,s,CCD6FF" />
          <br /><br />
          <img src="https://chart.apis.google.com/chart?cht=map:fixed=37,-16,65,77&chs=720x360&chd=t:<?=implode(',',$Rank)?>&chco=FFFFFF,EDEDED,1F0066&chld=<?=implode('|',$Countries)?>&chf=bg,s,CCD6FF" />
          <br /><br />
          <img src="https://chart.apis.google.com/chart?cht=map:fixed=-56,-132,14,32&chs=720x360&chd=t:<?=implode(',',$Rank)?>&chco=FFFFFF,EDEDED,1F0066&chld=<?=implode('|',$Countries)?>&chf=bg,s,CCD6FF" />
          <br /><br />
          <img src="https://chart.apis.google.com/chart?cht=map:fixed=-36,-57,37,100&chs=720x360&chd=t:<?=implode(',',$Rank)?>&chco=FFFFFF,EDEDED,1F0066&chld=<?=implode('|',$Countries)?>&chf=bg,s,CCD6FF" />
          <br /><br />
          <img src="https://chart.apis.google.com/chart?cht=map:fixed=13,62,60,180&chs=720x360&chd=t:<?=implode(',',$Rank)?>&chco=FFFFFF,EDEDED,1F0066&chld=<?=implode('|',$Countries)?>&chf=bg,s,CCD6FF" />
          <br /><br />
          <img src="https://chart.apis.google.com/chart?cht=map:fixed=-50,60,15,180&chs=720x360&chd=t:<?=implode(',',$Rank)?>&chco=FFFFFF,EDEDED,1F0066&chld=<?=implode('|',$Countries)?>&chf=bg,s,CCD6FF" />
          <br /><br />
          <img src="https://chart.apis.google.com/chart?cht=map:fixed=14.8,15,45,86&chs=720x360&chd=t:<?=implode(',',$Rank)?>&chco=FFFFFF,EDEDED,1F0066&chld=<?=implode('|',$Countries)?>&chf=bg,s,CCD6FF" />
          <br /><br />
          <img src="https://chart.apis.google.com/chart?chxt=y,x&chg=0,-1,1,1&chxs=0,h&cht=bvs&chco=76A4FB&chs=880x300&chd=t:<?=implode(',',array_slice($CountryUsers,0,31))?>&chxl=1:|<?=implode('|',array_slice($Countries,0,31))?>|0:|<?=implode('|',$LogIncrements)?>&amp;chf=bg,s,FFFFFF00" />
          <br /><br />
          <table style="width:90%;margin: 0px auto;">
<?php
    $len = count($Countries);
    $numrows = ceil($len/6);
    for ($i=0;$i<$numrows;$i++) {
?>
              <tr>
<?php
        for ($k=0;$k<6;$k++) {
            $index = $i+($k*$numrows);
            if ($index >= $len) break;
            if ($index == $len-1 && $k<5) $colspan = ' colspan="'.(6-$k).'"';
            else $colspan='';
?>
                  <td<?=$colspan?> style="width:100px; padding: 0px 10px;">
                      <table style="width:100px; border:1px solid #c4c4c4;<?php if ($i<$numrows-1 || $index == $len-1) echo 'border-bottom: none';?>">
                          <tr>
                              <td class="rowa" style="width:50px" title="<?=$CountryName[$index]?>"><?=$Countries[$index]?></td>
                              <td class="rowb" style="width:50px"><?=$CountryUsersNum[$index]?></td>
                          </tr>
                      </table>
                  </td>
<?php
        }
?>
              </tr>
<?php
    }
?>
          </table>
          <br /><br />
          <p class="small">GeoLite data used under Creative Commons Attribution-ShareAlike 3.0 Unported License<br/>GeoLite data from MaxMind, available from http://www.maxmind.com</p>
    </div>
</div>
<?php
show_footer();
