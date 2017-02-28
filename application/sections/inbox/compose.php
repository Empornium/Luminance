<?php
include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

if ($_REQUEST['action']=='forward') { // forwarding a msg

    $Header = "Forward";

    if ($_POST['forwardto']=='staff') {
        $ToID = $_POST['receiverid'];
    } else {
        $ToUsername = $_POST['receivername'];
        $DB->query("SELECT ID FROM users_main WHERE Username='".db_string($ToUsername)."'");
        if ($DB->record_count()==0) {
            error("Could not find user '$ToUsername' to forward to");
        }
        list($ToID) = $DB->next_record();
    }

    if ($_POST['forwardmessage']=='conversation') {
        $MsgType = 'conversation';
        $ConvID = (int) $_POST['convid'];
        $DB->query("SELECT pm.Subject, IFNULL(um.Username,'system'), m.Body
                FROM pm_messages as m
                JOIN pm_conversations AS pm ON pm.ID=m.ConvID
                JOIN pm_conversations_users AS u ON m.ConvID=u.ConvID AND m.ConvID='$ConvID'
           LEFT JOIN users_main AS um ON um.ID=m.SenderID
               WHERE u.UserID=".$LoggedUser['ID']);
        $FwdBody = "conv#$ConvID";

    } else {
        $msgID = (int) $_POST['forwardmessage'];
        $MsgType = 'message';
        $DB->query("SELECT pm.Subject, IFNULL(um.Username,'system'), m.Body
                FROM pm_messages as m
                JOIN pm_conversations AS pm ON pm.ID=m.ConvID
                JOIN pm_conversations_users AS u ON m.ConvID=u.ConvID
           LEFT JOIN users_main AS um ON um.ID=m.SenderID
               WHERE m.ID='$msgID'
                 AND u.UserID=".$LoggedUser['ID']);

        $msgID = " (msg#$msgID)";
    }

    while (list($Sub, $Sendername, $Body) = $DB->next_record()) {
        if (!$Subheader) {
            $Subheader = $Sub;
            $FwdBody = "[bg=#d3e3f3]FWD: $Subheader          [color=grey]{$FwdBody}[/color][/bg]\n";
        }
        $FwdBody .= "[quote=$Sendername$msgID]{$Body}[/quote]\n";
    }
    $FwdBody="[br]$FwdBody";
    $Subject = "FWD: $Subheader";

} else { // composing a new msg
    $Header = "Send";
    $ToID = $_GET['to'];
    $MsgType = 'message';
}

if (!$ToID || !is_number($ToID)) { error(404); }

if (!empty($LoggedUser['DisablePM']) && !isset($StaffIDs[$ToID])) {
    error("Your PM rights have been disabled.");
}

if ($ToID == $LoggedUser['ID']) {
        error("You cannot start a conversation with yourself!");
}

if (!$ToUsername) {
    $DB->query("SELECT Username FROM users_main WHERE ID='$ToID'");
    list($ToUsername) = $DB->next_record();
    if (!$ToUsername) { error(404); }
}

show_header('Compose', 'inbox,bbcode');

?>
<div class="thin">
    <h2><?=$Header?> a <?=$MsgType?> to <a href="user.php?id=<?=$ToID?>"><?=$ToUsername?></a></h2>
<?php
    if ($FwdBody) {
        ?>
        <div class="head">
            <?=$MsgType; //"$MsgType $msgID ($Subheader)"?> to be forwarded:
        </div>
        <div class="box vertical_space">
            <div class="body" >
                <?=$Text->full_format($FwdBody, true)?>
            </div>
        </div>
        <?php
    }

    if (isset($StaffIDs[$ToID])) {
?>
        <div class="box pad shadow">
            You are sending a PM to a member of staff. IF this is regarding a staffing issue
            <strong class="important_text">please use the <a href="staff.php?show=1&assign=mod">staff message form</a> instead.</strong> (You can specify if you want it to only be seen by admins or mods+ if you need to)
            <br />This way it can be dealt with appropriately and quickly. Please note - PM's sent to staff that are about staffing or moderation issues may be responded to as a new staff message by any appropriate staff member.
        </div>
<?php
    }
?>

    <div id="preview" class="hidden"></div>
    <form action="inbox.php" method="post" id="messageform">
        <div id="quickpost">
            <div class="head"><?=($FwdBody?'Add':'Compose')?> message</div>
            <div class="box pad">
                        <input type="hidden" name="action" value="takecompose" />
                        <input type="hidden" name="toid" value="<?=$ToID?>" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="forwardbody" value="<?=$FwdBody?>" />
                        <h3>Subject</h3>
                        <input type="text" name="subject" class="long" value="<?=(!empty($Subject) ? $Subject : '')?>"/>
                        <br />
                        <h3>Body</h3>
                        <?php  $Text->display_bbcode_assistant("body", get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])); ?>
                        <textarea id="body" name="body" class="long" rows="10"><?=(!empty($Body) ? $Body : '')?></textarea>
            </div>
        </div>
        <div class="center">
             <input type="button" id="previewbtn" value="Preview" onclick="Inbox_Preview();" />
             <input type="submit" value="Send <?php  if($FwdBody) echo 'forwarded ';?>message" />
        </div>
    </form>
</div>

<?php
show_footer();
