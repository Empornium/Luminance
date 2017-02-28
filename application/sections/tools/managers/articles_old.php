<?php
enforce_login();
if (!check_perms('admin_manage_articles')) { error(403); }

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

switch ($_REQUEST['action']) {
    case 'takeeditarticle':
        if (!check_perms('admin_manage_articles')) { error(403); }
        if (is_number($_POST['articleid'])) {
            authorize();

                        $DB->query("SELECT Count(*) as c FROM articles WHERE TopicID='".db_string($_POST['topicid'])."' AND ID<>'".db_string($_POST['articleid'])."'");
                        list($Count) = $DB->next_record();
                        if ($Count > 0) {
                            error('The topic ID must be unique for the article');
                        }

                        list($TopicID) = $DB->next_record();
            $DB->query("UPDATE articles SET Category='".(int) $_POST['category']."',
                                                    SubCat='".(int) $_POST['subcat']."',
                                                   TopicID='".db_string(strtolower($_POST['topicid']))."',
                                                     Title='".db_string($_POST['title'])."',
                                               Description='".db_string($_POST['description'])."',
                                                      Body='".db_string($_POST['body'])."',
                                                      Time='".sqltime()."'
                                            WHERE ID='".db_string($_POST['articleid'])."'");
        }
        header('Location: tools.php?action=articles');
        break;
    case 'editarticle':
            $ArticleID = db_string($_REQUEST['id']);

                    $DB->query("SELECT ID, Category, SubCat, TopicID, Title, Description, Body FROM articles WHERE ID='$ArticleID'");
                    list($ArticleID, $Category, $SubCat, $TopicID, $Title, $Description, $Body) = $DB->next_record();
                break;
}

show_header('Manage articles','bbcode');

?>
<div class="thin">
    <h2><?= ($_GET['action'] == 'articles')? 'Create a new article' : 'Edit an article';?></h2>
    <div id="quickreplypreview">
        <div id="contentpreview" style="text-align:left;"></div>
    </div>
    <form  id="quickpostform" action="tools.php" method="post">
        <div class="box pad">
            <div id="quickreplytext">
            <input type="hidden" name="action" value="<?= ($_GET['action'] == 'articles')? 'takearticle' : 'takeeditarticle';?>" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<?php  if ($_GET['action'] == 'editarticle') {?>
            <input type="hidden" name="articleid" value="<?=$ArticleID; ?>" />
<?php  }?>
                  <div style="display:inline-block;margin-right:40px;vertical-align: top;">
                        <h3>Topic ID</h3>
                        <input type="text" name="topicid" <?php  if (!empty($TopicID)) { echo 'value="'.display_str($TopicID).'"'; } ?> />
                  </div>
                  <div style="display:inline-block;margin-right:40px;vertical-align: top;">
                        <h3>Category</h3>
                        <select name="category">
<?php  foreach ($ArticleCats as $Key => $Value) { ?>
                            <option value="<?=display_str($Key)?>"<?=($Category == $Key) ? 'selected="selected"' : '';?>><?=$Value?></option>
<?php  } ?>
                        </select>
                  </div>
                  <div style="display:inline-block;margin-right:40px;vertical-align: top;">
                        <h3>Sub-Category</h3>
                        <select name="subcat">
<?php  foreach ($ArticleSubCats as $Key => $Value) { ?>
                            <option value="<?=display_str($Key)?>"<?=($SubCat == $Key) ? 'selected="selected"' : '';?>><?=$Value?></option>
<?php  } ?>
                        </select>
                  </div>
                  <div style="display:inline-block;">
                      <ul>
                          <li><strong>Rules/Tutorials</strong> appears in the rules/help section</li>
                          <li><strong>Hidden</strong> used for content on other site pages<br/>(don't delete hidden content without being sure the topic is not needed)</li>
                      </ul>
                  </div>
            <h3>Title</h3>
            <input type="text" name="title" size="95" <?php  if (!empty($Title)) { echo 'value="'.display_str($Title).'"'; } ?> />
                        <h3>Description</h3>
            <input type="text" name="description" size="100" <?php  if (!empty($Description)) { echo 'value="'.display_str($Description).'"'; } ?> />
            <br />
            <h3>Body</h3>
                  <?php  $Text->display_bbcode_assistant('textbody', get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])) ?>
                  <textarea id="textbody" name="body" class="long" rows="15"><?php  if (!empty($Body)) { echo display_str($Body); } ?></textarea>
            </div>
            <br />
           <div class="center">
            <input id="post_preview" type="button" value="Preview" onclick="if (this.preview) {Quick_Edit_Blog();} else {Quick_Preview_Blog();}" />
                  <input type="submit" value="<?= ($_GET['action'] == 'articles')? 'Create new article' : 'Edit article';?>" />
            </div>
        </div>
    </form>
    <br /><br />
    <h2>Other articles</h2>

<?php
    $OldCategory = -1;
    $OldSubCat = -1;
    $DB->query("SELECT ID, Category, SubCat, TopicID, Title, Body, Time, Description
                  FROM articles
              ORDER BY Category, SubCat, Title");// LIMIT 20
    while (list($ArticleID,$Category,$SubCat,$TopicID, $Title,$Body,$ArticleTime,$Description)=$DB->next_record()) {

        if ($OldCategory != $Category || $OldSubCat != $SubCat) { ?>
            <h3 id="general"><?= "$ArticleCats[$Category] > ".($SubCat==1?"Other $ArticleCats[$Category] articles":$ArticleSubCats[$SubCat]) ?></h3>
<?php
            $OldCategory = $Category;
            $OldSubCat = $SubCat;
        }
?>
        <div class="head">
                <strong><?=display_str($Title) ?></strong> - posted <?=time_diff($ArticleTime) ?>
                    <span style="float:right;"><?=$TopicID?> -
                <a href="tools.php?action=editarticle&amp;id=<?=$ArticleID?>">[Edit]</a>
                <a href="tools.php?action=deletearticle&amp;id=<?=$ArticleID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" onClick="return confirm('Are you sure you want to delete this article?');">[Delete]</a>
                 - <a href="#" onClick="$('#article_<?=$ArticleID?>').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(Show)':'(Hide)'); return false;">(Show)</a></span>
        </div>
    <div class="box pad rowa">

         <?=$Text->full_format($Description, true, true) ?>
    </div>
    <div class="box vertical_space hidden" id="article_<?=$ArticleID?>">

        <div class="pad"><?=$Text->full_format($Body, true) ?></div>
    </div>
<?php  } ?>
</div>
<?php
show_footer();
