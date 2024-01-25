<?php

//******************************************************************************//
//----------------- Take request -----------------------------------------------//

authorize();

if (!check_perms('site_submit_requests')) error(403);

if ($_POST['action'] != "takenew" &&  $_POST['action'] != "takeedit") {
    error(0);
}

include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');
$bbCode = new \Luminance\Legacy\Text;

$NewRequest = ($_POST['action'] == "takenew");

if (!$NewRequest) {
    $ReturnEdit = true;
}

if ($NewRequest) {
    if (!check_perms('site_submit_requests') || $activeUser['BytesUploaded'] < 250*1024*1024) {
        error(403);
    }
} else {
    $RequestID = $_POST['requestid'];
    if (!is_integer_string($RequestID)) {
        error(0);
    }

    $Request = get_requests([$RequestID]);
    $Request = $Request['matches'][$RequestID];
    if (empty($Request)) {
        error(404);
    }

     list(
         'ID'          => $RequestID,
         'UserID'      => $RequestorID,
         'Username'    => $RequestorName,
         'TimeAdded'   => $timeAdded,
         'LastVote'    => $LastVote,
         'CategoryID'  => $CategoryID,
         'Title'       => $Title,
         'Image'       => $Image,
         'Description' => $Description,
         'FillerID'    => $FillerID,
         'TorrentID'   => $torrentID,
         'TimeFilled'  => $timeFilled,
         'GroupID'     => $GroupID,
         'UploaderID'  => $UploaderID,
         'Anonymous'   => $IsAnon,
         'Tags'        => $Tags
     ) = $Request;

    $VoteArray = get_votes_array($RequestID);
    $VoteCount = count($VoteArray['Voters']);

    $IsFilled = !empty($torrentID);

    $ProjectCanEdit = (check_perms('site_project_team') && !$IsFilled && (($CategoryID == 0)));
    $CanEdit = ((!$IsFilled && $activeUser['ID'] == $RequestorID && $VoteCount < 2) || $ProjectCanEdit || check_perms('site_moderate_requests'));

    if (!$CanEdit) {
        error(403);
    }
}

// Validate
if (empty($_POST['category'])) {
    error("You forgot to enter a category!");
}
$CategoryID = $_POST['category'];
if (!is_integer_string($CategoryID)) {
    error(0);
}

if (empty($_POST['title'])) {
    $Err = "You forgot to enter the title!";
} else {
    $Title = trim($_POST['title']);
}

if (empty($_POST['taglist'])) {
    $Err = "You forgot to enter any tags!";
} else {
    $Tags = trim($_POST['taglist']);
}

if ($NewRequest) {
    if (empty($_POST['amount'])) {
        $Err = "You forgot to enter any bounty!";
    } else {
        $Bounty = trim($_POST['amount']);
        if (!is_integer_string($Bounty)) {
            $Err = "Your entered bounty is not a number";

        } elseif ($Bounty < 100*1024*1024) {
            $Err = "Minumum bounty is 100MB";

        } elseif ($Bounty > ($activeUser['BytesUploaded'] - $activeUser['BytesDownloaded'])) {
            // check users cannot go below 1.0 ratio!
            $Err = "You do not have sufficient upload credit to add " . get_size($Bounty) . " to this request";
        }
    }
}

if (empty($_POST['description'])) {
    $Err = "You forgot to enter any description!";
} else {
    $Description = trim($_POST['description']);
}

if (empty($_POST['image'])) {
    $Image = "";
} else {
      $Result = validate_imageurl($_POST['image'], 12, 255, get_whitelist_regex());
      if ($Result!==TRUE) $Err = display_str($Result);
      else $Image = trim($_POST['image']);
}

$bbCode->validate_bbcode($_POST['description'],  get_permissions_advtags($activeUser['ID']));

if (!empty($Err)) {
    error($Err);
}

