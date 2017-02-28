<?php
// error out on invalid requests (before caching)
if (isset($_GET['details'])) {
    if (in_array($_GET['details'],array('ul','dl','numul','uls','dls','rat'))) {
        $Details = $_GET['details'];
    } else {
        error(404);
    }
} else {
    $Details = 'all';
}

show_header('Top 10 Users');
?>
<div class="thin">
    <h2> Top 10 Users </h2>
    <div class="linkbox">
        <a href="top10.php?type=torrents">[Torrents]</a>
        <a href="top10.php?type=users"><strong>[Users]</strong></a>
        <a href="top10.php?type=tags">[Tags]</a>
        <a href="top10.php?type=taggers">[Taggers]</a>
    </div>

<?php
// defaults to 10 (duh)
$Limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$Limit = in_array($Limit, array(10,100,250,500)) ? $Limit : 10;

$BaseQuery = "SELECT
    u.ID,
    u.Username,
    ui.JoinDate,
    u.Uploaded,
    u.Downloaded,
    ABS(u.Uploaded-524288000) / (".time()." - UNIX_TIMESTAMP(ui.JoinDate)) AS UpSpeed,
    u.Downloaded / (".time()." - UNIX_TIMESTAMP(ui.JoinDate)) AS DownSpeed,
    COUNT(t.ID) AS NumUploads
    FROM users_main AS u
    JOIN users_info AS ui ON ui.UserID = u.ID
    LEFT JOIN torrents AS t ON t.UserID=u.ID
    WHERE u.Enabled='1'
    AND (Paranoia IS NULL OR (Paranoia NOT LIKE '%\"uploaded\"%' AND Paranoia NOT LIKE '%\"downloaded\"%')) ";

    if ($Details=='all' || $Details=='ul') {
        if (!$TopUserUploads = $Cache->get_value('topuser_ul_'.$Limit)) {
            $Query = $BaseQuery ."
                AND Uploaded>'". 1024*1024*1024 ."'
                GROUP BY u.ID";
            $DB->query("$Query ORDER BY u.Uploaded DESC LIMIT $Limit;");
            $TopUserUploads = $DB->to_array();
            $Cache->cache_value('topuser_ul_'.$Limit,$TopUserUploads,3600*12);
        }
        generate_user_table('Uploaders', 'ul', $TopUserUploads, $Limit);
    }

    if ($Details=='all' || $Details=='dl') {
        if (!$TopUserDownloads = $Cache->get_value('topuser_dl_'.$Limit)) {
            $Query = $BaseQuery ."
                AND Uploaded>'524288000'
                AND Downloaded>'". 1024*1024*1024 ."'
                GROUP BY u.ID";
            $DB->query("$Query ORDER BY u.Downloaded DESC LIMIT $Limit;");
            $TopUserDownloads = $DB->to_array();
            $Cache->cache_value('topuser_dl_'.$Limit,$TopUserDownloads,3600*12);
        }
        generate_user_table('Downloaders', 'dl', $TopUserDownloads, $Limit);
    }

    $Query = $BaseQuery ."
                AND Uploaded>'524288000'
                GROUP BY u.ID";

    if ($Details=='all' || $Details=='numul') {
        if (!$TopUserNumUploads = $Cache->get_value('topuser_numul_'.$Limit)) {
            $DB->query("$Query ORDER BY NumUploads DESC LIMIT $Limit;");
            $TopUserNumUploads = $DB->to_array();
            $Cache->cache_value('topuser_numul_'.$Limit,$TopUserNumUploads,3600*12);
        }
        generate_user_table('Torrents Uploaded', 'numul', $TopUserNumUploads, $Limit);
    }

    if ($Details=='all' || $Details=='uls') {
        if (!$TopUserUploadSpeed = $Cache->get_value('topuser_ulspeed_'.$Limit)) {
            $DB->query("$Query ORDER BY UpSpeed DESC LIMIT $Limit;");
            $TopUserUploadSpeed = $DB->to_array();
            $Cache->cache_value('topuser_ulspeed_'.$Limit,$TopUserUploadSpeed,3600*12);
        }
        generate_user_table('Fastest Uploaders', 'uls', $TopUserUploadSpeed, $Limit);
    }

    if ($Details=='all' || $Details=='dls') {
        if (!$TopUserDownloadSpeed = $Cache->get_value('topuser_dlspeed_'.$Limit)) {
            $DB->query("$Query ORDER BY DownSpeed DESC LIMIT $Limit;");
            $TopUserDownloadSpeed = $DB->to_array();
            $Cache->cache_value('topuser_dlspeed_'.$Limit,$TopUserDownloadSpeed,3600*12);
        }
        generate_user_table('Fastest Downloaders', 'dls', $TopUserDownloadSpeed, $Limit);
    }

    if ($Details=='all' || $Details=='rat') {
        if (!$TopUserRatio = $Cache->get_value('topuser_ratio_'.$Limit)) {
            $Query = $BaseQuery ."
                AND Uploaded>'". 1024*1024*1024 ."'
                AND Downloaded>'". 1024*1024*1024 ."'
                GROUP BY u.ID";
            $DB->query("$Query ORDER BY Uploaded/Downloaded DESC LIMIT $Limit;");
            $TopUserRatio = $DB->to_array();
            $Cache->cache_value('topuser_ratio_'.$Limit,$TopUserRatio,3600*12);
        }
        generate_user_table('Best Ratio', 'rat', $TopUserRatio, $Limit);
    }

