<?php

if (!check_perms('torrents_review_manage')) error(403);

include(SERVER_ROOT.'/classes/class_text.php'); // Text formatting class

show_header('Manage torrents marked for deletion', 'bbcode,marked_reasons,jquery');

$Text = new TEXT;

?>
<div class="thin">
    <div id="mfdreasons">
        <div class="messagecontainer" id="container_0"><div id="ajax_message_0" class="hidden center messagebar"></div></div>
        <div class="head">Marked For Deletion - Reasons</div>
        <div id="response_0" class="box">
            <form id="response_form_0" action="" method="post" onsubmit="return ValidateForm(0)">
                <div class="pad">
                    <strong>Name:</strong>
                     <input onfocus="if (this.value == 'New name') this.value='';"
                         type="text" name="name" id="response_name_0" size="80" value="New name"
                      />&nbsp;&nbsp;
                     <strong>Sort:</strong>
                     <input onfocus="if (this.value == 'New sort') this.value='';"
                         type="text" name="sort" id="response_sort_0" size="12" value="New sort"
                      />
                </div>
                <div class="box pad hidden" id="response_div_0" style="text-align:left;">
                </div>
                <div  class="pad" id="response_editor_0" >
                            <?php  $Text->display_bbcode_assistant("response_message_0", true); ?>
                    <textarea class="long" onfocus="if (this.value == 'New message') this.value='';"
                              rows="10" name="description" id="response_message_0">New message</textarea>
                </div>
                    <br />
                    <input type="button" value="Toggle preview" onClick="PreviewResponse(0);" />
                    <input type="hidden" name="id" value="0" />
                    <input type="hidden" name="action" value="mfd_edit_reason"" />
                    <input type="submit" name="submit" value="Save" id="save_0"/>
            </form>
        </div><a id="old_responses" name="old_responses"></a>
        <br />
        <br />
        <div class="center">
            <h3>Edit old responses:</h3>
        </div>
<?php

// List common responses
$DB->query("SELECT ID, Sort, Name, Description FROM review_reasons ORDER BY Sort ASC");
while (list($ID, $Sort, $Name, $Description) = $DB->next_record()) {

?>
        <div class="messagecontainer" id="container_<?=$ID?>"><div id="ajax_message_<?=$ID?>" class="hidden center messagebar"></div></div>
            <div  id="response_head_<?=$ID?>" class="head"></div>
            <div class="box pad">
                <input type="hidden" name="id" value="<?=$ID?>" />
                <strong>Name:</strong>
                <input type="text" name="name" id="response_name_<?=$ID?>" size="80" value="<?=display_str($Name)?>" />
                &nbsp;&nbsp;
                <strong>Sort:</strong>
                <input type="text" name="sort" id="response_sort_<?=$ID?>" size="12" value="<?=display_str($Sort)?>" />
                <br/><br/>
            <div id="response_<?=$ID?>" class="box">
                <form id="response_form_<?=$ID?>" action="">
                    <div class="box pad" id="response_div_<?=$ID?>" style="text-align:left;">
                        <?=$Text->full_format($Description, true, true)?>
                    </div>
                    <div class="pad hidden" id="response_editor_<?=$ID?>" >
                        <?php  $Text->display_bbcode_assistant("response_message_".$ID, true); ?>
                        <textarea class="long" onfocus="if (this.value == 'New message') this.value='';"
                           rows="10" id="response_message_<?=$ID?>" name="description"><?=display_str($Description)?></textarea>
                    </div>
                    <br />
                    <input type="button" value="Toggle preview" onClick="PreviewResponse(<?=$ID?>);" />
                    <input type="button" value="Delete" onClick="DeleteMessage(<?=$ID?>);" />
                    <input type="button" value="Save" id="save_<?=$ID?>" onClick="SaveMessage(<?=$ID?>);" />
                </div>
            </form>
        </div>
<?php
}

if (isset($_GET['added'])) {
?>
    <script type="text/javascript">
        addDOMLoadEvent(function () { Display_Message(<?=(int) $_GET['added']?>) } );
    </script>
<?php
}
?>
    </div>
</div>
<?php
show_footer();
