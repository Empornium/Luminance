<?php
/* AJAX Previews, simple stuff. */

$bbCode = new \Luminance\Legacy\Text;

if (!empty($_POST['AdminComment'])) {
    echo $bbCode->full_format($_POST['AdminComment'],true);
} else {
    $Content = $_REQUEST['message']; // Don't use URL decode.
      echo $bbCode->full_format($Content, get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions']), true);
}
