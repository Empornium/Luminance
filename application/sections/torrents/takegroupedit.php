<?php
authorize();

include(SERVER_ROOT.'/classes/class_text.php');
include(SERVER_ROOT . '/classes/class_validate.php');
include(SERVER_ROOT . '/sections/torrents/functions.php');

$Text = new TEXT;
$Validate = new VALIDATE;

// Quick SQL injection check
if (!$_REQUEST['groupid'] || !is_number($_REQUEST['groupid'])) {
    error(404);
}
// End injection check
$GroupID = (int) $_REQUEST['groupid'];

//check user has permission to edit
$CanEdit = check_perms('torrents_edit');

if (!$CanEdit) {
    $DB->query("SELECT UserID, Time FROM torrents WHERE GroupID='$GroupID'");
    list($AuthorID, $AddedTime) = $DB->next_record();
    if ($LoggedUser['ID'] == $AuthorID) {
        if (  check_perms ('site_edit_torrents') &&
             (check_perms ('site_edit_override_timelock') || time_ago($AddedTime)< TORRENT_EDIT_TIME) ) {

            $CanEdit = true;
        } else {
            error("Sorry - you only have ". date('z\d\a\y\s i\m\i\n\s', TORRENT_EDIT_TIME). "  to edit your torrent before it is automatically locked.");
        }
    }
}

//check user has permission to edit
if (!$CanEdit) { error(403); }

// Variables for database input - with edit, the variables are passed with POST
$OldCategoryID = (int) $_POST['oldcategoryid'];
$CategoryID = (int) $_POST['categoryid'];
$Body = $_POST['body'];
$Image = $_POST['image'];

$Text->validate_bbcode($_POST['body'],  get_permissions_advtags($LoggedUser['ID']));

$whitelist_regex = get_whitelist_regex();

$Validate->SetFields('image', '0', 'image', 'The image URL you entered was not valid.', array('regex' => $whitelist_regex, 'maxlength' => 255, 'minlength' => 12));

$Validate->SetFields('body', '1', 'desc', 'Description', array('minimages'=>1, 'regex' => $whitelist_regex, 'maxlength' => 1000000, 'minlength' => 20));

$Err = $Validate->ValidateForm($_POST, $Text); // Validate the form

if ($Err) { // Show the upload form, with the data the user entered
    $HasDescriptionData = TRUE; /// tells editgroup to use $Body and $Image vars instead of requerying them
    $_GET['groupid'] = $GroupID;
    $Name = $_POST['name'];
    $AuthorID = $_POST['authorid'];
    $EditSummary = $_POST['summary'];
    include(SERVER_ROOT . '/sections/torrents/editgroup.php');
    die();
}

// Trickery
if (!preg_match("/^".URL_REGEX."$/i", $Image)) {
        $Image = '';
}

$TorrentCache = get_group_info($GroupID, true);
$GroupName = $TorrentCache[0][0][3];

$Image = db_string($Image);
$SearchText = db_string(trim($GroupName) . ' ' . $Text->db_clean_search(trim($Body)));
$Body =  db_string($Body);

