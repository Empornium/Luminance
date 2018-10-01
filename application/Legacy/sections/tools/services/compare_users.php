<?php

/*
|--------------------------------------------------------------------------
| Users comparison tool
|--------------------------------------------------------------------------
|
| Sometimes, the detection of duplicate accounts needs a little more digging.
| The purpose of this tool is to give staff an overview of two accounts.
|
| TODO #1: Optimize SQL queries, although I like the readability so far (SubPixel)
|
*/

if (!check_perms('users_mod')) {
    error(403);
}

if (!isset($_REQUEST['usera'], $_REQUEST['userb'])) {
    error(0);
}

if ($_REQUEST['usera'] == $_REQUEST['userb']) {
    error('You cannot compare the same user.');
}

$UserA = user_info((int) $_REQUEST['usera']);
$UserB = user_info((int) $_REQUEST['userb']);

if (empty($UserA['ID']) || empty($UserB['ID'])) {
    error('Unable to find one of the given users. Please check the IDs.');
}

$UserA['Level'] = $Classes[$UserA['PermissionID']]['Level'];
$UserB['Level'] = $Classes[$UserB['PermissionID']]['Level'];
$CurUserLevel   = $LoggedUser['Class'];

// Staff cannot compare users above their rank
if ($CurUserLevel < $UserA['Level'] || $CurUserLevel < $UserB['Level']) {
    error('You cannot compare users above your rank.');
}

require SERVER_ROOT.'/common/functions.php';

# Shared E-Mails
$UserA['Emails'] = $master->db->raw_query("SELECT Address FROM emails WHERE UserID = :UserID", [':UserID' => $UserA['ID']])->fetchAll(PDO::FETCH_COLUMN);
$UserB['Emails'] = $master->db->raw_query("SELECT Address FROM emails WHERE UserID = :UserID", [':UserID' => $UserB['ID']])->fetchAll(PDO::FETCH_COLUMN);

# Shared IPs
$SharedIPs = $master->db->raw_query("SELECT uh.IP
                                     FROM users_history_ips AS uh
                                     INNER JOIN (SELECT IP FROM users_history_ips WHERE UserID = :UserA_ID) AS me ON uh.IP = me.IP
                                     WHERE uh.IP != '127.0.0.1' AND uh.IP !='' AND uh.UserID = :UserB_ID
                                     GROUP BY uh.IP",
                                     [':UserA_ID' => $UserA['ID'], ':UserB_ID' => $UserB['ID']])->fetchAll(PDO::FETCH_COLUMN);

# Shared Torrents
// Users' downloads count
$UserA['Downloads'] = $master->db->raw_query("SELECT COUNT(*) FROM users_downloads WHERE UserID = :UserID", [':UserID' => $UserA['ID']])->fetch(PDO::FETCH_COLUMN);
$UserB['Downloads'] = $master->db->raw_query("SELECT COUNT(*) FROM users_downloads WHERE UserID = :UserID", [':UserID' => $UserB['ID']])->fetch(PDO::FETCH_COLUMN);

$SharedTorrents = $master->db->raw_query("SELECT ud.TorrentID AS ID, tg.Name, t.Size, t.Time
                                          FROM users_downloads AS ud
                                          INNER JOIN (SELECT TorrentID FROM users_downloads WHERE UserID = :UserA_ID) AS ud2 ON ud.TorrentID = ud2.TorrentID
                                          LEFT JOIN torrents_group AS tg ON ud.TorrentID = tg.ID
                                          LEFT JOIN torrents AS t ON tg.ID = t.GroupID
                                          WHERE ud.UserID = :UserB_ID
                                          GROUP BY ud.TorrentID",
                                          [':UserA_ID' => $UserA['ID'], ':UserB_ID' => $UserB['ID']])->fetchAll(PDO::FETCH_ASSOC);

