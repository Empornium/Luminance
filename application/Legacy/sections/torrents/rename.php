<?php
authorize();


$groupID = $_POST['groupid'];
$OldGroupID = $groupID;
$NewName = trim($_POST['name']);

if (!$groupID || !is_integer_string($groupID)) { error(404); }

$AuthorID = $master->db->rawQuery(
        'SELECT UserID
           FROM torrents_group
         WHERE ID = ?',
        [$groupID]
    )->fetchColumn();
$CanEdit = check_perms('torrent_edit') || ($AuthorID == $activeUser['ID']);

if (!$CanEdit) { error(403); }

// prevent a user from editing a torrent once it is marked as "Okay", but let
// staff edit!
$Review = get_last_review($groupID);
if ($Review['Status'] == 'Okay' && !check_perms('torrent_edit')) {
    if (!check_perms('site_edit_override_review')) {
        error("Sorry - once a torrent has been reviewed by staff and passed it is automatically locked.");
    }
}

$bbCode = new \Luminance\Legacy\Text;
$Validate = new \Luminance\Legacy\Validate;

$Validate->SetFields('name', '1', 'string', 'You must enter a Title.', ['maxlength' => 200, 'minlength' => 2, 'maxwordlength'=>200]);

$Err = $Validate->ValidateForm($_POST, $bbCode); // Validate the form

if ($Err) error($Err);

$group = $master->repos->torrentgroups->load($groupID);
$OldName = $group->Name;
$SearchText = "{$NewName} {$bbCode->db_clean_search(trim($group->Body))}";

# Update group entity
$group->Name        = $NewName;
$group->SearchText  = $SearchText;
$master->repos->torrentgroups->save($group);

$master->cache->deleteValue('torrents_details_'.$groupID);

update_hash($groupID);

write_log("Torrent Group {$groupID} ({$OldName})  was renamed to '{$NewName}' by {$activeUser['Username']}");
write_group_log($groupID, 0, $activeUser['ID'], "renamed to {$NewName} from {$OldName}", 0);

header('Location: torrents.php?id='.$groupID."&did=2");
