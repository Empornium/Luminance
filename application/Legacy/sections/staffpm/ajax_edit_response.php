<?php
enforce_login();
// Get user level

$IsAjax = isset($_POST['submit']) && $_POST['submit'] == 'Save'? FALSE : TRUE;

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
      if (!$IsAjax) error(403);
      else {
          echo "You do not have permission to view this page";
          die();
      }
}

$Message = isset($_POST['message'])? $_POST['message']:false;
$Name = isset($_POST['name'])? trim($_POST['name']):false;

if ($Message && $Name && (trim($Message) != "") && ($Name != "")) {

      $bbCode = new \Luminance\Legacy\Text;
      if (!$bbCode->validate_bbcode($Message,  get_permissions_advtags($activeUser['ID']), !$IsAjax)) {
        echo "There are errors in your bbcode (unclosed tags)";
        die();
      }

    $ID = (int) $_POST['id'];
    if (is_integer_string($ID)) {
        if ($ID == 0) {
            // Create new response
            $master->db->rawQuery(
                "INSERT INTO staff_pm_responses (Message, Name)
                      VALUES (?, ?)",
                [$Message, $Name]
            );
      // if submit is set then this is not an ajax response - reload page and pass vars for message & return convid
      if (!$IsAjax) {
          $InsertedID = $master->db->lastInsertID();
          $ConvID = (int) $_POST['convid'];
          header("Location: staffpm.php?action=responses&added=$InsertedID".($ConvID>0?"&convid=$ConvID":'')."#old_responses");
      } else
          echo 1;
        } else {
            $foundRows = $master->db->rawQuery(
                "SELECT COUNT(*)
                   FROM staff_pm_responses
                  WHERE ID = ?",
                [$ID]
            )->fetchColumn();
            if (!($foundRows === 0)) {
                // Edit response
                $master->db->rawQuery(
                    "UPDATE staff_pm_responses
                        SET Message = ?,
                            Name = ?
                      WHERE ID = ?",
                    [$Message, $Name, $ID]
                );
                echo '2';
            } else {
                // Create new response
                $master->db->rawQuery(
                    "INSERT INTO staff_pm_responses (Message, Name)
                          VALUES (?, ?)",
                    [$Message, $Name]
                );
                // if submit is set then this is not an ajax response - reload page and pass vars for message & return convid
        if (!$IsAjax) {
              $InsertedID = $master->db->lastInsertID();
              $ConvID = (int) $_POST['convid'];
              header("Location: staffpm.php?action=responses&added=$InsertedID".($ConvID>0?"&convid=$ConvID":'')."#old_responses");
        } else
              echo 1;
            }
        }
    } else {
        // No id
        if (!$IsAjax) {
                  $ConvID = (int) $_POST['convid'];
                  header("Location: staffpm.php?action=responses&added=-2".($ConvID>0?"&convid=$ConvID":''));
            } else
                  echo -2;
    }

} else {
    // No message/name
    if (!$IsAjax) {
            $ConvID = (int) $_POST['convid'];
            header("Location: staffpm.php?action=responses&added=-1".($ConvID>0?"&convid=$ConvID":''));
      } else
            echo -1;
}
