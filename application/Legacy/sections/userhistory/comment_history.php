<?php
enforce_login();

$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::COMMENT);

$myTorrents = false;
if (!empty($_REQUEST['my_torrents']) && $_REQUEST['my_torrents'] == '1') {
    $myTorrents = true;
}

$userID = empty($_GET['userid']) ? $master->request->user->ID : $_GET['userid'];
if (!is_integer_string($userID)) {
    error(0);
}

if (isset($activeUser['PostsPerPage'])) {
    $perPage = $activeUser['PostsPerPage'];
} else {
    $perPage = POSTS_PER_PAGE;
}

$user = $master->repos->users->load($userID);
$viewingOwn = ($userID == $master->request->user->ID);

if ($viewingOwn === false) {
    if (!check_force_anon($user->ID) || !check_paranoia('torrentcomments', $user->legacy['Paranoia'], $user->class->Level, $user->ID)) {
        error(PARANOIA_MSG);
    }
}

list($page, $limit) = page_limit($perPage);
$join = '';
$where = '';

if ($myTorrents) {
    $join = "JOIN torrents AS t ON t.GroupID = tc.GroupID";
    $where = "t.UserID = ? AND tc.AuthorID != t.UserID AND tc.AddedTime > t.Time";
} else {
    $where = "tc.AuthorID = ?";
}

$commentIDs = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            tc.ID
       FROM torrents_comments as tc
       {$join}
      WHERE {$where}
   ORDER BY tc.AddedTime DESC
    LIMIT {$limit}",
    [$userID]
)->fetchAll(\PDO::FETCH_COLUMN);

$comments = [];
$results = $master->db->foundRows();
foreach ($commentIDs as $commentID) {
    $comment = $master->repos->torrentComments->load($commentID);
    $comments[] = $comment;
}

show_header($myTorrents?"Torrent comments left on $user->Username's torrents":"Torrent comment history for $user->Username", 'comments, bbcode, jquery, jquery.cookie');
$params = [
    'user'          => $user,
    'comments'      => $comments,
    'myTorrents'    => $myTorrents,
    'viewingOwn'    => $viewingOwn,
    'page'          => $page,
    'pageSize'      => $perPage,
    'results'       => $results,
];
echo $master->render->template('@Legacy/userhistory/comment_history.html.twig', $params);
show_footer();
