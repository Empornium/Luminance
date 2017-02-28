<?php
// error out on invalid requests (before caching)
if (isset($_GET['details'])) {
    if (in_array($_GET['details'],array('ut','ur','v'))) {
        $Details = $_GET['details'];
    } else {
        error(404);
    }
} else {
    $Details = 'all';
}

show_header('Top 10 Tags');
?>
<div class="thin">
    <h2> Top 10 Tags </h2>
    <div class="linkbox">
        <a href="top10.php?type=torrents">[Torrents]</a>
        <a href="top10.php?type=users">[Users]</a>
        <a href="top10.php?type=tags"><strong>[Tags]</strong></a>
        <a href="top10.php?type=taggers">[Taggers]</a>
    </div>

<?php

// defaults to 10 (duh)
$Limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$Limit = in_array($Limit, array(10,100,250,500)) ? $Limit : 10;

if ($Details=='all' || $Details=='ut') {
    if (!$TopUsedTags = $Cache->get_value('topusedtag_'.$Limit)) {
        $DB->query("SELECT
            t.ID,
            t.Name,
            COUNT(tt.GroupID) AS Uses,
            SUM(tt.PositiveVotes-1) AS PosVotes,
            SUM(tt.NegativeVotes-1) AS NegVotes
            FROM tags AS t
            JOIN torrents_tags AS tt ON tt.TagID=t.ID
            GROUP BY tt.TagID
            ORDER BY Uses DESC, PosVotes DESC, NegVotes ASC
            LIMIT $Limit");
        $TopUsedTags = $DB->to_array();
        $Cache->cache_value('topusedtag_'.$Limit,$TopUsedTags,3600*12);
    }

    generate_tag_table('Most Used Torrent Tags', 'ut', $TopUsedTags, $Limit);
}

if ($Details=='all' || $Details=='ur') {
    if (!$TopRequestTags = $Cache->get_value('toprequesttag_'.$Limit)) {
        $DB->query("SELECT
            t.ID,
            t.Name,
            COUNT(r.RequestID) AS Uses,
            '',''
            FROM tags AS t
            JOIN requests_tags AS r ON r.TagID=t.ID
            GROUP BY r.TagID
            ORDER BY Uses DESC
            LIMIT $Limit");
        $TopRequestTags = $DB->to_array();
        $Cache->cache_value('toprequesttag_'.$Limit,$TopRequestTags,3600*12);
    }

    generate_tag_table('Most Used Request Tags', 'ur', $TopRequestTags, $Limit, false, true);
}

if ($Details=='all' || $Details=='v') {
    if (!$TopVotedTags = $Cache->get_value('topvotedtag_'.$Limit)) {
        $DB->query("SELECT
            t.ID,
            t.Name,
            COUNT(tt.GroupID) AS Uses,
            SUM(tt.PositiveVotes-1) AS PosVotes,
            SUM(tt.NegativeVotes-1) AS NegVotes,
                    (SUM(tt.PositiveVotes-1)-SUM(tt.NegativeVotes-1)) AS Votes
            FROM tags AS t
            JOIN torrents_tags AS tt ON tt.TagID=t.ID
            GROUP BY tt.TagID
            ORDER BY Votes DESC, Uses DESC
            LIMIT $Limit");
        $TopVotedTags = $DB->to_array();
        $Cache->cache_value('topvotedtag_'.$Limit,$TopVotedTags,3600*12);
    }

    generate_tag_table('Most Highly Voted Tags', 'v', $TopVotedTags, $Limit);
}

echo '</div>';
show_footer();
exit;

// generate a table based on data from most recent query to $DB
function generate_tag_table($Caption, $Tag, $Details, $Limit, $ShowVotes=true, $RequestsTable = false)
{
    if ($RequestsTable) {
        $URLString = 'requests.php?tags=';
    } else {
        $URLString = 'torrents.php?taglist=';
    }
?>
    <div class="head top10_tags">Top <?=$Limit.' '.$Caption?>
        <small>
            - [<a href="top10.php?type=tags&amp;limit=100&amp;details=<?=$Tag?>">Top 100</a>]
            - [<a href="top10.php?type=tags&amp;limit=250&amp;details=<?=$Tag?>">Top 250</a>]
            - [<a href="top10.php?type=tags&amp;limit=500&amp;details=<?=$Tag?>">Top 500</a>]
        </small>
    </div>
    <table class="top10_tags">
    <tr class="colhead">
        <td class="tags_rank">Rank</td>
        <td class="tags_tag">Tag</td>
        <td class="tags_uses">Uses</td>
<?php 	if ($ShowVotes) {	?>
        <td class="tags_votes">Votes</td>
            <td class="tags_votes_detail"></td>
            <td class="tags_votes_detail2"></td>
<?php 	}	?>
    </tr>
<?php
    // in the unlikely event that query finds 0 rows...
    if (empty($Details)) {
        echo '
        <tr class="rowb">
            <td colspan="9" class="center">
                Found no tags matching the criteria
            </td>
        </tr>
        </table><br />';

        return;
    }
    $Rank = 0;
    foreach ($Details as $Detail) {
        $Rank++;
        $Highlight = ($Rank%2 ? 'b' : 'a');

        // print row
?>
    <tr class="row<?=$Highlight?>">
        <td class="tags_rank"><?=$Rank?></td>
        <td class="tags_tag"><a href="<?=$URLString?><?=$Detail['Name']?>"><?=$Detail['Name']?></a></td>
        <td class="tags_uses"><?=$Detail['Uses']?></td>
<?php 		if ($ShowVotes) { ?>
        <td class="tags_votes"><span class="total_votes"><?=($Detail['PosVotes']-$Detail['NegVotes'])?></span></td>
            <td class="tags_votes_detail"><span class="pos_votes">+<?=$Detail['PosVotes']?></span></td>
            <td class="tags_votes_detail2"><span class="neg_votes">-<?=$Detail['NegVotes']?></span></td>
<?php 		} ?>
    </tr>
<?php
    }
    echo '</table><br />';
}
