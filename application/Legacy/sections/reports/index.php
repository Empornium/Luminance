<?php
enforce_login();

if ($master->repos->restrictions->is_restricted($LoggedUser['ID'], \Luminance\Entities\Restriction::REPORT)) {
    error('Your report rights have been disabled.');
}

if (empty($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

// get vars from LoggedUser
$SupportFor = $LoggedUser['SupportFor'];
$DisplayStaff = $LoggedUser['DisplayStaff'];
// Logged in user is staff
$IsStaff = ($DisplayStaff == 1);
// Logged in user is Staff or FLS
$IsFLS = ($SupportFor != '' || $IsStaff);

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

        if(empty($_POST['reportid']) && !is_number($_POST['reportid'])) error(0);
        $ReportID = (int) $_POST['reportid'];

        if (isset($_POST['comment'])) $Comment = trim($_POST['comment']);
        if(!$Comment || $Comment == '') error("Cannot add a blank comment!");
        $Comment=db_string(sqltime()." - {$LoggedUser['Username']} - $Comment"  );

        $DB->query("UPDATE reports SET Comment=CONCAT_WS( '\n', Comment, '$Comment') WHERE ID='$ReportID'");

        header("Location: reports.php#report.$ReportID");

        break;

    case 'takepost': // == start a staff conversation
        authorize();
        if (!$IsStaff) error(403);

        if(empty($_POST['reportid']) || !is_number($_POST['reportid'])) error(0);
        $ReportID = (int) $_POST['reportid'];

        $ConvID = startStaffConversation($_POST['toid'], $_POST['subject'], $_POST['message']);

        $Comment=db_string(sqltime()." - {$LoggedUser['Username']} - [url=/staffpm.php?action=viewconv&id=$ConvID]Sent Message to {$_POST['username']}[/url]");
        $DB->query("UPDATE reports SET Comment=CONCAT_WS( '\n', Comment, '$Comment') WHERE ID='$ReportID'");
        $DB->query("INSERT INTO reports_conversations ( `ReportID` , `ConvID` )
                         VALUES ('$ReportID', '$ConvID') ");

        header("Location: reports.php#report$ReportID");

        break;
    case 'stats':
        include(SERVER_ROOT.'/Legacy/sections/reports/stats.php');
        break;
    default:
        include(SERVER_ROOT.'/Legacy/sections/reports/reports.php');
        break;
}
