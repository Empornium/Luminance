<?php
if (!check_perms('admin_manage_languages')) { error(403); }

authorize();

if ($_POST['submit'] == 'Delete') {

    if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
    $DB->query('DELETE FROM languages WHERE ID='.$_POST['id']);

} else {

    $Val->SetFields('language', '1','string','The name must be set, and has a max length of 64 characters', array('maxlength'=>64, 'minlength'=>1));
    $Val->SetFields('code', '1','string','The tag must be set, and must be 2 characters', array('maxlength'=>2, 'minlength'=>2));
    $Val->SetFields('flag_cc', '0','string','If set the flag code must be 2 characters long.', array('maxlength'=>2, 'minlength'=>2));

    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    $P=array();
    $P=db_array($_POST); // Sanitize the form

    $DB->query("SELECT ID, language FROM languages WHERE code='$P[code]' AND ID != '$P[id]'");
    if ($DB->record_count()>0) error("The language code '$P[code]' is already in use! (please use iso codes, which must be unique)");

    if ($P['active'] !== '1') $P['active'] = '0';

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
        $DB->query("UPDATE languages SET
                           language='$P[language]',
                           code='$P[code]',
                           flag_cc='$P[flag]',
                           active='$P[active]'
                     WHERE id='$P[id]'");
    } else { //Create
        $DB->query("INSERT INTO languages (language, code, flag_cc, active) VALUES
                                    ('$P[language]','$P[code]', '$P[flag]', '$P[active]')");
    }

}

$Cache->delete('site_languages');

// Go back
header('Location: tools.php?action=languages');
