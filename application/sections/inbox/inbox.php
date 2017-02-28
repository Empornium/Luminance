<?php
$UserID = $LoggedUser['ID'];

if (empty($_GET['action'])) { $Section = 'inbox'; } else {
    $Section = $_GET['action']; // either 'inbox' or 'sentbox'
}
if (!in_array($Section, array('inbox', 'sentbox'))) { error(404); }

list($Page,$Limit) = page_limit(MESSAGES_PER_PAGE);

show_header('Inbox');
?>
<div class="thin">
    <div class="linkbox">
<?php

if ($Section == 'inbox') { ?>
        <a href="inbox.php?action=sentbox">[Sentbox]</a>
<?php  } elseif ($Section == 'sentbox') { ?>
        <a href="inbox.php">[Inbox]</a>
<?php  } ?>
        <br /><br />
<?php

$Sort = empty($_GET['sort']) || $_GET['sort'] != "unread" ? "Date DESC" : "cu.Unread = '1' DESC, DATE DESC";

if ($Section == 'sentbox') {

    $sql = "SELECT
          SQL_CALC_FOUND_ROWS
          c.ID,
          c.Subject,
          cu2.Unread,
          cu.Sticky,
          cu2.UserID,
          um.Username,
          ui.Donor,
          ui.Warned,
          um.Enabled,
          cu.SentDate AS Date,
          um.PermissionID
          FROM pm_conversations AS c
          LEFT JOIN pm_conversations_users AS cu ON cu.ConvID=c.ID AND cu.UserID='$UserID'
          LEFT JOIN pm_conversations_users AS cu2 ON cu2.ConvID=c.ID AND cu2.UserID!='$UserID' AND cu2.ForwardedTo=0
          LEFT JOIN users_main AS um ON um.ID=cu2.UserID
          LEFT JOIN users_info AS ui ON ui.UserID=um.ID";

} else {

    $sql = "SELECT
          SQL_CALC_FOUND_ROWS
          c.ID,
          c.Subject,
          cu.Unread,
          cu.Sticky,
          pms.SenderID,
          um.Username,
          ui.Donor,
          ui.Warned,
          um.Enabled,
          cu.ReceivedDate AS Date,
          um.PermissionID,
          um.GroupPermissionID
          FROM pm_conversations AS c
          LEFT JOIN pm_conversations_users AS cu ON cu.ConvID=c.ID AND cu.UserID='$UserID'
          LEFT JOIN pm_messages AS pms ON pms.ConvID=c.ID AND pms.SenderID!='$UserID'
          LEFT JOIN users_main AS um ON um.ID=pms.SenderID
          LEFT JOIN users_info AS ui ON ui.UserID=um.ID";
}

if (!empty($_GET['search']) && $_GET['searchtype'] == "message") {
    $sql .=	" JOIN pm_messages AS m ON c.ID=m.ConvID";
}

$sql .= " WHERE ";
if (!empty($_GET['search'])) {
        $Search = db_string($_GET['search']);
        if ($_GET['searchtype'] == "user") {
            $sql .= "um.Username LIKE '".$Search."' AND ";
        } elseif ($_GET['searchtype'] == "subject") {
            $Words = explode(' ', $Search);
            $sql .= "c.Subject LIKE '%".implode("%' AND c.Subject LIKE '%", $Words)."%' AND ";
        } elseif ($_GET['searchtype'] == "message") {
            $Words = explode(' ', $Search);
            $sql .= "m.Body LIKE '%".implode("%' AND m.Body LIKE '%", $Words)."%' AND ";
        }
}
$sql .= ($Section == 'sentbox')? ' cu.InSentbox' : ' cu.InInbox';
$sql .="='1'";

$sql .=" GROUP BY c.ID
    ORDER BY cu.Sticky, ".$Sort." LIMIT $Limit";
$Results = $DB->query($sql);
$DB->query('SELECT FOUND_ROWS()');
list($NumResults) = $DB->next_record();
$DB->set_query_id($Results);

