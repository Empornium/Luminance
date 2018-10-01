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

use Luminance\Entities\Restriction;

//Don't escape: Log message, Admin message
$Escaped = db_array($_POST, array('log_message','admin_message', 'raw_name'));

//If we're here from the delete torrent page instead of the reports page.
if (!isset($Escaped['from_delete']) || $Escaped['from_delete']==0) {
    $Report = true;
} elseif (!is_number($Escaped['from_delete'])) {
    echo 'Hax occured in from_delete';
} else {
    $Report = false;
}

$PMMessage = $_POST['uploader_pm'];

if (is_number($Escaped['reportid'])) {
    $ReportID = $Escaped['reportid'];
} else {
    echo 'Hax occured in the reportid';
    die();
}

if ($Escaped['pm_type'] != 'Uploader') {
    $Escaped['uploader_pm'] = '';
}

$UploaderID = (int) $Escaped['uploaderid'];
if (!is_number($UploaderID)) {
    echo 'Hax occuring on the uploaderid';
    die();
}

if (isset($Escaped['reporterid'])) {
    $ReporterID = (int) $Escaped['reporterid'];
    if (!is_number($ReporterID)) {
          echo 'Hax occuring on the reporterid';
          die();
    }
}

$Warning = (int) $Escaped['warning'];
if (!is_number($Warning)) {
    echo 'Hax occuring on the warning';
    die();
}

$TorrentID = $Escaped['torrentid'];
$RawName = $Escaped['raw_name'];

// GroupID is only used to delete the torrent group cache key.
$DB->query("SELECT GroupID FROM torrents WHERE ID='$TorrentID'");
list($GroupID) = $DB->next_record();

