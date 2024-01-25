<?php

$bbCode = new \Luminance\Legacy\Text;
$Validate = new \Luminance\Legacy\Validate;

$Validate->SetFields('thread', '0', 'number', 'The value you entered was not a number.', ['minlength' => 0]);
$Validate->SetFields('forumid', '0', 'number', 'The value you entered was not a number.', ['minlength' => 0]);
$Validate->SetFields('title', '1', 'string', 'You must enter a Title.', ['maxlength' => 200, 'minlength' => 2, 'maxwordlength'=>TITLE_MAXWORD_LENGTH]);
$whitelist_regex = get_whitelist_regex();
$Validate->SetFields('image', '0', 'image', 'The image URL you entered was not valid.', ['regex' => $whitelist_regex, 'maxlength' => 255, 'minlength' => 12]);
$Validate->SetFields('body', '1', 'desc', 'Description', ['regex' => $whitelist_regex, 'minimages'=>0, 'maxlength' => 1000000, 'minlength' => 0]);

$err = $Validate->ValidateForm($_POST, $bbCode); // Validate the form

if (!$err && !$bbCode->validate_bbcode($_POST['body'],  get_permissions_advtags($activeUser['ID']), false)) {
        $err = "There are errors in your bbcode (unclosed tags)";
}
if (!in_array($blogSection,['Blog', 'Contests'])) $err = 'The section id was not valid, section: '.$blogSection;

if ($err) error($err);

$threadID = (int)$_POST['thread'];

if ($threadID && is_integer_string($threadID)) {
    $test = $master->db->rawQuery(
        "SELECT ForumID
           FROM forums_threads
          WHERE ID = ?",
        [$threadID]
    )->fetchColumn();
    if (!$test) {
        error("No such thread exists!");
    }
} else {
    $forumID = (int) $_POST['forumid'];
    $test = $master->db->rawQuery(
        "SELECT ID
           FROM forums
          WHERE ID = ?",
        [$forumID]
    )->fetchColumn();
    if (!$test) {
        error("No forum with id=$forumID exists!");
    }
    // postpone creating thread until we have a blogid
    $threadID=0;
}

$master->db->rawQuery(
    "INSERT INTO blog (UserID, Title, Body, Time, ThreadID, Section, Image)
          VALUES (?, ?, ?, ?, ?, ?, ?)",
    [
        $activeUser['ID'],
        $_POST['title'],
        $_POST['body'],
        sqltime(),
        $threadID,
        $blogSection,
        $_POST['image'],
    ]
);

$newblogID = $master->db->lastInsertID();
if ($newblogID < 1) error("Error creating post");

// if thread==0 then we need to create the thread in forumid
if ($threadID==0 && $forumID > 0) {
    $body = "[url=/".lcfirst($blogSection).".php#blog{$newblogID}][i]View the post in the {$blogSection} section[/i][/url]\n".$_POST['body'];

    $threadID = create_thread($forumID, $activeUser['ID'], $_POST['title'], $body);
    if ($threadID < 1) error("Error creating thread");

    $master->db->rawQuery(
        "UPDATE blog
            SET ThreadID = ?
          WHERE ID = ?",
        [$threadID, $newblogID]
    );
}

$master->cache->deleteValue(strtolower($blogSection));
$master->cache->deleteValue(strtolower($blogSection.'_latest_id'));

if (isset($_POST['subscribe'])) {
    $master->db->rawQuery(
        "INSERT IGNORE INTO forums_subscriptions VALUES (?, ?)",
        [$activeUser['ID'], $threadID]
    );
    $master->cache->deleteValue('subscriptions_user_'.$activeUser['ID']);
}

header('Location: '.$thispage);
