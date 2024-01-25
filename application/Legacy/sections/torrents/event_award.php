<?php

include(SERVER_ROOT.'/Legacy/sections/torrents/functions.php');

authorize();

// Quick SQL injection check
if (empty($_REQUEST['groupid']) || !is_integer_string($_REQUEST['groupid'])) {
  error(404);
}
// Quick SQL injection check
if (!$_REQUEST['eventid'] || !is_integer_string($_REQUEST['eventid'])) {
  error(404);
}
// End injection check
$GroupID = (int)$_REQUEST['groupid'];
$EventID = (int)$_REQUEST['eventid'];

//check user has permission to edit
if (!check_perms('torrent_review')) error(403);

// User PFL
$userID = $master->db->rawQuery(
    "SELECT UserID
       FROM torrents
      WHERE ID = ?",
    [$GroupID]
)->fetchColumn();

if (!$userID) {
    error(404);
}

$Event = $master->db->rawQuery(
    "SELECT Title,
            Comment,
             UFL,
             PFL,
             Tokens,
             Credits,
             StartTime,
             EndTime
        FROM events
       WHERE ID = ?",
    [$EventID]
)->fetch(\PDO::FETCH_ASSOC);

$user = $master->repos->users->load($userID);
$sqltime = sqltime();

$UpdateSet = [];
$params = [];
$EditSummary = [];

$MaxPFL = (60*60*24*7*4); // 4 weeks
if ($Event['PFL'] != 0) {

    if ($user->legacy['personal_freeleech'] < sqltime()) {
        $current = 0;
        $before = 'none';
    } else {
        $current = strtotime($user->legacy['personal_freeleech']) - time();
        $before = time_diff($user->legacy['personal_freeleech'], 2, false);
    }

    $MaxPFL = (60*60*24*7*4); // 4 weeks
    // If user already has more than max then don't touch
    if ($current < $MaxPFL*1000) {
        // If user already has PFL then stack
        if ($current > 0) {
            $PFL = $current + $Event['PFL']*(60*60);
            // Don't stack above max PFL.
            if ($PFL > $MaxPFL) {
              $PFL = $MaxPFL;
            }
            // Convert to date
            $PFL = time_plus($PFL);
        } else {
            $PFL = $Event['PFL']*(60*60);
            $PFL = time_plus($PFL);
        }

        $after = time_diff($PFL, 2, false);

        $UpdateSet[] = "personal_freeleech = ?";
        $params[] = $PFL;
        $EditSummary[] = "Personal Freeleech changed from {$before} to {$after}";
    }
}

$MaxTokens = 12;
if ($Event['Tokens'] != 0) {
    if ($user->legacy['FLTokens'] < $MaxTokens) {
        $Tokens = $user->legacy['FLTokens'] + $Event['Tokens'];
        if ($Tokens > $MaxTokens) {
            $Tokens = $MaxTokens;
        }

        $UpdateSet[] = "FLTokens = ?";
        $params[] = $Tokens;
        $EditSummary[] = "Freeleech Tokens changed from {$user->legacy['FLTokens']} to {$Tokens}";
    }
}

if ($Event['Credits'] != 0) {
    $Credits = $user->legacy['Credits'] + $Event['Credits'];
    $user->wallet->adjustBalance($Credits);
    $user->wallet->addLog(' | +'.number_format($Credits).' credits | You were you were awarded for adding torrent#'.$torrentID.' to the '.$Event['Title'].' event');
    $EditSummary[]="Credits changed from ".$user->legacy['Credits']." to ".$Credits;
}

// Run the DB updates
$Reason = "Uploader of [group]{$GroupID}[/group] which is part of {$Event['Title']} (Event)";

$antiNinja = $master->db->rawQuery(
    "SELECT COUNT(*)
       FROM torrents_events
      WHERE TorrentID = ?
        AND EventID = ?",
    [$GroupID, $EventID]
)->fetchColumn();

if ($antiNinja > 0) {
    // Torrent has already been awarded this event, bail out!
    header("Location: torrents.php?id=".$GroupID);
}

$Summary = '';
// Create edit summary
if (!empty($EditSummary)) {
    $Summary = implode(', ', $EditSummary)." by ".$activeUser['Username'];
    $Summary = sqltime().' - '.ucfirst($Summary);
    $Summary .= "\nReason: ".$Reason;
    $Summary .= "\n".$user->legacy['AdminComment'];
    $UpdateSet[] = "AdminComment = ?";
    $params[] = $Summary;
    $SET = implode(', ', $UpdateSet);

    $params[] = $userID;
    $master->db->rawQuery(
        "UPDATE users_main AS m
           JOIN users_info AS i ON m.ID = i.UserID
            SET {$SET}
          WHERE m.ID = ?",
        $params
    );
}

list($PFL, $Pass) = $master->db->rawQuery(
    "SELECT personal_freeleech,
            torrent_pass
       FROM users_main
      WHERE ID = ?",
    [$userID])->fetch(\PDO::FETCH_NUM);

if ($Event['UFL'] == 1) {
    freeleech_groups($GroupID, 1, false, $Event['Title']);
}

$master->db->rawQuery(
    "INSERT INTO torrents_events (TorrentID, StaffID, EventID, Time)
          VALUES (?, ?, ?, '{$sqltime}')",
    [$GroupID, $activeUser['ID'], $EventID]
);
write_group_log($GroupID, 0, $activeUser['ID'], "[b]Event:[/b] torrent added to {$Event['Title']}", 0);

$master->cache->deleteValue('torrents_details_'.$GroupID);
$master->cache->deleteValue("torrent_events_{$GroupID}");
$master->cache->deleteValue('user_info_heavy_'.$userID);
$master->repos->users->uncache($userID);

// Update tracker last in case it stalls
$master->tracker->setPersonalFreeleech($Pass, strtotime($PFL));

header("Location: torrents.php?id=".$GroupID);
