<?php
if (!check_perms('admin_donor_drives'))  error(403);

include(SERVER_ROOT . '/Legacy/sections/donate/functions.php');

$bbCode = new \Luminance\Legacy\Text;

show_header('Donation Drives', 'bitcoin,user,bbcode,jquery,jquery.cookie');
?>

<div class="thin">

    <h2>Donation Drives</h2>

    <div class="head">New Drive Creation</div>
    <table>
        <form action="tools.php" method="post">
        <input type="hidden" name="action" value="new_drive" />
        <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
            <tr>
            <td colspan="2">On this page you can create, start, and end donation drives.<br><br>
                <p style="margin-left: 40px">1) First fill out a title (this will be displayed at the top of pages when the drive is active.<br>
                       A target EUR (self explanitory).<br>
                       And a description (this will be used on the donation forum announcement thread).<br>
                    2) Once the drive is created, you will then see them below. They will be displayed in the order created.<br>
                       At this point the drive is still inactive / not started. To start a drive, you will need to set a start time. You can either set current time or set a future time.<br>
                    3) Next you will either create a new thread or use an exisiting thread for the announcement using the Thread ID line.<br>
                    4) Once the above is all set, click "Start Donation Drive"<br>
                        After the donation drive has been started, you will see the donation banner which includes your title, a message, a link to donate.php, and a progress bar.<br>
                        5) The donation drive will stay active until either the target EUR is reached or the drive is manually ended by clicking "Finish Donation Drive"</p><br>
                Good Luck!<br/></td>
            </tr>
            <tr class="colhead">
                <td>title</td>
                <td style="width:200px">target &euro;</td>
            </tr>
            <tr class="colhead">
                <td colspan="2">description</td>
            </tr>
            <tr>
                <td><input type="text" name="drivename" class="long" value="New Donation Drive" /></td>
                <td><strong> &euro;</strong> &nbsp;<input type="text" name="target" value="" /></td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="box pad hidden" id="preview_new0" style="text-align:left;"></div>
                    <div  class="" id="editor_new0" >
                                  <?php  $bbCode->display_bbcode_assistant("preview_message_new0" , true);?>
                    <textarea id="preview_message_new0" name="body" class="long" rows="8"><?= display_str("[#=title]New Donation Drive![/#]\n\nEnter your description here\n\n[url=/donate.php][b]To donate click here[/b][/url]") ?></textarea>
                    </div>
                    <input type="button" value="Toggle Preview" onclick="Preview_Toggle('new0');" />
            </tr>
            <tr>
                <td colspan="2">
                    <span style="float:right">
                        <input type="submit" name="createnew" value="Create new donation drive" title="This does not start the drive, it just creates it - you then review the details before starting it" /> &nbsp; &nbsp;
                    </span>
                </td>
            </tr>
        </form>
    </table>
    <br/>
    <br/>
    <br/>

