<?php
/*
  User collage subscription page
 */
if (!check_perms('collage_subscribe')) {
    error(403);
}

$bbCode = new \Luminance\Legacy\Text;

show_header('Subscribed collages', 'collage, overlib');

$ShowAll = !empty($_GET['showall']);

if (isset($activeUser['PostsPerPage'])) {
    $PerPage = $activeUser['PostsPerPage'];
} else {
    $PerPage = TORRENTS_PER_PAGE;
}
list($Page, $Limit) = page_limit($PerPage);

if (!$ShowAll) {
    $sql = "SELECT SQL_CALC_FOUND_ROWS
                   c.ID,
                   c.Name,
                   c.NumTorrents,
                   s.LastVisit
              FROM collages AS c
              JOIN collages_subscriptions AS s ON s.CollageID = c.ID
              JOIN collages_torrents AS ct ON ct.CollageID = c.ID
             WHERE s.UserID = ? AND c.Deleted = '0'
               AND ct.AddedOn > s.LastVisit
          GROUP BY c.ID
             LIMIT {$Limit}";
} else {
    $sql = "SELECT SQL_CALC_FOUND_ROWS
                   c.ID,
                   c.Name,
                   c.NumTorrents,
                   s.LastVisit
              FROM collages AS c
              JOIN collages_subscriptions AS s ON s.CollageID = c.ID
         LEFT JOIN collages_torrents AS ct ON ct.CollageID = c.ID
             WHERE s.UserID = ? AND c.Deleted = '0'
          GROUP BY c.ID
             LIMIT {$Limit}";
}

$collageSubs = $master->db->rawQuery($sql, [$activeUser['ID']])->fetchAll(\PDO::FETCH_NUM);
$numResults = $master->db->foundRows();
$TorrentTable = '';

