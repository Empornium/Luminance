<?php
enforce_login();
authorize();

if (!check_perms('admin_manage_tags')) {
    error(403);
}
include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');

$Message = '';
if (isset($_POST['doit'])) {

    if (isset($_POST['oldtags'])) {
        $OldTagIDs = $_POST['oldtags'];
        $ChangeNames = [];
        $NotChangeNames = [];
        $ChangeIDs = [];

        foreach ($OldTagIDs AS $OldTagID) {
            if (!is_integer_string($OldTagID)) {
                error(403);
            }
            $nextRecord = $master->db->rawQuery("SELECT Name, Count(ts.ID)
                          FROM tags AS t
                     LEFT JOIN tags_synonyms AS ts ON ts.TagID=t.ID
                         WHERE t.ID = ?
                      GROUP BY t.ID
                          ",
                [$OldTagID]
            )->fetch(\PDO::FETCH_NUM);
            list($SynName, $NumSynonyms) = $nextRecord;
            if ($NumSynonyms==0) {
                $ChangeIDs[] = (int) $OldTagID;
                $ChangeNames[] = $SynName;
            } else
                $NotChangeNames[] = $SynName;
        }
        if (count($NotChangeNames)>0) {
            $Message .= "Cannot remove tags from official list that have synonyms: ". implode(', ', $NotChangeNames).". ";
            $Result = 0;
        }
        if (count($ChangeIDs)>0) {
            $inQuery = implode(', ', array_fill(0, count($ChangeIDs), '?'));
            $master->db->rawQuery("UPDATE tags SET TagType = 'other' WHERE ID IN ({$inQuery})", $ChangeIDs);
            $Message .= "Removed tags from official list: ". implode(', ', $ChangeNames);
            $Result = 1;
        }
    }

    if ($_POST['newtag']) {
        $Tag = trim($_POST['newtag'], '.'); // trim dots from the beginning and end
        $Tag = sanitize_tag($Tag);
        $TagName = get_tag_synonym($Tag);

        if ($Tag != $TagName) // this was a synonym replacement
            $Message .= "$Tag = $TagName. ";

        $TagID = $master->db->rawQuery("SELECT t.ID FROM tags AS t WHERE t.Name LIKE ?", [$TagName])->fetchColumn();

        if ($TagID) {
            $master->db->rawQuery("UPDATE tags SET TagType = 'genre' WHERE ID = ?", [$TagID]);
        } else { // Tag doesn't exist yet - create tag
            $master->db->rawQuery("INSERT INTO tags (Name, UserID, TagType, Uses)
                VALUES (?, ?, 'genre', 0)",
                [$TagName, $activeUser['ID']]
            );
            $TagID = $master->db->lastInsertID();
            $Message .= "Created $TagName. ";
        }
        $Message .= "Added $TagName to official list.";
        $Result = 1;
    }
    $master->cache->deleteValue('genre_tags');
}

// ==============================  super delete ========================
// Disabled, takes *WAY* too long to complete
if (isset($_POST['deletetagperm']) && false) {

    if (!check_perms('admin_convert_tags')) error(403);

    $Result = 0;
    $TagID = (int) $_POST['permdeletetagid'];

    $nextRecord = $master->db->rawQuery("SELECT Name, Count(ts.ID)
                          FROM tags AS t
                     LEFT JOIN tags_synonyms AS ts ON ts.TagID=t.ID
                         WHERE t.ID = ?
                      GROUP BY t.ID ",
        [$TagID]
    )->fetch(\PDO::FETCH_NUM);
    list($TagName, $NumSynonyms) = $nextRecord;
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

    if (!check_perms('admin_convert_tags'))  error(403);

    // this may take a while...

    // delete any orphaned torrent-tag links where the torrent no longer exists
    $numtt = $master->db->rawQuery(
        "DELETE t
           FROM torrents_tags AS t
      LEFT JOIN torrents_group AS tg ON t.GroupID=tg.ID
          WHERE tg.ID is NULL"
    );
    $numtt = $numtt->foundRows();
    // delete any orphaned torrent-tag-vote links where the torrent no longer exists
    $numtv = $master->db->rawQuery(
        "DELETE tv
           FROM torrents_tags_votes AS tv
      LEFT JOIN torrents_group AS tg ON tv.GroupID=tg.ID
          WHERE tg.ID is NULL"
    );
    $numtv = $numtv->foundRows();

    // update tag uses per tag
    $master->db->rawQuery("UPDATE tags AS t LEFT JOIN
                (
                    SELECT TagID, COUNT(GroupID) AS TagCount
                      FROM torrents_tags
                     GROUP BY TagID
                ) AS c
                ON t.ID = c.TagID
                SET t.Uses=c.TagCount ");

    $numt = $master->db->foundRows();
    $Result = $numtt >= 0 && $numtv >= 0 && $numt >= 0? 1 :0; // just check no sql errors returned - 0 results are not errors
    $Message .= "Recounted total uses for $numt tags. Removed orphans: $numtt tor-tag links, $numtv tag-votes" ;

}

if ($Message != '') {
    header("Location: tools.php?action=official_tags&rst=$Result&msg=" . htmlentities($Message) .$anchor);
} else {
    header('Location: tools.php?action=official_tags'.$anchor);
}
