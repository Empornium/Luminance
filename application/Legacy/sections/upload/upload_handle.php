<?php
//******************************************************************************//
//--------------- Take upload --------------------------------------------------//
// This pages handles the backend of the torrent upload function. It checks	 //
// the data, and if it all validates, it builds the torrent file, then writes   //
// the data to the database and the torrent to the disk.						//
//******************************************************************************//

use Luminance\Errors\InternalError;
use Luminance\Entities\Torrent;
use Luminance\Entities\TorrentGroup;

ini_set('upload_max_filesize', MAX_FILE_SIZE_BYTES);
ini_set('max_file_uploads', 100);
set_time_limit(120); // 2 mins
include(SERVER_ROOT . '/Legacy/sections/torrents/functions.php');

enforce_login();
authorize();

$Validate = new \Luminance\Legacy\Validate;
$feed = new Luminance\Legacy\Feed;
$bbCode = new \Luminance\Legacy\Text;

define('QUERY_EXCEPTION', true); // Shut up debugging
//******************************************************************************//
//--------------- Set $Properties array ----------------------------------------//
// This is used if the form doesn't validate, and when the time comes to enter  //
// it into the database.								//

$Properties = [];

// trim whitespace before setting/evaluating these fields
$_POST['title']      = trim(($_POST['title'] ?? ''));
$Properties['Title'] = $_POST['title'];

$_POST['image']      = trim(($_POST['image'] ?? ''));
$Properties['Image'] = $_POST['image'];

$Properties['Category'] = ($_POST['category'] ?? null);
$Properties['TagList']  = ($_POST['taglist'] ?? null);
$Properties['GroupDescription'] = ($_POST['desc'] ?? null);
$Properties['TemplateFooter']   = ($_POST['templatefooter'] ?? null);
$Properties['IgnoreDupes'] = $_POST['ignoredupes'] ?? '0';
$Properties['FreeLeech']   = (($_POST['freeleech'] ?? '0')=='1' && check_perms('torrent_freeleech'))?'1':'0';
$Properties['Anonymous']   = (($_POST['anonymous'] ?? '0')=='1' && check_perms('site_upload_anon'))?'1':'0';

$Err = null;

$RequestID = ($_POST['requestid'] ?? null);

$LogScoreAverage = 0;
$SendPM = 0;
$LogMessage = "";
$CheckStamp = "";

$HideDNU = true;
$HideWL = true;

if (isset($_POST['tempfileid']) && is_integer_string($_POST['tempfileid'])) {
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

    list($FileName, $Contents) = $master->db->rawQuery(
        'SELECT filename, file
           FROM torrents_files_temp
          WHERE ID = ?',
        [$Properties['tempfileid']]
    )->fetch(\PDO::FETCH_NUM);

    if (!$FileName) {
        $Properties['tempfilename'] = '';
        $Properties['tempfileid'] = null;
        $Err = 'Error getting torrent file. Please re-upload.';
        include(SERVER_ROOT . '/Legacy/sections/upload/upload.php');
        return;
    }

    $Contents = unserialize(base64_decode($Contents));
    $Tor = new Luminance\Legacy\Torrent($Contents, true); // new Torrent object

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
        include(SERVER_ROOT . '/Legacy/sections/upload/upload.php');
        return;
    }

    //******************************************************************************//
    //--------------- Generate torrent file ----------------------------------------//

    $File = fopen($TorrentName, 'rb'); // open file for reading
    $Contents = fread($File, 10000000);
    $Tor = new Luminance\Legacy\Torrent($Contents); // new Torrent object
    fclose($File);
}

$Strict = $Tor->use_strict_bencode_specification(); // Fix torrents that do not follow the bencode specification.
$Tor->set_announce_url('ANNOUNCE_URL'); // We just use the string "ANNOUNCE_URL"

// $Private is true or false. true means that the uploaded torrent was private, false means that it wasn't.
$Private = $Tor->make_private();
$Unique = $Tor->make_unique();
// The torrent is now private.

//******************************************************************************//
//--------------- Check this torrent file does not already exist ----------------------------------------//

$InfoHash = pack("H*", sha1($Tor->Val['info']->enc()));
$ID = $master->db->rawQuery(
    'SELECT ID
       FROM torrents
      WHERE info_hash = ?',
    [$InfoHash]
)->fetchColumn();

