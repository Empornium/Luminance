<?php
header('Content-Type: application/json; charset=utf-8');

$term = trim($_GET['name']);
$term = preg_replace("/^[!&\|\-\+]/i", '', $term);
$data = $master->cache->get_value('tag_search_'.$term);
if ($data===false || !isset($data['ver']) || $data['ver'] < 2) {

    // sort column weights results that start with the search term as *2 closer to the top
    $tags = $master->db->raw_query("(SELECT Name, Uses, (Uses*2) AS Sort FROM tags WHERE Name LIKE :searchterm1 ORDER BY Uses DESC LIMIT 25)
                                     UNION
                                    (SELECT Name, Uses, Uses AS Sort FROM tags WHERE Name NOT LIKE :searchterm2 AND Name LIKE :searchterm3 ORDER BY Uses DESC LIMIT 40)
                                     ORDER BY Sort DESC
                                     LIMIT 40",
                                     [':searchterm1' => "$term%",
                                      ':searchterm2' => "$term%",
                                      ':searchterm3' => "%$term%"])->fetchAll(\PDO::FETCH_ASSOC);

    $data = [];
    foreach($tags as $tag) {
        $data[] = array($tag['Name'], "$tag[Name] &nbsp;<span class=\"num\">({$tag[Uses]})</span>");
    }
    $data = array( 'ver'=>2, 'd'=>$data );
    $master->cache->cache_value('tag_search_'.$term, $data, 3600*6);
}

echo json_encode(array($term, $data['d']));
