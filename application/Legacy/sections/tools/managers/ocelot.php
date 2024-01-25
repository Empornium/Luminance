<?php
$RequestKey = $_REQUEST['key'];
$Type = $_REQUEST['type'];
if (($RequestKey != TRACKER_SECRET)) {
// I don't think this is a robust idea at all. :(
// || $_SERVER['REMOTE_ADDR'] != TRACKER_HOST) {
    error(403);
}
switch ($Type) {
    case 'expiretoken':
        if (isset($_GET['tokens'])) {
            $Tokens = explode(',', $_GET['tokens']);
            if (empty($Tokens)) {
                error(0);
            }
            $Cond = $userIDs = [];
            $params = [];
            foreach ($Tokens as $Key => $Token) {
                list($userID, $torrentID) = explode(':', $Token);
                if (!is_integer_string($userID) || !is_integer_string($torrentID)) {
                  continue;
                }
                $Cond[] = "(UserID = ? AND TorrentID = ?)";
                $params[] = $userID;
                $params[] = $torrentID;
                $userIDs[] = $userID;
            }
            if (!empty($Cond)) {
                $Query = "
                  UPDATE users_freeleeches
                  SET Expired = TRUE
                  WHERE ".implode(" OR ", $Cond);
                $master->db->rawQuery($Query, $params);
                foreach ($userIDs as $userID) {
                    $master->cache->deleteValue("users_tokens_$userID");
                }
            }
        } else {
            $torrentID = $_REQUEST['torrentid'];
            $userID = $_REQUEST['userid'];
            if (!is_integer_string($torrentID) || !is_integer_string($userID)) {
                error(403);
            }
            $master->db->rawQuery(
                "UPDATE users_freeleeches
                    SET Expired = TRUE
                  WHERE UserID = ?
                    AND TorrentID = ?",
                [$userID, $torrentID]
            );
            $master->cache->deleteValue("users_tokens_$userID");
        }
        break;
}
