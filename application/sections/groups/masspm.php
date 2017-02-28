<?php
if (empty($_REQUEST['groupid']) || !is_number($_REQUEST['groupid']) ) {
     error(0);
}
$GroupID = (int) $_REQUEST['groupid'];

$DB->query("SELECT Name, Comment from groups WHERE ID=$GroupID");
if ($DB->record_count()==0) error(0);
list($Name, $Description) = $DB->next_record();

$DB->query("SELECT
                UserID,
                Username
            FROM users_groups as ug
            JOIN users_main as um ON um.ID = ug.UserID
            WHERE GroupID=$GroupID");

$Users = $DB->to_array(false,MYSQLI_BOTH);

if (!$Users) { error("Cannot send a mass PM as there are no users in this group"); }

show_header('Send Mass PM', 'upload,bbcode,inbox');

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

?>
<div class="thin">
    <h2>Send PM To All Users in Group: <?=$Name?></h2>

    <div class="head">Send list<span style="float:right;"><a href="#" onclick="$('#ulist').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(View)</a></span></div>
      <div id="ulist" class="box pad hidden">
<?php
           foreach ($Users as $User) {
               list($UserID,$Username) = $User; ?>
                <a href="/user.php?id=<?=$UserID?>"><?=$Username?></a><br/>
<?php            }      ?>
      </div>
      <br/>
        <div id="preview" class="hidden"></div>
        <form action="groups.php" method="post" id="messageform">
            <div id="quickpost">
                <div class="head">Compose message</div>
                <div class="box pad">
                    <input type="hidden" name="action" value="takemasspm" />
                    <input type="hidden" name="applyto" value="group" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                        <h3>Show Sender: </h3>
                        <input type="checkbox" name="showsender" value="1" />
                        <label for="showsender">if checked then the PM will be sent from you, if unchecked it will be sent from system</label><br/>
                        <strong>note:</strong> Mass PM is much much slower if it is not sent from the system... practically speaking only send a mass PM from yourself for groups with less than a 100 members<br/>
                        <br />
                        <h3>Subject</h3>
                        <input type="text" name="subject" class="long" value="<?=(!empty($Subject) ? $Subject : 'subject')?>"/>
                        <br />
                        <h3>Message</h3>
                        <?php  $Text->display_bbcode_assistant("message", true); ?>
                        <textarea id="message" name="message" class="long" rows="10"><?=(!empty($Body) ? $Body : '')?></textarea>
                </div>
            </div>
        <div class="center">
             <input type="button" id="previewbtn" value="Preview" onclick="Inbox_Preview();" />
             <input type="submit" value="Send Mass PM" />
        </div>
        </form>

</div>
<?php
show_footer();
