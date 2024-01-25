<?php
if (!check_perms('admin_email_blacklist')) { error(403); }

authorize();

if ($_POST['submit'] == 'Delete') { //Delete
    if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
    $master->db->rawQuery('DELETE FROM email_blacklist WHERE ID = ?', [$_POST['id']]);
} else { //Edit & Create, Shared Validation
    $Val->SetFields('email', '1', 'string', 'The email must be set', ['minlength'=>1]);
    $Val->SetFields('comment', '0', 'string', 'The description has a max length of 255 characters', ['maxlength'=>255]);
    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
        $master->db->rawQuery(
            "UPDATE email_blacklist
                SET Email = ?,
                    Comment = ?,
                    UserID = ?,
                    Time = ?
              WHERE ID = ?",
            [$_POST['email'], $_POST['comment'], $activeUser['ID'], sqltime(), $_POST['id']]
        );
    } else { //Create
        $master->db->rawQuery(
            "INSERT INTO email_blacklist (Email, Comment, UserID, Time)
                  VALUES (?, ?, ?, ?)",
            [$_POST['email'], $_POST['comment'], $activeUser['ID'], sqltime()]
        );
    }
}

$master->cache->deleteValue('emailblacklist_regex');

// Go back
header('Location: tools.php?action=email_blacklist');
