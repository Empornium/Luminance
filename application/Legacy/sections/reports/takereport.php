<?php
authorize();

if (empty($_POST['id']) || !is_integer_string($_POST['id']) || empty($_POST['type'])) {
    error(0);
}

if ($_POST['type'] != "request_update" && empty($_POST['reason'])) {
    error("You must enter a reason for your report");
}

include(SERVER_ROOT.'/Legacy/sections/reports/array.php');
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
$ID = $_POST['id'];
if ($Short == "request_update") {
    $Reason .= "[b]Additional Comments[/b]: ".$_POST['comment'];
} else {
    $Reason = $_POST['reason'];
}
$bbCode = new \Luminance\Legacy\Text;
$bbCode->validate_bbcode($Reason,  get_permissions_advtags($activeUser['ID']));

switch ($Short) {
    case "request" :
    case "request_update" :
        $Link = "/requests.php?action=view&id={$ID}";
        break;
    case "user" :
        $Link = "/user.php?id={$ID}";
        break;
    case "collage" :
        $Link = "/collage/{$ID}";
        break;
    case "thread" :
        $Link = "/forum/thread/{$ID}";
        break;
    case "post" :
        list($PostID, $ThreadID, $PostNum) = $master->db->rawQuery(
            "SELECT p.ID,
                    p.ThreadID,
                    (
                        SELECT COUNT(ID)
                          FROM forums_posts
                         WHERE forums_posts.ThreadID = p.ThreadID
                           AND forums_posts.ID <= p.ID
                    ) AS PostNum
               FROM forums_posts AS p
              WHERE ID = ?",
            [$ID]
        )->fetch(\PDO::FETCH_NUM);
        if ($_GET['type'] == 'post') {
            $Link = "/forum/thread/{$ThreadID}?postid={$PostNum}#post{$PostID}";
        } else {
            $Link = "/userhistory.php?action=posts&group=0&showunread=0#post{$PostID}";
        }
        break;
    case "requests_comment" :
        list($RequestID, $Body, $PostNum) = $master->db->rawQuery(
            "SELECT rc.RequestID,
                    rc.Body,
                    (
                        SELECT COUNT(ID)
                          FROM requests_comments
                         WHERE ID <= ?
                           AND requests_comments.RequestID = rc.RequestID
                    ) AS CommentNum
               FROM requests_comments AS rc
              WHERE ID = ?",
            [$ID, $ID]
        )->fetch(\PDO::FETCH_NUM);
        $PageNum = ceil($PostNum / TORRENT_COMMENTS_PER_PAGE);
        $Link = "/requests.php?action=view&id={$RequestID}&page={$PageNum}#post{$ID}";
        break;
    case "torrents_comment" :
        list($GroupID, $Body, $PostNum) = $master->db->rawQuery(
            "SELECT tc.GroupID,
                    tc.Body,
                    (
                        SELECT COUNT(ID)
                          FROM torrents_comments
                         WHERE ID <= ?
                           AND torrents_comments.GroupID = tc.GroupID
                    ) AS CommentNum
               FROM torrents_comments AS tc
              WHERE ID = ?",
            [$ID, $ID]
        )->fetch(\PDO::FETCH_NUM);
        $PageNum = ceil($PostNum / TORRENT_COMMENTS_PER_PAGE);
        if ($_GET['type'] == 'torrents_comment') {
            $Link = "/torrents.php?id={$GroupID}&page={$PageNum}#post{$ID}";
        } else {
            $Link = "/userhistory.php?action=comments#post".$ID;
        }
        break;
    case "collages_comments" :
        list($CollageID, $Body, $PostNum) = $master->db->rawQuery(
            "SELECT cc.CollageID,
                    cc.Body,
                    (
                        SELECT COUNT(ID)
                          FROM collages_comments
                         WHERE ID <= ?
                           AND collages_comments.CollageID = cc.CollageID
                    ) AS CommentNum
               FROM collages_comments AS cc
              WHERE ID = ?",
            [$ID, $ID]
        )->fetch(\PDO::FETCH_NUM);
        $PerPage = POSTS_PER_PAGE;
        $PageNum = ceil($PostNum / $PerPage);
        $Link = "/collage.php?id={$CollageID}&page={$PageNum}#post{$ID}";
        break;
}

$master->db->rawQuery(
    "INSERT INTO reports (UserID, ThingID, Type, ReportedTime, Reason)
          VALUES (?, ?, ?, ?, ?)",
     [$activeUser['ID'], $ID, $Short, sqltime(), $Reason]
);
$ReportID = $master->db->lastInsertID();

$master->cache->deleteValue('num_update_reports');
$master->cache->deleteValue('num_forum_reports');
$master->cache->deleteValue('num_other_reports');

$scheme = $master->request->ssl ? 'https' : 'http';
$Short = ucwords(str_replace('_', ' ', $Short));
$message  = "[\002\00304{$Short} Reported\003\002]";
$message .= " by ".$activeUser['Username'];
$message .= " - {$scheme}://{$master->settings->main->site_url}/reports.php?view=report&id={$ReportID}";
$master->irker->announceReport($message);

header('Location: '.$Link);
