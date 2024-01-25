<?php

if (!check_perms('admin_manage_events')) { error(403); }

authorize();

if ($_POST['submit'] == 'Delete') {

  if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }

        // Make sure event was never used!
        $Uploads = $master->db->rawQuery("SELECT COUNT(*) FROM torrents_events WHERE EventID = ?", [$_POST[id]])->fetchColumn();
        if ($Uploads > 0) error(0);

        // Delete event
        $master->db->rawQuery('DELETE FROM events WHERE ID= ?', [$_POST['id']]);
        $master->cache->deleteValue('active_events');

} else {
        $Val->SetFields('title',   '1', 'string',  'The name must be set, and has a max length of 64 characters',     ['maxlength'=>64,      'minlength'=>1]);
        $Val->SetFields('comment', '1', 'string',  'The comment must be set, and has a max length of 255 characters', ['maxlength'=>255,     'minlength'=>0]);
        $Val->SetFields('pfl',     '1', 'number',  'The PFL field has invalid input',                                 ['maxlength'=>672,     'minlength'=>0]);
        $Val->SetFields('tokens',  '1', 'number',  'The tokens field has invalid input',                              ['maxlength'=>4,       'minlength'=>0]);
        $Val->SetFields('credits', '1', 'number',  'The credits field has invalid input',                             ['maxlength'=>1000000, 'minlength'=>0]);
        // Need to fix checkbox validation
        //$Val->SetFields('ufl',     '1', 'checkbox', 'The UFL field has invalid input.');
        $Err=$Val->ValidateForm($_POST); // Validate the form
        if ($Err) { error($Err); }

        $Title = $_POST['title'];
        $Comment = $_POST['comment'];
        $UFL = $_POST['ufl']=='on' ? '1':'0';
        $PFL = (int) $_POST['pfl'];
        $Tokens = (int) $_POST['tokens'];
        $Credits = (int) $_POST['credits'];
        $StartTime = date('Y-m-d H:i:s', strtotime($_POST['starttime']) + (int) $activeUser['TimeOffset']);
        $EndTime = date('Y-m-d H:i:s', strtotime($_POST['endtime']) + (int) $activeUser['TimeOffset']);

  if ($_POST['submit'] == 'Edit') { //Edit
    if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
    $master->db->rawQuery(
        "UPDATE events
            SET Title = ?,
                Comment = ?,
                UFL = ?,
                PFL = ?,
                Tokens = ?,
                Credits = ?,
                StaffID = ?,
                StartTime = ?,
                EndTime = ?
          WHERE ID = ?",
        [$Title, $Comment, $UFL, $PFL, $Tokens, $Credits, $activeUser['ID'], $StartTime, $EndTime, $_POST['id']]
    );
  } else { //Create
    $master->db->rawQuery(
        "INSERT INTO events (Title, Comment, UFL, PFL, Tokens, Credits, StaffID, StartTime, EndTime)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$Title, $Comment, $UFL, $PFL, $Tokens, $Credits, $activeUser['ID'], $StartTime, $EndTime]
    );
  }

        $master->cache->deleteValue('active_events');
}

// Go back
header('Location: tools.php?action=events_list');

?>
