<?php

authorize();

if (!can_bookmark($_GET['type'])) { error(404); }
$feed = new Luminance\Legacy\Feed;
$bbCode = new \Luminance\Legacy\Text;

$Type = $_GET['type'];

list($Table, $Col) = bookmark_schema($Type);

if (!is_integer_string($_GET['id'])) {
    error(0);
}

$recordCount = $master->db->rawQuery(
    "SELECT COUNT(UserID)
       FROM {$Table}
      WHERE UserID = ?
        AND {$Col} = ?",
    [$activeUser['ID'], $_GET['id']]
)->fetchColumn();

if ($recordCount === 0) {
    $master->db->rawQuery(
        "INSERT IGNORE INTO {$Table} (UserID, {$Col}, Time)
                     VALUES (?, ?, ?)",
        [$activeUser['ID'], $_GET['id'], sqltime()]
    );
    $master->cache->deleteValue('bookmarks_'.$Type.'_'.$activeUser['ID']);
    if ($Type == 'torrent') {
        $master->cache->deleteValue('bookmarks_info_'.$activeUser['ID']);
        $GroupID = $_GET['id'];

        $group = $master->db->rawQuery(
            "SELECT Name,
                    Body,
                    TagList
               FROM torrents_group
              WHERE ID = ?",
            [$GroupID]
        )->fetch(\PDO::FETCH_ASSOC);

        $torrents = $master->db->rawQuery(
            "SELECT ID,
                    FreeTorrent,
                    UserID,
                    Anonymous
               FROM torrents
              WHERE GroupID = ?",
            [$GroupID]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // RSS feed stuff
        foreach ($torrents as $torrent) {
            $title = $group['Name'];
            $tagList = trim(str_replace('_', '.', $group['TagList']));
            $body = $group['Body'];
            if ($torrent['FreeTorrent'] == "1") { $title .= " / Freeleech!"; }
            if ($torrent['FreeTorrent'] == "2") { $title .= " / Neutral leech!"; }

            $UploaderInfo = user_info($torrent['UserID']);
            $Item = $feed->item(
                $title,
                $bbCode->strip_bbcode($body),
                "torrents.php?action=download&amp;authkey=[[AUTHKEY]]&amp;torrent_pass=[[PASSKEY]]&amp;id={$torrent['ID']}",
                trim(anon_username($UploaderInfo['Username'], $torrent['Anonymous'])),
                "torrents.php?id={$GroupID}",
                $tagList
            );
            $feed->populate('torrents_bookmarks_t_'.$activeUser['torrent_pass'], $Item);
        }
    } elseif ($Type == 'request') {
        $Bookmarkers = $master->db->rawQuery(
            "SELECT UserID
               FROM {$Table}
              WHERE {$Col} = ?",
            [$_GET['id']]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $search->updateAttributes('requests requests_delta', ['bookmarker'], [$_GET['id'] => [$Bookmarkers]], true);
    }
}
