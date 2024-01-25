<?php
authorize();

include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');

use Luminance\Entities\CommentEdit;

$bbCode = new \Luminance\Legacy\Text;
$Validate = new \Luminance\Legacy\Validate;

// Quick SQL injection check
if (!$_REQUEST['groupid'] || !is_integer_string($_REQUEST['groupid'])) {
    error(404);
}
// End injection check
$groupID = (int) $_REQUEST['groupid'];

//check user has permission to edit
$CanEdit = check_perms('torrent_edit');

$Review = get_last_review($groupID);

if (!$CanEdit) {
    list($authorID, $addedTime) = $master->db->rawQuery(
        "SELECT UserID,
                Time
           FROM torrents
          WHERE GroupID = ?",
        [$groupID]
    )->fetch();
    if ($activeUser['ID'] == $authorID) {
        if (  check_perms('site_edit_torrents') &&
             (check_perms('site_edit_override_timelock') || time_ago($addedTime)< TORRENT_EDIT_TIME || $Review['Status'] == 'Warned')) {
            $CanEdit = true;
        } else {
            error("Sorry - you only have ". date('z\d\a\y\s i\m\i\n\s', TORRENT_EDIT_TIME). "  to edit your torrent before it is automatically locked.");
        }
    }
}

//check user has permission to edit
if (!$CanEdit) { error(403); }

// prevent a user from editing a torrent once it is marked as "Okay", but let
// staff edit!
if ($Review['Status'] == 'Okay' && !check_perms('torrent_edit')) {
    if (!check_perms('site_edit_override_review')) {
        error("Sorry - once a torrent has been reviewed by staff and passed it is automatically locked.");
    }
}

// Variables for database input - with edit, the variables are passed with POST
$OldCategoryID = (int) $_POST['oldcategoryid'];
$CategoryID = (int) $_POST['categoryid'];

$bbCode->validate_bbcode($_POST['body'],  get_permissions_advtags($activeUser['ID']), true, false);

$whitelist_regex = get_whitelist_regex();

$Validate->SetFields('categoryid', '1', 'inarray', 'Please select a category.', ['inarray' => array_keys($openCategories)]);
$Validate->SetFields('image', $master->options->RequireCover, 'image', 'The image URL you entered was not valid.', ['regex' => $whitelist_regex, 'maxlength' => 255, 'minlength' => 12]);
$descriptonValidation = [
  'minimages' =>1,
  'regex'     => $whitelist_regex,
  'maxlength' => 1000000,
  'minlength' => 20
];
if (check_perms('torrent_edit')) {
  $descriptonValidation['maximages'] = 65535;
  $descriptonValidation['maximageweight'] = 512*1024*1024;
}
$Validate->SetFields('body', '1', 'desc', 'Description', $descriptonValidation);

$Err = $Validate->ValidateForm($_POST, $bbCode); // Validate the form

if ($Err) { // Show the upload form, with the data the user entered
    $HasDescriptionData = TRUE; /// tells editgroup to use $Body and $Image vars instead of requerying them
    $_GET['groupid'] = $groupID;
    $Name = display_str($_POST['name']);
    $authorID = display_str($_POST['authorid']);
    $EditSummary = display_str($_POST['summary']);
    $Body = display_str($_POST['body']);
    $Image = display_str($_POST['image']);
    include(SERVER_ROOT . '/Legacy/sections/torrents/editgroup.php');
    return;
}

// Trickery
if (!preg_match("/^".URL_REGEX."$/i", ($Image ?? ''))) {
    $Image = '';
}

$TorrentCache = get_group_info($groupID, true);
$GroupName = $TorrentCache[0][0][3];

$Image = $_POST['image'];
$Body =  $_POST['body'];
$SearchText = trim($GroupName) . ' ' . $bbCode->db_clean_search(trim($_POST['body']));


// Normalize line endings
$Body = str_replace("\r\n", "\n", $Body);
$Body = str_replace("\r", "\n", $Body);

# Load group entity & stash old body
$group = $master->repos->torrentgroups->load($groupID);
$oldBody = $group->Body;
$sqltime = sqltime();

# Update group entity
$group->NewCategoryID = $CategoryID;
$group->Body          = $Body;
$group->Image         = $Image;
$group->SearchText    = $SearchText;
$group->EditedUserID  = $userID;
$group->EditedTime    = $sqltime;
$master->repos->torrentgroups->save($group);


$sqltime = sqltime();

$edit = new CommentEdit;
$edit->Page     = 'descriptions';
$edit->PostID   = $groupID;
$edit->EditUser = $userID;
$edit->EditTime = $sqltime;
$edit->Body     = $oldBody;
$master->repos->commentedits->save($edit);

