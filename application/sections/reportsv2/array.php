<?php

/*
 * The $Types array is the backbone of the reports system and is stored here so it can
 * be included on the pages that need it, but not clog up the pages that don't.
 * Important thing to note about the array:
 * 1. When coding for a non music site, you need to ensure that the top level of the
 * array lines up with the $Categories array in your config.php.
 * 2. The first sub array contains resolves that are present on every report type
 * regardless of category.
 * 3. The only part that shouldn't be self explanatory is that for the tracks field in
 * the report_fields arrays, 0 means not shown, 1 means required, 2 means required but
 * you can't tick the 'All' box.
 * 4. The current report_fields that are set up are tracks, sitelink, link and image. If
 * you wanted to add a new one, you'd need to add a field to the reportsv2 table, elements
 * to the relevant report_fields arrays here, add the HTML in ajax_report and add security
 * in takereport.
 */

$Types = array(
    'dupe' => array(
        'priority' => '1',
        'title' => 'Dupe',
        'report_messages' => array(
            'Please specify a link to the original torrent.',
            "Note: It's ok to dupe torrents that are over " . time_diff(time()+ (EXCLUDE_DUPES_AFTER_DAYS*24*3600),1,false,false,0). " old and have less than ".EXCLUDE_DUPES_SEEDS." seeders.",
            "The site will not accept reports where the duped torrent fits this criteria."
        ),
        'report_fields' => array(
            'sitelink' => '1'
        ),
        'resolve_options' => array(
            'upload' => '0',
            'warn' => '0',
            'delete' => '1',
            'pm' => 'Your torrent has been deleted for being a duplicate of another torrent.',
            'bounty' => 1000
        ),
        'article' => array ('duperules', "Dupe Rules")
    ),
    'banned' => array(
        'priority' => '23',
        'title' => 'Specifically Banned',
        'report_messages' => array(
            'Please specify exactly which entry on the Do Not Upload list this is violating.'
        ),
        'report_fields' => array(
        ),
        'resolve_options' => array(
            'upload' => '0',
            'warn' => '4',
            'delete' => '1',
            'pm' => 'The releases on the Do Not Upload list (on the upload page) are currently forbidden from being uploaded from the site. Do not upload them unless your torrent meets a condition specified in the comment.',
            'bounty' => 2000
        ),
        'article' => array ('forbiddencontent', "Forbidden Content (Do Not Upload list)")
    ),
    'urgent' => array(
        'priority' => '28',
        'title' => 'Urgent',
        'report_messages' => array(
            'This report type is only for the very urgent reports, usually for personal information being found within a torrent.',
            'Abusing the Urgent reports could result in a warning or worse',
            'As by default this report type gives the staff absolutely no information about the problem, please be as clear as possible in your comments as to the problem'
        ),
        'report_fields' => array(
            'sitelink' => '0',
            'track' => '0',
            'link' => '0',
            'image' => '0',
        ),
        'resolve_options' => array(
            'upload' => '0',
            'warn' => '0',
            'delete' => '0',
            'pm' => '',
            'bounty' => 0
        )
    ),
    'other' => array(
        'priority' => '20',
        'title' => 'Other',
        'report_messages' => array(
            'Please include as much information as possible to verify the report'
        ),
        'report_fields' => array(
        ),
        'resolve_options' => array(
            'upload' => '0',
            'warn' => '0',
            'delete' => '0',
            'pm' => '',
            'bounty' => 0
        )
    ),
    'screens' => array(
        'priority' => '8',
        'title' => 'No Images',
        'report_messages' => array(
            'If possible, please provide a link to proper screens',
        ),
        'report_fields' => array(
            'link' => '0'
        ),
        'resolve_options' => array(
            'upload' => '0',
            'warn' => '0',
            'delete' => '0',
            'pm' => 'The torrent must have screenshots as per the rules.',
            'bounty' => 0
        ),
        'article' => array ('screenrules', "Screenshot Rules")
    ),
    'description' => array(
        'priority' => '8',
        'title' => 'No Description',
        'report_messages' => array(
            'If possible, please provide a link to an accurate description',
        ),
        'report_fields' => array(
            'link' => '0'
        ),
        'resolve_options' => array(
            'upload' => '0',
            'warn' => '0',
            'delete' => '0',
            'pm' => 'Torrents must have a description that describes the content of the torrent.',
            'bounty' => 0
        ),
        'article' => array ('descrules', "Description Rules")
    ),
    'pack' => array(
        'priority' => '2',
        'title' => 'Compressed Files',
        'report_messages' => array(
            'Please include as much information as possible to verify the report'
        ),
        'report_fields' => array(
            'link' => '0'
        ),
        'resolve_options' => array(
            'upload' => '0',
            'warn' => '0',
            'delete' => '1',
            'pm' => 'Torrents can not contain compressed files such as .rar or .zip, unless it contains a large number of images.',
            'bounty' => 0
        ),
        'article' => array ('ziprules', "Compressed Files Rules")
    ),
    'virus' => array(
        'priority' => '6',
        'title' => 'Contains Virus',
        'report_messages' => array(
            'Please include as much information as possible to verify the report.  Please also double check that your virus scanner is not incorrectly identifying a keygen or crack as a virus.',
        ),
        'report_fields' => array(
            'link' => '0'
        ),
        'resolve_options' => array(
            'upload' => '0',
            'warn' => '4',
            'delete' => '1',
            'pm' => 'The torrent was determined to be infected with a virus/trojan. In the future, please scan all potential uploads with an antivirus program such as AVG, Avast, or MS Security Essentials.',
            'bounty' => 0
        )
    ),
    'notwork' => array(
        'priority' => '6',
        'title' => 'Not Working',
        'report_messages' => array(
            'Please include as much information as possible to verify the report.',
        ),
        'report_fields' => array(
            'link' => '0'
        ),
        'resolve_options' => array(
            'upload' => '0',
            'warn' => '0',
            'delete' => '1',
            'pm' => 'The torrent content was determined to be not fully functional.',
            'bounty' => 0
        )
    )
);
