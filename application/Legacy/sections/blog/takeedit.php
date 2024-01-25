<?php

$bbCode = new \Luminance\Legacy\Text;
$Validate = new \Luminance\Legacy\Validate;

$Validate->SetFields('blogid', '1', 'number', 'The value you entered was not a number.', ['minlength' => 1]);
$Validate->SetFields('thread', '0', 'number', 'The value you entered was not a number.', ['minlength' => 0]);
$Validate->SetFields('title', '1', 'string', 'You must enter a Title.', ['maxlength' => 200, 'minlength' => 2, 'maxwordlength'=>TITLE_MAXWORD_LENGTH]);
$whitelistregex = get_whitelist_regex();
$Validate->SetFields('image', '0', 'image', 'The image URL you entered was not valid.', ['regex' => $whitelistregex, 'maxlength' => 255, 'minlength' => 12]);
$Validate->SetFields('body', '1', 'desc', 'Description', ['regex' => $whitelistregex, 'minimages'=>0, 'maxlength' => 1000000, 'minlength' => 0]);

$err = $Validate->ValidateForm($_POST, $bbCode); // Validate the form

if (!$err && !$bbCode->validate_bbcode($_POST['body'],  get_permissions_advtags($activeUser['ID']), false)) {
        $err = "There are errors in your bbcode (unclosed tags)";
}

if ($_POST['thread']>0) {
    $test = $master->db->rawQuery(
        "SELECT ForumID
           FROM forums_threads
          WHERE ID = ?",
        [$_POST['thread']]
    )->fetchColumn();
    if (!$test) $err = "No such thread exists!";
}

if ($err) error($err);

$master->db->rawQuery(
    "UPDATE blog
        SET Title = ?,
            Body = ?,
            ThreadID = ?,
            Image = ?
      WHERE ID = ?",
    [
        $_POST['title'],
        $_POST['body'],
        $_POST['thread'],
        $_POST['image'],
        $_POST['blogid'],
    ]
);

$master->cache->deleteValue(strtolower($blogSection));
$master->cache->deleteValue('feed_blog');

header('Location: '.$thispage);
