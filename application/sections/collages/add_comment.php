<?php
authorize();

if (empty($_POST['collageid']) || !is_number($_POST['collageid'])) { error(0); }

if (empty($_POST['body'])) {
    error('You cannot post a comment with no content.');
}
$CollageID = $_POST['collageid'];

if ($LoggedUser['DisablePosting']) {
    error('Your posting rights have been removed'); // Should this be logged?
}

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;
$Text->validate_bbcode($_POST['body'],  get_permissions_advtags($LoggedUser['ID']));

flood_check('collages_comments');

$DB->query("INSERT INTO collages_comments
    (CollageID, Body, UserID, Time)
    VALUES
    ('$CollageID', '".db_string($_POST['body'])."', '$LoggedUser[ID]', '".sqltime()."')");

$CommentID = $DB->inserted_id();

$Cache->delete_value('collage_'.$CollageID.'_1');
$Cache->delete_value('collage_'.$CollageID);
header('Location: collages.php?id='.$CollageID."#post$CommentID");
