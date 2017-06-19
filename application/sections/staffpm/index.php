<?php
enforce_login();

if (!isset($_REQUEST['action'])) $_REQUEST['action'] = '';

// get vars from LoggedUser
$SupportFor = $LoggedUser['SupportFor'];
$DisplayStaff = $LoggedUser['DisplayStaff'];
// Logged in user is staff
$IsStaff = ($DisplayStaff == 1);
// Logged in user is Staff or FLS
$IsFLS = ($SupportFor != '' || $IsStaff);

switch ($_REQUEST['action']) {

    case 'takenewpost': // == start a staff conversation
        authorize();
        if (!$IsStaff) error(403);

        if (empty($_POST['toid']) || !is_number($_POST['toid'])) {
            $DB->query("SELECT ID FROM users_main WHERE Username='".db_string($_POST['user'])."'");
            list($ToID) = $DB->next_record();
        } else $ToID = $_POST['toid'];

        $ConvID = startStaffConversation($ToID, $_POST['subject'], $_POST['message']);

        header("Location: staffpm.php?action=viewconv&id=$ConvID");

        break;

    case 'compose':
        if (!$IsStaff) error(403);
        require 'compose.php';
        break;
    case 'viewconv':
        require 'viewconv.php';
        break;
    case 'takepost':
        require 'takepost.php';
        break;
    case 'resolve':
        require 'resolve.php';
        break;
    case 'unresolve':
        require 'unresolve.php';
        break;
    case 'stealthresolve':
        require 'stealthresolve.php';
        break;
    case 'stealthunresolve':
        require 'stealthunresolve.php';
        break;
    case 'multiresolve':
        require 'multiresolve.php';
        break;
    case 'assign':
        require 'assign.php';
        break;
    case 'assign_urgency':
        require 'assign_urgency.php';
        break;
    case 'make_donor':
        require 'makedonor.php';
        break;
    case 'responses':
        require 'common_responses.php';
        break;
    case 'get_response':
        require 'ajax_get_response.php';
        break;
    case 'ajax_get_edit':
        require(SERVER_ROOT.'/common/ajax_get_edit.php');
        break;
    case 'get_post':
        require(SERVER_ROOT.'/common/get_post.php');
        break;
    case 'delete_response':
        require 'ajax_delete_response.php';
        break;
    case 'edit_response':
        require 'ajax_edit_response.php';
        break;
    case 'preview':
        require 'ajax_preview_response.php';
        break;
    case 'mark_read':
        require 'markread.php';
        break;
    case 'mark_unread':
        require 'markunread.php';
        break;
    case 'takeedit':
        require 'takeedit.php';
        break;

    case 'stats':
        require 'stats.php';
        break;

    case 'user_inbox': // so staff can access the user interface too
        require 'user_inbox.php';
        break;
    case 'staff_inbox': //
    default:
        if ($IsStaff || $IsFLS) {
            require 'staff_inbox.php';
        } else {
            require 'user_inbox.php';
        }
        break;
}
