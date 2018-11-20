<?php
authorize();

if (empty($_POST['id']) || !is_number($_POST['id']) || empty($_POST['type'])) {
    error(0);
}

if ($_POST['type'] != "request_update" && empty($_POST['reason'])) {
    error("You must enter a reason for your report");
}

include(SERVER_ROOT.'/Legacy/sections/reports/array.php');
switch ($_GET['type']) {
    case 'posthistory':
        $Short = 'post';
        break;

    case 'torrents_commenthistory':
        $Short = 'torrents_comment';
        break;

    default:
        $Short = $_GET['type'];
        break;
}

if (!array_key_exists($Short, $Types)) {
    error(403);
}

$Type = $Types[$Short];
$ID = $_POST['id'];
if ($Short == "request_update") {
    $Reason .= "[b]Additional Comments[/b]: ".$_POST['comment'];
} else {
    $Reason = $_POST['reason'];
}
$Text = new Luminance\Legacy\Text;
$Text->validate_bbcode($Reason, get_permissions_advtags($LoggedUser['ID']));

switch ($Short) {
    case "request":
    case "request_update":
        $Link = 'requests.php?action=view&id='.$ID;
        break;
    case "user":
        $Link = 'user.php?id='.$ID;
        break;
    case "collage":
        $Link = 'collages.php?id='.$ID;
        break;
    case "thread":
        $Link = 'forums.php?action=viewthread&threadid='.$ID;
        break;
    case "post":
        $DB->query("SELECT p.ID, p.TopicID, (SELECT COUNT(ID) FROM forums_posts WHERE forums_posts.TopicID = p.TopicID AND forums_posts.ID<=p.ID) AS PostNum FROM forums_posts AS p WHERE ID=".$ID);
        list($PostID,$TopicID,$PostNum) = $DB->next_record();
        if ($_GET['type'] == 'post') {
            $Link = "forums.php?action=viewthread&threadid=".$TopicID."&post=".$PostNum."#post".$PostID;
        } else {
            $Link = "userhistory.php?action=posts&group=0&showunread=0#post".$PostID;
        }
        break;
    case "requests_comment":
        $DB->query("SELECT rc.RequestID, rc.Body, (SELECT COUNT(ID) FROM requests_comments WHERE ID <= ".$ID." AND requests_comments.RequestID = rc.RequestID) AS CommentNum FROM requests_comments AS rc WHERE ID=".$ID);
        list($RequestID, $Body, $PostNum) = $DB->next_record();
        $PageNum = ceil($PostNum / TORRENT_COMMENTS_PER_PAGE);
        $Link = "requests.php?action=view&id=".$RequestID."&page=".$PageNum."#post".$ID."";
        break;
    case "torrents_comment":
        $DB->query("SELECT tc.GroupID, tc.Body, (SELECT COUNT(ID) FROM torrents_comments WHERE ID <= ".$ID." AND torrents_comments.GroupID = tc.GroupID) AS CommentNum FROM torrents_comments AS tc WHERE ID=".$ID);
        list($GroupID, $Body, $PostNum) = $DB->next_record();
        $PageNum = ceil($PostNum / TORRENT_COMMENTS_PER_PAGE);
        if ($_GET['type'] == 'torrents_comment') {
            $Link = "torrents.php?id=".$GroupID."&page=".$PageNum."#post".$ID;
        } else {
            $Link = "userhistory.php?action=comments#post".$ID;
        }
        break;
    case "collages_comment":
        $DB->query("SELECT cc.CollageID, cc.Body, (SELECT COUNT(ID) FROM collages_comments WHERE ID <= ".$ID." AND collages_comments.CollageID = cc.CollageID) AS CommentNum FROM collages_comments AS cc WHERE ID=".$ID);
        list($CollageID, $Body, $PostNum) = $DB->next_record();
        $PerPage = POSTS_PER_PAGE;
        $PageNum = ceil($PostNum / $PerPage);
        $Link = "collage.php?action=comments&collageid=".$CollageID."&page=".$PageNum."#post".$ID;
        break;
}

$DB->query("INSERT INTO reports
                (UserID, ThingID, Type, ReportedTime, Reason)
            VALUES
                (".db_string($LoggedUser['ID']).", ".$ID." , '".$Short."', '".sqltime()."', '".db_string($Reason)."')");
$ReportID = $DB->inserted_id();

$Channels = array();

if ($Short == "request_update") {
    $Channels[] = "#requestedits";
    $Cache->increment('num_update_reports');
}
if (in_array($Short, array('collages_comment', 'Post', 'requests_comment', 'thread', 'torrents_comment'))) {
    $Channels[] = "#forumreports";
}

foreach ($Channels as $Channel) {
    send_irc("PRIVMSG ".$Channel." :".$ReportID." - ".$LoggedUser['Username']." just reported a ".$Short.": https://".SITE_URL."/".$Link." : ".$Reason);
}

$Cache->delete_value('num_forum_reports');
$Cache->delete_value('num_other_reports');

header('Location: '.$Link);
