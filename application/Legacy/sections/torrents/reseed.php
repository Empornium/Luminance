<?php
$GroupID = $_GET['groupid'];
$torrentID = $_GET['torrentid'];

if (!is_integer_string($GroupID) || !is_integer_string($torrentID)) { error(0); }

// select info about the torrent and uploader
$Info = $master->db->rawQuery(
    "SELECT tg.Name,
            t.LastReseedRequest,
            t.UserID AS UploaderID,
            t.Time AS UploadedTime,
            SUM(xu.active) AS UploaderIsPeer,
            t.Seeders,
            t.Snatched,
            t.last_action
       FROM torrents AS t
       JOIN torrents_group AS tg ON t.GroupID = tg.ID
  LEFT JOIN xbt_files_users AS xu ON t.ID = xu.fid AND t.UserID = xu.uid
      WHERE t.ID = ?
   GROUP BY UploaderID",
    [$torrentID]
)->fetch(\PDO::FETCH_ASSOC);

// Check if the torrent really needs a reseed
// Those permissions are taken from torrents/details.php
// Note: Last reseed request is not checked here
$NotEnoughSeed  = $Info['Seeders'] < 5;
$Active         = $Info['last_action'] != '0000-00-00 00:00:00';
$LastActive     = $Active ? (time() - strtotime($Info['last_action'])) : 0;
$NotJustCreated = (time() - strtotime($Info['UploadedTime'])) > 86400;
$Snatched       = $Info['Snatched'] > 2 || $Info['Snatched'] > $Info['Seeders'];
$IsTime         = ($Info['Seeders'] < 3 && $LastActive >= 3600 * 3) || $LastActive >= 86400;
$NeedReseed     = $NotEnoughSeed && $Active && $NotJustCreated && $Snatched && $IsTime;

if (!$NeedReseed) {
    error(-1);
}

if (time()-strtotime($Info['LastReseedRequest'])<259200 && !check_perms('site_debug')) { error("There was already a re-seed request for this torrent within the past 3 days."); }

$master->cache->deleteValue('torrents_details_'.$GroupID);

// select everyone who has snatched the torrent who isn't currently an active peer
$Users = $master->db->rawQuery(
    "SELECT xs.uid AS UserID,
            Max(xs.tstamp) AS LastSnatchedTime
       FROM xbt_snatched AS xs
  LEFT JOIN xbt_files_users AS xu ON xs.fid = xu.fid AND xs.uid = xu.uid
      WHERE (xu.uid IS NULL OR xu.active = '0') AND xs.fid = ? AND xs.uid != ?
   GROUP BY UserID
   ORDER BY LastSnatchedTime DESC
      LIMIT 40",
    [$torrentID, $Info['UploaderID']]
)->fetchAll(\PDO::FETCH_ASSOC);

if (!$Info['UploaderIsPeer']) $Users[] = ['UserID' => $Info['UploaderID'], 'LastSnatchedTime' => strtotime($Info['UploadedTime'])];

foreach ($Users as $User) {
    // send a pm to each user
    $verb = $User['UserID']==$Info['UploaderID'] ? 'uploaded':'snatched';
    $Request = "Hi [you],
[br]The user [url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url] has requested a re-seed for the torrent [url=/torrents.php?id=$GroupID&torrentid=$torrentID]".$Info['Name']."[/url], which you $verb on ".date('M d Y', $User['LastSnatchedTime']).". The torrent is now un-seeded, and we need your help to resurrect it!
[br]The exact process for re-seeding a torrent is slightly different for each client, but the concept is the same. The idea is to download the .torrent file and open it in your client, and point your client to the location where the data files are, then initiate a hash check.
[br]Thanks!  :emplove:";

    send_pm($User['UserID'], 0, 'Re-seed request for torrent '.$Info['Name'], $Request);
}

$NumUsers = count($Users);

if ($NumUsers>0) {
    $master->db->rawQuery(
        "UPDATE torrents
            SET LastReseedRequest = ?
          WHERE ID = ?",
        [sqltime(), $torrentID]
    );
}

show_header();
?>
<div class="thin">
    <h2>Successfully sent re-seed request</h2>
    <div class="head"></div>
    <div class="box pad center">
        Successfully sent re-seed request for torrent <a href="/torrents.php?id=<?=$GroupID?>&amp;torrentid=<?=$torrentID?>"><?=display_str($Info['Name'])?></a> to <?=$NumUsers?> user(s).
    </div>
</div>
<?php
show_footer();
