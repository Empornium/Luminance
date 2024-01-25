<?php

use \Luminance\Errors\SystemError;

function get_group_info($GroupID, $Return = true, $ShowLog = true) {
    global $master;

    $GroupID=(int) $GroupID;

    $TorrentCache=$master->cache->getValue('torrents_details_'.$GroupID);

    //TODO: Remove LogInDB at a much later date.
    if (!is_array($TorrentCache) || !isset($TorrentCache[1][0]['LogInDB'])) {
        // Fetch the group details

        $SQL = "SELECT
                g.Body,
                g.Image,
                g.ID,
                g.Name,
                g.NewCategoryID,
                g.Time
            FROM torrents_group AS g
            WHERE g.ID = ?";

        $TorrentDetails = $master->db->rawQuery($SQL, [$GroupID])->fetchAll(\PDO::FETCH_BOTH);

        $TagDetails = $master->db->rawQuery(
            "SELECT tags.Name,
                    tt.TagID,
                    tt.UserID,
                    u1.Username,
                    tags.Uses,
                    tt.PositiveVotes,
                    tt.NegativeVotes,
                    GROUP_CONCAT(ttv.UserID SEPARATOR '|'),
                    GROUP_CONCAT(u2.Username SEPARATOR '|'),
                    GROUP_CONCAT(ttv.Way SEPARATOR '|')
               FROM torrents_tags AS tt
          LEFT JOIN tags ON tags.ID=tt.TagID
          LEFT JOIN torrents_tags_votes AS ttv ON ttv.GroupID=tt.GroupID AND ttv.TagID=tt.TagID
          LEFT JOIN users AS u1 ON u1.ID=tt.UserID
          LEFT JOIN users AS u2 ON u2.ID=ttv.UserID
              WHERE tt.GroupID = ?
           GROUP BY tt.TagID",
            [$GroupID]
        )->fetchAll(\PDO::FETCH_NUM);

        // Fetch the individual torrents

        $TorrentList = $master->db->rawQuery(
            "SELECT t.ID,
                    t.FileCount,
                    t.Size,
                    t.Seeders,
                    t.Leechers,
                    t.Snatched,
                    t.FreeTorrent,
                    t.DoubleTorrent,
                    t.Time,
                    t.FileList,
                    t.FilePath,
                    t.UserID,
                    u.Username,
                    t.last_action,
                    tbt.TorrentID,
                    tbf.TorrentID,
                    tfi.TorrentID,
                    t.LastReseedRequest,
                    tln.TorrentID AS LogInDB,
                    t.ID AS HasFile ,
                    t.Anonymous,
                    ta.Ducky,
                    t.AverageSeeders
               FROM torrents AS t
          LEFT JOIN users AS u ON u.ID=t.UserID
          LEFT JOIN torrents_bad_tags AS tbt ON tbt.TorrentID=t.ID
          LEFT JOIN torrents_bad_folders AS tbf on tbf.TorrentID=t.ID
          LEFT JOIN torrents_bad_files AS tfi on tfi.TorrentID=t.ID
          LEFT JOIN torrents_logs_new AS tln ON tln.TorrentID=t.ID
          LEFT JOIN torrents_awards AS ta ON ta.TorrentID=t.ID
              WHERE t.GroupID = ?
           ORDER BY t.ID",
            [$GroupID]
        )->fetchAll(\PDO::FETCH_BOTH);

        if (count($TorrentList) == 0 && $ShowLog) {
            if (isset($_GET['torrentid']) && is_integer_string($_GET['torrentid'])) {
                header("Location: log.php?search=Torrent+".$_GET['torrentid']);
            } else {
                header("Location: log.php?search=Torrent+".$GroupID);
            }
            die();
        }

        foreach ($TorrentList as &$Torrent) {
            $CacheTime = $Torrent['Seeders']==0 ? 120 : 900;
            $TorrentPeerInfo = ['Seeders'=>$Torrent['Seeders'], 'Leechers'=>$Torrent['Leechers'], 'Snatched'=>$Torrent['Snatched']];
            $master->cache->cacheValue('torrent_peers_'.$Torrent['ID'], $TorrentPeerInfo, $CacheTime);
        }

        // Store it all in cache
        $master->cache->cacheValue('torrents_details_'.$GroupID, [$TorrentDetails, $TorrentList, $TagDetails], 3600);

    } else { // If we're reading from cache
        $TorrentDetails=$TorrentCache[0];
        $TorrentList=$TorrentCache[1];
        $TagDetails=$TorrentCache[2];
        foreach ($TorrentList as &$Torrent) {
            $TorrentPeerInfo = get_peers($Torrent['ID']);
            $Torrent[3]=$TorrentPeerInfo['Seeders'];
            $Torrent[4]=$TorrentPeerInfo['Leechers'];
            $Torrent[5]=$TorrentPeerInfo['Snatched'];
            $Torrent['Seeders']=$TorrentPeerInfo['Seeders'];
            $Torrent['Leechers']=$TorrentPeerInfo['Leechers'];
            $Torrent['Snatched']=$TorrentPeerInfo['Snatched'];
        }
    }

    if ($Return) {
        return [$TorrentDetails, $TorrentList, $TagDetails];
    }
}

