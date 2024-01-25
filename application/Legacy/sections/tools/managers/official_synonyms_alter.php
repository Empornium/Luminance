<?php
enforce_login();
authorize();

if (!check_perms('admin_manage_tags')) {
    error(403);
}
include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');

$Message = '';

// ======================================  del synonym

if (isset($_POST['delsynonyms'])) {

    if (isset($_POST['oldsyns'])) {
        $OldSynonyms = $_POST['oldsyns'];
        $DeleteCache = [];
        foreach ($OldSynonyms AS $OldSynID) {
            if (!is_integer_string($OldSynID)) {
                error(403);
            }
            $SynName = $master->db->rawQuery("SELECT Synonym FROM tags_synonyms WHERE ID = ?", [$OldSynID])->fetchColumn();
            if ($SynName)
                $DeleteCache[] = $SynName;
        }
        $inQuery = implode(', ', array_fill(0, count($OldSynonyms), '?'));
        $master->db->rawQuery("DELETE FROM tags_synonyms WHERE ID IN ({$inQuery})", $OldSynonyms);
        $master->cache->deleteValue('all_synonyms');
        $Message .= "Deleted synonyms: " . implode(', ', $DeleteCache);
        $Result = 1;
    }
}

// ======================================  convert/add tag to/as synonym

if (isset($_POST['tagtosynonym'])) {

    $ParentTagID = (int) $_POST['parenttagid'];
    if ($ParentTagID) {
        $ParentTagName = $master->db->rawQuery("SELECT Name FROM tags WHERE ID = ?", [$ParentTagID])->fetchColumn();
    }

    if (isset($_POST['multi'])) {
        $anchor = "#convertbox";
        $TagsID = explode(",", $_POST['multiID']) ;
        foreach ($TagsID AS $TagID) {
            if (!is_integer_string($TagID)) error(0);
        }
    } else {
        $TagsID = [(int) $_POST['movetagid']];
    }

    foreach ($TagsID as $TagID) {
        $TagID = (int) $TagID;
        if ($TagID) {
            list($TagName, $NumSynonyms) = $master->db->rawQuery("SELECT Name, Count(ts.ID)
                          FROM tags AS t
                     LEFT JOIN tags_synonyms AS ts ON ts.TagID=t.ID
                         WHERE t.ID = ?
                      GROUP BY t.ID",
                [$TagID]
            )->fetch(\PDO::FETCH_NUM);
            if ($NumSynonyms>0) {
                $Message .= "Cannot remove tags from official list that have synonyms: $TagName\n";
                $TagName = '';
            }
        }

        if ($TagName && $ParentTagName) {

            // check this synonym is not already in syn table
            $SynID = $master->db->rawQuery("SELECT ID FROM tags_synonyms WHERE Synonym LIKE ?", [$TagName])->fetchColumn();

            if ($SynID) {
                $Message .= "$TagName already exists as a synonym for " . get_tag_synonym($TagName);
                $Result = 0;
            } else {

                $master->db->rawQuery("INSERT INTO tags_synonyms (Synonym, TagID, UserID)
                                                     VALUES (?, ?, ?)",
                    [$TagName, $ParentTagID, $activeUser['ID']]
                );
                $master->cache->deleteValue('all_synonyms');
                $Result = 1;
                // if we are just adding a tag as a synonym and not converting there is nothing more to do
                if (isset($_POST['converttag'])) {
                    // convert a synonym to a tag properly
                    if (!check_perms('admin_convert_tags')) {
                        $Message .= "(You do not have permission to convert an exisiting tag) Added tag $TagName as synonym for $ParentTagName";
                    } else {
                        // 'convert refrences to the original tag to parenttag and cleanup db
                        $GroupInfos = $master->db->rawQuery("SELECT tt.GroupID, tt.PositiveVotes, tt.NegativeVotes, Count(tt2.TagID) AS Count
                                                      FROM torrents_tags AS tt
                                                 LEFT JOIN torrents_tags AS tt2 ON tt2.GroupID = tt.GroupID
                                                            AND tt2.TagID = ?
                                                     WHERE tt.TagID = ?
                                                  GROUP BY tt.GroupID",
                            [$ParentTagID, $TagID]
                        )->fetchAll(\PDO::FETCH_NUM);

                        $NumAffectedTorrents = count($GroupInfos);
                        $NumChangedFilelists = 0;
                        if ($NumAffectedTorrents > 0) {
                            $SQL='';
                            $params = [];
                            $Div = ''; $Div2 = '';
                            $MsgGroups = "torrents ";
                            foreach ($GroupInfos as $Group) {
                                list($GroupID, $PVotes, $NVotes, $Count) = $Group;
                                if ($Count==0) { // only insert parenttag into groups where not already present
                                    $SQL .= "{$Div} (?, ?, ?, ?, ?)";
                                    $params = array_merge($params, [$ParentTagID, $GroupID, $PVotes, $NVotes, $activeUser['ID']]);
                                    $Div = ', ';
                                    $NumChangedFilelists++;
                                }
                                $MsgGroups .= "{$Div2}{$GroupID}";
                                $Div2 = ', ';
                            }

                            // update torrents_tags with entries for parentTagID
                            if ($SQL !='') {
                                $SQL = "INSERT IGNORE INTO torrents_tags
                                                  (TagID, GroupID, PositiveVotes, NegativeVotes, UserID) VALUES {$SQL}";
                                $master->db->rawQuery($SQL, $params);
                            }
                            // update the Uses where parenttag has been added as a replacement for tag
                            if ($NumChangedFilelists>0)
                                $master->db->rawQuery("UPDATE tags SET Uses = (Uses + ?) WHERE ID = ?",
                                    [$NumChangedFilelists, $ParentTagID]
                                );

                            $master->db->rawQuery("DELETE FROM torrents_tags WHERE TagID = ?", [$TagID]);
                        }
                        //// remove old entries for tagID
                        $master->db->rawQuery("DELETE FROM tags WHERE ID = ?", [$TagID]);
                        // Delete tag cache entry
                        $master->cache->deleteValue('tag_id_'.$TagName);

                        foreach ($GroupInfos as $Group) {
                            update_hash($Group[0]);
                        }
                        $Message .= "Converted tag $TagName to synonym for $ParentTagName. ";
                        // probably we should log this action in some way
                        write_log("Tag $TagName converted to synonym for tag $ParentTagName, $NumAffectedTorrents tag-torrent links updated $MsgGroups by " . $activeUser['Username']);
                    }
                } else {
                    $Message .= "Added tag $TagName as synonym for $ParentTagName";
                }
            }
        }
    }
}

