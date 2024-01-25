<?php
enforce_login();

// Get user level
$userLevel = $master->db->rawQuery(
    "SELECT i.SupportFor,
            p.DisplayStaff
       FROM users_info as i
       JOIN users_main as m ON m.ID = i.UserID
       JOIN permissions as p ON p.ID = m.PermissionID
      WHERE i.UserID = ?",
      [$activeUser['ID']]
)->fetch(\PDO::FETCH_ASSOC);

if (!($userLevel['SupportFor'] != '' || $userLevel['DisplayStaff'] == '1')) {
    // Logged in user is not FLS or Staff
    error(403);
}

if ($ID = (int) $_POST['id']) {
    $master->db->rawQuery(
        "DELETE
           FROM staff_pm_responses
          WHERE ID = ?",
        [$ID]
    );
    echo '1';

} else {
    // No id
    echo '-1';
}