$CurURL = get_url(array('sort'));
if (empty($CurURL)) {
    $CurURL = "inbox.php?";
} else {
    $CurURL = "inbox.php?".$CurURL."&";
}

$Pages=get_pages($Page,$NumResults,MESSAGES_PER_PAGE,9);
echo $Pages;
?>
    </div>
     <div class="head"><?= ($Section == 'sentbox') ? 'Sentbox' : 'Inbox' ?></div>
    <div class="box pad">
<?php  if ($DB->record_count()==0) { ?>
    <h2>Your <?= ($Section == 'sentbox') ? 'sentbox' : 'inbox' ?> is currently empty</h2>
<?php  } else { ?>
        <form action="inbox.php" method="get" id="searchbox">
            <div>
                <input type="hidden" name="action" value="<?=$Section?>" />
                <input type="radio" name="searchtype" value="user" checked="checked" /> User
                <input type="radio" name="searchtype" value="subject" /> Subject
                <input type="radio" name="searchtype" value="message" /> Message
                <span style="float: right;">
<?php 			if (empty($_GET['sort']) || $_GET['sort'] != "unread") { ?>
                    <a href="<?=$CurURL?>sort=unread">List unread first</a>
<?php 			} else { ?>
                    <a href="<?=$CurURL?>">List latest first</a>
<?php 			} ?>
                </span>
                <br />
                <input type="text" name="search" value="Search <?= ($Section == 'sentbox') ? 'Sentbox' : 'Inbox' ?>" style="width: 98%;"
                        onfocus="if (this.value == 'Search <?= ($Section == 'sentbox') ? 'Sentbox' : 'Inbox' ?>') this.value='';"
                        onblur="if (this.value == '') this.value='Search <?= ($Section == 'sentbox') ? 'Sentbox' : 'Inbox' ?>';"
                />
            </div>
        </form>
        <form action="inbox.php" method="post" id="messageform" onsubmit="return anyChecks('messageform')">
            <input type="hidden" name="action" value="masschange" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <table>
                <tr class="colhead">
                    <td width="10"><input type="checkbox" onclick="toggleChecks('messageform',this)" /></td>
                    <td width="45%">Subject</td>
                    <td><?=($Section == 'sentbox')? 'Receiver' : 'Sender' ?></td>
                    <td>Date</td>
                </tr>
<?php
    $Row = 'a';
    while (list($ConvID, $Subject, $Unread, $Sticky, $SenderID, $Username, $Donor, $Warned, $Enabled, $Date, $ClassID, $GroupPermID) = $DB->next_record()) {
        if ($Unread === '1') {
            $RowClass = 'unreadpm';
        } else {
            $Row = ($Row === 'a') ? 'b' : 'a';
            $RowClass = 'row'.$Row;
        }
?>
                <tr class="<?=$RowClass?>">
                    <td class="center"><input type="checkbox" name="messages[]" value="<?=$ConvID?>" /></td>
                    <td>
<?php 		if ($Unread) { echo '<strong>'; } ?>
<?php 		if ($Sticky) { echo 'Sticky: '; }
?>
                        <a href="inbox.php?action=viewconv&amp;id=<?=$ConvID?>"><?=$Subject?></a>
<?php
        if ($Unread) { echo '</strong>';} ?>
                    </td>
                    <td><?=format_username($SenderID, $Username, $Donor, $Warned, $Enabled, $ClassID, false, false, $GroupPermID)?></td>
                    <td><?=time_diff($Date)?></td>
                </tr>
<?php 	} ?>
            </table>
<?php           if ($Section == 'inbox') {  ?>
            <input type="submit" name="read" value="Mark as read" />&nbsp;
            <input type="submit" name="unread" value="Mark as unread" />&nbsp;
<?php 		} ?>
            <input type="submit" name="delete" value="Delete message(s)" />
        </form>
<?php  } ?>
    </div>
    <div class="linkbox"><?=$Pages?></div>
</div>
<?php
show_footer();
