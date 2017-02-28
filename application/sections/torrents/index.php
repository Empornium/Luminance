<?php
define('TORRENT_EDIT_TIME', 3600 * 24 * 14  );

//Function used for pagination of peer/snatch/download lists on details.php
function js_pages($Action, $TorrentID, $NumResults, $CurrentPage)
{
    $NumPages = ceil($NumResults/100);
    $PageLinks = array();
    for ($i = 1; $i<=$NumPages; $i++) {
        if ($i == $CurrentPage) {
            $PageLinks[]=$i;
        } else {
            $PageLinks[]='<a href="#" onclick="'.$Action.'('.$TorrentID.', '.$i.')">'.$i.'</a>';
        }
    }

    return implode(' | ',$PageLinks);
}

if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'resort_tags':
            error(0, true);
            authorize();

            header('Content-Type: application/json; charset=utf-8');

            include(SERVER_ROOT . '/sections/torrents/functions.php');

            $GroupID = $_POST['groupid'];
            if (!is_number($GroupID) || !$GroupID) error(0, true);

            echo json_encode(array(get_taglist_html($GroupID, $_POST['tagsort'], $_POST['order'])));

            break;

        case 'get_tags':
            authorize();

            header('Content-Type: application/json; charset=utf-8');
            include(SERVER_ROOT . '/sections/torrents/functions.php');

            $GroupID = $_REQUEST['groupid'];
            if (!is_number($GroupID) || !$GroupID) error(0, true);

            echo get_taglist_json($GroupID);

            break;

        case 'dupe_check':
            enforce_login();
            //authorize();

            if(!isset($_GET['id']) || !is_number($_GET['id'])) error(0);
            $GroupID = (int) $_GET['id'];

            require(SERVER_ROOT.'/classes/class_torrent.php');
            include(SERVER_ROOT . '/sections/upload/functions.php');

            $DB->query("SELECT tg.Name, tf.File, tg.TagList
                          FROM torrents AS t
                          JOIN torrents_group AS tg ON tg.ID=t.GroupID
                          JOIN torrents_files AS tf ON t.ID=tf.TorrentID
                         WHERE tg.ID='$GroupID'");

            list($DupeTitle, $Contents, $SearchTags) = $DB->next_record(MYSQLI_NUM, array(1));
            $Contents = unserialize(base64_decode($Contents));
            $Tor = new TORRENT($Contents, true); // New TORRENT object

            list($TotalSize, $FileList) = $Tor->file_list();

            $DupeResults  = check_size_dupes($FileList, $GroupID);
            $DupeResults['SearchTags'] = $SearchTags;
            $DupeResults['Title'] = $DupeTitle;
            $DupeResults['TotalSize'] = $TotalSize;

            include(SERVER_ROOT . '/sections/upload/display_dupes.php');

            break;

        case 'next':
            enforce_login();

            if(empty($_GET['id']) || !is_number($_GET['id'])) error(0);

            $DB->query("SELECT ID FROM torrents WHERE ID>'".$_GET['id']."' ORDER BY ID ASC LIMIT 1" );
            list($GroupID) = $DB->next_record();
            if(!$GroupID) error('Cannot find a next record after <a href="/torrents.php?id='.$_GET['id'].'">the torrent you came from</a>');

            header("Location: torrents.php?id=".$GroupID );
            break;

        case 'prev':
            enforce_login();

            if(empty($_GET['id']) || !is_number($_GET['id'])) error(0);

            $DB->query("SELECT ID FROM torrents WHERE ID<'".$_GET['id']."' ORDER BY ID DESC LIMIT 1" );
            list($GroupID) = $DB->next_record();
            if(!$GroupID) error('Cannot find a previous record to <a href="/torrents.php?id='.$_GET['id'].'">the torrent you came from</a>');

            header("Location: torrents.php?id=".$GroupID );
            break;

        case 'thank': // ajax
            enforce_login();
            authorize();
                  $GroupID = (int) $_POST['groupid'];

                  if ($GroupID) {
                      $Thanks = $Cache->get_value('torrent_thanks_'.$GroupID);
                      if ($Thanks === false) {
                          $Thanks = [];
                          $DB->query("SELECT Thanks FROM torrents WHERE GroupID = '$GroupID'");
                          list($Thanks['names']) = $DB->next_record();
                          $Thanks['count'] = count(explode(', ', $Thanks['names']));
                          $Cache->cache_value('torrent_thanks_'.$GroupID, $Thanks);
                      }
                      if (!$IsUploader && (!$Thanks || strpos($Thanks['names'], $LoggedUser['Username'])===false )) {
                          $DB->query("UPDATE torrents SET  Thanks=(IF(Thanks='','$LoggedUser[Username]',CONCAT_WS(', ',Thanks,'$LoggedUser[Username]'))) WHERE GroupID='$GroupID'");

                          $Cache->delete_value('torrent_thanks_'.$GroupID);
                          echo $LoggedUser[Username];
                      } else echo 'err_user';
                  } else echo 'err_group';
            break;

        case 'ajax_get_edit':
            // Page that switches edits for mods
            require(SERVER_ROOT.'/common/ajax_get_edit.php');
            break;

        case 'grouplog':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/grouplog.php');
            break;

        case 'editanon':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/editanon.php');
            break;

        case 'takeeditanon':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/takeeditanon.php');
            break;

        case 'editgroup':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/editgroup.php');
            break;

        case 'takeedit':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/takeedit.php');
            break;

        case 'newgroup':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/takenewgroup.php');
            break;

        case 'peerlist':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/peerlist.php');
            break;

        case 'snatchlist':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/snatchlist.php');
            break;

        case 'downloadlist':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/downloadlist.php');
            break;

        case 'redownload':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/redownload.php');
            break;

        case 'revert':
        case 'takegroupedit':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/takegroupedit.php');
            break;

        case 'nonwikiedit':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/nonwikiedit.php');
            break;

        case 'rename':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/rename.php');
            break;

        case 'delete':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/delete.php');
            break;

        case 'takedelete':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/takedelete.php');
            break;

        case 'masspm':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/masspm.php');
            break;

        case 'reseed':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/reseed.php');
            break;

        case 'takemasspm':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/takemasspm.php');
            break;

        case 'vote_tag':
            enforce_login();
            authorize();
            include(SERVER_ROOT.'/sections/torrents/vote_tag.php');
            break;

        case 'add_tag':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/add_tag.php');
            break;

        case 'delete_tag':
            enforce_login();
            authorize();
            include(SERVER_ROOT.'/sections/torrents/delete_tag.php');
            break;

        case 'tag_synonyms':
            //enforce_login();
            //include(SERVER_ROOT.'/sections/torrents/tag_synomyns.php');
            header('Location: tags.php');
            break;

        case 'notify':
            enforce_login();
            include(SERVER_ROOT.'/sections/torrents/notify.php');
            break;

        case 'notify_clear':
            enforce_login();
            authorize();
            if (!check_perms('site_torrents_notify')) {
                $DB->query("DELETE FROM users_notify_filters WHERE UserID='$LoggedUser[ID]'");
            }
            $DB->query("DELETE FROM users_notify_torrents WHERE UserID='$LoggedUser[ID]' AND UnRead='0'");
            $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
            header('Location: torrents.php?action=notify');
            break;

        case 'notify_cleargroup':
            enforce_login();
            authorize();
            if (!isset($_GET['filterid']) || !is_number($_GET['filterid'])) {
                error(0);
            }
            if (!check_perms('site_torrents_notify')) {
                $DB->query("DELETE FROM users_notify_filters WHERE UserID='$LoggedUser[ID]'");
            }
            $DB->query("DELETE FROM users_notify_torrents WHERE UserID='$LoggedUser[ID]' AND FilterID='$_GET[filterid]' AND UnRead='0'");
            $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
            header('Location: torrents.php?action=notify');
            break;

        case 'notify_clearitem':
            enforce_login();
            authorize();
            if (!isset($_GET['torrentid']) || !is_number($_GET['torrentid'])) {
                error(0);
            }
            if (!check_perms('site_torrents_notify')) {
                $DB->query("DELETE FROM users_notify_filters WHERE UserID='$LoggedUser[ID]'");
            }
            $DB->query("DELETE FROM users_notify_torrents WHERE UserID='$LoggedUser[ID]' AND TorrentID='$_GET[torrentid]'");
            $Cache->delete_value('notifications_new_'.$LoggedUser['ID']);
            break;

        case 'download':
            require(SERVER_ROOT.'/sections/torrents/download.php');
            break;

        case 'allcomments':

            require(SERVER_ROOT.'/sections/torrents/all_comments.php');
            break;

        case 'viewbbcode':
            require(SERVER_ROOT.'/sections/torrents/viewbbcode.php');
            break;

        case 'reply':
            enforce_login();
            authorize();

            if (!isset($_POST['groupid']) || !is_number($_POST['groupid'])) { // || empty($_POST['body'])
                error(0);
            }
            if (empty($_POST['body'])) {
                error('You cannot post a reply with no content.');
            }
            if ($LoggedUser['DisablePosting']) {
                error('Your posting rights have been removed.');
            }

           include(SERVER_ROOT.'/classes/class_text.php');
           $Text = new TEXT;
           $Text->validate_bbcode($_POST['body'],  get_permissions_advtags($LoggedUser['ID']));

           flood_check('torrents_comments');

           $GroupID = (int) $_POST['groupid'];
           if (!$GroupID) { error(404); }

           $DB->query("SELECT CEIL((SELECT COUNT(ID)+1 FROM torrents_comments AS tc WHERE tc.GroupID='".db_string($GroupID)."')/".TORRENT_COMMENTS_PER_PAGE.") AS Pages");
           list($Pages) = $DB->next_record();

           $DB->query("INSERT INTO torrents_comments (GroupID,AuthorID,AddedTime,Body) VALUES (
               '".db_string($GroupID)."', '".db_string($LoggedUser['ID'])."','".sqltime()."','".db_string($_POST['body'])."')");
           $PostID=$DB->inserted_id();

           $CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE*$Pages-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
           $Cache->begin_transaction('torrent_comments_'.$GroupID.'_catalogue_'.$CatalogueID);
           $Post = array(
                'ID'=>$PostID,
                'AuthorID'=>$LoggedUser['ID'],
                'AddedTime'=>sqltime(),
                'Body'=>$_POST['body'],
                'EditedUserID'=>0,
                'EditedTime'=>'0000-00-00 00:00:00',
                'Username'=>''
           );
           $Cache->insert('', $Post);
           $Cache->commit_transaction(0);
           $Cache->increment('torrent_comments_'.$GroupID);

           $DB->query("SELECT tg.Name, t.UserID, CommentsNotify
                         FROM users_info AS u
                    LEFT JOIN torrents AS t ON t.UserID=u.UserID
                    LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
                        WHERE t.GroupID='$GroupID'");
           list($TName, $UploaderID, $Notify)=$DB->next_record();
           // check whether system should pm uploader there is a new comment
           if( $Notify == 1 && $UploaderID!=$LoggedUser['ID'] )
           send_pm($UploaderID, 0, db_string("Comment received on your upload by {$LoggedUser['Username']}"),
           db_string("[br]You have received a comment from [url=/user.php?id={$LoggedUser['ID']}]{$LoggedUser['Username']}[/url] on your upload [url=/torrents.php?id=$GroupID&page=$Pages#post$PostID]{$TName}[/url][br][br][quote={$LoggedUser['Username']},t{$GroupID},{$PostID}]{$_POST['body']}[/quote]"));

            header('Location: torrents.php?id='.$GroupID.'&page='.$Pages."#post$PostID");
            break;

        case 'get_post':
            require(SERVER_ROOT.'/common/get_post.php');
            break;

        case 'takeedit_post':
            enforce_login();
            authorize();

            include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
            $Text = new TEXT;

            // Quick SQL injection check
            if (!$_POST['post'] || !is_number($_POST['post'])) { error(0); }

            // Mainly
            $DB->query("SELECT
                tc.Body,
                tc.AuthorID,
                tc.GroupID,
                tc.AddedTime,
                tc.EditedTime,
                tc.EditedUserID
                FROM torrents_comments AS tc
                WHERE tc.ID='".db_string($_POST['post'])."'");
            if ($DB->record_count()==0) { error(404); }
            list($OldBody, $AuthorID,$GroupID,$AddedTime,$EditedTime,$EditedUserID)=$DB->next_record();

            $DB->query("SELECT ceil(COUNT(ID) / ".TORRENT_COMMENTS_PER_PAGE.") AS Page FROM torrents_comments WHERE GroupID = $GroupID AND ID <= $_POST[post]");
            list($Page) = $DB->next_record();

            //if ($DB->record_count()==0) { error(404); }
            //if ($LoggedUser['ID']!=$AuthorID && !check_perms('site_moderate_forums')) { error(404); }

            validate_edit_comment($AuthorID, $EditedUserID, $AddedTime, $EditedTime);

            // Perform the update
            $DB->query("UPDATE torrents_comments SET
                Body = '".db_string($_POST['body'])."',
                EditedUserID = '".db_string($LoggedUser['ID'])."',
                EditedTime = '".sqltime()."'
                WHERE ID='".db_string($_POST['post'])."'");

            // Update the cache
            $CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE*$Page-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
            $Cache->delete('torrent_comments_'.$GroupID.'_catalogue_'.$CatalogueID);

/*            $Cache->begin_transaction('torrent_comments_'.$GroupID.'_catalogue_'.$CatalogueID);

            $Cache->update_row($_POST['key'], array(
                'ID'=>$_POST['post'],
                'AuthorID'=>$AuthorID,
                'AddedTime'=>$AddedTime,
                'Body'=>$_POST['body'],
                'EditedUserID'=>db_string($LoggedUser['ID']),
                'EditedTime'=>sqltime(),
                'Username'=>$LoggedUser['Username']
            ));
            $Cache->commit_transaction(0);
 */
            $Cache->delete('torrents_edits_'.$_POST['post']);
            $DB->query("INSERT INTO comments_edits (Page, PostID, EditUser, EditTime, Body)
                                    VALUES ('torrents', ".db_string($_POST['post']).", ".db_string($LoggedUser['ID']).", '".sqltime()."', '".db_string($OldBody)."')");

            // This gets sent to the browser, which echoes it in place of the old body
            //echo '<div class="post_content">'.$Text->full_format($_POST['body'], get_permissions_advtags($AuthorID)).'</div>';
?>
<div class="post_content">
    <?=$Text->full_format($_POST['body'], get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions']));?>
</div>
<div class="post_footer">
    <span class="editedby">Last edited by <a href="user.php?id=<?=$LoggedUser['ID']?>"><?=$LoggedUser['Username']?></a> just now</span>
</div>
<?php
                  break;

        case 'delete_post':
            enforce_login();
            authorize();

            // Quick SQL injection check
            if (!$_GET['postid'] || !is_number($_GET['postid'])) { error(0); }

            // Make sure they are moderators
            if (!check_perms('site_moderate_forums')) { error(403); }

            // Get topicid, forumid, number of pages
            $DB->query("SELECT DISTINCT
                GroupID,
                CEIL((SELECT COUNT(tc1.ID) FROM torrents_comments AS tc1 WHERE tc1.GroupID=tc.GroupID)/".TORRENT_COMMENTS_PER_PAGE.") AS Pages,
                CEIL((SELECT COUNT(tc2.ID) FROM torrents_comments AS tc2 WHERE tc2.ID<'".db_string($_GET['postid'])."')/".TORRENT_COMMENTS_PER_PAGE.") AS Page
                FROM torrents_comments AS tc
                WHERE tc.GroupID=(SELECT GroupID FROM torrents_comments WHERE ID='".db_string($_GET['postid'])."')");
            list($GroupID,$Pages,$Page)=$DB->next_record();

            // $Pages = number of pages in the thread
            // $Page = which page the post is on
            // These are set for cache clearing.

            $DB->query("DELETE FROM torrents_comments WHERE ID='".db_string($_GET['postid'])."'");

            //We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
            //$ThisCatalogue = floor((TORRENT_COMMENTS_PER_PAGE*$Page-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
            $LastCatalogue = floor((TORRENT_COMMENTS_PER_PAGE*$Pages-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
            for ($i=0;$i<=$LastCatalogue;$i++) {
                $Cache->delete('torrent_comments_'.$GroupID.'_catalogue_'.$i);
            }

            // Delete thread info cache (eg. number of pages)
            $Cache->delete('torrent_comments_'.$GroupID);

            break;
        case 'regen_filelist' :
            if (check_perms('users_mod') && !empty($_GET['torrentid']) && is_number($_GET['torrentid'])) {
                $TorrentID = $_GET['torrentid'];
                $DB->query("SELECT tg.ID,
                        tf.File
                    FROM torrents_files AS tf
                        JOIN torrents AS t ON t.ID=tf.TorrentID
                        JOIN torrents_group AS tg ON tg.ID=t.GroupID
                        WHERE tf.TorrentID = ".$TorrentID);
                if ($DB->record_count() > 0) {
                    require(SERVER_ROOT.'/classes/class_torrent.php');
                    list($GroupID, $Contents) = $DB->next_record(MYSQLI_NUM, false);
                    $Contents = unserialize(base64_decode($Contents));
                    $Tor = new TORRENT($Contents, true);
                    list($TotalSize, $FileList) = $Tor->file_list();
                    foreach ($FileList as $File) {
                        list($Size, $Name) = $File;
                        $TmpFileList []= $Name .'{{{'.$Size.'}}}'; // Name {{{Size}}}
                    }
                    $FilePath = $Tor->Val['info']->Val['files'] ? db_string($Tor->Val['info']->Val['name']) : "";
                    $FileString = db_string(implode('|||', $TmpFileList));
                    $DB->query("UPDATE torrents SET Size = ".$TotalSize.", FilePath = '".db_string($FilePath)."', FileList = '".db_string($FileString)."' WHERE ID = ".$TorrentID);
                    $Cache->delete_value('torrents_details_'.$GroupID);
                }
                header('Location: torrents.php?torrentid='.$TorrentID);
                die();
            } else {
                error(403);
            }
            break;
        case 'fix_group' :
            if (check_perms('users_mod') && authorize() && !empty($_GET['groupid']) && is_number($_GET['groupid'])) {
                $DB->query("SELECT COUNT(ID) FROM torrents WHERE GroupID = ".$_GET['groupid']);
                list($Count) = $DB->next_record();
                if ($Count == 0) {
                    delete_group($_GET['groupid']);
                } else {
                }
                                header('Location: torrents.php?id='.$_GET['groupid']);
            } else {
                error(403);
            }
            break;

   // ======================================================================
   // Review system (Marked for deletion)

        case 'get_review_message': // just a preview of the warning message that will be sent
            enforce_login();
            //authorize(); // who cares if somone fakes this

            include(SERVER_ROOT.'/sections/tools/managers/mfd_functions.php');
            include(SERVER_ROOT.'/classes/class_text.php');
            $Text = new TEXT;

            $GroupID = (int) $_REQUEST['groupid'];
            $ReasonID = (int) $_REQUEST['reasonid'];

            if (is_number($GroupID) && is_number($ReasonID)) {

                $DB->query("SELECT Name FROM torrents_group WHERE ID=$GroupID");
                list($Name) = $DB->next_record();

                if ($ReasonID > 0) { // if using a standard reason get standard message
                    $DB->query("SELECT Description FROM review_reasons WHERE ID = $ReasonID");
                    list($Description) = $DB->next_record();
                }
                $KillTime = get_warning_time();
                // return a preview of the warning message (the first part anyway, the last static part we can add in the page after the textarea used for other reason)
                echo $Text->full_format(get_warning_message(true, false, $GroupID, $Name, $Description, $KillTime), true);
            }
            break;

        case 'send_okay_message': // when an uploader wants to tell the staff they have fixed their upload
            enforce_login();      // this sets the status from Warned to Pending
            authorize();

            include(SERVER_ROOT.'/sections/tools/managers/mfd_functions.php');

            if (!empty($_POST['groupid']) && is_number($_POST['groupid'])) {

                $GroupID = (int) $_POST['groupid'];
                $DB->query("SELECT tg.Name,
                                        tr.Status,
                                        tr.KillTime,
                                        tr.ReasonID,
                                        tr.Reason,
                                        tr.ConvID,
                                        rr.Description
                                        FROM torrents_group AS tg
                                        LEFT JOIN torrents_reviews AS tr ON tr.GroupID = tg.ID
                                        LEFT JOIN review_reasons AS rr ON rr.ID = tr.ReasonID
                                        WHERE tg.ID=$GroupID
                                        ORDER BY tr.Time DESC
                                        LIMIT 1");

                list($Name, $Status, $KillTime, $ReasonID, $Reason, $ConvID, $Description) = $DB->next_record();

                if ($Status == 'Warned') { // if status != Warned then something fishy is going on (or its bugged)
                            // staff are going to see quite a few of these...

                    if ($ConvID>0) {
                                // if there is an existing conversation
                                $PMSetStatus = 'Unanswered';
                                $NewSubject =  "I have fixed my upload '$Name'";
                    } else {
                                // New conversation (does not compute!)
                                $Subject = "I have fixed my upload '$Name'";
                                $DB->query("INSERT INTO staff_pm_conversations
                                                 (Subject, Status, Level, UserID, Unread, Date)
                                          VALUES ('".db_string($Subject)."', 'Unanswered', 500, ".$LoggedUser['ID'].", 'false', '".sqltime()."')");
                                $NewSubject = null;
                                $PMSetStatus = false;
                                $ConvID = $DB->inserted_id();
                    }
                    if ($ConvID>0) { // send message to staff
                                $Message = get_user_okay_message($GroupID, $Name, $KillTime, $Description?$Description:$Reason);
                                send_message_reply($ConvID, 0, $LoggedUser['ID'], $Message, $PMSetStatus, false, $NewSubject, "false");

                                // create new review record
                                $DB->query("INSERT INTO torrents_reviews (GroupID, ReasonID, UserID, Time, ConvID, Status, Reason, KillTime)
                                         VALUES ($GroupID, $ReasonID, ".db_string($LoggedUser['ID']).", '".sqltime()."', '$ConvID', 'Pending', '".db_string($Reason)."', '".$KillTime."')");

                                echo $ConvID;
                    }
                }
                $Cache->delete_value('torrent_review_' . $GroupID);
            }

            break;

        case 'set_review_status':   // main functionality for staff marking an upload as Okay/Bad/Fixed/Rejected
            enforce_login();
            authorize();

                  include(SERVER_ROOT.'/sections/tools/managers/mfd_functions.php');
                  include(SERVER_ROOT . '/sections/torrents/functions.php');

                  if (check_perms('torrents_review') && !empty($_POST['groupid']) && is_number($_POST['groupid'])) {

                        $GroupID = (int) $_POST['groupid'];
                        $Review = get_last_review($GroupID);
                        if ($Review['ID'] != $_POST['ninja']) {
                            error("You've been ninja'd");
                            die();
                        }
                        $ReasonID = (int) $_POST['reasonid'];
                        $Time = sqltime();

                        // get the status we are setting this to
                        if ($_POST['submit'] == "Mark for Deletion") $Status = 'Warned';
                        elseif ($_POST['submit'] == "Reject Fix") $Status = 'Rejected';
                        elseif ($_POST['submit'] == "Accept Fix") $Status = 'Fixed';
                        else $Status = 'Okay';


                        $DB->query("SELECT Name, t.UserID, t.ID , tr.Status, tr.ConvID , tr.KillTime
                                    FROM torrents_group AS tg
                                    JOIN torrents AS t ON t.GroupID=tg.ID
                                    LEFT JOIN (
                                        SELECT GroupID, Max(Time) as LastTime
                                        FROM torrents_reviews
                                        GROUP BY GroupID
                                    ) AS x ON tg.ID= x.GroupID
                                    LEFT JOIN torrents_reviews AS tr ON tr.GroupID=x.GroupID AND tr.Time=x.LastTime
                                    WHERE tg.ID=$GroupID");

                        list($Name, $UserID, $TorrentID, $PreStatus, $ConvID, $PreKillTime) = $DB->next_record();

                        if (($PreStatus == 'Warned' && !check_perms('torrents_review_override')) ||
                            ($PreStatus == 'Pending' && ($Status == 'Okay' || $Status == 'Warned' ) && !check_perms('torrents_review_override'))) {
                            error(403);
                        }

                        $Reason = null;
                        switch ($Status) {
                            case 'Warned':
                                if ($ReasonID == 0) {
                                    $Reason = display_str($_POST['reason']);
                                    $LogDetails = "Reason: $Reason";
                                } else {
                                    $DB->query("SELECT Description FROM review_reasons WHERE ID = $ReasonID");
                                    list($Description) = $DB->next_record();
                                    $LogDetails = "Reason: $Description";
                                }
                                if (!$PreKillTime || $PreStatus == 'Okay')
                                    $KillTime = get_warning_time(); // 12 hours...  ?
                                else
                                    $KillTime = strtotime($PreKillTime);

                                $LogDetails .= ", Delete at: ".  date('M d Y, H:i', $KillTime);

                                $Message = get_warning_message(true, true, $GroupID, $Name, $Description?$Description:$Reason, $KillTime, false, display_str($_POST['msg_extra']) );

                                if (!$ConvID) {

                                    $DB->query("INSERT INTO staff_pm_conversations
                                             (Subject, Status, Level, UserID, Date, Unread)
                                        VALUES ('".db_string("Important: Your upload has been marked for deletion!")."', 'Resolved', '500', '$UserID', '$Time', true)");

                                    // New message
                                    $ConvID = $DB->inserted_id();

                                    $DB->query("INSERT INTO staff_pm_messages
                                             (UserID, SentDate, Message, ConvID)
                                        VALUES ('{$LoggedUser['ID']}', '$Time', '$Message', $ConvID)");

                                } else {
                                             // send message
                                    send_message_reply($ConvID, $UserID, $LoggedUser['ID'], $Message, 'Open');
                                }

                                break;
                            case 'Rejected':
                                // get the review status from the record before the current (pending) one
                                $DB->query("SELECT
                                                tr.Status,
                                                tr.KillTime,
                                                tr.ReasonID,
                                                tr.Reason,
                                                rr.Description
                                           FROM torrents_reviews AS tr
                                      LEFT JOIN review_reasons AS rr ON rr.ID = tr.ReasonID
                                          WHERE tr.GroupID=$GroupID
                                            AND tr.Status != 'Pending'
                                       ORDER BY tr.Time DESC
                                          LIMIT 1");
                                // overwrite these to be passed through to new record
                                list($Status, $KillTime, $ReasonID, $Reason, $Description) = $DB->next_record();
                                $KillTime = strtotime($KillTime);
                                $LogDetails = "Rejected Fix: ".$Description?$Description:$Reason;
                                $LogDetails .= " Delete at: ".  date('M d Y, H:i', $KillTime);

                                if ($ConvID) {
                                    send_message_reply($ConvID, $UserID, $LoggedUser['ID'],
                                        get_warning_message(true, true, $GroupID, $Name, $Description?$Description:$Reason, $KillTime, true), 'Open');
                                } else { // if no conv id then this has been rejected without the user sending a msg to staff
                                    send_pm($UserID, 0, db_string("Important: Your fix has been rejected!"),
                                                    get_warning_message(true, true, $GroupID, $Name, $Description?$Description:$Reason, $KillTime, true));
                                }
                                break;
                            case 'Fixed':
                                if ($ConvID) {
                                    // send message & resolve
                                    send_message_reply($ConvID, $UserID, $LoggedUser['ID'], get_fixed_message($GroupID, $Name), 'Resolved');
                                } else { // if no conv id then this has been fixed without the user sending a msg to staff
                                    send_pm($UserID, 0, db_string("Thank-you for fixing your upload"),
                                                    get_fixed_message($GroupID, $Name));
                                }
                                $Status="Okay";
                                $ReasonID = -1;
                                $KillTime ='0000-00-00 00:00:00';
                                $LogDetails = "Upload Fixed";
                                break;
                            default: // 'Okay'
                                if ($ConvID) {  // shouldnt normally be here but its not an impossible state... close the conversation
                                    // send message & resolve
                                    send_message_reply($ConvID, $UserID, $LoggedUser['ID'], get_fixed_message($GroupID, $Name), 'Resolved');
                                }  // if no convid then this has just been checked for the first time, no msg needed
                                $Status="Okay";
                                $ReasonID = -1;
                                $KillTime ='0000-00-00 00:00:00';
                                $LogDetails = "Marked as Okay";
                                break;
                        }

                        $DB->query("INSERT INTO torrents_reviews (GroupID, ReasonID, UserID, ConvID, Time, Status, Reason, KillTime)
                         VALUES ($GroupID, $ReasonID, ".db_string($LoggedUser['ID']).", ".($ConvID?$ConvID:"null").", '$Time', '$Status', '".db_string($Reason)."', '".sqltime($KillTime)."')");

                        $Cache->delete_value('torrent_review_' . $GroupID);
                        $Cache->delete_value('staff_pm_new_' . $UserID);

                        // logging -
                        write_log("Torrent $TorrentID ($Name) status set to $Status by ".$LoggedUser['Username']." ($LogDetails)"); // TODO: this is probably broken
                        write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "[b]Status:[/b] $Status $LogDetails", 0);

                        update_staff_checking("checked #$GroupID \"".cut_string($Name, 32).'"');

                        header('Location: torrents.php?id='.$GroupID."&checked=1");

                  } else {
                        error(403);
            }
            break;

        case 'change_status':  // a staff changing checking status
            enforce_login();
            authorize();

            if (!check_perms('torrents_review')) error(403);

            include(SERVER_ROOT . '/sections/torrents/functions.php');
            if ($_POST['remove']=='1') {
                $DB->query("UPDATE staff_checking SET IsChecking='0' WHERE UserID='$LoggedUser[ID]'");
                $Cache->delete_value('staff_checking');
                $Cache->delete_value('staff_lastchecked');
            } else {
                update_staff_checking('browsing torrents');
            }

            echo print_staff_status();

            break;

        case 'update_status':
            enforce_login();
            authorize();

            if (!check_perms('torrents_review')) error(403);
            include(SERVER_ROOT . '/sections/torrents/functions.php');

            echo print_staff_status();

            break;

        case 'output':
        case 'output_enc':
            enforce_login();
            //authorize();

            if (!check_perms('site_debug')) error(403);
            if(!isset($_GET['torrentid']) || !is_number($_GET['torrentid'])) error(0);
            $TorrentID = (int) $_GET['torrentid'];

            require(SERVER_ROOT.'/classes/class_torrent.php');

            $DB->query("SELECT File FROM torrents_files WHERE TorrentID='$TorrentID'");

            list($Contents) = $DB->next_record(MYSQLI_NUM, array(0));
            $Contents = unserialize(base64_decode($Contents));
            $Tor = new TORRENT($Contents, true); // New TORRENT object
            // Set torrent announce URL
            $Tor->set_announce_url(ANNOUNCE_URL.'/uSeRsToRrEntPaSs/announce');
            $Tor->set_comment('http'.($master->request->ssl ? 's' : '').'://'. SITE_URL."/torrents.php?torrentid=$TorrentID");

            // Remove multiple trackers from torrent
            unset($Tor->Val['announce-list']);
            // Remove web seeds (put here for old torrents not caught by previous commit
            unset($Tor->Val['url-list']);
            // Remove libtorrent resume info
            unset($Tor->Val['libtorrent_resume']);

            if ($_GET['action']=='output') {
                ksort($Tor->Val);
                error ( "<pre>". print_r($Tor->Val,true)."</pre>" );
            } else
                error ( "<pre>". print_r($Tor->enc(),true)."</pre>" );

            break;

        default:
            enforce_login();

            if (!empty($_GET['id'])) {
                include(SERVER_ROOT.'/sections/torrents/details.php');
            } elseif (isset($_GET['torrentid']) && is_number($_GET['torrentid'])) {
                $DB->query("SELECT GroupID FROM torrents WHERE ID=".$_GET['torrentid']);
                list($GroupID) = $DB->next_record();
                if ($GroupID) {
                    header("Location: torrents.php?id=".$GroupID."&torrentid=".$_GET['torrentid']);
                }
            } else {
                include(SERVER_ROOT.'/sections/torrents/browse.php');
            }
            break;
    }
} else {
    enforce_login();

    if (!empty($_GET['id'])) {
        include(SERVER_ROOT.'/sections/torrents/details.php');
    } elseif (isset($_GET['torrentid']) && is_number($_GET['torrentid'])) {
        $DB->query("SELECT GroupID FROM torrents WHERE ID=".$_GET['torrentid']);
        list($GroupID) = $DB->next_record();
        if ($GroupID) {
            header("Location: torrents.php?id=".$GroupID."&torrentid=".$_GET['torrentid']."#torrent".$_GET['torrentid']);
        } else {
            header("Location: log.php?search=Torrent+".$_GET['torrentid']);
        }
    } elseif (!empty($_GET['type'])) {
        include(SERVER_ROOT.'/sections/torrents/user.php');
    } elseif (!empty($_GET['groupname']) && !empty($_GET['forward'])) {
        $DB->query("SELECT ID FROM torrents_group WHERE Name LIKE '".db_string($_GET['groupname'])."'");
        list($GroupID) = $DB->next_record();
        if ($GroupID) {
            header("Location: torrents.php?id=".$GroupID);
        } else {
            include(SERVER_ROOT.'/sections/torrents/browse.php');
        }
    } else {
        include(SERVER_ROOT.'/sections/torrents/browse.php');
    }

}
