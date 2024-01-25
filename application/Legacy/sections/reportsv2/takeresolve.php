<?php
/*
 * This is the backend of the AJAXy reports resolve (When you press the shiny submit button).
 * This page shouldn't output anything except in error, if you do want output, it will be put
 * straight into the table where the report used to be. Currently output is only given when
 * a collision occurs or a POST attack is detected.
 */

if (!check_perms('admin_reports')) {
    error(403);
}
authorize();

use Luminance\Entities\Torrent;
use Luminance\Entities\Restriction;

// Don't escape: Log message, Admin message
$Escaped = $_POST;

// Fake it so the PM works later
if (!array_key_exists('extras_id', $Escaped)) {
    if (preg_match_all("/".TORRENT_REGEX."/is", $_POST['log_message'], $matches)) {
        $ExtraIDs = $matches[2];
        $Escaped['extras_id'] = implode(' ', $ExtraIDs);
    }
}

//If we're here from the delete torrent page instead of the reports page.
if (!isset($Escaped['from_delete']) || $Escaped['from_delete']==0) {
    $Report = true;
} elseif (!is_integer_string($Escaped['from_delete'])) {
    echo 'Hax occured in from_delete';
} else {
    $Report = false;
}

$PMMessage = $_POST['uploader_pm'];

if (is_integer_string($Escaped['reportid'])) {
    $ReportID = $Escaped['reportid'];
} else {
    echo 'Hax occured in the reportid';
    die();
}

if ($Escaped['pm_type'] != 'Uploader') {
    $Escaped['uploader_pm'] = '';
}

$UploaderID = (int) $Escaped['uploaderid'];
if (!is_integer_string($UploaderID)) {
    echo 'Hax occuring on the uploaderid';
    die();
}

if (isset($Escaped['reporterid'])) {
    $ReporterID = (int) $Escaped['reporterid'];
    if (!is_integer_string($ReporterID)) {
          echo 'Hax occuring on the reporterid';
          die();
    }
}

$Warning = (int) $Escaped['warning'];
if (!is_integer_string($Warning)) {
    echo 'Hax occuring on the warning';
    die();
}

$torrentID = $Escaped['torrentid'];
$RawName = $Escaped['raw_name'];
$report = $master->repos->reports->load($ReportID);

// GroupID is only used to delete the torrent group cache key.
$GroupID = $master->db->rawQuery(
    "SELECT GroupID
       FROM torrents
      WHERE ID = ?",
    [$torrentID]
)->fetchColumn();

if (($Escaped['resolve_type'] == "manual" || $Escaped['resolve_type'] == "dismiss") && $Report) {
    if ($Escaped['comment']) {
        $Comment = $_POST['comment'];
    } else {
        if ($Escaped['resolve_type'] == "manual") {
            $Comment = "Report was resolved manually";
        } elseif ($Escaped['resolve_type'] == "dismiss") {
             $Comment = "Report was dismissed as invalid";
        }
    }

    $affectedRows = $master->db->rawQuery(
        "UPDATE reportsv2
            SET Status = 'Resolved',
                LastChangeTime = ?,
                ModComment = ?,
                ResolverID = ?
          WHERE ID = ?
            AND Status <> 'Resolved'",
        [sqltime(), $Comment, $activeUser['ID'], $ReportID]
    )->rowCount();

    if ($affectedRows > 0) {
        $master->cache->deleteValue('num_torrent_reportsv2');
        $master->cache->deleteValue('reports_torrent_'.$torrentID);
        $master->cache->deleteValue('torrent_group_'.$GroupID);
        $master->repos->reports->uncache($report);
    } else {
    //Someone beat us to it. Inform the staffer.
?>
        <table cellpadding="5">
            <tr>
                <td>
                    <a href="/reportsv2.php?view=report&amp;id=<?=$ReportID?>">Somebody has already resolved this report</a>
                    <input type="button" value="Clear" onclick="ClearReport(<?=$ReportID?>);" />
                </td>
            </tr>
        </table>
<?php
    }
    die();
}

if (!isset($Escaped['resolve_type'])) {
    echo 'No resolve type';
    die();
} elseif (array_key_exists($_POST['resolve_type'], $types)) {
    $ResolveType = $types[$_POST['resolve_type']];
} else {
    //There was a type but it wasn't an option!
    echo "HAX (Invalid Resolve Type)";
    die();
}

$torrent = $master->repos->torrents->load($torrentID);
if (!($torrent instanceof Torrent)) {
    $master->db->rawQuery(
        "UPDATE reportsv2
            SET Status = 'Resolved',
                LastChangeTime = ?,
                ResolverID = ?,
                ModComment = 'Report already dealt with (Torrent deleted)'
          WHERE ID = ?
            AND Status <> 'Resolved'",
        [sqltime(), $activeUser['ID'], $ReportID]
    );

    $master->cache->decrementValue('num_torrent_reportsv2');
    $master->repos->reports->uncache($report);
}

