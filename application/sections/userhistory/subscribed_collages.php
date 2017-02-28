<?php
/*
  User collage subscription page
 */
if (!check_perms('site_collages_subscribe')) {
    error(403);
}

include(SERVER_ROOT . '/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

show_header('Subscribed collages', 'browse,collage');

$ShowAll = !empty($_GET['showall']);

if (!$ShowAll) {
    $sql = "SELECT c.ID,
               c.Name,
               c.NumTorrents,
               s.LastVisit
        FROM collages AS c
        JOIN users_collage_subs AS s ON s.CollageID = c.ID
        JOIN collages_torrents AS ct ON ct.CollageID = c.ID
        WHERE s.UserID = " . $LoggedUser['ID'] . " AND c.Deleted='0'
          AND ct.AddedOn > s.LastVisit
        GROUP BY c.ID";
} else {
    $sql = "SELECT c.ID,
               c.Name,
               c.NumTorrents,
               s.LastVisit
        FROM collages AS c
        JOIN users_collage_subs AS s ON s.CollageID = c.ID
        LEFT JOIN collages_torrents AS ct ON ct.CollageID = c.ID
        WHERE s.UserID = " . $LoggedUser['ID'] . " AND c.Deleted='0'
        GROUP BY c.ID";
}

$DB->query($sql);
$NumResults = $DB->record_count();
$CollageSubs = $DB->to_array();
?>
<div class="thin">
    <h2>Subscribed collages<?= ($ShowAll ? '' : ' with new additions') ?></h2>

    <div class="linkbox">
        <?php
        if ($ShowAll) {
            ?>
            <br /><br />
            [<a href="userhistory.php?action=subscribed_collages&showall=0">Only display collages with new additions</a>]&nbsp;&nbsp;&nbsp;
            <?php
        } else {
            ?>
            <br /><br />
            [<a href="userhistory.php?action=subscribed_collages&showall=1">Show all subscribed collages</a>]&nbsp;&nbsp;&nbsp;
            <?php
        }
        ?>
        [<a href="userhistory.php?action=catchup_collages&auth=<?= $LoggedUser['AuthKey'] ?>">Catch up</a>]&nbsp;&nbsp;&nbsp;
        <a href="userhistory.php?action=subscriptions">Go to forum subscriptions</a>&nbsp;&nbsp;&nbsp;
        <a href="userhistory.php?action=posts&amp;group=0&amp;showunread=0">Go to post history</a>&nbsp;&nbsp;&nbsp;
        <a href="userhistory.php?action=comments&amp;userid=<?=$LoggedUser['ID']?>">Go to comment history</a>
    </div>
    <?php
    if (!$NumResults) {
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

        foreach ($CollageSubs as $Collage) {
            unset($TorrentTable);

            list($CollageID, $CollageName, $CollageSize, $LastVisit) = $Collage;
            $RS = $DB->query("SELECT ct.GroupID,
                                tg.Image,
                                tg.NewCategoryID
                    FROM collages_torrents AS ct
                    JOIN torrents_group AS tg ON ct.GroupID = tg.ID
                    WHERE ct.CollageID = $CollageID
                      AND ct.AddedOn > '$LastVisit'
                    ORDER BY ct.AddedOn");
            $NewTorrentCount = $DB->record_count();
            //$NewTorrents = $DB->to_array();

            $GroupIDs = $DB->collect('GroupID');
            $CollageDataList = $DB->to_array('GroupID', MYSQLI_ASSOC);
            if (count($GroupIDs) > 0) {
                $TorrentList = get_groups($GroupIDs);
                $TorrentList = $TorrentList['matches'];
            } else {
                $TorrentList = array();
            }

            $Number = 0;

            foreach ($TorrentList as $GroupID => $Group) {
                list($GroupID, $GroupName, $TagList, $Torrents) = array_values($Group);
                list($GroupID2, $Image, $GroupCategoryID) = array_values($CollageDataList[$GroupID]);

                unset($DisplayName);

                $TagList = explode(' ', str_replace('_', '.', $TagList));

                $TorrentTags = array();
                $numtags=0;
                foreach ($TagList as $Tag) {
                    if ($numtags++>=$LoggedUser['MaxTags'])  break;
                    if (!isset($Tags[$Tag])) {
                        $Tags[$Tag] = array('name' => $Tag, 'count' => 1);
                    } else {
                        $Tags[$Tag]['count']++;
                    }
                    $TorrentTags[] = '<a href="torrents.php?taglist=' . $Tag . '">' . $Tag . '</a>';
                }
                $PrimaryTag = $TagList[0];
                $TorrentTags = implode(' ', $TorrentTags);
                $TorrentTags = '<br /><div class="tags">' . $TorrentTags . '</div>';

                $DisplayName .= '<a href="torrents.php?id=' . $GroupID . '" title="View Torrent">' . $GroupName . '</a>';

                // Start an output buffer, so we can store this output in $TorrentTable
                ob_start();
                // Viewing a type that does not require grouping

                list($TorrentID, $Torrent) = each($Torrents);

                $DisplayName = '<a href="torrents.php?id=' . $GroupID . '" title="View Torrent">' . $GroupName . '</a>';

                if (!empty($Torrent['FreeTorrent'])) {
                    $DisplayName .=' <strong>Freeleech!</strong>';
                }
                ?>
                <tr class="torrent" id="group_<?= $CollageID ?><?= $GroupID ?>">
                    <td></td>
                    <td class="center">
                        <div title="<?= ucfirst(str_replace('_', ' ', $PrimaryTag)) ?>" class="cats_<?= strtolower(str_replace(array('-', ' '), array('', ''), $Categories[$GroupCategoryID - 1])) ?> tags_<?= str_replace('.', '_', $PrimaryTag) ?>">
                        </div>
                    </td>
                    <td>
                        <span>
                            [<a href="torrents.php?action=download&amp;id=<?= $TorrentID ?>&amp;authkey=<?= $LoggedUser['AuthKey'] ?>&amp;torrent_pass=<?= $LoggedUser['torrent_pass'] ?>" title="Download">DL</a>
                            | <a href="reportsv2.php?action=report&amp;id=<?= $TorrentID ?>" title="Report">RP</a>]
                        </span>
                        <strong><?= $DisplayName ?></strong>
                        <?php  if ($LoggedUser['HideTagsInLists'] !== 1) {
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
                            <strong><a href="collages.php?id=<?= $CollageID ?>"><?= $CollageName ?></a></strong> (<?= $NewTorrentCount ?> new torrent<?= ($NewTorrentCount == 1 ? '' : 's') ?>)
                        </span>&nbsp;
                        <span style="float:right;">
                            <a href="#" onclick="$('#discog_table_<?= $CollageID ?>').toggle(); this.innerHTML=(this.innerHTML=='[Hide]'?'[Show]':'[Hide]'); return false;"><?= $ShowAll ? '[Show]' : '[Hide]' ?></a>&nbsp;&nbsp;&nbsp;[<a href="userhistory.php?action=catchup_collages&auth=<?= $LoggedUser['AuthKey'] ?>&collageid=<?= $CollageID ?>">Catch up</a>]&nbsp;&nbsp;&nbsp;<a href="#" onclick="CollageSubscribe(<?= $CollageID ?>); return false;" id="subscribelink<?= $CollageID ?>">[Unsubscribe]</a>
                        </span>
                    </td>
                </tr>
            </table>
            <!--</div>-->
            <table class="torrent_table <?= $ShowAll ? 'hidden' : '' ?>" id="discog_table_<?= $CollageID ?>">
                <tr class="colhead">
                    <td><!-- expand/collapse --></td>
        <?php  if (!$LoggedUser['HideCollage']) { ?>
                        <td style="padding: 0"><!-- image --></td>
                <?php  } ?>
                    <td width="70%"><strong>Torrents</strong></td>
                    <td>Size</td>
                    <td class="sign"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /></td>
                    <td class="sign"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /></td>
                    <td class="sign"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /></td>
                </tr>
        <?= $TorrentTable ?>
            </table>
    <?php  } // foreach() ?>
    <?php  }
?>
</div>
<?php
show_footer();
