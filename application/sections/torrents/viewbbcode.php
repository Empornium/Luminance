<?php
$GroupID = $_GET['groupid'];
if (!is_number($GroupID)) { error(404); }

$Torrent = $master->db->raw_query("SELECT tg.Name, tg.Body, t.UserID FROM torrents_group AS tg
  JOIN torrents AS t ON t.GroupID = tg.ID WHERE tg.ID=:GroupID", [':GroupID' => $GroupID])->fetch(\PDO::FETCH_ASSOC);

if (!$Torrent)
    error(404);

// check if user has permission to view the bbcode
if (!check_perms('torrents_edit') && $LoggedUser['ID'] != $Torrent['UserID'])
    error(403);

show_header('View torrent bbcode');

?>

<div class="thin">
    <h2>BBCode for <?=$Torrent['Name']?></h2>

    <div class="box pad">
        <textarea id="body" name="body" class="long" rows="20"><?=$Torrent['Body']?></textarea><br /><br />
    </div>
</div>
<?php
show_footer();
