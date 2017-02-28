<?php
include(SERVER_ROOT.'/sections/reports/array.php');

if (empty($_GET['type']) || empty($_GET['id']) || !is_number($_GET['id'])) {
    error(0);
}

switch($_GET['type']) {
    case 'posthistory' :
        $Short = 'post';
        break;

    case 'torrents_commenthistory' :
        $Short = 'torrents_comment';
        break;

    default :
        $Short = $_GET['type'];
        break;
}

if (!array_key_exists($Short, $Types)) {
    error(403);
}
$Type = $Types[$Short];

$ID = $_GET['id'];

switch ($Short) {
    case "user" :
        $DB->query("SELECT Username FROM users_main WHERE ID=".$ID);
        if ($DB->record_count() < 1) {
            error(404);
        }
        list($Username) = $DB->next_record();
        break;

    case "request" :
        $DB->query("SELECT Title, Description, TorrentID, UserID FROM requests WHERE ID=".$ID);
        if ($DB->record_count() < 1) {
            error(404);
        }
        list($Name, $Desc, $Filled, $AuthorID) = $DB->next_record();
        break;

    case "collage" :
        $DB->query("SELECT Name, Description, UserID FROM collages WHERE ID=".$ID);
        if ($DB->record_count() < 1) {
            error(404);
        }
        list($Name, $Desc, $AuthorID) = $DB->next_record();
        break;

    case "thread" :
        $DB->query("SELECT ft.Title, ft.ForumID, um.Username, um.ID FROM forums_topics AS ft JOIN users_main AS um ON um.ID=ft.AuthorID WHERE ft.ID=".$ID);
        if ($DB->record_count() < 1) {
            error(404);
        }
        list($Title, $ForumID, $Username, $AuthorID) = $DB->next_record();
        $DB->query("SELECT MinClassRead FROM forums WHERE ID = ".$ForumID);
        list($MinClassRead) = $DB->next_record();
        if(!empty($LoggedUser['DisableForums']) ||
                ($MinClassRead > $LoggedUser['Class'] && (!isset($LoggedUser['CustomForums'][$ForumID]) || $LoggedUser['CustomForums'][$ForumID] == 0)) ||
                (isset($LoggedUser['CustomForums'][$ForumID]) && $LoggedUser['CustomForums'][$ForumID] == 0)) {
            error(403);
        }
        break;

    case "post" :
        $DB->query("SELECT fp.Body, fp.TopicID, um.Username, um.ID FROM forums_posts AS fp JOIN users_main AS um ON um.ID=fp.AuthorID WHERE fp.ID=".$ID);
        if ($DB->record_count() < 1) {
            error(404);
        }
        list($Body, $TopicID, $Username, $AuthorID) = $DB->next_record();
        $DB->query("SELECT ForumID FROM forums_topics WHERE ID = ".$TopicID);
        list($ForumID) = $DB->next_record();
        $DB->query("SELECT MinClassRead FROM forums WHERE ID = ".$ForumID);
        list($MinClassRead) = $DB->next_record();
        if(!empty($LoggedUser['DisableForums']) ||
                ($MinClassRead > $LoggedUser['Class'] && (!isset($LoggedUser['CustomForums'][$ForumID]) || $LoggedUser['CustomForums'][$ForumID] == 0)) ||
                (isset($LoggedUser['CustomForums'][$ForumID]) && $LoggedUser['CustomForums'][$ForumID] == 0)) {
            error(403);
        }
        break;

    case "requests_comment" :
    case "torrents_comment" :
    case "collages_comment" :
        $Table = $Short.'s';
        if ($Short == "collages_comment") {
            $Column = "UserID";
        } else {
            $Column = "AuthorID";
        }
        $DB->query("SELECT ".$Short.".Body, um.Username, um.ID FROM ".$Table." AS ".$Short." JOIN users_main AS um ON um.ID=".$Short.".".$Column." WHERE ".$Short.".ID=".$ID);
        if ($DB->record_count() < 1) {
            error(404);
        }
        list($Body, $Username, $AuthorID) = $DB->next_record();
        break;
}