//Check if a givin string can be validated as a torrenthash
function is_valid_torrenthash($Str) {
    //6C19FF4C 6C1DD265 3B25832C 0F6228B2 52D743D5
    $Str = str_replace(' ', '', $Str);
    if (preg_match('/^[0-9a-fA-F]{40}$/', $Str))

        return $Str;
    return false;
}

function get_group_requests($GroupID) {
    global $master;

    $Requests = $master->cache->getValue('requests_group_'.$GroupID);
    if ($Requests === FALSE) {
        $Requests = $master->db->rawQuery(
            "SELECT ID
               FROM requests
              WHERE GroupID = ?
                AND TimeFilled = '0000-00-00 00:00:00'",
            [$GroupID]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $master->cache->cacheValue('requests_group_'.$GroupID, $Requests, 0);
    }
    $Requests = get_requests($Requests);

    return $Requests['matches'];
}

function get_group_requests_filled($torrentID) {
    global $master;

    $Requests = $master->cache->getValue('requests_torrent_'.$torrentID);
    if ($Requests === FALSE) {
        $Requests = $master->db->rawQuery(
            "SELECT ID
               FROM requests
              WHERE TorrentID = ?",
            [$torrentID]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $master->cache->cacheValue('requests_torrent_'.$torrentID, $Requests, 0);
    }
    $Requests = get_requests($Requests);

    return $Requests['matches'];
}

// tag sorting functions
function sort_uses_desc($X, $Y) {
    return($Y['uses'] - $X['uses']);
}

function sort_score_desc($X, $Y) {
    if ($Y['score'] == $X['score'])
        return ($Y['uses'] - $X['uses']);
    else
        return($Y['score'] - $X['score']);
}

function sort_added_desc($X, $Y) {
    return($X['id'] - $Y['id']);
}

function sort_az_desc($X, $Y) {
    return( strcmp($X['name'], $Y['name']));
}

function sort_uses_asc($X, $Y) {
    return($X['uses'] - $Y['uses']);
}

function sort_score_asc($X, $Y) {
    if ($Y['score'] == $X['score'])
        return ($X['uses'] - $Y['uses']);
    else
        return($X['score'] - $Y['score']);
}

function sort_added_asc($X, $Y) {
    return($Y['id'] - $X['id']);
}

function sort_az_asc($X, $Y) {
    return( strcmp($Y['name'], $X['name']));
}

 /**
 * Returns a json encoded arrary for sorting in the browser, because why the fuck is PHP doing this shit?
 */
function get_taglist_json($GroupID) {
    global $activeUser;

    $TorrentCache = get_group_info($GroupID, true);
    $TorrentDetails = $TorrentCache[0];
    $TorrentList = $TorrentCache[1];
    $TorrentTags = $TorrentCache[2];

    list(, , , , , , , , , , , $userID, $Username, , , , , , , , $IsAnon) = $TorrentList[0];

    $Tags = [];
    if ($TorrentTags != '') {
        foreach ($TorrentTags as $TagKey => $TagDetails) {
            list($TagName, $TagID, $TagUserID, $TagUsername, $TagUses, $TagPositiveVotes, $TagNegativeVotes,
                    $TagVoteUserIDs, $TagVoteUsernames, $TagVoteWays) = $TagDetails;

            # Rare, but can happen if a tag is messed up.
            if (empty($TagName) || empty($TagID) ||empty($TagUses)) {
                continue;
            }

            $Tags[$TagKey]['name']       = $TagName;
            $Tags[$TagKey]['score']      = (int)($TagPositiveVotes - $TagNegativeVotes);
            $Tags[$TagKey]['id']         = $TagID;
            $Tags[$TagKey]['userid']     = is_anon($IsAnon && $Username===$TagUsername) ? '0': $TagUserID;
            $Tags[$TagKey]['username']   = anon_username_ifmatch($TagUsername, $Username, $IsAnon) ;
            $Tags[$TagKey]['uses']       = (int)$TagUses;

            $TagVoteUsernames = explode('|', $TagVoteUsernames);
            $TagVoteWays = explode('|', $TagVoteWays);
            $VoteMsgs = [];
            $VoteMsgs[]= "$TagName (" . str_plural('use', $TagUses) . ")";
            $VoteMsgs[]= "added by " . anon_username_ifmatch($TagUsername, $Username, $IsAnon);
            $TagVotes = [];
            foreach ($TagVoteUsernames as $TagVoteKey => $TagVoteUsername) {
                if (!$TagVoteUsername) continue;
                $TagVotes[] = ['way' => $TagVoteWays[$TagVoteKey], 'user' => anon_username_ifmatch($TagVoteUsername, $Username, $IsAnon)];
            }
            $Tags[$TagKey]['votes'] = [ 'msg' => implode("\n", $VoteMsgs), 'votes' => $TagVotes] ;
        }
    }

    return json_encode($Tags);
}

/**
 * Returns the inner list elements of the tag table for a torrent
 * (this function calls/rebuilds the group_info cache for the torrent - in theory just a call to memcache as all calls come through the torrent details page)
 * @param int $GroupID The group id of the torrent
 * @return the html for the taglist
 */
function get_taglist_html($GroupID, $tagsort, $order = 'desc') {
    global $master, $activeUser;

    $TorrentCache = get_group_info($GroupID, true);
    $TorrentDetails = $TorrentCache[0];
    $TorrentList = $TorrentCache[1];
    $TorrentTags = $TorrentCache[2];

    list(, , , , , , , , , , , $userID, $Username, , , , , , , , $IsAnon) = $TorrentList[0];

    if (!$tagsort || !in_array($tagsort, ['uses', 'score', 'az', 'added'])) $tagsort = 'uses';

    $Tags = [];
    if ($TorrentTags != '') {
        foreach ($TorrentTags as $TagKey => $TagDetails) {
            list($TagName, $TagID, $TagUserID, $TagUsername, $TagUses, $TagPositiveVotes, $TagNegativeVotes,
                    $TagVoteUserIDs, $TagVoteUsernames, $TagVoteWays) = $TagDetails;

            $Tags[$TagKey]['name'] = $TagName;
            $Tags[$TagKey]['score'] = ($TagPositiveVotes - $TagNegativeVotes);
            $Tags[$TagKey]['id']= $TagID;
            $Tags[$TagKey]['userid']= is_anon($IsAnon && $Username===$TagUsername) ? '0': $TagUserID;

            $Tags[$TagKey]['username']= anon_username_ifmatch($TagUsername, $Username, $IsAnon) ;
            $Tags[$TagKey]['uses']= $TagUses;

            $TagVoteUsernames = explode('|', $TagVoteUsernames);
            $TagVoteWays = explode('|', $TagVoteWays);
            $VoteMsgs = [];
            $VoteMsgs[]= "$TagName (" . str_plural('use' , $TagUses).')';
            $VoteMsgs[]= "added by ".anon_username_ifmatch($TagUsername, $Username, $IsAnon);
            foreach ($TagVoteUsernames as $TagVoteKey => $TagVoteUsername) {
                if (!$TagVoteUsername) continue;
                $VoteMsgs[] = $TagVoteWays[$TagVoteKey] . " (". anon_username_ifmatch($TagVoteUsername, $Username, $IsAnon).")";
            }
            $Tags[$TagKey]['votes'] = implode("\n", $VoteMsgs) ;
        }
        if ($order!='desc') $order = 'asc';
        uasort($Tags, "sort_{$tagsort}_$order");
    }

    $IsUploader =  $userID == $activeUser['ID'];

    ob_start();
?>
                <ul id="torrent_tags_list" class="stats nobullet">

<?php
            foreach ($Tags as $TagKey=>$Tag) {
?>
                                <li id="tlist<?=$Tag['id']?>">
                                      <a href="/torrents.php?taglist=<?=$Tag['name']?>" style="float:left; display:block;" title="<?=$Tag['votes']?>"><?=display_str($Tag['name'])?></a>
                                      <div style="float:right; display:block; letter-spacing: -1px;">
        <?php 		if (!$master->repos->restrictions->isRestricted($activeUser['ID'], Luminance\Entities\Restriction::TAGGING) && (check_perms('site_vote_tag') || ($IsUploader && $activeUser['ID']==$Tag['userid']))) {  ?>
                                      <a title="Vote down tag '<?=$Tag['name']?>'" href="#tags" onclick="return Vote_Tag(<?="'{$Tag['name']}',{$Tag['id']}, $GroupID, 'down'"?>)" style="font-family: monospace;" >[-]</a>
                                      <span id="tagscore<?=$Tag['id']?>" style="width:10px;text-align:center;display:inline-block;"><?=$Tag['score']?></span>
                                      <a title="Vote up tag '<?=$Tag['name']?>'" href="#tags" onclick="return Vote_Tag(<?="'{$Tag['name']}',{$Tag['id']}, $GroupID, 'up'"?>)" style="font-family: monospace;">[+]</a>
                                      <a title="Add tag to notifications '<?=$Tag['name']?>'" href="#tags" onclick="return Quick_Notify_Tag(<?="'{$Tag['name']}',{$Tag['id']}"?>)" style="font-family: monospace;">[N]</a>

        <?php
                  } else {  // cannot vote on tags ?>
                                      <span style="width:10px;text-align:center;display:inline-block;" title="You do not have permission to vote on tags"><?=$Tag['score']?></span>
                                      <span style="font-family: monospace;" >&nbsp;&nbsp;&nbsp;</span>

        <?php 		} ?>
        <?php 		if (check_perms('users_warn')) { ?>
                                      <a title="Tag '<?=$Tag['name']?>' added by <?=$Tag['username']?>" href="/user.php?id=<?=$Tag['userid']?>" >[U]</a>
        <?php 		} ?>
        <?php 		if (check_perms('site_delete_tag')) { ?>
                                   <a title="Delete tag '<?=$Tag['name']?>'" href="#tags" onclick="return Del_Tag(<?="'{$Tag['id']}', $GroupID, '$tagsort'"?>)"   style="font-family: monospace;">[X]</a>
        <?php 		} else { ?>
                                      <span style="font-family: monospace;">&nbsp;&nbsp;&nbsp;</span>
        <?php 		} ?>
                                      </div>
                                      <br style="clear:both" />
                                </li>
<?php
            }
?>
                </ul>
<?php
    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}

// logs the staff in as 'checking'
function update_staff_checking($location="cyberspace", $dontactivate=false) {
    global $master, $activeUser;

    if ($dontactivate) {
        // if not already active dont activate
        $master->db->rawQuery(
            "SELECT UserID
               FROM staff_checking
              WHERE UserID = ?
                AND TimeOut > ?
                AND IsChecking = '1'",
            [$activeUser['ID'], time()]
        );
        if ($master->db->foundRows() == 0) return;
    }

    $sqltimeout = time() + 480;
    try {
        # This query sometimes fails, don't know why. :dunno:
        $master->db->rawQuery(
            "INSERT INTO staff_checking (UserID, TimeOut, TimeStarted, Location, IsChecking)
                  VALUES (?, '{$sqltimeout}', ?, ?, '1')
                      ON DUPLICATE KEY
                  UPDATE TimeOut = VALUES(TimeOut),
                         Location = VALUES(Location),
                         IsChecking = VALUES(IsChecking)",
            [$activeUser['ID'], sqltime(), $location]
        );
    } catch (SystemError $e) {}

    $master->cache->deleteValue('staff_checking');
    $master->cache->deleteValue('staff_lastchecked');
}

function print_staff_status() {
    global $master, $activeUser;

    $Checking = $master->cache->getValue('staff_checking');
    if ($Checking===false) {
        // delete old ones every 4 minutes
        $master->db->rawQuery(
            "UPDATE staff_checking
                SET IsChecking = '0'
              WHERE TimeOut <= ?",
            [time()]
        );
        $Checking = $master->db->rawQuery(
            "SELECT s.UserID,
                    u.Username,
                    s.TimeStarted,
                    s.TimeOut,
                    s.Location
               FROM staff_checking AS s
               JOIN users AS u ON u.ID = s.UserID
              WHERE s.IsChecking = '1'
           ORDER BY s.TimeStarted ASC"
        )->fetchAll(\PDO::FETCH_BOTH);
        $master->cache->cacheValue('staff_checking', $Checking, 240);
    }

    ob_start();
    $UserOn = false;
    $active=0;
    if (count($Checking)>0) {
        foreach ($Checking as $Status) {
            list($userID, $Username, $timeStart, $timeOut, $Location) =  $Status;
            $Own = $userID==$activeUser['ID'];
            if ($Own) $UserOn = true;

            $timeLeft = $timeOut - time();
            if ($timeLeft<0) {
                $master->cache->deleteValue('staff_checking');
                continue;
            }
            $active++;
?>
            <span class="staffstatus status_checking<?php if ($Own)echo' statusown';?>"
               title="<?=($Own?'Status: checking torrents ':"$Username is currently");
                        echo " $Location&nbsp;";
                        echo " (".time_diff($timeOut-480, 1, false, false, 0).") ";
                        if ($Own && $timeLeft<240) echo "(".time_diff($timeOut, 1, false, false, 0)." till time out)"; ?> ">
                <?php
                    if ($timeLeft<60) echo "<blink>";
                    if ($Own) echo "<a onclick=\"change_status('".($timeLeft<60?"1":"0")."')\">";
                    echo $Username;
                    if ($Own) echo "</a>";
                    if ($timeLeft<60) echo "</blink>";
                   ?>
            </span>
<?php
        }
    }

    if ($active==0) { // if no staff are checking now
            $LastChecked = $master->cache->getValue('staff_lastchecked');
            if ($LastChecked === false) {
                $LastChecked = $master->db->rawQuery(
                    "SELECT s.UserID,
                            u.Username,
                            s.TimeOut,
                            s.Location
                       FROM staff_checking AS s
                       JOIN users AS u ON u.ID=s.UserID
                       JOIN (
                          SELECT Max(TimeOut) as LastTimeOut
                            FROM staff_checking
                          ) AS x ON x.LastTimeOut = s.TimeOut"
                )->fetch(\PDO::FETCH_ASSOC);
                if ($master->db->foundRows() > 0) {
                    $master->cache->cacheValue('staff_lastchecked', $LastChecked);
                }
            }
            if ($LastChecked) $Str = time_diff($LastChecked['TimeOut']-480, 2, false)." ({$LastChecked['Username']})";
            else $Str = "never";
?>
            <span class="nostaff_checking" title="last check: <?=$Str?>">
                there are no staff checking torrents right now
            </span>
<?php
    }

    if (!$UserOn) {
?>
        <span class="staffstatus status_notchecking statusown"  title="Status: not checking">
            <a onclick="change_status('1')"> <?=$activeUser['Username']?> </a>
        </span>
<?php
    }

    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}

function validate_tags_number($tags) {
    global $master;

    if (!is_array($tags)) {
        $tags = split_tags($tags);
    }

    $tags = array_filter($tags, 'is_valid_tag');
    $tags = array_filter($tags, 'check_tag_input');
    $tags = array_unique($tags);

    return count($tags) >= (int) $master->options->MinTagNumber;
}

function get_events($EventIDs) {
    global $master;

    if (empty($EventIDs)) {
        return [];
    }

    $EventIDs = implode(', ', $EventIDs);
    $Events = $master->db->rawQuery(
        "SELECT ID,
                Title
           FROM events
          WHERE ID IN ({$EventIDs})
       ORDER BY ID"
    )->fetchAll(\PDO::FETCH_ASSOC);

    return $Events;
}

function get_active_events() {
    global $master;

    if (!$Events=$master->cache->getValue('active_events')) {
        $Events = $master->db->rawQuery(
            "SELECT ID
               FROM events
              WHERE StartTime <= ?
                AND EndTime >= ?
           ORDER BY ID",
            [sqltime(), sqltime()]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $master->cache->cacheValue('active_events', $Events, 600);
    }

    return $Events;
}

function get_torrent_events($torrentID) {
    global $master;

    if (!$Events=$master->cache->getValue("torrent_events_{$torrentID}")) {
        $Events = $master->db->rawQuery(
            "SELECT e.ID
               FROM torrents_events AS te
               JOIN events AS e ON e.ID = te.EventID
              WHERE TorrentID = ?", [$torrentID])->fetchAll(\PDO::FETCH_COLUMN);
        $master->cache->cacheValue("torrent_events_{$torrentID}", $Events, 600);
    }

    return $Events;
}

function get_seed_value($seeders, $size) {
    // constant values hardcoded for now, to be made configurable later
    $seeders_floor = 2.0;
    $seeders_ceil = 200.0;
    $size_floor = 10.0 * 1024 * 1024;
    $size_ceil = 100.0 * 1024 * 1024 * 1024;

    // values derived from the constants
    $seeders_mul = 9.0 / log($seeders_ceil / $seeders_floor);
    $size_mul = 9.0 / log($size_ceil / $size_floor);

    // values calculated based on this torrent
    $seeders_factor = 10.0 - (log($seeders / $seeders_floor) * $seeders_mul);
    $seeders_factor = min(max($seeders_factor, 1.0), 10.0);
    $size_factor = 1.0 + (log($size / $size_floor) * $size_mul);
    $size_factor = min(max($size_factor, 1.0), 10.0);

    $seed_value = $seeders_factor * $size_factor;
    return [$seed_value, $seeders_factor, $size_factor];
}

function getTokenTorrents($userID) {
    global $master;

    $tokenTorrents = $master->cache->getValue("users_tokens_{$userID}");
    if (empty($tokenTorrents)) {
        $tokenTorrents = $master->db->rawQuery(
            "SELECT TorrentID,
                    FreeLeech,
                    DoubleSeed
               FROM users_slots
              WHERE UserID = ?",
            [$userID]
        )->fetchAll(\PDO::FETCH_UNIQUE);
        $master->cache->cacheValue("users_tokens_{$userID}", $tokenTorrents);
    }
    return $tokenTorrents;
}
