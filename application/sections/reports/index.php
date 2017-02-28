<?php
enforce_login();

if (empty($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

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

            if(empty($_POST['reportid']) || !is_number($_POST['reportid'])) error(0);
            if(empty($_POST['message']) || $_POST['message']=='') error("No message!");
            if(empty($_POST['subject']) || $_POST['subject']=='') error("No Subject!");
            if(empty($_POST['toid']) || !is_number($_POST['toid'])) error(0);

            $ReportID = (int) $_POST['reportid'];

            $Message = db_string($_POST['message']);
            $Subject = db_string($_POST['subject']);
            $ToID = db_string($_POST['toid']);

            $DB->query("INSERT INTO staff_pm_conversations
                     (Subject, Status, Level, UserID, Date, Unread)
                VALUES ('$Subject', 'Open', '0', '$ToID', '".sqltime()."', true)");
            // New message
            $ConvID = $DB->inserted_id();
            $DB->query("INSERT INTO staff_pm_messages
                     (UserID, SentDate, Message, ConvID)
                VALUES ('{$LoggedUser['ID']}', '".sqltime()."', '$Message', $ConvID)");

            $Comment=db_string(sqltime()." - {$LoggedUser['Username']} - [url=/staffpm.php?action=viewconv&id=$ConvID]Sent Message to {$_POST['username']}[/url]");
            $DB->query("UPDATE reports SET Comment=CONCAT_WS( '\n', Comment, '$Comment') WHERE ID='$ReportID'");
            $DB->query("INSERT INTO reports_conversations ( `ReportID` , `ConvID` )
                             VALUES ('$ReportID', '$ConvID') ");

            $Cache->delete_value('staff_pm_new_'.$ToID);
            header("Location: reports.php#report$ReportID");

        break;
    case 'stats':
        include(SERVER_ROOT.'/sections/reports/stats.php');
        break;
    default:
        include(SERVER_ROOT.'/sections/reports/reports.php');
        break;
}