// The category has been changed, update the category tag
if ($OldCategoryID != $CategoryID) {
    $OldTag = $newCategories[$OldCategoryID]['tag'];
    $NewTag = $newCategories[$CategoryID]['tag'];

    // Remove the old tag
    $master->db->rawQuery(
        "DELETE tt, ttv
           FROM torrents_tags AS tt
     INNER JOIN tags t ON tt.TagID = t.ID
      LEFT JOIN torrents_tags_votes AS ttv ON ttv.TagID = tt.TagID AND ttv.GroupID = ?
          WHERE t.name = ?
            AND tt.GroupID = ?",
        [$groupID, $OldTag, $groupID]
    );

    $master->db->rawQuery(
        "UPDATE tags
            SET Uses = Uses - 1
          WHERE Name = ?",
        [$OldTag]
    );

    // And insert the new one.
    $master->db->rawQuery(
        "INSERT INTO tags (Name, UserID, Uses)
              VALUES (?, ?, 1)
                  ON DUPLICATE KEY
              UPDATE Uses = Uses + 1",
        [$NewTag, $activeUser['ID']]
    );

    $TagID = $master->db->lastInsertID();

    # Check if tag already exists
    $TagAdder = $master->db->rawQuery(
        "SELECT UserID
           FROM torrents_tags
          WHERE TagID = ?
            AND GroupID = ?",
        [$TagID, $groupID]
    )->fetchColumn();

    # We need to insert "9" here as all tags start with a single negative vote...
    # stupid stupid stupid!

    $params = [$TagID, $groupID, $activeUser['ID']];
    if (empty($activeUser['NotVoteUpTags'])) {
        if ($TagAdder === $activeUser['ID']) {
            $master->db->rawQuery(
                "INSERT IGNORE INTO torrents_tags (TagID, GroupID, UserID, PositiveVotes)
                             VALUES (?, ?, ?, 9)",
                $params
            );

            $master->db->rawQuery(
                "INSERT IGNORE INTO torrents_tags_votes (TagID, GroupID, UserID, Way)
                             VALUES (?, ?, ?, 'up')",
                $params
            );
        } else {
            $master->db->rawQuery(
                "INSERT INTO torrents_tags (TagID, GroupID, UserID, PositiveVotes)
                      VALUES (?, ?, ?, 9)
                          ON DUPLICATE KEY
                      UPDATE PositiveVotes = PositiveVotes + 4",
                $params
            );

            $master->db->rawQuery(
                "INSERT IGNORE INTO torrents_tags_votes (TagID, GroupID, UserID, Way)
                             VALUES (?, ?, ?, 'up')",
                $params
            );
        }
    } else {
        $master->db->rawQuery(
            "INSERT INTO torrents_tags (TagID, GroupID, UserID, PositiveVotes)
                  VALUES (?, ?, ?, 9)
                      ON DUPLICATE KEY
                  UPDATE PositiveVotes = PositiveVotes + 4",
            $params
        );
    }
}

// There we go, all done!
$master->cache->deleteValue("torrents_details_{$groupID}");

update_hash($groupID);

// Fix Recent Uploads/Downloads for image change
$userIDs = $master->db->rawQuery(
    "SELECT DISTINCT tg.UserID
       FROM torrents AS t
  LEFT JOIN torrents_group AS tg ON t.GroupID = tg.ID
      WHERE tg.ID = ?",
    [$groupID]
)->fetchAll(\PDO::FETCH_COLUMN);

$query = "SELECT ID FROM torrents WHERE GroupID = ?";
$torrentIDs = $master->db->rawQuery($query, [$groupID])->fetchAll(\PDO::FETCH_COLUMN);

foreach ($userIDs as $userID) {
    $RecentUploads = $master->cache->getValue('recent_uploads_'.$userID);
    if (is_array($RecentUploads)) {
        foreach ($RecentUploads as $Key => $Recent) {
            if ($Recent['ID'] == $groupID) {
                if ($Recent['Image'] != $Image) {
                    $Recent['Image'] = $Image;
                    $master->cache->deleteValue('recent_uploads_'.$userID);
                }
            }
        }
    }
}

$Snatchers = $master->db->rawQuery(
    "SELECT DISTINCT uid
       FROM xbt_snatched
      WHERE fid IN (
          SELECT ID
            FROM torrents
           WHERE GroupID = ?
      )",
    [$groupID]
)->fetchAll(\PDO::FETCH_COLUMN);
foreach ($Snatchers as $userID) {
    $RecentSnatches = $master->cache->getValue('recent_snatches_'.$userID);
    if (is_array($RecentSnatches)) {
        foreach ($RecentSnatches as $Key => $Recent) {
            if ($Recent['ID'] == $groupID) {
                if ($Recent['Image'] != $Image) {
                    $Recent['Image'] = $Image;
                    $master->cache->deleteValue('recent_snatches_'.$userID);
                }
            }
        }
    }
}

$record = $master->db->rawQuery(
    "SELECT NewCategoryID,
            Name,
            Body,
            Image
       FROM torrents_group
      WHERE ID = ?",
    [$groupID]
)->fetch(\PDO::FETCH_NUM);
list($OrigCatID, $OrigName, $OrigBody, $OrigImage) = $record;

$LogDetails = '';
$Concat = '';
if ($CategoryID != $OrigCatID) {
    $LogDetails .= "Category";
    $Concat = ', ';
}
if ($Body != $OrigBody) {
    $LogDetails .= "{$Concat}Description";
    $Concat = ', ';
}
if ($Image != $OrigImage) {
    $LogDetails .= "{$Concat}Image";
}

if ($_POST['summary'] != '') $Summary = " ({$_POST['summary']})";
else $Summary='';

$torrentIDs = implode(", ", $torrentIDs);
write_log("Torrent {$torrentIDs} ({$OrigName}) was edited by ".$activeUser['Username']." ({$LogDetails})"); //in group $groupID
write_group_log($groupID, $torrentIDs, $activeUser['ID'], "Torrent edited: {$LogDetails}{$Summary}", 0);

header("Location: torrents.php?id=".$groupID."&did=1");
