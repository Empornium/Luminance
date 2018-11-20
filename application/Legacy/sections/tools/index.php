<?php
/* * ***************************************************************
  Tools switch center

  This page acts as a switch for the tools pages.

  TODO!
  -Unify all the code standards and file names (tool_list.php,tool_add.php,tool_alter.php)

 * *************************************************************** */

if (isset($argv[1])) {
    if ($argv[1] == "cli_sandbox") {
        include 'misc/cli_sandbox.php';
        die();
    }

    $_REQUEST['action'] = $argv[1];
} else {
    if (empty($_REQUEST['action']) || ($_REQUEST['action'] != "public_sandbox" && $_REQUEST['action'] != "ocelot")) {
        enforce_login();
    }
}

if (!isset($_REQUEST['action'])) {
    include(SERVER_ROOT . '/Legacy/sections/tools/tools.php');
    die();
}

if (substr($_REQUEST['action'], 0, 7) == 'sandbox' && !isset($argv[1])) {
    if (!check_perms('site_debug')) {
        error(403);
    }
}

if (substr($_REQUEST['action'], 0, 12) == 'update_geoip' && !isset($argv[1])) {
    if (!check_perms('site_debug')) {
        error(403);
    }
}

$Val = new Luminance\Legacy\Validate;

$Feed = new Luminance\Legacy\Feed;

