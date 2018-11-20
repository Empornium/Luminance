<?php
enforce_login();

if (empty($_REQUEST['action'])) {
    $_REQUEST['action']='';
}

switch ($_REQUEST['action']) {
    case 'ajax_get_edit':
        // Page that switches edits for mods
        require(SERVER_ROOT.'/common/ajax_get_edit.php');
        break;
    case 'new':
        if (!check_perms('site_collages_create')) {
            error(403);
        }
        require(SERVER_ROOT.'/Legacy/sections/collages/new.php');
        break;
    case 'new_handle':
        if (!check_perms('site_collages_create')) {
            error(403);
        }
        require(SERVER_ROOT.'/Legacy/sections/collages/new_handle.php');
        break;
    case 'add_torrent':
    case 'add_torrent_batch':
        require(SERVER_ROOT.'/Legacy/sections/collages/add_torrent.php');
        break;
    case 'manage':
        require(SERVER_ROOT.'/Legacy/sections/collages/manage.php');
        break;
    case 'manage_handle':
        require(SERVER_ROOT.'/Legacy/sections/collages/manage_handle.php');
        break;
    case 'edit':
        require(SERVER_ROOT.'/Legacy/sections/collages/edit.php');
        break;
    case 'edit_handle':
        require(SERVER_ROOT.'/Legacy/sections/collages/edit_handle.php');
        break;
    case 'change_level':
            authorize();

            $CollageID = $_POST['collageid'];
        if (!is_number($CollageID)) {
            error(0);
        }

        if (!check_perms('site_collages_manage')) {
            $DB->query("SELECT UserID FROM collages WHERE ID='$CollageID'");
            list($UserID) = $DB->next_record();
            if ($UserID != $LoggedUser['ID']) {
                error(403);
            }
        }

            $Permissions = $_POST['permission'];
        if (!is_number($Permissions)) {
            error(0);
        }
            $Permissions=(int) $Permissions;
        if ($Permissions !=0 && !array_key_exists($Permissions, $ClassLevels)) {
            error(0);
        }

            $DB->query("UPDATE collages SET Permissions=$Permissions WHERE ID='$CollageID'");

            $Cache->delete_value('collage_'.$CollageID);
            header('Location: collages.php?id='.$CollageID);
        break;
    case 'delete':
        authorize();
        require(SERVER_ROOT.'/Legacy/sections/collages/delete.php');
        break;
    case 'take_delete':
        require(SERVER_ROOT.'/Legacy/sections/collages/take_delete.php');
        break;
    case 'allcomments':
        require(SERVER_ROOT.'/Legacy/sections/collages/all_comments.php');
        break;
    case 'add_comment':
        require(SERVER_ROOT.'/Legacy/sections/collages/add_comment.php');
        break;
    case 'comments':
        require(SERVER_ROOT.'/Legacy/sections/collages/all_comments.php');
        break;
    case 'takeedit_comment':
        require(SERVER_ROOT.'/Legacy/sections/collages/takeedit_comment.php');
        break;
    case 'delete_comment':
        require(SERVER_ROOT.'/Legacy/sections/collages/delete_comment.php');
        break;
    case 'get_post':
        require(SERVER_ROOT.'/common/get_post.php');
        break;
    case 'download':
        require(SERVER_ROOT.'/Legacy/sections/collages/download.php');
        break;
    case 'recover':
        //if (!check_perms('')) { error(403); }
        require(SERVER_ROOT.'/Legacy/sections/collages/recover.php');
        break;
    case 'create_personal':
        if (!check_perms('site_collages_personal')) {
            error(403);
        }

        $DB->query("SELECT COUNT(ID) FROM collages WHERE UserID='$LoggedUser[ID]' AND CategoryID='0' AND Deleted='0'");
        list($CollageCount) = $DB->next_record();

        if ($CollageCount >= $LoggedUser['Permissions']['MaxCollages']) {
            list($CollageID) = $DB->next_record();
            header('Location: collage.php?id='.$CollageID);
            die();
        }
        $NameStr = ($CollageCount > 0)?" no. " . ($CollageCount + 1):'';
        $DB->query("INSERT INTO collages (Name, Description, CategoryID, UserID) VALUES ('$LoggedUser[Username]\'s personal collage$NameStr', 'Personal collage for $LoggedUser[Username]. The first 5 torrents will appear on his or her [url=http:\/\/".SITE_URL."\/user.php?id=$LoggedUser[ID]]profile[\/url].', '0', $LoggedUser[ID])");
        $CollageID = $DB->inserted_id();
        header('Location: collage.php?id='.$CollageID);
        die();

    default:
        if (!empty($_GET['id'])) {
            require(SERVER_ROOT.'/Legacy/sections/collages/collage.php');
        } else {
            require(SERVER_ROOT.'/Legacy/sections/collages/browse.php');
        }
        break;
}
