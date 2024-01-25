<?php

if (!check_perms('users_fls')) {
    error(403);
}

$results = $master->db->rawQuery(
    'SELECT COUNT(*)
       FROM torrents_comments'
)->fetchColumn();
$pageSize = $master->request->user->options('PostsPerPage', $master->settings->pagination->torrent_comments);
list($page, $limit) = page_limit($pageSize);

# We could implement catalog caching for the comment IDs, but it's not worth it
$comments = $master->repos->torrentcomments->find('', [], 'AddedTime DESC', $limit);

$params = compact('comments', 'page', 'results', 'pageSize');

// Start printing
show_header('All torrent comments', 'comments,bbcode,jquery');
echo $master->render->template('@Legacy/torrents/all_comments.html.twig', $params);
show_footer();