<?php
    $Drives = $master->db->rawQuery(
        "SELECT ID,
                name,
                start_time,
                target_euros,
                description,
                threadid,
                end_time,
                raised_euros,
                state
           FROM donation_drives
       ORDER BY state,
                end_time DESC,
                start_time DESC,
                ID desc"
    )->fetchAll(\PDO::FETCH_NUM);
    foreach ($Drives as $Drive) {
        list($ID, $name, $start_time, $target_euros, $description, $threadid, $end_date, $raised_euros, $state) = $Drive;
        $disabled_html = $state == 'finished' ? 'disabled="disabled"' : '';
?>
    <a id="drive<?=$ID?>"></a>
    <div class="head"><?= $name ?><span style="float:right;">#<?=$ID?></span></div>
    <form action="tools.php" method="post">
        <input type="hidden" name="action" value="edit_drive" />
        <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
        <input type="hidden" name="driveid" value="<?=$ID?>" />
      <!--   <input type="hidden" name="state" value="<?=$state?>" /> -->
        <table class="donate_drives">
            <tr class="colhead">
                <td colspan="2">title</td>
                <td style="width:200px">target &euro;</td>
                <td style="width:100px">status</td>
            </tr>
            <tr class="rowa">
                <td colspan="2"><input type="text" name="drivename" class="long" value="<?= $name ?>" <?= $disabled_html ?> /></td>
                <td><input type="text" name="target" value="<?= $target_euros ?>" <?= $disabled_html ?> /></td>
                <td><span class="button<?php  if ($state == 'active') echo ' greenButton'; elseif ($state == 'finished') echo ' redButton'; else echo ' greyButton';?>">
                        <?= $state; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td class="label">
                    <label for="starttime">Start time</label>
                </td>
                <td colspan="<?=($state != 'notstarted'?'1':'3')?>">
                <?php   if ($state == 'notstarted') { ?>
                        <input type="radio" name="autodate" value="0" title="if selected the start time is when you click the start donation drive button" checked="checked" />
                        Use current time when drive is started
                        &nbsp;&nbsp;&nbsp;<input type="radio" name="autodate" value="1" title="if selected a valid date must be supplied" />
                        Specify the start date manually:
                        <input type="text" id="starttime" name="starttime" value="<?= date("Y-m-d H:i:s") ?>" title="start time is used to calculate the donation total so far" />
                <?php   } elseif ($state == 'active') { ?>
                        <input type="text" id="starttime" name="starttime" value="<?= $start_time ?>" title="start time is used to calculate the donation total so far" <?= $disabled_html ?> />
                <?php   } else {  ?>
                        <?= time_diff($start_time, 3, true, false, 1)  ?>
                <?php   }  ?>

                <?php   if ($state != 'notstarted') {
                        if ($state == 'finished') { ?>
                        &nbsp;&nbsp;&nbsp;<div style="display:inline-block" class="label"> End time: </div>
                        <?= time_diff($end_date, 3, true, false, 1)  ?>
                <?php       } ?>
                    </td>
                    <td  colspan="2">
                <?php
                        if ($state == 'active') {
                            list($raised_euros, $count) = $master->db->rawQuery(
                                "SELECT SUM(amount_euro),
                                        COUNT(ID)
                                   FROM bitcoin_donations
                                  WHERE state != 'unused'
                                    AND received >= ?",
                                [$start_time]
                            )->fetch();
                        }
                ?>
                        <div style="display:inline-block" class="label"> Raised: </div> &euro; <?=number_format($raised_euros,2);
                        if ($state == 'active') echo " &nbsp; from $count donations";  ?>
                <?php  }  ?>
                </td>
            </tr>
            <tr class="rowb">
                <td class="label" >
                    <label for="threadid">Thread ID</label>
                </td>
                <td colspan="3">
                  <?php  if ($state == 'notstarted') { ?>
                        <input type="radio" name="autothread" value="0" title="if selected a forum must be selected" checked="checked" />
                        Automatically create thread in forum:
                        <?= $master->repos->forums->printForumSelect(null, null, ANNOUNCEMENT_FORUM_ID) ?>

                        &nbsp;&nbsp;&nbsp;<input type="radio" name="autothread" value="1" title="if selected a valid threadid must be supplied" />
                        Thread already discussing this topic:
                        <input type="text" id="threadid" name="threadid" size="8" value="<?= display_str($threadid) ?>" />
                        &nbsp;(must be a valid thread id)
                  <?php  } else { ?>
                        <input type="text" id="threadid" name="threadid" size="8" value="<?= display_str($threadid) ?>" <?= $disabled_html ?> />
                        &nbsp; <?php  if ($threadid && is_integer_string($threadid)) {   ?>
                            <a href="/forum/thread/<?=$threadid?>" target="_blank">View thread</a>
                  <?php     }
                     }  ?>
                </td>
            </tr>
            <tr class="rowa">
                <td colspan="4">
                    <?php  if ($state == 'notstarted') { ?>
                        <div class="box pad" id="preview_<?=$ID?>" style="text-align:left;"><?= $bbCode->full_format($description, true) ;?></div>
                        <div  class="hidden" id="editor_<?=$ID?>" >
                                      <?php  $bbCode->display_bbcode_assistant("preview_message_". $ID , true);?>
                                    <textarea id="preview_message_<?=$ID?>" name="body" class="long" rows="8"><?=display_str($description)?></textarea>
                        </div>
                    <?php  } ?>
                    <?php  if ($state != 'notstarted') { ?>
                        <div class="box pad" id="preview_<?=$ID?>" style="text-align:left;">
                            <?= $bbCode->full_format($description, true) ;?>
                        </div>
                    <?php  } ?>
                    <?php  if ($state != 'finished') { ?>
                    <span style="float:right">
                        <?php  if ($state == 'notstarted') { ?>
                            <input type="button" value="Toggle Preview" onclick="Preview_Toggle('<?=$ID?>');" />
                        <?php  } ?>
                        <input type="submit" name="submit" value="Save changes" title="" /> &nbsp; &nbsp;
                    </span>
                    <?php  } ?>
                </td>
            </tr>
            <?php  if ($state != 'finished') {  ?>
            <tr class="rowa">
                <td colspan="4" style="text-align:center;">
                        <?php  if ($state == 'active') { // = 'active'      ?>
                            <input type="submit" name="submit" style="font-size: 1.5em" value="Finish Donation Drive" title="" /> &nbsp; &nbsp;
                        <?php  } ?>
                </td>
            </tr>
           <?php  }  ?>
           <?php  if ($state == 'notstarted') {   ?>
            <tr class="rowa">
                <td colspan="4" style="text-align:center;">
                        <input type="submit" name="submit" style="font-size: 1.5em" value="Start Donation Drive" title="" />
                </td>
            </tr>
           <?php  } ?>
        </table>
    </form>
    <br/><br/>
<?php   } ?>
</div>

<?php
show_footer();
