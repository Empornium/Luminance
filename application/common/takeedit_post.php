<?php
enforce_login();
authorize();

/*********************************************************************\
//--------------Take edited Post--------------------------------------------//

this handles ajax save edits from everywhere except forums

\*********************************************************************/

header('Content-Type: application/json; charset=utf-8');

$Text = new Luminance\Legacy\Text;

// Quick SQL injection check
if (!$_POST['post'] || !is_integer_string($_POST['post'])) {
    error(0, true);
}

if (empty($_POST['section']) || !in_array($_POST['section'], ['collages', 'requests', 'torrents', 'staffpm'])) {
    error(0, true);
}

if (empty($_POST['body'])) error('You cannot post a comment with no content.', true);

$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::POST);


$postID  = $_POST['post'];
$section = $_POST['section'];


// what a mess... one day this will all be gone!
$field = [];
switch ($section) {
    case 'torrents':
        $field['table']  = 'torrents_comments';
        $field['item']   = 'GroupID';
        $field['author'] = 'AuthorID';
        $field['time']   = 'AddedTime';
        $field['body']   = 'Body';
        break;
    case 'requests':
        $field['table']  = 'requests_comments';
        $field['item']   = 'RequestID';
        $field['author'] = 'AuthorID';
        $field['time']   = 'AddedTime';
        $field['body']   = 'Body';
        break;
    case 'staffpm':
        $field['table']  = 'staff_pm_messages';
        $field['item']   = 'ConvID';
        $field['author'] = 'UserID';
        $field['time']   = 'SentDate';
        $field['body']   = 'Message';
        break;
}

// get info on the current post
$postinfo = $master->db->rawQuery(
    "SELECT {$field['body']},
            {$field['author']},
            {$field['item']},
            {$field['time']},
            EditedTime,
            EditedUserID
       FROM {$field['table']}
      WHERE ID = :postid",
    [':postid' => $postID]
)->fetch(\PDO::FETCH_NUM);

if (!isset($postinfo[0])) error(404, true);

list($oldBody, $authorID, $itemID, $addedTime, $editedTime, $editedUserID)= $postinfo;
$shouldNotifyStaffEdit = false;

switch ($section) {
    case 'torrents':
    case 'requests':
        validate_edit_comment($authorID, $editedUserID, $addedTime, $editedTime);
        $page = $master->db->rawQuery(
            "SELECT ceil(COUNT(ID) / :commentsperpage) AS Page
               FROM {$field['table']}
              WHERE {$field['item']} = :itemid
                AND ID <= :postid",
            [':commentsperpage' => TORRENT_COMMENTS_PER_PAGE,
             ':itemid'          => $itemID,
             ':postid'          => $postID]
        )->fetchColumn();
        $catalogueID = floor((TORRENT_COMMENTS_PER_PAGE*$page-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
        $shouldNotifyStaffEdit = true;
        break;

    case 'staffpm':
        $pminfo = $master->db->rawQuery(
            "SELECT Level, AssignedToUser
               FROM staff_pm_conversations AS c
               JOIN staff_pm_messages AS m ON m.ConvID=c.ID
              WHERE m.ID = ?",
            [$postID]
        )->fetch(\PDO::FETCH_ASSOC);
        // only staff can save staffpm edits
        if ($activeUser['DisplayStaff'] != 1) {
            error(403, true);
        }

        if ($pminfo['AssignedToUser'] != $activeUser['ID'] &&  $pminfo['Level'] > $activeUser['Class']) {
            // only let appropriate level staff edit this staffpm
            error(403, true);
        }
        break;
}

$preview = $Text->full_format($_POST['body'], get_permissions_advtags($activeUser['ID'], $activeUser['CustomPermissions']));

if ($Text->has_errors()) {
    $result = 'error';
    $bbErrors = implode('<br/>', $Text->get_errors());
    $preview = ("<strong>NOTE: Changes were not saved.</strong><br/><br/>There are errors in your bbcode (unclosed tags)<br/><br/>{$bbErrors}<br/><div class=\"box\"><div class=\"post_content\">{$preview}</div></div>");
} else {
    // Perform the update
    $result = 'saved';
    $sqltime = sqltime();
    $master->db->rawQuery(
        "UPDATE {$field['table']}
            SET {$field['body']} = :body,
                EditedUserID = :edituserid,
                EditedTime   = :edittime
          WHERE ID = :postid",
        [':body'       => $_POST['body'],
         ':edituserid' => $activeUser['ID'],
         ':edittime'   => $sqltime,
         ':postid'     => $postID]
    );

    $master->db->rawQuery(
        "INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
        VALUES (:section, :postid, :userid, :sqltime, :body)",
        [':section' => $section,
         ':postid'  => $postID,
         ':userid'  => $activeUser['ID'],
         ':sqltime' => $sqltime,
         ':body'    => $oldBody]
    );

    // Notify the author if this is a Staff edit
    if ($shouldNotifyStaffEdit && $activeUser['ID'] != $authorID) {
        switch ($section) {
            case 'torrents':
                $url = '/torrents.php?id='.$itemID;
                break;
            case 'requests':
                $url = '/requests.php?action=view&id='.$itemID;
                break;
            default:
                break;
        }
        notify_staff_edit($authorID, $url);
    }

    // Update the cache
    switch ($section) {
        case 'torrents':
            $master->cache->deleteValue("_entity_TorrentComment_{$postID}");
            break;
        case 'requests':
            $master->cache->deleteValue('requests_edits_'.$postID);
            $master->cache->deleteValue('request_comments_'.$itemID);
            $master->cache->deleteValue('request_comments_'.$itemID.'_catalogue_'.$catalogueID); // request_comments_7_catalogue_0
            break;
        case 'staffpm':
            break;
    }
}

$html = $master->render->template(
    '@Legacy/snippets/takeedit_post.html.twig',
    [
        'preview' => $preview,
        'result'  => $result,
        'section' => $section,
        'postID'  => $postID,
    ]
);

echo json_encode([$result, $html]);
