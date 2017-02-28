<?php
authorize();

include(SERVER_ROOT . '/sections/torrents/functions.php');

$CollageID = $_POST['collageid'];
if (!is_number($CollageID)) { error(0); }

$DB->query("SELECT Name, Description, TagList, UserID, CategoryID FROM collages WHERE ID='$CollageID'");
list($Name, $Description, $TagList, $UserID, $CategoryID) = $DB->next_record();

if (!check_perms('site_collages_manage') && $UserID != $LoggedUser['ID']) {
          error(403);
}

$DB->query("SELECT ID,Deleted FROM collages WHERE Name='".db_string($_POST['name'])."' AND ID!='$CollageID' LIMIT 1");
if ($DB->record_count()) {
    list($ID, $Deleted) = $DB->next_record();
    if ($Deleted) {
        $Err = 'A collage with that name already exists but needs to be recovered, please <a href="staffpm.php">contact</a> the staff team!';
    } else {
        $Err = "A collage with that name already exists: <a href=\"/collages.php?id=$ID\">$ID</a>.";
    }
      if ($Err) error($Err);
}

$NewTagList = explode(' ',$_POST['tags']);
$NewTags = array();
foreach ($NewTagList as $ID=>$Tag) {
        $Tag = trim(trim($Tag, '.')); // trim whitespace & dots from the beginning and end
        $Tag = get_tag_synonym($Tag);
        if (!in_array($Tag, $NewTags) && is_valid_tag($Tag)) {
            $NewTags[] = $Tag;
        }
}
$NewTagList = implode(' ',$NewTags);

$Update = array();

if ($Name != $_POST['name']) {
    if ( check_perms('site_collages_manage') ) {

        $Update[] = "Name='".db_string($_POST['name'])."'";
    } elseif ( $CategoryID == 0 && $UserID == $LoggedUser['ID'] && check_perms('site_collages_renamepersonal' ) ) {

        if (!stristr($_POST['name'], $LoggedUser['Username'])) {
                error("Your personal collage's title must include your username.");
        }
        $Update[] = "Name='".db_string($_POST['name'])."'";
    }
}

include(SERVER_ROOT.'/classes/class_text.php');
$Text = new TEXT;
$Text->validate_bbcode($_POST['description'],  get_permissions_advtags($LoggedUser['ID']));

if ($Description != $_POST['description']) {
    $Update[] = "Description='".db_string($_POST['description'])."'";
}

if ($TagList != $NewTagList) {
    $Update[] = "TagList='$NewTagList'";
}

if (!empty($_POST['category']) && !empty($CollageCats[$_POST['category']]) && $_POST['category']!=$CategoryID && $_POST['category']!=0) {
    $Update[] = "CategoryID='".db_string($_POST['category'])."'";
}

if (isset($_POST['featured']) && $CategoryID == 0 && (($LoggedUser['ID'] == $UserID && check_perms('site_collages_personal')) || check_perms('site_collages_manage'))) {
    $DB->query("UPDATE collages SET Featured=0 WHERE CategoryID=0 and UserID=$UserID");
    $Update[] = $Update[] = "Featured=1";
}

if (count($Update)>0) {
    $SET = implode(', ', $Update);

    $DB->query("UPDATE collages SET $SET WHERE ID='$CollageID'");

    write_log("Collage ".$CollageID." (".db_string($_POST['name']).") was edited by ".$LoggedUser['Username']." - edited details");
}

$Cache->delete_value('collage_'.$CollageID);
header('Location: collages.php?id='.$CollageID);
