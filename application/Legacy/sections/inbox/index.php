<?php
enforce_login();

include_once(SERVER_ROOT.'/Legacy/sections/inbox/functions.php');

if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }
switch ($_REQUEST['action']) {
    case 'takecompose':
        require 'takecompose.php';
        break;
    case 'takeedit':
        require 'takeedit.php';
        break;
    case 'compose':
    case 'forward':
        require 'compose.php';
        break;
    case 'viewconv':
        require 'conversation.php';
        break;
    case 'masschange':
        require 'massdelete_handle.php';
        break;
    case 'get_post':
        require(SERVER_ROOT.'/common/get_post.php');
        break;
    default:
        require(SERVER_ROOT.'/Legacy/sections/inbox/inbox.php');
}
