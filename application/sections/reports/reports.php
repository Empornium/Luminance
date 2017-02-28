<?php
/* * **********************************************************************

 * ********************************************************************** */
if (!check_perms('admin_reports') && !check_perms('site_project_team') && !check_perms('site_moderate_forums')) {
    error(404);
}

// Number of reports per page
define('REPORTS_PER_PAGE', '20');
include(SERVER_ROOT . '/classes/class_text.php');
$Text = NEW TEXT;

list($Page, $Limit) = page_limit(REPORTS_PER_PAGE);

include(SERVER_ROOT . '/sections/reports/array.php');

// Header
show_header('Reports', 'bbcode,inbox,reports,jquery');

if ($_GET['id'] && is_number($_GET['id'])) {
    $View = "Single report";
    $Where = "r.ID = " . $_GET['id'];
} elseif (empty($_GET['view'])) {
    $View = "New";
    $Where = "Status='New'";
} else {
    $View = $_GET['view'];
    switch ($_GET['view']) {
        case 'old' :
            $Where = "Status='Resolved'";
            break;
        default :
            error(404);
            break;
    }
}

if (!check_perms('admin_reports')) {
    if (check_perms('site_project_team')) {
        $Where .= " AND Type = 'request_update'";
    }
    if (check_perms('site_moderate_forums')) {
        $Where .= " AND Type IN('collages_comment', 'Post', 'requests_comment', 'thread', 'torrents_comment')";
    }
}

