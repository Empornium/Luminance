<?php
enforce_login();
if (!check_perms('site_upload')) { error(403); }
$master->repos->restrictions->check_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::UPLOAD);

if (!$master->options->EnableUploads) {
    error('Uploads are currently disabled.');
}

include(SERVER_ROOT . '/Legacy/sections/upload/functions.php');

if (!empty($_POST['submit'])) {
    include(SERVER_ROOT.'/Legacy/sections/upload/upload_handle.php');

} else {

    switch ($_GET['action']) {
          case 'add_template': // ajax call
                include(SERVER_ROOT.'/Legacy/sections/upload/add_template.php');
                break;
          case 'delete_template': // ajax call
                include(SERVER_ROOT.'/Legacy/sections/upload/delete_template.php');
                break;

        default:
                include(SERVER_ROOT.'/Legacy/sections/upload/upload.php');
    }
}
