<?php
if (!check_perms('torrents_review')) { error(403); }

$ViewStatus = isset($_REQUEST['viewstatus'])?$_REQUEST['viewstatus']:'both';
$ViewStatus = in_array($ViewStatus, array('warned','pending','both','unmarked'))?$ViewStatus:'pending';
$OverdueOnly = (isset($_REQUEST['overdue']) && $_REQUEST['overdue'])?1:0;

$DB->query("SELECT ReviewHours, AutoDelete FROM site_options LIMIT 1");
list($Hours, $Delete) = $DB->next_record();

$CanManage = check_perms('torrents_review_manage');
$NumOverdue = get_num_overdue_torrents('both');
if ($NumOverdue) //
    $NumWarnedOverdue = get_num_overdue_torrents('warned');
else $OverdueOnly = 0;

show_header('Manage torrents marked for deletion');

?>
    <div class="thin">
        <h2>Torrents marked for deletion</h2>
<?php
    if ($NumDeleted) {
          $ResultMessage ="Successfully Deleted $NumDeleted Torrent";
          if($NumDeleted>1) $ResultMessage .= 's';
          if ($ResultMessage) {
?>
            <div id="messagebar" class="messagebar"><?=$ResultMessage?></div><br />
<?php
          }
      }
?>
        <table class="box pad wid740">
            <tr class="colhead"><td colspan="3" class="center">site settings</td></tr>
            <tr>
                <form action="tools.php?action=marked_for_deletion" method="post">
                        <input type="hidden" name="action" value="save_mfd_options" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="viewstatus" value="<?=$ViewStatus?>" />
                        <input type="hidden" name="overdue" value="<?=$OverdueOnly?>" />
                        <td class="center">
                            <label for="hours">Warning period: (hours) </label>
<?php  if ($CanManage) { ?>
                            <input name="hours" type="text" style="width:30px;" value="<?=$Hours?>" title="This is the hours given to fix the torrent when warned (has no effect on current list)" />
<?php  } else { ?>
                            <input name="hours" type="text" style="width:30px;color:black;" disabled="disabled" value="<?=$Hours?>" title="This is the hours given to fix the torrent when warned (has no effect on current list)" />
<?php  }  ?>
                        </td>
                        <td  class="center">
                            <label for="autodelete" title="AutoDelete">Auto Delete</label>
<?php  if ($CanManage) { ?>
                            <select id="autodelete" name="autodelete" title="If On then marked torrents are automatically deleted when they time out (if not pending). If Off then overdue marked torrents can still be deleted manually in this page.">
                                <option value="1"<?=$Delete?' selected="selected"':'';?>>On&nbsp;&nbsp;</option>
                                <option value="0"<?=$Delete?'':' selected="selected"';?>>Off&nbsp;&nbsp;</option>
                            </select>
<?php  } else { ?>
                            <input type="text" name="autodelete" style="width:30px;color:black;" disabled="disabled" value="<?=$Delete?'On':'Off';?>" title="If On then marked torrents are automatically deleted when they time out (if not pending). If Off then overdue marked torrents can still be deleted manually in this page." />
<?php  }  ?>
                        </td>
<?php  if ($CanManage) { ?>   <td  class="center">  <!-- width="30%" -->
                            <input type="submit" value="Save Changes" />
                        </td> <?php  }  ?>
                </form>
            </tr>
        </table>

        <div class="linkbox" >

<?php       if ($ViewStatus!='warned') {   ?>
          [<a href="tools.php?action=marked_for_deletion&amp;viewstatus=warned&amp;overdue=<?=$OverdueOnly?>"> View warned only </a>] &nbsp;&nbsp;&nbsp;
<?php       }
        if ($ViewStatus!='pending') {   ?>
          [<a href="tools.php?action=marked_for_deletion&amp;viewstatus=pending&amp;overdue=<?=$OverdueOnly?>"> View pending only </a>] &nbsp;&nbsp;&nbsp;
<?php       }
        if ($ViewStatus!='both') {   ?>
          [<a href="tools.php?action=marked_for_deletion&amp;viewstatus=both&amp;overdue=<?=$OverdueOnly?>"> View pending and warned </a>] &nbsp;&nbsp;&nbsp;
<?php       }
        if ($ViewStatus!='unmarked') {   ?>
          [<a href="tools.php?action=marked_for_deletion&amp;viewstatus=unmarked&amp;overdue=<?=$OvrdueOnly?>"> View unmarked only </a>] &nbsp;&nbsp;&nbsp;
<?php       }       ?>

