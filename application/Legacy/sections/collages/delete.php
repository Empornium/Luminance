<?php

$CollageID = $_GET['collageid'];
if (!is_number($CollageID) || !$CollageID) {
    error(404);
}

$DB->query("SELECT Name, UserID, CategoryID FROM collages WHERE ID='$CollageID'");
list($Name, $CreatorID, $CategoryID) = $DB->next_record();

// if user has permission to delete collages then no further checks needed
if (!check_perms('site_collages_delete') ) {
    // if not the user then cannot delete
    if ($CreatorID != $LoggedUser['ID']) error(403);

    if ($CategoryID !=0) {  // if personal cat then user can delete
        $DB->query("SELECT DISTINCT UserID FROM collages_torrents WHERE CollageID='$CollageID'");
        $NumUsers = $DB->record_count();
        if ($NumUsers == 1) {
            $UserIDs = $DB->collect('UserID');
            if ( !in_array($CreatorID, $UserIDs)) error("You cannot delete a collage that other users have contributed torrents to");
        } elseif ($NumUsers >1) {
            error("You cannot delete a collage that other users have contributed torrents to");
        }
    }
}

show_header('Delete collage');
?>
<div class="thin center">
    <div class="box" style="width:600px; margin:0px auto;">
        <div class="head colhead">
            Delete collage
        </div>
        <div class="pad">
            <form action="collages.php" method="post">
                <input type="hidden" name="action" value="take_delete" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="collageid" value="<?=$CollageID?>" />
                <strong>Reason: </strong>
                <input type="text" name="reason" size="30" />
                <input value="Delete" type="submit" />
            </form>
        </div>
    </div>
</div>
<?php
show_footer();