if (!empty($ID)) {
    $TorrentID = $master->db->rawQuery(
        'SELECT TorrentID
           FROM torrents_files
          WHERE TorrentID = ?',
        [$ID]
    )->fetchColumn();
    if (!empty($TorrentID)) {
        $Err = '<a href="/torrents.php?torrentid=' . $ID . '">The exact same torrent file already exists on the site!</a>';
    } else {
        //One of the lost torrents.
        $master->db->rawQuery(
            'INSERT INTO torrents_files (TorrentID, File, Version)
                  VALUES (?, ?, 2)',
            [$ID, $Tor->dump_data()]
        );
        $Err = '<a href="/torrents.php?torrentid=' . $ID . '">Thank you for fixing this torrent</a>';
    }
}

if (!empty($Err)) { // Show the upload form, with the data the user entered
        $Properties['tempfilename'] = '';
        $Properties['tempfileid'] = null;
    include(SERVER_ROOT . '/Legacy/sections/upload/upload.php');
    return;
}

// File list and size
list($TotalSize, $FileList) = $Tor->file_list();

$TmpFileList = [];
foreach ($FileList as $File) {
    list($Size, $Name) = $File;

    if (preg_match('/INCOMPLETE~\*/i', $Name)) {
        $Err = 'The torrent contained one or more forbidden files.' . PHP_EOL . display_str($Name);
    }
    if (preg_match('/[\<\>\|\*\"\?\:]/i', $Name)) {
        $Err = 'The torrent contains one or more files with an illegal character (<, >, |, *, ", ?, :). Please rename the files as necessary and recreate the .torrent file.<br/>[' . display_str($Name) . ']';
    }
    if (preg_match('/\.torrent$/i', $Name)) {
        $Err = 'The torrent contains one or more .torrent files inside the torrent. Please remove all .torrent files from your upload and recreate the .torrent file.<br/>[' . display_str($Name) . ']';
    }
    if (preg_match('/\.scr$/i', $Name)) {
        $Err = 'The torrent contains one or more .scr files. Please remove all .scr files from your upload and recreate the .torrent file.<br/>[' . display_str($Name) . ']';
    }
    if (preg_match('/\_____padding_file/i', $Name)) {
        $Err = 'The torrent contains one or more padding files. Please recreate the .torrent file with an alternative client.<br/>[' . display_str($Name) . ']';
    }
    if (preg_match('/^\./i', $Name)) {
        $Err = 'The torrent contains one or more hidden files. Please recreate the .torrent file with an alternative client.<br/>[' . display_str($Name) . ']';
    }
    if (!preg_match('/\./i', $Name)) {
        $Err = "The torrent contains one or more files without a file extension. Please remove or rename the files as appropriate and recreate the .torrent file.<br/><strong>note: this can also be caused by selecting 'create encrypted' in some clients</strong> in which case please recreate the .torrent file without encryption selected.<br/>[" . display_str($Name) . ']';
    }

    // Add file and size to array
    $TmpFileList [] = $Name . '{{{' . $Size . '}}}';
}

if ($Err) { // Show the upload form, with the data the user entered
        $Properties['tempfilename'] = '';
        $Properties['tempfileid'] = null;
    include(SERVER_ROOT . '/Legacy/sections/upload/upload.php');
    return;
}


// Load data from metadata section of the torrent
if ($Err || isset($_POST['checkonly'])) {
    if (array_key_exists('metadata', $Tor->Val)) {
        $metadata = $Tor->Val['metadata']->Val;
        if (array_key_exists('title', $metadata)) {
            $Properties['Title'] = empty($Properties['Title']) ? $metadata['title'] : $Properties['Title'];
        }

        if (array_key_exists('cover url', $metadata)) {
            $Properties['Image'] = empty($Properties['Image']) ? $metadata['cover url'] : $Properties['Image'];
        }

        if (array_key_exists('taglist', $metadata)) {
            $Properties['TagList'] = empty($Properties['TagList']) ? implode(' ', $metadata['taglist']->Val) : $Properties['TagList'];
        }

        if (array_key_exists('description', $metadata)) {
            $Properties['GroupDescription'] = empty($Properties['GroupDescription']) ? $metadata['description'] : $Properties['GroupDescription'];
        }
    }
}