show_header('Report a '.$Type['title'],'bbcode');
?>
<div class="thin">
    <h2>Report <?=$Type['title']?></h2>
    <div class="head">Reporting guidelines</div>
    <div class="box pad">
        <p>Following these guidelines will help the moderators deal with your report in a timely fashion. </p>
        <ul>
<?php
foreach ($Type['guidelines'] as $Guideline) {
?>
            <li><?=$Guideline?></li>
<?php  } ?>
        </ul>
        <p>In short, please include as much detail as possible when reporting. Thank you. </p>
    </div>
<?php

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class
$Text = new TEXT;

switch ($Short) {
    case "user" :
?>
    <p>You are reporting the user <strong><?=display_str($Username)?></strong></p>
<?php
        break;
    case "request_update" :
?>
    <div class="head">You are reporting the request:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Title</td>
            <td>Description</td>
            <td width="20">Filled?</td>
        </tr>
        <tr>
            <td><?=display_str($Name)?></td>
            <td><?=$Text->full_format($Desc, get_permissions_advtags($AuthorID))?></td>
            <td><strong><?=($Filled == 0 ? 'No' : 'Yes')?></strong></td>
        </tr>
    </table>
    <br />

    <div class="box pad center">
        <p><strong>It will greatly increase the turnover rate of the updates if you can fill in as much of the following details in as possible</strong></p>
        <form action="" method="post">
            <input type="hidden" name="action" value="takereport" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="type" value="<?=$Short?>" />
            <table>
                <tr>
                    <td class="label">Comment</td>
                    <td>
                        <textarea rows="8" cols="80" name="comment"></textarea>
                    </td>
                </tr>
            </table>
            <br />
            <br />
            <input type="submit" value="Submit report" />
        </form>
    </div>
<?php
        break;
    case "request" :
?>
    <div class="head">You are reporting the request:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Title</td>
            <td>Description</td>
            <td width="20">Filled?</td>
        </tr>
        <tr>
            <td><?=display_str($Name)?></td>
            <td><?=$Text->full_format($Desc, get_permissions_advtags($AuthorID))?></td>
            <td><strong><?=($Filled == 0 ? 'No' : 'Yes')?></strong></td>
        </tr>
    </table>
<?php
        break;
    case "collage" :
?>
    <div class="head">You are reporting the collage:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Title</td>
            <td>Description</td>
        </tr>
        <tr>
            <td><?=display_str($Name)?></td>
            <td><?=$Text->full_format($Desc, get_permissions_advtags($AuthorID))?></td>
        </tr>
    </table>
<?php
        break;
    case "thread" :
?>
    <div class="head">You are reporting the thread:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Username</td>
            <td>Title</td>
        </tr>
        <tr>
            <td><?=display_str($Username)?></td>
            <td><?=display_str($Title)?></td>
        </tr>
    </table>
<?php
        break;
    case "post" :
?>
    <div class="head">You are reporting the post:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Username</td>
            <td>Body</td>
        </tr>
        <tr>
            <td><?=display_str($Username)?></td>
            <td><?=$Text->full_format($Body, get_permissions_advtags($AuthorID))?></td>
        </tr>
    </table>
<?php
        break;
    case "requests_comment" :
    case "torrents_comment" :
    case "collages_comment" :
?>
    <div class="head">You are reporting the <?=$Types[$Short]['title']?>:</div>
    <table>
        <tr class="colhead">
            <td width="20%">Username</td>
            <td>Body</td>
        </tr>
        <tr>
            <td><?=display_str($Username)?></td>
            <td><?=$Text->full_format($Body, get_permissions_advtags($AuthorID))?></td>
        </tr>
    </table>
<?php
    break;
}
if (empty($NoReason)) {
?>
    <br/>
    <div class="head">Your reason for reporting:</div>
    <div class="box pad center">
        <form action="" method="post">
            <input type="hidden" name="action" value="takereport" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="type" value="<?=$Short?>" />
            <textarea rows="10" class="long" name="reason"></textarea><br /><br />
            <input type="submit" value="Submit report" />
        </form>
    </div>
<?php
}
// close <div class="thin"> ?>
</div>
<?php
show_footer();
