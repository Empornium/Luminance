<?php
$bbCode = new \Luminance\Legacy\Text;

$Subject = $_REQUEST['subject'];
if ( !empty($_REQUEST['prependtitle']))  $Subject = $_REQUEST['prependtitle'] . $Subject;
if ( !empty($_REQUEST['message'])) $Body = $_REQUEST['message'];
else $Body = $_REQUEST['body'];

echo'
              <h2>'. display_str($Subject).'</h2>
                    <div class="box">
                        <div class="head">
                               '. format_username($activeUser['ID'], $activeUser['Donor'], true, $activeUser['Enabled'], $activeUser['PermissionID'], $activeUser['Title'], true). '  Just now
                        </div>
                        <div class="body">'.$bbCode->full_format($Body, get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions']), true).'</div>
                    </div>';
