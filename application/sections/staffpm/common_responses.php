<?php
if (!($IsFLS)) {
    // Logged in user is not FLS or Staff
    error(403);
}

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
include(SERVER_ROOT.'/sections/staffpm/functions.php');
include(SERVER_ROOT.'/common/functions.php');

show_header('Staff PMs', 'bbcode,staffpm,jquery');

$View   = isset($_REQUEST['view'])?$_REQUEST['view']:'staff';
$Action = isset($_REQUEST['action'])?$_REQUEST['action']:'staff';

$Text = new TEXT;

list($NumMy, $NumUnanswered, $NumOpen) = get_num_staff_pms($LoggedUser['ID'], $LoggedUser['Class']);
?>
<div class="thin">
    <div class="linkbox">
<?php   if ($IsStaff) {
            echo view_link($View, 'my',         "<a href='staffpm.php?view=my'>My unanswered". ($NumMy > 0 ? "($NumMy)":"") ."</a>");
        }
            echo view_link($View, 'unanswered', "<a href='staffpm.php?view=unanswered'>All unanswered". ($NumUnanswered > 0 ? " ($NumUnanswered)":"") ."</a>");
            echo view_link($View, 'open',       "<a href='staffpm.php?view=open'>Open" . ($NumOpen > 0 ? " ($NumOpen)":"") ."</a>");
            echo view_link($View, 'resolved',   "<a href='staffpm.php?view=resolved'>Resolved</a>");
        if (check_perms('admin_stealth_resolve')) {
            echo view_link($View, 'stealthresolved', "<a href='staffpm.php?view=stealthresolved'>Stealth Resolved</a>");
        }
            echo view_link($Action, 'responses',     "<a href='staffpm.php?action=responses'>Common Answers</a>");
        if (check_perms('admin_staffpm_stats')) {
            echo view_link($Action, 'stats',         "<a href='staffpm.php?action=stats'>StaffPM Stats</a>");
        } ?>
        <br />
        <br />
    </div>
    <div id="commonresponses">
        <div class="messagecontainer" id="container_0"><div id="ajax_message_0" class="hidden center messagebar"></div></div>
                <div class="head">Staff PMs - Manage common responses</div>
        <div id="response_0" class="box">
                <form id="response_form_0" action="" method="post" onsubmit="return ValidateForm(0)">

                            <p>
                                <strong>Name:</strong>
                                <input onfocus="if (this.value == 'New name') this.value='';"
                                            type="text" name="name" id="response_name_0" size="87" value="New name"
                                />
                            </p>
                <div class="box pad hidden" id="response_div_0" style="text-align:left;">
                </div>
                <div  class="pad" id="response_editor_0" >
                            <?php  $Text->display_bbcode_assistant("response_message_0", true); ?>
                    <textarea class="long" onfocus="if (this.value == 'New message') this.value='';"
                              rows="10" name="message" id="response_message_0">New message</textarea>
                </div>
                    <br />
                    <input type="button" value="Toggle preview" onClick="PreviewResponse(0);" />
                    <input type="hidden" name="convid" value="<?=$ConvID?>" />
                    <input type="hidden" name="id" value="0" />
                    <input type="hidden" name="action" value="edit_response" />
                    <input type="submit" name="submit" value="Save" id="save_0" />
            </form>
        </div><a id="old_responses" name="old_responses"></a>
        <br />
        <br />
        <div class="center">
            <h3>Edit old responses:</h3>
        </div>
<?php

// List common responses
$DB->query("SELECT ID, Message, Name FROM staff_pm_responses ORDER BY Name ASC");
while (list($ID, $Message, $Name) = $DB->next_record()) {

?>
        <div class="messagecontainer" id="container_<?=$ID?>"><div id="ajax_message_<?=$ID?>" class="hidden center messagebar"></div></div>
            <div  id="response_head_<?=$ID?>" class="head"></div>
            <div class="box pad">
                <strong>Name:</strong>
                <input type="hidden" name="id" value="<?=$ID?>" />
                <input type="text" name="name" id="response_name_<?=$ID?>" size="87" value="<?=display_str($Name)?>" />
                <br />
                <br />
                <div id="response_<?=$ID?>" class="box">
                    <form id="response_form_<?=$ID?>" action="">
                    <div id="response_div_<?=$ID?>" style="text-align:left;">
                        <?=$Text->full_format($Message, true, true)?>
                    </div>
                    <div class="pad hidden" id="response_editor_<?=$ID?>" >
                        <?php  $Text->display_bbcode_assistant("response_message_".$ID, true); ?>
                            <textarea class="long" onfocus="if (this.value == 'New message') this.value='';"
                            rows="10" id="response_message_<?=$ID?>" name="message"><?=display_str($Message)?></textarea>
                    </div>
                    <br />
                    <input type="button" value="Toggle preview" onClick="PreviewResponse(<?=$ID?>);" />
                    <input type="button" value="Delete" onClick="DeleteMessage(<?=$ID?>);" />
                    <input type="button" value="Save" id="save_<?=$ID?>" onClick="SaveMessage(<?=$ID?>);" />
                </div>
            </form>
        </div>
<?php
}

if (isset($_GET['added'])) {
?>
    <script type="text/javascript">
        addDOMLoadEvent(function () { Display_Message(<?=(int) $_GET['added']?>) } );
    </script>
<?php
}
?>
    </div>
</div>
<?php
show_footer();
