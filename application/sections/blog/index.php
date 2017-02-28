<?php
enforce_login();

show_header('Blog','bbcode');
require(SERVER_ROOT.'/classes/class_text.php');

$Text = new TEXT;

if (check_perms('admin_manage_blog')) {
    if (!empty($_REQUEST['action'])) {
        switch ($_REQUEST['action']) {
            case 'deadthread' :
                if (is_number($_GET['id'])) {
                    $DB->query("UPDATE blog SET ThreadID=NULL WHERE ID=".$_GET['id']);
                    $Cache->delete_value('blog');
                    $Cache->delete_value('feed_blog');
                }
                header('Location: blog.php');
                break;
            case 'takeeditblog':
                authorize();
                if (is_number($_POST['blogid']) && is_number($_POST['thread'])) {
                    $DB->query("UPDATE blog SET Title='".db_string($_POST['title'])."', Body='".db_string($_POST['body'])."', ThreadID=".$_POST['thread']." WHERE ID='".db_string($_POST['blogid'])."'");
                    $Cache->delete_value('blog');
                    $Cache->delete_value('feed_blog');
                }
                header('Location: blog.php');
                break;
            case 'editblog':
                if (is_number($_GET['id'])) {
                    $BlogID = $_GET['id'];
                    $DB->query("SELECT Title, Body, ThreadID FROM blog WHERE ID=$BlogID");
                    list($Title, $Body, $ThreadID) = $DB->next_record();
                }
                break;
            case 'deleteblog':
                if (is_number($_GET['id'])) {
                    authorize();
                    $DB->query("DELETE FROM blog WHERE ID='".db_string($_GET['id'])."'");
                    $Cache->delete_value('blog');
                    $Cache->delete_value('feed_blog');
                }
                header('Location: blog.php');
                break;

            case 'takenewblog':
                authorize();
                $Title = db_string($_POST['title']);
                $Body = db_string($_POST['body']);
                $ThreadID = $_POST['thread'];
                if ($ThreadID && is_number($ThreadID)) {
                    $DB->query("SELECT ForumID FROM forums_topics WHERE ID=".$ThreadID);
                    if ($DB->record_count() < 1) {
                        error("No such thread exists!");
                        header('Location: blog.php');
                    }
                } else {
                              $ForumID = (int) $_POST['forumid'];
                    $DB->query("SELECT ID FROM forums WHERE ID=$ForumID");
                    if ($DB->record_count() < 1) {
                        error("No forum with id=$ForumID exists!");
                    }
                    $ThreadID = create_thread($ForumID, $LoggedUser[ID], $Title, $Body);
                    if ($ThreadID < 1) {
                        error(0);
                    }
                }

                $DB->query("INSERT INTO blog (UserID, Title, Body, Time, ThreadID) VALUES ('$LoggedUser[ID]', '".db_string($_POST['title'])."', '".db_string($_POST['body'])."', '".sqltime()."', ".$ThreadID.")");
                $Cache->delete_value('blog');
                if (isset($_POST['subscribe'])) {
                    $DB->query("INSERT IGNORE INTO users_subscriptions VALUES ('$LoggedUser[ID]', $ThreadID)");
                    $Cache->delete_value('subscriptions_user_'.$LoggedUser['ID']);
                }

                header('Location: blog.php');
                break;
        }
    }

include(SERVER_ROOT.'/sections/forums/functions.php');

$ForumCats = get_forum_cats();
//This variable contains all our lovely forum data
$Forums = get_forums_info();

    ?>
        <div class="thin">
            <h2>
                <a style="float:left;margin-top:4px" href="feeds.php?feed=feed_blog&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>" title="<?=SITE_NAME?> - Blog" ><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
        <?=SITE_NAME?> Blog</h2>
                <div id="quickreplypreview">
                    <div id="contentpreview" style="text-align:left;"></div>
                </div>
            </div>
                <div class="thin">
                        <div class="head">
                                <?=((empty($_GET['action'])) ? 'Create a blog post' : 'Edit blog post')?>
                        </div>
                    <div class="box">
                            <form  id="quickpostform" action="blog.php" method="post">
                                    <div class="pad">
                                    <div id="quickreplytext">
                                    <input type="hidden" name="action" value="<?=((empty($_GET['action'])) ? 'takenewblog' : 'takeeditblog')?>" />
                                            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <?php if (!empty($_GET['action']) && $_GET['action'] == 'editblog') { ?>
                                            <input type="hidden" name="blogid" value="<?=$BlogID; ?>" />
            <?php } ?>
                                            <br/><h3>Title</h3>
                                            <input type="text" name="title" class="long" <?php if (!empty($Title)) { echo 'value="'.display_str($Title).'"'; } ?> /><br />
                                            <br/><h3>Body</h3>
                            <?php $Text->display_bbcode_assistant('textbody', true)  ?>
                                            <textarea id="textbody" name="body" class="long" rows="15"><?php if (!empty($Body)) { echo display_str($Body); } ?></textarea> <br />
                                            <br/><h3>Discussion Thread</h3>
                                            <input type="radio" name="autothread" value="0" title="if selected a forum must be supplied" checked="checked" />
                                            Automatically create thread in forum:

                            <?= print_forums_select($Forums, $ForumCats, ANNOUNCEMENT_FORUM_ID) ?>
                                            <br/>
                                             <input type="radio" name="autothread" value="1" title="if selected a valid threadid must be supplied" />
                                            Thread already discussing this topic:
                                            <input type="text" name="thread" size="8"<?php if (!empty($ThreadID)) { echo 'value="'.display_str($ThreadID).'"'; } ?> />
                                            &nbsp;(must be a valid thread id)
                                            <br /><br />
                                            <input id="subscribebox" type="checkbox" name="subscribe"<?=!empty($HeavyInfo['AutoSubscribe'])?' checked="checked"':''?> tabindex="2" />
                                            <label for="subscribebox">Subscribe</label>
                            </div>
                                            <div class="center">
                                                    <input id="post_preview" type="button" value="Preview" onclick="if (this.preview) {Quick_Edit_Blog();} else {Quick_Preview_Blog();}" />
                                        <input type="submit" value="<?=((!isset($_GET['action'])) ? 'Create blog post' : 'Edit blog post') ?>" />
                                            </div>
                                    </div>
                            </form>
                    </div>
                </div>
        <br />
<?php
}
?>
<div class="thin">
<?php
if (!$Blog = $Cache->get_value('blog')) {
    $DB->query("SELECT
        b.ID,
        um.Username,
        b.Title,
        b.Body,
        b.Time,
        b.ThreadID
        FROM blog AS b LEFT JOIN users_main AS um ON b.UserID=um.ID
        ORDER BY Time DESC
        LIMIT 20");
    $Blog = $DB->to_array();
    $Cache->cache_value('Blog',$Blog,1209600);
}

foreach ($Blog as $BlogItem) {
    list($BlogID, $Author, $Title, $Body, $BlogTime, $ThreadID) = $BlogItem;
?>
                        <div class="head">
                                <strong><?=$Title?></strong> - posted <?=time_diff($BlogTime);?> by <?=$Author?>
        <?php if (check_perms('admin_manage_blog')) { ?>
                                - <a href="blog.php?action=editblog&amp;id=<?=$BlogID?>">[Edit]</a>
                                <a href="blog.php?action=deleteblog&amp;id=<?=$BlogID?>&amp;auth=<?=$LoggedUser['AuthKey']?>">[Delete]</a>
            <?php } ?>
                        </div>
            <div id="blog<?=$BlogID?>" class="box">
                <div class="pad">
                    <?=$Text->full_format($Body, true)?>
        <?php if ($ThreadID) { ?>
                    <br /><br />
                    <em><a href="forums.php?action=viewthread&threadid=<?=$ThreadID?>">Discuss this post here</a></em>
        <?php 		if (check_perms('admin_manage_blog')) { ?>
                    &nbsp;<a href="blog.php?action=deadthread&amp;id=<?=$BlogID?>&amp;auth=<?=$LoggedUser['AuthKey']?>">[Remove link]</a>
        <?php 		}
            } ?>
                </div>
            </div>
        <br />
<?php
}
?>
</div>
<?php
show_footer();
