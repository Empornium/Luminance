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
if (!$_POST['post'] || !is_number($_POST['post'])) { error(0, true); }

if (empty($_POST['section']) || !in_array($_POST['section'], array('collages', 'requests', 'torrents', 'staffpm'))) {
    error(0, true);
}

if (empty($_POST['body'])) error('You cannot post a comment with no content.', true);

$master->repos->restrictions->check_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::POST);


$postID  = $_POST['post'];
$section = $_POST['section'];


// what a mess... one day this will all be gone!
$field = [];
switch($section) {
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
    case 'collages':
        $field['table']  = 'collages_comments';
        $field['item']   = 'CollageID';
        $field['author'] = 'UserID';
        $field['time']   = 'Time';
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
$postinfo = $master->db->raw_query("SELECT
                                          $field[body],
                                          $field[author],
                                          $field[item],
                                          $field[time],
                                          EditedTime,
                                          EditedUserID
                                     FROM $field[table]
                                    WHERE ID = :postid",
                                         [':postid' => $postID])->fetch(\PDO::FETCH_NUM);

if (!isset($postinfo[0])) error(404, true);

list($oldBody, $authorID, $itemID, $addedTime, $editedTime, $editedUserID)= $postinfo;



switch($section) {
    case 'torrents':
    case 'requests':
    case 'collages':

        validate_edit_comment($authorID, $editedUserID, $addedTime, $editedTime);

        if ($section != 'collages') {
            $page = $master->db->raw_query("SELECT ceil(COUNT(ID) / :commentsperpage) AS Page FROM $field[table] WHERE $field[item] = :itemid AND ID <= :postid",
                                                    [':commentsperpage' => TORRENT_COMMENTS_PER_PAGE,
                                                     ':itemid'          => $itemID,
                                                     ':postid'          => $postID])->fetchColumn();
            $catalogueID = floor((TORRENT_COMMENTS_PER_PAGE*$page-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
        }
        break;

    case 'staffpm':
        $pminfo = $master->db->raw_query("SELECT Level, AssignedToUser
                                           FROM staff_pm_conversations AS c
                                           JOIN staff_pm_messages AS m ON m.ConvID=c.ID
                                          WHERE m.ID = :postid",
                                               [':postid' => $postID])->fetch(\PDO::FETCH_ASSOC);
        // only staff can save staffpm edits
        if ($LoggedUser['DisplayStaff'] != 1) error(403, true);

        if ( $pminfo['AssignedToUser'] != $LoggedUser['ID'] &&  $pminfo['Level'] > $LoggedUser['Class'] ) {
            // only let appropriate level staff edit this staffpm
            error(403, true);
        }
        break;
}

$preview = $Text->full_format($_POST['body'], get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']));

if ($Text->has_errors()) {
    $result = 'error';
    $bbErrors = implode('<br/>', $Text->get_errors());
    $preview = ("<strong>NOTE: Changes were not saved.</strong><br/><br/>There are errors in your bbcode (unclosed tags)<br/><br/>$bbErrors<br/><div class=\"box\"><div class=\"post_content\">$preview</div></div>");

} else {
    // Perform the update
    $result = 'saved';
    $sqltime = sqltime();
    $master->db->raw_query("UPDATE $field[table]
                               SET $field[body] = :body,
                                   EditedUserID = :edituserid,
                                   EditedTime   = :edittime
                             WHERE ID = :postid",
                                  [':body'       => $_POST['body'],
                                   ':edituserid' => $LoggedUser['ID'],
                                   ':edittime'   => $sqltime,
                                   ':postid'     => $postID]);

    $master->db->raw_query("INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
                                                VALUES (:section, :postid, :userid, :sqltime, :body)",
                                                     [':section' => $section,
                                                      ':postid'  => $postID,
                                                      ':userid'  => $LoggedUser['ID'],
                                                      ':sqltime' => $sqltime,
                                                      ':body'    => $oldBody]);
    // Update the cache
    switch($section) {
        case 'torrents':
            $master->cache->delete_value('torrents_edits_'.$postID);
            $master->cache->delete_value('torrent_comments_'.$itemID.'_catalogue_'.$catalogueID);
            break;
        case 'requests':
            $master->cache->delete_value('requests_edits_'.$postID);
            $master->cache->delete_value('request_comments_'.$itemID);
            $master->cache->delete_value('request_comments_'.$itemID.'_catalogue_'.$catalogueID); // request_comments_7_catalogue_0
            break;
        case 'collages':
            $master->cache->delete_value('collages_edits_'.$postID);
            $master->cache->delete_value('collage_comments_'.$itemID);
            break;
        case 'staffpm':
            break;
    }
}

ob_start();
?>      <div class="post_content">
            <?=$preview; ?>
        </div>
<?php   if ($result=='saved') { ?>
            <div class="post_footer">
<?php       if (check_perms('site_moderate_forums')) { ?>
                <a href="#content<?=$postID?>" onclick="LoadEdit('<?=$section?>', <?=$postID?>, 1); return false;">&laquo;</a>
<?php       }   ?>
                <span class="editedby">Last edited by <a href="user.php?id=<?=$LoggedUser['ID']?>"><?=$LoggedUser['Username']?></a> just now</span>
            </div>
<?php   }

$html = ob_get_contents();
ob_end_clean();

echo json_encode(array($result, $html));