# Shared Categories
$UserA['CategoriesAll'] = $master->db->raw_query("SELECT NewCategoryID
                                               FROM users_downloads AS ud
                                               LEFT JOIN torrents_group AS tg ON ud.TorrentID = tg.ID
                                               LEFT JOIN torrents AS t ON tg.ID = t.GroupID
                                               WHERE ud.UserID = :UserID AND NewCategoryID IS NOT NULL",
                                               [':UserID' => $UserA['ID']])->fetchAll(PDO::FETCH_COLUMN);

$UserB['CategoriesAll'] = $master->db->raw_query("SELECT NewCategoryID
                                               FROM users_downloads AS ud
                                               LEFT JOIN torrents_group AS tg ON ud.TorrentID = tg.ID
                                               LEFT JOIN torrents AS t ON tg.ID = t.GroupID
                                               WHERE ud.UserID = :UserID AND NewCategoryID IS NOT NULL",
                                               [':UserID' => $UserB['ID']])->fetchAll(PDO::FETCH_COLUMN);

// Remove duplicate categories
$UserA['CategoriesUnique'] = array_unique($UserA['CategoriesAll']);
$UserB['CategoriesUnique'] = array_unique($UserB['CategoriesAll']);

// Count personal categories uses
$UserA['CategoriesCount'] = array_count_values($UserA['CategoriesAll']);
$UserB['CategoriesCount'] = array_count_values($UserB['CategoriesAll']);

// Users' shared categories
$SharedCategories = array_intersect($UserA['CategoriesUnique'], $UserB['CategoriesUnique']);

# Shared Uncommon Tags
// Set how "rare" a tag should be
$MaxUses = isset($_GET['maxuses']) ? (int) $_GET['maxuses'] : 50;

$UserA['RareTagsAll'] = $master->db->raw_query("SELECT t.Name
                                           FROM tags AS t
                                           INNER JOIN torrents_tags AS tt ON tt.TagID = t.ID
                                           INNER JOIN users_downloads AS ud ON ud.TorrentID = tt.GroupID
                                           WHERE t.Uses <= :MaxUses AND ud.UserID = :UserID",
                                           [':MaxUses' => $MaxUses, ':UserID' => $UserA['ID']])->fetchAll(PDO::FETCH_COLUMN);

$UserB['RareTagsAll'] = $master->db->raw_query("SELECT t.Name
                                           FROM tags AS t
                                           INNER JOIN torrents_tags AS tt ON tt.TagID = t.ID
                                           INNER JOIN users_downloads AS ud ON ud.TorrentID = tt.GroupID
                                           WHERE t.Uses <= :MaxUses AND ud.UserID = :UserID",
                                           [':MaxUses' => $MaxUses, ':UserID' => $UserB['ID']])->fetchAll(PDO::FETCH_COLUMN);

// Remove duplicate tags
$UserA['RareTagsUnique'] = array_unique($UserA['RareTagsAll']);
$UserB['RareTagsUnique'] = array_unique($UserB['RareTagsAll']);

// Count personal tags uses
$UserA['RareTagsCount'] = array_count_values($UserA['RareTagsAll']);
$UserB['RareTagsCount'] = array_count_values($UserB['RareTagsAll']);

// Users' shared rare tags
$SharedRareTags = array_intersect($UserA['RareTagsUnique'], $UserB['RareTagsUnique']);

show_header('Users comparison'); ?>

