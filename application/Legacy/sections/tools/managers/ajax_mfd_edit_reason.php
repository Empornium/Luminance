<?php
enforce_login();
if (!check_perms('torrent_review_manage')) error(403);

$IsAjax = isset($_POST['submit']) && $_POST['submit'] == 'Save'? FALSE : TRUE;

$Sort = isset($_POST['sort'])? trim($_POST['sort']):false;
$Name = isset($_POST['name'])? trim($_POST['name']):false;
$Description = isset($_POST['description'])? $_POST['description']:false;

if ($Sort && $Name && $Description && ($Sort != "") && ($Name != "") && (trim($Description) != "")) {

    $bbCode = new \Luminance\Legacy\Text;
    if (!$bbCode->validate_bbcode($Description,  get_permissions_advtags($activeUser['ID']), !$IsAjax)) {
        echo "There are errors in your bbcode (unclosed tags)";
        die();
    }

    $ID = (int) $_POST['id'];
    if (is_integer_string($ID)) {
        if ($ID == 0) {
            // Create new response
            $master->db->rawQuery("INSERT INTO review_reasons (Sort, Name, Description) VALUES(?, ?, ?)",
                [$Sort, $Name, $Description]
            );
            // if submit is set then this is not an ajax response - reload page and pass vars for message & return convid
            if (!$IsAjax) {
                $InsertedID = $master->db->lastinsertID();
                header("Location: tools.php?action=marked_for_deletion_reasons&added=$InsertedID");
            } else
                echo '1';
        } else {
            $master->db->rawQuery("SELECT * FROM review_reasons WHERE ID = ?", [$ID]);
            if ($master->db->foundRows() != 0) {
                // Edit response
                $master->db->rawQuery(
                    "UPDATE review_reasons
                        SET Sort = ?,
                            Name = ?,
                            Description = ?
                      WHERE ID = ?",
                    [$Sort, $Name, $Description, $ID]
                );
                echo '2';
            } else {
                // Create new response
                $master->db->rawQuery("INSERT INTO review_reasons (Sort, Name, Description) VALUES(?, ?, ?)",
                    [$Sort, $Name, $Description]
                );
                // if submit is set then this is not an ajax response - reload page and pass vars for message & return convid
                if (!$IsAjax) {
                    $InsertedID = $master->db->lastInsertID();
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
