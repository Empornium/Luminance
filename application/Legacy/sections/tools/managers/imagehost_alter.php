<?php
if (!check_perms('admin_imagehosts')) { error(403); }

authorize();

if ($_POST['submit'] == 'Delete') {
    //Delete
    if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
    $master->db->raw_query("DELETE FROM imagehost_whitelist WHERE ID=:id", [':id'=>$_POST['id']]);
} else {
    //Edit & Create, Shared Validation
    $regex = '@^(?=.{1,255}$)(https?)://([a-z0-9\-\_\*]+\.)+([a-z\*]{1,5}[^\./])(/[^<>/]+)*(/)?$@i';
    $Val->SetFields('host',    '1', 'regex',   'The host must be a valid url',                       array('regex'=>$regex));
    $Val->SetFields('comment', '0', 'string',  'The description has a max length of 255 characters', array('maxlength'=>255));
    $Val->SetFields('link',    '0', 'regex',   'The goto link is not a valid url.',                  array('regex'=>getValidUrlRegex()));
    $Val->SetFields('show',    '1', 'inarray', 'The show field has invalid input.',                  array('inarray'=>array(0,1)));

    $_POST['link'] = trim($_POST['link']);
    $_POST['comment'] = trim($_POST['comment']);

    $Err=$Val->ValidateForm($_POST);
    if ($Err) { error($Err); }

    $hidden = $_POST['show']==0 ? '1':'0';

    if ($_POST['submit'] == 'Edit') {
        //Edit
        if (!is_number($_POST['id']) || $_POST['id'] == '') { error(0); }
        $master->db->raw_query("UPDATE imagehost_whitelist
                                   SET Imagehost = :host,
                                       Link      = :link,
                                       Comment   = :comment,
                                       UserID    = :userid,
                                       Hidden    = :hidden
                                 WHERE ID = :id",
                                      [':host'    => $_POST['host'],
                                       ':link'    => $_POST['link'],
                                       ':comment' => $_POST['comment'],
                                       ':userid'  => $LoggedUser['ID'],
                                       ':hidden'  => $hidden,
                                       ':id'      => $_POST['id']]);
    } else {
        //Create
        $master->db->raw_query("INSERT INTO imagehost_whitelist
                                            (Imagehost, Link, Comment, UserID, Time, Hidden)
                                     VALUES (:host, :link, :comment, :userid, :time, :hidden)",
                                             [':host'    => $_POST['host'],
                                              ':link'    => $_POST['link'],
                                              ':comment' => $_POST['comment'],
                                              ':userid'  => $LoggedUser['ID'],
                                              ':time'    => sqltime(),
                                              ':hidden'  => $hidden]);
    }
}
$master->cache->delete_value('imagehost_regex');
$master->cache->delete_value('imagehost_whitelist');

// Go back
header('Location: tools.php?action=imghost_whitelist');
