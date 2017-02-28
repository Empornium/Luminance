<?php
if (!check_perms('site_moderate_forums')) {
    error(403);
}

if (empty($_GET['postid']) || !is_number($_GET['postid'])) {
    die();
}

$PostID = $_GET['postid'];

if (!isset($_GET['depth']) || !is_number($_GET['depth'])) {
    die();
}

$Depth = $_GET['depth'];

if (empty($_GET['type']) || !in_array($_GET['type'], array('forums', 'collages', 'requests', 'torrents', 'staffpm'))) {
    die();
}
$Type = $_GET['type'];

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;

$Edits = $Cache->get_value($Type.'_edits_'.$PostID);
if (!is_array($Edits)) {
    $DB->query("SELECT ce.EditUser, um.Username, ce.EditTime, ce.Body
            FROM comments_edits AS ce
                JOIN users_main AS um ON um.ID=ce.EditUser
            WHERE Page = '".$Type."' AND PostID = ".$PostID."
            ORDER BY ce.EditTime DESC");
    $Edits = $DB->to_array();
    $Cache->cache_value($Type.'_edits_'.$PostID, $Edits, 0);
}

list($UserID, $Username, $Time) = $Edits[$Depth];
if ($Depth != 0) {
    list(,,,$Body) = $Edits[$Depth - 1];
} else {
    //Not an edit, have to get from the original
    switch ($Type) {
        case 'forums' :
            //Get from normal forum stuffs
            $DB->query("SELECT Body
                    FROM forums_posts
                    WHERE ID=$PostID");
            list($Body) = $DB->next_record();
            break;
        case 'collages' :
        case 'requests' :
        case 'torrents' :
            $DB->query("SELECT Body
                    FROM ".$Type."_comments
                    WHERE ID=$PostID");
            list($Body) = $DB->next_record();
            break;
        case 'staffpm' :
            $DB->query("SELECT Message
                    FROM staff_pm_messages
                    WHERE ID=$PostID");
            list($Body) = $DB->next_record();
            break;
    }
}
// Set container separately in case we read from cache
    switch ($Type) {
        case 'forums' :
            $Container='post_content';
            break;
        case 'collages' :
        case 'requests' :
        case 'torrents' :
            $Container='post_content';
            break;
        case 'staffpm' :
            $Container='body';
            break;
    }
?>

                <div class="<?=$Container?>"><?=$Text->full_format($Body, true)?></div>
                        <div class="post_footer">
<?php if ($Depth < count($Edits)) { ?>
                    <a href="#edit_info_<?=$PostID?>" onclick="LoadEdit('<?=$Type?>', <?=$PostID?>, <?=($Depth + 1)?>); return false;">&laquo;</a>
                    <span class="editedby"><?=(($Depth == 0) ? 'Last edited by' : 'Edited by')?>
                    <?=format_username($UserID, $Username) ?> <?=time_diff($Time,2,true,true)?>
                              </span>
<?php } else { ?>
                                  <em>Original Post</em>
<?php }

if ($Depth > 0) { ?>
                              <span class="editedby">
                                  <a href="#edit_info_<?=$PostID?>" onclick="LoadEdit('<?=$Type?>', <?=$PostID?>, <?=($Depth - 1)?>); return false;">&raquo;</a>
                              </span>
<?php } ?>

                        </div>
