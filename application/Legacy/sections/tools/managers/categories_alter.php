<?php

if (!check_perms('admin_manage_categories')) { error(403); }

authorize();

if ($_POST['submit'] == 'Delete') {
    if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
    $master->db->rawQuery('DELETE FROM categories WHERE ID = ?', [$_POST['id']]);
} else {
    $Val->SetFields('name', '1', 'string', 'The name must be set, and has a max length of 30 characters', ['maxlength'=>30, 'minlength'=>1]);
    $Val->SetFields('tag', '1', 'string', 'The tag must be set, and has a max length of 255 characters', ['maxlength'=>255, 'minlength'=>1]);
    $Val->SetFields('image', '1', 'string', 'The image must be set.', ['minlength'=>1]);
    $Val->SetFields('open', '1', 'inarray', 'The open field has invalid input.', ['inarray'=>[0, 1]]);
    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
        $master->db->rawQuery(
            "UPDATE categories
                SET name = ?,
                    image = ?,
                    tag = ?,
                    open = ?
              WHERE id = ?",
            [$_POST['name'], $_POST['image'], $_POST['tag'], $_POST['open'], $_POST['id']]
        );
    } else { //Create
        $master->db->rawQuery(
            "INSERT INTO categories (name, image, tag, open)
                  VALUES (?, ?, ?, ?)",
            [$_POST['name'], $_POST['image'], $_POST['tag'], $_POST['open']]
        );
    }
}

$master->cache->deleteValue('new_categories');
$master->cache->deleteValue('open_categories');

// Go back
header('Location: tools.php?action=categories');
