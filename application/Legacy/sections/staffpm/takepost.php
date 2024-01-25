<?php

include_once(SERVER_ROOT.'/Legacy/sections/staffpm/functions.php');

if ($Message = ($_POST['message'] ?? null)) {

    $bbCode = new \Luminance\Legacy\Text;
    $bbCode->validate_bbcode($_POST['message'],  get_permissions_advtags($activeUser['ID']));

    if (($_POST['note'] ?? false) && $IsStaff) {

        // make a staff note
        $ConvID = (int) $_POST['convid'];

        // Is the user allowed to access this StaffPM
        check_access($ConvID);

        $Message = "[b]".sqltime()." - Notes added by ".$activeUser['Username'].":[/b] ".$Message;
        make_staffpm_note($Message, $ConvID);

        header("Location: staffpm.php?action=viewconv&id=$ConvID");

    } else if (empty($_POST['subject']) === false) {

        $conv = $master->db->rawQuery(
            "SELECT ID
               FROM staff_pm_conversations
              WHERE UserID = ?
                AND Subject = ?
           ORDER BY ID DESC
              LIMIT 1",
            [$activeUser['ID'], $_POST['subject']]
        )->fetch();

        // Append to existing $StaffPMs
        if (empty($conv['ID'])) {
            // New staff pm conversation
            $master->db->rawQuery(
                "INSERT INTO staff_pm_conversations (Subject, Status, Level, UserID, Date)
                      VALUES (?, 'Unanswered', ?, ?, ?)",
                [$_POST['subject'], $_POST['level'], $activeUser['ID'], sqltime()]
            );
            $conv['ID'] = $master->db->lastInsertID();
        } else {
            if (empty($conv['Urgent']) || $conv['Urgent'] === 'Respond') {
                $conv['Urgent'] = 'No';
            }
            $master->db->rawQuery(
                "UPDATE staff_pm_conversations
                    SET Date = ?,
                        Unread = false,
                        Status = 'Unanswered',
                        Urgent = ?
                  WHERE ID = ?",
                [sqltime(), $conv['Urgent'], $conv['ID']]
            );

        }

        if (isset($_POST['forwardbody'])) {
            $Message = $_POST['forwardbody'] .$Message;
        }
        // New message
        $master->db->rawQuery(
            "INSERT INTO staff_pm_messages (UserID, SentDate, Message, ConvID, IsNotes)
                  VALUES (?, ?, ?, ?, FALSE)",
            [$activeUser['ID'], sqltime(), $Message, $conv['ID']]
        );

        header('Location: staffpm.php?action=user_inbox');

    } elseif ($ConvID = (int) $_POST['convid']) {

        // Is the user allowed to access this StaffPM
        check_access($ConvID);
        // Respond to existing conversation
        $conv = $master->db->rawQuery(
            "SELECT UserID,
                    Urgent
               FROM staff_pm_conversations
              WHERE ID = ?",
            [$ConvID]
        )->fetch();
        if (empty($conv['Urgent'])) $conv['Urgent'] = 'No';

        $LastMessageID = $master->db->rawQuery(
            "SELECT ID
               FROM staff_pm_messages
              WHERE ConvID = ?
           ORDER BY ID DESC
              LIMIT 1",
            [$ConvID]
        )->fetchColumn();
        if ($LastMessageID != $_POST['lastmessageid']) {
            error("There is a message you have not read, please go back and refresh the page. Your Ninja'd Text: " . $_POST['message']);
        }

        $master->db->rawQuery(
            "INSERT INTO staff_pm_messages (UserID, SentDate, Message, ConvID, IsNotes)
                  VALUES (?, ?, ?, ?, FALSE)",
            [$activeUser['ID'], sqltime(), $Message, $ConvID]
        );

        // Update conversation
        if ($conv['UserID'] != $activeUser['ID']) {
            // FLS/Staff
            $master->db->rawQuery(
                "UPDATE staff_pm_conversations
                    SET Date = ?,
                        Unread = true,
                        Status = 'Open'
                  WHERE ID = ?",
                [sqltime(), $ConvID]
            );
        } else {
            // User replied
            if ($conv['Urgent'] == 'Respond') {
                $conv['Urgent'] = 'No';
            }
            $master->db->rawQuery(
                "UPDATE staff_pm_conversations
                    SET Date = ?,
                        Unread = false,
                        Status = 'Unanswered',
                        Urgent = ?
                  WHERE ID = ?",
                [sqltime(), $conv['Urgent'], $ConvID]
            );
        }

        // Clear cache for user
        $master->cache->deleteValue('staff_pm_new_'.$conv['UserID']);
        $master->cache->deleteValue('staff_pm_urgent_'.$conv['UserID']);

        if ($_POST['resolve'] ?? false) {
              // Conversation belongs to user or user is staff, resolve it
              if ($conv['UserID'] == $activeUser['ID']) {
                  if ($conv['Urgent'] == 'Respond')  {
                      error("You cannot resolve this conversation until you respond to it.");
                  }
                  $Resolve = 'User Resolved';
              } else {
                  $Resolve = 'Resolved';
              }
              $master->db->rawQuery(
                  "UPDATE staff_pm_conversations
                      SET Date = ?,
                          Status = ?,
                          ResolverID = ?
                    WHERE ID = ?",
                  [sqltime(), $Resolve, $activeUser['ID'], $ConvID]
              );
              $master->cache->deleteValue('staff_pm_new_'.$activeUser['ID']);

              // Add a log message to the StaffPM
              $Message = sqltime()." - Resolved by ".$activeUser['Username'];
              make_staffpm_note($Message, $ConvID);
        }

        header("Location: staffpm.php?action=viewconv&id=$ConvID");

    } else {
        // Message but no subject or conversation id
        header("Location: staffpm.php?action=viewconv&id=$ConvID");
    }
} elseif ($ConvID = (int) $_POST['convid']) {
    if ($_POST['resolve'] ?? false) {
          // Is the user allowed to access this StaffPM
          check_access($ConvID);

          // Check if conversation belongs to user
          $conv = $master->db->rawQuery(
              "SELECT UserID,
                      Urgent
                 FROM staff_pm_conversations
                WHERE ID = ?",
              [$ConvID]
          )->fetch();
          if (empty($conv['Urgent'])) {
              $conv['Urgent'] = 'No';
          }

          // Conversation belongs to user or user is staff, resolve it
          if ($conv['UserID'] == $activeUser['ID']) {
              if ($conv['Urgent'] == 'Respond') {
                  error("You cannot resolve this conversation until you respond to it.");
              }
              $Resolve = 'User Resolved';
          } else {
              $Resolve = 'Resolved';
          }
          $master->db->rawQuery(
              "UPDATE staff_pm_conversations
                  SET Date = ?,
                      Status = ?,
                      ResolverID = ?
                WHERE ID = ?",
              [sqltime(), $Resolve, $activeUser['ID'], $ConvID]
          );
          $master->cache->deleteValue('staff_pm_new_'.$activeUser['ID']);

          // Add a log message to the StaffPM
          $Message = sqltime()." - Resolved by ".$activeUser['Username'];
          make_staffpm_note($Message, $ConvID);

          header('Location: staffpm.php?view=open');
    } else {
        // No message, but conversation id
        header("Location: staffpm.php?action=viewconv&id=$ConvID");
    }
} else {
    // No message or conversation id
    header('Location: staffpm.php?view=open');
}
