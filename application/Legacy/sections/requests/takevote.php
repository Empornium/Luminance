<?php
//******************************************************************************//
//--------------- Vote on a request --------------------------------------------//
//This page is ajax!

header('Content-Type: application/json; charset=utf-8');

if (!check_perms('site_vote'))  error(403, true);

authorize();

if (empty($_GET['id']) || !is_integer_string($_GET['id'])) error(0, true);

$RequestID = (int) $_GET['id'];

if (empty($_GET['amount']) || !is_integer_string($_GET['amount']) || $_GET['amount'] < $master->options->MinVoteBounty) {
    $Amount = $master->options->MinVoteBounty;
} else {
    $Amount = $_GET['amount'];
}

$Bounty = $Amount;

list($Filled, $RequesterID) = $master->db->rawQuery(
    'SELECT TorrentID,
            UserID
       FROM requests
      WHERE ID = ?',
    [$RequestID]
)->fetch(\PDO::FETCH_NUM);
if (!isset($Filled)) error("This request has been deleted!", true);
if ($Filled>0) error("This torrent is already filled!", true);

if (($activeUser['BytesUploaded'] - $activeUser['BytesDownloaded']) < $Amount) {

    echo json_encode(['bankrupt', $Bounty, 0, 0, false]);

} else {

    // Create vote!
    $affectedRows = $master->db->rawQuery(
        "INSERT IGNORE INTO requests_votes (RequestID, UserID, Bounty)
                     VALUES (?, ?, ?)",
        [$RequestID, $activeUser['ID'], $Bounty]
    )->rowCount();

    if ($affectedRows < 1) {
        //Insert failed, probably a dupe vote, just increase their bounty.
        $master->db->rawQuery(
            "UPDATE requests_votes
                SET Bounty = (Bounty + ?)
              WHERE UserID = ?
                AND RequestID = ?",
            [$Bounty, $activeUser['ID'], $RequestID]
        );
        $voteaction = 'dupe';
    } else {
        $voteaction = 'success';
    }

    $master->db->rawQuery(
        "UPDATE requests
            SET LastVote = NOW()
          WHERE ID = ?",
        [$RequestID]
    );

    $master->cache->deleteValue('request_'.$RequestID);
    $master->cache->deleteValue('request_votes_'.$RequestID);
    $master->cache->deleteValue('recent_requests_'.$RequesterID);

    // Subtract amount from user
    $master->db->rawQuery(
        "UPDATE users_main
            SET Uploaded = (Uploaded - ?)
          WHERE ID = ?",
        [$Amount, $activeUser['ID']]
    );
    $Title = $master->db->rawQuery(
        "SELECT Title
           FROM requests
          WHERE ID = ?",
        [$RequestID]
    )->fetchColumn();
    write_user_log($activeUser['ID'], "Removed -". get_size($Amount). " for vote on request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url]");

    $master->cache->deleteValue('user_stats_'.$activeUser['ID']);
    $master->cache->deleteValue('_entity_User_legacy_'.$activeUser['ID']);

    update_sphinx_requests($RequestID);

    $RequestVotes = get_votes_array($RequestID);

    echo json_encode(
        [
            $voteaction,
            $Bounty,
            $RequestVotes['TotalBounty'],
            count($RequestVotes['Voters']),
            get_votes_html($RequestVotes, $RequestID)
        ]
    );
}
