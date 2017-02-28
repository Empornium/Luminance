<?php
header('Content-Type: application/json; charset=utf-8');

$UserID = $LoggedUser['ID'];
$TagID = db_string($_POST['tagid']);
$GroupID = db_string($_POST['groupid']);
$Way = db_string($_POST['way']);

if (!is_number($TagID) || !is_number($GroupID)) {
    error(0,true);
}
if (!in_array($Way, array('up', 'down'))) {
    error(0,true);
}

$UserVote = check_perms('site_vote_tag_enhanced') ? ENHANCED_VOTE_POWER : 1;

$DB->query("SELECT Way FROM torrents_tags_votes WHERE TagID='$TagID' AND GroupID='$GroupID' AND UserID='$UserID'");
if($DB->record_count() > 0) list($LastVote)=$DB->next_record();

// if not voted before or changing vote
if ($LastVote!=$Way) {
    if ($LastVote) {
        $DB->query("DELETE FROM torrents_tags_votes WHERE TagID='$TagID' AND GroupID='$GroupID' AND UserID='$UserID'");
        $msg = "Removed $LastVote vote for tag ";
        if ($Way == 'down') { // $LastVote == 'up'
            $Change = "PositiveVotes=PositiveVotes-$UserVote";
            echo json_encode (array(-$UserVote, $msg));
        } else {  // $LastVote == 'down'
            $Change = "NegativeVotes=NegativeVotes-$UserVote";
            echo json_encode (array($UserVote, $msg));
        }
    } else {
        $DB->query("INSERT IGNORE INTO torrents_tags_votes (GroupID, TagID, UserID, Way) VALUES ('$GroupID', '$TagID', '$UserID', '$Way')");
        $msg = "Voted $Way for tag ";
        if ($Way == 'down') {
            $Change = "NegativeVotes=NegativeVotes+$UserVote";
            echo json_encode (array(-$UserVote, $msg));
        } else {
            $Change = "PositiveVotes=PositiveVotes+$UserVote";
            echo json_encode (array($UserVote, $msg));
        }
    }

    $DB->query("UPDATE torrents_tags SET $Change WHERE TagID='$TagID' AND GroupID='$GroupID'");

    $DB->query("DELETE FROM torrents_tags WHERE TagID='$TagID' AND GroupID='$GroupID' AND NegativeVotes>PositiveVotes");
    if ($DB->affected_rows()>0) {
        $DB->query("DELETE FROM torrents_tags_votes WHERE TagID='$TagID' AND GroupID='$GroupID'");

        // Decrease the tag count, if it's not in use any longer and not an official tag, delete it from the list.
        $DB->query("SELECT COUNT(GroupID) FROM torrents_tags WHERE TagID=".$TagID);
        list($Count) = $DB->next_record();
        if ($TagType == 'genre' || $Count > 0) {
            $Count = $Count > 0 ? $Count : 0;
            $DB->query("UPDATE tags SET Uses=$Count WHERE ID=$TagID");
        } else {
            $DB->query("DELETE FROM tags WHERE ID=".$TagID." AND TagType='other'");

            // Delete tag cache entry
            $DB->query("SELECT Name FROM tags WHERE TagID = '$TagID'");
            if ($DB->affected_rows() > 0) {
                list($TagName) = $DB->next_record();
                $Cache->delete_value('tag_id_'.$TagName);
            }
        }

        update_hash($GroupID);
    }
    $Cache->delete_value('torrents_details_'.$GroupID); // Delete torrent group cache
} else
    echo json_encode (array(0,"Already voted $Way for tag "));
