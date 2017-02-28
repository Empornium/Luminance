<?php
authorize();

if (!check_perms('admin_reports') && !check_perms('site_project_team') && !check_perms('site_moderate_forums')) {
    error(403);
}

if (empty($_POST['reportid']) && !is_number($_POST['reportid'])) {
    error(403);
}

$ReportID = (int) $_POST['reportid'];

$DB->query("SELECT Type, ConvID FROM reports WHERE ID = ".$ReportID);
list($Type,$ConvID) = $DB->next_record();
if (!check_perms('admin_reports')) {
    if (check_perms('site_moderate_forums')) {
        if (!in_array($Type, array('collages_comment', 'post', 'requests_comment', 'thread', 'torrents_comment'))) {
            error($Type);
        }
    } elseif (check_perms('site_project_team')) {
        if ($Type != "request_update") {
            error(403);
        }
    }
}

$Comment = sqltime()." - Resolved by {$LoggedUser['Username']}";
if (isset($_POST['comment'])) $Comment .= " - {$_POST['comment']}";
$Comment=db_string($Comment);

$DB->query("UPDATE reports
            SET Status='Resolved',
                ResolvedTime='".sqltime()."',
                ResolverID='{$LoggedUser['ID']}',
                Comment=CONCAT_WS( '\n', Comment, '$Comment')
            WHERE ID='".db_string($ReportID)."'");

if ($ConvID && $ConvID>0) {
    $DB->query("UPDATE staff_pm_conversations SET Status='Resolved', ResolverID=".$LoggedUser['ID']." WHERE ID=$ConvID");
    $Cache->delete_value('staff_pm_new_'.$LoggedUser['ID']);
    $Cache->delete_value('num_staff_pms_'.$LoggedUser['ID']);
}

$Channels = array();

if ($Type == "request_update") {
    $Channels[] = "#requestedits";
    $Cache->decrement('num_update_reports');
}

if (in_array($Type, array('collages_comment', 'post', 'requests_comment', 'thread', 'torrents_comment'))) {
    $Channels[] = "#forumreports";
    $Cache->decrement('num_forum_reports');
}

$DB->query("SELECT COUNT(ID) FROM reports WHERE Status = 'New'");
list($Remaining) = $DB->next_record();

foreach ($Channels as $Channel) {
    send_irc("PRIVMSG ".$Channel." :Report ".$ReportID." resolved by ".preg_replace("/^(.{2})/", "$1·", $LoggedUser['Username'])." on site (".(int) $Remaining." remaining).");
}

$Cache->delete_value('num_other_reports');

header('Location: reports.php');
