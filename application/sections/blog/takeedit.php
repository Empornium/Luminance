<?php

$Text = new Luminance\Legacy\Text;
$Validate = new Luminance\Legacy\Validate;

$Validate->SetFields('blogid', '1', 'number', 'The value you entered was not a number.', array('minlength' => 1));
$Validate->SetFields('thread', '0', 'number', 'The value you entered was not a number.', array('minlength' => 0));
$Validate->SetFields('title', '1', 'string', 'You must enter a Title.', array('maxlength' => 200, 'minlength' => 2, 'maxwordlength'=>TITLE_MAXWORD_LENGTH));
$whitelistregex = get_whitelist_regex();
$Validate->SetFields('image', '0', 'image', 'The image URL you entered was not valid.', array('regex' => $whitelistregex, 'maxlength' => 255, 'minlength' => 12));
$Validate->SetFields('body', '1', 'desc', 'Description', array('regex' => $whitelistregex, 'minimages'=>0, 'maxlength' => 1000000, 'minlength' => 0));

$err = $Validate->ValidateForm($_POST, $Text); // Validate the form

if (!$err && !$Text->validate_bbcode($_POST['body'],  get_permissions_advtags($LoggedUser['ID']), false)) {
        $err = "There are errors in your bbcode (unclosed tags)";
}

if ($_POST['thread']>0) {
    $test = $master->db->raw_query("SELECT ForumID FROM forums_topics WHERE ID=:threadid", [':threadid' => $_POST['thread']])->fetchColumn();
    if (!$test) $err = "No such thread exists!";
}

if ($err) error($err);

$master->db->raw_query("UPDATE blog SET Title    = :title,
                                        Body     = :body,
                                        ThreadID = :threadid,
                                        Image    = :image
                                  WHERE ID       = :blogid",
                                        [':title'    => $_POST['title'],
                                         ':body'     => $_POST['body'],
                                         ':threadid' => $_POST['thread'],
                                         ':image'    => $_POST['image'],
                                         ':blogid'   => $_POST['blogid']]);

$master->cache->delete_value(strtolower($blogSection));
$master->cache->delete_value('feed_blog');

header('Location: '.$thispage);
