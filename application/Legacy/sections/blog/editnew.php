<?php

$Text = new Luminance\Legacy\Text;

require_once(SERVER_ROOT.'/Legacy/sections/forums/functions.php');

if (!in_array($blogSection,['Blog', 'Contests'])) $blogSection = 'Blog';


show_header($blogSection,'bbcode');

$ForumCats = get_forum_cats();
//This variable contains all our lovely forum data
$Forums = get_forums_info();


if (is_number($_GET['id'])) {
    $BlogID = $_GET['id'];
    $item = $master->db->raw_query("SELECT Title, Body, ThreadID, Image FROM blog WHERE ID=:blogid",
                                          [':blogid' => $BlogID] )->fetch(\PDO::FETCH_ASSOC);
}

$Editing = !empty($_GET['action']) && $_GET['action'] == 'editpost';
$lcSection = lcfirst($blogSection);

?>
    <div class="thin">
        <h2><?=(!$Editing ? "Add post to $lcSection" : "Edit $lcSection post #$BlogID")?></h2>
        <div id="quickreplypreview">
            <div id="contentpreview" style="text-align:left;"></div>
        </div>
    </div>

    <div class="linkbox">
        [<a href="/<?=$thispage?>">View <?=$lcSection?></a>]
    </div>

    <div class="thin">
        <div class="head">
            <?=(!$Editing ? "Create a $lcSection post" : "Edit $lcSection post")?>
        </div>
        <div class="box">
            <form  id="quickpostform" action="<?=$thispage?>" method="post">
                <div class="pad">
                    <div id="quickreplytext">
                        <input type="hidden" name="action" value="<?=(!$Editing ? 'takenewpost' : 'takeeditpost')?>" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<?php               if ($Editing) { ?>
                        <input type="hidden" name="blogid" value="<?=$BlogID?>" />
<?php               } ?>
                        <br/><h3>Title</h3>
                        <input type="text" name="title" class="long" <?php if (!empty($item['Title'])) { echo 'value="'.display_str($item['Title']).'"'; } ?> /><br />
                        <br/><h3>Image</h3>
                        <input type="text" name="image" class="long" <?php if (!empty($item['Image'])) { echo 'value="'.display_str($item['Image']).'"'; } ?> /><br />
                        <br/><h3>Body</h3>
<?php                   $Text->display_bbcode_assistant('textbody', true)  ?>
                        <textarea id="textbody" name="body" class="long" rows="15"><?php if (!empty($item['Body'])) { echo display_str($item['Body']); } ?></textarea> <br />
                        <br/><h3>Discussion Thread</h3>
<?php               if (!$Editing) {   ?>
                        <input type="radio" name="autothread" value="0" <?=(!($Editing && $item['ThreadID'])?'checked="checked" ':'')?>title="if selected a forum must be supplied" />
                        Automatically create thread in forum:
                        <?= print_forums_select($Forums, $ForumCats, ANNOUNCEMENT_FORUM_ID) ?> &nbsp;(creates thread using blog title)
                        <br/>
                        <input type="radio" name="autothread" value="1" <?=($Editing && $item['ThreadID']?'checked="checked" ':'')?>title="if selected a valid threadid must be supplied" />
<?php               }          ?>
                        Thread already discussing this topic:
                        <input type="text" name="thread" size="8"<?php if (!empty($item['ThreadID'])) { echo 'value="'.display_str($item['ThreadID']).'"'; } ?> />
                        &nbsp;(must be a valid thread id)
                        <br /><br />
<?php               if (!$Editing) {   ?>
                        <input id="subscribebox" type="checkbox" name="subscribe" title="add the thread to my subscribed topics"<?=!empty($HeavyInfo['AutoSubscribe'])?' checked="checked"':''?> tabindex="2" />
                        <label for="subscribebox">Subscribe</label>
<?php               }          ?>
                    </div>
                    <div class="center">
                        <input id="post_preview" type="button" value="Preview" onclick="if (this.preview) {Quick_Edit_Blog();} else {Quick_Preview_Blog();}" />
                        <input type="submit" value="<?=(!$Editing ? "Create $lcSection" : "Save Edited $lcSection post")?>" />
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php

show_footer();
