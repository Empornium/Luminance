<?php
//******************************************************************************//
//--------------- Take upload --------------------------------------------------//
// This pages handles the backend of the torrent upload function. It checks	 //
// the data, and if it all validates, it builds the torrent file, then writes   //
// the data to the database and the torrent to the disk.						//
//******************************************************************************//

ini_set('upload_max_filesize', MAX_FILE_SIZE_BYTES);
ini_set('max_file_uploads', 100);
require(SERVER_ROOT . '/classes/class_torrent.php');
include(SERVER_ROOT . '/classes/class_validate.php');
include(SERVER_ROOT . '/classes/class_feed.php');
include(SERVER_ROOT . '/classes/class_text.php');
include(SERVER_ROOT . '/sections/torrents/functions.php');

enforce_login();
authorize();

$Validate = new VALIDATE;
$Feed = new FEED;
$Text = new TEXT;

define('QUERY_EXCEPTION', true); // Shut up debugging
//******************************************************************************//
//--------------- Set $Properties array ----------------------------------------//
// This is used if the form doesn't validate, and when the time comes to enter  //
// it into the database.								//

// trim whitespace before setting/evaluating these fields
$_POST['image'] = trim($_POST['image']);
$_POST['desc'] = $_POST['desc'];
$_POST['title'] = trim($_POST['title']);

$Properties = array();

$Properties['Category'] = $_POST['category'];
$Properties['Title'] = $_POST['title'];
$Properties['TagList'] = $_POST['tags'];
$Properties['Image'] = $_POST['image'];
$Properties['GroupDescription'] = $_POST['desc'];
$Properties['TemplateFooter'] = $_POST['templatefooter'];
$Properties['IgnoreDupes'] = $_POST['ignoredupes'];
$Properties['FreeLeech'] = ($_POST['freeleech']=='1' && check_perms('torrents_freeleech'))?'1':'0';

$Properties['Anonymous'] = ($_POST['anonymous']=='1' && check_perms('site_upload_anon'))?'1':'0';

$RequestID = $_POST['requestid'];

$LogScoreAverage = 0;
$SendPM = 0;
$LogMessage = "";
$CheckStamp = "";

$HideDNU = true;
$HideWL = true;

if (isset($_POST['tempfileid']) && is_number($_POST['tempfileid'])) {
    $Properties['tempfilename'] = $_POST['tempfilename'];
    $Properties['tempfileid'] = (int) $_POST['tempfileid'];
} else {
    $Properties['tempfilename'] = false;
    $Properties['tempfileid'] = null;
}
$FileName = '';

if ($Properties['tempfileid']) {

    //******************************************************************************//
    //--------------- Get already loaded torrent file ----------------------------------------//

    $DB->query("SELECT filename, file FROM torrents_files_temp WHERE ID='$Properties[tempfileid]'");

    list($FileName, $Contents) = $DB->next_record(MYSQLI_NUM, array(1));

    if (!$FileName) {
        $Properties['tempfilename'] = '';
        $Properties['tempfileid'] = null;
        $Err = 'Error getting torrent file. Please re-upload.';
        include(SERVER_ROOT . '/sections/upload/upload.php');
        die();
    }

    $Contents = unserialize(base64_decode($Contents));
    $Tor = new TORRENT($Contents, true); // New TORRENT object

} else {

    $File = $_FILES['file_input']; // This is our torrent file
    $TorrentName = $File['tmp_name'];
    $FileName = $File['name'];

    if (!is_uploaded_file($TorrentName) || !filesize($TorrentName)) {
        $Err = 'No torrent file uploaded, or file is empty.';
    } elseif (substr(strtolower($File['name']), strlen($File['name']) - strlen(".torrent")) !== ".torrent") {
        $Err = "You seem to have put something other than a torrent file into the upload field. (" . $File['name'] . ").";
    }

    if ($Err) { // Show the upload form, with the data the user entered
        include(SERVER_ROOT . '/sections/upload/upload.php');
        die();
    }

    //******************************************************************************//
    //--------------- Generate torrent file ----------------------------------------//

    $File = fopen($TorrentName, 'rb'); // open file for reading
    $Contents = fread($File, 10000000);
    $Tor = new TORRENT($Contents); // New TORRENT object
    fclose($File);
}

