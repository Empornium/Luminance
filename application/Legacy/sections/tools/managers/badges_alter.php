<?php

if (!check_perms('admin_manage_badges')) { error(403); }

authorize();

if (isset($_POST['delselected'])) {

    if (isset($_POST['deleteids'])) {

        $BadgeIDs = $_POST['deleteids'];
        if (!is_array($BadgeIDs)) error("Nothing selected to delete");

        foreach ($BadgeIDs as $bID) {
            if (!is_integer_string($bID))  error(0);
        }
        $inQuery = implode(', ', array_fill(0, count($BadgeIDs), '?'));
        $users = $master->db->rawQuery(
            "SELECT SQL_CALC_FOUND_ROWS DISTINCT UserID FROM users_badges WHERE BadgeID IN ({$inQuery})",
            $BadgeIDs
        )->fetchAll(\PDO::FETCH_BOTH);
        if ($master->db->foundRows() > 0) {
            foreach ($users as $userID) {
                  $master->cache->deleteValue('user_badges_ids_'.$userID[0]);
                  $master->cache->deleteValue('user_badges_'.$userID[0]);
                  $master->cache->deleteValue('user_badges_'.$userID[0].'_limit');
            }

            $master->db->rawQuery("DELETE FROM users_badges WHERE BadgeID IN ({$inQuery})", $BadgeIDs);
        }

        $master->db->rawQuery("DELETE FROM badges WHERE ID IN ({$inQuery})", $BadgeIDs);
        $ReturnID = 'editbadges'; // return user to edit items on return
    }
} else {
    if (!is_array($_POST['id'])) error("Nothing selected to add");

    $Val->OnlyValidateKeys = $_POST['id'];

    $Val->SetFields('badge', '1', 'regex', 'The badge field must be set and has a min length of 2 and a max length of 12 characters. Valid chars are A-Z,a-z,0-9 only. Awards with the same badge field are part of a set and must have different ranks', ['regex'=>'/^[A-Za-z0-9]{2,12}$/']);
    $Val->SetFields('title', '1', 'string', 'The name must be set, and has a max length of 64 characters', ['maxlength'=>64, 'minlength'=>1]);
    $Val->SetFields('desc', '1', 'string', 'The description must be set, and has a max length of 255 characters', ['maxlength'=>255, 'minlength'=>1]);
    $Val->SetFields('image', '1', 'string', 'The image must be set.', ['minlength'=>1]);
    $Val->SetFields('type', '1', 'inarray', 'Invalid badge type was set.', ['inarray'=>$badgeTypes]);

    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    $BadgeIDs = $_POST['id'];
    $NewRanks = [];
    $NewSorts = [];
    $SQL_values = [];
    $params = [];

    foreach ($BadgeIDs as $BadgeID) {
        if (isset($_POST['saveall'])) {
            if (!is_integer_string($BadgeID))  error(0);
            if (!$ReturnID) $ReturnID = $BadgeID; // return user to first edited badge on return
        }
        $Badge = $_POST['badge'][$BadgeID];
        $Title = $_POST['title'][$BadgeID];
        $Desc = $_POST['desc'][$BadgeID];
        $Image = $_POST['image'][$BadgeID];
        $Type = $_POST['type'][$BadgeID];
        $DisplayRow = (int) $_POST['row'][$BadgeID];
        $Rank = (int) $_POST['rank'][$BadgeID];
        if ($Rank < 1) $Rank = 1;
        $Sort = (int) $_POST['sort'][$BadgeID];
        $Cost = (int) $_POST['cost'][$BadgeID];

        // automagically constrain badge/rank
        if (isset($_POST['saveall'])) $Ranks = $master->db->rawQuery("SELECT Rank FROM badges WHERE Badge = ? AND ID != ?", [$Badge, $BadgeID])->fetchAll(\PDO::FETCH_COLUMN);
        else $Ranks = $master->db->rawQuery("SELECT Rank FROM badges WHERE Badge = ?", [$Badge])->fetchAll(\PDO::FETCH_COLUMN);

        while (in_array($Rank, $Ranks) || (isset($NewRanks[$Badge]) && $NewRanks[$Badge] >= $Rank)) {
            $Rank++;
        }
        $NewRanks[$Badge]=$Rank;

        // automagically constrain sort
        if (isset($_POST['saveall'])) $Sorts = $master->db->rawQuery("SELECT Sort FROM badges WHERE ID != ?", [$BadgeID])->fetchAll(\PDO::FETCH_COLUMN);
        else $Sorts = $master->db->rawQuery("SELECT Sort FROM badges")->fetchAll(\PDO::FETCH_COLUMN);

        while (in_array($Sort, $Sorts) || in_array($Sort, $NewSorts)) {
            $Sort++;
        }
        $NewSorts[] = $Sort;

        if (isset($_POST['create'])) {    // create

        $SQL_values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = array_merge($params, [$Badge, $Rank, $Type, $DisplayRow, $Sort, $Cost, $Title, $Desc, $Image]);

        } elseif (isset($_POST['saveall'])) { //Edit

            $master->db->rawQuery("UPDATE badges SET
                              Badge = ?,
                              Rank = ?,
                              Type = ?,
                              Display = ?,
                              Sort = ?,
                              Cost = ?,
                              Title = ?,
                              Description = ?,
                              Image = ?
                              WHERE ID = ?",
                [$Badge, $Rank, $Type, $DisplayRow, $Sort, $Cost, $Title, $Desc, $Image, $BadgeID]
            );
        }
    }

    if (isset($_POST['create']) && count($SQL_values) > 0) {   //Create
            $SQL_values = implode(', ', $SQL_values);
        $master->db->rawQuery("INSERT IGNORE INTO badges
            (Badge, Rank, Type, Display, Sort, Cost, Title, Description, Image)
            VALUES {$SQL_values}",
            $params
        );
            $ReturnID = $master->db->lastInsertID(); // return user to first saved badge on return
    }
}

$master->cache->deleteValue('available_badges');

if (isset($_REQUEST['numadd'])) { // set num add forms to be same as current
    $numAdds = (int) $_REQUEST['numadd'];
    if ($numAdds<1 || $numAdds > 20) $numAdds = 1;
    $UrlExtra = "&numadd=$numAdds";
}

if (isset($_REQUEST['returntop'])) $ReturnID='';
else $ReturnID = "#$ReturnID";
// Go back
header("Location: tools.php?action=badges_list$UrlExtra$ReturnID");
