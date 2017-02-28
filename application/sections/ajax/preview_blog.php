<?php
/* AJAX Previews, simple stuff. */

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

$Title = $_REQUEST['title'];
$Body = $_REQUEST['body'];
$Author = $_REQUEST['author'];
echo '<div class="head">
                <strong>'. display_str($Title).' </strong> - posted Just now by '.$Author.'
                - <a href="#quickreplypreview">[Edit]</a>
                <a href="#quickreplypreview">[Delete]</a>
        </div>
        <div class="box vertical_space">
        <div class="pad">'.$Text->full_format($Body, get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']), true).'</div>
    </div>
      <br />';
