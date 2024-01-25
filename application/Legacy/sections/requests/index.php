<?php
enforce_login();
include(SERVER_ROOT.'/Legacy/sections/requests/functions.php');

$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::REQUEST);

if (!isset($_REQUEST['action'])) {
    include(SERVER_ROOT.'/Legacy/sections/requests/requests.php');
} else {
    switch ($_REQUEST['action']) {
        case 'ajax_get_edit':
            // Page that switches edits for mods
            require(SERVER_ROOT.'/common/ajax_get_edit.php');
            break;
        case 'new':
        case 'edit':
            include(SERVER_ROOT.'/Legacy/sections/requests/new_edit.php');
            break;
        case 'takevote':
            include(SERVER_ROOT.'/Legacy/sections/requests/takevote.php');
            break;
        case 'takefill':
            include(SERVER_ROOT.'/Legacy/sections/requests/takefill.php');
            break;
        case 'takenew':
        case 'takeedit':
            include(SERVER_ROOT.'/Legacy/sections/requests/takenew_edit.php');
            break;
        case 'delete':
        case 'unfill':
        case 'delete_vote':
            include(SERVER_ROOT.'/Legacy/sections/requests/interim.php');
            break;
        case 'takeunfill':
            include(SERVER_ROOT.'/Legacy/sections/requests/takeunfill.php');
            break;
        case 'takedelete':
            include(SERVER_ROOT.'/Legacy/sections/requests/takedelete.php');
            break;
        case 'takedelete_vote':
            include(SERVER_ROOT.'/Legacy/sections/requests/takedelete_vote.php');
            break;
        case 'view':
        case 'viewrequest':
            include(SERVER_ROOT.'/Legacy/sections/requests/request.php');
            break;
        case 'allcomments':
            require(SERVER_ROOT.'/Legacy/sections/requests/all_comments.php');
            break;
        case 'reply':
            authorize();
            enforce_login();

            if (!isset($_POST['requestid']) || !is_integer_string($_POST['requestid'])) {
                error(0);
            }

            if (empty($_POST['body'])) {
                error('You cannot post a comment with no content.');
            }

            $master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::POST);
            $master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::COMMENT);

            $bbCode = new \Luminance\Legacy\Text;
            $bbCode->validate_bbcode($_POST['body'],  get_permissions_advtags($activeUser['ID']));

            $RequestID = $_POST['requestid'];
            if (!$RequestID) { error(404); }

            flood_check('requests_comments');

            $Pages = ceil($master->db->rawQuery(
                "SELECT COUNT(ID) + 1 / ?
                   FROM requests_comments AS rc
                  WHERE rc.RequestID = ?",
                [TORRENT_COMMENTS_PER_PAGE, $RequestID]
            )->fetchColumn());

            $master->db->rawQuery(
                "INSERT INTO requests_comments (RequestID, AuthorID, AddedTime, Body)
                      VALUES (?, ?, ?, ?)",
                [$RequestID, $activeUser['ID'], sqltime(), $_POST['body']]
            );
            $PostID = $master->db->lastInsertID();

            $CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE*$Pages-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
            $master->cache->deleteValue('request_comments_'.$RequestID.'_catalogue_'.$CatalogueID);
            $master->cache->incrementValue('request_comments_'.$RequestID);

            // Comments notification
            $RequestInfos = $master->db->rawQuery(
                'SELECT r.UserID,
                        r.Title,
                        CommentsNotify
                   FROM requests AS r
              LEFT JOIN users_info AS u ON u.UserID = r.UserID
                  WHERE r.ID = ?',
                [$RequestID]
            )->fetch(\PDO::FETCH_ASSOC);
            if ($RequestInfos['UserID'] != $activeUser['ID'] && $RequestInfos['CommentsNotify']) {
                $ToID    = (int) $RequestInfos['UserID'];
                $FromID  = 0; // System
                $Author  = "[url=/user.php?id={$activeUser['ID']}]{$activeUser['Username']}[/url]";
                $Request = "[url=/requests.php?action=view&id={$RequestID}&postid={$PostID}#post{$PostID}]{$RequestInfos['Title']}[/url]";
                $Subject = "Comment received on your request by {$activeUser['Username']}";
                $Body    = "[br]You have received a comment from {$Author} on your request {$Request}[br][br]";
                $Body   .= "[quote={$activeUser['Username']},r{$RequestID},{$PostID}]{$_POST['body']}[/quote]";
                send_pm($RequestInfos['UserID'], $FromID, $Subject, $Body);
            }

            header("Location: /requests.php?action=view&id={$RequestID}&postid={$PostID}#post{$PostID}");
            break;

        case 'get_post':
            require(SERVER_ROOT.'/common/get_post.php');
            break;

        case 'takeedit_comment':
            include(SERVER_ROOT.'/Legacy/sections/requests/takeedit_comment.php');
            break;

        case 'add_comment':
            require(SERVER_ROOT.'/Legacy/sections/requests/add_comment.php');
            break;

        case 'trash_post':
            enforce_login();
            authorize();
            $RequestID = (int) $_GET['id'];
            $postID = (int) $_GET['postid'];

            if (!$_GET['postid'] || !is_integer_string($_GET['postid'])) { error(0); }
            $post = $this->master->repos->requestcomments->load($postID);
            if ($this->auth->isAllowed('request_post_trash')) {
                $post->setFlags(\Luminance\Entities\RequestComment::TRASHED);
                $this->master->repos->requestcomments->save($post);
                $master->irker->announcelab('Comment '.$postID.' has been trashed on request '.$groupID);
                $master->flasher->success("Post ".$postID." has been successfully trashed");
            }
            elseif (!($this->auth->isAllowed('request_post_trash'))) {
                $master->flasher->warning("You do not have this permission.");
            }
            header("Location: requests.php?action=view&id=".$RequestID);
        break;

        case 'restore_post':
            enforce_login();
            authorize();
            $RequestID = (int) $_GET['id'];
            $postID = (int) $_GET['postid'];
            if (!$_GET['postid'] || !is_integer_string($_GET['postid'])) { error(0); }
            if ($this->auth->isAllowed('torrent_post_edit')) {
                $post = $this->master->repos->requestcomments->load($postID);
                $post->unsetFlags(\Luminance\Entities\RequestComment::TRASHED);
                $this->master->repos->requestcomments->save($post);
                $master->irker->announcelab('Comment '.$postID.' has been restored on request '.$groupID);
                $master->flasher->success("Post ".$postID." has been successfully restored");
            }
            elseif (!($this->auth->isAllowed('torrent_post_edit'))) {
                $master->flasher->warning("You do not have this permission.");
            }
            header("Location: requests.php?action=view&id=".$RequestID);
            break;

        case 'delete_comment':
            enforce_login();
            authorize();

            // Quick SQL injection check
            if (!$_GET['postid'] || !is_integer_string($_GET['postid'])) { error(0); }

            // Make sure they are moderators forum_TODO
            if (!check_perms('forum_moderate')) { error(403); }

            $RequestID = $master->db->rawQuery(
                "SELECT RequestID
                   FROM requests_comments
                  WHERE ID = ?",
                [$_GET['postid']]
            )->fetchColumn();

            // Get threadid, forumid, number of pages
            $Page = ceil($master->db->rawQuery(
                'SELECT COUNT(ID) / ?
                   FROM requests_comments
                  WHERE RequestID = ?',
                [TORRENT_COMMENTS_PER_PAGE, $RequestID]
            )->fetchColumn());

            $Pages = ceil($master->db->rawQuery(
                'SELECT COUNT(ID) / ?
                   FROM requests_comments
                  WHERE RequestID = ?
                    AND ID < ?',
                [TORRENT_COMMENTS_PER_PAGE, $RequestID, $_GET['postid']]
            )->fetchColumn());

            // $Pages = number of pages in the thread
            // $Page = which page the post is on
            // These are set for cache clearing.
            $master->db->rawQuery(
                "DELETE
                   FROM requests_comments
                  WHERE ID = ?",
                [$_GET['postid']]
            );

            // We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
            $ThisCatalogue = floor((TORRENT_COMMENTS_PER_PAGE*$Page)/THREAD_CATALOGUE);
            $LastCatalogue = floor((TORRENT_COMMENTS_PER_PAGE*$Pages)/THREAD_CATALOGUE);
            for ($i=$ThisCatalogue; $i<=$LastCatalogue; $i++) {
                $master->cache->deleteValue('request_comments_'.$RequestID.'_catalogue_'.$i);
            }

            // Delete thread info cache (eg. number of pages)
            $master->cache->deleteValue('request_comments_'.$GroupID);
        break;

        case 'next':
            enforce_login();

            if (empty($_GET['id']) || !is_integer_string($_GET['id'])) error(0);

            $RequestID = $master->db->rawQuery(
                "SELECT ID
                   FROM requests
                  WHERE ID > ?
               ORDER BY ID ASC
                  LIMIT 1",
                [$_GET['id']]
            )->fetchColumn();
            if (!$RequestID) error('Cannot find a next record after <a href="/requests.php?action=view&id='.$_GET['id'].'">the request you came from</a>');

            header("Location: requests.php?action=view&id=".$RequestID);
            break;

        case 'prev':
            enforce_login();

            if (empty($_GET['id']) || !is_integer_string($_GET['id'])) error(0);

            $RequestID = $master->db->rawQuery(
                "SELECT ID
                   FROM requests
                  WHERE ID < ?
               ORDER BY ID DESC
                  LIMIT 1",
                [$_GET['id']]
            )->fetchColumn();
            if (!$RequestID) error('Cannot find a previous record to <a href="/requests.php?action=view&id='.$_GET['id'].'">the request you came from</a>');

            header("Location: requests.php?action=view&id=".$RequestID);
            break;

        default:
            error(0);
    }
}
