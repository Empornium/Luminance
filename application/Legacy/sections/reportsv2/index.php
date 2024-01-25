<?php

/*
 * This is the index page, it is pretty much reponsible only for the switch statement.
 */

use Luminance\Entities\User;

enforce_login();

if ($master->repos->restrictions->isRestricted($activeUser['ID'], \Luminance\Entities\Restriction::REPORT)) {
    error('Your report rights have been disabled.');
}

$types = (new class { use \Luminance\Legacy\sections\reportsv2\types; })::getTypes();

if (isset($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {

        case 'report':
            include 'report.php';
            break;
        case 'takereport':
            include 'takereport.php';
            break;
        case 'takeresolve':
            include 'takeresolve.php';
            break;
        case 'take_pm':
            include 'take_pm.php';
            break;
        case 'search':
            include 'search.php';
            break;
        case 'new':
            include(SERVER_ROOT.'/Legacy/sections/reportsv2/reports.php');
            break;
        case 'ajax_new_report':
            include 'ajax_new_report.php';
            break;
        case 'ajax_report':
            include 'ajax_report.php';
            break;
        case 'ajax_change_resolve':
            include 'ajax_change_resolve.php';
            break;
        case 'ajax_taste':
            include 'ajax_taste.php';
            break;
        case 'ajax_take_pm':
            include 'ajax_take_pm.php';
            break;
        case 'ajax_grab_report':
            include 'ajax_grab_report.php';
            break;
        case 'ajax_update_comment':
            require 'ajax_update_comment.php';
            break;
        case 'ajax_update_resolve':
            require 'ajax_update_resolve.php';
            break;
        case 'ajax_create_report':
            require 'ajax_create_report.php';
            break;
    }
} else {
    if (($_POST['sendmessage'] ?? '') == 'Send message to selected user') {
        authorize();

            if (empty($_POST['reportid']) || !is_integer_string($_POST['reportid'])) error(0);

            $ReportID = (int) $_POST['reportid'];
            $user = $master->repos->users->load((int) ($_POST['toid'] ?? 0));
            if (!($user instanceof User)) {
                error('Unknown user');
            }
            $ConvID = startStaffConversation($user->ID, $_POST['subject'], $_POST['message']);

            $Comment = sqltime()." - {$activeUser['Username']} - [url=/staffpm.php?action=viewconv&id=$ConvID]Sent Message to {$user->Username}[/url]";
            $master->db->rawQuery(
                "UPDATE reportsv2
                    SET LogMessage = CONCAT_WS(CHAR(10 using utf8), LogMessage, ?)
                  WHERE ID = ?",
                [$Comment, $ReportID]
            );
            $master->db->rawQuery(
                "INSERT INTO reportsv2_conversations (`ReportID` , `ConvID`)
                      VALUES (?, ?)",
                [$ReportID, $ConvID]
            );

            header("Location: reportsv2.php?view=report&id=$ReportID");

    } elseif (isset($_GET['view'])) {
        include(SERVER_ROOT.'/Legacy/sections/reportsv2/static.php');
    } else {
        include(SERVER_ROOT.'/Legacy/sections/reportsv2/views.php');
    }
}
