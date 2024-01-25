<?php
/*
 * This is the AJAX backend for the SendNow() function.
 */

authorize();

if (!check_perms('admin_reports')) {
    echo 'HAX on premissions!';
    die();
}

$Recipient = $_POST['pm_type'];
$torrentID = $_POST['torrentid'];

if (isset($_POST['uploader_pm']) && $_POST['uploader_pm'] != "") {
    $Message = $_POST['uploader_pm'];
} else {
    //No message given
    die();
}

if (array_key_exists($_POST['type'], $types)) {
    $ReportType = $types[$_POST['type']];
} else {
    //There was a type but it wasn't an option!
    echo 'HAX on section type';
    die();
}

if (!isset($_POST['from_delete'])) {
    $Report = true;
} elseif (!is_integer_string($_POST['from_delete'])) {
    echo 'Hax occured in from_delete';
}

if ($Recipient == 'Uploader') {
    $ToID = $_POST['uploaderid'];
    if ($Report) {
        $Message = "You uploaded [url=/torrents.php?id={$torrentID}]the above torrent[/url], it has been reported for the reason: {$ReportType['title']}\n\n{$Message}";
    } else {
        $Message = "I am PMing you as you are the uploader of [url=/torrents.php?id={$torrentID}]the above torrent[/url].\n\n{$Message}";
    }
} elseif ($Recipient == 'Reporter') {
    $ToID = $_POST['reporterid'];
    $Message = "You reported the above torrent for the reason ".$ReportType['title'].":\n\"".$_POST['report_reason']."\"\n\n".$Message;
} else {
    $Err = "Something went horribly wrong";
}

$Subject = $_POST['raw_name'];

if (!is_integer_string($ToID)) {
    $Err = "Haxx occuring, non number present";
}

if ($ToID == $activeUser['ID']) {
    $Err = "That's you!";
}

if (isset($Err)) {
    echo $Err;
} else {
    send_pm($ToID, $activeUser['ID'], $Subject, $Message);
}