// To be stored in the database
$FilePath = ($Tor->Val['info']->Val['files'] ?? false) ? $Tor->Val['info']->Val['name'] : "";

if (empty($Properties['Title'])) {
    if ($FilePath) {
        # We could use stripslashes($FilePath), but this seems cleaner
        $_POST['title'] = $Tor->Val['info']->Val['name'];
    } else if (isset($TmpFileList[0])) {
        $_POST['title'] = preg_replace('/\{\{\{([^\{]*)\}\}\}/i', '', $TmpFileList[0]);
    }
    $Properties['Title'] = $_POST['title'];
}

// do dupe check & return to upload page if detected
$dupeResults = check_size_dupes($FileList);

if (empty($_POST['ignoredupes']) && $dupeResults['UniqueMatches'] > 0) { // Show the upload form, with the data the user entered

    //******************************************************************************//
    //--------------- Temp store torrent file -------------------------------------------//
    if (!$Properties['tempfileid']) {
        $master->db->rawQuery(
            'INSERT INTO torrents_files_temp (filename, file, time)
                  VALUES (?, ?, ?)',
            [$FileName, $Tor->dump_data(), sqltime()]
        );
        $Properties['tempfileid'] = $master->db->lastInsertID();
        $Properties['tempfilename'] = $FileName;
    }

    $Err = 'The torrent contained one or more possible dupes. Please check carefully!';
    $dupeResults['TotalSize'] = $TotalSize;
    include(SERVER_ROOT . '/Legacy/sections/upload/upload.php');
    return;
}

//******************************************************************************//
//--------------- Validate data in upload form ---------------------------------//
//** note: if the same field is set to be validated more than once then each time it is set it overwrites the previous test
//** ie.. one test per field max, last one set for a specific field is what is used


$Validate->SetFields('category', '1', 'inarray', 'Please select a category.', ['inarray' => array_keys($openCategories)]);
$Validate->SetFields('title', '1', 'string', 'You must enter a Title.', ['maxlength' => 200, 'minlength' => 2, 'maxwordlength'=>TITLE_MAXWORD_LENGTH]);
$Validate->SetFields('taglist', '1', 'string', "You must enter at least {$master->options->MinTagNumber} tag(s).", ['maxlength' => 10000, 'minlength' => 2]);
$whitelist_regex = get_whitelist_regex();
$Validate->SetFields('image', $master->options->RequireCover, 'image', 'The cover image URL you entered was not valid.', ['regex' => $whitelist_regex, 'maxlength' => 255, 'minlength' => 12]);
$Validate->SetFields('desc', '1', 'desc', 'Description', ['regex' => $whitelist_regex, 'minimages'=>1, 'maxlength' => 1000000, 'minlength' => 20]);

$Err = $Validate->ValidateForm($_POST, $bbCode); // Validate the form

if ($Err) {
    $Err = "Error validating form: {$Err}";
}

if (!$Err && !$bbCode->validate_bbcode($_POST['desc'],  get_permissions_advtags($activeUser['ID']), false, false)) {
        $Err = "There are errors in your bbcode (unclosed tags)";
}

// Validate the number of tags
if (!$Err && !validate_tags_number($Properties['TagList'])) {
    $Err = "You must enter at least {$master->options->MinTagNumber} tag(s).";
}


if ($Err || isset($_POST['checkonly'])) { // Show the upload form, with the data the user entered
    //******************************************************************************//
    //--------------- Temp store torrent file -------------------------------------------//
    if (!$Properties['tempfileid']) {
        $master->db->rawQuery(
            'INSERT INTO torrents_files_temp (filename, file, time)
                  VALUES (?, ?, ?)',
            [$FileName, $Tor->dump_data(), sqltime()]
        );
        $Properties['tempfileid'] = $master->db->lastInsertID();
        $Properties['tempfilename'] = $FileName;
    }

    include(SERVER_ROOT . '/Legacy/sections/upload/upload.php');
    return;
}

$FileString = implode('|||', $TmpFileList);

// Number of files described in torrent
$NumFiles = count($FileList);

// The string that will make up the final torrent file
$TorrentText = $Tor->enc();

$TorrentSize = strlen($Tor->dump_data());

