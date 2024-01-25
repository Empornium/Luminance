<?php
/* * **********************************************************************

 * ********************************************************************** */
if (!check_perms('site_view_reportsv1') && !check_perms('admin_reports') && !check_perms('forum_moderate') && !check_perms('site_project_team')) {
    error(403);
}

$bbCode = new \Luminance\Legacy\Text;

list($Page, $Limit) = page_limit($master->settings->pagination->reports);

include(SERVER_ROOT . '/Legacy/sections/reports/array.php');

// Header
show_header('Reports', 'bbcode,inbox,reports,jquery');

$params = [];
$reportID = $_GET['id'] ?? null;
if (is_integer_string($_GET['id'] ?? '')) {
    $View = "Single report";
    $Where = "r.ID = ?";
    $params[] = $_GET['id'];
} elseif (empty($_GET['view'] ?? '')) {
    $View = "New";
    $Where = "Status='New'";
} else {
    $View = $_GET['view'];
    switch ($_GET['view'] ?? '') {
        case 'old' :
            $Where = "Status='Resolved'";
            break;
        case 'new' :
            $Where = "Status='New'";
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
    if (check_perms('site_view_reportsv1') || check_perms('forum_moderate')) {
        $Where .= " AND Type IN('collages_comment', 'Post', 'requests_comment', 'thread', 'torrents_comment')";
    }
}

if (isset($_GET['userid']) && is_integer_string($_GET['userid'])) {
    $WhereUserID = (int) $_GET['userid'];
    $Where .= " AND r.UserID = ? ";
    $params[] = $WhereUserID;
}

if (isset($_GET['type']) && array_key_exists($_GET['type'], $types)) {
    $Where .= " AND r.Type = ? ";
    $params[] = $_GET['type'];
}

if (isset($_GET['keyword'], $_GET['in']) && strlen($_GET['keyword']) > 0) {
    $In = $_GET['in'];
    $Keyword = str_replace(['%', '_'], ['\%', '\_'], $Keyword);
    $Keyword = "%{$Keyword}%";
    if ($In === 'reason') {
        $Where .= " AND r.Reason LIKE ? ";
        $params[] = $Keyword;
    } else if ($In === 'comment') {
        $Where .= " AND r.Comment LIKE ? ";
        $params[] = $Keyword;
    } else if ($In === 'both') {
        $Where .= " AND (r.Reason LIKE ? OR r.Comment LIKE ?) ";
        $params[] = $Keyword;
        $params[] = $Keyword;
    }
}

$Reports = $master->db->rawQuery(
    "SELECT SQL_CALC_FOUND_ROWS
            r.ID,
            r.UserID,
            u.Username,
            r.ThingID,
            r.Type,
            r.ReportedTime,
            r.Reason,
            r.Status,
            r.Comment
       FROM reports AS r
       JOIN users AS u ON r.UserID=u.ID
      WHERE {$Where}
   ORDER BY ReportedTime DESC
      LIMIT {$Limit}",
    $params
)->fetchAll(\PDO::FETCH_NUM);

// Number of results (for pagination)
$Results = $master->db->foundRows();

// Start printing stuff
?>
<div class="thin">
    <h2>Active Reports</h2>
    <div class="linkbox">
        <a href="/reports.php">New</a> |
        <a href="/reports.php?view=old">Old</a>
<?php   if (check_perms('admin_reports')) {   ?>
         | <a href="/reports.php?action=stats">Stats</a>
<?php   }   ?>
    </div>

    <script>
        function Toggle_view(elem_id) {
            jQuery('#'+elem_id+'div').toggle();

            if (jQuery('#'+elem_id+'div').is(':hidden'))
                jQuery('#'+elem_id+'button').text('(Show)');
            else
                jQuery('#'+elem_id+'button').text('(Hide)');

            return false;
        }
    </script>

    <div class="head">
        <span style="float:left;">Advanced search</span>
        <span style="float:right;"><a id="searchbutton" href="#" onclick="return Toggle_view('search');">(<?=!empty($_GET['search'])?'Hide':'Show'?>)</a></span>
    </div>
    <div class="box">
        <div id="searchdiv" class="pad" style="<?=!empty($_GET['search'])?'':'display: none;'?>">
        <form action="reports.php">
            <input type="hidden" name="search" value="1">
            <table>
                <tr>
                    <td class="label nobr">Report view:</td>
                    <td>
                        <select name="view">
                            <option value="new" <?php selected('view', 'new') ?>>New</option>
                            <option value="old" <?php selected('view', 'old') ?>>Old</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label nobr">Report type:</td>
                    <td>
                        <select name="type">
                            <option value="all" <?php selected('type', 'all') ?>>All</option>
                            <?php foreach ($types as $Type => $Data): ?>
                                <option value="<?= $Type ?>" <?php selected('type', $Type) ?>><?= $Data['title'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label nobr">Reporter ID:</td>
                    <td><input type="text" name="userid" placeholder="User ID" value="<?=display_str($_GET['userid'] ?? '')?>"></td>
                </tr>
                <tr>
                    <td class="label nobr">Keyword:</td>
                    <td>
                        <input type="text" name="keyword" placeholder="Text to search" value="<?=display_str($_GET['keyword'] ?? '')?>">
                    </td>
                </tr>
                <tr>
                    <td class="label nobr">Search in:</td>
                    <td>
                        <input id="InReason" name="in" type="radio" value="reason" <?php selected('in', 'reason', 'checked') ?>> <label for="InReason">Reports</label>
                        <input id="InComment" name="in" type="radio" value="comment" <?php selected('in', 'comment', 'checked') ?>> <label for="InComment">Staff comments</label>
                        <input id="InBoth" name="in" type="radio" value="both" <?=empty($_GET['in'])?'checked':''?> <?php selected('in', 'both', 'checked') ?>> <label for="InBoth">Both</label>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <input type="submit" value="Filter">
                    </td>
                </tr>
            </table>
        </form>
        </div>
    </div>

    <div class="linkbox">
        <?php
        // pagination
        $Pages = get_pages($Page, $Results, $master->settings->pagination->reports, 11);
        echo $Pages;
        ?>
    </div>

    <?php

    foreach ($Reports as $Report) {
        list($ReportID, $SnitchID, $SnitchName, $ThingID, $Short, $ReportedTime, $Reason, $Status, $Comment) = $Report;
        $Type = $types[$Short];
        $Reference = "reports.php?id=" . $ReportID . "#report" . $ReportID;

        switch ($Short) {
            case "user" :
                $username = $master->db->rawQuery(
                    "SELECT Username
                       FROM users
                      WHERE ID = ?",
                    [$ThingID]
                )->fetchColumn();
                if ($master->db->foundRows() < 1) {
                    $Link = "No user with the reported ID found";
                } else {
                    $Subject = "You have been reported";
                    $Link = "<a href='/user.php?id=" . $ThingID . "'>" . display_str($username) . "</a>";
                    $userID = $ThingID;
                }
                break;
            case "request" :
            case "request_update" :
                $nextRecord = $master->db->rawQuery(
                    "SELECT r.Title,
                            r.UserID,
                            u.Username
                       FROM requests AS r
                  LEFT JOIN users AS u ON u.ID = r.UserID
                      WHERE r.ID = ?",
                    [$ThingID]
                )->fetch(\PDO::FETCH_NUM);
                if ($master->db->foundRows() < 1) {
                    $Link = "No request with the reported ID found";
                } else {
                    list($Name, $userID, $Username) = $nextRecord;
                    $Subject = "Your request " . display_str($Name) . " has been reported";
                    $Message = "Your request [url=/requests.php?action=view&amp;id=$ThingID]" . display_str($Name) . "[/url] has been reported";
                    $Link = "<a href='/requests.php?action=view&amp;id=" . $ThingID . "'>Request '" . display_str($Name) . "' by " . display_str($Username) . "</a>";
                }
                break;
            case "collage" :
                $nextRecord = $master->db->rawQuery(
                    "SELECT c.Name,
                            c.UserID,
                            u.Username
                       FROM collages AS c
                  LEFT JOIN users AS u ON u.ID = c.UserID
                      WHERE c.ID = ?",
                    [$ThingID]
                )->fetch(\PDO::FETCH_NUM);
                if ($master->db->foundRows() < 1) {
                    $Link = "No collage with the reported ID found";
                } else {
                    list($Name, $userID, $Username) = $nextRecord;
                    $Subject = "Your collage " . display_str($Name) . " has been reported";
                    $Message = "Your collage [url=/collage/$ThingID]" . display_str($Name) . "[/url] has been reported";
                    $Link = "<a href='/collage/" . $ThingID . "'>Collage '" . display_str($Name) . "' by " . display_str($Username) . "</a>";
                }
                break;
            case "thread" :
                $nextRecord = $master->db->rawQuery(
                    "SELECT f.Title,
                            f.AuthorID,
                            u.Username
                       FROM forums_threads AS f
                  LEFT JOIN users AS u ON u.ID = f.AuthorID
                      WHERE f.ID = ?",
                    [$ThingID]
                )->fetch(\PDO::FETCH_NUM);
                if ($master->db->foundRows() < 1) {
                    $Link = "No thread with the reported ID found";
                } else {
                    list($Title, $userID, $Username) = $nextRecord;
                    $Subject = "Your thread " . display_str($Title) . " has been reported";
                    $Message = "Your thread [url=/forum/thread/{$ThingID}]" . display_str($Title) . "[/url] has been reported";
                    $Link = "<a href='/forum/thread/{$ThingID}'>Thread '" . display_str($Title) . "' by " . display_str($Username) . "</a>";
                }
                break;
            case "post" :
                if (isset($activeUser['PostsPerPage'])) {
                    $PerPage = $activeUser['PostsPerPage'];
                } else {
                    $PerPage = POSTS_PER_PAGE;
                }

                $nextRecord = $master->db->rawQuery(
                    "SELECT p.ID,
                            p.Body,
                            p.ThreadID,
                            f.Title,
                            p.AuthorID,
                            u.Username
                       FROM forums_posts AS p
                  LEFT JOIN forums_threads AS f ON f.ID = p.ThreadID
                  LEFT JOIN users AS u ON u.ID = p.AuthorID
                      WHERE p.ID = ?",
                    [$ThingID]
                )->fetch(\PDO::FETCH_NUM);
                if ($master->db->foundRows() < 1) {
                    $Link = "No post with the reported ID found";
                } else {
                    list($PostID, $Body, $ThreadID, $Title, $userID, $Username) = $nextRecord;
                    $Subject = "Your post #$PostID in thread " . display_str($Title) . " has been reported";
                    $Message = "Your post [url=/forum/thread/{$ThreadID}?postid={$PostID}#post{$PostID}]#{$PostID} in thread '" . display_str($Title) . "'[/url] has been reported";
                    $Link = "<a href='/forum/thread/{$ThreadID}?postid={$PostID}#post{$PostID}'>Post#{$PostID} by " . display_str($Username) . " in thread '" . display_str($Title) . "'</a>";
                }
                break;
            case "requests_comment" :
                $nextRecord = $master->db->rawQuery(
                    "SELECT rc.RequestID,
                            rc.Body,
                            (
                                SELECT COUNT(ID)
                                  FROM requests_comments
                                 WHERE ID <= ?
                                   AND requests_comments.RequestID = rc.RequestID
                            ) AS CommentNum,
                            r.Title,
                            rc.AuthorID,
                            u.Username
                       FROM requests_comments AS rc
                  LEFT JOIN requests AS r ON r.ID = rc.RequestID
                  LEFT JOIN users AS u ON u.ID = rc.AuthorID
                      WHERE rc.ID = ?",
                    [$ThingID, $ThingID]
                )->fetch(\PDO::FETCH_NUM);
                if ($master->db->foundRows() < 1) {
                    $Link = "No comment with the reported ID found";
                } else {
                    list($RequestID, $Body, $PostNum, $Title, $userID, $Username) = $nextRecord;
                    $PageNum = ceil($PostNum / TORRENT_COMMENTS_PER_PAGE);
                    $Subject = "Your comment #$ThingID in request " . display_str($Title) . " has been reported";
                    $Message = "Your comment [url=/requests.php?action=view&amp;id=" . $RequestID . "&page=" . $PageNum . "#post" . $ThingID . "]#$ThingID in request " . display_str($Title) . "[/url] has been reported";
                    $Link = "<a href='/requests.php?action=view&amp;id=" . $RequestID . "&page=" . $PageNum . "#post" . $ThingID . "'>Comment#$ThingID by " . display_str($Username) . " in request '" . display_str($Title) . "'</a>";
                }
                break;
            case "torrents_comment" :
                $nextRecord = $master->db->rawQuery(
                    "SELECT tc.GroupID,
                            tc.Body,
                            (
                                SELECT COUNT(ID)
                                  FROM torrents_comments
                                 WHERE ID <= ?
                                   AND torrents_comments.GroupID = tc.GroupID
                            ) AS CommentNum,
                            tg.Name,
                            tc.AuthorID,
                            u.Username
                       FROM torrents_comments AS tc
                  LEFT JOIN torrents_group AS tg ON tg.ID = tc.GroupID
                  LEFT JOIN users AS u ON u.ID = tc.AuthorID
                      WHERE tc.ID = ?",
                    [$ThingID, $ThingID]
                )->fetch(\PDO::FETCH_NUM);
                if ($master->db->foundRows() < 1) {
                    $Link = "No comment with the reported ID found";
                } else {
                    list($GroupID, $Body, $PostNum, $Title, $userID, $Username) = $nextRecord;
                    $PageNum = ceil($PostNum / TORRENT_COMMENTS_PER_PAGE);
                    $Subject = "Your comment #$ThingID in torrent " . display_str($Title) . " has been reported";
                    $Message = "Your comment [url=/torrents.php?id=" . $GroupID . "&page=" . $PageNum . "#post" . $ThingID . "]#$ThingID in torrent " . display_str($Title) . "[/url] has been reported";
                    $Link = "<a href='/torrents.php?id=" . $GroupID . "&page=" . $PageNum . "#post" . $ThingID . "'>Comment#$ThingID by " . display_str($Username) . " in torrent '" . display_str($Title) . "'</a>";
                }
                break;
            case "collages_comments" :
                $nextRecord = $master->db->rawQuery(
                    "SELECT cc.CollageID,
                            cc.Body,
                            (
                                SELECT COUNT(ID)
                                  FROM collages_comments
                                 WHERE ID <= ?
                                   AND collages_comments.CollageID = cc.CollageID
                            ) AS CommentNum,
                            c.Name,
                            cc.AuthorID,
                            u.Username
                       FROM collages_comments AS cc
                  LEFT JOIN collages AS c ON c.ID = cc.CollageID
                  LEFT JOIN users AS u ON u.ID = cc.AuthorID
                      WHERE cc.ID = ?",
                    [$ThingID, $ThingID]
                )->fetch(\PDO::FETCH_NUM);
                if ($master->db->foundRows() < 1) {
                    $Link = "No comment with the reported ID found";
                } else {
                    list($CollageID, $Body, $PostNum, $Title, $userID, $Username) = $nextRecord;
                    $PerPage = POSTS_PER_PAGE;
                    $PageNum = ceil($PostNum / $PerPage);
                    $Subject = "Your comment #$ThingID in collage " . display_str($Title) . " has been reported";
                    $Message = "Your comment [url=/collage.php?action=comments&amp;collageid=" . $CollageID . "&page=" . $PageNum . "#post" . $ThingID . "]#$ThingID in collage " . display_str($Title) . "[/url] has been reported";
                    $Link = "<a href='/collage.php?action=comments&amp;collageid=" . $CollageID . "&page=" . $PageNum . "#post" . $ThingID . "'>Comment#$ThingID by " . display_str($Username) . " in collage '" . display_str($Title) . "'</a>";
                }
                break;
        }
        ?>

        <div id="report<?= $ReportID ?>">

            <table cellpadding="5" id="report_<?= $ReportID ?>">
                <form action="reports.php" method="post">

                    <input type="hidden" name="reportid" value="<?= $ReportID ?>" />
                    <input type="hidden" name="auth" value="<?= $activeUser['AuthKey'] ?>" />
                    <tr class="colhead">
                        <td class="center" colspan="3">
                            <strong style="float:left;"><?= $Type['title'] ?></strong>
                            <strong><?= $Link ?></strong>
                            <strong style="float:right;"><a href="/reports.php?id=<?= $ReportID ?>">Report #<?= $ReportID ?></a></strong>
                        </td>
                    </tr>
                    <tr class="rowb">
                        <td colspan="3">
                            <?= time_diff($ReportedTime) ?>

                            <span style="float:right;">reported by <strong><a href="/user.php?id=<?= $SnitchID ?>"><?= $SnitchName ?></a></strong></span>

                        </td>
                    </tr>
                    <tr class="rowa">
                        <td colspan="3">Reason:<br/><?= $bbCode->full_format($Reason, get_permissions_advtags($SnitchID)) ?></td>
                    </tr>
                    <tr class="rowb">
                        <td colspan="<?= ($Status != 'Resolved') ? '2' : '3' ?>">
                            <div>Staff Comment:<br/><?= $bbCode->full_format($Comment, true) ?></div>
<?php   if ($Status != "Resolved") { ?>
                                <br/><textarea name="comment" rows="2" class="long"></textarea>
<?php   } ?>
                        </td>
<?php   if ($Status != "Resolved") { ?>
                            <td width="80px" valign="bottom">
                                <input type="submit" name="action" value="Add comment" />
<?php       if (check_perms('admin_reports') || check_perms('forum_moderate')) { ?>
                                <input type="submit" name="action" value="Resolve" />
<?php       } ?>
                            </td>
<?php   } ?>
                    </tr>
                </form>
                <?php
                // get the conversations
                $Conversations = $master->db->rawQuery(
                    "SELECT rc.ConvID,
                            pm.UserID,
                            u.Username,
                            (
                                CASE
                                    WHEN UserID = ? THEN 'Reporter'
                                    WHEN UserID = ? THEN 'Offender'
                                    ELSE 'other'
                                 END
                            ) AS ConvType,
                            pm.Date
                       FROM reports_conversations AS rc
                       JOIN staff_pm_conversations AS pm ON pm.ID=rc.ConvID
                  LEFT JOIN users AS u ON u.ID=pm.UserID
                      WHERE ReportID = ?
                   ORDER BY pm.Date ASC",
                    [$SnitchID, $userID, $ReportID]
                )->fetchAll(\PDO::FETCH_BOTH);

                if (count($Conversations)>0) {
                ?>
                    <tr class="rowa">
                        <td colspan="3" style="border-right: none">
                            <?php
                            foreach ($Conversations as $Conv) {  // if conv has already been started just provide a link to it
                                list($cID, $cUserID, $cUsername, $cType, $cDate)=$Conv;
                                ?>
                                <div style="text-align: right;">
                                    <em>(<?=  time_diff($cDate)?>)</em> &nbsp;view existing conversation with <a href="/user.php?id=<?= $cUserID ?>"><?= $cUsername ?></a> (<?=$cType?>) about this report: &nbsp;&nbsp
                                    <a href="/staffpm.php?action=viewconv&amp;id=<?= $cID ?>" target="_blank">[View Message]</a> &nbsp;
                                </div>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                <?php
                }
                if ($Status != "Resolved" && (check_perms('admin_reports') || check_perms('forum_moderate'))) { ?>
                    <tr class="rowa">
                        <td colspan="3" style="border-right: none">

                            <form action="reports.php" method="post" id="messageform<?= $ReportID ?>">
                                <span style="float:right;">
                                    Start new staff conversation with
                                    <select name="toid" id="pm_type<?=$ReportID?>" onchange="change_pmto(<?=$ReportID?>);" >
                                        <option value="<?= $userID ?>"><?=$Username ?? ''?> (Offender)</option>
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
                                                $staffPMs = $master->db->rawQuery(
                                                    "SELECT ID,
                                                            Name
                                                       FROM staff_pm_responses"
                                                )->fetchAll(\PDO::FETCH_OBJ);
                                                foreach ($staffPMs as $staffPM) {
                                                    ?>
                                                    <option value="<?= $staffPM->ID ?>"><?= $staffPM->Name ?></option>
                                                <?php  } ?>
                                            </select>
                                            <input type="button" value="Set message" onClick="Set_Message(<?=$ReportID?>);" />
                                            <input type="button" value="Create new / Edit" onClick="location.href='/staffpm.php?action=responses&convid=<?= $ConvID ?? '' ?>'" />
                                        </div>
                                    </div>

                                        <div id="quickpost<?= $ReportID ?>">
                                            <input type="hidden" name="reportid" value="<?= $ReportID ?>" />
                                            <input type="hidden" name="username" value="<?= $Username ?? '' ?>" />
                                            <input type="hidden" name="auth" value="<?= $activeUser['AuthKey'] ?>" />
                                            <input type="hidden" name="action" value="takepost" />
                                            <input type="hidden" name="prependtitle" value="Staff PM - " />

                                            <label for="subject<?= $ReportID ?>"><h3>Subject</h3></label>
                                            <input class="long" type="text" name="subject" id="subject<?= $ReportID ?>" value="<?= display_str($Subject) ?>" />
                                            <br />

                                            <label for="message<?= $ReportID ?>"><h3>Message</h3></label>
                                            <?php  $bbCode->display_bbcode_assistant("message$ReportID"); ?>
                                            <textarea rows="6" class="long" name="message" id="message<?= $ReportID ?>"><?= display_str($Message ?? '') ?></textarea>
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
