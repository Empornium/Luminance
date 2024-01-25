<?php
if (!check_perms('site_view_flow')) { error(403); }

define('DAYS_PER_PAGE', 100);
list($Page, $Limit) = page_limit(DAYS_PER_PAGE);

$records = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            j.Date,
            DATE_FORMAT(j.Date, '%Y-%m') AS Month,
            CASE ISNULL(j.Flow)
                WHEN 0 THEN j.Flow
                ELSE '0'
            END AS Joined,
            CASE ISNULL(m.Flow)
                WHEN 0 THEN m.Flow
                ELSE '0'
            END AS Manual,
            CASE ISNULL(r.Flow)
                WHEN 0 THEN r.Flow
                ELSE '0'
            END AS Ratio,
            CASE ISNULL(i.Flow)
                WHEN 0 THEN i.Flow
                ELSE '0'
            END AS Inactivity
       FROM (
            SELECT
                DATE_FORMAT(JoinDate, '%Y-%m-%d') AS Date,
                COUNT(UserID) AS Flow
                FROM users_info
                WHERE JoinDate != '0000-00-00 00:00:00'
                GROUP BY Date
        ) AS j
        LEFT JOIN (
            SELECT
                DATE_FORMAT(BanDate, '%Y-%m-%d') AS Date,
                COUNT(UserID) AS Flow
                FROM users_info
                WHERE BanDate != '0000-00-00 00:00:00'
                AND BanReason = '1'
                GROUP BY Date
        ) AS m ON j.Date=m.Date
        LEFT JOIN (
            SELECT
                DATE_FORMAT(BanDate, '%Y-%m-%d') AS Date,
                COUNT(UserID) AS Flow
                FROM users_info
                WHERE BanDate != '0000-00-00 00:00:00'
                AND BanReason = '2'
                GROUP BY Date
        ) AS r ON j.Date=r.Date
        LEFT JOIN (
            SELECT
                DATE_FORMAT(BanDate, '%Y-%m-%d') AS Date,
                COUNT(UserID) AS Flow
                FROM users_info
                WHERE BanDate != '0000-00-00 00:00:00'
                AND BanReason = '3'
                GROUP BY Date
        ) AS i ON j.Date=i.Date
        ORDER BY j.Date DESC
        LIMIT {$Limit}")->fetchAll(\PDO::FETCH_NUM);
$Results = $master->db->foundRows();

show_header('User Flow');
show_header('User Flow', 'plotly, charts, jquery');
?>
<div class="thin">
<?php  if (!isset($_GET['page'])) { ?>
    <div class="box pad">
    <div id="chart_div" data-load_chart="/stats/user_flow_chart" class="js-plotly-plot"></div>
    </div>
<?php  } ?>
    <div class="linkbox">
<?php
$Pages = get_pages($Page, $Results, DAYS_PER_PAGE, 11);
echo $Pages;
?>
    </div>
    <table width="100%">
        <tr class="colhead">
            <td>Date</td>
            <td>(+) Joined</td>
            <td>(-) Manual</td>
            <td>(-) Ratio</td>
            <td>(-) Inactivity</td>
            <td>(-) Total</td>
            <td>Net Growth</td>
        </tr>
<?php
    foreach ($records as $record) {
        list($Date, $Month, $Joined, $Manual, $Ratio, $Inactivity) = $record;
        $TotalOut = $Ratio + $Inactivity + $Manual;
        $TotalGrowth = $Joined - $TotalOut;
?>
        <tr class="rowb">
            <td><?=$Date?></td>
            <td><?=number_format($Joined)?></td>
            <td><?=number_format($Manual)?></td>
            <td><?=number_format((double) $Ratio)?></td>
            <td><?=number_format($Inactivity)?></td>
            <td><?=number_format($TotalOut)?></td>
            <td><?=number_format($TotalGrowth)?></td>
        </tr>
<?php 	} ?>
    </table>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php
show_footer();
