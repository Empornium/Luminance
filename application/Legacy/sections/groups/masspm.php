<?php
if (empty($_REQUEST['groupid']) || !is_integer_string($_REQUEST['groupid'])) {
     error(0);
}
$GroupID = (int) $_REQUEST['groupid'];

list($name, $description) = $master->db->rawQuery(
    "SELECT Name,
            Comment
       FROM groups
      WHERE ID = ?",
    [$GroupID]
)->fetch(\PDO::FETCH_NUM);
if ($master->db->foundRows() == 0) error(0);

$users = $master->db->rawQuery(
    "SELECT UserID,
            Username
       FROM users_groups as ug
       JOIN users as u ON u.ID = ug.UserID
      WHERE GroupID = ?",
    [$GroupID]
)->fetchAll(\PDO::FETCH_OBJ);

if (!$users) { error("Cannot send a mass PM as there are no users in this group"); }

show_header('Send Mass PM', 'upload,bbcode,inbox');

$bbCode = new \Luminance\Legacy\Text;

?>
<div class="thin">
    <h2>Send PM To All Users in Group: <?= $name ?></h2>

    <div class="head">Send list<span style="float:right;"><a href="#" onclick="$('#ulist').toggle(); this.innerHTML=(this.innerHTML=='(Hide)'?'(View)':'(Hide)'); return false;">(View)</a></span></div>
      <div id="ulist" class="box pad hidden">
<?php
           foreach ($users as $user) { ?>
                <a href="/user.php?id=<?= $user->UserID ?>"><?= $user->Username ?></a><br/>
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
                    <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                    <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                        <h3>Show Sender: </h3>
                        <input type="checkbox" id="showsender" name="showsender" value="1" />
                        <label for="showsender">if checked then the PM will be sent from you, if unchecked it will be sent from system</label><br/>
                        <strong>note:</strong> Mass PM is much much slower if it is not sent from the system... practically speaking only send a mass PM from yourself for groups with less than a 100 members<br/>
                        <br />
                        <h3>Subject</h3>
                        <input type="text" name="subject" class="long" value="<?=(!empty($Subject) ? $Subject : 'subject')?>"/>
                        <br />
                        <h3>Message</h3>
                        <?php  $bbCode->display_bbcode_assistant("message", true); ?>
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
