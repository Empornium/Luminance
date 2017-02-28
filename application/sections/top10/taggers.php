<?php
// error out on invalid requests (before caching)
if (isset($_GET['details'])) {
    if (in_array($_GET['details'],array('tagother','tagown','voteother','voteown'))) {
        $Details = $_GET['details'];
    } else {
        error(404);
    }
} else {
    $Details = 'all';
}

show_header('Top 10 Taggers');
?>
<div class="thin">
    <h2> Top 10 Taggers </h2>
    <div class="linkbox">
        <a href="top10.php?type=torrents">[Torrents]</a>
        <a href="top10.php?type=users">[Users]</a>
        <a href="top10.php?type=tags">[Tags]</a>
        <a href="top10.php?type=taggers"><strong>[Taggers]</strong></a>
    </div>

<?php

// defaults to 10 (duh)
$Limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$Limit = in_array($Limit, array(10,100,250,500)) ? $Limit : 10;

if ($Details=='all' || $Details=='tagother') {
    if (!$TopTaggers = $Cache->get_value('toptaggers_'.$Limit)) {
        $DB->query("SELECT
                        um.ID,
                        um.Username,
                        COUNT(tt.TagID)  AS NumTags
                    FROM torrents_tags AS tt
                    JOIN torrents AS t ON t.GroupID=tt.GroupID AND tt.UserID!=t.UserID
                    JOIN torrents_group AS tg ON tg.ID=tt.GroupID
                    JOIN tags ON tt.TagID=tags.ID
                    JOIN users_main AS um ON um.ID=tt.UserID
                    GROUP BY tt.UserID
                    ORDER BY Count(tt.TagID) DESC
                    LIMIT $Limit");

        $TopTaggers = $DB->to_array();
        $Cache->cache_value('toptaggers_'.$Limit,$TopTaggers,3600*12);
    }

    generate_tagger_table('Taggers (others torrents)', 'tagother', $TopTaggers, $Limit);
}

if ($Details=='all' || $Details=='tagown') {
    if (!$TopOwnTaggers = $Cache->get_value('topselftaggers_'.$Limit)) {
        $DB->query("SELECT
                        um.ID,
                        um.Username,
                        COUNT(tt.TagID) AS NumTags
                    FROM torrents_tags AS tt
                    JOIN torrents AS t ON t.GroupID=tt.GroupID AND tt.UserID=t.UserID
                    JOIN torrents_group AS tg ON tg.ID=tt.GroupID
                    JOIN tags ON tt.TagID=tags.ID
                    JOIN users_main AS um ON um.ID=tt.UserID
                    GROUP BY tt.UserID
                    ORDER BY Count(tt.TagID) DESC
                    LIMIT $Limit");

        $TopOwnTaggers = $DB->to_array();
        $Cache->cache_value('topselftaggers_'.$Limit, $TopOwnTaggers, 3600*12);
    }

    generate_tagger_table('Taggers (own torrents)', 'tagown', $TopOwnTaggers, $Limit);
}

if ($Details=='all' || $Details=='voteother') {
    if (!$TopTagVoters = $Cache->get_value('toptagvoters_'.$Limit)) {
        $DB->query("SELECT
                        um.ID,
                        um.Username,
                        COUNT(ttv.TagID) AS NumTags
                    FROM torrents_tags_votes AS ttv
                    JOIN torrents AS t ON t.GroupID=ttv.GroupID AND ttv.UserID!=t.UserID
                    JOIN torrents_group AS tg ON tg.ID=ttv.GroupID
                    JOIN tags ON ttv.TagID=tags.ID
                    JOIN torrents_tags AS tt ON tt.TagID=ttv.TagID AND tt.GroupID=ttv.GroupID
                    JOIN users_main AS um ON um.ID=ttv.UserID
                    GROUP BY ttv.UserID
                    ORDER BY Count(ttv.TagID) DESC
                    LIMIT $Limit");

        $TopTagVoters = $DB->to_array();
        $Cache->cache_value('toptagvoters_'.$Limit, $TopTagVoters, 3600*12);
    }

    generate_tagger_table('Tag Voters (others torrents)', 'voteother', $TopTagVoters, $Limit, true);
}

if ($Details=='all' || $Details=='voteown') {
    if (!$TopTagVotersOwn = $Cache->get_value('toptagvotersown_'.$Limit)) {
        $DB->query("SELECT
                        um.ID,
                        um.Username,
                        COUNT(ttv.TagID) AS NumTags
                    FROM torrents_tags_votes AS ttv
                    JOIN torrents AS t ON t.GroupID=ttv.GroupID AND ttv.UserID=t.UserID
                    JOIN torrents_group AS tg ON tg.ID=ttv.GroupID
                    JOIN tags ON ttv.TagID=tags.ID
                    JOIN torrents_tags AS tt ON tt.TagID=ttv.TagID AND tt.GroupID=ttv.GroupID
                    JOIN users_main AS um ON um.ID=ttv.UserID
                    GROUP BY ttv.UserID
                    ORDER BY Count(ttv.TagID) DESC
                    LIMIT $Limit");

        $TopTagVotersOwn = $DB->to_array();
        $Cache->cache_value('toptagvotersown_'.$Limit, $TopTagVotersOwn, 3600*12);
    }

    generate_tagger_table('Tag Voters (own torrents)', 'voteown', $TopTagVotersOwn, $Limit, true);
}

?>
</div>
<?php
show_footer();
exit;

// generate a table based on data from most recent query to $DB
function generate_tagger_table($Caption, $Tag, $Details, $Limit, $IsVotes=false)
{
?>
    <div class="head top10_tags">Top <?=$Limit.' '.$Caption?>
        <small>
            - [<a href="top10.php?type=taggers&amp;limit=100&amp;details=<?=$Tag?>">Top 100</a>]
            - [<a href="top10.php?type=taggers&amp;limit=250&amp;details=<?=$Tag?>">Top 250</a>]
            - [<a href="top10.php?type=taggers&amp;limit=500&amp;details=<?=$Tag?>">Top 500</a>]
        </small>
    </div>
    <table class="top10_tags">
    <tr class="colhead">
        <td class="tags_rank">Rank</td>
        <td class="tags_rank">User</td>
        <td class="tags_uses"><?=($IsVotes?'Votes made':'Tags added');?></td>
    </tr>
<?php
    // in the unlikely event that query finds 0 rows...
    if (empty($Details)) {
        echo '
        <tr class="rowb">
            <td colspan="3" class="center">
                Found no taggers matching the criteria
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
        <td class="tags_rank"><?=format_username($Detail['ID'],$Detail['Username'])?></td>
        <td class="tags_uses"><?=$Detail['NumTags']?></td>

    </tr>
<?php
    }
    echo '</table><br />';
}
