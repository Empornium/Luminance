<?php
enforce_login();
include(SERVER_ROOT.'/Legacy/sections/requests/functions.php');

$master->repos->restrictions->check_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::REQUEST);

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

            if (!isset($_POST['requestid']) || !is_number($_POST['requestid'])) {
                error(0);
            }

            if (empty($_POST['body'])) {
                error('You cannot post a comment with no content.');
            }

            $master->repos->restrictions->check_restricted($LoggedUser['ID'], Luminance\Entities\Restriction::POST);

            $Text = new Luminance\Legacy\Text;
            $Text->validate_bbcode($_POST['body'],  get_permissions_advtags($LoggedUser['ID']));

            $RequestID = $_POST['requestid'];
            if (!$RequestID) { error(404); }

            flood_check('requests_comments');

            $DB->query("SELECT CEIL((SELECT COUNT(ID)+1 FROM requests_comments AS rc WHERE rc.RequestID='".$RequestID."')/".TORRENT_COMMENTS_PER_PAGE.") AS Pages");
            list($Pages) = $DB->next_record();

            $DB->query("INSERT INTO requests_comments (RequestID,AuthorID,AddedTime,Body) VALUES (
                '".$RequestID."', '".db_string($LoggedUser['ID'])."','".sqltime()."','".db_string($_POST['body'])."')");
            $PostID=$DB->inserted_id();

            $CatalogueID = floor((TORRENT_COMMENTS_PER_PAGE*$Pages-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
            $Cache->begin_transaction('request_comments_'.$RequestID.'_catalogue_'.$CatalogueID);
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
            $Cache->increment('request_comments_'.$RequestID);

            // Comments notification
            $Sql          = 'SELECT r.UserID, r.Title, CommentsNotify FROM requests AS r LEFT JOIN users_info AS u ON u.UserID = r.UserID WHERE r.ID = :ID';
            $Prepare      = [':ID' => $RequestID];
            $RequestInfos = $master->db->raw_query($Sql, $Prepare)->fetch(\PDO::FETCH_ASSOC);
            if ($RequestInfos['UserID'] != $LoggedUser['ID'] && $RequestInfos['CommentsNotify']) {
                $ToID    = (int) $RequestInfos['UserID'];
                $FromID  = 0; // System
                $Author  = "[url=/user.php?id={$LoggedUser['ID']}]{$LoggedUser['Username']}[/url]";
                $Request = "[url=/requests.php?action=view&id={$RequestID}&page={$Pages}#post{$PostID}]{$RequestInfos['Title']}[/url]";
                $Subject = "Comment received on your request by {$LoggedUser['Username']}";
                $Body    = "[br]You have received a comment from {$Author} on your request {$Request}[br][br]";
                $Body   .= "[quote={$LoggedUser['Username']},r{$RequestID},{$PostID}]{$_POST['body']}[/quote]";
                send_pm($RequestInfos['UserID'], $FromID, db_string($Subject), db_string($Body));
            }

            header('Location: requests.php?action=view&id='.$RequestID.'&page='.$Pages."#post$PostID");
            break;

        case 'get_post':
            require(SERVER_ROOT.'/common/get_post.php');
            break;

        case 'takeedit_comment':
            include(SERVER_ROOT.'/Legacy/sections/requests/takeedit_comment.php');
            break;

        case 'delete_comment':
            enforce_login();
            authorize();

            // Quick SQL injection check
            if (!$_GET['postid'] || !is_number($_GET['postid'])) { error(0); }

            // Make sure they are moderators
            if (!check_perms('site_moderate_forums')) { error(403); }

            // Get topicid, forumid, number of pages
            $DB->query("SELECT DISTINCT
                RequestID,
                CEIL((SELECT COUNT(rc1.ID) FROM requests_comments AS rc1 WHERE rc1.RequestID=rc.RequestID)/".TORRENT_COMMENTS_PER_PAGE.") AS Pages,
                CEIL((SELECT COUNT(rc2.ID) FROM requests_comments AS rc2 WHERE rc2.ID<'".db_string($_GET['postid'])."')/".TORRENT_COMMENTS_PER_PAGE.") AS Page
                FROM requests_comments AS rc
                WHERE rc.RequestID=(SELECT RequestID FROM requests_comments WHERE ID='".db_string($_GET['postid'])."')");
            list($RequestID,$Pages,$Page)=$DB->next_record();

            // $Pages = number of pages in the thread
            // $Page = which page the post is on
            // These are set for cache clearing.

            $DB->query("DELETE FROM requests_comments WHERE ID='".db_string($_GET['postid'])."'");

            //We need to clear all subsequential catalogues as they've all been bumped with the absence of this post
            $ThisCatalogue = floor((TORRENT_COMMENTS_PER_PAGE*$Page-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
            $LastCatalogue = floor((TORRENT_COMMENTS_PER_PAGE*$Pages-TORRENT_COMMENTS_PER_PAGE)/THREAD_CATALOGUE);
            for ($i=$ThisCatalogue;$i<=$LastCatalogue;$i++) {
                $Cache->delete('request_comments_'.$RequestID.'_catalogue_'.$i);
            }

            // Delete thread info cache (eg. number of pages)
            $Cache->delete('request_comments_'.$GroupID);
        break;

        case 'next':
            enforce_login();

            if(empty($_GET['id']) || !is_number($_GET['id'])) error(0);

            $DB->query("SELECT ID FROM requests WHERE ID>'".$_GET['id']."' ORDER BY ID ASC LIMIT 1" );
            list($RequestID) = $DB->next_record();
            if(!$RequestID) error('Cannot find a next record after <a href="/requests.php?action=view&id='.$_GET['id'].'">the request you came from</a>');

            header("Location: requests.php?action=view&id=".$RequestID);
            break;

        case 'prev':
            enforce_login();

            if(empty($_GET['id']) || !is_number($_GET['id'])) error(0);

            $DB->query("SELECT ID FROM requests WHERE ID<'".$_GET['id']."' ORDER BY ID DESC LIMIT 1" );
            list($RequestID) = $DB->next_record();
            if(!$RequestID) error('Cannot find a previous record to <a href="/requests.php?action=view&id='.$_GET['id'].'">the request you came from</a>');

            header("Location: requests.php?action=view&id=".$RequestID);
            break;

        default:
            error(0);
    }
}
