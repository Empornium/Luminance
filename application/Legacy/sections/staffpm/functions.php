<?php

function check_access($ConvID) {
    global $master, $activeUser;

    $IsStaff = check_perms('site_staff_inbox');

    // Check if conversation belongs to user
    list($TargetUserID, $Level, $AssignedToUser) = $master->db->rawQuery(
        "SELECT UserID,
                Level,
                AssignedToUser
           FROM staff_pm_conversations
          WHERE ID = ?",
        [$ConvID]
    )->fetch(\PDO::FETCH_NUM);

    if (!(($TargetUserID == $activeUser['ID']) || ($AssignedToUser == $activeUser['ID']) || (($Level > 0 && $Level <= $activeUser['Class']) || ($Level == 0 && $IsStaff)))) {
        // User is trying to view someone else's conversation
        error(403);
    }
}

function make_staffpm_note($Message, $ConvID) {
    global $master;
    $conv = $master->db->rawQuery(
        "SELECT ID,
                Message AS Notes
           FROM staff_pm_messages
          WHERE ConvID = ?
            AND IsNotes",
        [$ConvID]
    )->fetch(\PDO::FETCH_ASSOC);
    if (!empty($conv)) {
        $conv['Notes'] = $Message."[br]".$conv['Notes'];
        $master->db->rawQuery(
            "UPDATE staff_pm_messages
                SET Message = ?
              WHERE ID = ?
                AND IsNotes",
            [$conv['Notes'], $conv['ID']]
        );
    } else {
        $master->db->rawQuery(
            "INSERT INTO staff_pm_messages (UserID, SentDate, Message, ConvID, IsNotes)
                  VALUES (0, ?, ?, ?, TRUE)",
            [sqltime(), $Message, $ConvID]
        );
    }
}
function get_num_staff_pms($userID, $UserLevel) {
    global $master;
    $params = [$userID, $UserLevel];
    $NumUnanswered = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM staff_pm_conversations
          WHERE (AssignedToUser = ? OR Level <= ?)
            AND Status IN ('Unanswered', 'User Resolved')
            AND NOT StealthResolved",
        $params
    )->fetchColumn();

    $NumOpen = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM staff_pm_conversations
          WHERE (AssignedToUser = ? OR Level <= ?)
            AND Status IN ('Open', 'Unanswered', 'User Resolved')
            AND NOT StealthResolved",
        $params
    )->fetchColumn();
    $NumMy = $master->db->rawQuery(
        "SELECT COUNT(ID)
           FROM staff_pm_conversations
          WHERE (AssignedToUser = ? OR Level = ?)
            AND Status = 'Unanswered'
        AND NOT StealthResolved",
        $params
    )->fetchColumn();

    return [$NumMy, $NumUnanswered, $NumOpen];
}

function print_staff_assign_select($AssignedToUser, $Level) {
    global $master, $classLevels;
?>
        <select id="assign_to" name="assign">
            <optgroup label="User classes">
<?php       // FLS "class"
                $Selected = (!$AssignedToUser && $Level == 0) ? ' selected="selected"' : '';
?>
                <option value="class_0"<?=$Selected?>>First Line Support</option>
<?php       // Staff classes
foreach ($classLevels as $class) {
    // Create one <option> for each staff user class  >= 650
    if ($class['Level'] >= 500) {
        $Selected = (!$AssignedToUser && ($Level == $class['Level'])) ? ' selected="selected"' : '';
?>
                <option value="class_<?=$class['Level']?>"<?=$Selected?>><?=$class['Name']?></option>
<?php
    }
}
?>
            </optgroup>
            <optgroup label="Staff">
<?php       // Staff members
$permissions = $master->db->rawQuery(
    "SELECT u.ID,
            u.Username
       FROM permissions as p
       JOIN users_main AS um ON um.PermissionID = p.ID
       JOIN users AS u ON u.ID = um.ID
      WHERE p.DisplayStaff = '1'
   ORDER BY p.Level DESC, u.Username ASC"
)->fetchAll(\PDO::FETCH_OBJ);
foreach ($permissions as $permission) {
    // Create one <option> for each staff member
    $Selected = ($AssignedToUser == $permission->ID) ? ' selected="selected"' : '';
?>
                <option value="user_<?= $permission->ID ?>"<?= $Selected ?>><?= $permission->Username ?></option>
<?php
}
?>
            </optgroup>
            <optgroup label="First Line Support">
<?php
// FLS users
$flsUsers = $master->db->rawQuery(
    "SELECT u.ID,
            u.Username
       FROM users AS u
       JOIN users_main AS um ON um.ID = u.ID
       JOIN users_info AS ui ON ui.UserID = u.ID
       JOIN permissions as p ON p.ID = um.PermissionID
      WHERE p.DisplayStaff != '1' AND ui.SupportFor != ''
   ORDER BY u.Username ASC
")->fetchAll(\PDO::FETCH_OBJ);
foreach ($flsUsers as $flsUser) {
    // Create one <option> for each FLS user
    $Selected = ($AssignedToUser == $flsUser->ID) ? ' selected="selected"' : '';
?>
                <option value="user_<?= $flsUser->ID ?>"<?= $Selected ?>><?= $flsUser->Username ?></option>
<?php
}
?>
            </optgroup>
        </select>
        <input type="button"  style="margin-right: 10px;" onClick="Assign();" value="Assign" />
<?php
}
