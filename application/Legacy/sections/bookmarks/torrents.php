<?php

if (!empty($_GET['userid'])) {
    if (!check_perms('users_override_paranoia')) error(403);
    $userID = $_GET['userid'];
    if (!is_integer_string($userID)) error(404);
} else {
    $userID = $activeUser['ID'];
}

$user = $master->repos->users->load($userID);

$orders = ['Added', 'Title', 'Size', 'UploadDate', 'Snatched', 'Seeders', 'Leechers'];
$ways = ['desc'=>'Descending', 'asc'=>'Ascending'];

if (!empty($_GET['order_way']) && array_key_exists($_GET['order_way'], $ways)) {
    $orderWay = $_GET['order_way'];
} else {
    $orderWay = 'desc';
}

if (!empty($_GET['order_by']) && in_array($_GET['order_by'], $orders)) {
    $orderBy = $_GET['order_by'];
} else {
    $orderBy = 'Added';
}

$info = $master->cache->getValue('bookmarks_info_'.$user->ID);
if (empty($info)) {
    $info['tags'] = $master->db->rawQuery(
        "SELECT t.Name AS name, COUNT(tt.GroupID) count
           FROM bookmarks_torrents AS bt
           JOIN torrents_tags AS tt ON bt.GroupID=tt.GroupID
           JOIN tags AS t ON t.ID=tt.TagID
          WHERE bt.UserID=?
       GROUP BY tt.TagID
       ORDER BY count DESC
          LIMIT 5", [$user->ID])->fetchAll(\PDO::FETCH_ASSOC);

    $info['size'] = $master->db->rawQuery(
        "SELECT SUM(t.Size) AS Size
           FROM bookmarks_torrents AS bt
           JOIN torrents AS t ON bt.GroupID=t.GroupID
          WHERE bt.UserID=?", [$user->ID])->fetchColumn();

    $info['count'] = $master->db->rawQuery(
        "SELECT COUNT(*)
           FROM bookmarks_torrents AS bt
           JOIN torrents_group AS tg ON tg.ID=bt.GroupID
          WHERE bt.UserID=?", [$user->ID])->fetchColumn();
    $master->cache->cacheValue('bookmarks_info_'.$user->ID, $info);
}

if (isset($activeUser['TorrentsPerPage'])) {
    $torrentsPerPage = $activeUser['TorrentsPerPage'];
} else {
    $torrentsPerPage = TORRENTS_PER_PAGE;
}

list($page, $limit) = page_limit($torrentsPerPage);
$pages=get_pages($page, $info['count'], $torrentsPerPage, 8, '#torrent_table');

// Build the data for the collage and the torrent list, extra columns required for sorting
$bookmarks = $master->db->rawQuery(
    "SELECT bt.GroupID,
            bt.Time as Added,
            tg.Time as UploadDate,
            tg.Name as Title,
            t.Snatched as Snatched,
            t.Seeders as Seeders,
            t.Leechers as Leechers,
            t.Size as Size
       FROM bookmarks_torrents AS bt
       JOIN torrents_group AS tg ON tg.ID=bt.GroupID
       JOIN torrents AS t ON t.GroupID=tg.ID
      WHERE bt.UserID=?
   ORDER BY {$orderBy} {$orderWay}
      LIMIT {$limit}",
     [$user->ID]
)->fetchAll(\PDO::FETCH_ASSOC|PDO::FETCH_UNIQUE);

$groupIDs = array_keys($bookmarks);

if (count($groupIDs)>0) {
    $groups = get_groups($groupIDs, true, true, true);
    $groups = $groups['matches'];
} else {
    $groups = [];
}

foreach ($groups as $index => &$group) {
    $torrent          = end($group['Torrents']);
    $username         = anon_username($torrent['Username'], $torrent['Anonymous']);
    $group['Image']   = fapping_preview($group['Image'], 300);
    $group['review']  = get_last_review($index);
    $group['icons']   = torrent_icons($torrent, $torrent['ID'], $group['review'], true);
    $group['mfd']     = $group['review']['Status'] == 'Warned' || $group['review']['Status'] == 'Pending';
    $group['added']   = $bookmarks[$index]['Added'];
    $group['overlay'] = get_overlay_html($group['Name'], $username, $group['Image'], $torrent['Seeders'], $torrent['Leechers'], $torrent['Size'], $torrent['Snatched']);
}

show_header('Torrent Bookmarks', 'collage, overlib');
$params = [
    'info'       => $info,
    'categories' => $newCategories,
    'groups'     => $groups,
    'pages'      => $pages,
    'userID'     => $user->ID,
];
echo $master->render->template('@Legacy/bookmarks/torrents.html.twig', $params);
show_footer();