//******************************************************************************//
//--------------- Make variables ready for database input ----------------------//
// Shorten and escape $Properties for database input
$T = [];
foreach ($Properties as $Key => $Value) {
    $T[$Key] = trim($Value);
    if (!$T[$Key]) {
        $T[$Key] = NULL;
    }
}

$SearchText = trim($Properties['Title'] . ' ' . $bbCode->db_clean_search(trim($Properties['GroupDescription'])));

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$Body = $Properties['GroupDescription'];

// Normalize line endings
$Body = str_replace("\r\n", "\n", $Body);
$Body = str_replace("\r", "\n", $Body);

//Needs to be here as it isn't set for add format until now
$LogName = $Properties['Title'];
try {
    $transactionInScope = false;
    if (!$master->db->inTransaction()) {
        $master->db->beginTransaction();
        $transactionInScope = true;
    }

    $group = new TorrentGroup;
    $group->NewCategoryID = $T['Category'];
    $group->Name = $T['Title'];
    $group->TagList = $Properties['TagList'];
    $group->Time = sqltime();
    $group->UserID = $activeUser['ID'];
    $group->Body = $Body;
    $group->Image = $T['Image'];
    $group->SearchText = $SearchText;
    $master->repos->torrentgroups->save($group);

    $GroupID = $group->ID;
    $master->cache->incrementValue('stats_group_count');

    // Use this section to control freeleeches
    if ($Properties['FreeLeech']==='1' || $TotalSize >= $master->settings->torrents->auto_freeleech_size) {        // (20*1024*1024*1024)) {
        $Properties['FreeTorrent']='1';
    } else {
        $Properties['FreeTorrent']='0';
    }

    // Torrent
    $torrent = new Torrent;
    $torrent->GroupID = $group->ID;
    $torrent->UserID = $activeUser['ID'];
    $torrent->info_hash = $InfoHash;
    $torrent->FileCount = $NumFiles;
    $torrent->FileList = $FileString;
    $torrent->FilePath = $FilePath;
    $torrent->Size = $TotalSize;
    $torrent->FreeTorrent = $Properties['FreeTorrent'];
    $torrent->Time = sqltime();
    $torrent->Anonymous = $Properties['Anonymous'];
    $master->repos->torrents->save($torrent);

    $master->cache->incrementValue('stats_torrent_count');
    $torrentID = $master->db->lastInsertID();

    $Tags = split_tags($Properties['TagList']);

    $TagsAdded = [];
    foreach ($Tags as $Tag) {
        if (empty($Tag) || !is_valid_tag($Tag) || !check_tag_input($Tag)) {
            continue;
        }
        $Tag = get_tag_synonym($Tag);

        if (empty($Tag)) continue;
        if (in_array($Tag, $TagsAdded)) continue;

        $TagsAdded[] = $Tag;
        $master->db->rawQuery(
            "INSERT INTO tags (Name, UserID, Uses)
                  VALUES (?, ?, 1)
                      ON DUPLICATE KEY
                  UPDATE Uses = Uses + 1",
            [$Tag, $activeUser['ID']]
        );
        $TagID = $master->db->lastInsertID();

        if (empty($activeUser['NotVoteUpTags'])) {

            $UserVote = check_perms('site_vote_tag_enhanced') ? ENHANCED_VOTE_POWER : 1;
            $VoteValue = $UserVote + 8;

            $master->db->rawQuery(
                'INSERT INTO torrents_tags (TagID, GroupID, UserID, PositiveVotes)
                      VALUES (?, ?, ?, ?)
                          ON DUPLICATE KEY
                      UPDATE PositiveVotes = PositiveVotes + ?',
                [$TagID, $GroupID, $activeUser['ID'], $VoteValue, $UserVote]
            );

            $master->db->rawQuery(
                'INSERT IGNORE INTO torrents_tags_votes (TagID, GroupID, UserID, Way)
                             VALUES (?, ?, ?, ?)',
                [$TagID, $GroupID, $activeUser['ID'], 'up']
            );
        } else {
            $master->db->rawQuery(
                'INSERT IGNORE INTO torrents_tags (TagID, GroupID, UserID, PositiveVotes)
                             VALUES (?, ?, ?, ?)',
                [$TagID, $GroupID, $activeUser['ID'], 8]
            );
        }

    }

    $success = $master->tracker->addTorrent($torrentID, $InfoHash, (int) $Properties['FreeTorrent'], 0);
    if (!$success) {
        if (!$master->settings->site->debug_mode) {
            if ($transactionInScope === true) {
                $this->db->rollback();
            }
            throw new InternalError("Failed to update tracker");
        }
    }

    if ($transactionInScope === true) {
        $this->db->commit();
    }
} catch (\PDOException $e) {
    error_log("Failed to create torrent, SQL error: ".$e->getMessage().PHP_EOL);
    if ($transactionInScope === true) {
        $this->db->rollback();
    }

    // Just in case we already sent the command to the tracker,
    // which should not have happened...
    $master->tracker->deleteTorrent($InfoHash);
    error("Upload DB queries failed.");
    return;
}

