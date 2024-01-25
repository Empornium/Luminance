<?php

use Luminance\Entities\TorrentComment;

authorize();

if (empty($_POST['groupid']) || !is_integer_string($_POST['groupid'])) {
    error(0);
}

if (empty($_POST['body'])) {
    error('You cannot post a comment with no content.');
}

$groupID = (int) $_POST['groupid'];

$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::POST);
$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::COMMENT);

$bbCode = new \Luminance\Legacy\Text;
$bbCode->validate_bbcode($_POST['body'],  get_permissions_advtags($activeUser['ID']));

flood_check('torrents_comments');

$postsPerPage = $master->request->user->options('PostsPerPage', $master->settings->pagination->torrent_comments);

$page = $master->db->rawQuery(
    "SELECT COUNT(ID)+1
       FROM torrents_comments AS tc
      WHERE tc.GroupID = ?",
    [$groupID]
)->fetchColumn();

$page = ceil($page/$postsPerPage);

$comment = new TorrentComment;
$comment->GroupID = $groupID;
$comment->TorrentID = $groupID;
$comment->AuthorID = $activeUser['ID'];
$comment->AddedTime = sqltime();
$comment->Body = $_POST['body'];
$master->repos->torrentcomments->save($comment);

$postID = $comment->ID;

list($TName, $UploaderID, $Notify) = $master->db->rawQuery(
    "SELECT tg.Name,
            t.UserID,
            CommentsNotify
       FROM users_info AS u
  LEFT JOIN torrents AS t ON t.UserID = u.UserID
  LEFT JOIN torrents_group AS tg ON tg.ID = t.GroupID
      WHERE t.GroupID = ?",
    [$groupID]
)->fetch(\PDO::FETCH_NUM);
# check whether system should pm uploader there is a new comment
if ($Notify == 1 && $UploaderID!=$activeUser['ID']) {
    send_pm($UploaderID, 0, "Comment received on your upload by {$activeUser['Username']}",
    "[br]You have received a comment from [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url] on your upload [url=/torrents.php?id={$groupID}&postid={$postID}#post{$postID}]{$TName}[/url][br][br][quote={$activeUser['Username']},t{$groupID},{$postID}]{$_POST['body']}[/quote]");
}

header('Location: torrents.php?id='.$groupID.'&postID='.$postID."#post$postID");
