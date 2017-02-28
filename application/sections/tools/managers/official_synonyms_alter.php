<?php
enforce_login();
authorize();

if (!check_perms('site_manage_tags')) {
    error(403);
}
include(SERVER_ROOT . '/sections/torrents/functions.php');

$Message = '';

// ======================================  del synomyn

if (isset($_POST['delsynomyns'])) {

    if (isset($_POST['oldsyns'])) {
        $OldSynomyns = $_POST['oldsyns'];
        $DeleteCache = array();
        foreach ($OldSynomyns AS $OldSynID) {
            if (!is_number($OldSynID)) {
                error(403);
            }
            $DB->query("SELECT Synomyn FROM tag_synomyns WHERE ID = $OldSynID");
            list($SynName) = $DB->next_record();
            if ($SynName)
                $DeleteCache[] = $SynName;
        }
        $OldSynomyns = implode(', ', $OldSynomyns);
        $DB->query("DELETE FROM tag_synomyns WHERE ID IN ($OldSynomyns)");
        $Cache->delete_value('all_synomyns');
        $Message .= "Deleted synonyms: " . implode(', ', $DeleteCache);
        $Result = 1;
    }
}

// ======================================  convert/add tag to/as synomyn

if (isset($_POST['tagtosynomyn'])) {

    $ParentTagID = (int) $_POST['parenttagid'];
    if ($ParentTagID) {
        $DB->query("SELECT Name FROM tags WHERE ID=$ParentTagID");
        list($ParentTagName) = $DB->next_record();
    }

    if (isset($_POST['multi'])) {
        $anchor = "#convertbox";
        $TagsID = explode(",", $_POST['multiID']) ;
        foreach ($TagsID AS $TagID) {
            if (!is_number($TagID)) error(0);
        }
    } else {
        $TagsID = array( (int) $_POST['movetagid'] );
    }

    foreach ($TagsID as $TagID) {
        $TagID = (int) $TagID;
        if ($TagID) {
            $DB->query("SELECT Name, Count(ts.ID)
                          FROM tags AS t
                     LEFT JOIN tag_synomyns AS ts ON ts.TagID=t.ID
                         WHERE t.ID = $TagID
                      GROUP BY t.ID ");
            list($TagName, $NumSynomyns) = $DB->next_record();
            if ($NumSynomyns>0) {
                $Message .= "Cannot remove tags from official list that have synonyms: $TagName\n";
                $TagName = '';
            }
        }

        if ($TagName && $ParentTagName) {

            // check this synonym is not already in syn table
            $DB->query("SELECT ID FROM tag_synomyns WHERE Synomyn LIKE '" . $TagName . "'");
            list($SynID) = $DB->next_record();

            if ($SynID) {
                $Message .= "$TagName already exists as a synonym for " . get_tag_synonym($TagName);
                $Result = 0;
            } else {

                $DB->query("INSERT INTO tag_synomyns (Synomyn, TagID, UserID)
                                                     VALUES ('" . $TagName . "', " . $ParentTagID . ", " . $LoggedUser['ID'] . " )");
                $Cache->delete_value('all_synomyns');
                $Result = 1;
                // if we are just adding a tag as a synomyn and not converting there is nothing more to do
                if (isset($_POST['converttag'])) {
                    // convert a synomyn to a tag properly
                    if (!check_perms('site_convert_tags')) {
                        $Message .= "(You do not have permission to convert an exisiting tag) Added tag $TagName as synonym for $ParentTagName";
                    } else {
                        // 'convert refrences to the original tag to parenttag and cleanup db
                        $DB->query("SELECT tt.GroupID, tt.PositiveVotes, tt.NegativeVotes, Count(tt2.TagID) AS Count
                                                      FROM torrents_tags AS tt
                                                 LEFT JOIN torrents_tags AS tt2 ON tt2.GroupID=tt.GroupID
                                                            AND tt2.TagID=$ParentTagID
                                                     WHERE tt.TagID=$TagID
                                                  GROUP BY tt.GroupID");

                        $GroupInfos = $DB->to_array(false, MYSQLI_BOTH);

                        $NumAffectedTorrents = count($GroupInfos);
                        $NumChangedFilelists = 0;
                        if ($NumAffectedTorrents > 0) {
                            $SQL='';
                            $Div = ''; $Div2 = '';
                            $MsgGroups = "torrents ";
                            foreach ($GroupInfos as $Group) {
                                list($GroupID, $PVotes, $NVotes, $Count) = $Group;
                                if ($Count==0) { // only insert parenttag into groups where not already present
                                    $SQL .= "$Div ('$ParentTagID', '$GroupID', '$PVotes', '$NVotes', '{$LoggedUser['ID']}')";
                                    $Div = ',';
                                    $NumChangedFilelists++;
                                }
                                $MsgGroups .= "$Div2$GroupID";
                                $Div2 = ',';
                            }

                            // update torrents_tags with entries for parentTagID
                            if ($SQL !='') {
                                $SQL = "INSERT IGNORE INTO torrents_tags
                                                  (TagID, GroupID, PositiveVotes, NegativeVotes, UserID) VALUES $SQL";
                                $DB->query($SQL);
                            }
                            // update the Uses where parenttag has been added as a replacement for tag
                            if($NumChangedFilelists>0)
                                $DB->query("UPDATE tags SET Uses=(Uses+$NumChangedFilelists) WHERE ID='$ParentTagID'");

                            $DB->query("DELETE FROM torrents_tags WHERE TagID = '$TagID'");
                        }
                        //// remove old entries for tagID
                        $DB->query("DELETE FROM tags WHERE ID = '$TagID'");
                        // Delete tag cache entry
                        $Cache->delete_value('tag_id_'.$TagName);

                        foreach ($GroupInfos as $Group) {
                            update_hash($Group[0]);
                        }
                        $Message .= "Converted tag $TagName to synonym for $ParentTagName. ";
                        // probably we should log this action in some way
                        write_log("Tag $TagName converted to synonym for tag $ParentTagName, $NumAffectedTorrents tag-torrent links updated $MsgGroups by " . $LoggedUser['Username']);
                    }
                } else {
                    $Message .= "Added tag $TagName as synonym for $ParentTagName";
                }
            }
        }
    }
}

// ======================================  add synomyn

if (isset($_POST['addsynomyn'])) {

    $ParentTagID = (int) $_POST['parenttagid'];

    if (isset($_POST['newsynname']) && $ParentTagID) {

        $TagName = sanitize_tag(trim($_POST['newsynname'],'.'));
        if ($TagName != '') {
            // check this synonym is not already in syn table or tag table
            $DB->query("SELECT ID FROM tag_synomyns WHERE Synomyn LIKE '" . $TagName . "'");
            list($SynID) = $DB->next_record();
            if ($SynID) {
                $Message .= "$TagName already exists as a synonym for " . get_tag_synonym($TagName);
                $Result = 0;
            } else {
                $DB->query("SELECT ID FROM tags WHERE Name LIKE '" . $TagName . "'");
                list($SynID) = $DB->next_record();
                if ($SynID) {
                    $Message .= "Cannot add $TagName as a synonym - already exists as a tag.";
                    $Result = 0;
                } else { // synonym doesn't exist yet - create
                    $DB->query("INSERT INTO tag_synomyns (Synomyn, TagID, UserID)
                                                        VALUES ('" . $TagName . "', " . $ParentTagID . ", " . $LoggedUser['ID'] . " )");
                    $Cache->delete_value('all_synomyns');
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