<?php       if ($NumOverdue && $ViewStatus!='unmarked') {
            if ($OverdueOnly) {  ?>
          [<a href="tools.php?action=marked_for_deletion&amp;viewstatus=<?=$ViewStatus?>&amp;overdue=0"> View due and overdue </a>] &nbsp;&nbsp;&nbsp;
<?php           } else {     ?>
          [<a href="tools.php?action=marked_for_deletion&amp;viewstatus=<?=$ViewStatus?>&amp;overdue=1"> View overdue only </a>] &nbsp;&nbsp;&nbsp;
<?php           }
        }
        if (check_perms('torrents_review_manage')){
?>
          [<a href="tools.php?action=marked_for_deletion_reasons"> Edit reasons </a>]
<?php   } ?>
        </div>
        <br/>
<?php
        $Torrents = get_torrents_under_review($ViewStatus, $OverdueOnly);
        $NumTorrents = count($Torrents);
        if ($ViewStatus!='unmarked') {
?>
        <form method="post" action="tools.php" id="reviewform">
            <div class="head">Marked for Deletion Mass Actions</div>
            <div class="box pad">
                <h3 style="float:right;margin:5px 10px 0 0;">Showing: <?=$ViewStatus=='both'?'pending and warned':$ViewStatus;?> <?=$OverdueOnly?'(overdue only)':'';?></h3>
<?php       if ($NumOverdue) {
        if ($CanManage) {  // not sure who should have what permissions here??    ?>
                <span style="position:absolute;">
                    <input type="submit" name="submit" title="Delete selected torrents" value="Delete selected" />
                </span>
<?php 		}
            if ($NumWarnedOverdue) {  ?>
                <!-- anyone with torrents_review permission can delete warned and overdue torrents  -->
                <input type="submit" name="submitdelall" style="width:350px;margin-left:-175px;left:50%;position:relative;" title="Delete <?=$NumWarnedOverdue?> Warned and Overdue torrents (red background)" value="Delete <?=$NumWarnedOverdue?> Warned and Overdue torrents" />

<?php           }   ?>
<?php       }   ?>
                <br style="clear:both" />
             </div>

            <div class="head">Torrents Marked for Deletion</div>
            <table>
                <tr class="colhead">
<?php  if ($NumOverdue && $CanManage) { ?><td width="8px"><input type="checkbox" onclick="toggleChecks('reviewform',this)" /></td><?php } ?>
                    <td>Torrent</td>
                    <td width="40px"><strong>Status</strong></td>
                    <td>time till nuke</td>
                    <td><strong>Reason</strong>
                    <td>Username</td>
                </tr>
<?php
        } else {
?>
            <div class="head">Torrents Awating Review</div>
            <table>
                <tr class="colhead">
                    <td>Torrent</td>
                    <td width="40px"><strong>Status</strong></td>
                    <td>Time</td>
                    <td><strong>Reason</strong>
                    <td>Username</td>
                </tr>
<?php
        }
?>
<?php
    if ($NumTorrents==0) { //
?>
                <tr>
                    <td colspan="6" class="center">no torrents are under review</td>
                </tr>
<?php
    } else {
        $Row = 'a';
        foreach ($Torrents as $Torrent) {
            $Row = $Row=='a'?'b':'a';
            list($TorrentID, $GroupID, $TorrentName, $Status, $ConvID, $KillTime, $Reason, $UserID, $Username) = $Torrent;

            $IsOverdue = strtotime($KillTime)<time();
?>
<?php  if ($Status!='Unmarked') { ?>
                <tr class="<?=($IsOverdue?($Status=='Pending'?'orangebar':'redbar'):"row$Row")?>">
<?php  }
       if ($NumOverdue && $CanManage && $ViewStatus != 'unmarked') { ?><td class="center"><?=$IsOverdue?'<input type="checkbox" name="id[]" value="'.$GroupID.'" />':''?></td><?php  } ?>
                    <td><a href="torrents.php?id=<?=$GroupID?>"><?=$TorrentName?></a></td>
                    <td><?=$Status?></td>
                    <td><?=time_diff($KillTime)?></td>
                    <td><?=$Reason?>
<?php
        if ($ConvID>0) {
                echo '<span style="float:right;">'.($Status=='Pending'?'(user sent fixed message) &nbsp;&nbsp;':'').'<a href="staffpm.php?action=viewconv&id='.$ConvID.'">'.($Status=='Pending'?'Message sent to staff':"reply sent to $Username").'</a></span>';
        } elseif ($Status == 'Warned') {
                echo '<span style="float:right;">(pm sent to '.$Username.')</span>';
        }
?>
                    </td>
                    <td><?=format_username($UserID, $Username)?></td>
                </tr>
<?php
        }
    } // end print table of warned torrents
