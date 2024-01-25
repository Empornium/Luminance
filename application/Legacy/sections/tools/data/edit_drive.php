<?php
authorize();

if (!check_perms('admin_donor_drives'))  error(403);

if (!$_REQUEST['driveid'] || !is_integer_string($_REQUEST['driveid'])) {
    error(0);
}

$SET_SQL = [];
$params = [];

$id = (int) ($_REQUEST['driveid']);

list($state, $start_time) = $master->db->rawQuery(
    "SELECT state,
            start_time
       FROM donation_drives
      WHERE ID = ?",
    [$id]
)->fetch(\PDO::FETCH_NUM);
if ($master->db->foundRows() < 1) error("No Donation drive with ID={$id} could be found!");

if ($state=='finished') {
    error("You cannot edit a finished donation drive! (why you try hax0r?)");
}

if (!$_REQUEST['drivename'] || strlen($_REQUEST['drivename']) < 1) {
        error("Error: title was not provided");
}

if (!$_REQUEST['target'] || !is_integer_string($_REQUEST['target']) || $_REQUEST['target'] < 1) {
        error("Error: target euros was not provided");
}

$name = $_REQUEST['drivename'];
$description = $_REQUEST['body'];
if ($state == 'notstarted') {
    $SET_SQL[] = "description = ?";
    $params[] = $description;
}

if ($_REQUEST['submit']=='Start Donation Drive') {
    if ($state!='notstarted') error("haX0r?");

    $old = $master->db->rawQuery(
        "SELECT name
           FROM donation_drives
          WHERE state='active'"
    )->fetchColumn();
    if ($master->db->foundRows() > 0) {
        error("There is already an active drive!<br/><br/>You must close the '$old' donation drive before you can start this one");
    }

    if ($_REQUEST['autothread'] == '0') {

        $ForumID = (int) $_POST['forumid'];
        $count = $master->db->rawQuery(
            "SELECT COUNT(ID)
               FROM forums
              WHERE ID = ?",
            [$ForumID]
        )->fetchColumn();
        if ($count < 1) {
            error("No forum with id={$ForumID} exists!");
        }
        $thread_id = create_thread($ForumID, $activeUser['ID'], $name, $description);
        if ($thread_id < 1) {
            error(0);
        }
    } else {

        $thread_id = (int) ($_REQUEST['threadid']);
        if (!$thread_id || !is_integer_string($thread_id)) {
            error("No thread with id=$thread_id exists!");
        } else {
            $count = $master->db->rawQuery(
                "SELECT COUNT(ForumID)
                   FROM forums_threads
                  WHERE ID = ?",
                [$thread_id]
            )->fetchColumn();
            if ($count < 1) {
                error("No thread with id={$thread_id} exists!");
            }
        }
    }

    if ($_REQUEST['autodate'] == '0') {  // use now()

        $start_time = sqltime();

    } else {

        $timestamp = strtotime($_REQUEST['starttime']);
        if ($timestamp === -1 || $timestamp === false) {
            error("Error: could not parse date '{$_REQUEST['startdate']}'");
        }
        $start_time = sqltime($timestamp);
    }

    $SET_SQL[] = "start_time = ?";
    $params[] = $start_time;

    $SET_SQL[] = "state = 'active'";

    $master->cache->deleteValue('active_drive');

} else {

    $thread_id = (int) ($_REQUEST['threadid']);

    if ($_REQUEST['submit']=='Finish Donation Drive') {
        if ($state!='active') error("haX0r?");

        list($raised, $count) = $master->db->rawQuery(
            "SELECT SUM(amount_euro),
                    Count(ID)
               FROM bitcoin_donations
              WHERE state != 'unused'
                AND received > ?",
            [$start_time]
        )->fetch(\PDO::FETCH_NUM);

        $SET_SQL[] = "state = 'finished'";
        $SET_SQL[] = "end_time = ?";
        $params[] = sqltime();
        $SET_SQL[] = "raised_euros = ?";
        $params[] = $raised;

        $master->cache->deleteValue('active_drive');

    } else {

        $timestamp = strtotime($_REQUEST['starttime']);
        if ($timestamp === -1 || $timestamp === false) {
            error("Error: could not parse date '{$_REQUEST['starttime']}'");
        }
        $start_time = sqltime($timestamp);    // $_REQUEST['startdate']);
        $SET_SQL[] = "start_time = ?";
        $params[] = $start_time;
    }
}

$target_euros = (int) ($_REQUEST['target']);

$SET_SQL[] = "threadid = ?";
$params[] = $thread_id;
$SET_SQL[] = "name = ?";
$params[] = $name;
$SET_SQL[] = "target_euros = ?";
$params[] = $target_euros;

$params[] = $id;

$master->db->rawQuery(
    "UPDATE donation_drives
        SET ".implode(', ', $SET_SQL)."
      WHERE ID = ?",
    $params
);

header("Location: tools.php?action=donation_drives#drive$id");
