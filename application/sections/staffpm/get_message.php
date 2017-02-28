<?php
//TODO: make this use the cache version of the thread, save the db query
/*********************************************************************\
//--------------Get Message--------------------------------------------//

This gets the raw BBCode of a StaffPM. It's used for editing and
quoting messages.

It gets called if $_GET['action'] == 'get_message'. It requires
$_GET['message'], which is the ID of the message.

\*********************************************************************/

// Quick SQL injection check
if (!$_GET['message'] || !is_number($_GET['message'])) {
    error(0);
}

// Variables for database input
$MessageID = (int) $_GET['message'];

// Mainly
$DB->query("SELECT Message FROM staff_pm_messages WHERE ID='$MessageID'");
list($Message) = $DB->next_record(MYSQLI_NUM);

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;
$Message = $Text->clean_bbcode($Message, get_permissions_advtags($LoggedUser['ID']));

// This gets sent to the browser, which echoes it wherever

if (isset($_REQUEST['body']) && $_REQUEST['body']==1) {
    echo trim($Message);
} else {
    $Text->display_bbcode_assistant("editbox$MessageID", get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']));
?>
    <textarea id="editbox<?=$MessageID?>" class="long" onkeyup="resize('editbox<?=$MessageID?>');" name="body" rows="10"><?=display_str($Message)?></textarea>
<?php
}