$Tor->use_strict_bencode_specification(); // Fix torrents that do not follow the bencode specification.
$Tor->set_announce_url('ANNOUNCE_URL'); // We just use the string "ANNOUNCE_URL"

// $Private is true or false. true means that the uploaded torrent was private, false means that it wasn't.
$Private = $Tor->make_private();
// The torrent is now private.

//******************************************************************************//
//--------------- Check this torrent file does not already exist ----------------------------------------//

$InfoHash = pack("H*", sha1($Tor->Val['info']->enc()));
$DB->query("SELECT ID FROM torrents WHERE info_hash='" . db_string($InfoHash) . "'");
if ($DB->record_count() > 0) {
    list($ID) = $DB->next_record();
    $DB->query("SELECT TorrentID FROM torrents_files WHERE TorrentID = " . $ID);
    if ($DB->record_count() > 0) {
        $Err = '<a href="torrents.php?torrentid=' . $ID . '">The exact same torrent file already exists on the site!</a>';
    } else {
        //One of the lost torrents.
        $DB->query("INSERT INTO torrents_files (TorrentID, File) VALUES ($ID, '" . db_string($Tor->dump_data()) . "')");
        $Err = '<a href="torrents.php?torrentid=' . $ID . '">Thankyou for fixing this torrent</a>';
    }
}

if (!empty($Err)) { // Show the upload form, with the data the user entered
        $Properties['tempfilename'] = '';
        $Properties['tempfileid'] = null;
    include(SERVER_ROOT . '/sections/upload/upload.php');
    die();
}

$sqltime = db_string( sqltime() );

// File list and size
list($TotalSize, $FileList) = $Tor->file_list();

$TmpFileList = array();
foreach ($FileList as $File) {
    list($Size, $Name) = $File;

    if (preg_match('/INCOMPLETE~\*/i', $Name)) {
        $Err = 'The torrent contained one or more forbidden files (' . $Name . ').';
    }
    if (preg_match('/\?/i', $Name)) {
        $Err = 'The torrent contains one or more files with a ?, which is a forbidden character. Please rename the files as necessary and recreate the .torrent file.';
    }
    if (preg_match('/\:/i', $Name)) {
        $Err = 'The torrent contains one or more files with a :, which is a forbidden character. Please rename the files as necessary and recreate the .torrent file.';
    }
    if (preg_match('/\.torrent/i', $Name)) {
        $Err = 'The torrent contains one or more .torrent files inside the torrent. Please remove all .torrent files from your upload and recreate the .torrent file.';
    }
    if (!preg_match('/\./i', $Name)) {
    //if ( strpos($Name, '.')===false) {
        $Err = "The torrent contains one or more files without a file extension. Please remove or rename the files as appropriate and recreate the .torrent file.<br/><strong>note: this can also be caused by selecting 'create encrypted' in some clients</strong> in which case please recreate the .torrent file without encryption selected.";
    }
    // Add file and size to array
    $TmpFileList [] = $Name . '{{{' . $Size . '}}}'; // Name {{{Size}}}
}

if ($Err) { // Show the upload form, with the data the user entered
        $Properties['tempfilename'] = '';
        $Properties['tempfileid'] = null;
    include(SERVER_ROOT . '/sections/upload/upload.php');
    die();
}

// To be stored in the database
$FilePath = $Tor->Val['info']->Val['files'] ? db_string($Tor->Val['info']->Val['name']) : "";

if (!isset($_POST['title']) || $_POST['title']=='') {
    if ($FilePath) $_POST['title'] = $FilePath;
    else if (isset($TmpFileList[0])) {
        $_POST['title'] = preg_replace('/\{\{\{([^\{]*)\}\}\}/i', '', $TmpFileList[0]);
    }
    $Properties['Title'] = $_POST['title'];
}

// do dupe check & return to upload page if detected
$DupeResults = check_size_dupes($FileList);