// replace the original tag array with corrected tags
$Tags = $TagsAdded;


$scheme = $master->request->ssl ? 'https' : 'http';
$message  = $T['Title'];
$message .= " - Size: ".get_size($TotalSize);
$message .= " - Uploader: " .anon_username($activeUser['Username'], $Properties['Anonymous'], false);
$link = " - {$scheme}://{$master->settings->main->site_url}/torrents.php?torrentid={$torrentID}";

# Maximum IRC message is 512 bytes including channel name and #PRIVMSG,
# so limit the entire message to 400 characters.
$MaxLength = 400;

# Deduct the length of the known message elements
$MaxLength -= strlen($message);
$MaxLength -= strlen($link);

# First tag won't have a comma so account for this here
$IRCTagsLength = -1;
$IRCTags = [];

# Limit the tags to the remaining characters
foreach ($Tags as $Tag) {
    # Account for the length of the existing tags, the new tag, and a comma
    if (($IRCTagsLength + strlen($Tag) + 1) > $MaxLength) {
        break;
    }

    # Add the tag to the list
    $IRCTagsLength += (strlen($Tag) + 1);
    $IRCTags[] = $Tag;
}

$message .= " - Tags: ". implode(', ', $IRCTags);
$message .= $link;
$master->irker->announceTorrent($message);

//******************************************************************************//
//--------------- Delete any temp torrent file -------------------------------------------//

if (is_integer_string($Properties['tempfileid']) && $Properties['tempfileid'] > 0) {
    $master->db->rawQuery(
        "DELETE
           FROM torrents_files_temp
          WHERE ID = ?",
        [$Properties['tempfileid']]
    );
}

//******************************************************************************//
//--------------- Write torrent file -------------------------------------------//

$master->db->rawQuery(
    'INSERT INTO torrents_files (TorrentID, File, Version)
          VALUES (?, ?, 2)',
      [$torrentID, $Tor->dump_data()]
);

write_log("Torrent $torrentID ($LogName) (" . get_size($TotalSize) . ") was uploaded by " . $activeUser['Username']);
write_group_log($GroupID, $torrentID, $activeUser['ID'], "Uploaded $LogName (" . get_size($TotalSize) . ")", 0);

update_hash($GroupID);

//******************************************************************************//
//--------------- Clear upload total -------------------------------------------//
$master->cache->deleteValue('users_upload_total_'.$userID);

//******************************************************************************//
//--------------- Stupid Recent Uploads ----------------------------------------//

if ($Properties['Anonymous'] == "0") {
    $RecentUploads = $master->cache->getValue('recent_uploads_' . $userID);
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
            array_unshift($RecentUploads, ['ID' => $GroupID, 'Name' => trim($Properties['Title']), 'Image' => trim($Properties['Image'])]);
            $master->cache->cacheValue('recent_uploads_' . $userID, $RecentUploads, 0);
        } while (0);
    }
}

//******************************************************************************//
//--------------- possible dupe - send staff a pm ---------------------------------------//

