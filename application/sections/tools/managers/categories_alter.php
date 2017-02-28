<?php

if (!check_perms('admin_manage_categories')) { error(403); }

authorize();

if ($_POST['submit'] == 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
    $DB->query('DELETE FROM categories WHERE ID='.$_POST['id']);
} else {
    $Val->SetFields('name', '1','string','The name must be set, and has a max length of 30 characters', array('maxlength'=>30, 'minlength'=>1));
    $Val->SetFields('tag', '1','string','The tag must be set, and has a max length of 255 characters', array('maxlength'=>255, 'minlength'=>1));
        $Val->SetFields('image', '1','string','The image must be set.', array('minlength'=>1));
    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    $P=array();
    $P=db_array($_POST); // Sanitize the form

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
        $DB->query("UPDATE categories SET
            name='$P[name]',
            image='$P[image]',
            tag='$P[tag]'
            WHERE id='$P[id]'");
    } else { //Create
        $DB->query("INSERT INTO categories
            (name, image, tag) VALUES
            ('$P[name]','$P[image]', '$P[tag]')");
    }

}

$Cache->delete('new_categories');

// Go back
header('Location: tools.php?action=categories');
