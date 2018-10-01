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
        require(SERVER_ROOT.'/Legacy/sections/ajax/change_donation.php');
        break;
    case 'check_donation':
        require(SERVER_ROOT.'/Legacy/sections/ajax/check_donation.php');
        break;

    case 'check_synonym_list':
        require(SERVER_ROOT.'/Legacy/sections/ajax/check_synonym_list.php');
        break;
    case 'input_synonyms_list':
        require(SERVER_ROOT.'/Legacy/sections/ajax/input_synonyms_list.php');
        break;
    case 'get_taglist':
        require(SERVER_ROOT.'/Legacy/sections/ajax/get_taglist.php');
        break;
    case 'get_tagdetails':
        require(SERVER_ROOT.'/Legacy/sections/ajax/get_tagdetails.php');
        break;

    case 'get_ip_dupes':
        require(SERVER_ROOT.'/Legacy/sections/ajax/get_ip_dupes.php');
        break;

    case 'get_badge_info':
        require(SERVER_ROOT.'/Legacy/sections/ajax/get_badge_info.php');
        break;

    case 'get_options':
        echo json_encode(['MinCreateBounty' => $master->options->MinCreateBounty,
                          'MinVoteBounty'   => $master->options->MinVoteBounty,
                         ]);
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

    case 'takeedit_post':
        require(SERVER_ROOT.'/common/takeedit_post.php');
        break;

    case 'giveback_report':
        require 'giveback_report.php';
        break;
    case 'grab_report':
        require 'grab_report.php';
        break;
    case 'stats':
        require(SERVER_ROOT.'/Legacy/sections/ajax/stats.php');
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
    default:
        // If they're screwing around with the query string
        print json_encode(array('status' => 'failure'));
}
