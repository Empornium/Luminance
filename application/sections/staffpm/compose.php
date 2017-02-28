<?php
include(SERVER_ROOT . '/classes/class_text.php');
$Text = new TEXT;

show_header('Start Conversation', 'inbox,staffpm,bbcode,jquery');
?>

<div class="thin">
    <h2>Start Staff Conversation</h2>
    <div class="linkbox">

<?php  if ($IsStaff) { ?>
            [ &nbsp;<a href="staffpm.php?view=my">My unanswered<?= $NumMy > 0 ? " ($NumMy)" : '' ?></a>&nbsp; ] &nbsp;
        <?php
        }
        // FLS/Staff
        if ($IsFLS) {
            ?>
            [ &nbsp;<a href="staffpm.php?view=unanswered">All unanswered<?= $NumUnanswered > 0 ? " ($NumUnanswered)" : '' ?></a>&nbsp; ] &nbsp;
            [ &nbsp;<a href="staffpm.php?view=open">Open<?= $NumOpen > 0 ? " ($NumOpen)" : '' ?></a>&nbsp; ] &nbsp;
            [ &nbsp;<a href="staffpm.php?view=resolved">Resolved</a>&nbsp; ]  &nbsp;
            [ &nbsp;<a href="staffpm.php?action=responses&convid=<?= $ConvID ?>">Common Answers</a>&nbsp; ]
            <?php
            // User
        } else {
            ?>
            [ &nbsp;<a href="staffpm.php">Back to inbox</a>&nbsp; ]
            <?php
        }
        ?>
    </div>

    <div class="messagecontainer"><div id="ajax_message" class="hidden center messagebar"></div></div>

        <div id="compose" >
            <div id="common_answers" class="hidden">
                <div class="head"> <strong>Common Answers</strong></div>
                <div class="box pad center">

                    <select id="common_answers_select" onChange="UpdateMessage();">
                        <option id="first_common_response">Select a message</option>
                        <?php
                        // List common responses
                        $DB->query("SELECT ID, Name FROM staff_pm_responses ORDER BY Name ASC");
                        while (list($ID, $Name) = $DB->next_record()) {
                            ?>
                            <option value="<?= $ID ?>"><?= $Name ?></option>
                        <?php  } ?>
                    </select>
                    <input type="button" value="Set message" onClick="SetMessage();" />
                    <input type="button" value="Create new / Edit" onClick="location.href='staffpm.php?action=responses&convid=<?= $ConvID ?>'" />
                    <br/><br/>
                    <div id="common_answers_body" class="body">Select an answer from the dropdown to view it.</div>

                </div>
            </div>
<?php
            if (!empty($_GET['toid']) && is_number($_GET['toid'])) {
                $DB->query("SELECT Username FROM users_main WHERE ID='$_GET[toid]'");
                list($Username) = $DB->next_record();
            }
?>
            <div class="head">Start Conversation with <?=($Username?$Username:'User')?></div>
            <div class="box pad">
                <div id="preview" class="box pad hidden"></div>

                <form action="staffpm.php" method="post" id="messageform">
                    <div id="StaffPM" >
                        <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                        <input type="hidden" name="action" value="takenewpost" />
                        <input type="hidden" name="prependtitle" value="Staff PM - " />
                        <table>
                            <tr>
                                <td class="label"><label for="user">Send to</label></td>
                                <td>
<?php
                                    if ($Username) {  ?>
                                        <input type="hidden" name="toid" value="<?=$_GET['toid']?>" />
                                        <input class="long" type="text" name="user" id="user" disabled="disabled" value="<?= display_str($Username) ?>" />
<?php                                   } else { ?>
                                        <input class="long" type="text" name="user" id="user" value="" />
<?php                                   }   ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="label"><label for="subject">Subject</label></td>
                                <td><input class="long" type="text" name="subject" id="subject" value="<?= display_str($Subject) ?>" /></td>
                            </tr>
                        </table>

                        <br />

                        <label for="message"><h3>Message</h3></label>
                        <?php  $Text->display_bbcode_assistant("quickpost$ReportID"); ?>
                        <textarea rows="6" class="long" name="message" id="quickpost"><?= display_str($Message) ?></textarea>
                        <br />

                    </div>

                    <input type="button" id="previewbtn<?= $ReportID ?>" value="Preview" onclick="PreviewMessage();" />

                    <input type="button" value="Common answers" onClick="$('#common_answers<?= $ReportID ?>').toggle();" />
                    <input id="submit_pm<?= $ReportID ?>" type="submit" value="Send message to selected user" />

            </form>
            </div>
        </div>

</div>

<?php
show_footer();
