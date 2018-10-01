<?php
if(!check_perms('admin_manage_events')) { error(403); }


show_header('Manage Upload Events');

?>
<div class="thin">
<h2>Manage Upload Events</h2>
<div class="head">Add new event</div>
<table>
    <tr class="colhead">
    <td width="120px">Title</td>
    <td>Comment</td>
    <td width="50px" class="center">UFL</td>
    <td width="150px">PFL</td>
    <td width="150px">Tokens</td>
    <td width="150px">Credits</td>
    <td width="200px">Start Date-Time</td>
    <td width="200px">End Date-Time</td>
    <td width="120px"></td>
    </tr>
    <tr class="rowa">
  <form action="tools.php" method="post">
    <input type="hidden" name="action" value="events_alter" />
    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
    <td>
      <input class="medium" type="text" name="title" placeholder="title" />
    </td>
    <td>
      <input class="long" type="text" name="comment" placeholder="comment" />
    </td>
    <td class="center">
      <input class="checkbox" value="1" type="checkbox" name="ufl" />
    </td>
    <td>
      <select name="pfl">
          <option value="0" selected="seleced">None</option>
          <option value="24">24 hours</option>
          <option value="48">48 hours</option>
          <option value="168">1 week</option>
          <option value="672">2 weeks</option>
      </select>
    </td>
    <td>
      <input class="medium" type="text" name="tokens" value="0" />
    </td>
    <td>
      <input class="medium" type="text" name="credits" value="0" />
    </td>
    <td>
      <input class="medium" type="text" name="starttime" value="<?=date('Y-m-d H:i:s', time() - (int) $LoggedUser['TimeOffset'])?>" />
    </td>
    <td>
      <input class="medium" type="text" name="endtime" value="<?=date('Y-m-d H:i:s', time() - (int) $LoggedUser['TimeOffset'] + (24*60*60))?>" />
    </td>
    <td>
      <input type="submit" value="Create" />
    </td>
  </form>
    </tr>
</table>
<br/><br/>
<div class="head">Manage Upload Events</div>
<table>
    <tr class="colhead">
    <td width="120px">Title</td>
    <td>Comment</td>
    <td width="50px" class="center">UFL</td>
    <td width="150px">PFL</td>
    <td width="150px">Tokens</td>
    <td width="150px">Credits</td>
    <td width="200px">Start Date-Time</td>
    <td width="200px">End Date-Time</td>
    <td width="200px">Uploads</td>
    <td width="120px"></td>
    </tr>
<?php

$DB->query("SELECT ID,
                   Title,
                   Comment,
                   UFL,
                   PFL,
                   Tokens,
                   Credits,
                   StartTime,
                   EndTime
              FROM events");
$Row = 'b';

$Events = $DB->to_array(MYSQLI_ASSOC);
foreach($Events as $Event) {
    list($ID, $Title, $Comment, $UFL, $PFL, $Tokens, $Credits, $StartTime, $EndTime) = $Event;
    $DB->query("SELECT COUNT(*) FROM torrents_events WHERE EventID=$ID");
    list($Uploads)=$DB->next_record();
    $Row = ($Row === 'a' ? 'b' : 'a');
?>
    <tr class="row<?=$Row?>">
    <form action="tools.php" method="post">
                <input type="hidden" name="action" value="events_alter" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="id" value="<?=$ID?>" />

    <td>
      <input class="medium" type="text" name="title" value="<?=display_str($Title)?>" />
    </td>
    <td>
      <input class="long" type="text" name="comment" value="<?=display_str($Comment)?>" />
    </td>
    <td class="center">
      <input class="checkbox" <?php selected('UFL', '1', 'checked', $Event) ?> type="checkbox" name="ufl" />
    </td>
    <td>
      <select name="pfl">
          <option value="0"   <?php selected('PFL', '0',   'selected', $Event) ?>>None</option>
          <option value="24"  <?php selected('PFL', '24',  'selected', $Event) ?>>24 hours</option>
          <option value="48"  <?php selected('PFL', '48',  'selected', $Event) ?>>48 hours</option>
          <option value="168" <?php selected('PFL', '168', 'selected', $Event) ?>>1 week</option>
          <option value="672" <?php selected('PFL', '672', 'selected', $Event) ?>>2 weeks</option>
      </select>
    </td>
    <td>
      <input class="medium" type="text" name="tokens" value="<?=display_str($Tokens)?>" />
    </td>
    <td>
      <input class="medium" type="text" name="credits" value="<?=display_str($Credits)?>" />
    </td>
    <td>
      <input class="medium" type="text" name="starttime" value="<?=date('Y-m-d H:i:s', strtotime($StartTime) - (int) $LoggedUser['TimeOffset'])?>" />
    </td>
    <td>
      <input class="medium" type="text" name="endtime" value="<?=date('Y-m-d H:i:s', strtotime($EndTime) - (int) $LoggedUser['TimeOffset'])?>" />
    </td>
    <td>
      <input class="medium" type="text" style="text-align:right"  name="uploads" value="<?=$Uploads?>" disabled/>
    </td>
    <td>
                <input type="submit" name="submit" value="Edit" />
<?php if ($Uploads == 0) { ?>
                <input type="submit" name="submit" value="Delete" />
<?php } ?>
    </td>
    </form>
    </tr>
<?php  }  ?>
</table>
</div>
<?php  show_footer(); ?>
