<?php

if (!check_perms('site_manage_badges')) { error(403); }

authorize();

if (isset($_POST['delselected'])) {

    if (isset($_POST['deleteids'])) {

        $BadgeIDs = $_POST['deleteids'];
        if (!is_array($BadgeIDs)) error("Nothing selected to delete");

        foreach ($BadgeIDs as $bID) {
            if (!is_number($bID))  error(0);
        }
        $BadgeIDs = implode(',', $BadgeIDs);

        $DB->query("SELECT DISTINCT UserID FROM users_badges WHERE BadgeID IN ($BadgeIDs)");
        if ($DB->record_count()>0) {
            $Users = $DB->to_array();

            foreach ($Users as $UserID) {
                  $Cache->delete_value('user_badges_ids_'.$UserID[0]);
                  $Cache->delete_value('user_badges_'.$UserID[0]);
                  $Cache->delete_value('user_badges_'.$UserID[0].'_limit');
            }

            $DB->query("DELETE FROM users_badges WHERE BadgeID IN ($BadgeIDs)");
        }

        $DB->query("DELETE FROM badges WHERE ID IN ($BadgeIDs)");
        $ReturnID = 'editbadges'; // return user to edit items on return
    }
} else {
    if (!is_array($_POST['id'])) error("Nothing selected to add");

    $Val->OnlyValidateKeys = $_POST['id'];

    $Val->SetFields('badge', '1','regex','The badge field must be set and has a min length of 2 and a max length of 12 characters. Valid chars are A-Z,a-z,0-9 only. Awards with the same badge field are part of a set and must have different ranks', array('regex'=>'/^[A-Za-z0-9]{2,12}$/'));

    $Val->SetFields('title', '1','string','The name must be set, and has a max length of 64 characters', array('maxlength'=>64, 'minlength'=>1));
    $Val->SetFields('desc', '1','string','The description must be set, and has a max length of 255 characters', array('maxlength'=>255, 'minlength'=>1));
    $Val->SetFields('image', '1','string','The image must be set.', array('minlength'=>1));
    $Val->SetFields('type', '1','inarray','Invalid badge type was set.',array('inarray'=>$BadgeTypes));

    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    $BadgeIDs = $_POST['id'];
    $NewRanks = array();
    $NewSorts = array();
    $SQL_values = array();

    foreach ($BadgeIDs as $BadgeID) {
        if (isset($_POST['saveall'])) {
            if(!is_number($BadgeID))  error(0);
            if(!$ReturnID) $ReturnID = $BadgeID; // return user to first edited badge on return
        }
        $Badge=db_string($_POST['badge'][$BadgeID]);
        $Title=db_string($_POST['title'][$BadgeID]);
        $Desc=db_string($_POST['desc'][$BadgeID]);
        $Image=db_string($_POST['image'][$BadgeID]);
        $Type=db_string($_POST['type'][$BadgeID]);
        $DisplayRow=(int) $_POST['row'][$BadgeID];
        $Rank=(int) $_POST['rank'][$BadgeID];
        if ($Rank<1) $Rank=1;
        $Sort=(int) $_POST['sort'][$BadgeID];
        $Cost=(int) $_POST['cost'][$BadgeID];

        // automagically constrain badge/rank
        if (isset($_POST['saveall'])) $DB->query("SELECT Rank FROM badges WHERE Badge='$Badge' AND ID !='$BadgeID'");
        else $DB->query("SELECT Rank FROM badges WHERE Badge='$Badge'");

        $Ranks = $DB->collect('Rank');
        while ( in_array($Rank, $Ranks ) || (isset($NewRanks[$Badge]) && $NewRanks[$Badge] >= $Rank ) ) {
            $Rank++;
        }
        $NewRanks[$Badge]=$Rank;

        // automagically constrain sort
        if (isset($_POST['saveall'])) $DB->query("SELECT Sort FROM badges WHERE ID !='$BadgeID'");
        else $DB->query("SELECT Sort FROM badges");

        $Sorts = $DB->collect('Sort');
        while ( in_array($Sort, $Sorts ) || in_array($Sort, $NewSorts )) {
            $Sort++;
        }
        $NewSorts[] = $Sort;

        if ( isset($_POST['create']) ) {    // create

        $SQL_values[] = "('$Badge','$Rank','$Type','$DisplayRow','$Sort','$Cost','$Title','$Desc','$Image')" ;

        } elseif ( isset($_POST['saveall']) ) { //Edit

            $DB->query("UPDATE badges SET
                              Badge='$Badge',
                              Rank='$Rank',
                              Type='$Type',
                              Display='$DisplayRow',
                              Sort='$Sort',
                              Cost='$Cost',
                              Title='$Title',
                              Description='$Desc',
                              Image='$Image'
                              WHERE ID='$BadgeID'");
        }
    }

    if ( isset($_POST['create']) && count($SQL_values)>0 ) {   //Create
            $SQL_values = implode(',', $SQL_values);
        $DB->query("INSERT IGNORE INTO badges
            (Badge, Rank, Type, Display, Sort, Cost, Title, Description, Image)
            VALUES $SQL_values");
            $ReturnID = $DB->inserted_id(); // return user to first saved badge on return
    }
}

$Cache->delete_value('available_badges');

if (isset($_REQUEST['numadd'])) { // set num add forms to be same as current
    $numAdds = (int) $_REQUEST['numadd'];
    if ($numAdds<1 || $numAdds > 20) $numAdds = 1;
    $UrlExtra = "&numadd=$numAdds";
}

if (isset($_REQUEST['returntop'])) $ReturnID='';
else $ReturnID = "#$ReturnID";
// Go back
header("Location: tools.php?action=badges_list$UrlExtra$ReturnID");
