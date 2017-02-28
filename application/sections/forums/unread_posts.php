<?php
if (isset($LoggedUser['PostsPerPage'])) {
    $PerPage = $LoggedUser['PostsPerPage'];
} else {
    $PerPage = POSTS_PER_PAGE;
}

list($Page,$Limit) = page_limit($PerPage);

$UserID = (int) $LoggedUser['ID'];

$DB->query("SELECT RestrictedForums, PermittedForums FROM users_info WHERE UserID = $UserID");
list($RestrictedForums, $PermittedForums) = $DB->next_record();

if($PermittedForums) $PermittedForums = "f.ID IN ($PermittedForums) OR ";
if($RestrictedForums) $RestrictedForums = " AND f.ID NOT IN ($RestrictedForums) ";

 // I cannot find any useful way of caching this... problem is this is viewing user dependent, but clearing the cache is any user posting

$DB->query("SELECT SQL_CALC_FOUND_ROWS
                   f.ID, f.Description, t.ID, f.Name, t.Title, t.LastPostTime,
                  (t.NumPosts-1), t.NumViews, t.LastPostID, l.PostID,
                   author.ID, author.Username, f.MinClassRead, t.IsLocked, t.IsSticky
              FROM forums_topics AS t
              JOIN forums AS f ON f.ID = t.ForumID
              JOIN users_info AS i ON i.UserID=$UserID
               AND (i.CatchupTime < t.LastPostTime OR i.CatchupTime is null)
              JOIN users_main AS author ON author.ID=t.LastPostAuthorID
         LEFT JOIN forums_last_read_topics AS l ON l.UserID =i.UserID
               AND l.TopicID = t.ID
             WHERE t.LastPostAuthorID!=$UserID
               AND (l.PostID is null OR  l.PostID != t.LastPostID)
               AND ( $PermittedForums  (  f.MinClassRead<=$LoggedUser[Class] $RestrictedForums ) )
          ORDER BY t.LastPostTime DESC
             LIMIT $Limit");

$UnreadPosts = $DB->to_array();

$DB->query('SELECT FOUND_ROWS()');
list($Results) = $DB->next_record();

show_header('Unread Posts');

$Pages=get_pages($Page,$Results,$PerPage,9);

?>
<div class="thin">
    <?php  print_latest_forum_topics(); ?>
    <div class="linkbox">[<a href="forums.php?action=catchup&amp;forumid=all&amp;auth=<?=$LoggedUser['AuthKey']?>">Catch up all</a>]</div>
    <div class="linkbox pager">
        <?=$Pages?>
    </div>
    <div class="head"><a href="forums.php">Forums</a> &gt; Unread Posts</div>
    <table class="forum_index">
        <tr class="colhead">
            <td style="width:2%;"></td>
            <td style="width:25%;">Forum</td>
            <td>Topic</td>
            <td style="text-align: center;width:7%;">Replies</td>
            <td style="text-align: center;width:7%;">Views</td>
        </tr>
<?php  if ($Results == 0) { ?>
            <tr>
                <td colspan="5" class="center">
            No unread posts
                </td>
            </tr>
<?php  } else {

        $Row = 'a';

        foreach ($UnreadPosts as $UnreadPost) {
                list($ForumID, $ForumDescription, $ThreadID, $ForumName, $Title, $LastPostTime, $NumReplies, $NumViews,
                      $LastPostID, $LastReadPostID, $LastAuthorID, $LastPostAuthorName, $MinRead, $Locked, $Sticky) = $UnreadPost;
                $Row = ($Row == 'a') ? 'b' : 'a';

                $Read = 'unread';

                // Removed per request, as distracting
                if ($Locked) { $Read .= "_locked"; }
                if ($Sticky) { $Read .= "_sticky"; }

?>
                <tr class="row<?=$Row?>">
                    <td class="<?=$Read?>" title="<?=ucfirst($Read)?>"></td>
                    <td>
                          <h4 class="min_padding">
                                <a href="forums.php?action=viewforum&amp;forumid=<?=$ForumID?>" title="<?=display_str($ForumDescription)?>"><?=display_str($ForumName)?></a>
                          </h4>
                    </td>
                    <td>
                          <span style="float:left;" class="last_topic">
                                <a href="forums.php?action=viewthread&amp;threadid=<?=$ThreadID?>" title="<?=display_str($Title)?>"><?=display_str(cut_string($Title, 50, 0))?></a>
                          </span>
                          <span style="float: left;" class="last_read" title="Jump to last read">
                                <a href="forums.php?action=viewthread&amp;threadid=<?=$ThreadID;if($LastReadPostID>0)echo"&amp;postid=$LastReadPostID#post$LastReadPostID"?>"></a>
                          </span>
                          <span style="float:right;" class="last_poster">by <?=format_username($LastAuthorID, $LastPostAuthorName)?> <?=time_diff($LastPostTime,1)?></span>
                    </td>
                    <td style="text-align: center;"><?=number_format($NumReplies)?></td>
                    <td style="text-align: center;"><?=number_format($NumViews)?></td>
                </tr>
<?php           }
      }
?>
    </table>
    <div class="linkbox pager">
        <?=$Pages?>
    </div>
    <div class="linkbox">[<a href="forums.php?action=catchup&amp;forumid=all&amp;auth=<?=$LoggedUser['AuthKey']?>">Catch up all</a>]</div>
</div>

<?php
show_footer();
