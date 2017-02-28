<?php
enforce_login();
if (!check_perms('torrents_review_manage')) error(403);

$IsAjax = isset($_POST['submit']) && $_POST['submit'] == 'Save'? FALSE : TRUE;

$Sort = isset($_POST['sort'])? trim($_POST['sort']):false;
$Name = isset($_POST['name'])? trim($_POST['name']):false;
$Description = isset($_POST['description'])? $_POST['description']:false;

if ($Sort && $Name && $Description && ($Sort != "") && ($Name != "") && (trim($Description) != "")) {

    include(SERVER_ROOT.'/classes/class_text.php');
    $Text = new TEXT;
    if (!$Text->validate_bbcode($Description,  get_permissions_advtags($LoggedUser['ID']), !$IsAjax)) {
        echo "There are errors in your bbcode (unclosed tags)";
        die();
    }

    $Sort = db_string($Sort);
    $Name = db_string($Name);
    $Description = db_string($Description);
    $ID = (int) $_POST['id'];
    if (is_numeric($ID)) {
        if ($ID == 0) {
            // Create new response
            $DB->query("INSERT INTO review_reasons (Sort, Name, Description) VALUES('$Sort', '$Name', '$Description')");
            // if submit is set then this is not an ajax response - reload page and pass vars for message & return convid
            if (!$IsAjax) {
                $InsertedID = $DB->inserted_id();
                header("Location: tools.php?action=marked_for_deletion_reasons&added=$InsertedID");
            } else
                echo '1';
        } else {
            $DB->query("SELECT * FROM review_reasons WHERE ID=$ID");
            if ($DB->record_count() != 0) {
                // Edit response
                $DB->query("UPDATE review_reasons SET Sort='$Sort', Name='$Name', Description='$Description' WHERE ID=$ID");
                echo '2';
            } else {
                // Create new response
                $DB->query("INSERT INTO review_reasons (Sort, Name, Description) VALUES('$Sort', '$Name', '$Description')");
                // if submit is set then this is not an ajax response - reload page and pass vars for message & return convid
                if (!$IsAjax) {
                    $InsertedID = $DB->inserted_id();
                    header("Location: tools.php?action=marked_for_deletion_reasons&added=$InsertedID");
                } else
                    echo '1';
            }
        }
    } else {
        // No id
        if (!$IsAjax) {
            header("Location: tools.php?action=marked_for_deletion_reasons&added=-2");
        } else
            echo '-2';
    }

} else {
    // No message/name
    if (!$IsAjax) {
        header("Location: tools.php?action=marked_for_deletion_reasons&added=-1");
    } else
        echo '-1';
}