if ($NewRequest) {
        $master->db->rawQuery(
            "INSERT INTO requests (UserID, TimeAdded, LastVote, CategoryID, Title, Image, Description, Visible)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $activeUser['ID'],
                sqltime(),
                sqltime(),
                $CategoryID,
                $Title,
                $Image,
                $Description,
            ]
        );

        $RequestID = $master->db->lastInsertID();
} else {
        $master->db->rawQuery(
            "UPDATE requests
                SET CategoryID = ?,
                    Title = ?,
                    Image = ?,
                    Description = ?
              WHERE ID = ?",
            [$CategoryID, $Title, $Image, $Description, $RequestID]
        );
}

//Tags
if (!$NewRequest) {
    $master->db->rawQuery(
        "DELETE
           FROM requests_tags
          WHERE RequestID = ?",
        [$RequestID]
    );
}

$Tags = explode(' ', strtolower($openCategories[$CategoryID]['tag']." ".$Tags));

$TagsAdded = [];
foreach ($Tags as $Tag) {
        $Tag = strtolower(trim($Tag, '.')); // trim dots from the beginning and end
        if (!is_valid_tag($Tag) || !check_tag_input($Tag)) continue;
        $Tag = get_tag_synonym($Tag);
        if (!empty($Tag)) {
            if (!in_array($Tag, $TagsAdded)) { // and to create new tags as Uses=1 which seems more correct
                $TagsAdded[] = $Tag;
                $master->db->rawQuery(
                    "INSERT INTO tags (Name, UserID, Uses)
                          VALUES (?, ?, 1)
                              ON DUPLICATE KEY
                          UPDATE Uses = Uses + 1",
                    [$Tag, $activeUser['ID']]
                );
                $TagID = $master->db->lastInsertID();

                $master->db->rawQuery(
                    "INSERT IGNORE INTO requests_tags (TagID, RequestID)
                                 VALUES (?, ?)",
                    [$TagID, $RequestID]
                );
            }
        }
}
// replace the original tag array with corrected tags
$Tags = $TagsAdded;

if ($NewRequest) {
    //Remove the bounty and create the vote
    $master->db->rawQuery(
        "INSERT INTO requests_votes (RequestID, UserID, Bounty)
              VALUES (?, ?, ?)",
        [$RequestID, $activeUser['ID'], $Bounty]
    );

    $master->db->rawQuery(
        "UPDATE users_main
            SET Uploaded = (Uploaded - ?)
          WHERE ID = ?",
        [$Bounty, $activeUser['ID']]
    );
    $master->cache->deleteValue('user_stats_'.$activeUser['ID']);
    $master->cache->deleteValue('_entity_User_legacy_'.$activeUser['ID']);
    $master->cache->deleteValue('recent_requests_'.$activeUser['ID']);

    write_user_log($activeUser['ID'], "Removed -". get_size($Bounty). " for new request [url=/requests.php?action=view&id={$RequestID}]{$Title}[/url]");

    $Announce = "'".$Title."' - http://".SITE_URL."/requests.php?action=view&id=".$RequestID." - ".implode(" ", $Tags);

    $Announce = "'".$Title."' - https://".SSL_SITE_URL."/requests.php?action=view&id=".$RequestID." - ".implode(" ", $Tags);
    $master->irker->announceRequest("Help a user out by filling their request! " .$Announce);

    write_log("Request $RequestID ($Title) created with " . get_size($Bounty). " bounty by ".$activeUser['Username']);

} else {
    $master->cache->deleteValue('request_'.$RequestID);

    // Resolve what changed
    $ChangedFields = [];
    if (!empty(array_diff($Request['Tags'], $Tags))) { $ChangedFields[] = 'Tags'; }
    if ($Request['CategoryID'] != $CategoryID) { $ChangedFields[] = 'CategoryID'; }
    if ($Request['Title'] != $Title) { $ChangedFields[] = 'Title'; }
    if ($Request['Image'] != $Image) { $ChangedFields[] = 'Image'; }
    if ($Request['Description'] != $Description) { $ChangedFields[] = 'Description'; }

    $LogDetails = implode(', ', $ChangedFields);
    write_log("Request {$RequestID} was edited by {$activeUser['Username']} ({$LogDetails})");
}

update_sphinx_requests($RequestID);

header('Location: requests.php?action=view&id='.$RequestID);