echo '</div>';
show_footer();
exit;

// generate a table based on data from most recent query to $DB
function generate_user_table($Caption, $Tag, $Details, $Limit)
{
    global $Time;
?>
    <div class="head">Top <?=$Limit.' '.$Caption;?>
        <small>
            - [<a href="top10.php?type=users&amp;limit=100&amp;details=<?=$Tag?>">Top 100</a>]
            - [<a href="top10.php?type=users&amp;limit=250&amp;details=<?=$Tag?>">Top 250</a>]
            - [<a href="top10.php?type=users&amp;limit=500&amp;details=<?=$Tag?>">Top 500</a>]
        </small>
    </div>
    <table>
    <tr class="colhead">
        <td class="center">Rank</td>
        <td>User</td>
        <td style='text-align:right'>Uploaded</td>
        <td style='text-align:right'>UL speed</td>
        <td style='text-align:right'>Downloaded</td>
        <td style='text-align:right'>DL speed</td>
        <td style='text-align:right'>Uploads</td>
        <td style='text-align:right'>Ratio</td>
        <td style='text-align:right'>Joined</td>
    </tr>
<?php
    // in the unlikely event that query finds 0 rows...
    if (empty($Details)) {
        echo '
        <tr class="rowb">
            <td colspan="9" class="center">
                Found no users matching the criteria
            </td>
        </tr>
        </table><br />';

        return;
    }
    $Rank = 0;
    foreach ($Details as $Detail) {
        $Rank++;
        $Highlight = ($Rank%2 ? 'b' : 'a');
?>
    <tr class="row<?=$Highlight?>">
        <td class="center"><?=$Rank?></td>
        <td><?=format_username($Detail['ID'],$Detail['Username'])?></td>
        <td style="text-align:right"><?=get_size($Detail['Uploaded'])?></td>
        <td style="text-align:right"><?=get_size($Detail['UpSpeed'])?>/s</td>
        <td style="text-align:right"><?=get_size($Detail['Downloaded'])?></td>
        <td style="text-align:right"><?=get_size($Detail['DownSpeed'])?>/s</td>
        <td style="text-align:right"><?=number_format($Detail['NumUploads'])?></td>
        <td style="text-align:right"><?=ratio($Detail['Uploaded'], $Detail['Downloaded'])?></td>
        <td style="text-align:right"><?=time_diff($Detail['JoinDate'])?></td>
    </tr>
<?php
    }
?>
</table><br />
<?php
}
