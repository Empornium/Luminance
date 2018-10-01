<?php
$Text = new Luminance\Legacy\Text;

$Subject = $_REQUEST['subject'];
if( !empty($_REQUEST['prependtitle']))  $Subject = $_REQUEST['prependtitle'] . $Subject;
if ( !empty($_REQUEST['message'])) $Body = $_REQUEST['message'];
else $Body = $_REQUEST['body'];

echo'
              <h2>'. display_str($Subject).'</h2>
                    <div class="box">
                        <div class="head">
                               '. format_username($LoggedUser['ID'], $LoggedUser['Username'], $LoggedUser['Donor'], true, $LoggedUser['Enabled'], $LoggedUser['PermissionID'], $LoggedUser['Title'], true). '  Just now
                        </div>
                        <div class="body">'.$Text->full_format($Body, get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']), true).'</div>
                    </div>';