$Reports = $DB->query("SELECT SQL_CALC_FOUND_ROWS
        r.ID,
        r.UserID,
        um.Username,
        r.ThingID,
        r.Type,
        r.ReportedTime,
        r.Reason,
        r.Status,
            r.Comment
    FROM reports AS r
        JOIN users_main AS um ON r.UserID=um.ID
    WHERE " . $Where . "
    ORDER BY ReportedTime
    DESC LIMIT " . $Limit);

// Number of results (for pagination)
$DB->query('SELECT FOUND_ROWS()');
list($Results) = $DB->next_record();

// Done with the number of results. Move $DB back to the result set for the reports
$DB->set_query_id($Reports);

// Start printing stuff
?>
<div class="thin">
    <h2>Active Reports</h2>
    <div class="linkbox">
        <a href="reports.php">New</a> |
        <a href="reports.php?view=old">Old</a> |
        <a href="reports.php?action=stats">Stats</a>
    </div>
    <div class="linkbox">
        <?php
        // pagination
        $Pages = get_pages($Page, $Results, REPORTS_PER_PAGE, 11);
        echo $Pages;
        ?>
    </div>
    <?php
    while (list($ReportID, $SnitchID, $SnitchName, $ThingID, $Short, $ReportedTime, $Reason, $Status, $Comment) = $DB->next_record()) {
        $Type = $Types[$Short];
        $Reference = "reports.php?id=" . $ReportID . "#report" . $ReportID;

        switch ($Short) {
            case "user" :
                $DB->query("SELECT Username FROM users_main WHERE ID=" . $ThingID);
                if ($DB->record_count() < 1) {
                    $Link = "No user with the reported ID found";
                } else {
                    list($Username) = $DB->next_record();
                    $Subject = "You have been reported";
                    $Link = "<a href='user.php?id=" . $ThingID . "'>" . display_str($Username) . "</a>";
                    $UserID = $ThingID;
                }
                break;
            case "request" :
            case "request_update" :
                $DB->query("SELECT r.Title,
                                     r.UserID,
                                     u.Username
                                     FROM requests AS r
                                     LEFT JOIN users_main AS u ON u.ID = r.UserID WHERE r.ID=" . $ThingID);
                if ($DB->record_count() < 1) {
                    $Link = "No request with the reported ID found";
                } else {
                    list($Name, $UserID, $Username) = $DB->next_record();
                    $Subject = "Your request " . display_str($Name) . " has been reported";
                    $Message = "Your request [url=/requests.php?action=view&amp;id=$ThingID]" . display_str($Name) . "[/url] has been reported";
                    $Link = "<a href='requests.php?action=view&amp;id=" . $ThingID . "'>Request '" . display_str($Name) . "' by " . display_str($Username) . "</a>";
                }
                break;
            case "collage" :
                $DB->query("SELECT c.Name,
                                     c.UserID,
                                     u.Username
                                     FROM collages AS c
                                     LEFT JOIN users_main AS u ON u.ID = c.UserID WHERE c.ID=" . $ThingID);
                if ($DB->record_count() < 1) {
                    $Link = "No collage with the reported ID found";
                } else {
                    list($Name, $UserID, $Username) = $DB->next_record();
                    $Subject = "Your collage " . display_str($Name) . " has been reported";
                    $Message = "Your collage [url=/collages.php?id=$ThingID]" . display_str($Name) . "[/url] has been reported";
                    $Link = "<a href='collages.php?id=" . $ThingID . "'>Collage '" . display_str($Name) . "' by " . display_str($Username) . "</a>";
                }
                break;
            case "thread" :
                $DB->query("SELECT f.Title,
                                     f.AuthorID,
                                     u.Username
                                     FROM forums_topics AS f
                                     LEFT JOIN users_main AS u ON u.ID = f.AuthorID WHERE f.ID=" . $ThingID);
                if ($DB->record_count() < 1) {
                    $Link = "No thread with the reported ID found";
                } else {
                    list($Title, $UserID, $Username) = $DB->next_record();
                    $Subject = "Your thread " . display_str($Title) . " has been reported";
                    $Message = "Your thread [url=/forums.php?action=viewthread&amp;threadid=" . $ThingID . "]" . display_str($Title) . "[/url] has been reported";
                    $Link = "<a href='forums.php?action=viewthread&amp;threadid=" . $ThingID . "'>Thread '" . display_str($Title) . "' by " . display_str($Username) . "</a>";
                }
                break;
            case "post" :
                if (isset($LoggedUser['PostsPerPage'])) {
                    $PerPage = $LoggedUser['PostsPerPage'];
                } else {
                    $PerPage = POSTS_PER_PAGE;
                }

                $DB->query("SELECT p.ID,
                                     p.Body,
                                     p.TopicID,
                                     (SELECT COUNT(ID) FROM forums_posts
                                                       WHERE forums_posts.TopicID = p.TopicID
                                                       AND forums_posts.ID<=p.ID) AS PostNum,
                                     f.Title,
                                     p.AuthorID,
                                     u.Username FROM forums_posts AS p
                                                LEFT JOIN forums_topics AS f ON f.ID = p.TopicID
                                                LEFT JOIN users_main AS u ON u.ID = p.AuthorID WHERE p.ID=" . $ThingID);
                if ($DB->record_count() < 1) {
                    $Link = "No post with the reported ID found";
                } else {
                    list($PostID, $Body, $TopicID, $PostNum, $Title, $UserID, $Username) = $DB->next_record();
                    $Subject = "Your post #$PostID in thread " . display_str($Title) . " has been reported";
                    $Message = "Your post [url=/forums.php?action=viewthread&amp;threadid=" . $TopicID . "&post=" . $PostNum . "#post" . $PostID . "]#$PostID in thread '" . display_str($Title) . "'[/url] has been reported";
                    $Link = "<a href='forums.php?action=viewthread&amp;threadid=" . $TopicID . "&post=" . $PostNum . "#post" . $PostID . "'>Post#$PostID by " . display_str($Username) . " in thread '" . display_str($Title) . "'</a>";
                }
                break;
            case "requests_comment" :
                $DB->query("SELECT rc.RequestID,
                                     rc.Body,
                                     (SELECT COUNT(ID) FROM requests_comments
                                                       WHERE ID <= " . $ThingID . "
                                                       AND requests_comments.RequestID = rc.RequestID) AS CommentNum ,
                                     r.Title,
                                     rc.AuthorID,
                                     u.Username
                                     FROM requests_comments AS rc
                                     LEFT JOIN requests AS r ON r.ID = rc.RequestID
                                     LEFT JOIN users_main AS u ON u.ID = rc.AuthorID WHERE rc.ID=" . $ThingID);
                if ($DB->record_count() < 1) {
                    $Link = "No comment with the reported ID found";
                } else {
                    list($RequestID, $Body, $PostNum, $Title, $UserID, $Username) = $DB->next_record();
                    $PageNum = ceil($PostNum / TORRENT_COMMENTS_PER_PAGE);
                    $Subject = "Your comment #$ThingID in request " . display_str($Title) . " has been reported";
                    $Message = "Your comment [url=/requests.php?action=view&amp;id=" . $RequestID . "&page=" . $PageNum . "#post" . $ThingID . "]#$ThingID in request " . display_str($Title) . "[/url] has been reported";
                    $Link = "<a href='requests.php?action=view&amp;id=" . $RequestID . "&page=" . $PageNum . "#post" . $ThingID . "'>Comment#$ThingID by " . display_str($Username) . " in request '" . display_str($Title) . "'</a>";
                }
                break;
            case "torrents_comment" :
                $DB->query("SELECT tc.GroupID,
                                     tc.Body,
                                     (SELECT COUNT(ID) FROM torrents_comments
                                                       WHERE ID <= " . $ThingID . "
                                                       AND torrents_comments.GroupID = tc.GroupID) AS CommentNum,
                                     tg.Name,
                                     tc.AuthorID,
                                     u.Username
                                     FROM torrents_comments AS tc
                                     LEFT JOIN torrents_group AS tg ON tg.ID = tc.GroupID
                                     LEFT JOIN users_main AS u ON u.ID = tc.AuthorID WHERE tc.ID=" . $ThingID);
                if ($DB->record_count() < 1) {
                    $Link = "No comment with the reported ID found";
                } else {
                    list($GroupID, $Body, $PostNum, $Title, $UserID, $Username) = $DB->next_record();
                    $PageNum = ceil($PostNum / TORRENT_COMMENTS_PER_PAGE);
                    $Subject = "Your comment #$ThingID in torrent " . display_str($Title) . " has been reported";
                    $Message = "Your comment [url=/torrents.php?id=" . $GroupID . "&page=" . $PageNum . "#post" . $ThingID . "]#$ThingID in torrent " . display_str($Title) . "[/url] has been reported";
                    $Link = "<a href='torrents.php?id=" . $GroupID . "&page=" . $PageNum . "#post" . $ThingID . "'>Comment#$ThingID by " . display_str($Username) . " in torrent '" . display_str($Title) . "'</a>";
                }
                break;
            case "collages_comment" :
                $DB->query("SELECT cc.CollageID,
                                     cc.Body,
                                     (SELECT COUNT(ID) FROM collages_comments
                                                       WHERE ID <= " . $ThingID . "
                                                       AND collages_comments.CollageID = cc.CollageID) AS CommentNum,
                                     c.Name,
                                     cc.UserID,
                                     u.Username
                                     FROM collages_comments AS cc
                                     LEFT JOIN collages AS c ON c.ID = cc.CollageID
                                     LEFT JOIN users_main AS u ON u.ID = cc.UserID
                                     WHERE cc.ID=" . $ThingID);
                if ($DB->record_count() < 1) {
                    $Link = "No comment with the reported ID found";
                } else {
                    list($CollageID, $Body, $PostNum, $Title, $UserID, $Username) = $DB->next_record();
                    $PerPage = POSTS_PER_PAGE;
                    $PageNum = ceil($PostNum / $PerPage);
                    $Subject = "Your comment #$ThingID in collage " . display_str($Title) . " has been reported";
                    $Message = "Your comment [url=/collage.php?action=comments&amp;collageid=" . $CollageID . "&page=" . $PageNum . "#post" . $ThingID . "]#$ThingID in collage " . display_str($Title) . "[/url] has been reported";
                    $Link = "<a href='collage.php?action=comments&amp;collageid=" . $CollageID . "&page=" . $PageNum . "#post" . $ThingID . "'>Comment#$ThingID by " . display_str($Username) . " in collage '" . display_str($Title) . "'</a>";
                }
                break;
        }
        ?>

        <div id="report<?= $ReportID ?>">

            <table cellpadding="5" id="report_<?= $ReportID ?>">
                <form action="reports.php" method="post">

                    <input type="hidden" name="reportid" value="<?= $ReportID ?>" />
                    <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                    <tr class="colhead">
                        <td class="center" colspan="3">
                            <strong style="float:left;"><?= $Type['title'] ?></strong>
                            <strong><?= $Link ?></strong>
                        </td>
                    </tr>
                    <tr class="rowb">
                        <td colspan="3">
                            <?= time_diff($ReportedTime) ?>

                            <span style="float:right;">reported by <strong><a href="user.php?id=<?= $SnitchID ?>"><?= $SnitchName ?></a></strong></span>

                        </td>
                    </tr>
                    <tr class="rowa">
                        <td colspan="3">Reason:<br/><?= $Text->full_format($Reason, get_permissions_advtags($SnitchID)) ?></td>
                    </tr>
                    <tr class="rowb">
                        <td colspan="<?= ($Status != 'Resolved') ? '2' : '3' ?>">
                            <div>Staff Comment:<br/><?= $Text->full_format($Comment, true) ?></div>
                            <?php  if ($Status != "Resolved") { ?>
                                <br/><textarea name="comment" rows="2" class="long"></textarea>
                            <?php  } ?>
                        </td>
                        <?php  if ($Status != "Resolved") { ?>
                            <td width="80px" valign="bottom">
                                <input type="submit" name="action" value="Add comment" />
                                <input type="submit" name="action" value="Resolve" />
                            </td>
                        <?php  } ?>
                    </tr>
                </form>
                <?php
                // get the conversations
                $Conversations = array();
                $DB->query("SELECT rc.ConvID, pm.UserID, um.Username,
                                (CASE WHEN UserID='$SnitchID' THEN 'Reporter'
                                      WHEN UserID='$UserID' THEN 'Offender'
                                      ELSE 'other'
                                 END) AS ConvType, pm.Date
                                FROM reports_conversations AS rc
                                JOIN staff_pm_conversations AS pm ON pm.ID=rc.ConvID
                                LEFT JOIN users_main AS um ON um.ID=pm.UserID
                            WHERE ReportID=" . $ReportID . "
                                ORDER BY pm.Date ASC");
                $Conversations = $DB->to_array();

                if (count($Conversations)>0) {
                ?>
                    <tr class="rowa">
                        <td colspan="3" style="border-right: none">
                            <?php
                            foreach ($Conversations as $Conv) {  // if conv has already been started just provide a link to it
                                list($cID, $cUserID, $cUsername, $cType, $cDate)=$Conv;
                                ?>
                                <div style="text-align: right;">
                                    <em>(<?=  time_diff($cDate)?>)</em> &nbsp;view existing conversation with <a href="user.php?id=<?= $cUserID ?>"><?= $cUsername ?></a> (<?=$cType?>) about this report: &nbsp;&nbsp
                                    <a href="staffpm.php?action=viewconv&id=<?= $cID ?>" target="_blank">[View Message]</a> &nbsp;
                                </div>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                <?php
                }
                if ($Status != "Resolved") { ?>
                    <tr class="rowa">
                        <td colspan="3" style="border-right: none">

                            <form action="reports.php" method="post" id="messageform<?= $ReportID ?>">
                                <span style="float:right;">
                                    Start new staff conversation with
                                    <select name="toid" id="pm_type<?=$ReportID?>" onchange="change_pmto(<?=$ReportID?>);" >
                                        <option value="<?= $UserID ?>"><?=$Username?> (Offender)</option>
                                        <option value="<?= $SnitchID ?>"><?=$SnitchName?> (Reporter)</option>
                                    </select> about this report: &nbsp;&nbsp;&nbsp;&nbsp;
                                    <a href="#report<?= $ReportID ?>" onClick="Open_Compose_Message(<?="'$ReportID'"?>)">[Compose Message]</a>
                                </span>
                                <br class="clear" />
                                <div id="compose<?= $ReportID ?>" class="hide">
                                    <div id="preview<?= $ReportID ?>" class="hidden"></div>
                                    <div id="common_answers<?= $ReportID ?>" class="hidden">
                                        <div class="box vertical_space">
                                            <div class="head">
                                                <strong>Preview</strong>
                                            </div>
                                            <div id="common_answers_body<?= $ReportID ?>" class="body">Select an answer from the dropdown to view it.</div>
                                        </div>
                                        <br />
                                        <div class="center">
                                            <select id="common_answers_select<?= $ReportID ?>" onChange="Update_Message(<?= $ReportID ?>);">
                                                <option id="first_common_response<?= $ReportID ?>">Select a message</option>
                                                <?php
                                                // List common responses
                                                $DB->query("SELECT ID, Name FROM staff_pm_responses");
                                                while (list($ID, $Name) = $DB->next_record()) {
                                                    ?>
                                                    <option value="<?= $ID ?>"><?= $Name ?></option>
                                                <?php  } ?>
                                            </select>
                                            <input type="button" value="Set message" onClick="Set_Message(<?=$ReportID?>);" />
                                            <input type="button" value="Create new / Edit" onClick="location.href='staffpm.php?action=responses&convid=<?= $ConvID ?>'" />
                                        </div>
                                    </div>

                                        <div id="quickpost<?= $ReportID ?>">
                                            <input type="hidden" name="reportid" value="<?= $ReportID ?>" />
                                            <input type="hidden" name="username" value="<?= $Username ?>" />
                                            <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                                            <input type="hidden" name="action" value="takepost" />
                                            <input type="hidden" name="prependtitle" value="Staff PM - " />

                                            <label for="subject"><h3>Subject</h3></label>
                                            <input class="long" type="text" name="subject" id="subject<?= $ReportID ?>" value="<?= display_str($Subject) ?>" />
                                            <br />

                                            <label for="message"><h3>Message</h3></label>
                                            <?php  $Text->display_bbcode_assistant("message$ReportID"); ?>
                                            <textarea rows="6" class="long" name="message" id="message<?= $ReportID ?>"><?= display_str($Message) ?></textarea>
                                            <br />

                                        </div>
                                        <input type="button" value="Hide" onClick="jQuery('#compose<?= $ReportID ?>').toggle();return false;" />
                                        <input type="button" id="previewbtn<?= $ReportID ?>" value="Preview" onclick="Inbox_Preview(<?= "'$ReportID'" ?>);" />

                                        <input type="button" value="Common answers" onClick="$('#common_answers<?= $ReportID ?>').toggle();" />
                                        <input id="submit_pm<?=$ReportID?>" type="submit" value="Send message to selected user" />

                                    </div>
                            </form>

                        </td>
                    </tr>
                <?php  } ?>
            </table>
        </div>
        <br />
        <?php
        $DB->set_query_id($Reports);
    }
    ?>
</div>
<div class="linkbox">
    <?php
    echo $Pages;
    ?>
</div>
<?php
show_footer();
