<?php

include_once(SERVER_ROOT.'/Legacy/sections/reports/array.php');
include_once(SERVER_ROOT.'/Legacy/sections/reports/functions.php');

if (empty($_GET['type']) || empty($_GET['id']) || !is_integer_string($_GET['id'])) {
    error(0);
}

switch($_GET['type']) {
    case 'posthistory' :
        $Short = 'post';
        break;

    case 'torrents_commenthistory' :
        $Short = 'torrents_comment';
        break;

    case 'collages_comment' :
        $Short = 'collages_comments';
        break;

    default :
        $Short = $_GET['type'];
        break;
}

if (!array_key_exists($Short, $types)) {
    error('Report Malfunction, Contact Staff. ERROR: ReportArrayMissing');
}
$Type = $types[$Short];

$ID = $_GET['id'];

switch ($Short) {
    case "user" :
        $username = $master->db->rawQuery(
            "SELECT Username
               FROM users
              WHERE ID = ?",
            [$ID]
        )->fetchColumn();
        if ($master->db->foundRows() < 1) {
            error(404);
        }
        break;

    case "request" :
        $nextRecord = $master->db->rawQuery(
            "SELECT Title,
                    Description,
                    TorrentID,
                    UserID
               FROM requests
              WHERE ID = ?",
            [$ID]
        )->fetch(\PDO::FETCH_NUM);
        if ($master->db->foundRows() < 1) {
            error(404);
        }
        list($Name, $Desc, $Filled, $AuthorID) = $nextRecord;
        break;

    case "collage" :
        $nextRecord = $master->db->rawQuery(
            "SELECT Name,
                    Description,
                    UserID
               FROM collages
              WHERE ID = ?",
            [$ID]
        )->fetch(\PDO::FETCH_NUM);
        if ($master->db->foundRows() < 1) {
            error(404);
        }
        list($Name, $Desc, $AuthorID) = $nextRecord;
        break;

    case "thread" :
        $nextRecord = $master->db->rawQuery(
            "SELECT ft.Title,
                    ft.ForumID,
                    u.Username,
                    u.ID
               FROM forums_threads AS ft
               JOIN users AS u ON u.ID=ft.AuthorID
              WHERE ft.ID = ?",
            [$ID]
        )->fetch(\PDO::FETCH_NUM);
        if ($master->db->foundRows() < 1) {
            error(404);
        }
        list($Title, $forumID, $Username, $AuthorID) = $nextRecord;
        $minClassRead = $master->db->rawQuery(
            "SELECT MinClassRead
               FROM forums
              WHERE ID = ?",
            [$forumID]
        )->fetchColumn();
        if ($master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::FORUM) ||
                ($minClassRead > $activeUser['Class'] && (!isset($activeUser['CustomForums'][$forumID]) || $activeUser['CustomForums'][$forumID] == 0)) ||
                (isset($activeUser['CustomForums'][$forumID]) && $activeUser['CustomForums'][$forumID] == 0)) {
            error(403);
        }
        break;

    case "post" :
        $nextRecord = $master->db->rawQuery(
            "SELECT fp.Body,
                    fp.ThreadID,
                    u.Username,
                    u.ID
               FROM forums_posts AS fp
               JOIN users AS u ON u.ID=fp.AuthorID
              WHERE fp.ID = ?",
            [$ID]
        )->fetch(\PDO::FETCH_NUM);
        if ($master->db->foundRows() < 1) {
            error(404);
        }
        list($Body, $ThreadID, $Username, $AuthorID) = $nextRecord;
        $forumID = $master->db->rawQuery(
            "SELECT ForumID
               FROM forums_threads
              WHERE ID = ?",
            [$ThreadID]
        )->fetchColumn();
        $minClassRead = $master->db->rawQuery(
            "SELECT MinClassRead
               FROM forums
              WHERE ID = ?",
            [$forumID]
        )->fetchColumn();
        if ($master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::FORUM) ||
                ($minClassRead > $activeUser['Class'] && (!isset($activeUser['CustomForums'][$forumID]) || $activeUser['CustomForums'][$forumID] == 0)) ||
                (isset($activeUser['CustomForums'][$forumID]) && $activeUser['CustomForums'][$forumID] == 0)) {
            error(403);
        }
        break;

    case "requests_comment" :
    case "torrents_comment" :
    case "collages_comments" :
        if ($Short == "collages_comments") {
            $Table = $Short;
        } else {
            $Table = $Short.'s';
        }
        $Column = "AuthorID";
        $nextRecord = $master->db->rawQuery(
            "SELECT {$Short}.Body,
                    u.Username,
                    u.ID
               FROM {$Table} AS {$Short}
               JOIN users AS u ON u.ID = {$Short}.{$Column}
              WHERE {$Short}.ID = ?",
            [$ID]
        )->fetch(\PDO::FETCH_NUM);
        if ($master->db->foundRows() < 1) {
            error(404);
        }
        list($Body, $Username, $AuthorID) = $nextRecord;
        break;
}

