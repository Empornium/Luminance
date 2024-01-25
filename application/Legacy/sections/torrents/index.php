<?php
use Luminance\Entities\TorrentComment;
use Luminance\Entities\TorrentGroup;

use Luminance\Errors\NotFoundError;

# Function used for pagination of peer/snatch/download lists on torrents.php
function js_pages($Action, $torrentID, $NumResults, $CurrentPage)
{
    $NumPages = ceil($NumResults/100);
    $PageLinks = [];
    for ($i = 1; $i<=$NumPages; $i++) {
        if ($i == $CurrentPage) {
            $PageLinks[]=$i;
        } else {
            $PageLinks[]='<a href="#" onclick="'.$Action.'('.$torrentID.', '.$i.')">'.$i.'</a>';
        }
    }

    return implode(' | ', $PageLinks);
}

if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'event_award':
            enforce_login();
            include(SERVER_ROOT . '/Legacy/sections/torrents/event_award.php');
            break;

        case 'get_tags':
            enforce_login();
            authorize();

            header('Content-Type: application/json; charset=utf-8');
            include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');

            $GroupID = $_REQUEST['groupid'];
            if (!is_integer_string($GroupID) || !$GroupID) error(0, true);

            echo get_taglist_json($GroupID);

            break;

        case 'dupe_check':
            enforce_login();
            //authorize();

            if (!isset($_GET['id']) || !is_integer_string($_GET['id'])) error(0);
            $GroupID = (int) $_GET['id'];

            include(SERVER_ROOT.'/Legacy/sections/upload/functions.php');

            list($DupeTitle, $Contents, $SearchTags) = $master->db->rawQuery(
                "SELECT tg.Name,
                        tf.File,
                        tg.TagList
                   FROM torrents AS t
                   JOIN torrents_group AS tg ON tg.ID = t.GroupID
                   JOIN torrents_files AS tf ON t.ID = tf.TorrentID
                  WHERE tg.ID = ?",
                [$GroupID]
            )->fetch(\PDO::FETCH_NUM);
            $Contents = unserialize(base64_decode($Contents));
            $Tor = new Luminance\Legacy\Torrent($Contents, true); // new Torrent object

            list($TotalSize, $FileList) = $Tor->file_list();

            $dupeResults  = check_size_dupes($FileList, $GroupID);
            $dupeResults['SearchTags'] = $SearchTags;
            $dupeResults['Title'] = $DupeTitle;
            $dupeResults['TotalSize'] = $TotalSize;

            include(SERVER_ROOT . '/Legacy/sections/upload/display_dupes.php');

            break;

        case 'next':
            enforce_login();

            if (empty($_GET['id']) || !is_integer_string($_GET['id'])) error(0);

            $groupID = $master->db->rawQuery(
                "SELECT ID
                   FROM torrents_group
                  WHERE ID > ?
               ORDER BY ID ASC
                  LIMIT 1",
                [$_GET['id']]
            )->fetchColumn();
            if (!$groupID) error('Cannot find a next record after <a href="/torrents.php?id='.$_GET['id'].'">the torrent you came from</a>');

            header("Location: torrents.php?id={$groupID}");
            break;

        case 'prev':
            enforce_login();

            if (empty($_GET['id']) || !is_integer_string($_GET['id'])) error(0);

            $groupID = $master->db->rawQuery(
                "SELECT ID
                   FROM torrents_group
                  WHERE ID < ?
               ORDER BY ID DESC
                  LIMIT 1",
                [$_GET['id']]
            )->fetchColumn();
            if (!$groupID) error('Cannot find a previous record to <a href="/torrents.php?id='.$_GET['id'].'">the torrent you came from</a>');

            header("Location: torrents.php?id={$groupID}");
            break;

        case 'thank': // ajax
            enforce_login();
            authorize();
                  $groupID = (int) $_POST['groupid'];

                  if ($groupID) {
                      $group = $master->repos->torrentgroups->load($groupID);
                      if (!($group instanceof TorrentGroup)) {
                          error(404);
                      }
                      $thanks = $master->cache->getValue('torrent_thanks_'.$groupID);
                      if ($thanks === false) {
                          $thanks = [];
                          $thanks['names'] = $group->Thanks;
                          $thanks['count'] = count(explode(',', $thanks['names']));
                          $master->cache->cacheValue("torrent_thanks_{$groupID}", $thanks);
                      }
                      $IsUploader = $group->UserID == $activeUser['ID'];
                      if (!$IsUploader && (!$thanks || strpos($thanks['names'], $activeUser['Username']) === false)) {
                          $thanks = explode(',', $thanks['names']);
                          $thanks[] = $activeUser['Username'];
                          $thanks = implode(', ', array_filter($thanks));
                          $group->Thanks = $thanks;
                          $master->repos->torrentgroups->save($group);
                          $master->cache->deleteValue('torrent_thanks_'.$groupID);
                          echo $activeUser['Username'];
                      } else {
                          echo 'err_user';
                      }
                  } else {
                      echo 'err_group';
                  }
            break;

        case 'ajax_get_edit':
            // Page that switches edits for mods
            require(SERVER_ROOT.'/common/ajax_get_edit.php');
            break;

        case 'grouplog':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/grouplog.php');
            break;

        case 'editanon':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/editanon.php');
            break;

        case 'takeeditanon':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/takeeditanon.php');
            break;

        case 'editgroup':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/editgroup.php');
            break;

        case 'takeedit':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/takeedit.php');
            break;

        case 'newgroup':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/takenewgroup.php');
            break;

        case 'peerlist':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/peerlist.php');
            break;

        case 'snatchlist':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/snatchlist.php');
            break;

        case 'downloadlist':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/downloadlist.php');
            break;

        case 'redownload':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/redownload.php');
            break;

        case 'revert':
        case 'takegroupedit':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/takegroupedit.php');
            break;

        case 'revertedit':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/revert_edit.php');
            break;

        case 'nonwikiedit':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/nonwikiedit.php');
            break;

        case 'rename':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/rename.php');
            break;

        case 'delete':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/delete.php');
            break;

        case 'takedelete':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/takedelete.php');
            break;

        case 'masspm':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/masspm.php');
            break;

        case 'reseed':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/reseed.php');
            break;

        case 'takemasspm':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/takemasspm.php');
            break;

        case 'vote_tag':
            enforce_login();
            authorize();
            include(SERVER_ROOT.'/Legacy/sections/torrents/vote_tag.php');
            break;

        case 'quick_notify_tag':
            // Ability to quickly add tag from a torrent page tag section
            enforce_login();
            authorize();
            $GroupID = (int) $_POST['groupid'];
            $TagName = $_POST['tagname'];
            $Label = ('Quick added via torrents: ' . $TagName);
            $TagName = (string) '|'.$TagName.'|';
            $TagList = '';
            $NotTagList = '';
            $CategoryList = '';
            $master->db->rawQuery(
                "INSERT INTO users_notify_filters (UserID, Label, Tags, NotTags, Categories)
                      VALUES (?, ?, ?, '$NotTagList', '$CategoryList')",
                [$activeUser['ID'], $Label, $TagName]
            );
            break;

        case 'add_tag':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/add_tag.php');
            break;

        case 'delete_tag':
            enforce_login();
            authorize();
            include(SERVER_ROOT.'/Legacy/sections/torrents/delete_tag.php');
            break;

        case 'tags_synonyms':
            //enforce_login();
            //include(SERVER_ROOT.'/Legacy/sections/torrents/tags_synonyms.php');
            header('Location: tags.php');
            break;

        case 'notify':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/notify.php');
            break;

        case 'notify_clear':
            enforce_login();
            authorize();
            if (!check_perms('site_torrents_notify')) {
                $master->db->rawQuery(
                    "DELETE
                       FROM users_notify_filters
                      WHERE UserID = ?",
                    [$activeUser['ID']]
                );
            }
            $master->db->rawQuery(
                "DELETE
                   FROM users_notify_torrents
                  WHERE UserID = ?
                    AND UnRead = '0'",
                [$activeUser['ID']]
            );
            $master->cache->deleteValue('notifications_new_'.$activeUser['ID']);
            header('Location: torrents.php?action=notify');
            break;

        case 'notify_cleargroup':
            enforce_login();
            authorize();
            if (!isset($_GET['filterid']) || !is_integer_string($_GET['filterid'])) {
                error(0);
            }
            if (!check_perms('site_torrents_notify')) {
                $master->db->rawQuery(
                    "DELETE
                       FROM users_notify_filters
                      WHERE UserID = ?",
                    [$activeUser['ID']]
                );
            }
            $master->db->rawQuery(
                "DELETE
                   FROM users_notify_torrents
                  WHERE UserID = ?
                    AND FilterID = ?
                    AND UnRead = '0'",
                [$activeUser['ID'], $_GET['filterid']]
            );
            $master->cache->deleteValue('notifications_new_'.$activeUser['ID']);
            header('Location: torrents.php?action=notify');
            break;

        case 'notify_clearitem':
            enforce_login();
            authorize();
            if (!isset($_GET['torrentid']) || !is_integer_string($_GET['torrentid'])) {
                error(0);
            }
            if (!check_perms('site_torrents_notify')) {
                $master->db->rawQuery(
                    "DELETE
                       FROM users_notify_filters
                      WHERE UserID = ?",
                    [$activeUser['ID']]
                );
            }
            $master->db->rawQuery(
                "DELETE
                   FROM users_notify_torrents
                  WHERE UserID = ?
                    AND TorrentID = ?",
                [$activeUser['ID'], $_GET['torrentid']]
            );
            $master->cache->deleteValue('notifications_new_'.$activeUser['ID']);
            break;

        case 'download':
            require(SERVER_ROOT.'/Legacy/sections/torrents/download.php');
            break;

        case 'allcomments':
            enforce_login();
            require(SERVER_ROOT.'/Legacy/sections/torrents/all_comments.php');
            break;

        case 'viewbbcode':
            enforce_login();
            require(SERVER_ROOT.'/Legacy/sections/torrents/viewbbcode.php');
            break;

        case 'add_comment':
            enforce_login();
            require(SERVER_ROOT.'/Legacy/sections/torrents/add_comment.php');
            break;

        case 'get_post':
            enforce_login();
            require(SERVER_ROOT.'/common/get_post.php');
            break;

        case 'trash_post':
            enforce_login();
            authorize();
            $groupID = $this->request->getInt('id');
            $postID = $this->request->getInt('postid');

            if (!$_GET['postid'] || !is_integer_string($_GET['postid'])) { error(0); }
            //$master->flasher->notice("This was a successful test. PostID: " . $postID);
            $post = $this->master->repos->torrentcomments->load($postID);
            if ($this->auth->isAllowed('torrent_post_trash')) {
                $post->setFlags(TorrentComment::TRASHED);
                $this->master->repos->torrentcomments->save($post);
                $master->irker->announcelab('Comment '.$postID.' has been trashed in torrent group '.$groupID);
                $master->flasher->success("Post ".$postID." has been successfully trashed");
            }
            elseif (!($this->auth->isAllowed('torrent_post_trash'))) {
                $master->flasher->warning("You do not have this permission.");
            }
            header("Location: torrents.php?id=".$groupID);
            break;

        case 'restore_post':
            enforce_login();
            authorize();
            $groupID = $this->request->getInt('id');
            $postID = $this->request->getInt('postid');
            if (!$_GET['postid'] || !is_integer_string($_GET['postid'])) { error(0); }
            if ($this->auth->isAllowed('torrent_post_trash')) {
                $post = $this->master->repos->torrentcomments->load($postID);
                $post->unsetFlags(TorrentComment::TRASHED);
                $this->master->repos->torrentcomments->save($post);
                $master->irker->announcelab('Comment '.$postID.' has been restored in torrent group '.$groupID);
                $master->flasher->success("Post ".$postID." has been successfully restored");
            }
            elseif (!($this->auth->isAllowed('torrent_post_trash'))) {
                $master->flasher->warning("You do not have this permission.");
            }
            header("Location: torrents.php?id=".$groupID);
            break;

        case 'unset_pin':
            enforce_login();
            authorize();
            $groupID = $this->request->getInt('id');
            $postID = $this->request->getInt('postid');

            if (!is_integer_string($postID)) {
                throw new NotFoundError('', 'This post does not exist');
            }

            $this->auth->checkAllowed('torrent_comments_pin');

            if (!$_GET['postid'] || !is_integer_string($_GET['postid'])) { error(0); }
            $post = $this->master->repos->torrentcomments->load($postID);
            $post->unsetFlags(TorrentComment::PINNED);
            $this->master->repos->torrentcomments->save($post);

            header("Location: torrents.php?id=".$groupID."&postid=".$postID."#post".$postID);
            break;

        case 'set_pin':
            enforce_login();
            authorize();
            $groupID = $this->request->getInt('id');
            $postID = $this->request->getInt('postid');

            if (!is_integer_string($postID)) {
                throw new NotFoundError('', 'This post does not exist');
            }

            $this->auth->checkAllowed('torrent_comments_pin');

            if (!$_GET['postid'] || !is_integer_string($_GET['postid'])) { error(0); }
            $post = $this->master->repos->torrentcomments->load($postID);
            $post->setFlags(TorrentComment::PINNED);
            $this->master->repos->torrentcomments->save($post);

            header("Location: torrents.php?id=".$groupID."&postid=".$postID."#post".$postID);
            break;

        case 'delete_post':
            enforce_login();
            include(SERVER_ROOT.'/Legacy/sections/torrents/delete_comment.php');
            break;

        case 'regen_filelist' :
            enforce_login();

            if (check_perms('users_mod') && !empty($_GET['torrentid']) && is_integer_string($_GET['torrentid'])) {
                $torrentID = $_GET['torrentid'];
                list($GroupID, $Contents) = $master->db->rawQuery(
                    "SELECT tg.ID,
                            tf.File
                       FROM torrents_files AS tf
                       JOIN torrents AS t ON t.ID = tf.TorrentID
                       JOIN torrents_group AS tg ON tg.ID = t.GroupID
                      WHERE tf.TorrentID = ?",
                    [$torrentID]
                )->fetch(\PDO::FETCH_NUM);
                if ($master->db->foundRows() > 0) {
                    $Contents = unserialize(base64_decode($Contents));
                    $Tor = new Luminance\Legacy\Torrent($Contents, true);
                    list($TotalSize, $FileList) = $Tor->file_list();
                    foreach ($FileList as $File) {
                        list($Size, $Name) = $File;
                        $TmpFileList []= $Name .'{{{'.$Size.'}}}'; // Name {{{Size}}}
                    }
                    $FilePath = $Tor->Val['info']->Val['files'] ? $Tor->Val['info']->Val['name'] : "";
                    $FileString = implode('|||', $TmpFileList);
                    $master->db->rawQuery(
                        "UPDATE torrents
                            SET Size = ?,
                                FilePath = ?,
                                FileList = ?
                          WHERE ID = ?",
                        [$TotalSize, $FilePath, $FileString, $torrentID]
                    );
                    $master->cache->deleteValue('torrents_details_'.$GroupID);
                }
                header('Location: torrents.php?id='.$torrentID);
                die();
            } else {
                error(403);
            }
            break;
        case 'fix_group' :
            enforce_login();
            authorize();

            if (check_perms('users_mod') && authorize() && !empty($_GET['groupid']) && is_integer_string($_GET['groupid'])) {
                $count = $master->db->rawQuery(
                    "SELECT COUNT(ID)
                       FROM torrents
                      WHERE GroupID = ?",
                    [$_GET['groupid']]
                )->fetchColumn();
                if ($count == 0) {
                    delete_group($_GET['groupid']);
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

            include(SERVER_ROOT.'/Legacy/sections/tools/managers/mfd_functions.php');
            $bbCode = new \Luminance\Legacy\Text;

            $GroupID = (int) $_REQUEST['groupid'];
            $ReasonID = (int) $_REQUEST['reasonid'];

            if (is_integer_string($GroupID) && is_integer_string($ReasonID)) {

                $Name = $master->db->rawQuery(
                    "SELECT Name
                       FROM torrents_group
                      WHERE ID=?",
                    [$GroupID]
                )->fetchColumn();

                if ($ReasonID > 0) { // if using a standard reason get standard message
                    $Description = $master->db->rawQuery(
                        "SELECT Description
                           FROM review_reasons
                          WHERE ID = ?",
                        [$ReasonID]
                    )->fetchColumn();
                }
                $KillTime = get_warning_time();
                // return a preview of the warning message (the first part anyway, the last static part we can add in the page after the textarea used for other reason)
                echo $bbCode->full_format(get_warning_message(true, false, $GroupID, $Name, ($Description ?? ''), $KillTime), true);
            }
            break;

        case 'send_okay_message': // when an uploader wants to tell the staff they have fixed their upload
            enforce_login();      // this sets the status from Warned to Pending
            authorize();

            include(SERVER_ROOT.'/Legacy/sections/tools/managers/mfd_functions.php');

            if (!empty($_POST['groupid']) && is_integer_string($_POST['groupid'])) {

                $groupID = (int) $_POST['groupid'];
                list($Name, $Status, $KillTime, $ReasonID, $Reason, $ConvID, $Description) = $master->db->rawQuery(
                    "SELECT tg.Name,
                            tr.Status,
                            tr.KillTime,
                            tr.ReasonID,
                            tr.Reason,
                            tr.ConvID,
                            rr.Description
                       FROM torrents_group AS tg
                  LEFT JOIN torrents_reviews AS tr ON tr.GroupID = tg.ID
                  LEFT JOIN review_reasons AS rr ON rr.ID = tr.ReasonID
                      WHERE tg.ID = ?
                   ORDER BY tr.Time DESC
                      LIMIT 1",
                    [$groupID]
                )->fetch(\PDO::FETCH_NUM);

                if ($Status == 'Warned') { // if status != Warned then something fishy is going on (or its bugged)
                            // staff are going to see quite a few of these...

                    if ($ConvID>0) {
                                // if there is an existing conversation
                                $PMSetStatus = 'Unanswered';
                                $NewSubject =  "I have fixed my upload '$Name'";
                    } else {
                                // New conversation (does not compute!)
                                $Subject = "I have fixed my upload '$Name'";
                                $master->db->rawQuery(
                                    "INSERT INTO staff_pm_conversations (Subject, Status, Level, UserID, Unread, Date)
                                          VALUES (?, 'Unanswered', ?, ?, false, ?)",
                                    [$Subject, 500, $activeUser['ID'], sqltime()]
                                );
                                $NewSubject = null;
                                $PMSetStatus = false;
                                $ConvID = $master->db->lastInsertID();
                    }
                    if ($ConvID>0) { // send message to staff
                        $Message = get_user_okay_message($groupID, $KillTime, $Description?$Description:$Reason);
                        send_message_reply($ConvID, 0, $activeUser['ID'], $Message, $PMSetStatus, false, $NewSubject, "false");

                        // create new review record
                        $master->db->rawQuery(
                            "INSERT INTO torrents_reviews (GroupID, ReasonID, UserID, Time, ConvID, Status, Reason, KillTime)
                                  VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)",
                            [$groupID, $ReasonID, $activeUser['ID'], sqltime(), $ConvID, $Reason, $KillTime]
                        );

                        echo $ConvID;
                    }
                }
                $master->cache->deleteValue('torrent_review_' . $groupID);
            }

            break;

        case 'set_review_status':   // main functionality for staff marking an upload as Okay/Bad/Fixed/Rejected
            enforce_login();
            authorize();

            include(SERVER_ROOT.'/Legacy/sections/tools/managers/mfd_functions.php');
            include(SERVER_ROOT.'/Legacy/sections/torrents/functions.php');

            if (check_perms('torrent_review') && !empty($_POST['groupid']) && is_integer_string($_POST['groupid'])) {

                  $GroupID = (int) $_POST['groupid'];
                  $Review = get_last_review($GroupID);
                  if ($Review['ID'] != $_POST['ninja']) {
                      error("You've been ninja'd");
                      die();
                  }
                  $ReasonID = (int) $_POST['reasonid'];
                  $time = sqltime();

                  // get the status we are setting this to
                  if ($_POST['submit'] == "Mark for Deletion") $Status = 'Warned';
                  elseif ($_POST['submit'] == "Reject Fix") $Status = 'Rejected';
                  elseif ($_POST['submit'] == "Accept Fix") $Status = 'Fixed';
                  else $Status = 'Okay';

                  $nextRecord = $master->db->rawQuery(
                      "SELECT tg.Name,
                              t.UserID,
                              t.ID,
                              tr.Status,
                              tr.ConvID,
                              tr.KillTime
                         FROM torrents_group AS tg
                         JOIN torrents AS t ON t.GroupID = tg.ID
                    LEFT JOIN (
                              SELECT GroupID,
                                     Max(Time) as LastTime
                                FROM torrents_reviews
                            GROUP BY GroupID
                              ) AS x ON tg.ID = x.GroupID
                    LEFT JOIN torrents_reviews AS tr
                           ON tr.GroupID = x.GroupID
                          AND tr.Time = x.LastTime
                        WHERE tg.ID = ?",
                        [$GroupID]
                  )->fetch(\PDO::FETCH_NUM);

                  list($Name, $userID, $torrentID, $PreStatus, $ConvID, $PreKillTime) = $nextRecord;

                  if (($PreStatus == 'Warned' && !check_perms('torrent_review_override')) ||
                      ($PreStatus == 'Pending' && ($Status == 'Okay' || $Status == 'Warned') && !check_perms('torrent_review_override'))) {
                      error(403);
                  }

                  $Reason = null;
                  switch ($Status) {
                      case 'Warned':
                          $Reason = '';
                          if ($ReasonID == 0) {
                              $Reason = $_POST['reason'] ?? '';
                          } else {
                              $Reason = $master->db->rawQuery(
                                  "SELECT Description
                                     FROM review_reasons
                                    WHERE ID = ?",
                                  [$ReasonID]
                              )->fetchColumn();
                          }
                          $LogDetails = "Reason: {$Reason}";
                          if (!$PreKillTime || $PreStatus == 'Okay') {
                              $KillTime = get_warning_time(); // 12 hours...  ?
                          } else {
                              $KillTime = strtotime($PreKillTime);
                          }

                          $LogDetails .= ", Delete at: ".  date('M d Y, H:i', $KillTime);

                          $Message = get_warning_message(true, true, $GroupID, $Name, $Reason, $KillTime, false, display_str($_POST['msg_extra'] ?? ''));

                          if (!$ConvID) {

                              $master->db->rawQuery(
                                  "INSERT INTO staff_pm_conversations (Subject, Status, Level, UserID, Date, Unread)
                                        VALUES ('Important: Your upload has been marked for deletion!', 'Resolved', ?, ?, ?, true)",
                                  [500, $userID, $time]
                              );

                              // New message
                              $ConvID = $master->db->lastInsertID();

                              $master->db->rawQuery(
                                  "INSERT INTO staff_pm_messages (UserID, SentDate, Message, ConvID)
                                        VALUES (?, ?, ?, ?)",
                                  [$activeUser['ID'], $time, $Message, $ConvID]
                              );

                          } else {
                                       // send message
                              send_message_reply($ConvID, $userID, $activeUser['ID'], $Message, 'Open');
                          }

                          break;
                      case 'Rejected':
                          // get the review status from the record before the current (pending) one
                          list($Status, $KillTime, $ReasonID, $Reason, $Description) = $master->db->rawQuery(
                              "SELECT tr.Status,
                                      tr.KillTime,
                                      tr.ReasonID,
                                      tr.Reason,
                                      rr.Description
                                 FROM torrents_reviews AS tr
                            LEFT JOIN review_reasons AS rr ON rr.ID = tr.ReasonID
                                WHERE tr.GroupID = ?
                                  AND tr.Status != 'Pending'
                             ORDER BY tr.Time DESC
                                LIMIT 1",
                              [$GroupID]
                          )->fetch(\PDO::FETCH_NUM);
                          // overwrite these to be passed through to new record
                          $KillTime = strtotime($KillTime);
                          $LogDetails = "Rejected Fix: ".$Description?$Description:$Reason;
                          $LogDetails .= " Delete at: ".  date('M d Y, H:i', $KillTime);

                          if ($ConvID) {
                              send_message_reply($ConvID, $userID, $activeUser['ID'],
                                  get_warning_message(true, true, $GroupID, $Name, $Description?$Description:$Reason, $KillTime, true), 'Open');
                          } else { // if no conv id then this has been rejected without the user sending a msg to staff
                              send_pm($userID, 0, "Important: Your fix has been rejected!",
                                  get_warning_message(true, true, $GroupID, $Name, $Description?$Description:$Reason, $KillTime, true));
                          }
                          break;
                      case 'Fixed':
                          if ($ConvID) {
                              // send message & resolve
                              send_message_reply($ConvID, $userID, $activeUser['ID'], get_fixed_message($GroupID), 'Resolved');
                          } else { // if no conv id then this has been fixed without the user sending a msg to staff
                              send_pm($userID, 0, "Thank you for fixing your upload",
                                              get_fixed_message($GroupID));
                          }
                          $Status="Okay";
                          $ReasonID = -1;
                          $KillTime ='0000-00-00 00:00:00';
                          $LogDetails = "Upload Fixed";
                          break;
                      default: // 'Okay'
                          if ($ConvID) {  // shouldnt normally be here but its not an impossible state... close the conversation
                              // send message & resolve
                              send_message_reply($ConvID, $userID, $activeUser['ID'], get_fixed_message($GroupID), 'Resolved');
                          }  // if no convid then this has just been checked for the first time, no msg needed
                          $Status="Okay";
                          $ReasonID = -1;
                          $KillTime ='0000-00-00 00:00:00';
                          $LogDetails = "Marked as Okay";
                          break;
                  }

                  // Ensure $KillTime is formatted for SQL
                  $KillTime = sqltime($KillTime);
                  $master->db->rawQuery(
                      "INSERT INTO torrents_reviews (GroupID, ReasonID, UserID, ConvID, Time, Status, Reason, KillTime)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                      [$GroupID, $ReasonID, $activeUser['ID'], $ConvID ? $ConvID : 'null', $time, $Status, $Reason, $KillTime]
                  );

                  // if okayed check if this torrent gets the ducky award
                  if ($Status=="Okay") award_ducky_check($userID, $torrentID);

                  $master->cache->deleteValue('torrent_review_' . $GroupID);
                  $master->cache->deleteValue('staff_pm_new_' . $userID);

                  // logging -
                  write_log("Torrent $torrentID ($Name) status set to $Status by ".$activeUser['Username']." ($LogDetails)"); // TODO: this is probably broken
                  write_group_log($GroupID, $torrentID, $activeUser['ID'], "[b]Status:[/b] $Status $LogDetails", 0);

                  update_staff_checking("checked #$GroupID \"".cut_string($Name, 32).'"');

                  header('Location: torrents.php?id='.$GroupID."&checked=1");

            } else {
                  error(403);
            }
            break;

        case 'change_status':  // a staff changing checking status
            enforce_login();
            authorize();

            if (!check_perms('torrent_review')) error(403);

            include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');
            if (($_POST['remove'] ?? null) == '1') {
                $master->db->rawQuery(
                    "UPDATE staff_checking
                        SET IsChecking = '0'
                      WHERE UserID = ?",
                    [$activeUser['ID']]
                );
                $master->cache->deleteValue('staff_checking');
                $master->cache->deleteValue('staff_lastchecked');
            } else {
                update_staff_checking('browsing torrents');
            }

            echo print_staff_status();

            break;

        case 'update_status':
            enforce_login();
            authorize();

            if (!check_perms('torrent_review')) error(403);
            include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');

            echo print_staff_status();

            break;

        case 'output':
        case 'output_enc':
            enforce_login();
            //authorize();

            if (!check_perms('site_debug')) error(403);
            if (!isset($_GET['torrentid']) || !is_integer_string($_GET['torrentid'])) error(0);
            $torrentID = (int) $_GET['torrentid'];

            $Tor = getTorrentFile($torrentID, 'uSeRsToRrEntPaSs');

            $params = [];
            if ($_GET['action']=='output') {
                ksort($Tor->Val);
                $params['data'] = print_r($Tor->Val, true);
            } else {
                $params['data'] = print_r($Tor->enc(), true);
            }
            echo $master->render->template('@Legacy/torrents/data.html.twig', $params);
            break;

        case 'clear_browse':
            enforce_login();
            update_last_browse($activeUser['ID'], sqltime());
            header('Location: torrents.php');
            break;

        default:
            enforce_login();

            if (!empty($_GET['id'])) {
                include(SERVER_ROOT.'/Legacy/sections/torrents/details.php');
            } elseif (isset($_GET['torrentid']) && is_integer_string($_GET['torrentid'])) {
                $groupID = $master->db->rawQuery(
                    "SELECT GroupID
                       FROM torrents
                      WHERE ID = ?",
                    [$_GET['torrentid']]
                )->fetchColumn();
                if ($groupID) {
                    header("Location: torrents.php?id={$groupID}&torrentid={$_GET['torrentid']}");
                }
            } else {
                include(SERVER_ROOT.'/Legacy/sections/torrents/browse.php');
            }
            break;
    }
} else {
    enforce_login();

    if (!empty($_GET['id'])) {
        include(SERVER_ROOT.'/Legacy/sections/torrents/details.php');
    } elseif (isset($_GET['torrentid']) && is_integer_string($_GET['torrentid'])) {
        $groupID = $master->db->rawQuery(
            "SELECT GroupID
               FROM torrents
              WHERE ID = ?",
            [$_GET['torrentid']]
        )->fetchColumn();
        if ($groupID) {
            header("Location: torrents.php?id={$groupID}&torrentid={$_GET['torrentid']}#torrent{$_GET['torrentid']}");
        } else {
            header("Location: log.php?search=Torrent+{$_GET['torrentid']}");
        }
    } elseif (!empty($_GET['type'])) {
        include(SERVER_ROOT.'/Legacy/sections/torrents/user.php');
    } elseif (!empty($_GET['groupname']) && !empty($_GET['forward'])) {
        $groupID = $master->db->rawQuery(
            "SELECT ID
               FROM torrents_group
              WHERE Name LIKE ?",
            [$_GET['groupname']]
        )->fetchColumn();
        if ($groupID) {
            header("Location: torrents.php?id={$groupID}");
        } else {
            include(SERVER_ROOT.'/Legacy/sections/torrents/browse.php');
        }
    } else {
        include(SERVER_ROOT.'/Legacy/sections/torrents/browse.php');
    }
}
