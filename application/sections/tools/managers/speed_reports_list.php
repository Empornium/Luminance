<?php
include(SERVER_ROOT . '/sections/tools/managers/speed_functions.php');

if (!check_perms('users_manage_cheats')) { error(403); }

$Action = 'speed_records';

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $OrderWay = 'asc'; // For header links
} else {
    $_GET['order_way'] = 'desc';
    $OrderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('Username', 'Name', 'remaining', 'Size', 'uploaded', 'downloaded',
                                                                        'upspeed', 'downspeed', 'ip', 'mtime', 'timespent' ))) {
    $_GET['order_by'] = 'mtime';
    $OrderBy = 'mtime';
} else {
    $OrderBy = $_GET['order_by'];
}

$DB->query("SELECT DeleteRecordsMins, KeepSpeed FROM site_options ");
list($DeleteRecordsMins, $KeepSpeed) = $DB->next_record();

$ViewSpeed = isset($_GET['viewspeed'])?(int) $_GET['viewspeed']:$KeepSpeed;

show_header('Speed Reports','watchlist');

//---------- user watch

?>
<div class="thin">
    <h2>Speed Reports</h2>
    <div class="linkbox">
        <a href="tools.php?action=speed_watchlist">[Watch-list]</a>
        <a href="tools.php?action=speed_excludelist">[Exclude-list]</a>
        <a href="tools.php?action=speed_records">[Speed Records]</a>
        <a href="tools.php?action=speed_cheats">[Speed Cheats]</a>
        <a href="tools.php?action=speed_zerocheats">[Zero Cheats]</a>
    </div>
    <?php

    //---------- torrrent watch

    $DB->query("SELECT TorrentID, tg.Name, StaffID, um.Username AS Staffname, tl.Time, tl.Comment
                  FROM torrents_watch_list AS tl
             LEFT JOIN users_main AS um ON um.ID=tl.StaffID
             LEFT JOIN torrents AS t ON t.ID=tl.TorrentID
             LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
              ORDER BY Time DESC");
    $TWatchlist = $DB->to_array('TorrentID');

            ?>
    <div class="head">Torrent watch list &nbsp;<img src="static/common/symbols/watched.png" alt="view" /><span style="float:right;"><a href="#" onclick="$('#twatchlist').toggle();this.innerHTML=this.innerHTML=='(hide)'?'(view)':'(hide)';">(view)</a></span>&nbsp;</div>
    <table id="twatchlist" class="hidden">
        <tr class="rowa">
                <td colspan="6" style="text-align: left;color:grey">
                    Torrents in the watch list will have all their records retained until they are manually deleted. You can use this information to help detect ratio cheaters.<br/>
                    note: use the list sparingly - this can quickly fill the database with a huge number of records.
                </td>
        </tr>
        <tr class="colhead">
                <td class="center"></td>
                <td class="center">Torrent</td>
                <td class="center">Time added</td>
                <td class="center">added by</td>
                <td class="center">comment</td>
                <td class="center" width="100px"></td>
        </tr>
<?php
        $row = 'a';
        if (count($TWatchlist)==0) {
?>
            <tr class="rowb">
                <td class="center" colspan="6">no torrents on watch list</td>
            </tr>
<?php
        } else {
                foreach ($TWatchlist as $Watched) {
                    list($TorrentID, $TName, $StaffID, $Staffname, $Time, $Comment) = $Watched;
                    $row = ($row === 'b' ? 'a' : 'b');
?>
                    <tr class="row<?=$row?>">
                        <form action="tools.php" method="post">
                            <input type="hidden" name="action" value="edit_torrentwl" />
                            <input type="hidden" name="viewspeed" value="<?=$ViewSpeed?>" />
                            <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
                            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                            <td class="center">
                                <a href="?action=speed_records&viewspeed=<?=$ViewSpeed?>&torrentid=<?=$TorrentID?>" title="View records for just this torrent">
                                    [view]
                                </a>
                            </td>
                            <td class="center"><?=format_torrentid($TorrentID, $TName,40)?></td>
                            <td class="center"><?=time_diff($Time, 2, true, false, 1)?></td>
                            <td class="center"><?=format_username($StaffID, $Staffname)?></td>
                            <td class="center" title="<?=$Comment?>"><?=cut_string($Comment, 40)?></td>
                            <td class="center">
                                <input type="submit" name="submit" value="Remove" title="Remove torrent from watchlist" />
                            </td>
                        </form>
                    </tr>
<?php               }
        }  ?>
    </table>
    <br/>
    <div class="head">options</div>
<?php
    //---------- options

    if (is_number($_GET['userid']) && $_GET['userid']>0) {
        $_GET['userid'] = (int) $_GET['userid'];
        $WHERE = " AND xbt.uid='$_GET[userid]' ";
        $ViewInfo = "User ($_GET[userid]) ". $Watchlist[$_GET['userid']]['Username'] .' &nbsp;&nbsp; ';
    } elseif (is_number($_GET['torrentid']) && $_GET['torrentid']>0) {
        $_GET['torrentid'] = (int) $_GET['torrentid'];
        $WHERE = " AND xbt.fid='$_GET[torrentid]' ";
        $ViewInfo = "Torrent ($_GET[torrentid]) &nbsp;&nbsp; ". $TWatchlist[$_GET['torrentid']]['Name'] .' &nbsp;&nbsp; ';
    } else {
        //$ViewInfo = 'all over speed specified';
        $ViewInfo = ">= ".get_size($ViewSpeed);
    }
    if (isset($_GET['viewbanned']) && $_GET['viewbanned']) {
        $ViewInfo .= ' (all)';
    } else {
        $WHERE .= " AND um.Enabled='1'";
        $ViewInfo .= ' (enabled only)';
    }
    if (isset($_GET['viewexcluded']) && $_GET['viewexcluded'] || !isset($_GET['viewexcluded'])) {
        $EXCLUDED = "";
    } else {
        $EXCLUDED = "AND nc.UserID IS NULL";
    }

    $CanManage = check_perms('admin_manage_cheats');
?>
    <table class="box pad">
        <form action="tools.php" method="post">
            <tr class="colhead"><td colspan="3">storage settings: </td></tr>
            <tr>
                <input type="hidden" name="action" value="save_records_options" />
                <input type="hidden" name="userid" value="<?=$_GET['userid']?>" />
                <input type="hidden" name="torrentid" value="<?=$_GET['torrentid']?>" />
                <input type="hidden" name="viewspeed" value="<?=$ViewSpeed?>" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <td class="center">
                            <label for="delrecordmins">Delete unwatched records after </label>
<?php  if ($CanManage) { ?>
                            <select id="delrecordmins" name="delrecordmins" title="Delete unwatched records after this time">
                                <option value="0"<?=($DeleteRecordsMins==0?' selected="selected"':'');?>>&nbsp;asap&nbsp;&nbsp;</option>
<?php                               for ($i=1;$i<5;$i++) {
                                    $mins = $i * 15;  ?>
                                    <option value="<?=$mins?>" <?=($DeleteRecordsMins==$mins?' selected="selected"':'');?>>&nbsp;<?=time_span($mins*60);?>&nbsp;&nbsp;</option>
<?php                               }
                                for ($i=1;$i<25;$i++) {
                                    $mins = $i * 120;  ?>
                                    <option value="<?=$mins?>" <?=($DeleteRecordsMins==$mins?' selected="selected"':'');?>>&nbsp;<?=time_span($mins*60);?>&nbsp;&nbsp;</option>
<?php                               }  ?>
                            </select>
<?php  } else { ?>
                            <input name="delrecords" type="text" style="width:130px;color:black;" disabled="disabled" value="<?=time_span($DeleteRecordsMins*60)?>" title="Delete unwatched records after this time" />
<?php  }  ?>
                </td>
                <td  class="center">
                            <label for="keepspeed" title="Keep Speed">Keep unwatched records with upload speed over </label>
<?php  if ($CanManage) { ?>
                            <select id="keepspeed" name="keepspeed" title="Keep unwatched records over this speed">
                                <option value="524288"<?=($KeepSpeed==524288?' selected="selected"':'');?>>&nbsp;<?=get_size(524288);?>/s&nbsp;&nbsp;</option>
<?php                               for ($i=1;$i<21;$i++) {
                                    $speed = $i * 1048576;  ?>
                                    <option value="<?=$speed?>" <?=($KeepSpeed==$speed?' selected="selected"':'');?>>&nbsp;<?=get_size($speed);?>/s&nbsp;&nbsp;</option>
<?php                               } ?>
                            </select>
<?php  } else { ?>
                            <input type="text" name="keepspeed" style="width:130px;color:black;" disabled="disabled" value="<?=get_size($KeepSpeed);?>/s" title="Keep unwatched records over this speed" />
<?php  }  ?>
                </td>
<?php  if ($CanManage) { ?>
                <td  class="center">
                    <input type="submit" value="Save Changes" />
                </td>
<?php  }  ?>
            </tr>
            <tr class="colhead"><td colspan="3">view settings: </td></tr>
            <tr>
                <td class="center">
                    Viewing: <?=$ViewInfo?> &nbsp; (order: <?="$OrderBy $OrderWay"?>)
<?php                   if ($ViewInfo!= ">= ".get_size($ViewSpeed)) { ?>
                        <a href="?action=speed_records&viewspeed=<?=$ViewSpeed?>&viewbanned=<?=$_GET['viewbanned']?>&order_by=<?=$OrderBy?>&order_way=<?=$OrderWay?>" title="Removes any user or torrent filters for viewing (still applies speed filter)">View All</a>
<?php                   } ?>
                </td>
                <td class="right">
                            <label for="viewbanned" title="Keep Speed">show disabled users </label>
                        <input type="checkbox" value="1" onchange="change_view_reports('<?=$_GET['userid']?>','<?=$_GET['torrentid']?>')"
                               id="viewbanned" name="viewbanned" <?php  if (isset($_GET['viewbanned']) && $_GET['viewbanned'])echo' checked="checked"'?> />
                            <br>
                            <label for="viewexcluded" title="Keep Speed">show excluded users </label>
                        <input type="checkbox" value="1" onchange="change_view_reports('<?=$_GET['userid']?>','<?=$_GET['torrentid']?>')"
                               id="viewexcluded" name="viewexcluded" <?php  if (isset($_GET['viewexcluded']) && $_GET['viewexcluded'] || !isset($_GET['viewexcluded']))echo' checked="checked"'?> />
                </td>
                <td class="center">
                    <label for="viewspeed" title="View Speed">View records with upload speed over </label>
                    <select id="viewspeed" name="viewspeed" title="Hide records under this speed" onchange="change_view_reports('<?=$_GET['userid']?>','<?=$_GET['torrentid']?>')">
                        <option value="0"<?=($ViewSpeed==0?' selected="selected"':'');?>>&nbsp;0&nbsp;&nbsp;</option>
                        <option value="262144"<?=($ViewSpeed==262144?' selected="selected"':'');?>>&nbsp;<?=get_size(262144);?>/s&nbsp;&nbsp;</option>
                        <option value="524288"<?=($ViewSpeed==524288?' selected="selected"':'');?>>&nbsp;<?=get_size(524288);?>/s&nbsp;&nbsp;</option>
<?php                       for ($i=1;$i<21;$i++) {
                            $speed = $i * 1048576;  ?>
                            <option value="<?=$speed?>" <?=($ViewSpeed==$speed?' selected="selected"':'');?>>&nbsp;<?=get_size($speed);?>/s&nbsp;&nbsp;</option>
<?php                       } ?>
                    </select>
                </td>
            </tr>
        </form>
<?php  if ($CanManage) {   ?>
    <form action="tools.php" method="post">
        <input type="hidden" name="action" value="test_delete_schedule" />
        <input type="hidden" name="viewspeed" value="<?=$ViewSpeed?>" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <tr class="rowa">
                <td colspan="8" style="text-align: right;">
                    <input type="submit" value="Run auto-delete schedule manually" title="Will run the delete schedule for speed records based on settings above" />
                </td>
            </tr>
    </form>
<?php  }  ?>
    </table>
    <br/>
<?php
//---------- print records

if (isset($_GET['matchspeed']) && is_number($_GET['matchspeed']))
    $WHERESTART = "upspeed='".(int) $_GET['matchspeed']."'";
elseif (isset($_GET['matchuploaded']) && is_number($_GET['matchuploaded']))
    $WHERESTART = "xbt.uploaded='".(int) $_GET['matchuploaded']."'";
else
    $WHERESTART = "upspeed>='$ViewSpeed'";

list($Page,$Limit) = page_limit(25);

$DB->query("SELECT Count(*) FROM xbt_peers_history");
list($TotalResults) = $DB->next_record();

$DB->query("SELECT SQL_CALC_FOUND_ROWS
                            xbt.id, uid, Username, xbt.downloaded, remaining, t.Size, xbt.uploaded,
                            upspeed, downspeed, timespent, peer_id, xbt.ip, tg.ID, fid, tg.Name, xbt.mtime,
                             ui.Donor, ui.Warned, um.Enabled, um.PermissionID,
                                IF(w.UserID,'1','0'), IF(nc.UserID,'1','0')
                          FROM xbt_peers_history AS xbt
                     LEFT JOIN users_main AS um ON um.ID=xbt.uid
                     LEFT JOIN users_info AS ui ON ui.UserID=xbt.uid
                     LEFT JOIN torrents AS t ON t.ID=xbt.fid
                     LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
                     LEFT JOIN users_not_cheats AS nc ON nc.UserID=xbt.uid
                     LEFT JOIN users_watch_list AS w ON w.UserID=xbt.uid
                         WHERE $WHERESTART $EXCLUDED $WHERE
                      ORDER BY $OrderBy $OrderWay
                         LIMIT $Limit");
$Records = $DB->to_array();
$DB->query("SELECT FOUND_ROWS()");
list($NumResults) = $DB->next_record();

$Pages=get_pages($Page,$NumResults,25,9);

?>

    <div class="linkbox"><?=$Pages?></div>

    <div class="head"><?=" $NumResults / $TotalResults"?> records</div>
        <table>
            <tr class="colhead">
                <td style="min-width:70px"></td>
                <td class="center"><a href="<?=header_link('Username') ?>">User</a></td>
                <td class="center"><a href="<?=header_link('remaining') ?>">Remaining</a></td>
                <td class="center"><a href="<?=header_link('uploaded') ?>">Uploaded</a></td>
                <td class="center"><a href="<?=header_link('upspeed') ?>">UpSpeed</a></td>
                <td class="center"><span style="color:#777">-clientID-</span> &nbsp;<a href="<?=header_link('ip') ?>">Client IP address</a></td>
                <td class="center"><a href="<?=header_link('mtime') ?>">date time</a></td>
                <td width="10px" rowspan="2" title="toggle selection for all records on this page">
                    <input type="checkbox" onclick="toggleChecks('speedrecords',this)" title="toggle selection for all records on this page" />
                </td>
            </tr>
            <tr class="colhead">
                <td ></td>
                <td class="center"><a href="<?=header_link('Name') ?>"><span style="color:#777">TorrentID</span></a></td>
                <td class="center"><a href="<?=header_link('Size') ?>"><span style="color:#777">Total</span></a></td>
                <td class="center"><a href="<?=header_link('downloaded') ?>"><span style="color:#777">Downloaded</span></a></td>
                <td class="center"><a href="<?=header_link('downspeed') ?>"><span style="color:#777">DownSpeed</span></a></td>
                <td class="center"><span style="color:#777">host</span></td>
                <td class="center" style="min-width:80px"><a href="<?=header_link('timespent') ?>"><span style="color:#777">total time</span></a></td>
            </tr>
    <form id="speedrecords" action="tools.php" method="post">
        <input type="hidden" name="action" value="delete_speed_records" />
        <input type="hidden" name="viewspeed" value="<?=$ViewSpeed?>" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<?php
            $row = 'a';
            if ($NumResults==0) {
?>
                    <tr class="rowb">
                        <td class="center" colspan="12">no speed records</td>
                    </tr>
<?php
            } else {
                foreach ($Records as $Record) {
                    list($ID, $UserID, $Username, $Downloaded, $Remaining, $Size, $Uploaded, $UpSpeed, $DownSpeed,
                                       $Timespent, $ClientPeerID, $IP, $GroupID, $TorrentID, $Name, $Time,
                                       $IsDonor, $Warned, $Enabled, $ClassID, $OnWatchlist, $OnExcludeList) = $Record;
                    $row = ($row === 'a' ? 'b' : 'a');
                    $ipcc = geoip($IP);
?>
                    <tr class="row<?=$row?>">
                        <td>
<?php                           if ($_GET['userid']!=$UserID) {    ?>
                            <a href="?action=speed_records&viewspeed=0&userid=<?=$UserID?>" title="View records for just <?=$Username?>"><img src="static/common/symbols/view.png" alt="view" /></a>
<?php                           }   ?>
                            <div style="display:inline-block">
<?php                           if (!$OnWatchlist) {
?>                           <a onclick="watchlist_add('<?=$UserID?>',true);return false;" href="#" title="Add <?=$Username?> to watchlist"><img src="static/common/symbols/watchedred.png" alt="view" /></a><br/><?php
                            }
                            if (!$OnExcludeList) {
?>                           <a onclick="excludelist_add('<?=$UserID?>',true);return false;" href="#" title="Add <?=$Username?> to exclude list"><img src="static/common/symbols/watchedgreen.png" alt="view" /></a><?php
                            }
?>                          </div>
                              <a onclick="remove_records('<?=$UserID?>');return false;" href="#" title="Remove all speed records belonging to <?=$Username?> from watchlist"><img src="static/common/symbols/trash.png" alt="del records" /></a>
<?php
                            if ($Enabled=='1') { ?>
                                <a href="tools.php?action=ban_speed_cheat&banuser=1&userid=<?=$UserID?>" title="ban this user for being a big fat cheat"><img src="static/common/symbols/ban.png" alt="ban" /></a>
<?php                           }  ?>
                        </td>
                        <td class="center">
<?php                           echo format_username($UserID, $Username, $IsDonor, $Warned, $Enabled, $ClassID, false, false);  ?>
                        </td>
                        <td class="center"><?=get_size($Remaining)?></td>
                        <td class="center"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/seeders.png" title="up"/> <?=size_span($Uploaded, get_size($Uploaded))?></td>
                        <td class="center"><?=speed_span($UpSpeed, $KeepSpeed, 'red', get_size($UpSpeed).'/s')?></td>
                        <td class="center"><span style="color:#555"><?=substr($ClientPeerID,0,8)?></span> &nbsp;<?=display_ip($IP, $ipcc)?></td>
                        <td class="center"><?=time_diff($Time, 2, true, false, 1)?></td>
                        <td rowspan="2">
                            <input class="remove" type="checkbox"  name="rid[]" value="<?=$ID?>" title="check to remove selected records" />
                        </td>
                    </tr>
                    <tr class="row<?=$row?>">
                        <td><span style="color:#555">
<?php                           if ($_GET['torrentid']!=$TorrentID) {
                        ?>  <a href="?action=speed_records&viewspeed=0&torrentid=<?=$TorrentID?>" title="View records for just this torrent"><img src="static/common/symbols/view.png" alt="view" /></a> <?php
                            }
                            if ($GroupID && !array_key_exists($TorrentID, $TWatchlist)) {
                       ?>   <a onclick="twatchlist_add('<?=$GroupID?>','<?=$TorrentID?>',true);" href="#" title="Add torrent to watchlist"><img src="static/common/symbols/watched.png" alt="view" /></a> <?php
                            }  ?>
                        </td>
                        <td class="center">
                            <span style="color:#555"><?=format_torrentid($TorrentID, $Name)?></span>
                        </td>
                        <td class="center"><span style="color:#555"><?=get_size($Size)?></span></td>
                        <td class="center"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/leechers.png" title="down"/> <?=size_span($Downloaded, get_size($Downloaded))?></td>
                        <td class="center"><?=speed_span($DownSpeed, $KeepSpeed, 'purple', get_size($DownSpeed).'/s')?></td>
                        <td class="center"><span style="color:#555"><?=get_host($IP)?> </span></td>
                        <td class="center"><span style="color:#555" title="<?=time_span($Timespent, 4)?>"><?=time_span($Timespent, 2)?></span></td>
                    </tr>
<?php               }
            }
            $row = ($row === 'b' ? 'a' : 'b');
            ?>
            <tr class="row<?=$row?>">
                <td colspan="8" style="text-align: right;">
                    <input type="submit" name="delselected" value="Delete selected" title="Delete selected speed records" />
                </td>
            </tr>
    </form>
        </table>
    <div class="linkbox"><?=$Pages?></div>
</div>
<?php
show_footer();
