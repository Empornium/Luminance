<?php
if (isset($activeUser['ID'])) {
    if (!isset($_REQUEST['action'])) {
        include 'private.php';
    } else {
        switch ($_REQUEST['action']) {
            case 'poll':
                include(SERVER_ROOT.'/Legacy/sections/forums/poll_vote.php');
                break;
            default:
                error(0);
        }
    }
}
