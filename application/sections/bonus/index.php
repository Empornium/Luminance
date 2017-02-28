<?php
include(SERVER_ROOT.'/sections/bonus/functions.php');

if (!isset($_REQUEST['action'])) {
    include(SERVER_ROOT.'/sections/bonus/bonus.php');
} else {
    switch ($_REQUEST['action']) {
        case 'slot_result':
            include(SERVER_ROOT.'/sections/bonus/ajax_slot_result.php');
            break;
        case 'ajax_slot_paytable':
            include(SERVER_ROOT.'/sections/bonus/slot_xxx_arrays.php');
            $Bet=(int) $_REQUEST['bet'];
            print_payout_table($Bet);
            break;
        case 'slot':
            include(SERVER_ROOT.'/sections/bonus/slotmachine_xxx.php');
            break;
        case 'awards':
            include(SERVER_ROOT.'/sections/bonus/awards.php');
            break;
        case 'buy':
            include(SERVER_ROOT.'/sections/bonus/takebonus.php');
            break;
        case 'msg':
            include(SERVER_ROOT.'/sections/bonus/result.php');
            break;
        case 'gift':
            include(SERVER_ROOT.'/sections/bonus/gift.php');
            break;
        case 'givegift':
            include(SERVER_ROOT.'/sections/bonus/takegift.php');
            break;
        case 'takecompose_giftpm':
            include(SERVER_ROOT.'/sections/bonus/takegiftpm.php');
            break;
        default:
            include(SERVER_ROOT.'/sections/bonus/bonus.php');
            break;
    }
}