<div class="thin">
    <h2>Comparison between <a href="/user.php?id=<?= intval($UserA['ID']) ?>"><?= display_str($UserA['Username']) ?></a> and <a href="/user.php?id=<?= intval($UserB['ID']) ?>"><?= display_str($UserB['Username']) ?></a> (Beta)</h2>

    <div class="head">E-mails</div>
    <div class="box pad shadow">
            <table>
                <thead>
                    <tr>
                        <th><?= display_str($UserA['Username']) ?></th>
                        <th><?= display_str($UserB['Username']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= implode("<br>", $UserA['Emails']) ?></td>
                        <td><?= implode("<br>", $UserB['Emails']) ?></td>
                    </tr>
                </tbody>
            </table>
    </div>

    <div class="head">Shared IPs (<?= count($SharedIPs) ?>)</div>
    <div class="box pad shadow">
        <?php if (empty($SharedIPs)): ?>
            <p>These users have never shared the same IP.</p>
        <?php else: ?>
            <?php foreach($SharedIPs as $SharedIP): ?>
                <?= display_ip($SharedIP, geoip($SharedIP)) ?><br>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="head">Shared Torrents (<?= count($SharedTorrents) ?>)</div>
    <div class="box pad shadow">

        <p>
            <em>
                This section shows the torrents both users have downloaded, as some people like te re-watch the same videos they did not keep.<br>
                The less torrents they downloaded, the more alarming it is to find a shared torrent in this list.
            </em>
        </p>

        <p>
            <strong><?= display_str($UserA['Username']) ?></strong> has grabbed <?= (int) $UserA['Downloads'] ?> torrent(s).
            <strong><?= display_str($UserB['Username']) ?></strong> has grabbed <?= (int) $UserB['Downloads'] ?> torrent(s).
        </p>

        <?php if (empty($SharedTorrents)): ?>
            <p>These users have never downloaded the same torrent.</p>
        <?php else: ?>
                <table class="torrent_table">
                    <tr class="colhead">
                        <td></td>
                        <td>Torrent</td>
                        <td>Time</td>
                        <td>Size</td>
                    </tr>
                    <?php foreach($SharedTorrents as $SharedTorrent): ?>
                    <tr class="torrent">
                        <td class="center cats_col"><div title="CATEGORY"></div></td>
                        <td><a href="/torrents.php?id=<?= $SharedTorrent['ID'] ?>"><?= display_str($SharedTorrent['Name']) ?></a></td>
                        <td class="nobr"><?= time_diff($SharedTorrent['Time'], 1) ?></td>
                        <td class="nobr"><?= get_size($SharedTorrent['Size']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
        <?php endif; ?>
    </div>

    <div class="head">Shared Categories (<?= count($SharedCategories) ?>)</div>
    <div class="box pad shadow">
        <p><em>You can find here the niches the two users share.</em></p>
        <?php if (empty($SharedCategories)): ?>
            <p>These users do not share any categories.</p>
        <?php else: ?>
            <table class="torrent_table">
                <tr class="colhead">
                    <td></td>
                    <td>Category</td>
                    <td><?= display_str($UserA['Username']) ?>'s uses</td>
                    <td><?= display_str($UserB['Username']) ?>'s uses</td>
                </tr>
                <?php foreach($SharedCategories as $SharedCategory): ?>
                    <tr class="torrent">
                        <td><img src="static/common/caticons/<?= $NewCategories[$SharedCategory]['image'] ?>" /></td>
                        <td><?= display_str($NewCategories[$SharedCategory]['name']) ?></td>
                        <td class="nobr"><?= (int) $UserA['CategoriesCount'][$SharedCategory] ?></td>
                        <td class="nobr"><?= (int) $UserB['CategoriesCount'][$SharedCategory] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="head">Shared <abbr title="Used in less than <?= (int) $MaxUses ?> torrents">uncommon</abbr> Tags (<?= count($SharedRareTags) ?>)</div>
    <div class="box pad shadow">
        <p><em>You can find here all the "rare" tags the two users share. You can change the rarity of usage by adding a maxuses parameter in the URL.</em></p>
        <?php if (empty($SharedRareTags)): ?>
            <p>These users do not share uncommon tags.</p>
        <?php else: ?>
            <table class="torrent_table">
                <tr class="colhead">
                    <td>Tags name</td>
                    <td><?= display_str($UserA['Username']) ?>'s uses</td>
                    <td><?= display_str($UserB['Username']) ?>'s uses</td>
                </tr>
                <?php foreach($SharedRareTags as $SharedRareTag): ?>
                    <tr class="torrent">
                        <td><?= display_str($SharedRareTag) ?></td>
                        <td class="nobr"><?= (int) $UserA['RareTagsCount'][$SharedRareTag] ?></td>
                        <td class="nobr"><?= (int) $UserB['RareTagsCount'][$SharedRareTag] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php show_footer();