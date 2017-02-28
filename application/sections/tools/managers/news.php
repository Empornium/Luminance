<?php
enforce_login();
if (!check_perms('admin_manage_news')) { error(403); }

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;
show_header('Manage news','bbcode');

switch ($_GET['action']) {
    case 'takeeditnews':
        if (!check_perms('admin_manage_news')) { error(403); }
        if (is_number($_POST['newsid'])) {
            authorize();

            $DB->query("UPDATE news SET Title='".db_string($_POST['title'])."', Body='".db_string($_POST['body'])."' WHERE ID='".db_string($_POST['newsid'])."'");
            $Cache->delete_value('news');
            $Cache->delete_value('feed_news');
        }
        header('Location: index.php');
        break;
    case 'editnews':
    if (is_number($_GET['id'])) {
        $NewsID = $_GET['id'];
        $DB->query("SELECT Title, Body FROM news WHERE ID=$NewsID");
        list($Title, $Body) = $DB->next_record();
    }
}

?>
<div class="thin">
    <h2><?= ($_GET['action'] == 'news')? 'Create a news post' : 'Edit news post';?></h2>
    <div id="quickreplypreview">
        <div id="contentpreview" style="text-align:left;"></div>
    </div>
    <form  id="quickpostform" action="tools.php" method="post">
        <div class="box pad">
            <div id="quickreplytext">
            <input type="hidden" name="action" value="<?= ($_GET['action'] == 'news')? 'takenewnews' : 'takeeditnews';?>" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<?php  if ($_GET['action'] == 'editnews') {?>
            <input type="hidden" name="newsid" value="<?=$NewsID; ?>" />
<?php  }?>
            <h3>Title</h3>
            <input type="text" name="title" size="95" <?php  if (!empty($Title)) { echo 'value="'.display_str($Title).'"'; } ?> />
            <br />
            <h3>Body</h3>
                  <?php  $Text->display_bbcode_assistant('textbody')  ?>
                  <textarea id="textbody" name="body" class="long" rows="15"><?php  if (!empty($Body)) { echo display_str($Body); } ?></textarea>
            </div>
            <br />
           <div class="center">
            <input id="post_preview" type="button" value="Preview" onclick="if (this.preview) {Quick_Edit_Blog();} else {Quick_Preview_Blog();}" />
                  <input type="submit" value="<?= ($_GET['action'] == 'news')? 'Create news post' : 'Edit news post';?>" />
            </div>
        </div>
    </form>
<br /><br />
    <h2>News archive</h2>

<?php
$DB->query("SELECT n.ID,n.Title,n.Body,n.Time FROM news AS n ORDER BY n.Time DESC");// LIMIT 20
while (list($NewsID,$Title,$Body,$NewsTime)=$DB->next_record()) {
?>
        <div class="head">
                <strong><?=display_str($Title) ?></strong> - posted <?=time_diff($NewsTime) ?>
                - <a href="tools.php?action=editnews&amp;id=<?=$NewsID?>">[Edit]</a>
                <a href="tools.php?action=deletenews&amp;id=<?=$NewsID?>&amp;auth=<?=$LoggedUser['AuthKey']?>">[Delete]</a>
        </div>
    <div class="box vertical_space">
        <div class="pad"><?=$Text->full_format($Body, true) ?></div>
    </div>
<?php  } ?>
</div>
<?php
show_footer();
