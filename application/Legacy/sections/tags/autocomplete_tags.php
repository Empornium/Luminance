<?php
header('Content-Type: application/json; charset=utf-8');

$term = trim($_GET['name']);
$term = preg_replace("/^[!&\|\-\+]/i", '', $term);
$data = $master->cache->getValue('tag_search_'.$term);
if ($data===false || !isset($data['ver']) || $data['ver'] < 2) {

    // the first five results have to start with the search term
    $tagsStart = $master->db->rawQuery(
        "SELECT Name,
                Uses
           FROM tags
          WHERE Name LIKE ?
       ORDER BY Uses DESC
          LIMIT 5",
        ["{$term}%"]
    )->fetchAll(\PDO::FETCH_ASSOC);

    // the rest just have to include the search term
    $tagsInclude = $master->db->rawQuery(
        "SELECT Name,
                Uses
           FROM tags
          WHERE Name LIKE ?
       ORDER BY Uses DESC
          LIMIT 40",
        ["%{$term}%"]
    )->fetchAll(\PDO::FETCH_ASSOC);

    // Merge results
    $tags = array_merge($tagsStart, $tagsInclude);

    // Remove duplicates
    $tags = array_column($tags, null, 'Name');

    // Limit overall results to 40
    $tags = array_slice($tags, 0, 40);

    $data = [];
    foreach ($tags as $tag) {
        $data[] = [$tag['Name'], "{$tag['Name']} &nbsp;<span class=\"num\">({$tag['Uses']})</span>"];
    }
    $data = ['ver'=>2, 'd'=>$data];
    $master->cache->cacheValue('tag_search_'.$term, $data, 3600*6);
}

echo json_encode([$term, $data['d']]);
