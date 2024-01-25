<?php
enforce_login();

// Get user level
list($SupportFor, $DisplayStaff) = $master->db->rawQuery(
    "SELECT i.SupportFor,
            p.DisplayStaff
       FROM users_info as i
       JOIN users_main as m ON m.ID = i.UserID
       JOIN permissions as p ON p.ID = m.PermissionID
      WHERE i.UserID = ?",
    [$activeUser['ID']]
)->fetch(\PDO::FETCH_NUM);

if (!($SupportFor != '' || $DisplayStaff == '1')) {
    // Logged in user is not FLS or Staff
    error(403);
}

if ($ID = (int) $_GET['id']) {
    $Message = $master->db->rawQuery(
        "SELECT Message
           FROM staff_pm_responses
          WHERE ID = ?",
        [$ID]
    )->fetchColumn();
    if ($_GET['plain'] == 1) {
        echo $Message;
    } else {
        $bbCode = new \Luminance\Legacy\Text;
        echo $bbCode->full_format($Message, true);
    }

} else {
    // No id
    echo '-1';
}
