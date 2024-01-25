<?php
if (!($IsFLS)) {
    // Logged in user is not FLS or Staff
    error(403);
}

include_once(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

show_header('Staff PMs', 'bbcode,staffpm,jquery');

$View   = isset($_REQUEST['view'])?$_REQUEST['view']:'staff';
$Action = isset($_REQUEST['action'])?$_REQUEST['action']:'staff';

$bbCode = new \Luminance\Legacy\Text;

list($NumMy, $NumUnanswered, $NumOpen) = get_num_staff_pms($activeUser['ID'], $activeUser['Class']);
?>
<div class="thin">
    <div class="linkbox">
<?php   if ($IsStaff) {
            echo view_link($View, 'my',         "<a href='/staffpm.php?view=my'>My unanswered". ($NumMy > 0 ? "($NumMy)":"") ."</a>");
        }
            echo view_link($View, 'unanswered', "<a href='/staffpm.php?view=unanswered'>All unanswered". ($NumUnanswered > 0 ? " ($NumUnanswered)":"") ."</a>");
            echo view_link($View, 'open',       "<a href='/staffpm.php?view=open'>Open" . ($NumOpen > 0 ? " ($NumOpen)":"") ."</a>");
            echo view_link($View, 'resolved',   "<a href='/staffpm.php?view=resolved'>Resolved</a>");
        if (check_perms('admin_stealth_resolve')) {
            echo view_link($View, 'stealthresolved', "<a href='/staffpm.php?view=stealthresolved'>Stealth Resolved</a>");
        }
            echo view_link($Action, 'responses',     "<a href='/staffpm.php?action=responses'>Common Answers</a>");
        if (check_perms('admin_staffpm_stats')) {
            echo view_link($Action, 'stats',         "<a href='/staffpm.php?action=stats'>StaffPM Stats</a>");
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
                            <?php  $bbCode->display_bbcode_assistant("response_message_0", true); ?>
                    <textarea class="long" onfocus="if (this.value == 'New message') this.value='';"
                              rows="10" name="message" id="response_message_0">New message</textarea>
                </div>
                    <br />
                    <input type="button" value="Toggle preview" onClick="PreviewResponse(0);" />
                    <input type="hidden" name="convid" value="<?=$ConvID ?? 0?>" />
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
$staffPMs = $master->db->rawQuery(
    "SELECT ID,
            Message,
            Name
       FROM staff_pm_responses
   ORDER BY Name ASC"
)->fetchAll(\PDO::FETCH_OBJ);
foreach ($staffPMs as $staffPM) {
?>
            <div id="response_head_<?= $staffPM->ID ?>" class="head"><?= $staffPM->Name ?></div>
            <div class="box pad">
                <strong>Name:</strong>
                <input type="hidden" name="id" value="<?= $staffPM->ID ?>" />
                <input type="text" name="name" id="response_name_<?= $staffPM->ID ?>" size="87" value="<?= display_str($staffPM->Name) ?>" />
                <br />
                <br />
                <div id="response_<?= $staffPM->ID ?>" class="box">
                    <form id="response_form_<?= $staffPM->ID ?>" action="">
                    <div id="response_div_<?= $staffPM->ID ?>" style="text-align:left;">
                        <?=$bbCode->full_format($staffPM->Message, true, true)?>
                    </div>
                    <div class="pad hidden" id="response_editor_<?= $staffPM->ID ?>" >
                        <?php  $bbCode->display_bbcode_assistant("response_message_{$staffPM->ID}", true); ?>
                            <textarea class="long" onfocus="if (this.value == 'New message') this.value='';"
                            rows="10" id="response_message_<?= $staffPM->ID ?>" name="message"><?= display_str($staffPM->Message) ?></textarea>
                    </div>
                    <br />
                    <input type="button" value="Toggle preview" onClick="PreviewResponse(<?= $staffPM->ID ?>);" />
                    <input type="button" value="Delete" onClick="DeleteMessage(<?= $staffPM->ID ?>);" />
                    <input type="button" value="Save" id="save_<?= $staffPM->ID ?>" onClick="SaveMessage(<?= $staffPM->ID ?>);" />
                </div>
            </form>
        </div>
    <div class="messagecontainer" id="container_<?= $staffPM->ID ?>"><div id="ajax_message_<?= $staffPM->ID ?>" class="hidden center messagebar"></div></div>
<?php
}

if (isset($_GET['added'])) {
?>
    <script type="text/javascript">
        addDOMLoadEvent(function () { Display_Message(<?=(int) $_GET['added']?>) });
    </script>
<?php
}
?>
    </div>
</div>
<?php
show_footer();