show_header('Report a '.$Type['title'], 'bbcode');
?>
<div class="thin">
    <h2>Report <?=$Type['title']?></h2>
    <div class="head">Reporting guidelines</div>
    <div class="box pad">
        <?php echo report_guideline($Short, $types); ?>
    </div>
<?php

$bbCode = new \Luminance\Legacy\Text;

switch ($Short) {
    case "user" :
?>
    <p>You are reporting the user <strong><?= display_str($username) ?></strong></p>
<?php
        break;
    case "request_update" :
?>
    <div class="head">You are reporting the request:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Title</td>
            <td>Description</td>
            <td width="20">Filled?</td>
        </tr>
        <tr>
            <td><?=display_str($Name)?></td>
            <td><?=$bbCode->full_format($Desc, get_permissions_advtags($AuthorID))?></td>
            <td><strong><?=($Filled == 0 ? 'No' : 'Yes')?></strong></td>
        </tr>
    </table>
    <br />

    <div class="box pad center">
        <p><strong>It will greatly increase the turnover rate of the updates if you can fill in as much of the following details in as possible</strong></p>
        <form action="" method="post">
            <input type="hidden" name="action" value="takereport" />
            <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="type" value="<?=$Short?>" />
            <table>
                <tr>
                    <td class="label">Comment</td>
                    <td>
                        <textarea rows="8" cols="80" name="comment"></textarea>
                    </td>
                </tr>
            </table>
            <br />
            <br />
            <input type="submit" value="Submit report" />
        </form>
    </div>
<?php
        break;
    case "request" :
?>
    <div class="head">You are reporting the request:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Title</td>
            <td>Description</td>
            <td width="20">Filled?</td>
        </tr>
        <tr>
            <td><?=display_str($Name)?></td>
            <td><?=$bbCode->full_format($Desc, get_permissions_advtags($AuthorID))?></td>
            <td><strong><?=($Filled == 0 ? 'No' : 'Yes')?></strong></td>
        </tr>
    </table>
<?php
        break;
    case "collage" :
?>
    <div class="head">You are reporting the collage:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Title</td>
            <td>Description</td>
        </tr>
        <tr>
            <td><?=display_str($Name)?></td>
            <td><?=$bbCode->full_format($Desc, get_permissions_advtags($AuthorID))?></td>
        </tr>
    </table>
<?php
        break;
    case "thread" :
?>
    <div class="head">You are reporting the thread:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Username</td>
            <td>Title</td>
        </tr>
        <tr>
            <td><?=display_str($Username)?></td>
            <td><?=display_str($Title)?></td>
        </tr>
    </table>
<?php
        break;
    case "post" :
?>
    <div class="head">You are reporting the post:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Username</td>
            <td>Body</td>
        </tr>
        <tr>
            <td><?=display_str($Username)?></td>
            <td><?=$bbCode->full_format($Body, get_permissions_advtags($AuthorID))?></td>
        </tr>
    </table>
<?php
        break;
    case "requests_comment" :
    case "torrents_comment" :
    case "collages_comments" :
?>
    <div class="head">You are reporting the <?=$types[$Short]['title']?>:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Username</td>
            <td>Body</td>
        </tr>
        <tr>
            <td><?=display_str($Username)?></td>
            <td><?=$bbCode->full_format($Body, get_permissions_advtags($AuthorID))?></td>
        </tr>
    </table>
<?php
    break;
}
if (empty($NoReason)) {
?>
    <br/>
    <div class="head">Your reason for reporting:</div>
    <div class="box pad center">
        <form action="" method="post">
            <input type="hidden" name="action" value="takereport" />
            <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="type" value="<?=$Short?>" />
            <textarea rows="10" class="long" name="reason"></textarea><br /><br />
            <input type="submit" value="Submit report" />
        </form>
    </div>
<?php
}
// close <div class="thin"> ?>
</div>
<?php
show_footer();
