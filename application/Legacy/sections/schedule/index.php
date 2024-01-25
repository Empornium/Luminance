<?php

use Luminance\Entities\IP;
use Luminance\Entities\Reminder;

set_time_limit(840); // 14 mins
ob_end_flush();
gc_enable();

$sqltime = sqltime();

print("$sqltime\n");
$LockStatus = $master->db->rawQuery("SELECT GET_LOCK('{$master->settings->main->site_name}:scheduler', 3)")->fetchColumn();
if ($LockStatus != '1') {
    print("Scheduler failed to aquire lock (another scheduler process is running!)\n");
    die();
}

/*************************************************************************\
//--------------Schedule page -------------------------------------------//

This page is run every 15 minutes, by cron.

\*************************************************************************/

if (check_perms('admin_schedule')) {
    authorize();
    show_header();
    echo '<pre>';
}

$nextHour   = date('H');
$nextDay    = date('d');
$nextBiWeek = (date('d') < 22 && date('d') >= 8) ? 22 : 8;

$runHour   = $_GET['runhour']    ?? null;
$runDay    = $_GET['runday']     ?? null;
$runBiWeek = $_GET['runbiweek']  ?? null;

$schedule = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            NextHour,
            NextDay,
            NextBiWeekly
       FROM schedule"
)->fetch(\PDO::FETCH_NUM);
list($thisHour, $thisDay, $thisBiWeek) = $schedule;
if ($master->db->foundRows() == 0) {
    $master->db->rawQuery(
        "INSERT INTO schedule VALUES()"
    );
}

# Update the DB
$master->db->rawQuery(
    "UPDATE schedule
        SET NextHour = ?,
            NextDay = ?,
            NextBiWeekly = ?",
    [$nextHour, $nextDay, $nextBiWeek]
);

$minute = date('i');
$quarter = floor($minute/15);
print("Schedule quarter: $quarter\n");

