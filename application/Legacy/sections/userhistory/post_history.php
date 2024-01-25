<?php
use Luminance\Entities\ForumPost;

$master->repos->restrictions->checkRestricted($master->request->user->ID, Luminance\Entities\Restriction::FORUM);

$bbCode = new \Luminance\Legacy\Text;

$userID = empty($_GET['userid']) ? $master->request->user->ID : $_GET['userid'];
if (!is_integer_string($userID)) {
    error(0);
}

if (isset($activeUser['PostsPerPage'])) {
    $perPage = $activeUser['PostsPerPage'];
} else {
    $perPage = POSTS_PER_PAGE;
}

list($page, $limit) = page_limit($perPage);

$user = $master->repos->users->load($userID);
$viewingOwn = ($userID == $master->request->user->ID);
$showUnread = ($viewingOwn && (!isset($_GET['showunread']) || !!$_GET['showunread']));
$showGrouped = ($viewingOwn && (!isset($_GET['group']) || !!$_GET['group']));
// FIXME: showunread = 1 & group = 1 causes full table scan
$showUnread = false;
$showGrouped = false;
if (check_perms('forum_post_trash')) {
    $checkFlags = ForumPost::TRASHED;
} else {
    $checkFlags = 0;
}

# num of params need to match query so build appropriately
$queryParams = [
    ':userid1'    => $user->ID,
    ':postflags'  => $checkFlags,
    ':userid2'    => $master->request->user->ID,
    ':userclass'  => $master->request->user->class->Level,
];

if (!empty($master->request->user->legacy['RestrictedForums'])) {
    $restrictedForums = (array)explode(',', $master->request->user->legacy['RestrictedForums']);
    $restrictedVars = $this->db->bindParamArray('rfid', $restrictedForums, $queryParams);
}
if (!empty($master->request->user->legacy['PermittedForums'])) {
    $userForums  = (array)explode(',', $master->request->user->legacy['PermittedForums']);
    $groupForums = (array)explode(',', $master->request->user->group->Forums);
    $permittedForums = array_merge($userForums, $groupForums);
    $permittedVars = $this->db->bindParamArray('pfid', $permittedForums, $queryParams);
}

$sql =
    'SELECT SQL_CALC_FOUND_ROWS
            p.ID
       FROM forums_posts as p';

if ($showGrouped || $showUnread) {
    $sql .= '
        JOIN (
            SELECT MAX(fp.ID) AS LastPostID,
                   fp.ThreadID
              FROM forums_posts AS fp
              JOIN (
                  SELECT MAX(AddedTime) AS AddedTime, ThreadID
                    FROM forums_posts
                GROUP BY ThreadID
              ) AS fp2 ON fp.AddedTime=fp2.AddedTime AND fp.ThreadID=fp2.ThreadID
          GROUP BY fp.ThreadID
        ) AS lp ON lp.ThreadID=p.ThreadID';
}

$sql .= '
  LEFT JOIN forums_threads AS t ON t.ID = p.ThreadID
  LEFT JOIN forums AS f ON f.ID = t.ForumID
  LEFT JOIN forums_last_read_threads AS l ON l.UserID = :userid2 AND l.ThreadID = t.ID
      WHERE p.AuthorID = :userid1
      AND p.Flags & :postflags = 0
        AND ';

$sql .= '((f.MinClassRead <= :userclass';
$sql .= (!empty($permittedVars) ? " OR f.ID IN ( {$permittedVars} ))" : ")");
$sql .= (!empty($restrictedVars) ? " AND f.ID NOT IN ( {$restrictedVars} ))" : ")");

if ($showUnread) {
    $sql .= '
    AND ((t.IsLocked=\'0\' OR t.IsSticky=\'1\')
    AND (l.PostID<lp.LastPostID OR l.PostID IS NULL))';
}

if ($showGrouped) {
  $sql .= " GROUP BY t.ID";
}

$sql .= " ORDER BY p.ID DESC LIMIT {$limit}";

$postIDs = $master->db->rawQuery($sql, $queryParams)->fetchAll(\PDO::FETCH_COLUMN);

$posts = [];
$results = $master->db->foundRows();
foreach ($postIDs as $postID) {
    $post = $master->repos->forumposts->load($postID);
    $posts[] = $post;
}

show_header("Post history for {$user->Username}", 'jquery, jquery.cookie, subscriptions, comments, bbcode');
$params = [
    'user'          => $user,
    'posts'         => $posts,
    'viewingOwn'    => $viewingOwn,
    'showUnread'    => $showUnread,
    'showGrouped'   => $showGrouped,
    'page'          => $page,
    'pageSize'      => $perPage,
    'results'       => $results,
];
echo $master->render->template('@Legacy/userhistory/post_history.html.twig', $params);
show_footer();
