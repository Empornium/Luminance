<?php
authorize();

//Set by system
if (!$_POST['groupid'] || !is_number($_POST['groupid'])) {
    error(404);
}
$GroupID = $_POST['groupid'];

//Usual perm checks
if (!check_perms('torrents_edit')) {
    $DB->query("SELECT UserID FROM torrents WHERE GroupID = ".$GroupID);
    if (!in_array($LoggedUser['ID'], $DB->collect('UserID'))) {
        error(403);
    }
}

if (check_perms('torrents_freeleech') && isset($_POST['freeleech'])) {
    $Free = (int) $_POST['freeleech'];
    $Free = $Free==1?1:0;

    freeleech_groups($GroupID, $Free);
}

header("Location: torrents.php?id=".$GroupID);
