<?php
enforce_login();

if (!check_perms('users_mod')) {
    error(403);
}

show_header('Staff Blog','bbcode');
require(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

if (check_perms('admin_manage_blog')) {
    if (!empty($_REQUEST['action'])) {
        switch ($_REQUEST['action']) {
            case 'takeeditblog':
                authorize();
                if (empty($_POST['title'])) {
                    error("Please enter a title.");
                }
                if (is_number($_POST['blogid'])) {
                    $DB->query("UPDATE staff_blog SET Title='".db_string($_POST['title'])."', Body='".db_string($_POST['body'])."' WHERE ID='".db_string($_POST['blogid'])."'");
                    $Cache->delete_value('staff_blog');
                    $Cache->delete_value('staff_feed_blog');
                }
                header('Location: staffblog.php');
                break;
            case 'editblog':
                if (is_number($_GET['id'])) {
                    $BlogID = $_GET['id'];
                    $DB->query("SELECT Title, Body FROM staff_blog WHERE ID=$BlogID");
                    list($Title, $Body, $ThreadID) = $DB->next_record();
                }
                break;
            case 'deleteblog':
                if (is_number($_GET['id'])) {
                    authorize();
                    $DB->query("DELETE FROM staff_blog WHERE ID='".db_string($_GET['id'])."'");
                    $Cache->delete_value('staff_blog');
                    $Cache->delete_value('staff_feed_blog');
                }
                header('Location: staffblog.php');
                break;

            case 'takenewblog':
                authorize();
                if (empty($_POST['title'])) {
                    error("Please enter a title.");
                }
                $Title = db_string($_POST['title']);
                $Body = db_string($_POST['body']);

                $DB->query("INSERT INTO staff_blog (UserID, Title, Body, Time) VALUES ('$LoggedUser[ID]', '".db_string($_POST['title'])."', '".db_string($_POST['body'])."', '".sqltime()."')");
                $Cache->delete_value('staff_blog');

                send_irc("PRIVMSG ".ADMIN_CHAN." :!blog " . $_POST['title']);

                header('Location: staffblog.php');
                break;
        }
    }

    ?>
        <div class="thin">
                <div id="quickreplypreview">
                    <div id="contentpreview" style="text-align:left;"></div>
                </div>
            </div>
                <div class="thin">
            <div class="head">
                <?=((empty($_GET['action'])) ? 'Create a staff blog post' : 'Edit staff blog post')?>
                <span style="float:right;">
                    <a href="#" onclick="$('#postform').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(Show)':'(Hide)'); return false;"><?=($_REQUEST['action']!='editblog')?'(Show)':'(Hide)'?></a>
                </span>
            </div>
                <div class="box">
            <form  id="quickpostform" action="staffblog.php" method="post">
                <div id="postform" class="pad<?=($_REQUEST['action']!='editblog')?' hidden':''?>">
                <div id="quickreplytext">
                    <input type="hidden" name="action" value="<?=((empty($_GET['action'])) ? 'takenewblog' : 'takeeditblog')?>" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="author" value="<?=$LoggedUser['Username']; ?>" />
    <?php  if (!empty($_GET['action']) && $_GET['action'] == 'editblog') {?>
                    <input type="hidden" name="blogid" value="<?=$BlogID; ?>" />
    <?php  }?>
                    <h3>Title</h3>
                    <input type="text" name="title" class="long" <?php  if (!empty($Title)) { echo 'value="'.display_str($Title).'"'; } ?> /><br />
                    <h3>Body</h3>
                           <?php  $Text->display_bbcode_assistant('textbody', true)  ?>
                    <textarea id="textbody" name="body" class="long" rows="15"><?php  if (!empty($Body)) { echo display_str($Body); } ?></textarea> <br />

                </div>
                           <br />
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
if (!$Blog = $Cache->get_value('staff_blog')) {
    $DB->query("SELECT
        b.ID,
        um.Username,
        b.Title,
        b.Body,
        b.Time
        FROM staff_blog AS b LEFT JOIN users_main AS um ON b.UserID=um.ID
        ORDER BY Time DESC
        LIMIT 20");
    $Blog = $DB->to_array();
    $Cache->cache_value('Blog',$Blog,1209600);
}

$DB->query("INSERT INTO staff_blog_visits (UserID, Time) VALUES (".$LoggedUser['ID'].", NOW()) ON DUPLICATE KEY UPDATE Time=NOW()");
$Cache->delete_value('staff_blog_read_'.$LoggedUser['ID']);

foreach ($Blog as $BlogItem) {
    list($BlogID, $Author, $Title, $Body, $BlogTime) = $BlogItem;
?>
                <div class="head">
                    <?=$Title?> - <?=time_diff($BlogTime);?> by <?=$Author?>
        <?php  if (check_perms('admin_manage_blog')) { ?>
                    - <a href="staffblog.php?action=editblog&amp;id=<?=$BlogID?>">[Edit]</a>
                    <a href="staffblog.php?action=deleteblog&amp;id=<?=$BlogID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" onClick="return confirm('Do you want to delete this?')">[Delete]</a>
         <?php  } ?>
                </div>

            <div id="blog<?=$BlogID?>" class="box">
                <div class="pad">
                    <?=$Text->full_format($Body,true)?>
                </div>
            </div>
        <br />
<?php
}
?>
</div>
<?php
show_footer();
