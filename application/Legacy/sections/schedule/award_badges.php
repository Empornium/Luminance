<?php
/*
 * To be run from the scheduler to award badges
 *
 */

$autoActions = $master->db->rawQuery(
    "SELECT BadgeID,
            Badge,
            Rank,
            Title,
            Action,
            SendPM,
            Value,
            CategoryID,
            Description,
            Image
       FROM badges_auto AS ba
       JOIN badges AS b ON b.ID = ba.BadgeID
      WHERE ba.Active = 1
   ORDER BY b.Sort"
)->fetchAll(\PDO::FETCH_NUM);

foreach ($autoActions as $autoAction) {
    list($BadgeID, $Badge, $Rank, $Name, $Action, $SendPM, $Value, $CategoryID, $Description, $Image) = $autoAction;

    $SQL = false;
    $NOTIN =  "u.ID NOT IN (
                  SELECT DISTINCT u2.ID
                    FROM users_main AS u2
                    JOIN users_badges AS ub ON u2.ID = ub.UserID
                    JOIN badges AS b ON b.ID = ub.BadgeID
                   WHERE ub.BadgeID = ?
                      OR (b.Badge = ? AND b.Rank >= ?))";
    $params = [$BadgeID, $Badge, $Rank];

    switch ($Action) { // count things done by user
        case 'NumComments':
            $SQL = "SELECT u.ID,
                           Count(tc.ID)
                      FROM users_main AS u
                 LEFT JOIN torrents_comments AS tc ON tc.AuthorID = u.ID
                     WHERE Enabled = '1'
                       AND {$NOTIN}
                     GROUP BY u.ID
                    HAVING Count(tc.ID) >= ?";
            $params[] = $Value;
            break;

        case 'NumPosts':
            $SQL = "SELECT u.ID,
                           Count(fp.ID)
                      FROM users_main AS u
                 LEFT JOIN forums_posts AS fp ON fp.AuthorID = u.ID
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                     GROUP BY u.ID
                    HAVING Count(fp.ID) >= ?";
            $params[] = $Value;
            break;

        case 'RequestsFilled':
            $SQL = "SELECT u.ID,
                           Count(r.ID)
                      FROM users_main AS u
                 LEFT JOIN requests AS r ON r.FillerID = u.ID AND r.UserID != u.ID
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                     GROUP BY u.ID
                    HAVING Count(r.ID) >= ?";
            $params[] = $Value;
            break;

        case 'UploadedTB':
            $Value *= pow(2, 40); // value is in TB
            $SQL = "SELECT u.ID
                      FROM users_main AS u
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                       AND u.Uploaded >= ?";
            $params[] = $Value;
            break;

        case 'DownloadedTB':
            $Value *= pow(2, 40); // value is in TB
            $SQL = "SELECT u.ID
                      FROM users_main AS u
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                       AND u.Downloaded >= ?";
            $params[] = $Value;
            break;

        case 'NumUploaded':
            if ($CategoryID >0) { // category specific awards
                $SQL = "SELECT u.ID,
                               Count(t.ID)
                          FROM users_main AS u
                          JOIN torrents AS t ON t.UserID = u.ID
                          JOIN torrents_group AS tg ON tg.ID = t.GroupID
                         WHERE u.Enabled = '1'
                           AND tg.NewCategoryID = ?
                           AND {$NOTIN}
                      GROUP BY u.ID
                        HAVING Count(t.ID) >= ?";
                $params = array_merge([$CategoryID], $params, [$Value]);
            } else {           // count all torrents
                $SQL = "SELECT u.ID,
                               Count(t.ID)
                          FROM users_main AS u
                     LEFT JOIN torrents AS t ON t.UserID = u.ID
                         WHERE u.Enabled = '1'
                           AND {$NOTIN}
                      GROUP BY u.ID
                        HAVING Count(t.ID) >= ?";
                $params[] = $Value;
            }
            break;

        case 'NumNewTags': // unique tags
            $SQL = "SELECT u.ID,
                           Count(t.ID)
                      FROM users_main AS u
                 LEFT JOIN tags AS t ON t.UserID = u.ID
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                  GROUP BY u.ID
                    HAVING Count(t.ID) >= ?";
            $params[] = $Value;
            break;

        case 'NumTags': // tags added to torrents
            $SQL = "SELECT u.ID,
                           Count(t.ID)
                      FROM users_main AS u
                 LEFT JOIN torrents_tags AS t ON t.UserID = u.ID
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                  GROUP BY u.ID
                    HAVING Count(t.ID) >= ?";
            $params[] = $Value;
            break;

        case 'NumTagVotes':
            $SQL = "SELECT u.ID,
                           Count(t.ID)
                      FROM users_main AS u
                 LEFT JOIN torrents_tags_votes AS t ON t.UserID = u.ID
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                  GROUP BY u.ID
                    HAVING Count(t.ID) >= ?";
            $params[] = $Value;
            break;

        case 'MaxSnatches': // of a torrent this user uploaded
            $SQL = "SELECT u.ID,
                           Max(t.Snatched)
                      FROM users_main AS u
                 LEFT JOIN torrents AS t ON t.UserID = u.ID
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                  GROUP BY u.ID
                    HAVING Max(t.Snatched) >= ?";
            $params[] = $Value;
            break;

        case 'NumBounties': // num of dupes this user reported where they got the credit
            $SQL = "SELECT u.ID,
                           Count(r.ID)
                      FROM users_main AS u
                 LEFT JOIN reportsv2 AS r ON r.Type='dupe' AND r.Credit = '1' AND r.ReporterID = u.ID
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                  GROUP BY u.ID
                    HAVING Count(r.ID) >= ?";
            $params[] = $Value;
            break;

        case 'AccountAge': // num of days since the account was registered.
            $SQL = "SELECT u.ID,
                           i.JoinDate
                      FROM users_main AS u
                INNER JOIN users_info AS i ON i.UserID = u.ID
                     WHERE u.Enabled = '1'
                       AND {$NOTIN}
                  GROUP BY u.ID
                    HAVING DATEDIFF(CURDATE(), i.JoinDate) >= ?";
            $params[] = $Value;
            break;
    }

    if ($SQL) {
        $SQL .= " LIMIT 500";
        $userIDs = $master->db->rawQuery($SQL, $params)->fetchAll(\PDO::FETCH_COLUMN, 0);

        $CountUsers = count($userIDs);

        if ($CountUsers > 0) {

            $logmsg = "Awarding $Name ($Badge/$Rank) to $CountUsers users...\n";
            echo $logmsg;   // for debug output

            //FOR DEBUG ONLY
            //write_log($logmsg." (starting...)");

            $inQuery = implode(', ', array_fill(0, count($userIDs), '?'));
            $sqltime = sqltime();
            $comment = "{$sqltime} - Badge {$Name} {$Description} by Scheduler\n";
            $master->db->rawQuery(
                "UPDATE users_info
                    SET AdminComment = CONCAT(?, AdminComment)
                  WHERE UserID IN ({$inQuery})",
                [$comment, ...$userIDs]
            );

            $valuesQuery = implode(', ', array_fill(0, count($userIDs), '(?, ?, ?)'));
            $params = [];
            foreach ($userIDs as $userID) {
                $params = array_merge($params, [$userID, $BadgeID, $Description]);
            }
            $master->db->rawQuery(
                "INSERT INTO users_badges (UserID, BadgeID, Description)
                      VALUES {$valuesQuery}",
                $params
            );

            // remove lower ranked badges of same badge set
            $params = $userIDs;
            $params[] = $Badge;
            $params[] = $Rank;
            $master->db->rawQuery(
                "DELETE ub
                   FROM users_badges AS ub
                   JOIN badges AS b ON b.ID = ub.BadgeID
                  WHERE ub.UserID IN ({$inQuery})
                    AND b.Badge = ?
                    AND b.Rank < ?",
                $params
            );

            if ($SendPM) {
                send_pm(
                    $userIDs,
                    0,
                    "Congratulations you have been awarded the {$Name}",
                    "[center][br][br][img]/static/common/badges/{$Image}[/img][br][br][size=5][color=white][bg=#0261a3]"
                    ."[br]{$Description}[br][br][/bg][/color][/size][/center]"
                );
            }

            foreach ($userIDs as $userID) {
                $master->cache->deleteValue('user_badges_'.$userID);
                $master->cache->deleteValue('user_badges_'.$userID.'_limit');
            }
            
            write_log($logmsg.' ('.implode(', ',$userIDs).')');
        }
    }

}  // end foreach auto actions