// Update torrents table
$DB->query("UPDATE torrents_group SET
    NewCategoryID='$CategoryID',
        Body='$Body',
    Image='$Image',
        SearchText='$SearchText'
    WHERE ID='$GroupID'");

// The category has been changed, update the category tag
if ($OldCategoryID != $CategoryID) {
    $OldTag = $NewCategories[$OldCategoryID]['tag'];
    $NewTag = $NewCategories[$CategoryID]['tag'];

    // Remove the old tag
    $DB->query("DELETE tt, ttv
                FROM torrents_tags AS tt
                INNER JOIN tags t ON tt.TagID=t.ID
                LEFT JOIN torrents_tags_votes AS ttv ON ttv.TagID=tt.TagID AND ttv.GroupID='$GroupID'
                WHERE t.name='$OldTag' AND tt.GroupID='$GroupID'");

    $DB->query("UPDATE tags SET Uses=Uses-1 WHERE Name='$OldTag'");

    // And insert the new one.
    $DB->query("INSERT INTO tags
                (Name, UserID, Uses) VALUES
                ('" . $NewTag . "', $LoggedUser[ID], 1)
                ON DUPLICATE KEY UPDATE Uses=Uses+1;
            ");

    $TagID = $DB->inserted_id();

    if (empty($LoggedUser['NotVoteUpTags'])) {

        $DB->query("INSERT INTO torrents_tags
                    (TagID, GroupID, UserID, PositiveVotes) VALUES
                    ($TagID, $GroupID, $LoggedUser[ID], 9)
                    ON DUPLICATE KEY UPDATE PositiveVotes=PositiveVotes+1; ");

        $DB->query("INSERT IGNORE INTO torrents_tags_votes (TagID, GroupID, UserID, Way) VALUES
                                ($TagID, $GroupID, $LoggedUser[ID], 'up');");
    } else {

        $DB->query("INSERT IGNORE INTO torrents_tags
                    (TagID, GroupID, UserID, PositiveVotes) VALUES
                    ($TagID, $GroupID, $LoggedUser[ID], 8); ");

    }
}

// There we go, all done!
$Cache->delete_value('torrents_details_'.$GroupID);
$DB->query("SELECT CollageID FROM collages_torrents WHERE GroupID='$GroupID'");
if ($DB->record_count()>0) {
    while (list($CollageID) = $DB->next_record()) {
        $Cache->delete_value('collage_'.$CollageID);
    }
}

update_hash($GroupID);

//Fix Recent Uploads/Downloads for image change
$DB->query("SELECT DISTINCT UserID
            FROM torrents AS t
            LEFT JOIN torrents_group AS tg ON t.GroupID=tg.ID
            WHERE tg.ID = $GroupID");

$UserIDs = $DB->collect('UserID');
foreach ($UserIDs as $UserID) {
    $RecentUploads = $Cache->get_value('recent_uploads_'.$UserID);
    if (is_array($RecentUploads)) {
        foreach ($RecentUploads as $Key => $Recent) {
            if ($Recent['ID'] == $GroupID) {
                if ($Recent['Image'] != $Image) {
                    $Recent['Image'] = $Image;
                    $Cache->begin_transaction('recent_uploads_'.$UserID);
                    $Cache->update_row($Key, $Recent);
                    $Cache->commit_transaction(0);
                }
            }
        }
    }
}

$DB->query("SELECT ID FROM torrents WHERE GroupID = ".$GroupID);
$TorrentIDs = implode(",", $DB->collect('ID'));
$DB->query("SELECT DISTINCT uid FROM xbt_snatched WHERE fid IN (".$TorrentIDs.")");
$Snatchers = $DB->collect('uid');
foreach ($Snatchers as $UserID) {
    $RecentSnatches = $Cache->get_value('recent_snatches_'.$UserID);
    if (is_array($RecentSnatches)) {
        foreach ($RecentSnatches as $Key => $Recent) {
            if ($Recent['ID'] == $GroupID) {
                if ($Recent['Image'] != $Image) {
                    $Recent['Image'] = $Image;
                    $Cache->begin_transaction('recent_snatches_'.$UserID);
                    $Cache->update_row($Key, $Recent);
                    $Cache->commit_transaction(0);
                }
            }
        }
    }
}

$DB->query("SELECT NewCategoryID, Name, Body, Image FROM torrents_group WHERE ID=$GroupID");
list($OrigCatID, $OrigName, $OrigBody, $OrigImage) = $DB->next_record();

if ($CategoryID != $OrigCatID) {
    $LogDetails = "Category";
    $Concat = ', ';
}
if ($Body != $OrigBody) {
    $LogDetails .= "{$Concat}Description";
    $Concat = ', ';
}
if($Image != $OrigImage) $LogDetails .= "{$Concat}Image";

if($_POST['summary'] != '') $Summary = db_string(" ({$_POST['summary']})");
else $Summary='';

write_log("Torrent $TorrentIDs ($OrigName) was edited by ".$LoggedUser['Username']." ($LogDetails)"); //in group $GroupID
write_group_log($GroupID, $TorrentIDs, $LoggedUser['ID'], "Torrent edited: $LogDetails$Summary", 0);

header("Location: torrents.php?id=".$GroupID."&did=1");
