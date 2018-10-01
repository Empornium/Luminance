<?php
$TorrentID = $_GET['torrentid'];
if (!$TorrentID || !is_number($TorrentID)) error(404);

$torrent = $master->db->raw_query("SELECT t.UserID,
                                          t.Time,
                                          COUNT(x.uid) As Snatches
                                     FROM torrents AS t LEFT JOIN xbt_snatched AS x ON x.fid=t.ID
                                    WHERE t.ID= :torrentid
                                 GROUP BY t.UserID",
                                          [':torrentid' => $TorrentID])->fetch(\PDO::FETCH_ASSOC);

if (!$torrent) error('Torrent already deleted.');

if ($LoggedUser['ID']!=$torrent['UserID'] && !check_perms('torrents_delete')) {
    error(403);
}
if($master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::UPLOAD) || !check_perms('site_upload')) error('You can no longer delete this torrent as your upload rights have been disabled');

if (isset($_SESSION['logged_user']['multi_delete']) && $_SESSION['logged_user']['multi_delete']>=3 && !check_perms('torrents_delete_fast')) {
    error('You have recently deleted 3 torrents, please contact a staff member if you need to delete more.');
}

if (time_ago($torrent['Time']) > 3600*24*7 && !check_perms('torrents_delete')) { // Should this be torrents_delete or torrents_delete_fast?
    error('You can no longer delete this torrent as it has been uploaded for over a week with no problems. If you now think there is a problem, please report it instead.');
}

if ($torrent['Snatches'] > 4 && !check_perms('torrents_delete')) { // Should this be torrents_delete or torrents_delete_fast?
    error('You can no longer delete this torrent as it has been snatched by 5 or more users. If you believe there is a problem with the torrent please report it instead.');
}

show_header('Delete torrent', 'reportsv2,jquery');
?>
<div class="thin center">
    <h2>Delete torrent </h2>
    <div class="thin">
        <div class="head">Uploader Delete</div>
        <div class="box pad" >
            <div class="pad">
                <form action="torrents.php" method="post">
                    <input type="hidden" name="action" value="takedelete" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
                    <strong>Reason: </strong>
                    <select name="reason">
                        <option value="Dead">Dead</option>
                        <option value="Dupe">Dupe</option>
                        <!--<option value="Trumped">Trumped</option>-->
                        <option value="Screenshot Rules Broken">Screenshot Rules broken</option>
                        <option value="Description Rules Broken">Description Rules broken</option>
                        <option value="Rules Broken">Rules broken</option>
                        <option value="" selected="selected">Other</option>
                    </select>
                    &nbsp;
                    <strong>Extra info: </strong>
                    <input type="text" name="extra" size="30" />
                    <input value="Delete" type="submit" />
                </form>
            </div>
        </div>
    </div>
