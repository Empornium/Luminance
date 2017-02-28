<?php
/*
 * This is the AJAX page that gets called from the javascript
 * function NewReport(), any changes here should probably be
 * replicated on static.php.
 */

if (!check_perms('admin_reports')) {
    error(403);
}

include(SERVER_ROOT.'/classes/class_text.php');
$Text = NEW TEXT;

$DB->query("SELECT
            r.ID,
            r.ReporterID,
            reporter.Username,
            r.TorrentID,
            r.Type,
            r.UserComment,
            r.ResolverID,
            resolver.Username,
            r.Status,
            r.ReportedTime,
            r.LastChangeTime,
            r.ModComment,
            r.Track,
            r.Image,
            r.ExtraID,
            r.Link,
            r.LogMessage,
            tg.Name,
            tg.ID,
            t.Time,
            t.Size,
            t.UserID AS UploaderID,
            uploader.Username
            FROM reportsv2 AS r
            LEFT JOIN torrents AS t ON t.ID=r.TorrentID
            LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
            LEFT JOIN users_main AS resolver ON resolver.ID=r.ResolverID
            LEFT JOIN users_main AS reporter ON reporter.ID=r.ReporterID
            LEFT JOIN users_main AS uploader ON uploader.ID=t.UserID
            WHERE r.Status = 'New'
            GROUP BY r.ID
            ORDER BY ReportedTime ASC
            LIMIT 1");

        if ($DB->record_count() < 1) {
            die();
        }

        list($ReportID, $ReporterID, $ReporterName, $TorrentID, $Type, $UserComment, $ResolverID, $ResolverName, $Status, $ReportedTime, $LastChangeTime,
            $ModComment, $Tracks, $Images, $ExtraIDs, $Links, $LogMessage, $GroupName, $GroupID, $Time,
            $Size, $UploaderID, $UploaderName) = $DB->next_record(MYSQLI_BOTH, array("ModComment"));

        if (!$GroupID) {
            //Torrent already deleted
            $DB->query("UPDATE reportsv2 SET
            Status='Resolved',
            LastChangeTime='".sqltime()."',
            ModComment='Report already dealt with (Torrent deleted)'
            WHERE ID=".$ReportID);
?>
    <div>
        <table>
            <tr>
                <td class='center'>
                    <a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Report <?=$ReportID?></a> for torrent <?=$TorrentID?> (deleted) has been automatically resolved. <input type="button" value="Clear" onclick="ClearReport(<?=$ReportID?>);" />
                </td>
            </tr>
        </table>
    </div>
<?php
            die();
        }
        $DB->query("UPDATE reportsv2 SET Status='InProgress',
                                        ResolverID=".$LoggedUser['ID']."
                                        WHERE ID=".$ReportID);

        if (array_key_exists($Type, $Types)) {
            $ReportType = $Types[$Type];
        } else {
            //There was a type but it wasn't an option!
            $Type = 'other';
            $ReportType = $Types['other'];
        }
                $RawName = $GroupName." (". get_size($Size).")" ;
                $LinkName = "<a href='torrents.php?id=$GroupID'>$GroupName"."</a> (". get_size($Size).")" ;
                $BBName = "[url=torrents.php?id=$GroupID]$GroupName"."[/url] (".get_size($Size).")" ;
    ?>
        <div id="report<?=$ReportID?>">
            <form id="report_form<?=$ReportID?>" action="reports.php" method="post">
                <?php
                    /*
                    * Some of these are for takeresolve, some for the javascript.
                    */
                ?>
                <div>
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" id="newreportid" name="newreportid" value="<?=$ReportID?>" />
                    <input type="hidden" id="reportid<?=$ReportID?>" name="reportid" value="<?=$ReportID?>" />
                    <input type="hidden" id="torrentid<?=$ReportID?>" name="torrentid" value="<?=$TorrentID?>" />
                    <input type="hidden" id="uploader<?=$ReportID?>" name="uploader" value="<?=$UploaderName?>" />
                    <input type="hidden" id="uploaderid<?=$ReportID?>" name="uploaderid" value="<?=$UploaderID?>" />
                    <input type="hidden" id="reporterid<?=$ReportID?>" name="reporterid" value="<?=$ReporterID?>" />
                    <input type="hidden" id="raw_name<?=$ReportID?>" name="raw_name" value="<?=$RawName?>" />
                    <input type="hidden" id="type<?=$ReportID?>" name="type" value="<?=$Type?>" />
                    <input type="hidden" id="from_delete<?=$ReportID?>" name="from_delete" value="0" />
                </div>
                <table cellpadding="5">
                    <tr>
                        <td class="label"><a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Reported </a>Torrent:</td>
                        <td colspan="3">
<?php 		if (!$GroupID) { ?>
                            <a href="log.php?search=Torrent+<?=$TorrentID?>"><?=$TorrentID?></a> (Deleted)
<?php 		} else { ?>
                            <?=$LinkName?>
                            <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download">[DL]</a>
                            uploaded by <a href="user.php?id=<?=$UploaderID?>"><?=$UploaderName?></a> <?=time_diff($Time)?>
                            <br />
                            <div style="text-align: right;">was reported by <a href="user.php?id=<?=$ReporterID?>"><?=$ReporterName?></a> <?=time_diff($ReportedTime)?> for the reason: <strong><?=$ReportType['title']?></strong></div>
        <?php 		$DB->query("SELECT r.ID
                            FROM reportsv2 AS r
                            LEFT JOIN torrents AS t ON t.ID=r.TorrentID
                            WHERE r.Status != 'Resolved'
                            AND t.GroupID=$GroupID");
                $GroupOthers = ($DB->record_count() - 1);

                if ($GroupOthers > 0) { ?>
                            <div style="text-align: right;">
                                <a href="reportsv2.php?view=group&amp;id=<?=$GroupID?>">There <?=(($GroupOthers > 1) ? "are $GroupOthers other reports" : "is 1 other report")?> for torrent(s) in this group</a>
                            </div>
        <?php  		$DB->query("SELECT t.UserID
                            FROM reportsv2 AS r
                            JOIN torrents AS t ON t.ID=r.TorrentID
                            WHERE r.Status != 'Resolved'
                            AND t.UserID=$UploaderID");
                $UploaderOthers = ($DB->record_count() - 1);

                if ($UploaderOthers > 0) { ?>
                            <div style="text-align: right;">
                                <a href="reportsv2.php?view=uploader&amp;id=<?=$UploaderID?>">There <?=(($UploaderOthers > 1) ? "are $UploaderOthers other reports" : "is 1 other report")?> for torrent(s) uploaded by this user</a>
                            </div>
        <?php  		}

                $DB->query("SELECT DISTINCT req.ID,
                            req.FillerID,
                            um.Username,
                            req.TimeFilled
                            FROM requests AS req
                            LEFT JOIN torrents AS t ON t.ID=req.TorrentID
                            LEFT JOIN reportsv2 AS rep ON rep.TorrentID=t.ID
                            JOIN users_main AS um ON um.ID=req.FillerID
                            WHERE rep.Status != 'Resolved'
                            AND req.TimeFilled > '2010-03-04 02:31:49'
                            AND req.TorrentID=$TorrentID");
                $Requests = ($DB->record_count());
                if ($Requests > 0) {
                    while (list($RequestID, $FillerID, $FillerName, $FilledTime) = $DB->next_record()) {
            ?>
                                <div style="text-align: right;">
                                    <a href="user.php?id=<?=$FillerID?>"><?=$FillerName?></a> used this torrent to fill <a href="requests.php?action=view&amp;id=<?=$RequestID?>">this request</a> <?=time_diff($FilledTime)?>
                                </div>
            <?php 		}
                }
            }
        }
            ?>
                        </td>
                    </tr>
        <?php  if ($Tracks) { ?>
                    <tr>
                        <td class="label">Relevant Tracks:</td>
                        <td colspan="3">
                            <?=str_replace(" ", ", ", $Tracks)?>
                        </td>
                    </tr>
        <?php  }

            if ($Links) {
        ?>
                    <tr>
                        <td class="label">Relevant Links:</td>
                        <td colspan="3">
        <?php
                $Links = explode(" ", $Links);
                foreach ($Links as $Link) {

                    if ($local_url = $Text->local_url($Link)) {
                        $Link = $local_url;
                    }
        ?>
                            <a href="<?=$Link?>"><?=$Link?></a>
        <?php
                }
        ?>
                        </td>
                    </tr>
        <?php
            }

            if ($ExtraIDs) {
        ?>
                    <tr>
                        <td class="label">Relevant Other Torrents:</td>
                        <td colspan="3">
                                <input class="hidden" name="extras_id" value="<?=$ExtraIDs?>" />
        <?php
                $First = true;
                $Extras = explode(" ", $ExtraIDs);
                foreach ($Extras as $ExtraID) {


                        $DB->query("SELECT
                                    tg.Name,
                                    tg.ID,
                                    t.Time,
                                    t.Size,
                                    t.UserID AS UploaderID,
                                    uploader.Username
                                    FROM torrents AS t
                                    LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
                                    LEFT JOIN users_main AS uploader ON uploader.ID=t.UserID
                                    WHERE t.ID='$ExtraID'
                                    GROUP BY tg.ID");

                        list($ExtraGroupName, $ExtraGroupID,$ExtraTime,
                            $ExtraSize, $ExtraUploaderID, $ExtraUploaderName) = display_array($DB->next_record());

                    if ($ExtraGroupName) {
            $ExtraLinkName = "<a href='torrents.php?id=$ExtraGroupID'>$ExtraGroupName</a> (".number_format($ExtraSize/(1024*1024), 2)." MB)";

            ?>
                                <?=($First ? "" : "<br />")?>
                                <?=$ExtraLinkName?>
                                <a href="torrents.php?action=download&amp;id=<?=$ExtraID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download">[DL]</a>
                                uploaded by <a href="user.php?id=<?=$ExtraUploaderID?>"><?=$ExtraUploaderName?></a>
                                    <?=time_diff($ExtraTime)?>
                                [<a title="Close this report and create a new dupe report with this torrent as the reported one"
                                    href="#"
                                    onclick="Switch(<?=$ReportID?>, <?=$ReporterID?>, '<?=urlencode($UserComment)?>', <?=$TorrentID?>, <?=$ExtraID?>); return false;"
                                    >Switch</a>]
            <?php
                        $First = false;
                    }
                }
        ?>
                        </td>
                    </tr>
        <?php
            }

            if ($Images) {
        ?>
                    <tr>
                        <td class="label">Relevant Images:</td>
                        <td colspan="3">
        <?php
                $Images = explode(" ", $Images);
                foreach ($Images as $Image) {
        ?>
                            <img style="max-width: 200px;" onclick="lightbox.init(this,200);" src="<?=$Image?>" alt="<?=$Image?>" />
        <?php
                }
        ?>
                        </td>
                    </tr>
        <?php
            }
        ?>
                    <tr>
                        <td class="label">User Comment:</td>
                        <td colspan="3"><?=$Text->full_format($UserComment)?></td>
                    </tr>
                    <?php  // END REPORTED STUFF :|: BEGIN MOD STUFF ?>
                    <tr>
                        <td class="label">Report Comment:</td>
                        <td colspan="3">
                            <input type="text" name="comment" id="comment<?=$ReportID?>" size="45" value="<?=$ModComment?>" />
                            <input type="button" value="Update now" onclick="UpdateComment(<?=$ReportID?>)" />
                        </td>
                    </tr>
                    <tr class="spacespans">
                        <td class="label">
                            <a href="javascript:Load('<?=$ReportID?>')" title="Set back to <?=$ReportType['title']?>">Resolve</a>
                        </td>
                        <td colspan="3">
                            <select name="resolve_type" id="resolve_type<?=$ReportID?>" onchange="ChangeResolve(<?=$ReportID?>)">
<?php
    $TypeList = $Types;
    $Priorities = array();
    foreach ($TypeList as $Key => $Value) {
        $Priorities[$Key] = $Value['priority'];
    }
    array_multisort($Priorities, SORT_ASC, $TypeList);

    foreach ($TypeList as $Type => $Data) {
?>
                        <option value="<?=$Type?>"><?=$Data['title']?></option>
<?php  } ?>
                            </select>
                            <span id="options<?=$ReportID?>">
<?php  if (check_perms('users_mod')) { ?>
                                <span title="Delete Torrent?">
                                    <strong>Delete</strong>
                                    <input type="checkbox" name="delete" id="delete<?=$ReportID?>"/>
                                </span>
<?php  } ?>
                                <span title="Warning length in weeks">
                                    <strong>Warning</strong>
                                    <select name="warning" id="warning<?=$ReportID?>">
                                    <option value="0">none</option>
                                    <option value="1">1 week</option>
<?php                                       for ($i = 2; $i < 9; $i++) {  ?>
                                    <option value="<?=$i?>"><?=$i?> weeks</option>
<?php                                       }       ?>
                                    </select>
                                </span>
                                <span title="Remove upload privileges?">
                                    <strong>Disable Upload</strong>
                                    <input type="checkbox" name="upload" id="upload<?=$ReportID?>"/>
                                </span>
<?php                                       //if ($ReportType['resolve_options']['bounty'] != '0') {  ?>
                                <span title="Pay bounty to reporter">
                                    <strong>Pay Bounty (<span id="bounty_amount<?=$ReportID?>"><?=$ReportType['resolve_options']['bounty']?></span>)</strong>
                                                      <input type="checkbox" name="bounty" id="bounty<?=$ReportID?>"/>
                                </span>
<?php                                       //}       ?>
                                <span title="Change report type / resolve action">
                                    <input type="button" name="update_resolve" id="update_resolve<?=$ReportID?>" value="Change report type" onclick="UpdateResolve(<?=$ReportID?>)" />
                                </span>
                            </span>
                            </td>
                    </tr>
                    <tr>
                        <td class="label">
                            PM
                            <select name="pm_type" id="pm_type<?=$ReportID?>">
                                <option value="Uploader">Uploader</option>
                                <option value="Reporter">Reporter</option>
                            </select>
                        </td>
                        <td colspan="3">A PM is automatically generated for the uploader (and if a bounty is paid to the reporter). Any text here is appended to the uploaders auto PM unless using 'Send Now' to immediately send a message.<br />
                            <blockquote><strong>uploader pm text:</strong><br/><span id="pm_message<?=$ReportID?>"><?=$ReportType['resolve_options']['pm']?></span></blockquote>
                                    <span title="Uploader: Appended to the regular message unless using send now. Reporter: Must be used with send now">
                                    <textarea name="uploader_pm" id="uploader_pm<?=$ReportID?>" cols="50" rows="1"></textarea>
                            </span>
                            <input type="button" value="Send Now" onclick="SendPM(<?=$ReportID?>)" />
                        </td>
                    </tr>
                    <tr>
                        <td class="label">SiteLog Message:</td>
                        <td>
                            <input type="text" name="log_message" id="log_message<?=$ReportID?>" class="long" <?php  if ($ExtraIDs) {
                                        $Extras = explode(" ", $ExtraIDs);
                                        $Value = "";
                                        foreach ($Extras as $ExtraID) {
                                            $Value .= 'http://'.SITE_URL.'/torrents.php?torrentid='.$ExtraID.' ';
                                        }
                                        echo 'value="'.trim($Value).'"';
                                    } ?>/>
                        </td>
                        <td class="label">Extra Staff Notes:</td>
                        <td>
                            <input type="text" name="admin_message" id="admin_message<?=$ReportID?>" class="long" />
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: center;">
                            <input type="button" value="Invalid Report" onclick="Dismiss(<?=$ReportID?>);" title="Dismiss this as an invalid Report" />
                            <input type="button" value="Report resolved manually" onclick="ManualResolve(<?=$ReportID?>);" title="Set status to Resolved but take no automatic action"/>
                            | <input type="button" value="Give back" onclick="GiveBack(<?=$ReportID?>);" />
                            | <input id="grab<?=$ReportID?>" type="button" value="Grab!" onclick="Grab(<?=$ReportID?>);" />
                            | <span  title="If checked then include when multi-resolving">Multi-Resolve <input type="checkbox" name="multi" id="multi<?=$ReportID?>" checked="checked" /></span>
                            | <input type="button" value="Resolve Report" onclick="TakeResolve(<?=$ReportID?>);" title="Resolve Report (carry out whatever actions are set)" />
                        </td>
                    </tr>
                </table>
            </form>
            <br />
        </div>
        <script type="text/javascript">Load('<?=$ReportID?>');</script>
