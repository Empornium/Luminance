<?php
/* AJAX Previews, simple stuff. */

$Text = new Luminance\Legacy\Text;

if (!empty($_POST['AdminComment'])) {
    echo $Text->full_format($_POST['AdminComment'],true);
} else {
    $Content = $_REQUEST['message']; // Don't use URL decode.
      echo $Text->full_format($Content, get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']), true);
}
