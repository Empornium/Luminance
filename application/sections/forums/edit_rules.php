<?php
enforce_login();
if (!check_perms('site_moderate_forums')) {
    error(403);
}

$ForumID = $_GET['forumid'];
if (!is_number($ForumID)) {
    error(404);
}

if (!empty($_POST['add']) || (!empty($_POST['del']))) {
    if (!empty($_POST['add'])) {
        if (is_number($_POST['new_thread'])) {

            $ThreadID = (int) $_POST['new_thread'];
            $DB->query("SELECT ID FROM forums_topics WHERE ID = $ThreadID" );
            if ($DB->record_count()==0) $ResultMessage ="No thread with id=$ThreadID!";
            else $DB->query("INSERT INTO forums_specific_rules (ForumID, ThreadID) VALUES ( $ForumID, $ThreadID)");
        }
    }
    if (!empty($_POST['del'])) {
        if (is_number($_POST['threadid'])) {
            $DB->query("DELETE FROM forums_specific_rules WHERE ForumID = " . $ForumID . " AND ThreadID = " . $_POST['threadid']);
        }
    }
    $Cache->delete_value('forums_list');
}

$DB->query("SELECT ThreadID , Title
              FROM forums_specific_rules AS fs LEFT JOIN forums_topics AS ft ON ft.ID=fs.ThreadID
             WHERE fs.ForumID = $ForumID" );

$ThreadIDs = $DB->to_array();

show_header();
?>
<div class="thin">
    <?php if ($ResultMessage) { ?>
            <div id="messagebar" class="messagebar alert"><?=$ResultMessage?></div>
    <?php } ?>
    <div class="head">
        <a href="forums.php">Forums</a>
        &gt;
        <a href="forums.php?action=viewforum&amp;forumid=<?= $ForumID ?>"><?= $Forums[$ForumID]['Name'] ?></a>
        &gt;
        Edit forum specific rules
    </div>
    <div class="box pad">
        ThreadID's entered here are shown at the top of the forum page in the 'forum specific rules' box
    </div>
    <table>
        <tr class="colhead">
            <td colspan="2">Thread ID</td>
            <td></td>
        </tr>
        <tr>
        <form action="" method="post">
            <td colspan="2">
                <input name="new_thread" type="text" size="8" />
            </td>
            <td>
                <input type="submit" name="add" value="Add thread" />
            </td>
        </form>
        <?php foreach ($ThreadIDs as $ThreadID) { ?>
            <tr>
                <td><?= $ThreadID[0] ?></td>
                <td><?= $ThreadID[1] ?></td>
                <td>
                    <form action="" method="post">
                        <input type="hidden" name="threadid" value="<?= $ThreadID[0] ?>" />
                        <input type="submit" name="del" value="Delete link" />
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
</div>
<?php
show_footer();
