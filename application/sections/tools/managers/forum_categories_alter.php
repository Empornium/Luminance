<?php
authorize();

if (!check_perms('admin_manage_forums')) { error(403); }
$P = db_array($_POST);
if ($_POST['submit'] == 'Delete') { //Delete
    if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
    $DB->query('DELETE FROM forums_categories WHERE ID='.$_POST['id']);
} else { //Edit & Create, Shared Validation
    $Val->SetFields('name', '1','string','The name must be set, and has a max length of 40 characters', array('maxlength'=>40, 'minlength'=>1));
    $Val->SetFields('sort', '1','number','Sort must be set');
    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
        $DB->query("UPDATE forums_categories SET
            Sort='$P[sort]',
            Name='$P[name]'
            WHERE ID='$P[id]'");
    } else { //Create
        $DB->query("INSERT INTO forums_categories
            (Name, Sort) VALUES('$P[name]', '$P[sort]')");
    }
}

$Cache->delete('forums_categories'); // Clear cache

// Go back
header('Location: tools.php?action=forum');