// ======================================  add synonym

if (isset($_POST['addsynonym'])) {

    $ParentTagID = (int) $_POST['parenttagid'];

    if (isset($_POST['newsynname']) && $ParentTagID) {

        $TagName = sanitize_tag(trim($_POST['newsynname'], '.'));
        if ($TagName != '') {
            // check this synonym is not already in syn table or tag table
            $SynID = $master->db->rawQuery("SELECT ID FROM tags_synonyms WHERE Synonym LIKE ?", [$TagName])->fetchColumn();
            if ($SynID) {
                $Message .= "$TagName already exists as a synonym for " . get_tag_synonym($TagName);
                $Result = 0;
            } else {
                $SynID = $master->db->rawQuery("SELECT ID FROM tags WHERE Name LIKE ?", [$TagName])->fetchColumn();
                if ($SynID) {
                    $Message .= "Cannot add $TagName as a synonym - already exists as a tag.";
                    $Result = 0;
                } else { // synonym doesn't exist yet - create
                    $master->db->rawQuery("INSERT INTO tags_synonyms (Synonym, TagID, UserID)
                                                        VALUES (?, ?, ?)",
                        [$TagName, $ParentTagID, $activeUser['ID']]
                    );
                    $master->cache->deleteValue('all_synonyms');
                    $Result = 1;
                    $Message .= "$TagName created as a synonym for " . get_tag_synonym($TagName);
                }
            }
        }
    }
}

if ($Message != '') {
    header("Location: tools.php?action=official_synonyms&rst=$Result&msg=" . htmlentities($Message) .$anchor);
} else {
    header('Location: tools.php?action=official_synonyms'.$anchor);
}
