<?php
authorize();

if (!check_perms('admin_donor_drives'))  error(403);

if (!$_REQUEST['driveid'] || !is_number($_REQUEST['driveid'])) {
    error(0);
}

$SET_SQL = array();

$id = (int) ($_REQUEST['driveid']);

$DB->query("SELECT state, start_time FROM donation_drives WHERE ID='$id'" );
if($DB->record_count()<1) error("No Donation drive with ID=$id could be found!");
list($state,$start_time)=$DB->next_record();

if ($state=='finished') {
    error("You cannot edit a finished donation drive! (why you try hax0r?)");
}

if (!$_REQUEST['drivename'] || strlen($_REQUEST['drivename']) < 1) {
        error("Error: title was not provided");
}

if (!$_REQUEST['target'] || !is_number($_REQUEST['target']) || $_REQUEST['target'] < 1) {
        error("Error: target euros was not provided");
}

$name = db_string($_REQUEST['drivename']);
$description = db_string($_REQUEST['body']);
if ($state == 'notstarted') $SET_SQL[] = "description='$description'";

if ($_REQUEST['submit']=='Start Donation Drive') {
    if($state!='notstarted') error("haX0r?");

    $DB->query("SELECT name FROM donation_drives WHERE state='active'" );
    if ($DB->record_count()>0) {
        list($old)=$DB->next_record();
        error("There is already an active drive!<br/><br/>You must close the '$old' donation drive before you can start this one");
    }

    if ($_REQUEST['autothread'] == '0') {

        $ForumID = (int) $_POST['forumid'];
        $DB->query("SELECT ID FROM forums WHERE ID=$ForumID");
        if ($DB->record_count() < 1) {
            error("No forum with id=$ForumID exists!");
        }
        $thread_id = create_thread($ForumID, $LoggedUser['ID'], $name, $description);
        if ($thread_id < 1) {
            error(0);
        }
    } else {

        $thread_id = (int) ($_REQUEST['threadid']);
        if (!$thread_id || !is_number($thread_id)) {
            error("No thread with id=$thread_id exists!");
        } else {
            $DB->query("SELECT ForumID FROM forums_topics WHERE ID=" . $thread_id);
            if ($DB->record_count() < 1) {
                error("No thread with id=$thread_id exists!");
            }
        }
    }

    if ($_REQUEST['autodate'] == '0') {  // use now()

        $start_time = db_string(sqltime());

    } else {

        $timestamp = strtotime($_REQUEST['starttime']);
        if ($timestamp === -1 || $timestamp === false)
            error("Error: could not parse date '$_REQUEST[startdate]'");
        $start_time = db_string(sqltime($timestamp));
    }

    $SET_SQL[] = "start_time='$start_time'";

    $SET_SQL[] = "state='active'";

    $Cache->delete_value('active_drive');

} else {

    $thread_id = (int) ($_REQUEST['threadid']);

    if ($_REQUEST['submit']=='Finish Donation Drive') {
        if($state!='active') error("haX0r?");

        $DB->query("SELECT SUM(amount_euro), Count(ID) FROM bitcoin_donations WHERE state!='unused' AND received > '$start_time'");
        list($raised, $count)=$DB->next_record();
        $raised=db_string($raised);

        $SET_SQL[] = "state='finished'";
        $SET_SQL[] = "end_time='".db_string(sqltime())."'";
        $SET_SQL[] = "raised_euros='$raised'";

        $Cache->delete_value('active_drive');

    } else {

        $timestamp = strtotime($_REQUEST['starttime']);
        if ($timestamp === -1 || $timestamp === false)
            error("Error: could not parse date '$_REQUEST[starttime]'");
        $start_time = db_string(sqltime($timestamp));    // $_REQUEST['startdate']);
        $SET_SQL[] = "start_time='$start_time'";
    }
}

$target_euros = (int) ($_REQUEST['target']);

$SET_SQL[] = "threadid='$thread_id'";
$SET_SQL[] = "name='$name'";
$SET_SQL[] = "target_euros='$target_euros'";

$DB->query("UPDATE donation_drives SET ".implode(',', $SET_SQL)." WHERE ID='$id';");

header("Location: tools.php?action=donation_drives#drive$id");
