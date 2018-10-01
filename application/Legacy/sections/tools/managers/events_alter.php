<?php

if(!check_perms('admin_manage_events')){ error(403); }

authorize();

if($_POST['submit'] == 'Delete') {

  if(!is_number($_POST['id']) || $_POST['id'] == ''){ error(0); }

        // Make sure event was never used!
        $DB->query("SELECT COUNT(*) FROM torrents_events WHERE EventID=$_POST[id]");
        list($Uploads) = $DB->next_record();
        if ($Uploads > 0) error(0);

        // Delete event
        $DB->query('DELETE FROM events WHERE ID='.$_POST['id']);
        $Cache->delete_value('active_events');

} else {
        $Val->SetFields('title',   '1', 'string',  'The name must be set, and has a max length of 64 characters',     array('maxlength'=>64, 'minlength'=>1));
        $Val->SetFields('comment', '1', 'string',  'The comment must be set, and has a max length of 255 characters', array('maxlength'=>255, 'minlength'=>0));
        $Val->SetFields('pfl',     '1', 'number',  'The PFL field has invalid input',                                 array('maxlength'=>672, 'minlength'=>0));
        $Val->SetFields('tokens',  '1', 'number',  'The tokens field has invalid input',                              array('maxlength'=>4, 'minlength'=>0));
        $Val->SetFields('credits', '1', 'number',  'The credits field has invalid input',                             array('maxlength'=>1000000, 'minlength'=>0));
        // Need to fix checkbox validation
        //$Val->SetFields('ufl',     '1', 'checkbox', 'The UFL field has invalid input.');
        $Err=$Val->ValidateForm($_POST); // Validate the form
        if($Err){ error($Err); }

        $Title=db_string($_POST['title']);
        $Comment=db_string($_POST['comment']);
        $UFL = $_POST['ufl']=='on' ? '1':'0';
        $PFL = (int) $_POST['pfl'];
        $Tokens = (int) $_POST['tokens'];
        $Credits = (int) $_POST['credits'];
        $StartTime = date('Y-m-d H:i:s', strtotime($_POST['starttime']) + (int) $LoggedUser['TimeOffset']);
        $EndTime = date('Y-m-d H:i:s', strtotime($_POST['endtime']) + (int) $LoggedUser['TimeOffset']);

  if($_POST['submit'] == 'Edit'){ //Edit
    if(!is_number($_POST['id']) || $_POST['id'] == ''){ error(0); }
    $DB->query("UPDATE events SET
                              Title='$Title',
                              Comment='$Comment',
                              UFL='$UFL',
                              PFL='$PFL',
                              Tokens='$Tokens',
                              Credits='$Credits',
                              StaffID='$LoggedUser[ID]',
                              StartTime='$StartTime',
                              EndTime='$EndTime'
                              WHERE ID='{$_POST['id']}'");
  } else { //Create
    $DB->query("INSERT INTO events
      (Title, Comment, UFL, PFL, Tokens, Credits, StaffID, StartTime, EndTime) VALUES
      ('$Title', '$Comment', '$UFL', '$PFL', '$Tokens', '$Credits', '$LoggedUser[ID]', '$StartTime', '$EndTime')");
  }

        $Cache->delete_value('active_events');
}

// Go back
header('Location: tools.php?action=events_list');

?>
