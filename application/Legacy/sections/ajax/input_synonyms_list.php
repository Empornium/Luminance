<?php
if (!check_perms('admin_manage_tags')) error(403, true);

function get_rejected_message4($Tag, $Item, $Reason) {
    $Result = "<span class=\"red\">[rejected]</span> '$Tag'";
    if ($Item!== $Tag) $Result .= "&nbsp; <--&nbsp; '$Item'";
    $Result .= "&nbsp; $Reason<br />";

    return $Result ;
}

function get_rejected_message3($Tag, $Item, $NumUses, $Reason) {
    $Result = "<span class=\"red\">[rejected]</span> '$Tag'";
    if ($Item!== $Tag) $Result .= "&nbsp; <--&nbsp; '$Item'";
    $Result .= "&nbsp; ($NumUses) $Reason<br />";

    return $Result ;
}

// ======================================

function Add_Synonyms($ParentTagItem, $TagsItems, &$Result) {
    global $master, $activeUser;

    if (!is_array($ParentTagItem)) return;

    list($ParentTagID, $ParentTagName, $pitem, $pNumUses) = $ParentTagItem;
    if ($ParentTagID>0) {
        $master->db->rawQuery(
            "UPDATE tags
                SET TagType = 'genre'
              WHERE ID = ?",
            [$ParentTagID]
        );
        $Result .= "Set parent tag: $ParentTagName (id=$ParentTagID)<br/>";
        write_log("Set parent tag $ParentTagName (id=$ParentTagID)");
    } else {
        $master->db->rawQuery(
            "INSERT INTO tags (Name, UserID, TagType, Uses)
                  VALUES (?, ?, 'genre', 0)",
            [$ParentTagName, $activeUser['ID']]
        );
        $ParentTagID = $master->db->lastInsertID();
        //$ParentTagItem[0] = $ParentTagID;
        $Result .= "Created parent tag: $ParentTagName (id=$ParentTagID)<br/>";
        write_log("Created parent tag $ParentTagName (id=$ParentTagID)");
    }
    // add synonyms
    foreach ($TagsItems as $TagItem) {
        list($TagID, $TagName, $item, $NumUses) = $TagItem;

        $master->db->rawQuery(
            "INSERT INTO tags_synonyms (Synonym, TagID, UserID)
                  VALUES (?, ?, ?)",
            [$TagName, $ParentTagID, $activeUser['ID']]
        );

        $Result .= "Created tag synonym $TagName for $ParentTagName<br/>";
        write_log("Created synonym $TagName for tag $ParentTagName by ".$activeUser['Username']);

        if ($TagID>0) {
            // 'convert references to the original tag to parenttag and cleanup db
            $GroupInfos = $master->db->rawQuery(
                "SELECT tt.GroupID,
                        tt.PositiveVotes,
                        tt.NegativeVotes,
                        Count(tt2.TagID) AS Count,
                        tt.UserID AS AdderID
                   FROM torrents_tags AS tt
              LEFT JOIN torrents_tags AS tt2 ON tt2.GroupID=tt.GroupID AND tt2.TagID = ?
                  WHERE tt.TagID = ?
               GROUP BY tt.GroupID",
                [$ParentTagID, $TagID]
            )->fetchAll(\PDO::FETCH_NUM);

            $NumAffectedTorrents = count($GroupInfos);
            $NumChangedFilelists = 0;
            $MsgGroups = '';
            if ($NumAffectedTorrents > 0) {

                $SQL = '';
                $params = [];
                $Div = '';
                $Div2 = '';
                $MsgGroups = "torrents ";
                foreach ($GroupInfos as $Group) {
                    list($GroupID, $PVotes, $NVotes, $Count, $AdderID) = $Group;
                    if ($Count == 0) { // only insert parenttag into groups where not already present
                        $SQL .= "{$Div} (?, ?, ?, ?, ?)";
                        $params = array_merge($params, [$ParentTagID, $GroupID, $PVotes, $NVotes, $AdderID]);
                        $Div = ',';
                        $NumChangedFilelists++;
                    }
                    $MsgGroups .= "{$Div2}{$GroupID}";
                    $Div2 = ',';
                }

                // update torrents_tags with entries for parentTagID
                if ($SQL != '') {
                    $SQL = "INSERT IGNORE INTO torrents_tags (TagID, GroupID, PositiveVotes, NegativeVotes, UserID) VALUES {$SQL}";
                    $master->db->rawQuery($SQL, $params);
                }
                // update the Uses where parenttag has been added as a replacement for tag
                if ($NumChangedFilelists > 0)
                    $master->db->rawQuery(
                        "UPDATE tags
                            SET Uses = (Uses + {$NumChangedFilelists})
                          WHERE ID = ?",
                        [$ParentTagID]
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
            $Result .= "Converted tag $TagName to synonym in $NumAffectedTorrents torrents, updated $MsgGroups<br/>";
            // probably we should log this action in some way
            write_log("Tag $TagName converted to synonym $TagName for parent tag $ParentTagName, $NumAffectedTorrents tag-torrent links updated $MsgGroups by " . $activeUser['Username']);
        }
    }
}

$ListInput = $_POST['taglist'];
$ListInput = str_replace(["\r\n", "\n\n\n", "\n\n", "\n"], ' ', $ListInput);
$ListInput = explode(' ', $ListInput);

if (!$ListInput) error("Nothing in list", true);

$Result = '';
$AllTags = [];
$TagInfos = [];
$ParentTag = '';
$numparents = 0;
$numtags = 0;

foreach ($ListInput as $item) {

    $StartingNewList = false;
    $Tag = trim($item);
    if (!$Tag)
        continue;

    if (substr($Tag, 0, 1) == '+') {
        $Tag = substr($Tag, 1);
        $StartingNewList = true;

        // if parenttag and tags are set then add them
        Add_Synonyms($ParentTag, $TagInfos, $Result);

        $TagInfos = [];
        $ParentTag = '';
    }

    $Tag = sanitize_tag($Tag);
    if (!$Tag) {
        $Result .= get_rejected_message4($Tag, $item, "(parses to nothing)");   // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' (parses to nothing)<br />";
        continue;
    }

    // check this tag is not in syn table
    $ExistingParent = $master->db->rawQuery(
        "SELECT t.Name
           FROM tags_synonyms AS ts
           JOIN tags AS t ON ts.TagID=t.ID
          WHERE ts.Synonym = ?",
        [$Tag]
    )->fetchColumn();
    if ($ExistingParent) {
        $Result .= get_rejected_message4($Tag, $item, "(already exists as a synonym for '$ExistingParent')");   // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' (already exists as a synonym for '$ExistingParent')<br />";
        continue;
    }

    $nextRecord = $master->db->rawQuery(
        "SELECT t.ID,
                t.Uses,
                t.TagType,
                Count(ts.ID)
           FROM tags AS t
      LEFT JOIN tags_synonyms AS ts ON ts.TagID=t.ID
          WHERE t.Name = ?
       GROUP BY t.ID",
        [$Tag]
    )->fetch(\PDO::FETCH_NUM);
    if ($master->db->foundRows() > 0) {
        list($TagID, $NumUses, $TagType, $NumSyns) = $nextRecord;
    } else {
        $TagID=0;
        $TagType='';
        $NumUses=0;
        $NumSyns=0;
    }

    if (!$NumUses) $NumUses = '0';

    if (in_array($Tag, $AllTags)) {   // array_key_exists($Tag, $Tags)) {
        $Result .= get_rejected_message3($Tag, $item, $NumUses, "DUPLICATE IN LIST!"); // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' ($NumUses) DUPLICATE IN LIST!<br />";
        continue;
    }

    if ($StartingNewList) {

        // set new parent tag
        $AllTags[] = $Tag;
        $ParentTag = [$TagID, $Tag, substr($item, 1), $NumUses];
        $numparents++;
        //$Tags = [];
    } else {

        // check this synonym to be is not an official tag
        if ($TagType == 'genre' || $NumSyns > 0) {
            $Result .= get_rejected_message3($Tag, $item, $NumUses, "is a $TagType tag - $NumSyns synonyms"); // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' ($NumUses) is an official tag - $NumSyns synonyms<br />";
            continue;
        }

        if (!is_array($ParentTag)) {
            $Result .= get_rejected_message3($Tag, $item, $NumUses, "NO PARENT TAG!"); // "<span class=\"red\">[rejected]</span> '$Tag'  <--  '$item' ($NumUses) NO PARENT TAG!<br />";
            continue;
        }


        $numtags++;
        $AllTags[] = $Tag;
        $TagInfos[] = [$TagID, $Tag, $item, $NumUses];
    }
}

Add_Synonyms($ParentTag, $TagInfos, $Result);

$master->cache->deleteValue('all_synonyms');

$Result = "<div class=\"box pad\"><span style=\"font-weight:bold\">Inputted: $numparents parents, $numtags synonyms</span></div>$Result";
echo json_encode([$numtags, $Result]);