if (empty($_POST['ignoredupes']) && $DupeResults['DupeResults']) { // Show the upload form, with the data the user entered

    //******************************************************************************//
    //--------------- Temp store torrent file -------------------------------------------//
    if (!$Properties['tempfileid']) {
        $DB->query("INSERT INTO torrents_files_temp (filename, file, time)
                         VALUES ('".db_string($FileName)."', '" . db_string($Tor->dump_data()) . "', '$sqltime')");
        $Properties['tempfileid'] = $DB->inserted_id();
        $Properties['tempfilename'] = $FileName;
    }
    
    $Err = 'The torrent contained one or more possible dupes. Please check carefully!';
	$DupeResults['TotalSize'] = $TotalSize;
    include(SERVER_ROOT . '/sections/upload/upload.php');
    die();
}

//******************************************************************************//
//--------------- Validate data in upload form ---------------------------------//
//** note: if the same field is set to be validated more than once then each time it is set it overwrites the previous test
//** ie.. one test per field max, last one set for a specific field is what is used


$Validate->SetFields('category', '1', 'inarray', 'Please select a category.', array('inarray' => array_keys($NewCategories)));
$Validate->SetFields('title', '1', 'string', 'You must enter a Title.', array('maxlength' => 200, 'minlength' => 2, 'maxwordlength'=>TITLE_MAXWORD_LENGTH));
$Validate->SetFields('tags', '1', 'string', 'You must enter at least one tag.', array('maxlength' => 10000, 'minlength' => 2));
$whitelist_regex = get_whitelist_regex();
$Validate->SetFields('image', '0', 'image', 'The image URL you entered was not valid.', array('regex' => $whitelist_regex, 'maxlength' => 255, 'minlength' => 12));
$Validate->SetFields('desc', '1', 'desc', 'Description', array('regex' => $whitelist_regex, 'minimages'=>1, 'maxlength' => 1000000, 'minlength' => 20));

$Err = $Validate->ValidateForm($_POST, $Text); // Validate the form

if (!$Err && !$Text->validate_bbcode($_POST['desc'],  get_permissions_advtags($LoggedUser['ID']), false)) {
        $Err = "There are errors in your bbcode (unclosed tags)";
}

if ($Err || isset($_POST['checkonly'])) { // Show the upload form, with the data the user entered
    //******************************************************************************//
    //--------------- Temp store torrent file -------------------------------------------//
    if (!$Properties['tempfileid']) {
        $DB->query("INSERT INTO torrents_files_temp (filename, file, time)
                         VALUES ('".db_string($FileName)."', '" . db_string($Tor->dump_data()) . "', '$sqltime')");
        $Properties['tempfileid'] = $DB->inserted_id();
        $Properties['tempfilename'] = $FileName;
    }

    include(SERVER_ROOT . '/sections/upload/upload.php');
    die();
}

$FileString = "'" . db_string(implode('|||', $TmpFileList)) . "'";

// Number of files described in torrent
$NumFiles = count($FileList);

// The string that will make up the final torrent file
$TorrentText = $Tor->enc();

$TorrentSize = strlen($Tor->dump_data());

//******************************************************************************//
//--------------- Make variables ready for database input ----------------------//
// Shorten and escape $Properties for database input
$T = array();
foreach ($Properties as $Key => $Value) {
    $T[$Key] = "'" . db_string(trim($Value)) . "'";
    if (!$T[$Key]) {
        $T[$Key] = NULL;
    }
}

$SearchText = db_string(trim($Properties['Title']) . ' ' . $Text->db_clean_search(trim($Properties['GroupDescription'])));

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$Body = $Properties['GroupDescription'];

//Needs to be here as it isn't set for add format until now
$LogName = $Properties['Title'];

// Create torrent group
$DB->query("
    INSERT INTO torrents_group
    (NewCategoryID, Name, Time, Body, Image, SearchText) VALUES
    ( " . $T['Category'] . ", " . $T['Title'] . ", '$sqltime', '" . db_string($Body) . "', $T[Image], '$SearchText')");
$GroupID = $DB->inserted_id();
$Cache->increment('stats_group_count');

// Use this section to control freeleeches
if ($Properties['FreeLeech']==='1' || $TotalSize >= AUTO_FREELEECH_SIZE) {        // (20*1024*1024*1024)) {
    $Properties['FreeTorrent']='1';
} else {
    $Properties['FreeTorrent']='0';
}

// Torrent
$DB->query("
    INSERT INTO torrents
        (GroupID, UserID, info_hash, FileCount, FileList, FilePath, Size, Time, FreeTorrent, Anonymous)
    VALUES
        ( $GroupID, " . $LoggedUser['ID'] . ", '" . db_string($InfoHash) . "', " . $NumFiles . ", " . $FileString . ", '" . $FilePath . "', " . $TotalSize . ",
        '$sqltime', '" . $Properties['FreeTorrent'] . "', '" . $Properties['Anonymous'] . "')");

$Cache->increment('stats_torrent_count');
$TorrentID = $DB->inserted_id();

if ($TorrentID>$GroupID) {
    $DB->query("UPDATE torrents_group SET ID='$TorrentID' WHERE ID='$GroupID'");
    $DB->query("UPDATE torrents SET GroupID='$TorrentID' WHERE ID='$TorrentID'");
    $GroupID = $TorrentID;
} elseif ($GroupID>$TorrentID) {
    $DB->query("UPDATE torrents SET ID='$GroupID' WHERE ID='$TorrentID'");
    $TorrentID = $GroupID;
}

// in case of comma delineators.
$Tags = str_replace(',', ' ', $Properties['TagList']);
// insert the category tag here.
$Tags = explode(' ', strtolower($NewCategories[(int) $_POST['category']]['tag']." ".$Tags));

$TagsAdded=array();
foreach ($Tags as $Tag) {
    if (empty($Tag)) continue;
    $Tag = strtolower(trim(trim($Tag,'.'))); // trim dots from the beginning and end
    if (!is_valid_tag($Tag) || !check_tag_input($Tag)) continue;
    $Tag = get_tag_synonym($Tag);

    if (empty($Tag)) continue;
    if (in_array($Tag, $TagsAdded)) continue;

    $TagsAdded[] = $Tag;
    $DB->query("INSERT INTO tags
                            (Name, UserID, Uses) VALUES
                            ('" . $Tag . "', $LoggedUser[ID], 1)
                            ON DUPLICATE KEY UPDATE Uses=Uses+1;");
    $TagID = $DB->inserted_id();

    if (empty($LoggedUser['NotVoteUpTags'])) {

        $UserVote = check_perms('site_vote_tag_enhanced') ? ENHANCED_VOTE_POWER : 1;
        $VoteValue = $UserVote + 8;

        $DB->query("INSERT INTO torrents_tags
                            (TagID, GroupID, UserID, PositiveVotes) VALUES
                            ($TagID, $GroupID, $LoggedUser[ID], $VoteValue)
                            ON DUPLICATE KEY UPDATE PositiveVotes=PositiveVotes+$UserVote;");

        $DB->query("INSERT IGNORE INTO torrents_tags_votes (TagID, GroupID, UserID, Way) VALUES
                                ($TagID, $GroupID, $LoggedUser[ID], 'up');");
    } else {
        $DB->query("INSERT IGNORE INTO torrents_tags
                            (TagID, GroupID, UserID, PositiveVotes) VALUES
                            ($TagID, $GroupID, $LoggedUser[ID], 8);");
    }

}
// replace the original tag array with corrected tags
$Tags = $TagsAdded;

update_tracker('add_torrent', array('id' => $TorrentID, 'info_hash' => rawurlencode($InfoHash), 'freetorrent' => (int) $Properties['FreeTorrent']));

//******************************************************************************//
//--------------- Delete any temp torrent file -------------------------------------------//

if (is_number($Properties['tempfileid']) && $Properties['tempfileid'] > 0) {
    $DB->query("DELETE FROM torrents_files_temp WHERE ID='$Properties[tempfileid]'");
}

//******************************************************************************//
//--------------- Write torrent file -------------------------------------------//

$DB->query("INSERT INTO torrents_files (TorrentID, File) VALUES ($TorrentID, '" . db_string($Tor->dump_data()) . "')");

write_log("Torrent $TorrentID ($LogName) (" . get_size($TotalSize) . ") was uploaded by " . $LoggedUser['Username']);
write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "Uploaded $LogName (" . get_size($TotalSize) . ")", 0);

update_hash($GroupID);

//******************************************************************************//
//--------------- Stupid Recent Uploads ----------------------------------------//

if ($Properties['Anonymous'] == "0") {
    $RecentUploads = $Cache->get_value('recent_uploads_' . $UserID);
    if (is_array($RecentUploads)) {
        do {
            foreach ($RecentUploads as $Item) {
                if ($Item['ID'] == $GroupID) {
                    break 2;
                }
            }

            // Only reached if no matching GroupIDs in the cache already.
            if (count($RecentUploads) == 5) {
                array_pop($RecentUploads);
            }
            array_unshift($RecentUploads, array('ID' => $GroupID, 'Name' => trim($Properties['Title']), 'Image' => trim($Properties['Image'])));
            $Cache->cache_value('recent_uploads_' . $UserID, $RecentUploads, 0);
        } while (0);
    }
}

//******************************************************************************//
//--------------- possible dupe - send staff a pm ---------------------------------------//

if(!empty($_POST['ignoredupes']) && $DupeResults['DupeResults']) { // means uploader has ignored dupe warning...
    $NumDupes = count($DupeResults['DupeResults']);
    $DupeIDs = array_unique(array_column($DupeResults['DupeResults'], 'ID'));
    $UniqueResults = $DupeResults['UniqueMatches'];
    $NumChecked = $DupeResults['NumChecked'];

    $Subject = db_string("Possible dupe was uploaded: $LogName by $LoggedUser[Username]");
    $Message = "";

	// ### DEBUGGING #### (remove at some point) - mifune
    //$DebuggingOutput = print_r($DupeResults, true);

    foreach ($DupeIDs as $ID) {
        $DupeKeys = array_keys(array_column($DupeResults['DupeResults'], 'ID'), $ID);
        $Message .= "[size=2][b][url=/torrents.php?id=$ID]".$DupeResults['DupeResults'][$DupeKeys[0]]['Name']."[/url][/b][/size] ";
        $Message .= "(".$DupeResults['DupeResults'][$DupeKeys[0]]['seeders']." Seeders)[br]";
        $Message .= "[table][tr][th]New File[/th][th]Duped File[/th][th]Size[/th][/tr]";
        foreach ($DupeKeys as $KEY) {
            $ThisDupe = $DupeResults['DupeResults'][$KEY];
            if (!isset($ThisDupe['excluded'])) {
                $Message .= "[tr]";
                $Message .= "[td=45%]".$ThisDupe['dupedfile']."[/td]";
                $Message .= "[td=45%]".$ThisDupe['origfile']."[/td]";
                $Message .= "[td=10%]".get_size($ThisDupe['dupedfilesize'])."[/td]";
                $Message .= "[/tr]";
            }
        }
        $Message .= "[/table][br][br]";
    }
    $Percent = round((($DupeResults['SizeUniqueMatches']/$TotalSize)*100),2);

	// ### DEBUGGING #### (remove at some point) - mifune
	//$DebuggingOutput = "[br][br][hr][br][b]Debugging:[/b][br][vr]Percent: {$Percent}[br][br]DupeResults['DupeResults']:[br][br]" . $DebuggingOutput;

    //if ($Percent >= 40.00) {
        $Message = db_string("Possible dupe was uploaded:[br][size=5][b][url=/torrents.php?id=$GroupID]{$LogName}[/url][/b] (" . get_size($TotalSize) . ")[/size][size=2] was uploaded by $LoggedUser[Username][/size]
[br][br][table][tr][th]Duped files[/th][th]Duped size[/th][/tr][tr]
[td=50%][align=center]".$UniqueResults."/".$NumChecked."[/align][/td]
[td=50%][align=center]".get_size($DupeResults['SizeUniqueMatches'])."/".get_size($TotalSize)." ({$Percent}%)[/align][/td][/tr]
[/table][br][br][br][br]{$Message}[br][br]($UniqueResults/$NumChecked files with matches, $NumDupes possible matches overall)[br][br][url=/torrents.php?id=$GroupID&action=dupe_check][size=2][b]View detailed possible dupelist for this torrent[/b][/size][/url]");

            $DB->query("INSERT INTO staff_pm_conversations
                                     (Subject, Status, Level, UserID, Date)
                            VALUES ('$Subject', 'Unanswered', '0', '0', '".sqltime()."')");
            // New message
            $ConvID = $DB->inserted_id();
            $DB->query("INSERT INTO staff_pm_messages
                                     (UserID, SentDate, Message, ConvID)
                            VALUES ('0', '".sqltime()."', '$Message', $ConvID)");
    //}

}


//******************************************************************************//
//--------------- IRC announce and feeds ---------------------------------------//
$Announce = "";

$Announce .= trim($Properties['Title']) . " ";
$Title = $Announce;

$AnnounceSSL = $Announce . " - https://" . SSL_SITE_URL . "/torrents.php?id=$GroupID / https://" . SSL_SITE_URL . "/torrents.php?action=download&id=$TorrentID";
$Announce .= " - http://" . SITE_URL . "/torrents.php?id=$GroupID / http://" . SITE_URL . "/torrents.php?action=download&id=$TorrentID";

$AnnounceSSL .= " - " . trim($Properties['TagList']);
$Announce .= " - " . trim($Properties['TagList']);

send_irc('PRIVMSG #' . SSL_SITE_URL . '-announce-ssl :' . $AnnounceSSL);
send_irc('PRIVMSG #' . SITE_URL . '-announce :' . html_entity_decode($Announce));

$Item = $Feed->torrent($Title,
                        $Text->strip_bbcode($Body),
                        'torrents.php?id=' . $GroupID,
                        'torrents.php?action=download&amp;authkey=[[AUTHKEY]]&amp;torrent_pass=[[PASSKEY]]&amp;id=' . $TorrentID,
                        rawurlencode($InfoHash),
                        $FileName,
                        $TorrentSize,
                        $TotalSize,
                        get_size($TotalSize),
                        'anon',
                        "torrents.php?filter_cat[".$_POST['category']."]=1",
                        $NewCategories[(int) $_POST['category']]['name'],
                        implode($Tags, ' '));

//Notifications
$SQL = "SELECT unf.ID, unf.UserID, torrent_pass
    FROM users_notify_filters AS unf
    JOIN users_main AS um ON um.ID=unf.UserID
    WHERE um.Enabled='1'";

reset($Tags);
$TagSQL = array();
$NotTagSQL = array();
foreach ($Tags as $Tag) {
    $TagSQL[] = " Tags LIKE '%|" . db_string(trim($Tag)) . "|%' ";
    $NotTagSQL[] = " NotTags LIKE '%|" . db_string(trim($Tag)) . "|%' ";
}

$SQL .= " AND ((";

$TagSQL[] = "Tags=''";
$SQL.=implode(' OR ', $TagSQL);

$SQL.= ") AND !(" . implode(' OR ', $NotTagSQL) . ")";
$SQL.=" AND (Categories LIKE '%|" . db_string($NewCategories[(int) $_POST['category']]['name']) . "|%' OR Categories='') ";
$SQL .= ") AND UserID != '" . $LoggedUser['ID'] . "' ";

$DB->query($SQL);

if ($DB->record_count() > 0) {
    $UserArray = $DB->to_array('UserID');
    $FilterArray = $DB->to_array('ID');

    $Rows = array();
    foreach ($UserArray as $User) {
        list($FilterID, $UserID, $Passkey) = $User;
        $Rows[] = "('$UserID', '$GroupID', '$TorrentID', '$FilterID')";
        $Feed->populate('torrents_notify_' . $Passkey, $Item);
        $Cache->delete_value('notifications_new_' . $UserID);
    }
    $InsertSQL = "INSERT IGNORE INTO users_notify_torrents (UserID, GroupID, TorrentID, FilterID) VALUES ";
    $InsertSQL.=implode(',', $Rows);
    $DB->query($InsertSQL);

    foreach ($FilterArray as $Filter) {
        list($FilterID, $UserID, $Passkey) = $Filter;
        $Feed->populate('torrents_notify_' . $FilterID . '_' . $Passkey, $Item);
    }
}

// RSS for bookmarks
$DB->query("SELECT u.ID, u.torrent_pass
            FROM users_main AS u
            JOIN bookmarks_torrents AS b ON b.UserID = u.ID
            WHERE b.GroupID = $GroupID");
while (list($UserID, $Passkey) = $DB->next_record()) {
    $Feed->populate('torrents_bookmarks_t_' . $Passkey, $Item);
}

$Feed->populate('torrents_all', $Item);

if (!$Private) {
    show_header("Warning");
    ?>
    <h2>Warning</h2>
    <div class="thin">
        <div class="box pad shadow">
            <span style="font-size: 1.5em;">
                Your torrent has been uploaded however, because you didn't choose the private option you <span class="red">must</span> download the torrent file from <a href="torrents.php?id=<?= $GroupID ?>">here</a> before you can start seeding.
            </span>
        </div>
    </div>
    <?php
    show_footer();
    die();
} elseif ($RequestID) {
    header("Location: requests.php?action=takefill&requestid=" . $RequestID . "&torrentid=" . $TorrentID . "&auth=" . $LoggedUser['AuthKey']);
} else {
    header("Location: torrents.php?id=$GroupID");
}
