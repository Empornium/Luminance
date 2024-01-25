<?php
if (!check_perms('admin_manage_awards')) { error(403); }

authorize();

if (isset($_REQUEST['numadd'])) { // set num add forms to be same as current
    $numAdds = (int) $_REQUEST['numadd'];
    if ($numAdds<1 || $numAdds > 20) $numAdds = 1;
    $UrlExtra = "&numadd=$numAdds";
}

if (isset($_POST['createcats'])) {

    $SQL_values = [];
    $params = [];
    $Results = [];
    $Results[] = "reserve";
    $Cats = [];

    $results = $master->db->rawQuery(
        "SELECT id, name
           FROM categories"
    )->fetchAll(\PDO::FETCH_NUM);
    foreach ($results as $result) {
        list($catId, $name) = $result;
        $name = str_replace(' ', '', $name);
        $nparts = explode('/', $name);
        $nparts = explode('-', $nparts[0]);
        $Cats[strtolower($nparts[0])] = $catId;
    }

    $results = $master->db->rawQuery(
        "SELECT ID, Badge, Rank
           FROM badges
          WHERE Display = '1'
       ORDER BY Sort"
    )->fetchAll(\PDO::FETCH_NUM);

    foreach ($results as $result) {
        list($BadgeId, $Badge, $Rank) = $result;
        $Badge = strtolower($Badge);
        if (isset( $Cats[$Badge])) {
            $CatId = $Cats[$Badge];
            switch ($Rank) {
                case 1:
                    $Value = 50;
                    break;

                case 2:
                    $Value = 100;
                    break;

                default:
                    $Value = 250;
            }
            $SQL_values[] = "(?, 'NumUploaded', '1', '1', ?, ?)";
            $params = array_merge($params, [$BadgeID, $Value, $CatId]);
            $Results[] = "Created schedule item for ".ucfirst($Badge)." Rank $Rank";
        } else {
            $Results[] = "Could not find category match for ".ucfirst($Badge);
        }
    }

    $numinsert = count($SQL_values);
    if ($numinsert>0) {   //Create
            $master->db->rawQuery("DELETE FROM badges_auto WHERE CategoryID!=0");

            $SQL_values = implode(', ', $SQL_values);
        $master->db->rawQuery("INSERT INTO badges_auto
            (BadgeID, Action, Active, SendPM, Value, CategoryID)
            VALUES {$SQL_values}",
            $params
        );
        $ReturnID = $master->db->lastInsertID(); // return user to first saved item on return
    }

    if (count($Results)>1) {
        $Results[0] = "Wrote $numinsert schedule items.";
        $Results[] = '<br/><br/><a href="/tools.php?action=awards_auto'.$UrlExtra.'">back to awards schedule mamager</a>';
        error(implode("<br/>", $Results));
    }

} elseif (isset($_POST['delselected'])) {

    $AwardIDs = $_POST['deleteids'];
    if (!is_array($AwardIDs)) error("Nothing selected to delete");

    foreach ($AwardIDs as $bID) {
        if (!is_integer_string($bID))  error(0);
    }
    $inQuery = implode(', ', array_fill(0, count($AwardIDs), '?'));
    $master->db->rawQuery("DELETE FROM badges_auto WHERE ID IN ({$inQuery})", $AwardIDs);

    $ReturnID = 'editawards'; // return user to edit items on return

} else {

    if (!is_array($_POST['id'])) error("Nothing selected to add");
    $AwardIDs = $_POST['id'];

    $ValidBadgeIDs = $master->db->rawQuery("SELECT ID FROM badges")->fetchAll(\PDO::FETCH_COLUMN);

    $Val->OnlyValidateKeys = $_POST['id'];

    $Val->SetFields('badgeid', '1', 'inarray', 'The badge ', ['inarray' => $ValidBadgeIDs]);
    $Val->SetFields('type', '1', 'inarray', 'Invalid award action was set.',['inarray' => $autoAwardTypes]);
    $Val->SetFields('value', '1', 'number', 'The value must be a valid number.', ['allowcomma' => 1]);

    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

    $SQL_values = [];
    $params = [];
    foreach ($AwardIDs as $AwardID) {
        if (isset($_POST['saveall'])) {
            if (!is_integer_string($AwardID))  error(0);
            if (!$ReturnID) $ReturnID = $AwardID; // return user to first edited item on return
        }

        if (!in_array($_POST['type'][$AwardID], $autoAwardTypes)) { error(0); }

        $BadgeId = (int) $_POST['badgeid'][$AwardID];
        $Action = $_POST['type'][$AwardID];
        $Active = $_POST['active'][$AwardID] == 1 ? 1 : 0;
        $SendPm = $_POST['sendpm'][$AwardID] == 1 ? 1 : 0;
        $Value = (int) $_POST['value'][$AwardID];
        $CatId = (int) $_POST['catid'][$AwardID];

        if (isset($_POST['create'])) {    // create

        $SQL_values[] = "(?, ?, ?, ?, ?, ?)" ;
        $params = array_merge($params, [$BadgeID, $Action, $Active, $SendPm, $Value, $CatID]);

        } elseif (isset($_POST['saveall'])) { //Edit

            $master->db->rawQuery("UPDATE badges_auto SET
                              BadgeID = ?,
                              Action = ?,
                              Active = ?,
                              SendPM = ?,
                              Value = ?,
                              CategoryID = ?,
                              WHERE ID = ?",
                [$BadgeId, $Action, $Active, $SendPM, $Value, $CatId, $AwardID]
            );
        }
    }

    if (isset($_POST['create']) && count($SQL_values) > 0) {   //Create
            $SQL_values = implode(', ', $SQL_values);
        $master->db->rawQuery("INSERT INTO badges_auto
            (BadgeID, Action, Active, SendPM, Value, CategoryID)
            VALUES {$SQL_values}",
            $params
        );
            $ReturnID = $master->db->lastInsertID(); // return user to first saved item on return
    }

}

if ($_POST['returntop']==1) $ReturnID='';
else $ReturnID = "#$ReturnID";

header("Location: tools.php?action=awards_auto$UrlExtra$ReturnID");
