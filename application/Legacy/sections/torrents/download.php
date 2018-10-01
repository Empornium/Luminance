<?php

if (!isset($_REQUEST['torrent_pass'])) {
    enforce_login();
    $TorrentPass = $LoggedUser['torrent_pass'];
    $DownloadAlt = $LoggedUser['DownloadAlt'];
} else {
    $UserInfo = $Cache->get_value('user_'.$_REQUEST['torrent_pass']);
    if (!is_array($UserInfo)) {
        $DB->query("SELECT
            ID,
            DownloadAlt
            FROM users_main AS m
            INNER JOIN users_info AS i ON i.UserID=m.ID
            WHERE m.torrent_pass='".db_string($_REQUEST['torrent_pass'])."'
            AND m.Enabled='1'");
        $UserInfo = $DB->next_record();
        $Cache->cache_value('user_'.$_REQUEST['torrent_pass'], $UserInfo, 3600);
    }
    $UserInfo = array($UserInfo);
    list($UserID,$DownloadAlt)=array_shift($UserInfo);
    if (!$UserID) { error(403); }
    $TorrentPass = $_REQUEST['torrent_pass'];
}

if (!$master->options->EnableDownloads) {
    error("Downloads are currently disabled.");
}

$TorrentID = $_REQUEST['id'];

if (!is_number($TorrentID)) { error(0); }

$Info = $Cache->get_value('torrent_download_'.$TorrentID);
if (!is_array($Info) || empty($Info[10])) {
    $DB->query("SELECT
        tg.ID AS GroupID,
        tg.Name,
        t.Size,
        t.FreeTorrent,
        t.DoubleTorrent,
        t.info_hash
        FROM torrents AS t
        INNER JOIN torrents_group AS tg ON tg.ID=t.GroupID
        WHERE t.ID='".db_string($TorrentID)."'");
    if ($DB->record_count() < 1) {
        header('Location: log.php?search='.$TorrentID);
        die();
    }
    $Info = array($DB->next_record(MYSQLI_NUM, array(4,5,6,10)));
    $Cache->cache_value('torrent_download_'.$TorrentID, $Info, 0);
}
if (!is_array($Info[0])) {
    error(404);
}
list($GroupID,$Name, $Size, $FreeTorrent, $DoubleTorrent, $InfoHash) = array_shift($Info); // used for generating the filename

$TokenTorrents = $Cache->get_value('users_tokens_'.$UserID);
if (empty($TokenTorrents)) {
        $DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$UserID");
        $TokenTorrents = $DB->to_array('TorrentID');
}

// If he's trying use a token on this, we need to make sure he has one,
// deduct it, add this to the FLs table, and update his cache key.
if ($_REQUEST['usetoken'] == 1 && $FreeTorrent == 0) {
    if (isset($LoggedUser)) {
        $FLTokens = $LoggedUser['FLTokens'];
        if ($LoggedUser['CanLeech'] != '1') {
            error('You cannot use tokens while leech disabled.');
        }
    } else {
        $UInfo = user_heavy_info($UserID);
        if ($UInfo['CanLeech'] != '1') {
            error('You may not use tokens while leech disabled.');
        }
        $FLTokens = $UInfo['FLTokens'];
    }

    // First make sure this isn't already FL, and if it is, do nothing
        // if it's currently using a double seed slot, switch to FL.
    if (empty($TokenTorrents[$TorrentID]) || $TokenTorrents[$TorrentID]['FreeLeech'] < sqltime()) {
        if ($FLTokens <= 0) {
            error("You do not have any tokens left. Please use the regular DL link.");
        }

        // We need to fetch and check this again here because of people
        // double-clicking the FL link while waiting for a tracker response.
        $TokenTorrents = $Cache->get_value('users_tokens_'.$UserID);
        if (empty($TokenTorrents)) {
            $DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$UserID");
            $TokenTorrents = $DB->to_array('TorrentID');
        }

        if (empty($TokenTorrents[$TorrentID]) || $TokenTorrents[$TorrentID]['FreeLeech'] < sqltime()) {
                        $time = time_plus(60*60*24*14); // 14 days

            // Let the tracker know about this
            //if (!update_tracker('add_token_fl', array('info_hash' => rawurlencode($InfoHash), 'userid' => $UserID, 'time' => strtotime($time)))) {
            if (!$master->tracker->addTokenFreeleech($InfoHash, $UserID, strtotime($time))) {
                    error("Sorry! An error occurred while trying to register your token. Most often, this is due to the tracker being down or under heavy load. Please try again later.");
            }

            // Update the db.
            $DB->query("INSERT INTO users_slots (UserID, TorrentID, FreeLeech) VALUES ('$UserID', '$TorrentID', '$time')
                            ON DUPLICATE KEY UPDATE FreeLeech=VALUES(FreeLeech)");
            $DB->query("UPDATE users_main SET FLTokens = FLTokens - 1 WHERE ID=$UserID");

            // Fix for downloadthemall messing with the cached token count
            $UInfo = user_heavy_info($UserID);
            $FLTokens = $UInfo['FLTokens'];

            $master->repos->users->uncache($UserID);

            $TokenTorrents[$TorrentID]['FreeLeech'] = $time;
            $Cache->cache_value('users_tokens_'.$UserID, $TokenTorrents);
        }
    }
} elseif ($_REQUEST['usetoken'] == 2 && $DoubleTorrent == 0) {

    // First make sure this isn't already DS, and if it is, do nothing
    if (empty($TokenTorrents[$TorrentID]) || $TokenTorrents[$TorrentID]['DoubleSeed'] < sqltime()) {
        if (isset($LoggedUser)) {
            $FLTokens = $LoggedUser['FLTokens'];
        } else {
            $UInfo = user_heavy_info($UserID);
            $FLTokens = $UInfo['FLTokens'];
        }

        if ($FLTokens <= 0) {
            error("You do not have any tokens left. Please use the regular DL link.");
        }

        // We need to fetch and check this again here because of people
        // double-clicking the DS link while waiting for a tracker response.
        $TokenTorrents = $Cache->get_value('users_tokens_'.$UserID);
        if (empty($TokenTorrents)) {
            $DB->query("SELECT TorrentID, FreeLeech, DoubleSeed FROM users_slots WHERE UserID=$UserID");
            $TokenTorrents = $DB->to_array('TorrentID');
        }

        if (empty($TokenTorrents[$TorrentID]) || $TokenTorrents[$TorrentID]['DoubleSeed'] < sqltime()) {
                        $time = time_plus(60*60*24*14); // 14 days

            // Let the tracker know about this
            //if (!update_tracker('add_token_ds', array('info_hash' => rawurlencode($InfoHash), 'userid' => $UserID, 'time' => strtotime($time)))) {
            if (!$master->tracker->addTokenDoubleseed($InfoHash, $UserID, strtotime($time))) {
                    error("Sorry! An error occurred while trying to register your token. Most often, this is due to the tracker being down or under heavy load. Please try again later.");
            }

            // Update the db
            $DB->query("INSERT INTO users_slots (UserID, TorrentID, DoubleSeed) VALUES ('$UserID', '$TorrentID', '$time')
                ON DUPLICATE KEY UPDATE DoubleSeed=VALUES(DoubleSeed)");
            $DB->query("UPDATE users_main SET FLTokens = FLTokens - 1 WHERE ID=$UserID");

            // Fix for downloadthemall messing with the cached token count
            $UInfo = user_heavy_info($UserID);
            $FLTokens = $UInfo['FLTokens'];

            $master->repos->users->uncache($UserID);

            $TokenTorrents[$TorrentID]['DoubleSeed'] = $time;
            $Cache->cache_value('users_tokens_'.$UserID, $TokenTorrents);
        }
    }
}

$DB->query("INSERT IGNORE INTO users_downloads (UserID, TorrentID, Time) VALUES ('$UserID', '$TorrentID', '".sqltime()."')");

$GrabbedTorrents[$TorrentID] =  ['TorrentID'=>$TorrentID];
$Cache->cache_value("users_torrents_grabbed_{$UserID}_{$TorrentID}", $GrabbedTorrents, 600);

$Tor = getTorrentFile($GroupID, $TorrentID, $TorrentPass);

// Torrent name takes the format of Album - YYYY
$TorrentName = '['.SITE_NAME.']'.((!empty($Name)) ? $Name : 'No Name');
$FileName = trim(file_string($TorrentName));
$FileName = ($Browser == 'Internet Explorer') ? urlencode($FileName) : $FileName;
$MaxLength = $DownloadAlt ? 192 : 196;
$FileName = cut_string($FileName, $MaxLength, true, false);
$FileName = $DownloadAlt ? $FileName.'.txt' : $FileName.'.torrent';

if ($DownloadAlt) {
    header('Content-Type: text/plain; charset=utf-8');
} elseif (!$DownloadAlt || $Failed) {
    header('Content-Type: application/x-bittorrent; charset=utf-8');
}
header('Content-disposition: attachment; filename="'.$FileName.'"');
$torrentdata = $Tor->enc();
header('Content-length: '.strlen($torrentdata));

echo $torrentdata;
