<?php
enforce_login();
authorize();

if (!check_perms('admin_convert_tags'))  error(403);

include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');

$Message = '';

// ==============================  super delete ========================

if (isset($_POST['deletetagperm'])) {

    $Result = 0;
    $TagID = (int) $_POST['permdeletetagid'];

    list($TagName, $NumSynonyms) = $master->db->rawQuery("SELECT Name, Count(ts.ID)
                          FROM tags AS t
                     LEFT JOIN tags_synonyms AS ts ON ts.TagID=t.ID
                         WHERE t.ID = ?
                      GROUP BY t.ID",
        [$TagID]
    )->fetch(\PDO::FETCH_NUM);
    if ($NumSynonyms>0) {
        $Message .= "Cannot delete a tag that has synonyms: $TagName\n";
        $TagName = '';
    }

    if ($TagName) {
         // get all the torrents that have this tag
        $GroupIDs = $master->db->rawQuery("SELECT GroupID FROM torrents_tags WHERE TagID = ?", [$TagID])->fetchAll(\PDO::FETCH_COLUMN);

        // remove old entries for tagID
        $master->db->rawQuery("DELETE FROM torrents_tags_votes WHERE TagID = ?", [$TagID]);
        $master->db->rawQuery("DELETE FROM torrents_tags       WHERE TagID = ?", [$TagID]);
        $master->db->rawQuery("DELETE FROM tags                WHERE ID = ?",    [$TagID]);

        // Delete tag cache entry
        $master->cache->deleteValue('tag_id_'.$TagName);

        foreach ($GroupIDs as $GID) {
            update_hash($GID); // update tags sphinx delta
        }


         // get all the requests that have this tag
        $RequestIDs = $master->db->rawQuery("SELECT RequestID FROM requests_tags WHERE TagID = ?", [$TagID])->fetchAll(\PDO::FETCH_COLUMN);

        // remove old entries for tagID
        $master->db->rawQuery("DELETE FROM requests_tags WHERE TagID = ?", [$TagID]);

        foreach ($RequestIDs as $RID) {
            // update_sphinx_requests($RID); // update sphinx requests delta
            $master->cache->deleteValue('request_'.$RID);
        }

        $Message .= "Permanently deleted tag $TagName.";
        $count=count($GroupIDs);
        if ($count > 0) $Message .= " $count torrent taglists updated. ";

        //  log action
        $AllGroupIDs = implode(', ', $GroupIDs);
        write_log("Tag $TagName deleted permanently, $count tag-torrent links updated torrents $AllGroupIDs by " . $activeUser['Username']);
        $Result = 1;
    }
}

if (isset($_POST['recountall'])) {

    // this may take a while...

    // delete any orphaned torrent-tag links where the torrent no longer exists
    $numtt = $master->db->rawQuery(
        "DELETE t
           FROM torrents_tags AS t
      LEFT JOIN torrents_group AS tg ON t.GroupID=tg.ID
          WHERE tg.ID is NULL"
    );
    $numtt = $numtt->rowCount();
    // delete any orphaned torrent-tag-vote links where the torrent no longer exists
    $numtv = $master->db->rawQuery(
        "DELETE tv
           FROM torrents_tags_votes AS tv
      LEFT JOIN torrents_group AS tg ON tv.GroupID=tg.ID
          WHERE tg.ID is NULL"
    );
    $numtv = $numtv->rowCount();

    // update tag uses per tag
    $numt = $master->db->rawQuery("UPDATE tags AS t LEFT JOIN
                (
                    SELECT TagID, COUNT(GroupID) AS TagCount
                      FROM torrents_tags
                     GROUP BY TagID
                ) AS c
                ON t.ID = c.TagID
                SET t.Uses=c.TagCount "
    )->rowCount();

    $Result = $numtt >= 0 || $numtv >= 0 || $numt >= 0? 1 :0; // just check no sql errors returned - 0 results are not errors
    $Message .= "Recounted total uses for $numt tags. Removed orphans: $numtt tor-tag links, $numtv tag-votes" ;

}

if ($Message != '') {
    header("Location: tools.php?action=tags_admin&rst=$Result&msg=" . htmlentities($Message));
} else {
    header('Location: tools.php?action=tags_admin');
}