?>
                <input type="hidden" name="viewstatus" value="<?=$ViewStatus?>" />
                <input type="hidden" name="overdue" value="<?=$OverdueOnly?>" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="action" value="mfd_delete" />
            </table>
        </form>
        <br/>
        <div class="head">Statistics</div>
        <div class="box pad">
        <table>
        <tr>
            <td style="width: 50%;">
<?php

$BaseSQL = "SELECT um.ID,
                   um.Username,
                   COUNT(r.ID) AS Num ,
                   (SELECT Count(torrents_reviews.ID)
                                         FROM torrents_reviews
                                         WHERE torrents_reviews.UserID=um.ID
                                           AND torrents_reviews.Status='Okay'
                                           AND torrents_reviews.Time > [TIMECLAUSE] ) AS NumOkay,
                   (SELECT Count(torrents_reviews.ID)
                                         FROM torrents_reviews
                                         WHERE torrents_reviews.UserID=um.ID
                                           AND torrents_reviews.Status='Warned'
                                           AND torrents_reviews.Time > [TIMECLAUSE] ) AS NumWarned
              FROM torrents_reviews AS r JOIN users_main AS um ON um.ID=r.UserID
             WHERE r.Status!='Pending'  AND r.Time > [TIMECLAUSE]
          GROUP BY r.UserID
          ORDER BY Num DESC";

$DB->query(str_replace('[TIMECLAUSE]', 'NOW() - INTERVAL 24 HOUR', $BaseSQL));
$Results = $DB->to_array();

?>
            <strong>MFD actions in the last 24 hours</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Total</td>
                    <td>Okay</td>
                    <td>Warned</td>
                </tr>
<?php  foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $NumOkay, $NumWarned) = $Result;
?>
                <tr>
                    <td><a href="reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$NumOkay?></td>
                    <td><?=$NumWarned?></td>
                </tr>
<?php  } ?>
            </table>
            <br /><br />
<?php

$DB->query(str_replace('[TIMECLAUSE]', 'NOW() - INTERVAL 1 WEEK', $BaseSQL));
$Results = $DB->to_array();

?>
            <strong>MFD actions in the last week</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Total</td>
                    <td>Okay</td>
                    <td>Warned</td>
                </tr>
<?php  foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $NumOkay, $NumWarned) = $Result;
?>
                <tr>
                    <td><a href="reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$NumOkay?></td>
                    <td><?=$NumWarned?></td>
                </tr>
<?php  } ?>
            </table>
        </td>
        <td>
<?php

$DB->query(str_replace('[TIMECLAUSE]', 'NOW() - INTERVAL 1 MONTH', $BaseSQL));
$Results = $DB->to_array();

?>
        <strong>MFD actions in the last month</strong>
        <table class="noborder">
            <tr class="colhead">
                <td>Username</td>
                <td>Total</td>
                <td>Okay</td>
                <td>Warned</td>
            </tr>
<?php  foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $NumOkay, $NumWarned) = $Result;
?>
            <tr>
                <td><a href="reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                <td><?=$Num?></td>
                <td><?=$NumOkay?></td>
                <td><?=$NumWarned?></td>
            </tr>
<?php  } ?>
        </table>
        <br /><br />
<?php

$DB->query(str_replace('[TIMECLAUSE]', "'0000-00-00 00:00:00'", $BaseSQL));
$Results = $DB->to_array();

?>
            <strong>MFD actions total</strong>
            <table class="noborder">
                <tr class="colhead">
                    <td>Username</td>
                    <td>Total</td>
                    <td>Okay</td>
                    <td>Warned</td>
                </tr>
<?php  foreach ($Results as $Result) {
    list($UserID, $Username, $Num, $NumOkay, $NumWarned) = $Result;
?>
                <tr>
                    <td><a href="reportsv2.php?view=resolver&amp;id=<?=$UserID?>"><?=$Username?></a></td>
                    <td><?=$Num?></td>
                    <td><?=$NumOkay?></td>
                    <td><?=$NumWarned?></td>
                </tr>
<?php  } ?>
            </table>
        </td></tr>
        </table>
        </div>
         <br/>
    </div>

<?php
show_footer();
