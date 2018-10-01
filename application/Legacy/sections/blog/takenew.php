<?php

$Text = new Luminance\Legacy\Text;
$Validate = new Luminance\Legacy\Validate;

$Validate->SetFields('thread', '0', 'number', 'The value you entered was not a number.', array('minlength' => 0));
$Validate->SetFields('forumid', '0', 'number', 'The value you entered was not a number.', array('minlength' => 0));
$Validate->SetFields('title', '1', 'string', 'You must enter a Title.', array('maxlength' => 200, 'minlength' => 2, 'maxwordlength'=>TITLE_MAXWORD_LENGTH));
$whitelist_regex = get_whitelist_regex();
$Validate->SetFields('image', '0', 'image', 'The image URL you entered was not valid.', array('regex' => $whitelist_regex, 'maxlength' => 255, 'minlength' => 12));
$Validate->SetFields('body', '1', 'desc', 'Description', array('regex' => $whitelist_regex, 'minimages'=>0, 'maxlength' => 1000000, 'minlength' => 0));

$err = $Validate->ValidateForm($_POST, $Text); // Validate the form

if (!$err && !$Text->validate_bbcode($_POST['body'],  get_permissions_advtags($LoggedUser['ID']), false)) {
        $err = "There are errors in your bbcode (unclosed tags)";
}
if (!in_array($blogSection,['Blog', 'Contests'])) $err = 'The section id was not valid, section: '.$blogSection;

if ($err) error($err);

$threadID = (int)$_POST['thread'];

if ($threadID && is_number($threadID)) {
    $test = $master->db->raw_query("SELECT ForumID FROM forums_topics WHERE ID=:threadid", [':threadid' => $threadID])->fetchColumn();
    if (!$test) {
        error("No such thread exists!");
    }
} else {
    $forumID = (int) $_POST['forumid'];
    $test = $master->db->raw_query("SELECT ID FROM forums WHERE ID=:forumid", [':forumid' => $forumID])->fetchColumn();
    if (!$test) {
        error("No forum with id=$forumID exists!");
    }
    // postpone creating thread until we have a blogid
    $threadID=0;
}

$master->db->raw_query("INSERT INTO blog (UserID, Title, Body, Time, ThreadID, Section, Image)
                VALUES (:userid, :title, :body, :time, :threadid, :section, :image)",
                    [':userid'   => $LoggedUser['ID'],
                     ':title'    => $_POST['title'],
                     ':body'     => $_POST['body'],
                     ':time'     => sqltime(),
                     ':threadid' => $threadID,
                     ':section'  => $blogSection,
                     ':image'    => $_POST['image']]);

$newblogID = $master->db->last_insert_id();
if ($newblogID < 1) error("Error creating post");

// if thread==0 then we need to create the thread in forumid
if ($threadID==0 && $forumID > 0) {
    $body = "[url=/".lcfirst($blogSection).".php#blog{$newblogID}][i]View the post in the {$blogSection} section[/i][/url]\n".$_POST['body'];

    $threadID = create_thread($forumID, $LoggedUser['ID'], db_string($_POST['title']), db_string($body));
    if ($threadID < 1) error("Error creating thread");

    $master->db->raw_query("UPDATE blog SET ThreadID = :threadid WHERE ID=:blogid",
                            [':threadid' => $threadID,
                             ':blogid'   => $newblogID]);
}

$master->cache->delete_value(strtolower($blogSection));
$master->cache->delete_value(strtolower($blogSection.'_latest_id'));

if (isset($_POST['subscribe'])) {
    $master->db->raw_query("INSERT IGNORE INTO users_subscriptions VALUES (:userid, :threadid)",
                            [':userid'   => $LoggedUser['ID'],
                             ':threadid' => $threadID]);
    $master->cache->delete_value('subscriptions_user_'.$LoggedUser['ID']);
}

header('Location: '.$thispage);
