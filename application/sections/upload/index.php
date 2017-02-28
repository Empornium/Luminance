<?php
enforce_login();
if (!check_perms('site_upload')) { error(403); }
if ($LoggedUser['DisableUpload']) {
    error('Your upload privileges have been revoked.');
}

include(SERVER_ROOT . '/sections/upload/functions.php');

if (!empty($_POST['submit'])) {
    include(SERVER_ROOT.'/sections/upload/upload_handle.php');

} else {

    switch ($_GET['action']) {
          case 'add_template': // ajax call
                include(SERVER_ROOT.'/sections/upload/add_template.php');
                break;
          case 'delete_template': // ajax call
                include(SERVER_ROOT.'/sections/upload/delete_template.php');
                break;

        default:
                include(SERVER_ROOT.'/sections/upload/upload.php');
    }
}
