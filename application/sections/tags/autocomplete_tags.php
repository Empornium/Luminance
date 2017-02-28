<?php
header('Content-Type: application/json; charset=utf-8');

$term = trim($_GET['name']);

$Data = $Cache->get_value('tag_search_'.$term);
if ($Data===false || !isset($Data['ver']) || $Data['ver'] < 1) {
    $esc_term = db_string($term);
    // sort column weights results that start with the search term as *2 closer to the top
    $DB->query("(SELECT Name, Uses, (Uses*2) AS Sort FROM tags WHERE Name LIKE '$esc_term%' ORDER BY Uses DESC LIMIT 25)
                UNION
                (SELECT Name, Uses, Uses AS Sort FROM tags WHERE Name NOT LIKE '$esc_term%' AND Name LIKE '%$esc_term%' ORDER BY Uses DESC LIMIT 40)
                ORDER BY Sort DESC
                LIMIT 40;");

    $Data = array();
    while (list($tag, $num) = $DB->next_record(MYSQLI_NUM)) {
        $Data[] = array($tag, "$tag &nbsp;<span class=\"num\">($num)</span>");
    }
    $Data = array( 'ver'=>1, 'd'=>$Data );
    $Cache->cache_value('tag_search_'.$term, $Data, 3600*24);
}

echo json_encode(array($term, $Data['d']));
