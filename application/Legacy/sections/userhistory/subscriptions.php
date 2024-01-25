<?php
/*
User topic subscription page
*/

$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::FORUM);

$bbCode = new \Luminance\Legacy\Text;

if (isset($activeUser['PostsPerPage'])) {
    $perPage = $activeUser['PostsPerPage'];
} else {
    $perPage = POSTS_PER_PAGE;
}
list($page, $limit) = page_limit($perPage);

$showUnread = (!isset($_GET['showunread']) && !isset($heavyInfo['SubscriptionsUnread']) || isset($heavyInfo['SubscriptionsUnread']) && !!$heavyInfo['SubscriptionsUnread'] || isset($_GET['showunread']) && !!$_GET['showunread']);
$showCollapsed = (!isset($_GET['collapse']) && !isset($heavyInfo['SubscriptionsCollapse']) || isset($heavyInfo['SubscriptionsCollapse']) && !!$heavyInfo['SubscriptionsCollapse'] || isset($_GET['collapse']) && !!$_GET['collapse']);

$user = $master->request->user;

# num of params need to match query so build appropriately
$params = [
    ':userid1'    => $user->ID,
    ':userid2'    => $user->ID,
    ':userclass'  => $user->class->Level,
];

if (!empty($user->legacy['RestrictedForums'])) {
    $restrictedForums = (array)explode(',', ($user->legacy['RestrictedForums'] ?? []));
    $restrictedVars = $this->db->bindParamArray('rfid', $restrictedForums, $params);
}
if (!empty($user->legacy['PermittedForums'])) {
    $userForums  = (array)explode(',', ($user->legacy['PermittedForums'] ?? []));
    $groupForums = [];
    if (!empty($user->group->Forums)) {
        $groupForums = (array)explode(',', ($user->group->Forums ?? []));
    }
    $permittedForums = array_merge($userForums, $groupForums);
    $permittedVars = $this->db->bindParamArray('pfid', $permittedForums, $params);
}

if (check_perms('forum_moderate')) {
    $flags = 0;
} else {
    $flags = Luminance\Entities\ForumPost::TRASHED;
}

$sql = "SELECT
    SQL_CALC_FOUND_ROWS
    IFNULL(MIN(p.ID), lp.LastPostID) AS PostID,
    IFNULL(COUNT(p.ID), 0) AS NewPosts
    FROM forums_subscriptions AS s
    LEFT JOIN forums_last_read_threads AS l ON s.ThreadID = l.ThreadID AND s.UserID = l.UserID
    LEFT JOIN forums_posts AS lrp ON l.PostID=lrp.ID
    LEFT JOIN forums_posts AS p ON s.ThreadID = p.ThreadID AND p.AddedTime > IFNULL(lrp.AddedTime,0) AND p.Flags & {$flags} = 0
    LEFT JOIN (
        SELECT MAX(fp.ID) AS LastPostID, fp.ThreadID
          FROM forums_posts AS fp
          JOIN (
              SELECT MAX(AddedTime) AS AddedTime, s.ThreadID
                FROM forums_posts AS fp
                JOIN forums_subscriptions AS s ON fp.ThreadID=s.ThreadID
               WHERE s.UserID = :userid1
            GROUP BY s.ThreadID
          ) AS fp2 ON fp.AddedTime=fp2.AddedTime AND fp.ThreadID=fp2.ThreadID
      GROUP BY fp.ThreadID
    ) AS lp ON lp.ThreadID=s.ThreadID
    LEFT JOIN forums_threads AS t ON t.ID = s.ThreadID
    LEFT JOIN forums AS f ON f.ID = t.ForumID
    WHERE s.UserID = :userid2";
$sql .= '
    AND ((f.MinClassRead <= :userclass';
$sql .= (!empty($permittedVars) ? " OR f.ID IN ({$permittedVars}))" : ")");
$sql .= (!empty($restrictedVars) ? " AND f.ID NOT IN ({$restrictedVars}))" : ")");

if ($showUnread) {
    $sql .= '
    AND p.AuthorID != :userid3
    AND l.PostID < lp.LastPostID';
    $params[':userid3'] = $user->ID;
}
$sql .= '
    GROUP BY t.ID
    ORDER BY lp.LastPostID DESC
    LIMIT '.$limit;

$posts = $master->db->rawQuery($sql, $params)->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_COLUMN);
$results = $master->db->foundRows();

show_header('Subscribed Forum Threads', 'subscriptions,bbcode,jquery');
$params = [
    'posts'         => $posts,
    'showUnread'    => $showUnread,
    'showCollapsed' => $showCollapsed,
    'page'          => $page,
    'pageSize'      => $perPage,
    'results'       => $results,
];
echo $master->render->template('@Legacy/userhistory/subscriptions.html.twig', $params);
show_footer();
