<?php
/* AJAX Previews, simple stuff. */

$bbCode = new \Luminance\Legacy\Text;

$Title = $_REQUEST['title'];
$Body = $_REQUEST['body'];

$Body = $bbCode->full_format($Body, get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions']), true);
$Body = $master->getPlugin('Articles')->replaceSpecialTags($Body);

echo '<div class="head">
                <strong>'. display_str($Title).' </strong> - posted Just now
        </div>
        <div class="box vertical_space">
        <div class="pad">'.$Body.'</div>
      </div>
      <br />';
