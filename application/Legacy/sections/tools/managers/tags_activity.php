<?php

global $master;

$VotesPerPage = 25;

if (!check_perms('admin_manage_tags')) {
    error(403);
}

include_once(SERVER_ROOT.'/common/functions.php');

if (!empty($_GET['tag'])) {
    if (isset($_GET['order_way']) && in_array($_GET['order_way'], ['asc', 'desc'])) {
        $OrderWay = $_GET['order_way'];
    } else {
        $OrderWay = 'asc';
    }

    if (isset($_GET['order_by']) && in_array($_GET['order_by'], ['TagName', 'Username', 'Way', 'TorrentName'])) {
        $OrderBy = $_GET['order_by'];
    } else {
        $OrderBy = 'GroupID';
    }

    $Where = '';

    $Tag   = display_str($_GET['tag']);
    $Where = "WHERE tags.Name = '{$Tag}'";

    list($Page, $Limit) = page_limit($VotesPerPage);

    $TagsVotes = $master->db->raw_query("
      SELECT SQL_CALC_FOUND_ROWS ttv.TagID, tags.Name AS TagName, ttv.GroupID, ttv.UserID, ttv.Way, um.Username, tg.Name AS TorrentName
      FROM torrents_tags_votes AS ttv
      LEFT JOIN tags ON ttv.TagID = tags.ID
      LEFT JOIN users_main AS um ON um.ID = ttv.UserID
      LEFT JOIN torrents_group AS tg ON ttv.GroupID = tg.ID
      $Where
      ORDER BY $OrderBy $OrderWay
      LIMIT $Limit
    ")->fetchAll(\PDO::FETCH_ASSOC);

    $TagCount = $master->db->raw_query("SELECT FOUND_ROWS()")->fetchColumn();
    $Pages    = get_pages($Page, $TagCount, $VotesPerPage);
}


function display_way($Way)
{
    switch ($Way) {
        case 'up':
            return '<span class="green">Up</span>';
        case 'down':
            return '<span class="red">Down</span>';
        default:
            return '<span class="blue">-</span>';
    }
}

show_header('Tags Activity');

?>

<div class="thin">
    <h2>Tags Admin</h2>
    <?php printTagLinks(); ?>

    <div class="head">Filters</div>
    <div class="box pad center shadow">
        <form action="/tools.php">
            <input type="hidden" name="action" value="tags_activity">
            <label for="tag" class="label nobr">Search by tag:</label>
            <input id="tag" type="text" name="tag" value="<?= display_str($_REQUEST['tag']) ?>">
            <input type="submit" value="Search">
        </form>
    </div>

    <?php if(!empty($_GET['tag'])): ?>
    <div class="linkbox"><?= $Pages ?></div>
    <div class="head">Tags activity</div>
    <table>
        <tr class="colhead">
            <td>
                <a href="/<?= header_link('TagName') ?>" title="sort by number of votes">Tag</a>
            </td>
            <td>
                <a href="/<?= header_link('Username') ?>" title="sort by added by">User</a>
            </td>
            <td>
                <a href="/<?= header_link('Way') ?>" title="sort by vote direction">Way</a>
            </td>
            <td>
                <a href="/<?= header_link('TorrentName') ?>" title="sort by number of votes">Torrent</a>
            </td>
        </tr>
        <?php foreach($TagsVotes as $TagsVote): ?>
            <tr>
                <td>
                    <a href="/torrents.php?taglist=<?= display_str($TagsVote['TagName']) ?>"><?= display_str($TagsVote['TagName']) ?></a>
                </td>
                <td>
                    <a href="/user.php?id=<?= $TagsVote['UserID'] ?>"><?= display_str($TagsVote['Username']) ?></a>
                </td>
                <td>
                    <?= display_way($TagsVote['Way']) ?>
                </td>
                <td>
                    <a href="/torrents.php?id=<?= $TagsVote['GroupID'] ?>" title="<?= display_str($TagsVote['TorrentName']) ?>"><?= display_str(cut_string($TagsVote['TorrentName'],50)) ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <div class="head">Tags activity</div>
        <div class="box pad center shadow"><p>Search something first!</p></div>
    <?php endif; ?>
</div>

<?php show_footer();