<?php
if (!check_perms('admin_dnu')) { error(403); }

authorize();

if ($_POST['submit'] == 'Delete') { //Delete
    if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
    $master->db->rawQuery('DELETE FROM do_not_upload WHERE ID = ?', [$_POST['id']]);
} else { //Edit & Create, Shared Validation
    $Val->SetFields('name', '1', 'string', 'The name must be set, and has a max length of 40 characters', ['maxlength'=>40, 'minlength'=>1]);
    $Val->SetFields('comment', '0', 'string', 'The description has a max length of 255 characters', ['maxlength'=>255]);
    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
        $master->db->rawQuery(
            "UPDATE do_not_upload
                SET Name = ?,
                    Comment = ?,
                    UserID = ?,
                    Time = ?
              WHERE ID = ?",
            [$_POST['name'], $_POST['comment'], $activeUser['ID'], sqltime(), $_POST['id']]
    );
    } else { //Create
        $master->db->rawQuery(
            "INSERT INTO do_not_upload (Name, Comment, UserID, Time)
                  VALUES (?, ?, ?, ?)",
            [$_POST['name'], $_POST['comment'], $activeUser['ID'], sqltime()]
        );
    }
}
$master->cache->deleteValue('do_not_upload_list');

// Go back
header('Location: tools.php?action=dnu');
