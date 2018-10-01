<?php
$Text = new Luminance\Legacy\Text;

if ($master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::PM)) {
    error('Your PM rights have been disabled.');
}

if ($_REQUEST['action']=='forward') { // forwarding a msg

    $Header = "Forward";

    $ToUsername = $_POST['receivername'];
    $DB->query("SELECT ID, Username FROM users_main WHERE Username='".db_string($ToUsername)."'");
    if ($DB->record_count()==0) {
        error("Could not find user '".display_str($ToUsername)."' to forward to");
    }
    // grab username from db to get correct capitalisation
    list($ToID, $ToUsername) = $DB->next_record();

    list($MsgType, $Subject, $FwdBody) = getForwardedPostData();


} else { // composing a new msg
    $Header = "Send";
    $ToID = $_GET['to'];
    $MsgType = 'message';
}

if (!$ToID || !is_number($ToID)) { error(404); }

$StaffIDs = getStaffIDs();

if ($master->repos->restrictions->is_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::PM) && !isset($StaffIDs[$ToID])) {
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

if (blockedPM($ToID, $LoggedUser['ID'], $Err)) {
    if(!isset($StaffIDs[$ToID])) {
        error($Err);
    } else {
        $blocked = true;
    }
}

show_header('Compose', 'inbox,bbcode');

?>
<div class="thin">
    <h2><?=$Header?> a <?=$MsgType?> to <a href="/user.php?id=<?=$ToID?>"><?=$ToUsername?></a></h2>
<?php

    if (isset($StaffIDs[$ToID])) {
    ?>
        <div class="box pad shadow">
            You are sending a PM to a member of staff. IF this is regarding a staffing issue
            <strong class="important_text">please use the <a href="/staff.php?show=1&amp;assign=mod">staff message form</a> instead.</strong> (You can specify if you want it to only be seen by admins or mods+ if you need to)
            <br />This way it can be dealt with appropriately and quickly. Please note - PM's sent to staff that are about staffing or moderation issues may be responded to as a new staff message by any appropriate staff member.
        </div>
    <?php
    }
    if ($FwdBody) {
        ?>
        <div class="head">
            <?=$MsgType;?> to be forwarded:
        </div>
        <div class="box vertical_space">
            <div class="body" >
                <?=$Text->full_format($FwdBody, true)?>
            </div>
        </div>
        <?php
    }
    if (!$blocked) {
?>
    <div id="preview" class="hidden"></div>
    <form action="inbox.php" method="post" id="messageform">
        <div id="quickpost">
            <div class="head"><?=($FwdBody?'Add':'Compose')?> message</div>
            <div class="box pad">
                        <input type="hidden" name="action" value="takecompose" />
                        <input type="hidden" name="toid" value="<?=$ToID?>" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="forwardbody" value="<?=display_str($FwdBody)?>" />
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
<?php
    } else {
?>
        <div class="head"></div>
        <div class="box pad shadow"><?=$Err?></div>
<?php
    }
?>
</div>

<?php
show_footer();
