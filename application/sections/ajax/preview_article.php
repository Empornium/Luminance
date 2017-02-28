<?php
/* AJAX Previews, simple stuff. */

include(SERVER_ROOT.'/sections/articles/functions.php');
include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

$Title = $_REQUEST['title'];
$Body = $_REQUEST['body'];

$Body = $Text->full_format($Body, get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']), true);
$Body = replace_special_tags($Body);

echo '<div class="head">
                <strong>'. display_str($Title).' </strong> - posted Just now
        </div>
        <div class="box vertical_space">
        <div class="pad">'.$Body.'</div>
      </div>
      <br />';
