<?php
// error out on invalid requests (before caching)
if (isset($_GET['details'])) {
    if (in_array($_GET['details'], ['tagother', 'tagown', 'voteother', 'voteown'])) {
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
        <a href="/top10.php?type=torrents">[Torrents]</a>
        <a href="/top10.php?type=users">[Users]</a>
        <a href="/top10.php?type=tags">[Tags]</a>
        <a href="/top10.php?type=taggers"><strong>[Taggers]</strong></a>
    </div>

<?php

// defaults to 10 (duh)
$Limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$Limit = in_array($Limit, [10, 100, 250, 500]) ? $Limit : 10;

if ($Details=='all' || $Details=='tagother') {
    if (!$TopTaggers = $master->cache->getValue('toptaggers_'.$Limit)) {
        $TopTaggers = $master->db->rawQuery(
            "SELECT u.ID,
                    u.Username,
                    COUNT(tt.TagID)  AS NumTags
               FROM torrents_tags AS tt
               JOIN torrents AS t ON t.GroupID = tt.GroupID AND tt.UserID != t.UserID
               JOIN torrents_group AS tg ON tg.ID = tt.GroupID
               JOIN tags ON tt.TagID = tags.ID
               JOIN users AS u ON u.ID = tt.UserID
           GROUP BY tt.UserID
           ORDER BY Count(tt.TagID) DESC
              LIMIT {$Limit}"
        )->fetchAll(\PDO::FETCH_BOTH);

        $master->cache->cacheValue("toptaggers_{$Limit}", $TopTaggers, 3600 * 12);
    }

    generate_tagger_table('Taggers (others torrents)', 'tagother', $TopTaggers, $Limit);
}

if ($Details=='all' || $Details=='tagown') {
    if (!$TopOwnTaggers = $master->cache->getValue('topselftaggers_'.$Limit)) {
        $TopOwnTaggers = $master->db->rawQuery(
            "SELECT u.ID,
                    u.Username,
                    COUNT(tt.TagID) AS NumTags
               FROM torrents_tags AS tt
               JOIN torrents AS t ON t.GroupID = tt.GroupID AND tt.UserID = t.UserID
               JOIN torrents_group AS tg ON tg.ID = tt.GroupID
               JOIN tags ON tt.TagID = tags.ID
               JOIN users AS u ON u.ID = tt.UserID
           GROUP BY tt.UserID
           ORDER BY Count(tt.TagID) DESC
              LIMIT {$Limit}"
        )->fetchAll(\PDO::FETCH_BOTH);

        $master->cache->cacheValue("topselftaggers_{$Limit}", $TopOwnTaggers, 3600 * 12);
    }

    generate_tagger_table('Taggers (own torrents)', 'tagown', $TopOwnTaggers, $Limit);
}

if ($Details=='all' || $Details=='voteother') {
    if (!$TopTagVoters = $master->cache->getValue('toptagvoters_'.$Limit)) {
        $TopTagVoters = $master->db->rawQuery(
            "SELECT u.ID,
                    u.Username,
                    COUNT(ttv.TagID) AS NumTags
               FROM torrents_tags_votes AS ttv
               JOIN torrents AS t ON t.GroupID = ttv.GroupID AND ttv.UserID != t.UserID
               JOIN torrents_group AS tg ON tg.ID = ttv.GroupID
               JOIN tags ON ttv.TagID = tags.ID
               JOIN torrents_tags AS tt ON tt.TagID = ttv.TagID AND tt.GroupID = ttv.GroupID
               JOIN users AS u ON u.ID = ttv.UserID
           GROUP BY ttv.UserID
           ORDER BY Count(ttv.TagID) DESC
              LIMIT {$Limit}"
        )->fetchAll(\PDO::FETCH_BOTH);

        $master->cache->cacheValue("toptagvoters_{$Limit}", $TopTagVoters, 3600 * 12);
    }

    generate_tagger_table('Tag Voters (others torrents)', 'voteother', $TopTagVoters, $Limit, true);
}

if ($Details=='all' || $Details=='voteown') {
    if (!$TopTagVotersOwn = $master->cache->getValue('toptagvotersown_'.$Limit)) {
        $TopTagVotersOwn = $master->db->rawQuery(
            "SELECT u.ID,
                    u.Username,
                    COUNT(ttv.TagID) AS NumTags
               FROM torrents_tags_votes AS ttv
               JOIN torrents AS t ON t.GroupID = ttv.GroupID AND ttv.UserID = t.UserID
               JOIN torrents_group AS tg ON tg.ID = ttv.GroupID
               JOIN tags ON ttv.TagID = tags.ID
               JOIN torrents_tags AS tt ON tt.TagID = ttv.TagID AND tt.GroupID = ttv.GroupID
               JOIN users AS u ON u.ID = ttv.UserID
           GROUP BY ttv.UserID
           ORDER BY Count(ttv.TagID) DESC
              LIMIT {$Limit}"
        )->fetchAll(\PDO::FETCH_BOTH);

        $master->cache->cacheValue("toptagvotersown_{$Limit}", $TopTagVotersOwn, 3600 * 12);
    }

    generate_tagger_table('Tag Voters (own torrents)', 'voteown', $TopTagVotersOwn, $Limit, true);
}

?>
</div>
<?php
show_footer();
return;

// generate a table based on data from most recent query to $DB
function generate_tagger_table($Caption, $Tag, $Details, $Limit, $IsVotes=false)
{
?>
    <div class="head top10_tags">Top <?=$Limit.' '.$Caption?>
        <small>
            - [<a href="/top10.php?type=taggers&amp;limit=100&amp;details=<?=$Tag?>">Top 100</a>]
            - [<a href="/top10.php?type=taggers&amp;limit=250&amp;details=<?=$Tag?>">Top 250</a>]
            - [<a href="/top10.php?type=taggers&amp;limit=500&amp;details=<?=$Tag?>">Top 500</a>]
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
        <td class="tags_rank"><?=format_username($Detail['ID'])?></td>
        <td class="tags_uses"><?=$Detail['NumTags']?></td>

    </tr>
<?php
    }
    echo '</table><br />';
}