/*************************************************************************\
//--------------Run every hour, quarter 1 ('15)--------------------------//

These functions are run every hour, on the hour.

\*************************************************************************/
if ($quarter == 1 || $runHour || isset($argv[2])) {
    // Don't interrupt the hourly!
    set_time_limit(3600);
    echo "Running hourly functions\n";

    // ---------- remove old torrents_files_temp (can get left behind by aborted uploads) -------------
    print("Remove old torrents_files_temp\n");
    $master->db->rawQuery(
        "DELETE
           FROM torrents_files_temp
          WHERE time < ?",
        [time_minus(3600*24*1)]
    );

    // ---------- remove old requests (and return bounties) -------------

    // return bounties for each voter
    print("Find bounties to return\n");
    $RemoveBounties = $master->db->rawQuery(
        "SELECT r.ID,
                r.Title,
                v.UserID,
                v.Bounty,
                r.UserID as OwnerID,
                r.Description,
                r.CategoryID,
                r.Image
           FROM requests as r
           JOIN requests_votes as v ON v.RequestID = r.ID
          WHERE TorrentID = '0'
            AND TimeAdded < ?",
        [time_minus(3600*24*91)]
    )->fetchAll(\PDO::FETCH_NUM);

    $RemoveRequestIDs = [];

    print("Do the actual bounty returning per user\n");
    foreach ($RemoveBounties as $BountyInfo) {
        // Include requests function only once, and only if there're bounties to return
        include_once SERVER_ROOT.'/Legacy/sections/requests/functions.php';
        list($RequestID, $Title, $userID, $Bounty) = $BountyInfo;
        // collect unique request ID's the old fashioned way
        if (!in_array($RequestID, $RemoveRequestIDs)) {
            $RemoveRequestIDs[] = $RequestID;
        }
        // return bounty and log in staff notes
        $bountySize = get_size($Bounty);
        $bountyComment = "{$sqltime} - Bounty of {$bountySize} returned from expired Request {$RequestID} ({$Title})\n";
        $master->db->rawQuery(
            "UPDATE users_info AS ui
               JOIN users_main AS um
                 ON um.ID = ui.UserID
                SET um.Uploaded = (um.Uploaded + ?),
                    ui.AdminComment = CONCAT(?, ui.AdminComment)
              WHERE ui.UserID = ?",
            [$Bounty, $bountyComment, $userID]
        );
        // send users who got bounty returned a PM
        expired_pm($BountyInfo);
    }

    print("Remove requests\n");
    if (count($RemoveRequestIDs)>0) {
        // log and update sphinx for each request
        $inQuery = implode(', ', array_fill(0, count($RemoveRequestIDs), '?'));
        $RemoveRequests = $master->db->rawQuery(
            "SELECT r.ID,
                    r.Title,
                    Count(v.UserID) AS NumUsers,
                    SUM( v.Bounty) AS Bounty,
                    r.GroupID,
                    r.Description,
                    r.UserID
               FROM requests as r
               JOIN requests_votes as v ON v.RequestID=r.ID
              WHERE r.ID IN ({$inQuery})
           GROUP BY r.ID",
            $RemoveRequestIDs
        )->fetchAll(\PDO::FETCH_BOTH);

        // delete the requests
        $master->db->rawQuery(
            "DELETE r, v, t, c
               FROM requests as r
          LEFT JOIN requests_votes as v ON r.ID=v.RequestID
          LEFT JOIN requests_tags AS t ON r.ID=t.RequestID
          LEFT JOIN requests_comments AS c ON r.ID=c.RequestID
              WHERE r.ID IN({$inQuery})",
            $RemoveRequestIDs
        );

        //log and update sphinx (sphinx call must be done after requests are deleted)
        foreach ($RemoveRequests as $Request) {
            //list($RequestID, $Title, $NumUsers, $Bounty, $GroupID) = $Request;

            send_pm($Request['UserID'], 0, "Your request has expired", "Your request ({$Request['Title']}) has now expired.\n\nPlease feel free to start a new request with the same [spoiler=details][code]{$Request['Description']}[/code][/spoiler]\n\nThanks, Staff.");

            write_log("Request ".$Request['ID']." (".$Request['Title'].") expired - returned total of ". get_size($Request['Bounty'])." to ".$Request['NumUsers']." users");

            $master->cache->deleteValue('request_votes_'.$Request['ID']);
            if ($Request['GroupID']) {
                $master->cache->deleteValue('requests_group_'.$Request['GroupID']);
            }
            update_sphinx_requests($Request['ID']);

        }
    }

    //------------- Award Badges ----------------------------------------//
    print("Include award_badges.php\n");
    include(SERVER_ROOT.'/Legacy/sections/schedule/award_badges.php');

    //------------- Record daily seedhours  ----------------------------------------//

    print("Record daily seedhours\n");
    $master->db->rawQuery(
        "UPDATE users_main AS um
           JOIN users_info AS ui ON um.ID = ui.UserID
           JOIN users_wallets AS uw ON um.ID = uw.UserID
            SET uw.Log = CONCAT(?, ' | +', uw.BalanceDaily, ' credits | seeded ', uw.SeedHoursDaily, ' hrs\n', uw.Log),
                ui.SeedHistory = CONCAT(?, ' | ', uw.SeedHoursDaily, ' hrs | up: ',
                              FORMAT(um.UploadedDaily/1073741824, 2) , ' GB | down: ',
                              FORMAT(um.DownloadedDaily/1073741824, 2) , ' GB | ', uw.BalanceDaily, ' credits\n', ui.SeedHistory),
                uw.SeedHoursDaily = 0.00,
                uw.BalanceDaily = 0.00 ,
                um.UploadedDaily = 0.00 ,
                um.DownloadedDaily = 0.00
          WHERE ui.RunHour = ?
            AND uw.SeedHoursDaily>0.00",
            [$sqltime, $sqltime, $thisHour]
    );

    //------------- Front page stats ----------------------------------------//

    //Love or hate, this makes things a hell of a lot faster
    print("Update front page snatches\n");
    if ($thisHour%2 == 0) {
        $snatchStats = $master->db->rawQuery(
            "SELECT COUNT(uid) AS Snatches
               FROM xbt_snatched"
        )->fetchColumn();
        $master->cache->cacheValue('stats_snatches', $snatchStats, 0);
    }


    print("Update front page peer stats\n");
    $PeerCount = $master->db->rawQuery(
        "SELECT IF(remaining=0, 'Seeding', 'Leeching') AS Type,
                COUNT(uid)
           FROM xbt_files_users
          WHERE active = 1
       GROUP BY Type"
    )->fetchAll(\PDO::FETCH_KEY_PAIR);
    $SeederCount = $peerCount['Seeding'] ?? 0;
    $LeecherCount = $peerCount['Leeching'] ?? 0;
    $master->cache->cacheValue('stats_peers', [$LeecherCount, $SeederCount], 0);

    if ($thisHour%6 == 0) { // 4 times a day record site history
            $userCount = $master->db->rawQuery(
                "SELECT COUNT(ID)
                   FROM users_main
                  WHERE Enabled='1'"
            )->fetchColumn();
            $master->cache->cacheValue('stats_user_count', $userCount, 0);

            $torrentCount = $master->db->rawQuery(
                "SELECT COUNT(ID)
                   FROM torrents"
            )->fetchColumn();
            $master->cache->cacheValue('stats_torrent_count', $torrentCount, 0);

            $master->db->rawQuery(
                "INSERT INTO site_stats_history (TimeAdded, Users, Torrents, Seeders, Leechers)
                      VALUES (?, ?, ?, ?, ?)",
                [sqltime(), $userCount, $torrentCount, $SeederCount, $LeecherCount]
            );
            $master->cache->deleteValue('site_stats');
      }

    print("Update front page user stats\n");
    $userStats['Day'] = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM users_main
          WHERE Enabled = '1'
            AND LastAccess > ?",
        [time_minus(3600*24*1)]
    )->fetchColumn();

    $userStats['Week'] = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM users_main
          WHERE Enabled = '1'
            AND LastAccess > ?",
        [time_minus(3600*24*7)]
    )->fetchColumn();

    $userStats['Month'] = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM users_main
          WHERE Enabled = '1'
            AND LastAccess > ?",
        [time_minus(3600*24*30)]
    )->fetchColumn();

    $master->cache->cacheValue('stats_users', $userStats, 0);

    //------------- Promote users -------------------------------------------//
    sleep(5);
    // Retrieve the from/to pairs of user classes starting.  Should return a number of rows equivalent to the number of AutoPromote classes minus 1
    $promotions = $master->db->rawQuery(
        "SELECT a.level AS `From`,
                b.id AS `To`,
                b.reqUploaded AS MinUpload,
                b.reqRatio AS MinRatio,
                IFNULL(b.reqTorrents,0) AS MinUploads,
                IFNULL(b.reqForumPosts,0) AS MinPosts,
                b.reqWeeks AS MinTime
           FROM (
                  SELECT @curRow := @curRow + 1 AS rn, id, level
                    FROM permissions
                    JOIN (
                            SELECT @curRow := -1) AS r
                             WHERE isAutoPromote='1'
                          ORDER BY Level
                         ) AS a
                   JOIN (
                            SELECT @curRow2 := @curRow2 + 1 AS rn,
                                   id,
                                   level,
                                   name,
                                   reqWeeks,
                                   reqUploaded,
                                   reqTorrents,
                                   reqForumPosts,
                                   reqRatio
                              FROM permissions
                              JOIN (SELECT @curRow2 := -1) AS r
                          WHERE isAutoPromote = '1'
                       ORDER BY Level
                        ) AS b ON a.rn = b.rn-1"
    )->fetchAll(\PDO::FETCH_ASSOC);

    print("Grab stats for promotions\n");
    // This god aweful SQL means that only old unreviewed or new reviewed torrents are counted in the promotion
    $TorrentCountSQL=" FROM torrents AS t
                  LEFT JOIN torrents_reviews AS tr ON tr.GroupID=t.GroupID
                      WHERE ((tr.Status = 'Okay' AND tr.Time=(
                             SELECT MAX(torrents_reviews.Time)
                               FROM torrents_reviews
                              WHERE torrents_reviews.GroupID=t.GroupID))
                         OR (tr.Status IS NULL AND t.Time <= DATE_SUB(NOW(), INTERVAL 1 WEEK)))
                        AND t.Snatched > 0
                        AND t.UserID=users_main.ID";

    foreach ($promotions as $promotion) {
        print("Runnng {$classes[$promotion['To']]['Name']} promotions\n");
        $userIDs = $master->db->rawQuery(
            "SELECT users_main.ID
               FROM users_main
               JOIN users_info ON users_main.ID = users_info.UserID
               JOIN permissions ON users_main.PermissionID = permissions.ID
              WHERE permissions.Level <= ?
                AND permissions.isAutoPromote = '1'
                AND users_main.Uploaded >= ?
                AND (users_main.Uploaded/users_main.Downloaded >= ? OR (users_main.Uploaded/users_main.Downloaded IS NULL))
                AND users_info.JoinDate <= DATE_SUB(NOW(), INTERVAL ? WEEK)
                AND (? = 0 OR (SELECT COUNT(DISTINCT t.ID) {$TorrentCountSQL}) >= ?) -- Short circuit, skip torrents unless needed
                AND (? = 0 OR (SELECT COUNT(*) FROM forums_posts WHERE authorid = users_main.ID) >= ?) -- Short circuit, skip posts unless needed
                AND users_main.Enabled = ?",
            [
                $promotion['From'],
                $promotion['MinUpload'],
                $promotion['MinRatio'],
                $promotion['MinTime'],
                $promotion['MinUploads'],
                $promotion['MinUploads'],
                $promotion['MinPosts'],
                $promotion['MinPosts'],
                '1'
            ]
        )->fetchAll(\PDO::FETCH_COLUMN);

        $NumPromotions = count($userIDs);

        if ($NumPromotions > 0) {
            $note  = sqltime();
            $note .= " - Class changed to [b][color=";
            $note .= str_replace(" ", "", $classes[$promotion['To']]['Name']);
            $note .= "]" ;
            $note .= make_class_string($promotion['To']);
            $note .= "[/color][/b] by System\n";
            foreach ($userIDs as $userID) {
                # Skip over warned users
                if ($master->repos->restrictions->isWarned($userID)) {
                    $NumPromotions--;
                    continue;
                }

                $master->repos->users->uncache($userID);
                $master->db->rawQuery(
                    "UPDATE users_info
                        SET AdminComment = CONCAT(?, AdminComment)
                      WHERE UserID = ?",
                        [$note, $userID]
                );
                $master->db->rawQuery(
                    "UPDATE users_main
                        SET PermissionID = ?
                      WHERE ID = ?",
                    [$promotion['To'], $userID]
                );
            }
            echo "Promoted {$NumPromotions} user".($NumPromotions>1?'s':'')." to ".make_class_string($promotion['To'])."\n";
        }
    }

    //------------- Expire invites ------------------------------------------//
    sleep(3);
    print("Expire invites\n");
    $userIDs = $master->db->rawQuery(
        'SELECT InviterID
           FROM invites
          WHERE Expires < ?',
        [$sqltime]
    )->fetchAll(\PDO::FETCH_COLUMN);

    foreach ($userIDs as $userID) {
        $Invites = $master->db->rawQuery(
            'SELECT Invites
               FROM users_main
              WHERE ID = ?',
            [$userID]
        )->fetchColumn();
        if ($Invites < 10) {
            $master->db->rawQuery(
                'UPDATE users_main
                    SET Invites = Invites + 1
                  WHERE ID = ?',
                [$userID]
            );
            $master->repos->users->uncache($userID);
        }
    }
    $master->db->rawQuery('DELETE FROM invites WHERE Expires < ?', [$sqltime]);

    //------------- Hide old requests ---------------------------------------//
    sleep(3);
    print("Hide old requests\n");
    $master->db->rawQuery(
        "UPDATE requests
            SET Visible = 0
          WHERE TimeFilled < (NOW() - INTERVAL 7 DAY)
            AND TimeFilled <> '0000-00-00 00:00:00'
            AND Visible = 1"
    );

    //------------- Remove dead peers ---------------------------------------//
    sleep(3);
    print("Remove dead peers\n");
    $deletedPeers = $master->db->rawQuery(
        "DELETE
           FROM xbt_files_users
          WHERE mtime < unix_timestamp(NOW() - INTERVAL 90 MINUTE)"
    )->rowCount();
    echo "Dead peers removed: {$deletedPeers}\n";

    //------------- Remove dead sessions ---------------------------------------//
    sleep(3);

    print("Remove dead sessions\n");
    $AgoDays = time_minus(3600*24*30);
    $sessions = $master->db->rawQuery(
        "SELECT ID,
                UserID
           FROM sessions
          WHERE Active = 1
            AND Updated < ?
            AND Flags & 1 = '1'",
        [$AgoDays]
    )->fetchAll(\PDO::FETCH_OBJ);
    foreach ($sessions as $session) {
        $master->cache->deleteValue('users_sessions_'.$session->UserID);
        $master->repos->sessions->uncache($session->ID);
    }

    $master->db->rawQuery(
        "DELETE
           FROM sessions
          WHERE Updated < ?
            AND Flags & 1 = '1'",
        [$AgoDays]
    );

    $AgoMins = time_minus(3600*24); // 24 hours for a non keep login
    $sessions = $master->db->rawQuery(
        "SELECT ID,
                UserID
           FROM sessions
          WHERE Active = 1
            AND Updated < ?
            AND Flags & 1 = '0'",
        [$AgoMins]
    )->fetchAll(\PDO::FETCH_OBJ);
    foreach ($sessions as $session) {
        $master->cache->deleteValue('users_sessions_'.$session->UserID);
        $master->repos->sessions->uncache($session->ID);
    }

    $master->db->rawQuery(
        "DELETE
           FROM sessions
          WHERE Updated < ?
            AND Flags & 1 = '0'", [$AgoMins]);

    //------------- Cleanup CID Entries -------------------------------------//
    $master->db->rawQuery("DELETE FROM clients WHERE Updated < ?", [$AgoDays]);

    //------------- Cleanup old disabled hits--------------------------------//
    print("Cleaning up old disabled hits\n");
    $time = time_minus(3600*24*3);
    $master->db->rawQuery("DELETE FROM disabled_hits WHERE Time < ?", [$time]);

    print("Various login/warning stats things\n");
    print("Lower request floods\n");
    //------------- Lower Request Floods ------------------------------------//
    if ($thisHour % 2 == 0) {
        $floods = $master->repos->requestfloods->find('Requests > ?', [0]);
        foreach ($floods as $flood) {
            $flood->Requests--;
            $master->repos->requestfloods->save($flood);
        }

        $floods = $master->repos->requestfloods->find('LastRequest < ?', [time_minus(3600*24*90)]);
        foreach ($floods as $flood) {
            $master->repos->requestfloods->delete($flood);
        }
    }
}

