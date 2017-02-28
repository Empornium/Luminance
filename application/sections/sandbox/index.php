<?php
if ($_REQUEST['action']=='smilies') {
    include(SERVER_ROOT.'/sections/sandbox/smilies.php');
    die();
}
enforce_login();

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

show_header('Sandbox', 'bbcode');
?>
<div class="thin">
    <h2>Sandbox</h2>

    <div class="head"></div>
    <form action="" method="post" id="messageform">
            <div class="box pad">
                <h3 class="center">Practice your bbCode skills here</h3>
                <br/>
                <div id="preview" class="hidden"><br/>
                    <h3 class="left">Preview:</h3>
                    <div class="box pad">
                    <div id="preview_content" class="body">
                    </div>
                    </div>
                </div>
                <?php  $Text->display_bbcode_assistant("body",get_permissions_advtags($LoggedUser['ID'], $LoggedUser['CustomPermissions'])); ?>
                <textarea id="body" name="body" class="long" rows="10" onkeyup="resize('body');" ></textarea>
                <div class="center">
                    <input  id="preview_button" type="button" value="Preview" onclick="Sandbox_Preview();" />
                </div>
                <span style="float:right"><a href="/sandbox.php?action=smilies" target="_blank">list of smileys</a></span>
                <br/>
            </div>
      </form>
</div>

<?php
show_footer();
