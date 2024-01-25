<?php
if (!check_perms('admin_manage_tags')) error(403,true);
authorize(true);

if ($_GET['checktag']) {
    $tagdetails = $master->db->rawQuery(
        "SELECT t.ID,
                t.Name,
                t.Uses,
                Count(ts.ID)
           FROM tags AS t
      LEFT JOIN tags_synonyms AS ts ON ts.TagID = t.ID
          WHERE t.Name = ?
       GROUP BY t.ID",
        [$_GET['checktag']]
    )->fetch(\PDO::FETCH_NUM);
}

if (!$tagdetails || !is_array($tagdetails)) {
    $tagdetails=[0, '', 0, 0];
}

echo json_encode($tagdetails);
