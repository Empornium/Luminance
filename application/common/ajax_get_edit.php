<?php
if (!check_perms('site_moderate_forums')) {
    error(403, true);
}

if (empty($_GET['postid']) || !is_number($_GET['postid'])) error(0, true);
if (!isset($_GET['depth']) || !is_number($_GET['depth'])) error(0, true);
if (empty($_GET['type']) || !in_array($_GET['type'], array('forums', 'collages', 'requests', 'torrents', 'staffpm'))) error(0, true);

$PostID = $_GET['postid'];
$Depth  = $_GET['depth'];
$Type   = $_GET['type'];

$Text = new Luminance\Legacy\Text;

$Edits = $master->cache->get_value($Type.'_edits_'.$PostID);
if (!is_array($Edits)) {
    $Edits = $master->db->raw_query("SELECT ce.EditUser, um.Username, ce.EditTime, ce.Body
                                       FROM comments_edits AS ce
                                       JOIN users_main AS um ON um.ID=ce.EditUser
                                      WHERE PostID = :postid
                                        AND Page   = :type
                                   ORDER BY ce.EditTime DESC",
                                            [':postid' => $PostID,
                                             ':type'   => $Type])->fetchAll(\PDO::FETCH_ASSOC);

    $master->cache->cache_value($Type.'_edits_'.$PostID, $Edits, 0);
}

if ($Depth != 0) {
    $Body = $Edits[$Depth - 1]['Body'];
} else {
    //Not an edit, have to get from the original
    switch ($Type) {
        case 'forums' :
            //Get from normal forum stuffs
            $Body = $master->db->raw_query("SELECT Body FROM forums_posts WHERE ID=:postid",[':postid'=>$PostID])->fetchColumn();
            break;
        case 'collages' :
        case 'requests' :
        case 'torrents' :
            //if (!in_array($Type, array('collages', 'requests', 'torrents'))) error(0, true);
            $Body = $master->db->raw_query("SELECT Body FROM {$Type}_comments WHERE ID=:postid",[':postid'=>$PostID])->fetchColumn();
            break;
        case 'staffpm' :
            $Body = $master->db->raw_query("SELECT Message FROM staff_pm_messages WHERE ID=:postid",[':postid'=>$PostID])->fetchColumn();
            break;
    }
}
?>
            <div class="post_content"><?=$Text->full_format($Body, true)?></div>
            <div class="post_footer">
<?php   if ($Depth < count($Edits)) { ?>
                <a href="#edit_info_<?=$PostID?>" onclick="LoadEdit('<?=$Type?>', <?=$PostID?>, <?=($Depth + 1)?>); return false;">&laquo;</a>
                <span class="editedby"><?=(($Depth == 0) ? 'Last edited by' : 'Edited by')?>
                <?=format_username($Edits[$Depth]['EditUser']) ?> <?=time_diff($Edits[$Depth]['EditTime'],2,true,true)?>
                </span>
<?php   } else {      ?>
                <em>Original Post</em>
<?php   }
        if ($Depth > 0) { ?>
                <span class="editedby">
                    <a href="#edit_info_<?=$PostID?>" onclick="LoadEdit('<?=$Type?>', <?=$PostID?>, <?=($Depth - 1)?>); return false;">&raquo;</a>
                </span>
<?php   }

        if ($Type == 'forums' && $Depth == 0 && check_perms('site_admin_forums')) { ?>
                &nbsp;&nbsp;<a href="#content<?=$PostID?>" onclick="RevertEdit(<?=$PostID?>); return false;" title="remove last edit">&reg;</a>
<?php   }   ?>
            </div>
