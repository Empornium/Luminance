<?php
enforce_login();

if ($master->repos->restrictions->isRestricted($activeUser['ID'], \Luminance\Entities\Restriction::REPORT)) {
    error('Your report rights have been disabled.');
}

if (empty($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

// Logged in user is Staff or FLS
$IsStaff = check_perms('site_staff_inbox');
$IsFLS = (check_perms('users_fls') || $IsStaff);

switch ($_REQUEST['action']) {
    case 'report':
        include 'report.php';
        break;
    case 'takereport':
        include 'takereport.php';
        break;
    case 'Resolve':
        include 'takeresolve.php';
        break;
    case 'Add comment':
        authorize();
        if (!$IsFLS) error(403);

        if (empty($_POST['reportid']) && !is_integer_string($_POST['reportid'])) error(0);
        $ReportID = (int) $_POST['reportid'];

        if (isset($_POST['comment'])) $Comment = trim($_POST['comment']);
        if (!$Comment || $Comment == '') error("Cannot add a blank comment!");
        $Comment = sqltime() . " - {$activeUser['Username']} - $Comment";

        $master->db->rawQuery(
            "UPDATE reports
                SET Comment = CONCAT_WS(CHAR(10 using utf8), Comment, ?)
              WHERE ID = ?",
            [$Comment, $ReportID]
        );

        header("Location: reports.php#report.$ReportID");

        break;

    case 'takepost': // == start a staff conversation
        authorize();
        if (!$IsStaff) error(403);

        if (empty($_POST['reportid']) || !is_integer_string($_POST['reportid'])) error(0);
        $ReportID = (int) $_POST['reportid'];

        $ConvID = startStaffConversation($_POST['toid'], $_POST['subject'], $_POST['message']);

        $Comment =sqltime()." - {$activeUser['Username']} - [url=/staffpm.php?action=viewconv&id={$ConvID}]Sent Message to {$_POST['username']}[/url]";
        $master->db->rawQuery(
            "UPDATE reports
                SET Comment=CONCAT_WS(CHAR(10 using utf8), Comment, ?)
              WHERE ID= ?",
            [$Comment, $ReportID]
        );
        $master->db->rawQuery(
            "INSERT INTO reports_conversations (`ReportID`, `ConvID`)
                  VALUES (?, ?) ",
             [$ReportID, $ConvID]
        );

        header("Location: reports.php#report$ReportID");

        break;
    case 'stats':
        include(SERVER_ROOT.'/Legacy/sections/reports/stats.php');
        break;
    default:
        include(SERVER_ROOT.'/Legacy/sections/reports/reports.php');
        break;
}