$affectedRows = 0;
if ($Report) {
    //Resolve with a parallel check
    $affectedRows = $master->db->rawQuery(
        "UPDATE reportsv2
            SET Status = 'Resolved',
                LastChangeTime = ?,
                ResolverID = ?
          WHERE ID = ?
            AND Status <> 'Resolved'",
        [sqltime(), $activeUser['ID'], $ReportID]
    )->rowCount();
    $master->repos->reports->uncache($report);
}

//See if it we managed to resolve
if ($affectedRows > 0 || !$Report) {

    //We did, lets do all our shit
    if ($Report) {
        $master->cache->decrementValue('num_torrent_reportsv2');
    }

    if (isset($Escaped['upload'])) {
        $Upload = true;
    } else {
        $Upload = false;
    }

    if (isset($Escaped['bounty'])) {
        $Bounty = true;
    } else {
        $Bounty = false;
    }

    $params = [$torrentID, $activeUser['ID'], sqltime()];
    $SendPM = false;
    if ($_POST['resolve_type'] == "tags_lots") {
        $master->db->rawQuery(
            "INSERT IGNORE INTO torrents_bad_tags (TorrentID, UserID, TimeAdded)
                         VALUES (?, ?, ?)",
            $params
        );
        $master->cache->deleteValue('torrents_details_'.$GroupID);
        $SendPM = true;
    }

    if ($_POST['resolve_type'] == "folders_bad") {
        $master->db->rawQuery(
            "INSERT IGNORE INTO torrents_bad_folders (TorrentID, UserID, TimeAdded)
                         VALUES (?, ?, ?)",
            $params
        );
        $master->cache->deleteValue('torrents_details_'.$GroupID);
        $SendPM = true;
    }
    if ($_POST['resolve_type'] == "filename") {
        $master->db->rawQuery(
            "INSERT IGNORE INTO torrents_bad_files (TorrentID, UserID, TimeAdded)
                         VALUES (?, ?, ?)",
            $params
        );
        $master->cache->deleteValue('torrents_details_'.$GroupID);
        $SendPM = true;
    }

    //Log and delete
    if (isset($Escaped['delete']) && check_perms('users_mod')) {
        $UpUsername = $master->db->rawQuery(
            "SELECT Username
               FROM users
              WHERE ID = ?",
            [$UploaderID]
        )->fetchColumn();
        $Log = "Torrent ".$torrentID." (".$RawName.") uploaded by ".$UpUsername." was deleted by ".$activeUser['Username'];
        $Log .= ($Escaped['resolve_type'] == 'custom' ? "" : " for the reason: ".$ResolveType['title'].".");
        if (isset($Escaped['log_message']) && $Escaped['log_message'] != "") {
            $Log .= " ( ".$Escaped['log_message']." )";
        }
        $GroupID = $master->db->rawQuery(
            "SELECT GroupID
               FROM torrents
              WHERE ID = ?",
            [$torrentID]
        )->fetchColumn();

        if ($ResolveType['title']=='Dupe' && isset($Escaped['extras_id'])) {
            //------ if deleting a dupe pm peers with the duped torrents id
            $ExtraIDs = explode(" ", $Escaped['extras_id']);
            foreach ($ExtraIDs as $ExtraID) {
                if (!is_integer_string($ExtraID)) error(0);
            }

            $Peers = $master->db->rawQuery(
                "SELECT DISTINCT uid
                   FROM xbt_snatched
                  WHERE fid = ?
                  UNION
                 SELECT DISTINCT uid
                   FROM xbt_files_users
                  WHERE fid = ?",
                [$torrentID, $torrentID]
            )->fetchAll(\PDO::FETCH_COLUMN);

            if ($master->db->foundRows() > 0) {
                $Message = "Torrent ".$torrentID." (".$RawName.") was deleted for being a dupe.[br][br]";
                $Message .= "The torrent it was duping was:";
                $inQuery = implode(',', array_fill(0, count($ExtraIDs), '?'));
                $torrents = $master->db->rawQuery(
                    "SELECT tg.ID AS GroupID,
                            tg.Name,
                            t.Time,
                            t.Size
                       FROM torrents AS t
                  LEFT JOIN torrents_group AS tg ON tg.ID = t.GroupID
                      WHERE tg.ID IN ({$inQuery})
                   ORDER BY t.Time DESC",
                   $ExtraIDs
                )->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($torrents as $torrent) {
                    $Message .= "[br][url=/torrents.php?id={$torrent['GroupID']}]{$torrent['Name']}[/url] (". get_size($torrent['Size']).") uploaded ".time_diff($torrent['Time'],2,false,false);
                }
                $Message .= "[br][br]You may be able to join the torrent already here by grabbing its torrent file and doing a force recheck in your torrent client.[br][br]See the [url=/articles/view/unseeded]Reseed a torrent[/url] article for details.";
                send_pm($Peers, 0, 'A torrent you were a peer on was deleted', $Message);
            }
        }

        delete_torrent($torrentID, $GroupID, $UploaderID, isset($Escaped['refundufl']));
        write_log($Log);
        $Log = "deleted torrent for the reason: ".$ResolveType['title'].". ( ".$Escaped['log_message']." )";
        write_group_log($GroupID, $torrentID, $activeUser['ID'], $Log, 0);
    } else {
        $Log = "No log message (Torrent wasn't deleted)";
        unset($Escaped['delete']); // for later checks
    }

    //Warnings / remove upload
    if ($Upload) {
        $restriction = new Restriction;
        $restriction->setFlags(Restriction::UPLOAD);
        $restriction->UserID  = $UploaderID;
        $restriction->StaffID = $activeUser['ID'];
        $restriction->Created = new \DateTime();
        $master->repos->restrictions->save($restriction);
    }

    if ($Bounty && $ReporterID) {
        $Bounty = (int) $ResolveType['resolve_options']['bounty'];
        if ($Bounty>0) {

              $user = $master->repos->users->load($ReporterID);
              $user->wallet->adjustBalance($Bounty);
              $user->wallet->addLog(" | +$Bounty credits | You got a bounty payment.");

              $Summary = sqltime()." - User received a bounty payment of $Bounty credits.";

              $master->db->rawQuery(
                  "UPDATE users_info
                      SET AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
                    WHERE UserID = ?",
                  [$Summary, $ReporterID]
              );
              $master->cache->deleteValue('user_stats_'.$ReporterID);

              $Body = "Thank-you for your {$ResolveType['title']} report re: [url=/torrents.php?id=$torrentID]{$RawName}[/url]\n\nYou received a bounty payment of $Bounty credits.";

              send_pm($ReporterID, 0, "Received Bounty Payment", $Body);
        }
    }


    //PM
    if ($Escaped['uploader_pm'] || $Warning > 0 || isset($Escaped['delete']) || $SendPM) {
        if (isset($Escaped['delete'])) {
            $PM = "[url=/torrents.php?id=".$torrentID."]Your above torrent[/url] was reported and has been deleted.\n\n";
        } else {
            $PM = "[url=/torrents.php?id=".$torrentID."]Your above torrent[/url] was reported but not deleted.\n\n";
        }

        $Preset = $ResolveType['resolve_options']['pm'];

        if ($Preset != "") {
             $PM .= "Reason: ".$Preset;
        }

        if ($Warning > 0) {
            $PM .= "\nThis has resulted in a [url=/articles/view/rules]$Warning week warning.[/url]\n";
        }

        if ($Upload) {
            $PM .= "This has ".($Warning > 0 ? 'also ' : '')."resulted in you losing your upload privileges.";
        }

        if ($Log) {
            $PM = $PM."\nLog Message: ".$Log."\n";
        }

        if ($Escaped['uploader_pm']) {
            $PM .= "\nMessage from ".$activeUser['Username'].": ".$PMMessage;
        }

        $PM .= "\n\nReport was handled by [url=/staff?]".$activeUser['Username']."[/url].";

        send_pm($UploaderID, 0, $Escaped['raw_name'], $PM);
        $SendPM = true;
    }


    // write to the uploaders staff notes/warn
    $StaffNote = "Uploader of torrent [url=/torrents.php?id=".$torrentID."]".$RawName."[/url] which was reported (ID: ".$ReportID.") as ".$ResolveType['title'].".";
    $XtraNote = '';
    if (isset($Escaped['delete'])) $XtraNote .= "\nTorrent deleted by ".$activeUser['Username'];

    if ($Upload) $XtraNote .= "\nUpload privileges Disabled by ".$activeUser['Username'];

    if ($SendPM) $XtraNote .= "\nSystem PM sent to user";

    if ($Escaped['admin_message']) {
        if ($XtraNote) $XtraNote .= "\nNotes: ".$Escaped['admin_message'];
        else $XtraNote = "\nNotes added by ".$activeUser['Username'].": ".$Escaped['admin_message'];
    }
    $StaffNote .= $XtraNote;

    if ($Warning > 0) {
        // warn the user (makes its own staff note)
        warn_user($UploaderID, $Warning, $StaffNote);
    } else {
        write_user_log($UploaderID, $StaffNote);
    }

    $master->cache->deleteValue('reports_torrent_'.$torrentID);
    $master->cache->deleteValue('torrent_group_'.$GroupID);

    //Now we've done everything, update the DB with values
    if ($Report) {
        $CreditSQL = '';
        if ($ResolveType['title']=='Dupe') {
            $CreditSQL = ", Credit = '1'";
        }
        $master->db->rawQuery(
            "UPDATE reportsv2
                SET Type = ?,
                    LogMessage = ?,
                    ModComment = ?
                    $CreditSQL
              WHERE ID = ?",
            [$_POST['resolve_type'], $Log, $_POST['comment'], $ReportID]
        );
        $master->repos->reports->uncache($report);
    }
} else {
    //Someone beat us to it. Inform the staffer.
?>
    <table cellpadding="5">
        <tr>
            <td>
                <a href="/reportsv2.php?view=report&amp;id=<?=$ReportID?>">Somebody has already resolved this report</a>
                <input type="button" value="Clear" onclick="ClearReport(<?=$ReportID?>);" />
            </td>
        </tr>
    </table>
<?php
}
