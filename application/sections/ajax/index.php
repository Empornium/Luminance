<?php
/*
AJAX Switch Center

This page acts as an AJAX "switch" - it's called by scripts, and it includes the required pages.

The required page is determined by $_GET['action'].

*/

enforce_login();

header('Content-Type: application/json; charset=utf-8');

switch ($_GET['action']) {
    // things that (may be) used on the site
    case 'change_donation':
        require(SERVER_ROOT.'/sections/ajax/change_donation.php');
        break;
    case 'check_donation':
        require(SERVER_ROOT.'/sections/ajax/check_donation.php');
        break;

    case 'check_synonym_list':
        require(SERVER_ROOT.'/sections/ajax/check_synonym_list.php');
        break;
    case 'input_synonyms_list':
        require(SERVER_ROOT.'/sections/ajax/input_synonyms_list.php');
        break;
    case 'get_taglist':
        require(SERVER_ROOT.'/sections/ajax/get_taglist.php');
        break;

    case 'get_ip_dupes':
        require(SERVER_ROOT.'/sections/ajax/get_ip_dupes.php');
        break;

    case 'get_badge_info':
        require(SERVER_ROOT.'/sections/ajax/get_badge_info.php');
        break;
    case 'upload_section':
        // Gets one of the upload forms
        require(SERVER_ROOT.'/sections/ajax/upload.php');
        break;
    case 'preview':
        require 'preview.php';
        break;
    case 'preview_staffpm':
        require 'preview_staffpm.php';
        break;
    case 'preview_article':
        require 'preview_article.php';
        break;
    case 'preview_newpm':
        require 'preview_newpm.php';
        break;
    case 'preview_upload':
        require 'preview_upload.php';
        break;
    case 'preview_image':
        require 'preview_image.php';
        break;
    case 'preview_blog':
        require 'preview_blog.php';
        break;
      case 'preview_edit_torrent':
        require 'preview_edit_torrent.php';
        break;
    case 'torrent_info':
        require 'torrent_info.php';
        break;
    case 'giveback_report':
        require 'giveback_report.php';
        break;
    case 'grab_report':
        require 'grab_report.php';
        break;
    case 'stats':
        require(SERVER_ROOT.'/sections/ajax/stats.php');
        break;
    case 'get_smilies':
        require 'get_smilies.php';
        break;

    case 'connchecker':
        include 'do_conncheck.php';
        break;
    case 'remove_conn_status':
        include 'remove_connstatus.php';
        break;
    case 'delete_conn_record':
        include 'delete_connrecord.php';
        break;

    case 'watchlist_add':
    case 'watchlist_remove':
    case 'excludelist_add':
    case 'excludelist_remove':
    case 'remove_records':
        include 'do_watchlist.php';
        break;

    // things not yet used on the site
    case 'torrentgroup':
        // disabled, get_group_info() is broken for this code.
                //require('torrentgroup.php');
        break;
    case 'user':
        require(SERVER_ROOT.'/sections/ajax/user.php');
        break;
    case 'forum':
        require(SERVER_ROOT.'/sections/ajax/forum/index.php');
        break;
    case 'top10':
        require(SERVER_ROOT.'/sections/ajax/top10/index.php');
        break;
    case 'browse':
        require(SERVER_ROOT.'/sections/ajax/browse.php');
        break;
    case 'usersearch':
        require(SERVER_ROOT.'/sections/ajax/usersearch.php');
        break;
    case 'requests':
        require(SERVER_ROOT.'/sections/ajax/requests.php');
        break;
    case 'inbox':
        require(SERVER_ROOT.'/sections/ajax/inbox/index.php');
        break;
    case 'subscriptions':
        require(SERVER_ROOT.'/sections/ajax/subscriptions.php');
        break;
    case 'index':
        require(SERVER_ROOT.'/sections/ajax/info.php');
        break;
    case 'bookmarks':
        require(SERVER_ROOT.'/sections/ajax/bookmarks.php');
        break;
    case 'notifications':
        require(SERVER_ROOT.'/sections/ajax/notifications.php');
        break;
    case 'request':
        require(SERVER_ROOT.'/sections/ajax/request.php');
        break;
    case 'loadavg':
        require(SERVER_ROOT.'/sections/ajax/loadavg.php');
        break;
    default:
        // If they're screwing around with the query string
        print json_encode(array('status' => 'failure'));
}
