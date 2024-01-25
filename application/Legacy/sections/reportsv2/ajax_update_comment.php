<?php
// perform the back end of updating a report comment

authorize();

if (!check_perms('admin_reports')) {
    error(403, true);
}

if (empty($_POST['reportid']) || !is_integer_string($_POST['reportid'])) {
    //echo 'HAX ATTEMPT!'.$_GET['reportid'];
    //die();
    error(0, true);
}

$ReportID = (int)$_POST['reportid'];

$Message = $_POST['comment'];
//Message can be blank!

$ModComment = $master->db->rawQuery(
    "SELECT ModComment
       FROM reportsv2
      WHERE ID = ?",
    [$ReportID]
)->fetchColumn();
if (isset($ModComment)) {
    $master->db->rawQuery(
        "UPDATE reportsv2
            SET ModComment = ?
          WHERE ID = ?",
        [$Message, $ReportID]
    );
}
