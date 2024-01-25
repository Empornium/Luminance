<?php
authorize();

header('Content-Type: application/json; charset=utf-8');

$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::TAGGING);

include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');

$userID = $activeUser['ID'];
$GroupID = $_POST['groupid'];

if (!is_integer_string($GroupID) || !$GroupID) {
    error(0, true);
}

$AuthorID = $master->db->rawQuery(
    "SELECT UserID
       FROM torrents
      WHERE GroupID = ?",
    [$GroupID]
)->fetchColumn();
$IsAuthor = $AuthorID == $userID;

if (!check_perms('site_add_tag') && !$IsAuthor) error(403,true);

$Tags = explode(' ', $_POST['tagname']);

$VoteValue = $IsAuthor ? 8: 4;
if (empty($activeUser['NotVoteUpTags'])) {
    $UserVote = check_perms('site_vote_tag_enhanced') ? ENHANCED_VOTE_POWER : 1;
    $VoteValue += $UserVote;
}

$Results = [];
$CheckedTags = [];
$AddedTags = [];
$AddedIDs = [];

foreach ($Tags as $Tag) {
    $Tag = trim($Tag, '.'); // trim dots from the beginning and end
    if (count($CheckedTags)>0 && !check_perms('site_add_multiple_tags')) {
        $Results[] = [0, "You cannot enter multiple tags."];
        break;
    }
    if ($Tag && !in_array($Tag, $CheckedTags)) {
        $CheckedTags[]=$Tag;

        $Tag = display_str(strtolower(trim($Tag)));

        if ( !check_tag_input($Tag)) {
            $Results[] = [0, "$Tag contains invalid characters."];
            continue;
        }

        $OrigTag = $Tag;
        $Tag = sanitize_tag($Tag);

        if ( !is_valid_tag($Tag)) {
            $Results[] = [0, "$OrigTag is not a valid tag."];
            continue;
        } elseif ($OrigTag!=$Tag) {
            $Results[] = [1, "$OrigTag --> $Tag"];
        }

        $TagName = get_tag_synonym($Tag, false);

        if (in_array($TagName, $AddedTags)) {
                if ($Tag != $TagName) { // this was a   replacement
                    $Results[] = [0, "$Tag --> $TagName : already added."];
                } else {
                    $Results[] = [0, "$TagName is already added."];
                }
            continue;
        }

        $TagID = $master->db->rawQuery(
            "SELECT ID
               FROM tags
              WHERE Name = ?",
            [$TagName]
        )->fetchColumn();
        if ($master->db->foundRows() === 0) {
            $master->db->rawQuery(
                "INSERT INTO tags (Name, UserID, Uses)
                      VALUES (?, ?, '0')",
                [$TagName, $userID]
            );
            $TagID = $master->db->lastInsertID();
        }

        if ($TagID) {

            $master->db->rawQuery(
                "SELECT TagID
                   FROM torrents_tags_votes
                  WHERE GroupID = ?
                    AND TagID = ?
                    AND UserID = ?",
                [$GroupID, $TagID, $userID]
            );
            if ($master->db->foundRows() != 0) { // User has already added/voted on this tag+torrent so dont count again
                if ($Tag != $TagName) { // this was a synonym replacement
                    $Results[] = [0, "$Tag --> $TagName : already added."];
                } else {
                    $Results[] = [0, "$TagName is already added."];
                }
                continue;
            }

            $AddedTags[] = $TagName;
            $AddedIDs[] = $TagID;

            if ($Tag != $TagName) // this was a synonym replacement
                $Results[] = [1, "Added $Tag --> $TagName"];
            else
                $Results[] = [1, "Added $TagName"];
       }
    }
}

$count = count($AddedIDs);
if ($count > 0) {

    $params = [];
    $valuesQuery = implode(', ', array_fill(0, $count, '(?, ?, ?, ?)'));
    foreach ($AddedIDs as $addedID) {
        $params = array_merge($params, [$addedID, $GroupID, $VoteValue, $userID]);
    }

    if (!empty($activeUser['NotVoteUpTags'])) {
        // add without voting up if already present
        $master->db->rawQuery(
            "INSERT IGNORE INTO torrents_tags (TagID, GroupID, PositiveVotes, UserID)
                         VALUES {$valuesQuery}",
            $params
        );
    } else {
        // add and vote up
        $master->db->rawQuery(
            "INSERT INTO torrents_tags (TagID, GroupID, PositiveVotes, UserID)
                  VALUES {$valuesQuery}
                      ON DUPLICATE KEY
                  UPDATE PositiveVotes = PositiveVotes + ?",
            array_merge($params, [$UserVote])
        );

        $valuesQuery = implode(', ', array_fill(0, $count, "(?, ?, ?, 'up')"));
        $params = [];
        foreach ($AddedIDs as $addedID) {
            $params = array_merge($params, [$GroupID, $addedID, $userID]);
        }

        $master->db->rawQuery(
            "INSERT IGNORE INTO torrents_tags_votes (GroupID, TagID, UserID, Way)
                         VALUES {$valuesQuery}
                             ON DUPLICATE KEY
                         UPDATE Way = 'up'",
            $params
        );
    }

    $inQuery = implode(', ', array_fill(0, count($AddedIDs), '?'));
    $master->db->rawQuery(
        "UPDATE tags
            SET Uses = Uses + 1
          WHERE ID IN ({$inQuery})",
        $AddedIDs
    );

    $master->db->rawQuery(
        "INSERT INTO group_log (GroupID, UserID, Time, Info)
              VALUES (?, ?, ?, ?)",
        [$GroupID, $activeUser['ID'], sqltime(), 'Tag'.($count>1?'s':''). " ".implode(', ', $AddedTags)." added to group"]
    );

    update_hash($GroupID); // Delete torrent group cache

    echo json_encode([$Results, get_taglist_html($GroupID, ($_POST['tagsort'] ?? 'uses'))]);

} else { //none actually added

    echo json_encode([$Results, 0]);
}