/*************************************************************************\
//--------------Run every hour, quarter 3 ('45)--------------------------//

These functions are run every hour, on the half hour.

\*************************************************************************/
if ($master->options->RatioWatchEnabled) {
    $ratio = $master->options->MinimumRatio;
    if ($quarter == 3 || $runHour || isset($argv[2])) {
        //------------- Ratio Watch Stuff ---------------------------------------//
        print("Find users to disable leeching for\n");
        $UserQuery = $master->db->rawQuery(
            "SELECT ID,
                    torrent_pass
               FROM users_info AS ui
               JOIN users_main AS um ON um.ID=ui.UserID
              WHERE (ui.RatioWatchEnds != '0000-00-00 00:00:00' OR ui.RatioWatchEnds IS NOT NULL)
                AND ui.RatioWatchDownload+10*1024*1024*1024 < um.Downloaded
                And um.Enabled = '1'
                AND um.can_leech = '1'"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $userIDs = array_column($UserQuery, 'ID');
        print("Disable leeching in DB\n");
        if (count($userIDs) > 0) {
            $inQuery = implode(', ', array_fill(0, count($userIDs), '?'));
            $master->db->rawQuery(
                "UPDATE users_info AS ui
                   JOIN users_main AS um
                     ON um.ID = ui.UserID
                    SET um.can_leech = '0',
                        ui.AdminComment = CONCAT(
                        '{$sqltime}',
                        ' - Leeching ability disabled by ratio watch system for downloading more than 10 gigs on ratio watch - required ratio: ',
                        um.RequiredRatio,
                        CHAR(10 using utf8),
                        ui.AdminComment
                    )
                  WHERE um.ID IN ({$inQuery})",
                $userIDs
            );
        }

        foreach ($userIDs as $userID) {
            $master->repos->users->uncache($userID);
            send_pm(
            $userID,
            0,
            "Your downloading rights have been disabled",
            "As you downloaded more than 10GB whilst on ratio watch your downloading rights have been revoked. You will not be able to download any torrents until your ratio is above your new required ratio.",
            ''
            );
            echo "Ratio watch leeching disabled (>10GB): {$userID}\n";
        }

        $Passkeys = array_column($UserQuery, 'torrent_pass');
        print("Disable leeching in tracker\n");
        foreach ($Passkeys as $Passkey) {
            sleep(1);
            $master->tracker->updateUser($Passkey, 0);
        }

        sleep(5);

        //------------- Ratio requirements
        $RatioRequirements = [
            [80*1024*1024*1024, 0.50, 0.40],
            [60*1024*1024*1024, 0.50, 0.30],
            [50*1024*1024*1024, 0.50, 0.20],
            [40*1024*1024*1024, 0.40, 0.10],
            [30*1024*1024*1024, 0.30, 0.05],
            [20*1024*1024*1024, 0.20, 0.00],
            [10*1024*1024*1024, 0.15, 0.00],
            [ 5*1024*1024*1024, 0.10, 0.00],
        ];

        $master->db->rawQuery(
            "UPDATE users_main AS um
               JOIN users_info AS ui ON um.ID = ui.UserID
                SET um.RequiredRatio = 0.50
              WHERE um.Downloaded > 100*1024*1024*1024
                AND um.RequiredRatio < 0.50
                AND ui.RunHour = ?",
            [$thisHour]
        );

        $DownloadBarrier = 100*1024*1024*1024;
        foreach ($RatioRequirements as $Requirement) {
            list($Download, $Ratio, $MinRatio) = $Requirement;
            $master->db->rawQuery(
                "UPDATE users_main AS um
                   JOIN users_info AS ui ON um.ID = ui.UserID
                    SET um.RequiredRatio = ?
                  WHERE ui.RunHour = ?
                    AND um.Downloaded >= ?
                    AND um.Downloaded < ?
                    AND um.Enabled='1'",
                [$Ratio, $thisHour, $Download, $DownloadBarrier]
            );
            $DownloadBarrier = $Download;
        }

        $master->db->rawQuery(
            "UPDATE users_main AS um
               JOIN users_info AS ui ON um.ID = ui.UserID
                SET um.RequiredRatio = 0.00
              WHERE ui.RunHour = ?
                AND Downloaded < 5*1024*1024*1024",
            [$thisHour]
        );

        print("Update Ratio Watch\n");
        // Here is where we manage ratio watch

        sleep(4);
        $OffRatioWatch = [];
        $OnRatioWatch = [];

        // Take users off ratio watch and enable leeching
        $UserQuery = $master->db->rawQuery(
            "SELECT um.ID,
                    torrent_pass
               FROM users_info AS ui
               JOIN users_main AS um ON um.ID = ui.UserID
              WHERE (um.Downloaded = 0 OR um.Uploaded/um.Downloaded >= um.RequiredRatio)
                AND (ui.RatioWatchEnds != '0000-00-00 00:00:00' OR ui.RatioWatchEnds IS NOT NULL)
                AND um.can_leech = '0'
                AND um.Enabled = '1'"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $OffRatioWatch = array_column($UserQuery, 'ID');
        if (count($OffRatioWatch)>0) {
            $inQuery = implode(', ', array_fill(0, count($OffRatioWatch), '?'));
            $master->db->rawQuery(
                "UPDATE users_info AS ui
                   JOIN users_main AS um
                     ON um.ID = ui.UserID
                    SET ui.RatioWatchEnds = NULL,
                        ui.RatioWatchDownload = '0',
                        um.can_leech = '1',
                        ui.AdminComment = CONCAT(
                            '{$sqltime}',
                            ' - Leeching re-enabled by adequate ratio.',
                            CHAR(10 using utf8),
                            ui.AdminComment
                        )
                  WHERE ui.UserID IN ({$inQuery})",
                $OffRatioWatch
            );
        }

        foreach ($OffRatioWatch as $userID) {
            $master->repos->users->uncache($userID);
            send_pm(
                $userID,
                0,
                "You have been taken off Ratio Watch",
                "Congratulations! Feel free to begin downloading again.\n To ensure that you do not get put on ratio watch again, please read the rules located [url=".$ratio."]here[/url].\n",
                ''
            );
            echo "Ratio watch off: {$userID}\n";
        }
        $Passkeys = array_column($UserQuery, 'torrent_pass');
        foreach ($Passkeys as $Passkey) {
            sleep(1);
            $master->tracker->updateUser($Passkey, 1);
        }

        // Take users off ratio watch
        $UserQuery = $master->db->rawQuery(
            "SELECT um.ID,
                    torrent_pass
               FROM users_info AS ui
               JOIN users_main AS um ON um.ID = ui.UserID
              WHERE (um.Downloaded = 0 OR um.Uploaded/um.Downloaded >= um.RequiredRatio)
                AND (ui.RatioWatchEnds != '0000-00-00 00:00:00' OR ui.RatioWatchEnds IS NOT NULL)
                AND um.Enabled = '1'"
        )->fetchAll(\PDO::FETCH_ASSOC);
        $OffRatioWatch = array_column($UserQuery, 'ID');
        if (count($OffRatioWatch)>0) {
            $inQuery = implode(', ', array_fill(0, count($OffRatioWatch), '?'));
            $master->db->rawQuery(
                "UPDATE users_info AS ui
                   JOIN users_main AS um
                     ON um.ID = ui.UserID
                    SET ui.RatioWatchEnds = '0000-00-00 00:00:00',
                        ui.RatioWatchDownload = '0',
                        um.can_leech = '1'
                  WHERE ui.UserID IN ({$inQuery})",
                $OffRatioWatch
            );
        }

        foreach ($OffRatioWatch as $userID) {
            $master->repos->users->uncache($userID);
            send_pm(
                $userID,
                0,
                "You have been taken off Ratio Watch",
                "Congratulations! Feel free to begin downloading again.\n To ensure that you do not get put on ratio watch again, please read the rules located [url=".$ratio."]here[/url].\n",
                ''
            );
            echo "Ratio watch off: {$userID}\n";
        }
        $Passkeys = array_column($UserQuery, 'torrent_pass');
        foreach ($Passkeys as $Passkey) {
            sleep(1);
            $master->tracker->updateUser($Passkey, 1);
        }

        // Put user on ratio watch if he doesn't meet the standards
        sleep(10);
        $OnRatioWatch = $master->db->rawQuery(
            "SELECT um.ID
               FROM users_info AS ui
               JOIN users_main AS um ON um.ID = ui.UserID
              WHERE um.Downloaded>0
                AND um.Uploaded/um.Downloaded < um.RequiredRatio
                AND (ui.RatioWatchEnds = '0000-00-00 00:00:00' OR ui.RatioWatchEnds IS NULL)
                AND um.Enabled = '1'
                AND um.can_leech = '1'"
        )->fetchAll(\PDO::FETCH_COLUMN);

        if (count($OnRatioWatch)>0) {
            $inQuery = implode(', ', array_fill(0, count($OnRatioWatch), '?'));
            $master->db->rawQuery(
                "UPDATE users_info AS ui
                   JOIN users_main AS um ON um.ID = ui.UserID
                    SET ui.RatioWatchEnds = ?,
                        ui.RatioWatchTimes = ui.RatioWatchTimes + 1,
                        ui.RatioWatchDownload = um.Downloaded
                  WHERE um.ID IN ({$inQuery})",
                array_merge([time_plus(3600 * 24 * 14)], $OnRatioWatch)
            );
        }

        foreach ($OnRatioWatch as $userID) {
            $master->repos->users->uncache($userID);
            send_pm(
                $userID,
                0,
                "You have been put on Ratio Watch",
                "This happens when your ratio falls below the requirements we have outlined in the rules located [url=".$ratio."]here[/url].\n For information about ratio watch, click the link above.",
                ''
            );
            echo "Ratio watch on: {$userID}\n";
        }

        sleep(5);

        }
}

if ($quarter == 3 || $runHour || isset($argv[2])) {

    print("Time for reminders\n");
    $countRemind = 0;
    $reminders = $master->db->rawQuery(
        "SELECT ID
           FROM users_reminders
          WHERE RemindDate <= NOW() - INTERVAL 60 MINUTE
            AND (Flags = 0 OR Flags = ?)",
        [Reminder::SHARED]
    )->fetchAll(\PDO::FETCH_COLUMN);

    if ($reminders != null) {
        foreach ($reminders as $remind) {
            $reminder = $master->repos->reminders->load($remind);
            if ($reminder instanceof Reminder) {
                if (!($reminder->getFlag(Reminder::COMPLETED) || $reminder->getFlag(Reminder::CANCELLED))) {
                    $created = $reminder->Created;
                    $created = $created->format('Y-m-d H:i:s');
                    $user = $master->repos->users->load($reminder->UserID);
                    $name = $user->Username;
                    $subject = 'Reminder: '.$reminder->Subject;
                    $body = 'Greetings![br]You have requested a reminder to be sent to you on '.$created.'[br][br]';
                    $body .= $reminder->Note;
                    send_pm($reminder->UserID, 0, $subject, $body);
                    // Also send a staff PM if it was shared
                    if ($reminder->getFlag(Reminder::SHARED)) {
                        $staffBody = $name.' has created a shared reminder on '.$created.'[br][br]';
                        $staffBody .= $reminder->Note;
                        send_staff_pm($subject, $staffBody, $reminder->StaffLevel);
                    }
                    $countRemind++;
                    $reminder->setFlags(Reminder::COMPLETED);
                    $this->reminders->save($reminder);
                }
            }
        }
        print($countRemind." reminders were sent\n");
    } else {
        print('No reminders due\n');
    }
}


/*************************************************************************\
//--------------Run every day -------------------------------------------//

These functions are run in the first 15 minutes of every day.

\*************************************************************************/

if ($thisDay != $nextDay || $runDay) {
    echo "Running daily functions\n";
    if ($thisDay%2 == 0) { // If we should generate the drive database (at the end)
        $GenerateDriveDB = true;
    }

    print("Updating daily site stats\n");
    $TorrentCountLastDay = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM torrents
          WHERE Time > ?",
        [time_minus(3600*24)]
    )->fetchColumn();
    $master->cache->cacheValue('stats_torrent_count_daily', $TorrentCountLastDay, 0); //inf cache

    $master->db->rawQuery("TRUNCATE TABLE users_geodistribution");
    $master->db->rawQuery(
        "INSERT INTO users_geodistribution (Code, Users)
         SELECT ipcc, COUNT(ID) AS NumUsers
           FROM users_main
          WHERE Enabled = '1'
            AND ipcc != ''
       GROUP BY ipcc
       ORDER BY NumUsers DESC"
    );

    $master->cache->deleteValue('geodistribution');

    // -------------- clean up users_connectable_status table - remove values older than 60 days

    $master->db->rawQuery(
        "DELETE FROM users_connectable_status
          WHERE Time < ?",
        [time_minus(3600*24*60)]
    );

    //------------- Disable downloading ability of users on ratio watch

    $UserQuery = $master->db->rawQuery(
        "SELECT um.ID,
                torrent_pass
           FROM users_info AS ui
           JOIN users_main AS um ON um.ID = ui.UserID
          WHERE (ui.RatioWatchEnds != '0000-00-00 00:00:00' OR ui.RatioWatchEnds IS NOT NULL)
            AND ui.RatioWatchEnds < ?
            AND um.Enabled = '1'
            AND um.can_leech != '0'",
        [$sqltime]
    )->fetchAll(\PDO::FETCH_ASSOC);

    $userIDs = array_column($UserQuery, 'ID');
    if (count($userIDs) > 0) {
        $inQuery = implode(', ', array_fill(0, count($userIDs), '?'));
        $master->db->rawQuery(
            "UPDATE users_info AS ui
               JOIN users_main AS um
                 ON um.ID = ui.UserID
                SET um.can_leech = '0',
                    ui.AdminComment = CONCAT(
                        ?,
                        ' - Leeching ability disabled by ratio watch system - required ratio: ',
                        um.RequiredRatio,
                        CHAR(10 using utf8),
                        ui.AdminComment
                    )
              WHERE um.ID IN ({$inQuery})",
            array_merge([$sqltime], $userIDs)
        );
    }

    foreach ($userIDs as $userID) {
        $master->repos->users->uncache($userID);
        send_pm($userID, 0, "Your downloading rights have been disabled", "As you did not raise your ratio in time, your downloading rights have been revoked. You will not be able to download any torrents until your ratio is above your new required ratio.", '');
        echo "Ratio watch disabled: {$userID}\n";
    }

    $Passkeys = array_column($UserQuery, 'torrent_pass');
    foreach ($Passkeys as $Passkey) {
        sleep(1);
        $master->tracker->updateUser($Passkey, 0);
    }

    print("Disable inactive users\n");
    //------------- Disable inactive user accounts --------------------------//
    sleep(5);
    // Send email
    $userIDs = $master->db->rawQuery(
        "SELECT ID
           FROM users_main AS um
           JOIN users_info AS ui ON um.ID = ui.UserID
          WHERE (
                        PermissionID IN ('".APPRENTICE."', '".PERV."')
                    AND LastAccess < ?
                    AND LastAccess > ?
                    AND LastAccess != '0000-00-00 00:00:00'
                    AND (InactivityException < NOW() OR InactivityException IS NULL)
                    AND Enabled != '2'
                ) OR (
                        PermissionID IN ('".GOOD_PERV."', '".GREAT_PERV."',  '".SEXTREME_PERV."', '".SMUT_PEDDLER."')
                    AND LastAccess < ?
                    AND LastAccess > ?
                    AND LastAccess != '0000-00-00 00:00:00'
                    AND (InactivityException < NOW() OR InactivityException IS NULL)
                    AND Enabled != '2'
                )",
        [time_minus(3600*24*110, true), time_minus(3600*24*111, true), time_minus(3600*24*354, true), time_minus(3600*24*355, true)]
    )->fetchAll(\PDO::FETCH_NUM);
    foreach ($userIDs as $userID) {
        $user = $master->repos->users->load($userID);
        if (is_null($user)) {
            continue;
        }
        $subject = "Your {$master->settings->main->site_name} account is about to be disabled";
        $variables = [
            'username' => $user->Username,
            'scheme'   => 'https',
            'time'     => time_diff($user->legacy['LastAccess']),
        ];
        $user->sendEmail($subject, 'inactivity_warning', $variables);
        unset($user);
    }

    $userIDs = $master->db->rawQuery(
        "SELECT ID
           FROM users_main AS um
           JOIN users_info AS ui ON um.ID = ui.UserID
          WHERE PermissionID IN ('".APPRENTICE."', '".PERV."')
            AND LastAccess < '".time_minus(3600*24*120)."'
            AND LastAccess != '0000-00-00 00:00:00'
            AND (InactivityException < NOW() OR InactivityException IS NULL)
            AND Enabled != '2'"
    )->fetchAll(\PDO::FETCH_COLUMN);

    $userCount = $master->db->foundRows();

    if ($userCount > 0) {
        print("Disabling {$userCount} inactive users after 120 days of inactivity\n");
        disable_users($userIDs, "Disabled for inactivity.", 3);
    }

    $userIDs = $master->db->rawQuery(
        "SELECT ID
           FROM users_main AS um
           JOIN users_info AS ui ON um.ID = ui.UserID
          WHERE PermissionID IN ('".GOOD_PERV."', '".GREAT_PERV."',  '".SEXTREME_PERV."', '".SMUT_PEDDLER."')
            AND LastAccess < '".time_minus(3600*24*365)."'
            AND LastAccess != '0000-00-00 00:00:00'
            AND (InactivityException < NOW() OR InactivityException IS NULL)
            AND Enabled != '2'"
    )->fetchAll(\PDO::FETCH_COLUMN);

    $userCount = $master->db->foundRows();

    if ($userCount > 0) {
        print("Disabling {$userCount} inactive users after 365 days of inactivity\n");
        disable_users($userIDs, "Disabled for inactivity.", 3);
    }

    //------------- Disable unconfirmed users ------------------------------//
    sleep(10);

    print("Disable unconfirmed\n");
    $affectedRows = $master->db->rawQuery(
        "UPDATE users_info AS ui
           JOIN users_main AS um ON um.ID = ui.UserID
            SET um.Enabled = '2',
                ui.BanDate = ?,
                ui.BanReason = '3',
                ui.AdminComment = CONCAT(
                    ?,
                    ' - Disabled for inactivity (never logged in)',
                    CHAR(10 using utf8),
                    ui.AdminComment
                )
          WHERE (um.LastAccess = '0000-00-00 00:00:00' OR um.LastAccess IS NULL)
            AND ui.JoinDate < ?
            AND um.Enabled != '2'",
        [$sqltime, $sqltime, time_minus(3600*24*7)]
    )->rowCount();
    $master->cache->decrementValue('stats_user_count', $affectedRows);


    print("Demote users\n");
    //------------- Demote users --------------------------------------------//
    // removed upload amount check - this means we can manually promote pervs (who have ratio >0.95) and they wont get auto demoted. its more or less impossible to reduce your upload amount so there is no loss of function anyway
    sleep(10);
    $Users = $master->db->rawQuery(
        'SELECT ID
           FROM users_main
          WHERE PermissionID IN('.GOOD_PERV.', '.GREAT_PERV.', '.SEXTREME_PERV.', '.SMUT_PEDDLER.')
            AND Uploaded/Downloaded < 0.95'
    )->fetchAll(\PDO::FETCH_BOTH);

    echo "demoted 1\n";
    foreach ($Users as $User) {
        $master->repos->users->uncache($User['ID']);

        $message  = ' - Class changed to [b][color=perv]';
        $message .= make_class_string(PERV);
        $message .= '[/color][/b] by System';

        $master->db->rawQuery(
            "UPDATE users_info
                SET AdminComment = CONCAT(
                        '{$sqltime}',
                        ?,
                        CHAR(10 using utf8),
                        AdminComment
                    )
                WHERE UserID = ?",
            [$message, $User['ID']]
        );
    }
    $master->db->rawQuery(
        'UPDATE users_main
            SET PermissionID = '.PERV.'
          WHERE PermissionID IN('.GOOD_PERV.', '.GREAT_PERV.', '.SEXTREME_PERV.', '.SMUT_PEDDLER.')
            AND Uploaded/Downloaded < 0.95'
    );
    echo "demoted 2\n";

    $Users = $master->db->rawQuery(
        'SELECT ID
           FROM users_main
          WHERE PermissionID IN('.PERV.', '.GOOD_PERV.', '.GREAT_PERV.', '.SEXTREME_PERV.', '.SMUT_PEDDLER.')
            AND Uploaded/Downloaded < 0.5'
    )->fetchAll(\PDO::FETCH_BOTH);
    echo "demoted 3\n";
    foreach ($Users as $User) {
        $master->repos->users->uncache($User['ID']);

        $message  = ' - Class changed to [b][color=apprentice]';
        $message .= make_class_string(APPRENTICE);
        $message .= '[/color][/b] by System';

        $master->db->rawQuery(
            "UPDATE users_info
                SET AdminComment = CONCAT(
                        '{$sqltime}',
                        ?,
                        CHAR(10 using utf8),
                        AdminComment
                    )
                WHERE UserID = ?",
            [$message, $User['ID']]
        );
    }
    $master->db->rawQuery(
        'UPDATE users_main
            SET PermissionID='.APPRENTICE.'
          WHERE PermissionID IN('.PERV.', '.GOOD_PERV.', '.GREAT_PERV.', '.SEXTREME_PERV.', '.SMUT_PEDDLER.')
            AND Uploaded/Downloaded < 0.5'
    );
    echo "demoted 4\n";

    print("Lock threads\n");
    //------------- Lock old threads ----------------------------------------//
    sleep(10);
    $threads = $master->db->rawQuery(
        "SELECT fp.ThreadID,
                MAX(fp.AddedTime) AS LastPostTime
           FROM forums_posts AS fp
           JOIN forums_threads AS ft ON ft.ID = fp.ThreadID
           JOIN forums AS f ON ft.ForumID = f.ID
          WHERE ft.IsLocked = '0'
            AND f.AutoLock = '1'
          GROUP BY fp.ThreadID
         HAVING LastPostTime < '".time_minus(3600*24*28)."'"
    )->fetchALl(\PDO::FETCH_ASSOC);
    $IDs = array_column($threads, 'ID');

    if (count($IDs) > 0) {
        $inQuery = implode(', ', array_fill(0, count($IDs), '?'));
        $master->db->rawQuery(
            "UPDATE forums_threads
                SET IsLocked = '1'
              WHERE ID IN ({$inQuery})",
            $IDs
        );
        sleep(2);
    }
    echo "Old threads locked\n";

    //------------- Delete dead torrents   ## torrent reaper ## ------------------------------------//

    sleep(10);
    echo "AutoDelete unseeded torrents: ". ($master->options->UnseededAutoDelete ?'On':'Off')."\n";
    if ($master->options->UnseededAutoDelete) {

    $i = 0;
    $torrentIDs = $master->db->rawQuery(
        "SELECT t.ID,
                t.GroupID,
                tg.Name,
                t.last_action,
                t.UserID
           FROM torrents AS t
           JOIN torrents_group AS tg ON tg.ID = t.GroupID
          WHERE t.last_action < '".time_minus(3600*24*28)."'
            AND t.last_action IS NOT NULL
             OR t.Time < '".time_minus(3600*24*2)."'
            AND t.last_action IS NULL"
    )->fetchAll(\PDO::FETCH_BOTH);
    echo "Found ".count($torrentIDs)." inactive torrents to be deleted.\n";

        $LogEntries = [];

    // Exceptions for inactivity deletion
    $InactivityExceptionsMade = $master->db->rawQuery(
        "SELECT UserID
           FROM users_info
          WHERE InactivityException >= NOW()"
    )->fetchAll(\PDO::FETCH_BOTH);
    $DeleteNotes = [];

        foreach ($torrentIDs as $torrentID) {
            list($ID, $GroupID, $Name, $LastAction, $userID) = $torrentID;
            if (array_key_exists($userID, $InactivityExceptionsMade) && (time() < $InactivityExceptionsMade[$userID])) {
                // don't delete the torrent!
                continue;
            }

            delete_torrent($ID, $GroupID);
            $LogEntries[] = "Torrent ".$ID." (".$Name.") was deleted for inactivity (unseeded)";

            if (!array_key_exists($userID, $DeleteNotes)) {
                $DeleteNotes[$userID] = ['Count' => 0, 'Msg' => ''];
            }

            $DeleteNotes[$userID]['Msg'] .= "\n$Name";
            $DeleteNotes[$userID]['Count']++;

            ++$i;
            if ($i % 500 == 0) {
                echo "{$i} inactive torrents removed.\n";
            }
        }
        echo "{$i} torrents deleted for inactivity.\n";

        foreach ($DeleteNotes as $userID => $MessageInfo) {
            $Singular = ($MessageInfo['Count'] == 1) ? true : false;
            send_pm($userID,0, $MessageInfo['Count'].' of your torrents '.($Singular?'has':'have').' been deleted for inactivity', ($Singular?'One':'Some').' of your uploads '.($Singular?'has':'have').' been deleted for being unseeded.  Since '.($Singular?'it':'they').' didn\'t break any rules (we hope), please feel free to re-upload '.($Singular?'it':'them').".\nThe following torrent".($Singular?' was':'s were').' deleted:'.$MessageInfo['Msg']);
        }
        unset($DeleteNotes);

        if (count($LogEntries) > 0) {
            foreach ($LogEntries as $LogEntry) {
                $master->db->rawQuery(
                    'INSERT INTO log (Message, Time)
                          VALUES (?, ?)',
                    [$LogEntry, $sqltime]
                );
            }
            echo "\nDeleted {$i} torrents for inactivity\n";
        }
    }

    print("Update top 10\n");
    // Daily top 10 history.
    $master->db->rawQuery("INSERT INTO top10_history (Date, Type) VALUES ('{$sqltime}', 'Daily')");
    $HistoryID = $master->db->lastInsertID();

    $Top10 = $master->cache->getValue('top10tor_day_10');
    if ($Top10 === false) {
        $Top10 = $master->db->rawQuery(
            "SELECT t.ID,
                    g.ID,
                    g.Name,
                    g.TagList,
                    t.Snatched,
                    t.Seeders,
                    t.Leechers,
                    ((t.Size * t.Snatched) + (t.Size * 0.5 * t.Leechers)) AS Data
               FROM torrents AS t
          LEFT JOIN torrents_group AS g ON g.ID = t.GroupID
              WHERE t.Seeders>0
                AND t.Time > ('$sqltime' - INTERVAL 1 DAY)
           ORDER BY (t.Seeders + t.Leechers) DESC
              LIMIT 10"
        )->fetchAll(\PDO::FETCH_NUM);
    }

    $i = 1;
    foreach ($Top10 as $Torrent) {
        list($torrentID, $GroupID, $GroupName, $TorrentTags,
                     $Snatched, $Seeders, $Leechers, $Data) = $Torrent;

        $DisplayName.= $GroupName;

        $TitleString = $DisplayName;

        $TagString = str_replace("|", " ", $TorrentTags);

        $master->db->rawQuery(
            "INSERT INTO top10_history_torrents (HistoryID, Rank, TorrentID, TitleString, TagString)
                  VALUES (?, ?, ?, ?, ?)",
            [$HistoryID, $i, $torrentID, $TitleString, $TagString]
        );
        $i++;
    }

    // Weekly top 10 history.
    // We need to haxxor it to work on a Sunday as we don't have a weekly schedule
    if (date('w') == 0) {
        $master->db->rawQuery("INSERT INTO top10_history (Date, Type) VALUES ('{$sqltime}', 'Weekly')");
        $HistoryID = $master->db->lastInsertID();

        $Top10 = $master->cache->getValue('top10tor_week_10');
        if ($Top10 === false) {
            $Top10 = $master->db->rawQuery(
                "SELECT t.ID,
                        g.ID,
                        g.Name,
                        g.TagList,
                        t.Snatched,
                        t.Seeders,
                        t.Leechers,
                        ((t.Size * t.Snatched) + (t.Size * 0.5 * t.Leechers)) AS Data
                   FROM torrents AS t
              LEFT JOIN torrents_group AS g ON g.ID = t.GroupID
                  WHERE t.Seeders>0
                    AND t.Time > ('{$sqltime}' - INTERVAL 1 WEEK)
               ORDER BY (t.Seeders + t.Leechers) DESC
                  LIMIT 10"
            )->fetchAll(\PDO::FETCH_NUM);
        }

        $i = 1;
        foreach ($Top10 as $Torrent) {
            list($torrentID, $GroupID, $GroupName, $TorrentTags,
                             $Snatched, $Seeders, $Leechers, $Data) = $Torrent;

            $DisplayName.= $GroupName;

            $TitleString = $DisplayName.' '.$ExtraInfo;

            $TagString = str_replace("|", " ", $TorrentTags);

            $master->db->rawQuery(
                "INSERT INTO top10_history_torrents (HistoryID, Rank, TorrentID, TitleString, TagString)
                      VALUES (?, ?, ?, ?, ?)",
                [$HistoryID, $i, $torrentID, $TitleString, $TagString]
            );
            $i++;
        }

        // Send warnings to uploaders of torrents that will be deleted this week
        $torrentIDs = $master->db->rawQuery(
            "SELECT t.ID,
                    t.GroupID,
                    tg.Name,
                    t.UserID
               FROM torrents AS t
               JOIN torrents_group AS tg ON tg.ID = t.GroupID
               JOIN users_info AS u ON u.UserID = t.UserID
              WHERE t.last_action < NOW() - INTERVAL 20 DAY
                AND t.last_action != 0
                AND u.UnseededAlerts = '1'
           ORDER BY t.last_action ASC"
        )->fetchAll(\PDO::FETCH_NUM);
        $TorrentAlerts = [];
        foreach ($torrentIDs as $torrentID) {
            list($ID, $GroupID, $Name, $userID) = $torrentID;

            if (array_key_exists($userID, $InactivityExceptionsMade) && (time() < $InactivityExceptionsMade[$userID])) {
                // don't notify exceptions
                continue;
            }

            if (!array_key_exists($userID, $TorrentAlerts)) {
                $TorrentAlerts[$userID] = ['Count' => 0, 'Msg' => ''];
            }

            $TorrentAlerts[$userID]['Msg'] .= "\n[url=/torrents.php?torrentid=$ID]".$Name."[/url]";
            $TorrentAlerts[$userID]['Count']++;
        }
        foreach ($TorrentAlerts as $userID => $MessageInfo) {
            send_pm($userID, 0, 'Unseeded torrent notification', "{$MessageInfo['Count']} of your upload".($MessageInfo['Count']>1?'s':'')." will be deleted for inactivity soon. Unseeded torrents are deleted after 4 weeks. If you still have the files, you can seed your uploads by ensuring the torrents are in your client and that they aren't stopped. You can view the time that a torrent has been unseeded by clicking on the torrent description line and looking for the \"Last active\" time. For more information, please go [url='{$torrentunseeded}']here[/url].\n\nThe following torrent".($MessageInfo['Count']>1?'s':'')." will be removed for inactivity:{$MessageInfo['Msg']}\n\nIf you no longer wish to receive these notifications, please disable them in your profile settings.");
        }
    }

    sleep(5);
    print("Cleanup old system PMs\n");
    $systemPMs = $master->db->rawQuery(
        "DELETE pm, pc, pcu
           FROM pm_messages AS pm
           JOIN pm_conversations AS pc ON pm.ConvID = pc.ID
           JOIN pm_conversations_users AS pcu ON pm.ConvID = pcu.ConvID
          WHERE pcu.SentDate < DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND Subject REGEXP '".
                "Comment received on your upload|".
                "been deleted for i|".
                "Re-seed request for torrent|".
                "You have been put on Ratio Watch|".
                "You have been taken off Ratio Watch|".
                "Your downloading rights have been disabled|".
                "Bounty returned from expired request|".
                "Unseeded torrent notification|".
                "You filled the request|".
                "One of your torrents was used to fill request|".
                "The request .* has been filled|".
                "A request you filled has been unfilled|".
                "A request which was filled with one of your torrents has been unfilled|".
                "A request you created has been unfilled|".
                "Bonus Shop - You received a gift|".
                "A torrent you were a peer on was deleted|".
                "Security Alert'"
    );

    print("Deleted {$systemPMs->rowCount()} old system PMs\n");

    //-- Regenerate TagLists --//
    /* Disabled for now, takes way too long
    $count = $master->db->rawQuery(
        "SELECT MAX(ID) FROM torrents_group"
    )->fetchColumn();
    $step = 10000;
    $affected = 0;
    for ($batch = 0; $batch <= $count; $batch+=$step) {
        $affected += $master->db->rawQuery(
            "UPDATE torrents_group AS tg
               JOIN (SELECT REPLACE(GROUP_CONCAT(tags.Name ORDER BY  (t.PositiveVotes-t.NegativeVotes) DESC SEPARATOR ' '), '.', '_') AS TagList,
                            t.GroupID
                       FROM torrents_tags AS t
                 INNER JOIN tags ON tags.ID=t.TagID
                      WHERE t.GroupID BETWEEN ? AND ?
                   GROUP BY t.GroupID) AS taglists ON tg.ID=taglists.GroupID
                SET tg.TagList=taglists.TagList",
            [$batch, $batch+$step]
        )->rowCount();
    }
    print("Updated {$affected} taglists\n");

    //-- Correct tag uses counts --//
    $count = $master->db->rawQuery(
        "SELECT MAX(ID) FROM tags"
    )->fetchColumn();
    $step = 10000;
    $affected = 0;
    for ($batch = 0; $batch <= $count; $batch+=$step) {
        $affected += $master->db->rawQuery(
            "UPDATE tags
               JOIN (SELECT COUNT(*) AS Uses,
                            TagID
                       FROM torrents_tags
                      WHERE TagID BETWEEN ? AND ?
                   GROUP BY TagID) AS tt ON tt.TagID=tags.ID
                SET tags.Uses=tt.Uses
              WHERE tags.Uses!=tt.Uses",
            [$batch, $batch+$step]
        )->rowCount();
    }
    print("Updated {$affected} tag usage counts\n");
    */

   //-- Cleanup orphaned synonyms --//
   $affected = $master->db->rawQuery(
        "DELETE ts FROM tags_synonyms AS ts LEFT JOIN tags AS t on ts.TagID=t.ID WHERE t.ID IS NULL"
   )->rowCount();
   print("Removed {$affected} orphaned tag synonyms\n");
}

/*************************************************************************\
//--------------Run twice per month -------------------------------------//

These functions are twice per month, on the 8th and the 22nd.

\*************************************************************************/

if ($thisBiWeek != $nextBiWeek || $runBiWeek) {
    echo "Running bi-weekly functions\n";

    //------------- Cleanup bookmarks ---------------------------------------//
    sleep(5);
    echo "Removing dead bookmarks\n";
    $master->db->rawQuery(
        "DELETE bt
           FROM bookmarks_torrents AS bt
      LEFT JOIN torrents AS t ON t.GroupID = bt.GroupID
          WHERE t.ID IS NULL");

    //------------- Give out invites! ---------------------------------------//
    sleep(5);
    /*
    PUs have a cap of 2 invites.  Elites have a cap of 4.
    Every month, on the 8th and the 22nd, each PU/Elite User gets one invite up to their max.

    Then, every month, on the 8th and the 22nd, we give out bonus invites like this:

    Every Power User or Elite whose total invitee ratio is above 0.75 and total invitee upload is over 2 gigs gets one invite.
    Every Elite whose total invitee ratio is above 2.0 and total invitee upload is over 10 gigs gets one more invite.
    Every Elite whose total invitee ratio is above 3.0 and total invitee upload is over 20 gigs gets yet one more invite.

    This cascades, so if you qualify for the last bonus group, you also qualify for the first two and will receive three bonus invites.

    The bonus invites cannot put a user over their cap.

    */

    $GiveOutInvites = false;

    if ($GiveOutInvites) {
        echo "Generating invites\n";
        $userIDs = $master->db->rawQuery(
            "SELECT ID
               FROM users_main AS um
               JOIN users_info AS ui on ui.UserID=um.ID
              WHERE um.Enabled='1
                AND ((um.PermissionID = ? AND um.Invites < 2)
                 OR (um.PermissionID = ? AND um.Invites < 4))",
            [GOOD_PERV, SEXTREME_PERV]
        )->fetchAll(\PDO::FETCH_COLUMN);
        if (count($userIDs) > 0) {
            foreach ($userIDs as $userID) {
                // Skip over users with invites disabled
                if ($master->repos->restrictions->isRestricted($user, Luminance\Entities\Restriction::INVITE)) {
                    unset($userIDs[$userID]);
                    continue;
                }
                $master->repos->users->uncache($userID);
            }
            $inQuery = implode(', ', array_fill(0, count($userIDs), '?'));
            $master->db->rawQuery("UPDATE users_main SET Invites = Invites + 1 WHERE ID IN ({$inQuery})", $userIDs);
        }

        $BonusReqs = [
            [0.75, 2*1024*1024*1024],
            [2.0, 10*1024*1024*1024],
            [3.0, 20*1024*1024*1024],
        ];

        // Since MySQL doesn't like subselecting from the target table during an update, we must create a temporary table.

        $master->db->rawQuery(
            "CREATE TEMPORARY TABLE temp_sections_schedule_index
            SELECT SUM(Uploaded) AS Upload,
                   SUM(Downloaded) AS Download,
                   Inviter
              FROM users_main AS um
              JOIN users_info AS ui ON ui.UserID = um.ID
          GROUP BY Inviter"
        );

        foreach ($BonusReqs as $BonusReq) {
            list($Ratio, $Upload) = $BonusReq;
            $userIDs = $master->db->rawQuery(
                "SELECT ID
                   FROM users_main AS um
                   JOIN users_info AS ui ON ui.UserID=um.ID
                   JOIN temp_sections_schedule_index AS u ON u.Inviter = um.ID
                  WHERE u.Upload>$Upload AND u.Upload/u.Download>$Ratio
                    AND um.Enabled = '1'
                    AND ((um.PermissionID = ".GOOD_PERV." AND um.Invites < 2) OR (um.PermissionID = ".SEXTREME_PERV." AND um.Invites < 4))"
            )->fetchAll(\PDO::FETCH_COLUMN);
            if (count($userIDs) > 0) {
                foreach ($userIDs as $userID) {
                    // Skip over users with invites disabled
                    if ($master->repos->restrictions->isRestricted($user, Luminance\Entities\Restriction::INVITE)) {
                        unset($userIDs[$userID]);
                        continue;
                    }
                    $master->repos->users->uncache($userID);
                }
                $inQuery = implode(', ', array_fill(0, count($userIDs), '?'));
                $master->db->rawQuery("UPDATE users_main SET Invites = Invites + 1 WHERE ID IN ({$inQuery})", $userIDs);
            }
        }

    } // end give out invites

    if ($thisBiWeek == 8) {
        $master->db->rawQuery("TRUNCATE TABLE top_snatchers;");
        $master->db->rawQuery(
            "INSERT INTO top_snatchers (UserID)
                  SELECT uid
                    FROM xbt_snatched
                GROUP BY uid
                ORDER BY COUNT(uid) DESC
                   LIMIT 1000;"
        );
    }
}

//---  moved this run every 15 mins section to the end as if xbt_peers_history gets too big (>~2.6 million? records on our server...)
//          it can screw the scheduler

/*************************************************************************\
//--------------Run every time ------------------------------------------//

These functions are run every time the script is executed (every 15
minutes).

\*************************************************************************/

echo "Running every-time functions\n";
$sqltime = sqltime();
sleep(5);

//------------- Apply IP range bans to curb brute force attacks quicker ------//
print("Apply IPv4 range bans\n");
try {
    $IPv4Ranges = $master->db->rawQuery(
        "SELECT  INET_NTOA(INET_ATON(INET6_NTOA(ip.StartAddress)) & 0xFFFFFF00) AS Subnet,
                 COUNT(*) AS UniqueAddresses
           FROM request_flood AS rf
           JOIN ips AS ip ON ip.ID = rf.IPID
          WHERE LastRequest >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND LENGTH(StartAddress) = 4
       GROUP BY Subnet
       HAVING  UniqueAddresses > 10"
    )->fetchAll(\PDO::FETCH_OBJ);

    foreach ((array)$IPv4Ranges as $range) {
        $ip = $master->repos->ips->getOrNew($range->Subnet, 24);
        if ($ip instanceof IP) {
            $master->repos->ips->ban($ip, 'Automated range ban', 8);
            $ip->ActingUserID = null;
            $ip->Reason = 'Automated range ban';
            $master->repos->ips->save($ip);
        }
    }
    sleep(5);
} catch (Exception $e) {
    print("Failed to create IPv4 range ban for {$range->Subnet}/24\n");
}

print("Apply IPv6 range bans\n");
try {
    $IPv6Ranges = $master->db->rawQuery(
        "SELECT INET6_NTOA(UNHEX(CONCAT(SUBSTR(HEX(StartAddress), 1, 16), REPEAT('0', 16)))) AS Subnet,
                COUNT(*) AS UniqueAddresses
           FROM request_flood AS rf
           JOIN ips AS ip ON ip.ID = rf.IPID
          WHERE LastRequest >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND LENGTH(StartAddress) = 16
       GROUP BY Subnet
       HAVING  UniqueAddresses > 10"
    )->fetchAll(\PDO::FETCH_OBJ);

    foreach ((array)$IPv6Ranges as $range) {
        $ip = $master->repos->ips->getOrNew($range->Subnet, 64);
        if ($ip instanceof IP) {
            $master->repos->ips->ban($ip, 'Automated range ban', 8);
            $ip->ActingUserID = null;
            $ip->Reason = 'Automated range ban';
            $master->repos->ips->save($ip);
        }
    }
    sleep(5);
} catch (Exception $e) {
    print("Failed to create IPv6 range ban for {$range->Subnet}/64\n");
}

print("Clearing old IP bans\n");
$master->db->rawQuery("UPDATE ips SET Banned = FALSE WHERE Banned = TRUE AND BannedUntil < '{$sqltime}' AND BannedUntil IS NOT NULL");

sleep(5);

// check any pending ducky awards
print("Award Golden Ducky\n");
$torrentids = award_ducky_pending();
if (!empty($torrentids)) {
    print('to torrents: '. implode(', ', $torrentids)."\n");
}

sleep(5);

//------------- Expire old FL Tokens and clear cache where needed ------//
print("Expire old FL tokens\n");
$userIDs = $master->db->rawQuery("SELECT DISTINCT UserID from users_slots WHERE FreeLeech < '$sqltime' AND DoubleSeed < '$sqltime'")->fetchAll(\PDO::FETCH_COLUMN);
foreach ($userIDs as $userID) {
    $master->cache->deleteValue("users_tokens_{$userID}");
}

//-------Gives credits to users with active torrents-------------------------//
sleep(3);
print("Update credits\n");
$CAP = BONUS_TORRENTS_CAP;
$master->db->rawQuery(
    "UPDATE users_wallets AS uw
       JOIN (
           SELECT xbt_files_users.uid AS UserID,
                  (ROUND((SQRT(8.0 * ((if (COUNT(*) < $CAP, COUNT(*), $CAP))  /20) + 1.0) - 1.0) / 2.0 *20) * 0.25) AS SeedCount,
                  (COUNT(*) * 0.25) AS SeedHours
             FROM xbt_files_users
            WHERE xbt_files_users.remaining = 0
              AND xbt_files_users.active = 1
              AND xbt_files_users.uid
         GROUP BY xbt_files_users.uid
       ) AS s ON s.UserID=uw.UserID
        SET uw.Balance=uw.Balance+SeedCount,
            uw.BalanceDaily=uw.BalanceDaily+SeedCount,
            uw.SeedHours=uw.SeedHours+s.SeedHours,
            uw.SeedHoursDaily=uw.SeedHoursDaily+s.SeedHours"
);


//------------ record ip's/ports for users and refresh time field for existing status records -------------------------//
sleep(3);
$nowtime = time();

print("Update connectable status\n");
$count = $master->db->rawQuery(
    "SELECT MAX(ID) FROM users"
)->fetchColumn();
$step = 10000;
for ($batch = 0; $batch <= $count; $batch+=$step) {
    $master->db->rawQuery(
        "INSERT INTO users_connectable_status (UserID, IP, Time)
         SELECT uid, INET6_NTOA(ipv4), '$nowtime'
           FROM xbt_files_users
          WHERE uid BETWEEN ? AND ?
       GROUP BY uid, ipv4
             ON DUPLICATE KEY
         UPDATE Time = '$nowtime'",
        [$batch, $batch+$step]
    );
}

//------------Remove inactive peers every 15 minutes-------------------------//
sleep(3);
print("Remove inactive peers\n");
$deletedPeers = $master->db->rawQuery(
    "DELETE
       FROM xbt_files_users
      WHERE active='0'"
)->rowCount();
echo "Inactive peers removed: {$deletedPeers}\n";

//------------- Remove torrents that have expired their warning period every 15 minutes ----------//

echo "AutoDelete torrents marked for deletion: ". ($master->options->MFDAutoDelete ?'On':'Off')."\n";
if ($master->options->MFDAutoDelete) {
    include(SERVER_ROOT.'/Legacy/sections/tools/managers/mfd_functions.php');

    $Torrents = get_torrents_under_review('warned', true);
    $NumTorrents = count($Torrents);
    //echo "Num to auto-delete: {$NumTorrents}\n";
    if ($NumTorrents>0) {
        $NumDeleted = delete_torrents_list($Torrents);
        echo "Num of torrents auto-deleted: {$NumDeleted}\n";
    }
}

//-- Ban passkey leakers --//
$userIDs = $master->db->rawQuery(
         "SELECT um.ID
            FROM xbt_files_users AS xfu
            JOIN users_main AS um ON um.ID=xfu.uid
           WHERE um.Enabled='1'
           GROUP BY xfu.uid
          HAVING COUNT(DISTINCT xfu.useragent) >= :clients
             AND COUNT(DISTINCT xfu.ipv4) >= :ips",
    [':clients' => $master->options->LeakingClients,
     ':ips'     => $master->options->LeakingIPs])->fetchAll(\PDO::FETCH_COLUMN);

if (is_array($userIDs) && count($userIDs) > 0) {
    disable_users($userIDs, "Disabled for suspected passkey leak.", 2);
    foreach ($userIDs as $userID) {
        echo "Passkey leaking user disabled : {$userID}\n";
    }
}


//------------ Remove unwatched and unwanted speed records

$CLEAN_SPEED_RECORDS=true;
// run each time, once a day (00:30) recreate the table
if ($CLEAN_SPEED_RECORDS && !($thisHour == 0 && $quarter == 2)) {
    print("Delete old speed records\n");
    $master->db->rawQuery("DROP TABLE IF EXISTS temp_copy"); // jsut in case!

    // On hourly run a delete quick, once a day we'll do a
    // recreate to try to preserve performance here.
    $DeletedRecords = $master->db->rawQuery(
        "DELETE x FROM xbt_peers_history AS x
      LEFT JOIN users_watch_list AS uw ON uw.UserID=x.uid
      LEFT JOIN torrents_watch_list AS tw ON tw.TorrentID=x.fid
          WHERE uw.UserID IS NULL
            AND tw.TorrentID IS NULL
            AND x.upspeed <  :keepSpeed
            AND x.mtime   <= :keepTime",
            [':keepSpeed' => $master->options->KeepSpeed,
             ':keepTime' => (time() - ( $master->options->DeleteRecordsMins * 60))])->rowCount();

    print("Deleted ".$DeletedRecords." speed records\n");

} elseif ($CLEAN_SPEED_RECORDS) {
    // as we are deleting way way more than keeping, and to avoid exceeding lockrow size in innoDB we do it another way:
    print("Rotate Speed Record Table\n");
    $master->db->rawQuery("DROP TABLE IF EXISTS temp_copy"); // jsut in case!
    $master->db->rawQuery("CREATE TABLE `temp_copy` LIKE `xbt_peers_history`");
    $master->db->rawQuery("ALTER TABLE `temp_copy` DISABLE KEYS");

    $RecordsBefore = $master->db->rawQuery("SELECT COUNT(*) FROM xbt_peers_history")->fetchColumn();

    // insert the records we want to keep into the temp table
    $master->db->rawQuery(
        "INSERT INTO temp_copy (
         SELECT x.*
           FROM xbt_peers_history AS x
      LEFT JOIN users_watch_list AS uw ON uw.UserID=x.uid
      LEFT JOIN torrents_watch_list AS tw ON tw.TorrentID=x.fid
          WHERE uw.UserID IS NOT NULL
             OR tw.TorrentID IS NOT NULL
             OR x.upspeed >= ?
             OR x.mtime   >  ?)",
             [$master->options->KeepSpeed, (time() - ($master->options->DeleteRecordsMins * 60))]
    );
    // Enable keys after data insertion
    $master->db->rawQuery("ALTER TABLE `temp_copy` ENABLE KEYS");

    //Use RENAME TABLE to atomically move the original table out of the way and rename the copy to the original name:
    $master->db->rawQuery("RENAME TABLE xbt_peers_history TO temp_old, temp_copy TO xbt_peers_history");
    //Drop the original table:
    $master->db->rawQuery("DROP TABLE temp_old");

    $RecordsAfter = $master->db->rawQuery("SELECT COUNT(*) FROM xbt_peers_history")->fetchColumn();
    print("$RecordsBefore speed records before delete\n");
    print("$RecordsAfter speed records after delete\n");
}

echo "-------------------------\n\n";
if (check_perms('admin_schedule')) {
    echo '</pre>';
    show_footer();
}