if (($Escaped['resolve_type'] == "manual" || $Escaped['resolve_type'] == "dismiss" ) && $Report) {
    if ($Escaped['comment']) {
        $Comment = $Escaped['comment'];
    } else {
        if ($Escaped['resolve_type'] == "manual") {
            $Comment = "Report was resolved manually";
        } elseif ($Escaped['resolve_type'] == "dismiss") {
             $Comment = "Report was dismissed as invalid";
        }
    }

    $DB->query("UPDATE reportsv2 SET
    Status='Resolved',
    LastChangeTime='".sqltime()."',
    ModComment = '".$Comment."',
    ResolverID='".$LoggedUser['ID']."'
    WHERE ID='".$ReportID."'
    AND Status <> 'Resolved'");

    if ($DB->affected_rows() > 0) {
        $Cache->delete_value('num_torrent_reportsv2');
        $Cache->delete_value('reports_torrent_'.$TorrentID);
                $Cache->delete_value('torrent_group_'.$GroupID);
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
} elseif (array_key_exists($_POST['resolve_type'], $Types)) {
    $ResolveType = $Types[$_POST['resolve_type']];
} else {
    //There was a type but it wasn't an option!
    echo "HAX (Invalid Resolve Type)";
    die();
}

$DB->query("SELECT ID FROM torrents WHERE ID = ".$TorrentID);
$TorrentExists = ($DB->record_count() > 0);
if (!$TorrentExists) {
    $DB->query("UPDATE reportsv2
        SET Status='Resolved',
        LastChangeTime='".sqltime()."',
        ResolverID='".$LoggedUser['ID']."',
        ModComment='Report already dealt with (Torrent deleted)'
    WHERE ID=".$ReportID);

    $Cache->decrement('num_torrent_reportsv2');
}

if ($Report) {
    //Resolve with a parallel check
    $DB->query("UPDATE reportsv2
        SET Status='Resolved',
            LastChangeTime='".sqltime()."',
            ResolverID='".$LoggedUser['ID']."'
        WHERE ID=".$ReportID."
            AND Status <> 'Resolved'");
}

//See if it we managed to resolve
if ($DB->affected_rows() > 0 || !$Report) {
    //We did, lets do all our shit
    if ($Report) { $Cache->decrement('num_torrent_reportsv2'); }

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

    if ($_POST['resolve_type'] == "tags_lots") {
        $DB->query("INSERT IGNORE INTO torrents_bad_tags (TorrentID, UserID, TimeAdded) VALUES (".$TorrentID.", ".$LoggedUser['ID']." , '".sqltime()."')");
        $DB->query("SELECT GroupID FROM torrents WHERE ID = ".$TorrentID);
        list($GroupID) = $DB->next_record();
        $Cache->delete_value('torrents_details_'.$GroupID);
        $SendPM = true;
    }

    if ($_POST['resolve_type'] == "folders_bad") {
        $DB->query("INSERT IGNORE INTO torrents_bad_folders (TorrentID, UserID, TimeAdded) VALUES (".$TorrentID.", ".$LoggedUser['ID'].", '".sqltime()."')");
        $DB->query("SELECT GroupID FROM torrents WHERE ID = ".$TorrentID);
        list($GroupID) = $DB->next_record();
        $Cache->delete_value('torrents_details_'.$GroupID);
        $SendPM = true;
    }
    if ($_POST['resolve_type'] == "filename") {
        $DB->query("INSERT IGNORE INTO torrents_bad_files (TorrentID, UserID, TimeAdded) VALUES (".$TorrentID.", ".$LoggedUser['ID'].", '".sqltime()."')");
        $DB->query("SELECT GroupID FROM torrents WHERE ID = ".$TorrentID);
        list($GroupID) = $DB->next_record();
        $Cache->delete_value('torrents_details_'.$GroupID);
        $SendPM = true;
    }

    //Log and delete
    if (isset($Escaped['delete']) && check_perms('users_mod')) {
        $DB->query("SELECT Username FROM users_main WHERE ID = ".$UploaderID);
        list($UpUsername) = $DB->next_record();
        $Log = "Torrent ".$TorrentID." (".$RawName.") uploaded by ".$UpUsername." was deleted by ".$LoggedUser['Username'];
        $Log .= ($Escaped['resolve_type'] == 'custom' ? "" : " for the reason: ".$ResolveType['title'].".");
        if (isset($Escaped['log_message']) && $Escaped['log_message'] != "") {
            $Log .= " ( ".$Escaped['log_message']." )";
        }
        $DB->query("SELECT GroupID FROM torrents WHERE ID = ".$TorrentID);
        list($GroupID) = $DB->next_record();

        if ($ResolveType['title']=='Dupe' && isset($Escaped['extras_id'])) {
            //------ if deleting a dupe pm peers with the duped torrents id
            $ExtraIDs = explode(" ", $Escaped['extras_id']);
            foreach ($ExtraIDs as $ExtraID) {
                if(!is_number($ExtraID)) error(0);
            }
            $ExtraIDs = implode(',', $ExtraIDs);

            $DB->query("SELECT DISTINCT uid FROM xbt_snatched WHERE fid = '$TorrentID'
                        UNION
                        SELECT DISTINCT uid FROM xbt_files_users WHERE fid = '$TorrentID'");

            if ($DB->record_count()>0) {
                $Peers = $DB->collect('uid');
                $Message = "Torrent ".$TorrentID." (".$RawName.") was deleted for being a dupe.[br][br]";
                $Message .= "The torrent it was duping was:";
                $DB->query("SELECT tg.ID, tg.Name, t.Time, t.Size, t.UserID, um.Username
                              FROM torrents AS t JOIN torrents_group AS tg ON tg.ID=t.GroupID
                              LEFT JOIN users_main AS um ON um.ID=t.UserID
                             WHERE tg.ID IN ($ExtraIDs)
                             ORDER BY t.Time DESC");
                while (list($xID, $xName, $xTime, $xSize, $xUserID, $xUsername) = $DB->next_record()) {
                    $Message .= "[br][url=/torrents.php?id=$xID]{$xName}[/url] (". get_size($xSize).") uploaded by [url=/user.php?id=$xUserID]{$xUsername}[/url] " .time_diff($xTime,2,false,false);
                }
                $Message .= "[br][br]You should be able to join the torrent already here by grabbing its torrent file and doing a force recheck in your torrent client.[br][br]See the [url=/articles.php?topic=unseeded]Reseed a torrent[/url] article for details.";
                send_pm($Peers, 0, db_string('A torrent you were a peer on was deleted'), db_string($Message));
            }

        }

        delete_torrent($TorrentID, $GroupID, $UploaderID, isset($Escaped['refundufl']));
        write_log($Log);
        $Log = "deleted torrent for the reason: ".$ResolveType['title'].". ( ".$Escaped['log_message']." )";
        write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], $Log, 0);
    } else {
        $Log = "No log message (Torrent wasn't deleted)";
        unset($Escaped['delete']); // for later checks
    }

    //Warnings / remove upload
    if ($Upload) {
        $restriction = new Restriction;
        $restriction->setFlags(Restriction::UPLOAD);
        $restriction->UserID  = $UploaderID;
        $restriction->StaffID = $LoggedUser['ID'];
        $restriction->Created = new \DateTime();
        $master->repos->restrictions->save($restriction);
    }

    if ($Bounty && $ReporterID) {
        $Bounty = (int) $ResolveType['resolve_options']['bounty'];
        if ($Bounty>0) {

              $SET = "m.Credits=(m.Credits+$Bounty)";
              $Summary = sqltime()." - User received a bounty payment of $Bounty credits.";
              $SET .=",i.AdminComment=CONCAT_WS( '\n', '".db_string($Summary)."', i.AdminComment)";
              $Summary = sqltime()." | +$Bounty credits | You got a bounty payment.";
              $SET .=",i.BonusLog=CONCAT_WS( '\n', '".db_string($Summary)."', i.BonusLog)";

              $DB->query("UPDATE users_main AS m JOIN users_info AS i ON m.ID=i.UserID SET $SET WHERE m.ID='$ReporterID'");
              $Cache->delete_value('user_stats_'.$ReporterID);

              $Body = "Thank-you for your {$ResolveType['title']} report re: [url=/details.php?id=$TorrentID]{$RawName}[/url]\n\nYou received a bounty payment of $Bounty credits.";

              send_pm($ReporterID, 0, "Received Bounty Payment", db_string($Body));
        }
    }


    //PM
    if ($Escaped['uploader_pm'] || $Warning > 0 || isset($Escaped['delete']) || $SendPM) {
        if (isset($Escaped['delete'])) {
            $PM = "[url=/details.php?id=".$TorrentID."]Your above torrent[/url] was reported and has been deleted.\n\n";
        } else {
            $PM = "[url=/details.php?id=".$TorrentID."]Your above torrent[/url] was reported but not deleted.\n\n";
        }

        $Preset = $ResolveType['resolve_options']['pm'];

        if ($Preset != "") {
             $PM .= "Reason: ".$Preset;
        }

        if ($Warning > 0) {
            $PM .= "\nThis has resulted in a [url=/articles.php?topic=rules]$Warning week warning.[/url]\n";
        }

        if ($Upload) {
            $PM .= "This has ".($Warning > 0 ? 'also ' : '')."resulted in you losing your upload privileges.";
        }

        if ($Log) {
            $PM = $PM."\nLog Message: ".$Log."\n";
        }

        if ($Escaped['uploader_pm']) {
            $PM .= "\nMessage from ".$LoggedUser['Username'].": ".$PMMessage;
        }

        $PM .= "\n\nReport was handled by [url=/staff.php?]".$LoggedUser['Username']."[/url].";

        send_pm($UploaderID, 0, db_string($Escaped['raw_name']), db_string($PM));
        $SendPM = true;
    }


    // write to the uploaders staff notes/warn
    $StaffNote = "Uploader of torrent [url=/torrents.php?id=".$TorrentID."]".$RawName."[/url] which was reported (ID: ".$ReportID.") as ".$ResolveType['title'].".";
    $XtraNote = '';
    if (isset($Escaped['delete'])) $XtraNote .= "\nTorrent deleted by ".$LoggedUser['Username'];

    if ($Upload) $XtraNote .= "\nUpload privileges Disabled by ".$LoggedUser['Username'];

    if ($SendPM) $XtraNote .= "\nSystem PM sent to user";

    if ($Escaped['admin_message']) {
        if ($XtraNote) $XtraNote .= "\nNotes: ".$Escaped['admin_message'];
        else $XtraNote = "\nNotes added by ".$LoggedUser['Username'].": ".$Escaped['admin_message'];
    }
    $StaffNote .= $XtraNote;

    if ($Warning > 0) {
        // warn the user (makes its own staff note)
        warn_user($UploaderID, $Warning, $StaffNote);
    } else {
        write_user_log($UploaderID, $StaffNote);
    }

    $Cache->delete_value('reports_torrent_'.$TorrentID);
    $Cache->delete_value('torrent_group_'.$GroupID);

    //Now we've done everything, update the DB with values
    if ($Report) {
        if ($ResolveType['title']=='Dupe') {
            $CreditSQL = ",Credit='1'";
        }
        $DB->query("UPDATE reportsv2 SET
        Type = '".$Escaped['resolve_type']."',
        LogMessage='".db_string($Log)."',
        ModComment='".$Escaped['comment']."'
        $CreditSQL
        WHERE ID=".$ReportID);
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