?>
<div class="thin">
    <h2>Subscribed collages<?= ($ShowAll ? '' : ' with new additions') ?></h2>

    <div class="linkbox">
        <?php
        if ($ShowAll) {
            ?>
            <br /><br />
            [<a href="/userhistory.php?action=subscribed_collages&showall=0">Only display collages with new additions</a>]&nbsp;&nbsp;&nbsp;
            <?php
        } else {
            ?>
            <br /><br />
            [<a href="/userhistory.php?action=subscribed_collages&showall=1">Show all subscribed collages</a>]&nbsp;&nbsp;&nbsp;
            <?php
        }
        ?>
        [<a href="/userhistory.php?action=catchup_collages&auth=<?= $activeUser['AuthKey'] ?>">Catch up</a>]&nbsp;&nbsp;&nbsp;
        <a href="/userhistory.php?action=subscriptions">Go to forum subscriptions</a>&nbsp;&nbsp;&nbsp;
        <a href="/userhistory.php?action=posts&amp;group=0&amp;showunread=0">Go to post history</a>&nbsp;&nbsp;&nbsp;
        <a href="/userhistory.php?action=comments&amp;userid=<?=$activeUser['ID']?>">Go to comment history</a>
    </div>
    <?=get_pages($Page, $numResults, $PerPage)?>
    <?php
    if (!$numResults) {
        ?>
        <div class="center">
            No subscribed collages<?= ($ShowAll ? '' : ' with new additions') ?>
        </div>
        <?php
    } else {
        $HideGroup = '';
        $ActionTitle = "Hide";
        $ActionURL = "hide";
        $ShowGroups = 0;

        foreach ($collageSubs as $Collage) {
            $TorrentTable = '';

            list($CollageID, $CollageName, $CollageSize, $LastVisit) = $Collage;
            $groupIDs = $master->db->rawQuery(
                "SELECT ct.GroupID
                   FROM collages_torrents AS ct
                   JOIN torrents_group AS tg ON ct.GroupID = tg.ID
                  WHERE ct.CollageID = ?
                    AND ct.AddedOn > ?
               ORDER BY ct.AddedOn",
               [$CollageID, $LastVisit]
            )->fetchAll(\PDO::FETCH_COLUMN);

            $newTorrentCount = $master->db->foundRows();

            if (count($groupIDs) > 0) {
                $groups = get_groups($groupIDs, true, true, true);
                $groups = $groups['matches'];
            } else {
                $groups = [];
            }

            $Number = 0;

            foreach ($groups as $groupID => $group) {
                unset($DisplayName);

                $group['TagList'] = explode(' ', str_replace('_', '.', $group['TagList']));

                $TorrentTags = [];
                $numtags=0;
                foreach ($group['TagList'] as $Tag) {
                    if ($numtags++>=$activeUser['MaxTags'])  break;
                    if (!isset($Tags[$Tag])) {
                        $Tags[$Tag] = ['name' => $Tag, 'count' => 1];
                    } else {
                        $Tags[$Tag]['count']++;
                    }
                    $TorrentTags[] = '<a href="/torrents.php?taglist=' . $Tag . '">' . $Tag . '</a>';
                }
                $PrimaryTag = $group['TagList'][0];
                $TorrentTags = implode(' ', $TorrentTags);
                $TorrentTags = '<br /><div class="tags">' . $TorrentTags . '</div>';

                //$DisplayName .= '<a href="/torrents.php?id=' . $groupID . '" title="View Torrent">' . display_str($group['Name']) . '</a>';

                // Start an output buffer, so we can store this output in $TorrentTable
                ob_start();
                // Viewing a type that does not require grouping

                $Torrent = array_values($group['Torrents'])[0];
                $torrentID = $Torrent['ID'];

                $DisplayName = '<a href="/torrents.php?id=' . $groupID . '" onmouseover="return overlib(overlay'.$groupID.', FULLHTML);" onmouseout="return nd();">' . display_str($group['Name']) . '</a>';

                if (!empty($Torrent['FreeTorrent'])) {
                    $DisplayName .=' <strong>Freeleech!</strong>';
                }
                ?>
                <tr class="torrent" id="group_<?= $CollageID ?><?= $groupID ?>">
                    <td></td>
                    <td class="center">
                        <div title="<?= ucfirst(str_replace('_', ' ', $PrimaryTag)) ?>">
                        </div>
                    </td>
                    <td>
                        <?php if (!$activeUser['HideFloat']) {
                            $TorrentUsername = anon_username($Torrent['Username'], $Torrent['Anonymous']);
                            $Overlay = get_overlay_html($group['Name'], $TorrentUsername, $group['Image'], $Torrent['Seeders'], $Torrent['Leechers'], $Torrent['Size'], $Torrent['Snatched']); ?>
                            <script>
                                var overlay<?=$groupID?> = <?=json_encode($Overlay)?>
                            </script>
                        <?php } ?>
                        <span>
                            [<a href="/torrents.php?action=download&amp;id=<?= $torrentID ?>&amp;authkey=<?= $activeUser['AuthKey'] ?>&amp;torrent_pass=<?= $activeUser['torrent_pass'] ?>" title="Download">DL</a>
                            | <a href="/reportsv2.php?action=report&amp;id=<?= $torrentID ?>" title="Report">RP</a>]
                        </span>
                        <strong><?= $DisplayName ?></strong>
                        <?php  if ($activeUser['HideTagsInLists'] !== 1) {
                                echo $TorrentTags;
                           } ?>
                    </td>
                    <td class="nobr"><?= get_size($Torrent['Size']) ?></td>
                    <td><?= number_format($Torrent['Snatched']) ?></td>
                    <td<?= ($Torrent['Seeders'] == 0) ? ' class="r00"' : '' ?>><?= number_format($Torrent['Seeders']) ?></td>
                    <td><?= number_format($Torrent['Leechers']) ?></td>
                </tr>
            <?php
            $TorrentTable.=ob_get_clean();
        }
        ?>
            <!-- I hate that proton is making me do it like this -->
            <table style="margin-top: 8px">
                <tr class="colhead_dark">
                    <td>
                        <span style="float:left;">
                            <strong><a href="/collage/<?= $CollageID ?>"><?= $CollageName ?></a></strong> (<?= $newTorrentCount ?> new Torrent<?= ($newTorrentCount == 1 ? '' : 's') ?>)
                        </span>&nbsp;
                        <span style="float:right;">
                            <a href="#" onclick="$('#discog_table_<?= $CollageID ?>').toggle(); this.innerHTML=(this.innerHTML=='[Hide]'?'[Show]':'[Hide]'); return false;"><?= $ShowAll ? '[Show]' : '[Hide]' ?></a>&nbsp;&nbsp;&nbsp;[<a href="/userhistory.php?action=catchup_collages&auth=<?= $activeUser['AuthKey'] ?>&collageid=<?= $CollageID ?>">Catch up</a>]&nbsp;&nbsp;&nbsp;<a href="#" onclick="CollageSubscribe(<?= $CollageID ?>); return false;" id="subscribelink<?= $CollageID ?>">[Unsubscribe]</a>
                        </span>
                    </td>
                </tr>
            </table>
            <!--</div>-->
            <table class="torrent_table <?= $ShowAll ? 'hidden' : '' ?>" id="discog_table_<?= $CollageID ?>">
                <tr class="colhead">
                    <td><!-- expand/collapse --></td>
        <?php  if (!($activeUser['HideCollage'] ?? false)) { ?>
                        <td style="padding: 0"><!-- image --></td>
                <?php  } ?>
                    <td width="70%"><strong>Torrents</strong></td>
                    <td>Size</td>
                    <td class="sign"><img src="/static/styles/<?= $activeUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /></td>
                    <td class="sign"><img src="/static/styles/<?= $activeUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /></td>
                    <td class="sign"><img src="/static/styles/<?= $activeUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /></td>
                </tr>
        <?= $TorrentTable ?>
            </table>
    <?php  }  ?>
    <?php  }
?>
</div>
<?php
show_footer();