if (!empty($_POST['ignoredupes']) && $dupeResults['UniqueMatches'] > 0) { // means uploader has ignored dupe warning...
    $NumDupes = count($dupeResults['DupeResults']);
    $DupeIDs = array_unique(array_column($dupeResults['DupeResults'], 'ID'));
    $UniqueResults = $dupeResults['UniqueMatches'];
    $NumChecked = $dupeResults['NumChecked'];

    $Subject = "Possible dupe was uploaded: $LogName by {$activeUser['Username']}";
    $Message = "";

    foreach ($DupeIDs as $ID) {
        $DupeKeys = array_keys(array_column($dupeResults['DupeResults'], 'ID'), $ID);
        $Message .= "[size=2][b][id=url_{$ID}][url=/torrents.php?id=$ID]".$dupeResults['DupeResults'][$DupeKeys[0]]['Name']."[/url][/id][/b][/size] ";
        $Message .= "(".$dupeResults['DupeResults'][$DupeKeys[0]]['seeders']." Seeders)[br]";
        $Message .= "[table][tr][th]New File[/th][th]Duped File[/th][th]Size[/th][/tr]";
        foreach ($DupeKeys as $KEY) {
            $ThisDupe = $dupeResults['DupeResults'][$KEY];
            if (!isset($ThisDupe['excluded'])) {
                $Message .= "[tr]";
                $Message .= "[td=45%][id=newfile_{$ThisDupe['dupedfilesize']}]".$ThisDupe['dupedfile']."[/id][/td]";
                $Message .= "[td=45%][id=oldfile_{$ThisDupe['dupedfilesize']}]".$ThisDupe['origfile']."[/id][/td]";
                $Message .= "[td=10%][id=filesize_{$ThisDupe['dupedfilesize']}_{$ID}]".get_size($ThisDupe['dupedfilesize'])."[/id][/td]";
                $Message .= "[/tr]";
            }
        }
        $Message .= "[/table][br][br]";
    }
    $Percent = round((($dupeResults['SizeUniqueMatches']/$TotalSize)*100),2);

  // ### DEBUGGING #### (remove at some point) - mifune
  //$DebuggingOutput = "[br][br][hr][br][b]Debugging:[/b][br][br]Percent: {$Percent}[br][br]\$dupeResults:[br][br]" . $DebuggingOutput;

    if ($Percent >= 50) $bbPercent ='[color=#AA0000]([id=percent]'.$Percent.'[/id]%) :dupe:[/color]';
    if ($Percent < 50) $bbPercent ='[color=#009900]([id=percent]'.$Percent.'[/id]%) :gjob:[/color]';
    //if ($Percent >= 40.00) {
        $Message = "[br]Possible dupe was uploaded:[br][br][align=center][size=4][b][id=urlnew][url=/torrents.php?id=$GroupID]{$LogName}[/url][/id][/b] (" . get_size($TotalSize) . ")[/size][size=2] was uploaded by {$activeUser['Username']}[/size][/align]
[br][br][table][tr][th]Duped files[/th][th]Duped size[/th][/tr][tr]
[td=50%][align=center][size=3][id=numresults]{$UniqueResults}[/id] / [id=numchecked]{$NumChecked}[/id][/size][/align][/td]
[td=50%][align=center][size=3][id=size]".get_size($dupeResults['SizeUniqueMatches'])."[/id] / [id=totalsize]".get_size($TotalSize)."[/id] {$bbPercent}[/size][/align][/td][/tr]
[/table][br][br][align=right][id=copylink]initialising...[/id][/align][br]{$Message}[br][br]($UniqueResults/$NumChecked files with matches, $NumDupes possible matches overall)[br][br][url=/torrents.php?id=$GroupID&action=dupe_check][size=2][b]View detailed possible dupelist for this torrent[/b][/size][/url]";
        //$Message .= $DebuggingOutput;

            $master->db->rawQuery(
                'INSERT INTO staff_pm_conversations (Subject, Status, Level, UserID, Date)
                      VALUES (?, ?, ?, ?, ?)',
                [$Subject, 'Unanswered', '0', '0', sqltime()]
            );
            // New message
            $ConvID = $master->db->lastInsertID();
            $master->db->rawQuery(
                'INSERT INTO staff_pm_messages (UserID, SentDate, Message, ConvID)
                      VALUES (?, ?, ?, ?)',
                ['0', sqltime(), $Message, $ConvID]
            );
    //}

}


//******************************************************************************//
//--------------- IRC announce and feeds ---------------------------------------//

$Title = trim($Properties['Title']) . ' ';

$Item = $feed->torrent($Title,
                        $bbCode->strip_bbcode($Body),
                        'torrents.php?id=' . $GroupID,
                        'torrents.php?action=download&amp;authkey=[[AUTHKEY]]&amp;torrent_pass=[[PASSKEY]]&amp;id=' . $torrentID,
                        rawurlencode($InfoHash),
                        $FileName,
                        $TorrentSize,
                        $TotalSize,
                        get_size($TotalSize),
                        'anon',
                        "torrents.php?filter_cat[".$_POST['category']."]=1",
                        $newCategories[(int) $_POST['category']]['name'],
                        implode( ' ', $Tags),
                        (int)$Properties['FreeTorrent']);

