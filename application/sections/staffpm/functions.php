<?php
function make_staffpm_note($Message, $ConvID)
{
        global $DB;
        $DB->query("SELECT ID, Message FROM staff_pm_messages WHERE ConvID=$ConvID AND IsNotes");
        if (list($ID, $Notes) = $DB->next_record()) {
            $Notes = $Message."[br]".$Notes;
            $DB->query("UPDATE staff_pm_messages SET Message='$Notes' WHERE ID=$ID AND IsNotes");
        } else {
            $DB->query("
                INSERT INTO staff_pm_messages
                    (UserID, SentDate, Message, ConvID, IsNotes)
                VALUES
                    (0, '".sqltime()."', '$Message', $ConvID, TRUE)"
            );
        }
}
function get_num_staff_pms($UserID, $UserLevel)
{
        global $DB, $Cache;
        $DB->query("SELECT COUNT(ID) FROM staff_pm_conversations
                             WHERE (AssignedToUser=$UserID OR Level <=$UserLevel) AND Status IN ('Unanswered', 'User Resolved') AND NOT StealthResolved");
        list($NumUnanswered) = $DB->next_record();
        $DB->query("SELECT COUNT(ID) FROM staff_pm_conversations
                             WHERE (AssignedToUser=$UserID OR Level <=$UserLevel) AND Status IN ('Open', 'Unanswered', 'User Resolved') AND NOT StealthResolved");
        list($NumOpen) = $DB->next_record();
        $DB->query("SELECT COUNT(ID) FROM staff_pm_conversations
                             WHERE (AssignedToUser=$UserID OR Level =$UserLevel) AND Status='Unanswered' AND NOT StealthResolved");
        list($NumMy) = $DB->next_record();

        return array($NumMy, $NumUnanswered, $NumOpen);
}

function print_compose_staff_pm($Hidden = true, $Assign = 0, $Subject ='', $Msg = '', $Text = false)
{
        global $LoggedUser;
        $IsStaff = $LoggedUser['DisplayStaff'] == 1;
        if (!$Text) {
            include(SERVER_ROOT.'/classes/class_text.php');
            $Text = new TEXT;
        }
        if ($Msg=='changeusername') {
            $Subject='Change Username';
            $Msg="\n\nI would like to change my username to\n\nBecause";
            $Assign='admin';
        } elseif ($Msg=='donategb' || $Msg=='donatelove') {
            $Subject='I would like to donate for ';
            if ($Msg=='donategb') {
                $Subject .= 'GB';
                $Msg="\n\nPlease send me instructions on how to donate to remove gb from my download.";
            } else {
                $Subject .= 'love';
                $Msg="\n\nPlease send me instructions on how to donate to help support the site.";
            }
            $Assign='sysop';
            $AssignDirect = '1000';
        } elseif ($Msg=='nobtcrate') {
            $Subject='Error: No exchange rate for bitcoin';
            $Msg='';
            $Assign='admin';
        }

        ?>
        <div id="compose" class="<?=($Hidden ? 'hide' : '')?>">
             <?php  if ($LoggedUser['SupportFor'] !="" || $IsStaff) {  ?>
                    <div class="box pad">
                      <strong class="important_text">Are you sure you want to send a message to staff? You are staff yourself you know...</strong>
                    </div>
             <?php  }  ?>
                    <div id="preview" class="hidden"></div>
                    <form action="staffpm.php" method="post" id="messageform">
                    <div id="quickpost">
                <input type="hidden" name="action" value="takepost" />
                <input type="hidden" name="prependtitle" value="Staff PM - " />

                <label for="subject"><h3>Subject</h3></label>
                <input class="long" type="text" name="subject" id="subject" value="<?=display_str($Subject)?>" />
                <br />

                <label for="message"><h3>Message</h3></label>
                            <?php  $Text->display_bbcode_assistant("message"); ?>
                <textarea rows="10" class="long" name="message" id="message"><?=display_str($Msg)?></textarea>
                <br />

                    </div>
                <input type="button" value="Hide" onClick="jQuery('#compose').toggle();return false;" />
                <strong>Send to: </strong>
<?php                   if ($AssignDirect) { ?>
                <input type="hidden" name="level" value="<?=$AssignDirect?>" />
                <input type="text" value="<?=$Assign?>" disabled="disabled" />
<?php                   } else { ?>
                <select name="level">
                    <option value="0"<?php if(!$Assign)echo ' selected="selected"';?>>First Line Support</option>
                    <option value="500"<?php if($Assign=='mod')echo ' selected="selected"';?>>Moderators</option>
                    <option value="549"<?php if($Assign=='smod')echo ' selected="selected"';?>>Senior Staff</option>
<?php                       if($IsStaff) { ?>
                    <option value="600"<?php if($Assign=='admin')echo ' selected="selected"';?>>Admin Team</option>
<?php                       } ?>
                </select>
<?php                   } ?>
                <input type="button" id="previewbtn" value="Preview" onclick="Inbox_Preview();" />
                        <input type="submit" value="Send message" />

            </form>
        </div>
<?php  }
