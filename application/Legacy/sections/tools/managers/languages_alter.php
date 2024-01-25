<?php
if (!check_perms('admin_manage_languages')) { error(403); }

authorize();

if ($_POST['submit'] == 'Delete') {
    if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
    $master->db->rawQuery(
        'DELETE FROM languages
               WHERE ID = ?',
        [$_POST['id']]
    );
} else {

    $Val->SetFields('language', '1', 'string', 'The name must be set, and has a max length of 64 characters', ['maxlength'=>64, 'minlength'=>1]);
    $Val->SetFields('code', '1', 'string', 'The tag must be set, and must be 2 characters', ['maxlength'=>2, 'minlength'=>2]);
    $Val->SetFields('flag_cc', '0', 'string', 'If set the flag code must be 2 characters long.', ['maxlength'=>2, 'minlength'=>2]);

    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    $P=[];
    $P=$_POST; // Sanitize the form

    $master->db->rawQuery(
        "SELECT ID, language
           FROM languages
          WHERE code = ? AND ID != ?",
        [$_POST['code'], $_POST['id']]
    );
    if ($master->db->foundRows() > 0) error("The language code '{$P['code']}' is already in use! (please use iso codes, which must be unique)");

    if ($P['active'] !== '1') $P['active'] = '0';

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
        $master->db->rawQuery(
            "UPDATE languages
                SET language = ?,
                    code = ?,
                    flag_cc = ?,
                    active = ?
              WHERE id = ?",
            [$_POST['language'], $_POST['code'], $_POST['flag'], $_POST['active'], $_POST['id']]
        );
    } else { //Create
        $master->db->rawQuery(
            "INSERT INTO languages (language, code, flag_cc, active)
                  VALUES (?, ?, ?, ?)",
            [$_POST['language'], $_POST['code'], $_POST['flag'], $_POST['active']]
        );
    }

}

$master->cache->deleteValue('site_languages');

// Go back
header('Location: tools.php?action=languages');