//Notifications
$SQL =
    "SELECT unf.ID,
            unf.UserID,
            torrent_pass
       FROM users_notify_filters AS unf
       JOIN users_main AS um ON um.ID = unf.UserID
      WHERE um.Enabled = '1'";

reset($Tags);
$TagSQL = [];
$tagParams = [];
$NotTagSQL = [];
foreach ($Tags as $Tag) {
    $tag = trim($Tag);
    $tag = "%|{$tag}|%";
    $TagSQL[] = " Tags LIKE ? ";
    $NotTagSQL[] = " NotTags LIKE ? ";
    $tagParams[] = $tag;
}
$params = array_merge($tagParams, $tagParams);

$SQL .= " AND ((";

$TagSQL[] = "Tags=''";
$SQL.=implode(' OR ', $TagSQL);

$SQL.= ") AND !(" . implode(' OR ', $NotTagSQL) . ")";
$SQL.=" AND (Categories LIKE ? OR Categories='') ";
$params[] = "%|{$newCategories[(int) $_POST['category']]['name']}|%";
$SQL .= ") AND UserID != ? ";
if (!(int)$Properties['FreeTorrent']) {
    $SQL .= " AND unf.Freeleech = 0 ";
}
$params[] = $activeUser['ID'];

$UserArray = $master->db->rawQuery($SQL, $params)->fetchAll(\PDO::FETCH_NUM);

if ($master->db->foundRows() > 0) {
    $Rows = [];
    $params = [];
    foreach ($UserArray as $User) {
        list($FilterID, $userID, $Passkey) = $User;
        $Rows[] = "(?, ?, ?, ?)";
        $params = array_merge($params, [$userID, $GroupID, $torrentID, $FilterID]);
        $feed->populate('torrents_notify_' . $Passkey, $Item);
        $master->cache->deleteValue('notifications_new_' . $userID);
        $feed->populate('torrents_notify_' . $FilterID . '_' . $Passkey, $Item);
    }
    $InsertSQL = "INSERT IGNORE INTO users_notify_torrents (UserID, GroupID, TorrentID, FilterID) VALUES ";
    $InsertSQL.=implode(', ', $Rows);
    $master->db->rawQuery($InsertSQL, $params);
}

// RSS for bookmarks
$bookmarkRSS = $master->db->rawQuery(
    'SELECT u.ID,
            u.torrent_pass
       FROM users_main AS u
       JOIN bookmarks_torrents AS b ON b.UserID = u.ID
      WHERE b.GroupID = ?',
    [$GroupID]
)->fetchAll(\PDO::FETCH_NUM);

foreach ($bookmarkRSS as $bookmark) {
    list($userID, $Passkey) = $bookmark;
    $feed->populate('torrents_bookmarks_t_' . $Passkey, $Item);
}

$feed->populate('torrents_all', $Item);

if (!($Private && $Strict && $Unique)) {
    show_header("Warning");
    ?>
    <h2>Warning</h2>
    <div class="thin">
        <div class="box pad shadow">
            <span style="font-size: 1.5em;">
                Your torrent has been uploaded however, it has been corrected to resolve the following issues:
                <ul>
    <?php if (!$Private) {  ?>
                <li>The private flag was not set</li>
    <?php } ?>
    <?php if (!$Unique) { ?>
                <li>The source tag was not set</li>
    <?php } ?>
    <?php if (!$Strict) { ?>
                <li>One or more empty paths existed in the file tree</li>
    <?php } ?>
                </ul>
                You <span class="red">must</span> download the torrent file from <a href="torrents.php?id=<?= $GroupID ?>">here</a> before you can start seeding.
            </span>
        </div>
    </div>
    <?php
    show_footer();
    return;
} elseif ($RequestID) {
    header("Location: requests.php?action=takefill&requestid=" . $RequestID . "&torrentid=" . $torrentID . "&auth=" . $activeUser['AuthKey']);
} else {
    header("Location: torrents.php?id=$GroupID");
}
