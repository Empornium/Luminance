<?php
if (isset($_GET['userid']) && check_perms('users_view_invites')) {
    if (!is_number($_GET['userid'])) {
        error(403);
    }

    $UserID=$_GET['userid'];
    $Sneaky = true;
} else {
    if (!$UserCount = $Cache->get_value('stats_user_count')) {
        $DB->query("SELECT COUNT(ID) FROM users_main WHERE Enabled='1'");
        list($UserCount) = $DB->next_record();
        $Cache->cache_value('stats_user_count', $UserCount, 0);
    }

    $UserID = $LoggedUser['ID'];
    $Sneaky = false;
}

list($UserID, $Username, $PermissionID) = array_values(user_info($UserID));

$Tree = new Luminance\Legacy\InviteTree($UserID);

show_header($Username.' &gt; Invites &gt; Tree');
?>
<div class="thin">
    <h2><?=format_username($UserID, $Username)?> &gt; <a href="/users/<?=$UserID?>/invite">Invites</a> &gt; Tree</h2>
    <div class="box pad">
<?php   $Tree->make_tree(); ?>
    </div>
</div>
<?php
show_footer();
