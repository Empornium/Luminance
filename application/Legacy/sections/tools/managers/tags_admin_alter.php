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

    $DB->query("SELECT Name, Count(ts.ID)
                          FROM tags AS t
                     LEFT JOIN tag_synomyns AS ts ON ts.TagID=t.ID
                         WHERE t.ID = $TagID
                      GROUP BY t.ID ");
    list($TagName, $NumSynomyns) = $DB->next_record();
    if ($NumSynomyns>0) {
        $Message .= "Cannot delete a tag that has synonyms: $TagName\n";
        $TagName = '';
    }

    if ($TagName) {
         // get all the torrents that have this tag
        $DB->query("SELECT GroupID FROM torrents_tags WHERE TagID='$TagID'");
        $GroupIDs = $DB->collect('GroupID');

        // remove old entries for tagID
        $DB->query("DELETE FROM torrents_tags_votes WHERE TagID = '$TagID'");
        $DB->query("DELETE FROM torrents_tags WHERE TagID = '$TagID'");
        $DB->query("DELETE FROM tags WHERE ID = '$TagID'");

        // Delete tag cache entry
        $Cache->delete_value('tag_id_'.$TagName);

        foreach ($GroupIDs as $GID) {
            update_hash($GID); // update tags sphinx delta
        }


         // get all the requests that have this tag
        $DB->query("SELECT RequestID FROM requests_tags WHERE TagID='$TagID'");
        $RequestIDs = $DB->collect('RequestID');

        // remove old entries for tagID
        $DB->query("DELETE FROM requests_tags WHERE TagID = '$TagID'");

        foreach ($RequestIDs as $RID) {
            // update_sphinx_requests($RID); // update sphinx requests delta
            $Cache->delete_value('request_'.$RID);
        }

        $Message .= "Permanently deleted tag $TagName.";
        $count=count($GroupIDs);
        if ($count > 0) $Message .= " $count torrent taglists updated. ";

        //  log action
        $AllGroupIDs = implode(',', $GroupIDs);
        write_log("Tag $TagName deleted permanently, $count tag-torrent links updated torrents $AllGroupIDs by " . $LoggedUser['Username']);
        $Result = 1;
    }
}

if (isset($_POST['recountall'])) {

    // this may take a while...

    // delete any orphaned torrent-tag links where the torrent no longer exists
    $DB->query("DELETE t
                  FROM torrents_tags AS t
             LEFT JOIN torrents_group AS tg ON t.GroupID=tg.ID
                 WHERE tg.ID is NULL");
    $numtt =$DB->affected_rows();
    // delete any orphaned torrent-tag-vote links where the torrent no longer exists
    $DB->query("DELETE tv
                  FROM torrents_tags_votes AS tv
             LEFT JOIN torrents_group AS tg ON tv.GroupID=tg.ID
                 WHERE tg.ID is NULL");
    $numtv =$DB->affected_rows();

    // update tag uses per tag
    $DB->query("UPDATE tags AS t LEFT JOIN
                (
                    SELECT TagID, COUNT(GroupID) AS TagCount
                      FROM torrents_tags
                     GROUP BY TagID
                ) AS c
                ON t.ID = c.TagID
                SET t.Uses=c.TagCount ");

    $numt =$DB->affected_rows();
    $Result = $numtt >= 0 || $numtv >= 0 || $numt >= 0? 1 :0; // just check no sql errors returned - 0 results are not errors
    $Message .= "Recounted total uses for $numt tags. Removed orphans: $numtt tor-tag links, $numtv tag-votes" ;

}

if ($Message != '') {
    header("Location: tools.php?action=tags_admin&rst=$Result&msg=" . htmlentities($Message) .$anchor);
} else {
    header('Location: tools.php?action=tags_admin'.$anchor);
}