<?php
if (check_perms('admin_reports')) {
?>
    <br/>
    <div id="all_reports" class="thin" >
<?php
    require(SERVER_ROOT.'/Legacy/sections/reportsv2/array.php');
    $Text = new Luminance\Legacy\Text;
    $ReportID = 0;
    $torrentgroup = $master->db->raw_query("SELECT
                                                tg.Name,
                                                tg.ID,
                                                t.Time,
                                                t.Size,
                                                t.UserID AS UploaderID,
                                                u.Username AS Uploader
                                           FROM torrents AS t
                                      LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
                                      LEFT JOIN users_main AS u ON u.ID=t.UserID
                                          WHERE t.ID=:torrentid",
                                                [':torrentid' => $TorrentID])->fetch(\PDO::FETCH_ASSOC);

    if (!$torrentgroup) error("Could not find torrent group");

    if (isset($_GET['type']) ) $Type = $_GET['type'];
    else $Type = 'other';

    if (array_key_exists($Type, $Types)) {
        $ReportType = $Types[$Type];
    } else {
        //There was a type but it wasn't an option!
        $Type = 'other';
        $ReportType = $Types['other'];
    }

    $RawName = display_str($torrentgroup['Name'])." (". get_size($torrentgroup['Size']).")" ;
    $LinkName = "<a href='torrents.php?id={$torrentgroup['ID']}'>".display_str($torrentgroup['Name'])."</a> (". get_size($torrentgroup['Size']).")" ;
?>
        <div id="report<?=$ReportID?>">
            <div class="head">Use Report resolve system to delete (much better logging/auto actions)</div>
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
                <input type="hidden" id="uploader<?=$ReportID?>" name="uploader" value="<?=$torrentgroup['Uploader']?>" />
                <input type="hidden" id="uploaderid<?=$ReportID?>" name="uploaderid" value="<?=$torrentgroup['UploaderID']?>" />
                <input type="hidden" id="reporterid<?=$ReportID?>" name="reporterid" value="<?=$ReporterID?>" />
                <input type="hidden" id="raw_name<?=$ReportID?>" name="raw_name" value="<?=$RawName?>" />
                <input type="hidden" id="type<?=$ReportID?>" name="type" value="<?=$Type?>" />
                <input type="hidden" id="pm_type<?=$ReportID?>" name="pm_type" value="Uploader" />
                <input type="hidden" id="from_delete<?=$ReportID?>" name="from_delete" value="<?=$torrentgroup['ID']?>" />
                <input type="hidden" id="update_resolve<?=$ReportID?>" name="update_resolve" value="" />
            </div>
            <table class="report" cellpadding="5">
                <tr>
                    <td class="label">Torrent:</td>
                    <td colspan="3">
<?php
        if (!$torrentgroup['ID']) { ?>
                        <a href="/log.php?search=Torrent+<?=$TorrentID?>"><?=$TorrentID?></a> (Deleted)
<?php
        } else {
?>
                        <?=$LinkName?>
                        <a href="/torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download">[DL]</a>
                        uploaded by <a href="/user.php?id=<?=$torrentgroup['UploaderID']?>"><?=$torrentgroup['Uploader']?></a> <?=time_diff($torrentgroup['Time'])?>
                        <br />
<?php

            $GroupOthers = $master->db->raw_query("SELECT Count(r.ID)
                                                     FROM reportsv2 AS r
                                                LEFT JOIN torrents AS t ON t.ID=r.TorrentID
                                                    WHERE r.Status != 'Resolved'
                                                      AND t.GroupID=:groupid",
                                                          [':groupid' => $torrentgroup['ID']])->fetchColumn();

            if ($GroupOthers > 0) {
?>
                        <div style="text-align: right;">
                            <a href="/reportsv2.php?view=group&amp;id=<?=$torrentgroup['ID']?>">There <?=(($GroupOthers > 1) ? "are $GroupOthers reports" : "is 1 other report")?> for torrent(s) in this group</a>
                        </div>
<?php
            }

            $UploaderOthers = $master->db->raw_query("SELECT Count(t.UserID)
                                                        FROM reportsv2 AS r
                                                   LEFT JOIN torrents AS t ON t.ID=r.TorrentID
                                                       WHERE r.Status != 'Resolved'
                                                         AND t.UserID=:userid",
                                                             [':userid' => $torrentgroup['UploaderID']])->fetchColumn();

            if ($UploaderOthers > 0) {
?>
                        <div style="text-align: right;">
                            <a href="/reportsv2.php?view=uploader&amp;id=<?=$torrentgroup['UploaderID']?>">There <?=(($UploaderOthers > 1) ? "are $UploaderOthers reports" : "is 1 other report")?> for torrent(s) uploaded by this user</a>
                        </div>
<?php
            }

            $requests = $master->db->raw_query("SELECT DISTINCT req.ID,
                                                                req.FillerID,
                                                                um.Username,
                                                                req.TimeFilled
                                                           FROM requests AS req
                                                           JOIN users_main AS um ON um.ID=req.FillerID
                                                            AND req.TorrentID=:torrentid",
                                                             [':torrentid' => $TorrentID])->fetchAll(\PDO::FETCH_ASSOC);

            if (count($requests) > 0) {
                foreach ($requests as $request) {
?>
                    <div style="text-align: right;">
                        <a href="/user.php?id=<?=$request['FillerID']?>"><?=$request['Username']?></a> used this torrent to fill <a href="/requests.php?action=viewrequest&amp;id=<?=$request['ID']?>">this request</a> <?=time_diff($request['TimeFilled'])?>
                    </div>
<?php
                }
            }

            $ExtraIDs = $_GET['extraIDs'];
            $SiteLog = '';

            if ($ExtraIDs) {
?>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Relevant Other Torrents:</td>
                        <td colspan="3">
                            <input class="hidden" name="extras_id" value="<?=display_str($ExtraIDs)?>" />
<?php
                    $First = true;
                    $Extras = explode(" ", $ExtraIDs);
                    foreach ($Extras as $ExtraID) {
                        if(!is_number($ExtraID)) continue;

                        $extra = $master->db->raw_query("SELECT
                                        tg.Name,
                                        tg.ID AS GroupID,
                                        t.Time,
                                        t.Size,
                                        t.UserID AS UploaderID,
                                        u.Username AS Uploader,
                                        t.FileCount
                                        FROM torrents AS t
                                        LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
                                        LEFT JOIN users_main AS u ON u.ID=t.UserID
                                        WHERE t.ID=:torrentid
                                        GROUP BY tg.ID",
                                            [':torrentid' => $ExtraID])->fetch(\PDO::FETCH_ASSOC);

                        if ($extra['Name']) {
                            $ExtraLinkName = '<a href="/torrents.php?id='.$extra[GroupID].'">'.display_str($extra['Name']).'</a> ('. get_size($extra['Size']).")";

                            $ExtraPeerInfo = get_peers($ExtraID);
                            echo ($First ? "" : "<br />");
                            echo $ExtraLinkName;            ?>
                                    <a href="/torrents.php?action=download&amp;id=<?=$ExtraID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download">[DL]</a>
                                    uploaded by <a href="/user.php?id=<?=$extra['UploaderID']?>"><?=display_str($extra['Uploader'])?></a>  <?=time_diff($extra['Time'])?> &nbsp;(<?=str_plural('file',$extra['FileCount'])?>)&nbsp;
                                    [ <span title="Seeders"><?=$ExtraPeerInfo['Seeders']?> <img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/seeders.png" alt="seeders" title="seeders" /></span> | <span title="Leechers"><?=$ExtraPeerInfo['Leechers']?> <img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/leechers.png" alt="leechers" title="leechers" /></span> ]
                                    &nbsp;[<a href="/torrents.php?action=dupe_check&amp;id=<?=$ExtraID ?>" target="_blank" title="Check for exact matches in filesize">Dupe check</a>]

<?php
                            if (!$First) $SiteLog .= ' ';
                            $SiteLog .= "/torrents.php?id={$extra[GroupID]}";
                            $First = false;
                        } else {
?>
                                <a href="/torrents.php?id=<?=$ExtraID?>">(deleted torrent) #<?=$ExtraID?></a>
<?php
                        }
                    }

            }

        }
?>
                    </td>
                </tr>
                <?php  // END REPORTED STUFF :|: BEGIN MOD STUFF ?>
                <tr class="spacespans">
                    <td class="label">
                        <a href="javascript:Load('<?=$ReportID?>')" title="Set back to <?=$ReportType['title']?>">Resolve Type</a>
                    </td>
                    <td colspan="3">
                        <select name="resolve_type" id="resolve_type<?=$ReportID?>" onchange="ChangeResolve('<?=$ReportID?>')">
<?php
$TypeList = $Types;
$Priorities = array();
foreach ($TypeList as $Key => $Value) {
    $Priorities[$Key] = $Value['priority'];
}
array_multisort($Priorities, SORT_ASC, $TypeList);

foreach ($TypeList as $IType => $Data) {
?>
                    <option value="<?=$IType?>"<?=(($Type == $IType)?' selected="selected"':'')?>><?=$Data['title']?></option>
<?php  } ?>
                        </select>

                    </td>
                </tr>
                <tr class="spacespans">
                    <td class="label">Resolve Options</td>
                    <td colspan="3">
                        <span id="options<?=$ReportID?>">
                            <span title="Delete Torrent?">
                                <strong>Delete</strong>
                                <input type="checkbox" name="delete" id="delete<?=$ReportID?>" onchange="UpdateUFLOption('<?=$ReportID?>');" >
                            </span>
                            <span title="Warning length in weeks">
                                <strong>Warning</strong>
                                <select name="warning" id="warning<?=$ReportID?>">
                                    <option value="0">none</option>
                                    <option value="1"<?=(($ReportType['resolve_options']['warn'] == 1)?' selected="selected"':'')?>>1 week</option>
<?php                                       for ($i = 2; $i < 9; $i++) {  ?>
                                    <option value="<?=$i?>"<?=(($ReportType['resolve_options']['warn'] == $i)?' selected="selected"':'')?>><?=$i?> weeks</option>
<?php                                       }       ?>
                                </select>
                            </span>
                            <span title="Remove upload privileges?">
                                <strong>Disable Upload</strong>
                                <input type="checkbox" name="upload" id="upload<?=$ReportID?>"<?=($ReportType['resolve_options']['upload']?' checked="checked"':'')?>>
                            </span>
                            <span title="Refund UFL?">
                                <strong>Refund UFL</strong>
                                <input type="checkbox" name="refundufl" id="refundufl<?=$ReportID?>"/>
                            </span>
                            <input type="hidden" name="bounty" id="bounty<?=$ReportID?>" />
                            <input type="hidden" name="bounty_amount" id="bounty_amount<?=$ReportID?>" />
                        </span>
                        </td>
                </tr>
                <tr>
                    <td class="label">PM Uploader</td>
                    <td colspan="3">A PM is automatically generated for the uploader (and if a bounty is paid to the reporter). Any text here is appended to the uploaders auto PM unless using 'Send Now' to immediately send a message.<br />
                              <blockquote><strong>uploader pm text:</strong><br/><span id="pm_message<?=$ReportID?>"><?=$ReportType['resolve_options']['pm']?></span></blockquote>
                            <span title="Appended to the regular message unless using send now.">
                                <textarea name="uploader_pm" id="uploader_pm<?=$ReportID?>" cols="110" rows="1" onkeyup="resize('uploader_pm<?=$ReportID?>');"><?=display_str($_GET['textPM'])?></textarea>
                            </span>
                        <input type="button" value="Send Now" onclick="SendPM(<?=$ReportID?>)" />
                    </td>
                </tr>
                <tr>
                    <td class="label">SiteLog Message:</td>
                    <td>
                        <input type="text" name="log_message" id="log_message<?=$ReportID?>" size="40" value="<?=$SiteLog?>" />
                    </td>
                    <td class="label">Extra Staff Notes:</td>
                    <td>
                        <input type="text" name="admin_message" id="admin_message<?=$ReportID?>" size="40" />
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align: center;">
                        <input type="button" value="Resolve Report" onclick="TakeResolve(<?=$ReportID?>);"  title="Resolve Report (carry out whatever actions are set)" />
                    </td>
                </tr>
            </table>
            </form>
            <br />
        </div>
    </div>
    <script type="text/javascript">Load('<?=$ReportID?>');</script>
<?php
}
?>
</div>
<?php
show_footer();