switch ($_REQUEST['action']) {
    case 'phpinfo':
        if (!check_perms('site_debug')) {
            error(403);
        }
        phpinfo();
        break;
    //Services
    case 'get_host':
        include(SERVER_ROOT . '/Legacy/sections/tools/services/get_host.php');
        break;
    case 'get_cc':
        include(SERVER_ROOT . '/Legacy/sections/tools/services/get_cc.php');
        break;

    //Managers
    case 'site_options':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/site_options_list.php');
        break;
    case 'take_site_options':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/site_options_alter.php');
        break;

    case 'languages':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/languages_list.php');
        break;
    case 'languages_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/languages_alter.php');
        break;

    case 'speed_watchlist':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/speed_watchlist.php');
        break;
    case 'speed_excludelist':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/speed_excludelist.php');
        break;

    case 'speed_records':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/speed_reports_list.php');
        break;
    case 'speed_cheats':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/speed_cheats.php');
        break;
    case 'speed_zerocheats':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/speed_zerocheats.php');
        break;

    case 'ban_zero_cheat':
        if (!check_perms('admin_manage_cheats')) {
            error(403);
        }

        if ($_REQUEST['banuser'] && is_number($_REQUEST['userid'])) {
            $DB->query("SELECT UserID FROM users_not_cheats WHERE UserID='$_REQUEST[userid]' ");
            if ($DB->record_count()>0) {
                error("This user is in the 'exclude user' list - you must remove them from the list if you want to ban them from this page");
            }

            disable_users(array($_REQUEST['userid']), "Disabled for cheating (0 stat downloading) by $LoggedUser[Username]", 4);
        }

        header("Location: tools.php?action=speed_zerocheats");

        break;
    case 'ban_pattern_cheat':
        if (!check_perms('admin_manage_cheats')) {
            error(403);
        }

        if ($_REQUEST['banuser'] && is_number($_REQUEST['userid'])) {
            $DB->query("SELECT UserID FROM users_not_cheats WHERE UserID='$_REQUEST[userid]' ");
            if ($DB->record_count()>0) {
                error("This user is in the 'exclude user' list - you must remove them from the list if you want to ban them from this page");
            }

            disable_users(array($_REQUEST['userid']), "Disabled for cheating ($_REQUEST[pattern] matching records) by $LoggedUser[Username]", 4);
        }
        if (isset($_REQUEST['returnto']) && $_REQUEST['returnto']=='cheats') {
            $returnto = 'speed_cheats';
        } else {
            $returnto = 'speed_records';
        }
        header("Location: tools.php?action=$returnto");

        break;
    case 'ban_speed_cheat':
        if (!check_perms('admin_manage_cheats')) {
            error(403);
        }

        if ($_REQUEST['banuser'] && is_number($_REQUEST['userid'])) {
            $DB->query("SELECT UserID FROM users_not_cheats WHERE UserID='$_REQUEST[userid]' ");
            if ($DB->record_count()>0) {
                error("This user is in the 'exclude user' list - you must remove them from the list if you want to ban them from this page");
            }

            $DB->query("SELECT MAX(upspeed) FROM xbt_peers_history WHERE uid='$_REQUEST[userid]' ");
            list($Maxspeed) = $DB->next_record();
            disable_users(array($_REQUEST['userid']), "Disabled for speeding (maxspeed=" . get_size($Maxspeed) . "/s) by $LoggedUser[Username]", 4);
        } elseif ($_POST['banusers'] && is_number($_POST['banspeed']) && $_POST['banspeed'] > 0) {
            $DB->query("SELECT GROUP_CONCAT(DISTINCT xbt.uid SEPARATOR '|')
                          FROM xbt_peers_history AS xbt JOIN users_main AS um ON um.ID=xbt.uid
                       LEFT JOIN users_not_cheats AS nc ON nc.UserID=xbt.uid
                         WHERE um.Enabled='1' AND nc.UserID IS NULL AND xbt.upspeed >='$_POST[banspeed]' ");

            list($UserIDs) = $DB->next_record();
            if ($UserIDs) {
                $UserIDs = explode('|', $UserIDs);
                if (count($UserIDs)>0) {
                    //error(print_r($UserIDs, true));
                    disable_users($UserIDs, "Disabled for speeding (mass banned users with speed>" . get_size($_POST['banspeed']) . "/s) by $LoggedUser[Username]", 4);
                }
            }
        }
        if (isset($_REQUEST['returnto']) && $_REQUEST['returnto']=='cheats') {
            $returnto = 'speed_cheats';
        } else {
            $returnto = 'speed_records';
        }
        header("Location: tools.php?action=$returnto&viewspeed=$_POST[banspeed]&banspeed=$_POST[banspeed]");
        break;

    case 'edit_userwl':
        if (!check_perms('users_manage_cheats')) {
            error(403);
        }
        if (!isset($_POST['userid']) || !is_number($_POST['userid']) || $_POST['userid'] == 0) {
            error(0);
        }
        $UserID = (int) $_POST['userid'];
        if ($_POST['submit'] == 'Remove') {
            $DB->query("DELETE FROM users_watch_list WHERE UserID='$UserID'");
            if ($DB->affected_rows() > 0) {
                write_user_log($UserID, "User removed from watchlist by $LoggedUser[Username]");
            }
        } elseif ($_POST['submit'] == 'Delete records') {
            $DB->query("DELETE FROM xbt_peers_history WHERE uid='$UserID'");
        } elseif ($_POST['submit'] == 'Save') {
            $KeepTorrents = $_POST['keeptorrent'] == '1' ? '1' : '0';
            $DB->query("UPDATE users_watch_list SET KeepTorrents='$KeepTorrents' WHERE UserID='$UserID'");
        }
        if (isset($_REQUEST['returnto']) && $_REQUEST['returnto']=='cheats') {
            $returnto = 'speed_cheats';
        } else {
            $returnto = 'speed_records';
        }
        header("Location: tools.php?action=$returnto&viewspeed=$_POST[viewspeed]");
        break;
    case 'edit_torrentwl':
        if (!check_perms('users_manage_cheats')) {
            error(403);
        }

        if (!isset($_POST['torrentid']) || !is_number($_POST['torrentid']) || $_POST['torrentid'] == 0) {
            error(0);
        }
        $TorrentID = (int) $_POST['torrentid'];

        if ($_POST['submit'] == 'Remove') {
            $DB->query("DELETE FROM torrents_watch_list WHERE TorrentID='$TorrentID'");
            if ($DB->affected_rows() > 0) {
                $DB->query("SELECT GroupID FROM torrents WHERE ID='$TorrentID'");
                list($GroupID) = $DB->next_record();
                write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], "Torrent removed from watchlist", '1');
            }
        }
        if (isset($_REQUEST['returnto']) && $_REQUEST['returnto']=='cheats') {
            $returnto = 'speed_cheats';
        } else {
            $returnto = 'speed_records';
        }
        header("Location: tools.php?action=$returnto&viewspeed=$_POST[viewspeed]");
        break;
    case 'save_records_options':
        if (!check_perms('admin_manage_cheats')) {
            error(403);
        }

        $master->options->DeleteRecordsMins = (int) $_POST['delrecordmins'];
        $master->options->KeepSpeed = (int) $_POST['keepspeed'];

        if (isset($_REQUEST['returnto']) && $_REQUEST['returnto']=='cheats') {
            $returnto = 'speed_cheats';
        } else {
            $returnto = 'speed_records';
        }
        header("Location: tools.php?action=$returnto&viewspeed=$_POST[viewspeed]");
        break;
    case 'delete_speed_records':
        if (!check_perms('users_manage_cheats')) {
            error(403);
        }

        if (!isset($_POST['rid']) || !is_array($_POST['rid'])) {
            error('You didn\'t select any records to delete.');
        }
        $recordIDS = $_POST['rid'];
        foreach ($recordIDS as $rid) {
            $rid = trim($rid);
            if (!is_number($rid)) {
                error(0);
            }
        }
        $recordIDS = implode(',', $recordIDS);
        $DB->query("DELETE FROM xbt_peers_history WHERE ID IN ($recordIDS)");
        if (isset($_REQUEST['returnto']) && $_REQUEST['returnto']=='cheats') {
            $returnto = 'speed_cheats';
        } else {
            $returnto = 'speed_records';
        }
        header("Location: tools.php?action=$returnto&viewspeed=$_POST[viewspeed]");
        break;

    case 'test_delete_schedule':
        if (!check_perms('admin_manage_cheats')) {
            error(403);
        }
        //------------ Remove unwatched and unwanted speed records
        // as we are deleting way way more than keeping, and to avoid exceeding lockrow size in innoDB we do it another way:
        $master->db->raw_query("DROP TABLE IF EXISTS temp_copy"); // jsut in case!
        $master->db->raw_query("CREATE TABLE `temp_copy` LIKE xbt_peers_history");

        // insert the records we want to keep into the temp table
        $master->db->raw_query(
            "INSERT INTO temp_copy (
                                     SELECT x.*
                                       FROM xbt_peers_history AS x
                                  LEFT JOIN users_watch_list AS uw ON uw.UserID=x.uid
                                  LEFT JOIN torrents_watch_list AS tw ON tw.TorrentID=x.fid
                                      WHERE uw.UserID IS NOT NULL
                                         OR tw.TorrentID IS NOT NULL
                                         OR x.upspeed >= :keepSpeed
                                         OR x.mtime   >  :keepTime)",
            [':keepSpeed' => $master->options->KeepSpeed,
            ':keepTime' => (time() - ( $master->options->DeleteRecordsMins * 60))]
        );

        //Use RENAME TABLE to atomically move the original table out of the way and rename the copy to the original name:
        $master->db->raw_query("RENAME TABLE xbt_peers_history TO temp_old, temp_copy TO xbt_peers_history");
        //Drop the original table:
        $master->db->raw_query("DROP TABLE temp_old");

        header("Location: tools.php?action=speed_records&viewspeed=$_POST[viewspeed]");
        break;

    case 'forum':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/forum_list.php');
        break;

    case 'forum_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/forum_alter.php');
        break;

    case 'forum_categories_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/forum_categories_alter.php');
        break;

    case 'client_blacklist':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/blacklist_list.php');
        break;

    case 'client_blacklist_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/blacklist_alter.php');
        break;

    case 'login_watch':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/login_watch.php');
        break;

    case 'security_logs':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/security_logs.php');
        break;

    case 'disabled_hits':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/disabled_hits.php');
        break;

    case 'email_blacklist':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/eb.php');
        break;

    case 'eb_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/eb_alter.php');
        break;

    case 'dnu':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/dnu_list.php');
        break;

    case 'dnu_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/dnu_alter.php');
        break;

    case 'imghost_whitelist':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/imagehost_list.php');
        break;

    case 'iw_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/imagehost_alter.php');
        break;

    case 'shop_list':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/shop_list.php');
        break;

    case 'shop_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/shop_alter.php');
        break;

    case 'events_list':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/events_list.php');
        break;

    case 'events_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/events_alter.php');
        break;

    case 'badges_list':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/badges_list.php');
        break;

    case 'badges_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/badges_alter.php');
        break;

    case 'awards_auto':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/awards_auto_list.php');
        break;

    case 'awards_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/awards_auto_alter.php');
        break;

    case 'categories':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/categories_list.php');
        break;

    case 'categories_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/categories_alter.php');
        break;

    case 'takeeditnews':
    case 'takenewnews':
    case 'deletenews':
    case 'editnews':
    case 'news':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/news.php');
        break;

    case 'editarticle':
    case 'takearticle':
    case 'deletearticle':
    case 'takeeditarticle':
    case 'articles':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/articles.php');
        break;

    case 'tokens':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/tokens.php');
        break;
    case 'ocelot':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/ocelot.php');
        break;
    case 'ocelot_info':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/ocelot_info.php');
        break;

    case 'official_tags':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/official_tags.php');
        break;
    case 'official_tags_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/official_tags_alter.php');
        break;
    case 'official_synonyms':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/official_synonyms.php');
        break;
    case 'official_synonyms_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/official_synonyms_alter.php');
        break;
    case 'synonyms_admin':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/synonyms_admin.php');
        break;
    case 'synonyms_admin_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/synonyms_admin_alter.php');
        break;
    case 'official_goodtags':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/official_goodtags.php');
        break;
    case 'official_goodtags_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/official_goodtags_alter.php');
        break;
    case 'tags_admin':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/tags_admin.php');
        break;
    case 'tags_activity':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/tags_activity.php');
        break;
    case 'tags_admin_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/tags_admin_alter.php');
        break;
    case 'tags_goodbad':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/tags_goodbad.php');
        break;
    case 'tags_goodbad_alter':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/tags_goodbad_alter.php');
        break;

    case 'marked_for_deletion':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/mfd_functions.php');
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/mfd_manager.php');
        break;
    case 'save_mfd_options':
        enforce_login();
        authorize();

        if (!check_perms('torrents_review_manage')) {
            error(403);
        }

        if (isset($_POST['hours']) && is_number($_POST['hours']) &&
            isset($_POST['autodelete']) && is_number($_POST['autodelete'])) {
            $master->options->MFDReviewHours = (int) $_POST['hours'];
            $master->options->MFDAutoDelete  = (int) $_POST['autodelete'] == 1 ? 1 : 0;
        }
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/mfd_functions.php');
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/mfd_manager.php');
        break;

    case 'mfd_delete':
        enforce_login();
        authorize();

        include 'managers/mfd_functions.php';

        if (!check_perms('torrents_review')) {
            error(403);
        }

        if (isset($_POST['submitdelall'])) {
            $Torrents = get_torrents_under_review('warned', true);
            if (count($Torrents)) {
                //$NumTorrents = count($Torrents); //echo "Num to delete: $NumTorrents";
                $NumDeleted = delete_torrents_list($Torrents);
            }
        } elseif ($_POST['submit'] == 'Delete selected') {
            // if ( !check_perms('torrents_review_manage')) error(403); ??

            $IDs = $_POST['id'];
            $Torrents = get_torrents_under_review('both', true, $IDs);
            if (count($Torrents)) {
                $NumDeleted = delete_torrents_list($Torrents);
            }
        }
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/mfd_manager.php');
        break;

    case 'marked_for_deletion_reasons':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/mfd_reasons.php');
        break;

    case 'mfd_edit_reason':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/ajax_mfd_edit_reason.php');
        break;

    case 'mfd_delete_reason':
         include(SERVER_ROOT . '/Legacy/sections/tools/managers/ajax_mfd_delete_reason.php');
        break;

    case 'mfd_preview_reason':
         include(SERVER_ROOT . '/Legacy/sections/tools/managers/ajax_mfd_preview_reason.php');
        break;

    case 'permissions':
        if (!check_perms('admin_manage_permissions')) {
            error(403);
        }

        if (!empty($_REQUEST['id'])) {
            $Values = array();
            if (is_numeric($_REQUEST['id'])) {
                $DB->query("SELECT p.ID,p.Name,p.Description,p.Level,p.Values,p.DisplayStaff,p.IsUserClass,
                                   p.MaxSigLength,p.MaxAvatarWidth,p.MaxAvatarHeight,
                                   p.isAutoPromote,p.reqWeeks,p.reqUploaded,p.reqTorrents,p.reqForumPosts,p.reqRatio,
                                   p.Color
                              FROM permissions AS p WHERE p.ID='" . db_string($_REQUEST['id']) . "' ");
                list($ID, $Name, $Description, $Level, $Values, $DisplayStaff, $IsUserClass, $MaxSigLength, $MaxAvatarWidth, $MaxAvatarHeight, $isAutoPromote, $reqWeeks, $reqUploaded, $reqTorrents, $reqForumPosts, $reqRatio, $Color) = $DB->next_record(MYSQLI_NUM, array(4));

                if ($IsUserClass == '1' && ($Level > $LoggedUser['Class'] || $_REQUEST['level'] > $LoggedUser['Class'])) {
                    error(403);
                }
                $JoinOn = $IsUserClass == '1' ? 'PermissionID' : 'GroupPermissionID';
                $DB->query("SELECT COUNT(ID) FROM users_main WHERE $JoinOn='" . db_string($_REQUEST['id']) . "' ");
                list($UserCount) = $DB->next_record(MYSQLI_NUM);

                $Values = unserialize($Values);
            } else {
                $IsUserClass = isset($_REQUEST['isclass']) && $_REQUEST['isclass'] == 1 ? '1' : '0';
            }

            if (!empty($_POST['submit'])) {
                $Values = array();
                $Val->SetFields('name', true, 'string', 'You did not enter a valid name for this permission set.');
                if ($IsUserClass) {
                    $Val->SetFields('level', true, 'number', 'You did not enter a valid level for this permission set.');
                    $Val->SetFields('maxsiglength', true, 'number', 'You did not enter a valid number for MaxSigLength.');
                    $Val->SetFields('maxavatarwidth', true, 'number', 'You did not enter a valid number for MaxAvavtarWidth.');
                    $Val->SetFields('maxavatarheight', true, 'number', 'You did not enter a valid number for MaxAvavtarHeight.');
                    $Val->SetFields('color', true, 'string', 'You did not enter a valid hex color.', array('minlength' => 6, 'maxlength' => 6));
                    $Val->SetFields('maxcollages', true, 'number', 'You did not enter a valid number of personal collages.');

                    if (!is_numeric($_REQUEST['id'])) {
                        $DB->query("SELECT ID FROM permissions WHERE Level='" . db_string($_REQUEST['level']) . "'");
                        list($DupeCheck) = $DB->next_record();
                        if ($DupeCheck) {
                            $Err = "There is already a user class with that level.";
                        }
                    }
                    $Level           = $_REQUEST['level'];
                    $DisplayStaff    = $_REQUEST['displaystaff'];
                    $MaxSigLength    = $_REQUEST['maxsiglength'];
                    $MaxAvatarWidth  = $_REQUEST['maxavatarwidth'];
                    $MaxAvatarHeight = $_REQUEST['maxavatarheight'];
                    $isAutoPromote   = $_REQUEST['isautopromote'];
                    $reqWeeks        = $_REQUEST['reqweeks'];
                    $reqUploaded     = get_bytes($_REQUEST['requploaded']);
                    $reqTorrents     = $_REQUEST['reqtorrents'];
                    $reqForumPosts   = $_REQUEST['reqforumposts'];
                    $reqRatio        = $_REQUEST['reqratio'];
                    $Color = strtolower($_REQUEST['color']);
                    $Values['MaxCollages'] = $_REQUEST['maxcollages'];
                } else {
                    if (!is_numeric($_REQUEST['id'])) { // new record
                        $DB->query("SELECT ID FROM permissions WHERE Name='" . db_string($_REQUEST['name']) . "'");
                        list($DupeCheck) = $DB->next_record();
                        if ($DupeCheck) {
                            $Err = "There is already a permission class with that name.";
                        }
                    }
                    $Level = 202;
                    $DisplayStaff = '0';
                    $Val->SetFields('description', true, 'string', 'You did not enter a valid description.');
                    $Val->SetFields('color', true, 'string', 'You did not enter a valid hex color.', array('minlength' => 6, 'maxlength' => 6));
                    $Description = $_REQUEST['description'];
                    $Color = strtolower($_REQUEST['color']);
                    $Values['MaxCollages'] = $_REQUEST['maxcollages'];
                }
                if (!$Err) {
                    $Err = $Val->ValidateForm($_POST);
                }

                foreach ($_REQUEST as $Key => $Perms) {
                    if (substr($Key, 0, 5) == "perm_") {
                        $Values[substr($Key, 5)] = (int) $Perms;
                    }
                }

                $Name = $_REQUEST['name'];

                if (!$Err) {
                    if (!is_numeric($_REQUEST['id'])) {
                        $DB->query("INSERT INTO permissions
                                            (Level,Description,Name,`Values`,DisplayStaff,IsUserClass,MaxSigLength,MaxAvatarWidth,MaxAvatarHeight,isAutoPromote,reqWeeks,reqUploaded,reqTorrents,reqForumPosts,reqRatio,Color)
                                     VALUES ('" . db_string($Level)
                                        . "','" . db_string($Description)
                                        . "','" . db_string($Name)
                                        . "','" . db_string(serialize($Values))
                                        . "','" . db_string($DisplayStaff)
                                        . "','" . db_string($IsUserClass)
                                        . "','" . db_string($MaxSigLength)
                                        . "','" . db_string($MaxAvatarWidth)
                                        . "','" . db_string($MaxAvatarHeight)
                                        . "','" . db_string($isAutoPromote)
                                        . "','" . db_string($reqWeeks)
                                        . "','" . db_string($reqUploaded)
                                        . "','" . db_string($reqTorrents)
                                        . "','" . db_string($reqForumPosts)
                                        . "','" . db_string($reqRatio)
                                        . "','" . db_string($Color) . "')");
                    } else {
                        $DB->query("UPDATE permissions SET Level='" . db_string($Level)
                           . "',Description='" . db_string($Description)
                           . "',Name='" . db_string($Name)
                           . "',`Values`='" . db_string(serialize($Values))
                           . "',DisplayStaff='" . db_string($DisplayStaff)
                           . "',MaxSigLength='" . db_string($MaxSigLength)
                           . "',MaxAvatarWidth='" . db_string($MaxAvatarWidth)
                           . "',MaxAvatarHeight='" . db_string($MaxAvatarHeight)
                           . "',isAutoPromote='" . db_string($isAutoPromote)
                           . "',reqWeeks='" . db_string($reqWeeks)
                           . "',reqUploaded='" . db_string($reqUploaded)
                           . "',reqTorrents='" . db_string($reqTorrents)
                           . "',reqForumPosts='" . db_string($reqForumPosts)
                           . "',reqRatio='" . db_string($reqRatio)
                           . "',Color='" . db_string($Color)
                           . "' WHERE ID='" . db_string($_REQUEST['id']) . "'");
                        $Cache->delete_value('perm_' . $_REQUEST['id']);
                    }

                    $master->auth->permissions->uncache($_REQUEST['id']);
                    $Cache->delete_value('classes');
                    $Cache->delete_value('group_permissions');
                } else {
                    error($Err);
                }
            }

            include 'managers/permissions_alter.php';
        } else {
            if (!empty($_REQUEST['removeid']) && is_numeric($_REQUEST['removeid'])) {
                $DB->query("SELECT ID, IsUserClass FROM permissions WHERE ID='" . db_string($_REQUEST['removeid']) . "'");
                list($pID, $IsUserClass) = $DB->next_record(MYSQLI_NUM);
                if ($pID) {
                    $DB->query("DELETE FROM permissions WHERE ID='" . db_string($_REQUEST['removeid']) . "'");
                    $DB->query("UPDATE users_main SET PermissionID='" . APPRENTICE . "' WHERE PermissionID='" . db_string($_REQUEST['removeid']) . "'");
                    $DB->query("UPDATE users_main SET GroupPermissionID='0' WHERE GroupPermissionID='" . db_string($_REQUEST['removeid']) . "'");

                    $master->auth->permissions->uncache($_REQUEST['removeid']);
                    $Cache->delete_value('classes');
                    $Cache->delete_value('group_permissions');
                }
            }

            include(SERVER_ROOT . '/Legacy/sections/tools/managers/permissions_list.php');
        }

        break;

    case 'ip_ban':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/bans.php');
        break;

    //Data
    case 'registration_log':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/registration_log.php');
        break;

    case 'btc_address_input':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/donation_addresses.php');
        break;

    case 'enter_addresses':
        // admin submits new unused addresses
        include(SERVER_ROOT . '/Legacy/sections/tools/data/take_btc_addresses.php');
        break;

    case 'delete_addresses':
        authorize();
        if (!check_perms('admin_donor_addresses')) {
            error(403);
        }

        $AddressIDs = $_POST['deleteids'];
        if (!is_array($AddressIDs)) {
            error("Nothing selected to delete");
        }

        foreach ($AddressIDs as $addressID) {
            if (!is_number($addressID)) {
                error(0);
            }
        }
        $AddressIDs = implode(',', $AddressIDs);

        $DB->query("DELETE FROM bitcoin_addresses WHERE ID IN ($AddressIDs)");

        header("Location: tools.php?action=btc_address_input");
        break;

    case 'new_drive':
        authorize();

        if (!check_perms('admin_donor_drives')) {
            error(403);
        }

        $name = db_string($_REQUEST['drivename']);
        $target_euros = (int) ($_REQUEST['target']);
        $desc = db_string($_REQUEST['body']);

        $DB->query("INSERT INTO donation_drives ( `name`, `target_euros`, `description`)
                                VALUES ( '$name', '$target_euros', '$desc');");

        header("Location: tools.php?action=donation_drives");
        break;

    case 'edit_drive':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/edit_drive.php');
        break;

    case 'donation_log':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/donation_log.php');
        break;

    case 'donation_drives':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/donation_drives.php');
        break;

    case 'upscale_pool':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/upscale_pool.php');
        break;

    case 'invite_pool':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/invite_pool.php');
        break;

    case 'torrent_stats':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/torrent_stats.php');
        break;

    case 'user_flow':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/user_flow.php');
        break;

    case 'economic_stats':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/economic_stats.php');
        break;

    case 'service_stats':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/service_stats.php');
        break;

    case 'database_specifics':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/database_specifics.php');
        break;

    case 'special_users':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/special_users.php');
        break;

    case 'data_viewer':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/data_viewer.php');
        break;

    case 'browser_support':
        include(SERVER_ROOT . '/Legacy/sections/tools/data/browser_support.php');
        break;
    //END Data
    //Misc
    case 'update_geoip':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/update_geoip.php');
        break;

    case 'repair_geoip':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/repair_geodist.php');
        break;

    case 'dupe_ips_old':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/dupe_ip_old.php');
        break;
    case 'dupe_ips':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/dupe_ip.php');
        break;
    case 'banned_ip_users':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/banned_ip_users.php');
        break;

    case 'clear_cache':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/clear_cache.php');
        break;

    case 'create_user':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/create_user.php');
        break;

    case 'manipulate_tree':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/manipulate_tree.php');
        break;

    case 'recommendations':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/recommendations.php');
        break;

    case 'analysis':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/analysis.php');
        break;

    case 'sandbox1':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/sandbox1.php');
        break;

    case 'sandbox2':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/sandbox2.php');
        break;

    case 'sandbox3':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/sandbox3.php');
        break;

    case 'sandbox4':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/sandbox4.php');
        break;

    case 'sandbox5':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/sandbox5.php');
        break;

    case 'sandbox6':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/sandbox6.php');
        break;

    case 'sandbox7':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/sandbox7.php');
        break;

    case 'sandbox8':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/sandbox8.php');
        break;

    case 'public_sandbox':
        include(SERVER_ROOT . '/Legacy/sections/tools/misc/public_sandbox.php');
        break;

    case 'mod_sandbox':
        if (check_perms('users_mod')) {
            include(SERVER_ROOT . '/Legacy/sections/tools/misc/mod_sandbox.php');
        } else {
            error(403);
        }
        break;

    case 'compare_users':
        include(SERVER_ROOT . '/Legacy/sections/tools/services/compare_users.php');
        break;

    case 'manage_invites':
        include(SERVER_ROOT . '/Legacy/sections/tools/managers/manage_invites.php');
        break;

    default:
        include(SERVER_ROOT . '/Legacy/sections/tools/tools.php');
}
