<?php

include(SERVER_ROOT.'/Legacy/sections/torrents/functions.php');

authorize();

// Quick SQL injection check
if(empty($_REQUEST['groupid']) || !is_number($_REQUEST['groupid'])) {
  error(404);
}
// Quick SQL injection check
if(!$_REQUEST['eventid'] || !is_number($_REQUEST['eventid'])) {
  error(404);
}
// End injection check
$GroupID = (int)$_REQUEST['groupid'];
$EventID = (int)$_REQUEST['eventid'];

//check user has permission to edit
if(!check_perms('torrents_review')) error(403);

// User PFL
$DB->query("SELECT UserID FROM torrents	WHERE ID = $GroupID");
list($UserID) = $DB->next_record();

if (!$UserID) {
    error(404);
}

$DB->query("SELECT Title,
                   Comment,
                   UFL,
                   PFL,
                   Tokens,
                   Credits,
                   StartTime,
                   EndTime
              FROM events
             WHERE ID = '{$EventID}'");
$Event = $DB->next_record(MYSQLI_ASSOC);

$user = $master->repos->users->load($UserID);
$sqltime = sqltime();

$UpdateSet = [];

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

        $UpdateSet[]="personal_freeleech='$PFL'";
        $EditSummary[]="Personal Freeleech changed from ".$before." to ".$after;
    }
}

$MaxTokens = 12;
if ($Event['Tokens'] != 0) {
    if($user->legacy['FLTokens'] < $MaxTokens) {
        $Tokens = $user->legacy['FLTokens'] + $Event['Tokens'];
        if($Tokens > $MaxTokens) {
            $Tokens = $MaxTokens;
        }

        $UpdateSet[]="FLTokens=".$Tokens;
        $EditSummary[]="Freeleech Tokens changed from ".$user->legacy['FLTokens']." to ".$Tokens;
    }
}

if ($Event['Credits'] != 0) {
    $Credits = $user->legacy['Credits'] + $Event['Credits'];
    $UpdateSet[]="Credits=".$Credits;
    $EditSummary[]="Credits changed from ".$user->legacy['Credits']." to ".$Credits;
}

// Run the DB updates
$Reason = "Uploader of [torrent]{$GroupID}[/torrent] which is part of {$Event['Title']} (Event)";

$DB->query("SELECT COUNT(*) from torrents_events WHERE TorrentID='{$GroupID}' AND EventID='{$EventID}'");
$antiNinja = $DB->next_record;

if ($antiNinja > 0) {
    // Torrent has already been awarded this event, bail out!
    header("Location: torrents.php?id=".$GroupID);
}

$Summary = '';
// Create edit summary
if (!empty($EditSummary)) {
    $Summary = implode(', ', $EditSummary)." by ".$LoggedUser['Username'];
    $Summary = sqltime().' - '.ucfirst($Summary);
    $Summary .= "\nReason: ".$Reason;
    $Summary .= "\n".$user->legacy['AdminComment'];
    $UpdateSet[]="AdminComment='".db_string($Summary)."'";
    $SET = implode(', ', $UpdateSet);

    $DB->query("UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID SET {$SET} WHERE m.ID='{$UserID}'");
}

$DB->query("SELECT personal_freeleech, torrent_pass FROM users_main WHERE ID=$UserID");
list($PFL, $Pass) = $DB->next_record();

if($Event['UFL'] == 1) {
    freeleech_groups($GroupID, 1);
}

$DB->query("INSERT INTO torrents_events (TorrentID, StaffID, EventID, Time) VALUES('{$GroupID}', '{$LoggedUser['ID']}', '{$EventID}', '{$sqltime}')");
write_group_log($GroupID, 0, $LoggedUser['ID'], "[b]Event:[/b] torrent added to {$Event['Title']}", 0);

$Cache->delete_value('torrents_details_'.$GroupID);
$Cache->delete_value("torrent_events_{$GroupID}");
$Cache->delete_value('user_info_heavy_'.$UserID);
$master->repos->users->uncache($UserID);

// Update tracker last in case it stalls
$master->tracker->setPersonalFreeleech($Pass, strtotime($PFL));

header("Location: torrents.php?id=".$GroupID);
