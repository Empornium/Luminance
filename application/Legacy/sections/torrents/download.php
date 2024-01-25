<?php

include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');


$torrentID = (int) ($_REQUEST['id'] ?? null);
$TorrentPass = trim($_REQUEST['torrent_pass'] ?? '');

if (!is_integer_string($torrentID)) {
    error(0);
}

if (isset($activeUser)) {
    if (!empty($TorrentPass)) {
        if ($TorrentPass != $activeUser['torrent_pass']) {
            $Subject  = "{$activeUser['Username']} attempted to download a torrent with the wrong passkey!";
            $Message  = "[user]{$activeUser['ID']}[/user] attempted to download [torrent]{$torrentID}[/torrent] with passkey [code]{$TorrentPass}[/code][br]";
            $Message .= "[br][br]/user/{$activeUser['ID']}/security[br]";
            $Message .= "/user.php?action=search&action=search&passkey={$TorrentPass}";
            $staffClass = $master->repos->permissions->getMinClassPermission('users_view_keys');
            send_staff_pm($Subject, $Message, $staffClass->Level);
        }
    }
    $TorrentPass = $activeUser['torrent_pass'];
    $DownloadAlt = $activeUser['DownloadAlt'];
}

if (empty($TorrentPass)) {
    enforce_login();
} else {
    $UserInfo = $master->cache->getValue('user_'.$TorrentPass);
    if (!is_array($UserInfo)) {
        $UserInfo = $master->db->rawQuery(
            "SELECT ID,
                    DownloadAlt
               FROM users_main AS m
         INNER JOIN users_info AS i ON i.UserID = m.ID
              WHERE m.torrent_pass = ?
                AND m.Enabled = '1'",
            [$TorrentPass]
        )->fetch(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('user_'.$TorrentPass, $UserInfo, 3600);
    }
    $UserInfo = [$UserInfo];
    list($userID, $DownloadAlt) = array_shift($UserInfo);
    if (!$userID) { error(403); }
}


if (!$master->options->EnableDownloads) {
    error("Downloads are currently disabled.");
}

$Info = $master->cache->getValue("torrent_download_{$torrentID}");
if (!is_array($Info) || empty($Info[10])) {
    $Info = [$master->db->rawQuery(
        "SELECT tg.ID AS GroupID,
                tg.Name,
                t.Size,
                t.FreeTorrent,
                t.DoubleTorrent,
                t.info_hash
           FROM torrents AS t
     INNER JOIN torrents_group AS tg ON tg.ID = t.GroupID
          WHERE t.ID = ?",
        [$torrentID]
    )->fetch(\PDO::FETCH_NUM)];
    if ($master->db->foundRows() < 1) {
        header('Location: log.php?search='.$torrentID);
        die();
    }
    $master->cache->cacheValue("torrent_download_{$torrentID}", $Info, 0);
}
if (!is_array($Info[0])) {
    error(404);
}
list($GroupID, $Name, $Size, $FreeTorrent, $DoubleTorrent, $InfoHash) = array_shift($Info); // used for generating the filename

$tokenTorrents = getTokenTorrents($userID);

// If he's trying use a token on this, we need to make sure he has one,
// deduct it, add this to the FLs table, and update his cache key.
if (($_REQUEST['usetoken'] ?? 0) == 1 && $FreeTorrent == 0) {
    if (isset($activeUser)) {
        $FLTokens = $activeUser['FLTokens'];
        if ($activeUser['CanLeech'] != '1') {
            error('You cannot use tokens while leech disabled.');
        }
    } else {
        $UInfo = user_heavy_info($userID);
        if ($UInfo['CanLeech'] != '1') {
            error('You may not use tokens while leech disabled.');
        }
        $FLTokens = $UInfo['FLTokens'];
    }

    // First make sure this isn't already FL, and if it is, do nothing
        // if it's currently using a double seed slot, switch to FL.
    if (empty($tokenTorrents[$torrentID]) || ($tokenTorrents[$torrentID]['FreeLeech'] ?? null) < sqltime()) {
        if ($FLTokens <= 0) {
            error("You do not have any tokens left. Please use the regular DL link.");
        }

        // We need to fetch and check this again here because of people
        // double-clicking the FL link while waiting for a tracker response.
        $tokenTorrents = getTokenTorrents($userID);

        if (empty($tokenTorrents[$torrentID]) || ($tokenTorrents[$torrentID]['FreeLeech'] ?? null) < sqltime()) {
                        $time = time_plus(60*60*24*14); // 14 days

            // Let the tracker know about this
            if (!$master->tracker->addTokenFreeleech($InfoHash, $userID, strtotime($time))) {
                    error("Sorry! An error occurred while trying to register your token. Most often, this is due to the tracker being down or under heavy load. Please try again later.");
            }

            // Update the db.
            $master->db->rawQuery(
                "INSERT INTO users_slots (UserID, TorrentID, FreeLeech)
                      VALUES (?, ?, ?)
                          ON DUPLICATE KEY
                      UPDATE FreeLeech = VALUES(FreeLeech)",
                [$userID, $torrentID, $time]
            );
            $master->db->rawQuery(
                "UPDATE users_main
                    SET FLTokens = FLTokens - 1
                  WHERE ID = ?",
                [$userID]
            );

            // Fix for downloadthemall messing with the cached token count
            $UInfo = user_heavy_info($userID);
            $FLTokens = $UInfo['FLTokens'];

            $master->repos->users->uncache($userID);

            $tokenTorrents[$torrentID]['FreeLeech'] = $time;
            $master->cache->cacheValue("users_tokens_{$userID}", $tokenTorrents);
        }
    }
} elseif (($_REQUEST['usetoken'] ?? 0) == 2 && $DoubleTorrent == 0) {

    // First make sure this isn't already DS, and if it is, do nothing
    if (empty($tokenTorrents[$torrentID]) || ($tokenTorrents[$torrentID]['DoubleSeed'] ?? null) < sqltime()) {
        if (isset($activeUser)) {
            $FLTokens = $activeUser['FLTokens'];
        } else {
            $UInfo = user_heavy_info($userID);
            $FLTokens = $UInfo['FLTokens'];
        }

        if ($FLTokens <= 0) {
            error("You do not have any tokens left. Please use the regular DL link.");
        }

        // We need to fetch and check this again here because of people
        // double-clicking the DS link while waiting for a tracker response.
        $tokenTorrents = getTokenTorrents($userID);

        if (empty($tokenTorrents[$torrentID]) || ($tokenTorrents[$torrentID]['DoubleSeed'] ?? null) < sqltime()) {
                        $time = time_plus(60*60*24*14); // 14 days

            // Let the tracker know about this
            if (!$master->tracker->addTokenDoubleseed($InfoHash, $userID, strtotime($time))) {
                    error("Sorry! An error occurred while trying to register your token. Most often, this is due to the tracker being down or under heavy load. Please try again later.");
            }

            // Update the db
            $master->db->rawQuery(
                "INSERT INTO users_slots (UserID, TorrentID, DoubleSeed)
                      VALUES (?, ?, ?)
                          ON DUPLICATE KEY
                      UPDATE DoubleSeed = VALUES(DoubleSeed)",
                [$userID, $torrentID, $time]
            );
            $master->db->rawQuery(
                "UPDATE users_main
                    SET FLTokens = FLTokens - 1
                  WHERE ID = ?",
                [$userID]
            );

            // Fix for downloadthemall messing with the cached token count
            $UInfo = user_heavy_info($userID);
            $FLTokens = $UInfo['FLTokens'];

            $master->repos->users->uncache($userID);

            $tokenTorrents[$torrentID]['DoubleSeed'] = $time;
            $master->cache->cacheValue("users_tokens_{$userID}", $tokenTorrents);
        }
    }
}

$master->db->rawQuery(
    "INSERT IGNORE INTO users_downloads (UserID, TorrentID, Time)
                 VALUES (?, ?, ?)",
    [$userID, $torrentID, sqltime()]
);

$GrabbedTorrents[$torrentID] =  ['TorrentID'=>$torrentID];
$master->cache->cacheValue("users_torrents_grabbed_{$userID}_{$torrentID}", $GrabbedTorrents, 600);

$Tor = getTorrentFile($torrentID, $TorrentPass);

// Torrent name takes the format of Album - YYYY
$TorrentName = '['.SITE_NAME.']'.((!empty($Name)) ? $Name : 'No Name');
$FileName = trim(file_string($TorrentName));
$FileName = ($browser == 'Internet Explorer') ? urlencode($FileName) : $FileName;
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
