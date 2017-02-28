<?php
if (!check_perms('admin_donor_drives'))  error(403);

include(SERVER_ROOT . '/sections/donate/functions.php');

include(SERVER_ROOT . '/sections/forums/functions.php');
include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class

$Text = new TEXT;

$ForumCats = get_forum_cats();
//This variable contains all our lovely forum data
$Forums = get_forums_info();

show_header('Donation Drives', 'bitcoin,user,bbcode,jquery');
?>

<div class="thin">

    <h2>Donation Drives</h2>

    <div class="head">new drive</div>
    <table>
        <form action="tools.php" method="post">
        <input type="hidden" name="action" value="new_drive" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <tr>
                <td colspan="2">some instructions<br/></td>
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
                                  <?php  $Text->display_bbcode_assistant("preview_message_new0" , true);?>
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
    $DB->query("SELECT ID, name, start_time, target_euros, description, threadid, end_time, raised_euros, state
                      FROM donation_drives ORDER BY state, end_time DESC, start_time DESC, ID desc");
    $Drives = $DB->to_array(false, MYSQL_NUM);
    foreach ($Drives as $Drive) {
        list($ID, $name, $start_time, $target_euros, $description, $threadid, $end_date, $raised_euros, $state) = $Drive;
        $disabled_html = $state == 'finished' ? 'disabled="disabled"' : '';
?>
    <a id="drive<?=$ID?>"></a>
    <div class="head"><?= $name ?><span style="float:right;">#<?=$ID?></span></div>
    <form action="tools.php" method="post">
        <input type="hidden" name="action" value="edit_drive" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
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
                <td><span class="button<?php  if($state=='active')echo ' greenButton';elseif($state=='finished')echo ' redButton';else echo ' greyButton';?>">
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
                        <input type="text" name="starttime" value="<?= date("Y-m-d H:i:s") ?>" title="start time is used to calculate the donation total so far" />
                <?php   } elseif ($state == 'active') { ?>
                        <input type="text" name="starttime" value="<?= $start_time ?>" title="start time is used to calculate the donation total so far" <?= $disabled_html ?> />
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
                            $DB->query("SELECT SUM(amount_euro), Count(ID) FROM bitcoin_donations WHERE state!='unused' AND received >= '".db_string($start_time)."'");
                            list($raised_euros, $count)=$DB->next_record();
                        }
                ?>
                        <div style="display:inline-block" class="label"> Raised: </div> &euro; <?=number_format($raised_euros,2);
                        if($state == 'active') echo " &nbsp; from $count donations";  ?>
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
                        <?= print_forums_select($Forums, $ForumCats, ANNOUNCEMENT_FORUM_ID) ?>

                        &nbsp;&nbsp;&nbsp;<input type="radio" name="autothread" value="1" title="if selected a valid threadid must be supplied" />
                        Thread already discussing this topic:
                        <input type="text" name="threadid" size="8" value="<?= display_str($threadid) ?>" />
                        &nbsp;(must be a valid thread id)
                  <?php  } else { ?>
                        <input type="text" name="threadid" size="8" value="<?= display_str($threadid) ?>" <?= $disabled_html ?> />
                        &nbsp; <?php  if ($threadid && is_number($threadid)) {   ?>
                            <a href="/forums.php?action=viewthread&threadid=<?=$threadid?>" target="_blank">View thread</a>
                  <?php     }
                     }  ?>
                </td>
            </tr>
            <tr class="rowa">
                <td colspan="4">
                    <?php  if ($state == 'notstarted') { ?>
                        <div class="box pad" id="preview_<?=$ID?>" style="text-align:left;"><?= $Text->full_format($description, true) ;?></div>
                        <div  class="hidden" id="editor_<?=$ID?>" >
                                      <?php  $Text->display_bbcode_assistant("preview_message_". $ID , true);?>
                                    <textarea id="preview_message_<?=$ID?>" name="body" class="long" rows="8"><?=display_str($description)?></textarea>
                        </div>
                    <?php  } ?>
                    <?php  if ($state != 'notstarted') { ?>
                        <div class="box pad" id="preview_<?=$ID?>" style="text-align:left;">
                            <?= $Text->full_format($description, true) ;?>
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
