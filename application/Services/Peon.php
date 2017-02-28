<?php
namespace Luminance\Services;

use Luminance\Core\Master;

# The Peon class is a useful little minion that helps tie interface/template logic together
class Peon extends Service {

    public $userinfo_tools_entries = [
        #  permission                   action                                  title
        [ 'admin_manage_articles',     'tools.php?action=articles',            'Articles'                ],
        [ 'site_manage_awards',        'tools.php?action=awards_auto',         'Automatic Awards'        ],
        [ 'site_manage_badges',        'tools.php?action=badges_list',         'Badges'                  ],
        [ 'site_manage_shop',          'tools.php?action=shop_list',           'Bonus Shop'              ],
        [ 'admin_manage_categories',   'tools.php?action=categories',          'Categories'              ],
        [ 'admin_whitelist',           'tools.php?action=client_blacklist',    'Client Blacklist'        ],
        [ 'admin_dnu',                 'tools.php?action=dnu',                 'Do not upload list'      ],
        [ 'admin_email_blacklist',     'tools.php?action=email_blacklist',     'Email Blacklist'         ],
        [ 'admin_manage_forums',       'tools.php?action=forum',               'Forums'                  ],
        [ 'admin_imagehosts',          'tools.php?action=imghost_whitelist',   'Imagehost Whitelist'     ],
        [ 'admin_manage_ipbans',       'tools.php?action=ip_ban',              'IP Bans'                 ],
        [ 'users_view_ips',            'tools.php?action=login_watch',         'Login Watch'             ],
        [ 'users_mod',                 'tools.php?action=tokens',              'Manage freeleech tokens' ],
        [ 'torrents_review',           'tools.php?action=marked_for_deletion', 'Marked for Deletion'     ],
        [ 'admin_manage_news',         'tools.php?action=news',                'News'                    ],
        [ 'site_manage_tags',          'tools.php?action=official_tags',       'Official Tags'           ],
        [ 'site_convert_tags',         'tools.php?action=official_synonyms',   'Official Synonyms'       ],
        [ 'users_fls',                 'torrents.php?action=allcomments',      'Recent Torrent Comments' ],
        [ 'users_fls',                 'requests.php?action=allcomments',      'Recent Request Comments' ],
        [ 'users_fls',                 'collages.php?action=allcomments',      'Recent Collage Comments' ],
        [ 'users_fls',                 'forums.php?action=allposts',           'Recent Posts'            ],
        [ 'admin_manage_languages',    'tools.php?action=languages',           'Site Languages'          ],
        [ 'users_manage_cheats',       'tools.php?action=speed_cheats',        'Speed Cheats'            ],
        [ 'users_manage_cheats',       'tools.php?action=speed_records',       'Speed Reports'           ],
        [ 'admin_manage_site_options', 'tools.php?action=site_options',        'Site Options'            ],
        [ 'admin_manage_permissions',  'tools.php?action=permissions',         'User Classes'            ],
        [ 'users_groups',              'groups.php',                           'User Groups'             ]
    ];

    public $flashStyleClasses = [
        1 => 'success',
        2 => 'notice',
        3 => 'warning',
        4 => 'error',
        5 => 'critical',
    ];

    protected static $useRepositories = [
        'stylesheets' => 'StylesheetRepository',
    ];

    protected static $useServices = [
        'auth'      => 'Auth',
        'flasher'   => 'Flasher',
        'secretary' => 'Secretary',
        'settings'  => 'Settings',
        'tpl'       => 'TPL',
        'cache' => 'Cache',
        'db'    => 'DB',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $master->request;
    }

    protected function get_base_variables () {
        global $Document;

        $user = $this->request->user;
        $base_variables = [];
        $base_variables['master'] = $this->master;
        $base_variables['auth'] = $this->auth;
        $base_variables['flashes'] = $this->flasher->grabFlashes();
        $base_variables['flashStyleClasses'] = $this->flashStyleClasses;
        $base_variables['request'] = $this->request;
        $base_variables['secretary'] = $this->secretary;
        $base_variables['settings'] = $this->settings;
        $base_variables['rss_auth_string'] = $this->getRSSAuthString();
        $base_variables['peon'] = $this;
        $base_variables['static_uri'] = $this->settings->main->static_server;
        $base_variables['main_uri'] = '';
        $base_variables['authenticated'] = isset($this->request->user);
        $base_variables['ActiveUser'] = $user;
        $base_variables['site_name'] = $this->settings->main->site_name;
        $base_variables['site_url'] = $this->settings->main->site_url;
        $base_variables['Notifications'] = $this->get_notifications();
        if ($user) {
            $base_variables['UserStats'] = $this->auth->get_user_stats($user->ID);
            $base_variables['Stylesheet'] = $this->stylesheets->get_by_user($user);
            $base_variables['Permissions'] = $this->auth->get_user_permissions($user);
        }
        $base_variables['Document'] = $Document;
        $base_variables['headeronly'] = false;
        $base_variables['footeronly'] = false;
        return $base_variables;
    }

    protected function getRSSAuthString() {
        $u = $this->request->user;
        if (!$u) {
            return '';
        }
        $rss_auth_string = "user={$u->ID}&auth={$u->legacy['RSS_Auth']}&passkey={$u->legacy['torrent_pass']}&authkey={$u->legacy['AuthKey']}";
        return $rss_auth_string;
    }

    public function display($template, $values) {
        $all_values = array_merge($this->get_base_variables(), $values);
        $this->tpl->display($template, $all_values);
    }

    public function render($template, $values) {
        $all_values = array_merge($this->get_base_variables(), $values);
        $output = $this->tpl->render($template, $all_values);
        return $output;
    }

    public function display_header($values) {
        global $LoggedUser;

        //if ($this->master->auth->authenticated) {
            $values['hv'] = $this->get_header_vars();
            list($values['seeding'], $values['leeching']) = array_values(user_peers($this->auth->get_active_user()->ID));
            if (check_perms('users_mod') || $LoggedUser['SupportFor'] !="" || $LoggedUser['DisplayStaff'] == 1 ) {
                $values['userinfo_tools'] = $this->get_allowed_userinfo_tools();
            }
            $values['userinfo_invites'] = $this->auth->isAllowed('site_can_invite_always');
            $values['userinfo_unlimited_invites'] = $this->auth->isAllowed('site_send_unlimited_invites');
            $values['invites'] = $LoggedUser['Invites'];
            $values['freeleech_html'] = $this->get_freeleech_html();
            $values['donation_drive'] = $this->get_donation_drive();

        //}
        $values['scripts'] = $this->get_scripts($values['bscripts']);
        $values['headeronly'] = true;
        $values['flashes'] = $this->flasher->grabFlashes();
        $values['flashStyleClasses'] = $this->flashStyleClasses;
        $this->display('base.html', $values);
    }

    public function display_footer() {
        $values = [];
        $values['footeronly'] = true;
        if (check_perms('users_mod')) {
            $values['performance_info'] = $this->get_performance_info();
        }
        if ($this->settings->site->debug || check_perms('site_debug')) {
            $values['debug_html'] = $this->get_debug_html();
        }
        $this->display('base.html', $values);
    }

    public function public_file_mtime($path) {
        $full_path = $this->master->public_path . '/' . $path;
        return filemtime($full_path);
    }

    public function get_allowed_userinfo_tools() {
        $entries = [];
        foreach ($this->userinfo_tools_entries as $e) {
            list($privilege, $target, $title) = $e;
            if (check_perms($privilege)) {
                $entry = new \stdClass();
                $entry->target = $target;
                $entry->title = $title;
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    public function get_scripts($extra_scripts = []) {
        $scripts = [];
        $scripts[] = [ 'path'=>'functions/sizzle.js' ];
        $scripts[] = [ 'path'=>'functions/script_start.js' ];
        $scripts[] = [ 'path'=>'functions/class_ajax.js' ];
        $scripts[] = [ 'path'=>'functions/svg-injector.min.js' ];
        if (!$this->master->auth->authenticated) {
            $scripts[] = [ 'path'=>'functions/class_cookie.js' ];
            $scripts[] = [ 'path'=>'functions/class_storage.js' ];
        }
        $scripts[] = [ 'path'=>'functions/global.js' ];

        foreach ($extra_scripts as $extra_script) {
            $script = [];
            $script['path'] = "functions/{$extra_script}.js";
            if ($extra_script == 'jquery') {
                $script['append'] = "<script type=\"text/javascript\">\n$.noConflict();\n</script>";
            } elseif ($extra_script == 'charts') {
                $script['append'] = '<script type="text/javascript" src="https://www.google.com/jsapi"></script>';
            }
            $scripts[] = $script;
        }
        foreach ($scripts as &$script) {
            $script['src'] = $this->settings->main->static_server . $script['path'] . '?v=' . $this->public_file_mtime('static/'.$script['path']);
        }
        return $scripts;
    }

    public function get_notifications() {
        global $LoggedUser;

        $user = $this->request->user;
        if (isset($LoggedUser['Permissions']['site_torrents_notify'])) {
            $Notifications = $this->cache->get_value('notify_filters_' . $user->ID);
            if (!is_array($Notifications)) {
                $Notifications = $this->db->raw_query("SELECT ID, ID, Label FROM users_notify_filters WHERE UserID=:userid",
                                                        [':userid' => $user->ID])->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
                $this->cache->cache_value('notify_filters_' . $user->ID, $Notifications, 2592000);
            }
        } else {
            $Notifications = null;
        }
        return $Notifications;
    }


    public function get_header_vars() {
        # This used to live in privateheader.php
        global $LoggedUser;
        $user = $this->request->user;

        $hv = new \stdClass(); # Cheap & easy way to bundle it all

        $hv->NewSubscriptions = $this->cache->get_value('subscriptions_user_new_'.$user->ID);
        if ($hv->NewSubscriptions === FALSE) {
            if ($LoggedUser['CustomForums']) {
                unset($LoggedUser['CustomForums']['']);
                $RestrictedForums = implode("','", array_keys($LoggedUser['CustomForums'], 0));
                $PermittedForums  = implode("','", array_keys($LoggedUser['CustomForums'], 1));
            }
            // num of params need to match query so build appropriately
            $params = [':userclass' => $LoggedUser['Class'],
                       ':userid'    => $user->ID];
            if (!empty($PermittedForums)) $params[':permittedforums'] = $PermittedForums;
            if (!empty($RestrictedForums)) $params[':restrictedforums'] = $RestrictedForums;

            $hv->NewSubscriptions = $this->db->raw_query("SELECT COUNT(s.TopicID)
                        FROM users_subscriptions AS s
                                JOIN forums_last_read_topics AS l ON s.UserID = l.UserID AND s.TopicID = l.TopicID
                                JOIN forums_topics AS t ON l.TopicID = t.ID
                                JOIN forums AS f ON t.ForumID = f.ID
                        WHERE (f.MinClassRead <= :userclass".
                        (!empty($PermittedForums) ? " OR f.ID IN ( :permittedforums ))" : ")")."
                                AND l.PostID < t.LastPostID
                                AND s.UserID = :userid".
                        (!empty($RestrictedForums) ? " AND f.ID NOT IN ( :restrictedforums )" : ""), $params)->fetchColumn();

            $this->cache->cache_value('subscriptions_user_new_'.$user->ID, $hv->NewSubscriptions, 0);
        }

        // Moved alert bar handling to before we draw minor stats to allow showing alert status in links too

        //Start handling alert bars
        $hv->Infos = array(); // an info alert bar (nicer color)
        $hv->Alerts = array(); // warning bar (red!)
        $hv->ModBar = array();

        // News
        $MyNews = $LoggedUser['LastReadNews']+0;
        $CurrentNews = $this->cache->get_value('news_latest_id');
        if ($CurrentNews === false) {
            $CurrentNews = $this->db->raw_query("SELECT ID FROM news ORDER BY Time DESC LIMIT 1")->fetchColumn();
            $this->cache->cache_value('news_latest_id', $CurrentNews, 0);
        }

        if ($MyNews < $CurrentNews) {
            $hv->Alerts[] = '<a href="index.php">New Announcement!</a>';
        }

        //Staff PMs for users
        $hv->NewStaffPMs = $this->cache->get_value('staff_pm_new_'.$user->ID);
        if ($hv->NewStaffPMs === false) {
            $hv->NewStaffPMs = $this->db->raw_query("SELECT COUNT(ID) FROM staff_pm_conversations WHERE UserID=:userid AND UnRead = '1'", [':userid' => $user->ID])->fetchColumn();
            $this->cache->cache_value('staff_pm_new_'.$user->ID, $hv->NewStaffPMs, 0);
        }

        if ($hv->NewStaffPMs > 0) {
            $hv->Alerts[] = '<a href="staffpm.php?action=user_inbox">You have '.$hv->NewStaffPMs.(($hv->NewStaffPMs > 1) ? ' new staff messages' : ' new staff message').'</a>';
        }

        //Inbox
        $hv->NewMessages = $this->cache->get_value('inbox_new_'.$user->ID);
        if ($hv->NewMessages === false) {
            $hv->NewMessages = $this->db->raw_query("SELECT COUNT(UnRead) FROM pm_conversations_users WHERE UserID=:userid AND UnRead = '1' AND InInbox = '1'", [':userid' => $user->ID])->fetchColumn();
            $this->cache->cache_value('inbox_new_'.$user->ID, $hv->NewMessages, 0);
        }

        if ($hv->NewMessages > 0) {
            $hv->Alerts[] = '<a href="inbox.php">You have '.$hv->NewMessages.(($hv->NewMessages > 1) ? ' new messages' : ' new message').'</a>';
        }

        if ($LoggedUser['RatioWatch']) {
            if ($LoggedUser['CanLeech'] == 1) {
                $hv->Alerts[] = '<a href="articles.php?topic=ratio">'.'Ratio Watch'.'</a>: '.'You have '.time_diff($LoggedUser['RatioWatchEnds'],3,true,false,0).' to get your ratio over your required ratio or your leeching abilities will be disabled.';
            } else {
                $hv->Alerts[] = '<a href="articles.php?topic=ratio">'.'Ratio Watch'.'</a>: '.'Your downloading privileges are disabled until you meet your required ratio.';
            }
        }

        if (check_perms('site_torrents_notify')) {
            $hv->NewNotifications = $this->cache->get_value('notifications_new_'.$user->ID);
            if ($hv->NewNotifications === false) {
                $hv->NewNotifications = $this->db->raw_query("SELECT COUNT(UserID) FROM users_notify_torrents WHERE UserID=:userid AND UnRead = '1'", [':userid' => $user->ID])->fetchColumn();
                $this->cache->cache_value('notifications_new_'.$user->ID, $hv->NewNotifications, 0);
            }
            if ($hv->NewNotifications > 0) {
                $hv->Alerts[] = '<a href="torrents.php?action=notify">'.'You have '.$hv->NewNotifications.' new torrent notification'.(($hv->NewNotifications > 1) ? 's' : '').'</a>';
            }
        }

        // Collage subscriptions
        if (check_perms('site_collages_subscribe')) {
            $hv->NewCollages = $this->cache->get_value('collage_subs_user_new_'.$user->ID);
            if ($hv->NewCollages === FALSE) {
                $hv->NewCollages = $this->db->raw_query("SELECT COUNT(DISTINCT s.CollageID)
                            FROM users_collage_subs as s
                            JOIN collages as c ON s.CollageID = c.ID
                            JOIN collages_torrents as ct on ct.CollageID = c.ID
                            WHERE s.UserID =:userid AND ct.AddedOn > s.LastVisit AND c.Deleted = '0'", [':userid' => $user->ID])->fetchColumn();
                $this->cache->cache_value('collage_subs_user_new_'.$user->ID, $hv->NewCollages, 0);
            }
            if ($hv->NewCollages > 0) {
                $hv->Alerts[] = '<a href="userhistory.php?action=subscribed_collages">You have '.$hv->NewCollages.' new collage update'.(($hv->NewCollages > 1) ? 's' : '').'</a>';
            }
        }

        if (check_perms('users_mod')) {
            $hv->ModBar[] = '<a href="tools.php">Toolbox</a>';
        }
        // check allows FLS as well as staff to see PM's
        if ($LoggedUser['SupportFor'] !="" || $LoggedUser['DisplayStaff'] == 1) {

            $hv->NumUnansweredStaffPMs = $this->db->raw_query("SELECT COUNT(ID) FROM staff_pm_conversations
                         WHERE (AssignedToUser=:userid OR Level <=:userclass)
                           AND Status IN ('Unanswered', 'User Resolved')
                           AND NOT StealthResolved",
                           [':userid'    => $user->ID,
                            ':userclass' => $LoggedUser['Class']])->fetchColumn();

            $hv->NumOpenStaffPMs = $this->db->raw_query("SELECT COUNT(ID) FROM staff_pm_conversations
                         WHERE (AssignedToUser=:userid OR Level<=:userclass)
                           AND Status = 'Open'
                           AND NOT StealthResolved",
                           [':userid'    => $user->ID,
                            ':userclass' => $LoggedUser['Class']])->fetchColumn();

            $hv->NumOpenStaffPMs += $hv->NumUnansweredStaffPMs;

            // if ($hv->NumUnansweredStaffPMs > 0 || $hv->NumOpenStaffPMs >0) - removing this test to make it consistent with other parts of staff header -- mifune
            $hv->ModBar[] = '<a href="staffpm.php?view=unanswered">('.$hv->NumUnansweredStaffPMs.')</a><a href="staffpm.php?view=open">('.$hv->NumOpenStaffPMs.') Staff PMs</a>';
        }

        if (check_perms('admin_reports')) {
            $hv->NumTorrentReports = $this->cache->get_value('num_torrent_reportsv2');
            if ($hv->NumTorrentReports === false) {
                $hv->NumTorrentReports = $this->db->raw_query("SELECT COUNT(ID) FROM reportsv2 WHERE Status='New'")->fetchColumn();
                $this->cache->cache_value('num_torrent_reportsv2', $hv->NumTorrentReports, 0);
            }

            $hv->ModBar[] = '<a href="reportsv2.php">'.$hv->NumTorrentReports.(($hv->NumTorrentReports == 1) ? ' Report' : ' Reports').'</a>';
        }

        if (check_perms('admin_reports')) {
            $hv->NumOtherReports = $this->cache->get_value('num_other_reports');
            if ($hv->NumOtherReports === false) {
                $hv->NumOtherReports = $this->db->raw_query("SELECT COUNT(ID) FROM reports WHERE Status='New'")->fetchColumn();
                $this->cache->cache_value('num_other_reports', $hv->NumOtherReports, 0);
            }

            $hv->ModBar[] = '<a href="reports.php">'.$hv->NumOtherReports.(($hv->NumTorrentReports == 1) ? ' Other Report' : ' Other Reports').'</a>';

        } elseif (check_perms('site_project_team')) {
            $hv->NumUpdateReports = $this->cache->get_value('num_update_reports');
            if ($hv->NumUpdateReports === false) {
                $hv->NumUpdateReports = $this->db->raw_query("SELECT COUNT(ID) FROM reports WHERE Status='New' AND Type = 'request_update'")->fetchColumn();
                $this->cache->cache_value('num_update_reports', $hv->NumUpdateReports, 0);
            }

            if ($hv->NumUpdateReports > 0) {
                $hv->ModBar[] = '<a href="reports.php">'.'Request update reports'.'</a>';
            }
        } elseif (check_perms('site_moderate_forums')) {
            $hv->NumForumReports = $this->cache->get_value('num_forum_reports');
            if ($hv->NumForumReports === false) {
                $hv->NumForumReports = $this->db->raw_query("SELECT COUNT(ID) FROM reports WHERE Status='New' AND Type IN('collages_comment', 'Post', 'requests_comment', 'thread', 'torrents_comment')")->fetchColumn();
                $this->cache->cache_value('num_forum_reports', $hv->NumForumReports, 0);
            }

            if ($hv->NumForumReports > 0) {
                $hv->ModBar[] = '<a href="reports.php">'.$hv->NumForumReports.' Forum report'.(($hv->NumForumReports > 1) ? 's' : '').'</a>';
            }
        }
        return $hv;
    }

    public function get_freeleech_html() {
        global $Sitewide_Freeleech_On, $Sitewide_Freeleech, $LoggedUser;

        $PFL = null;
        if ($Sitewide_Freeleech_On) {

            $TimeNow = date('M d Y, H:i', strtotime($Sitewide_Freeleech) - (int) $LoggedUser['TimeOffset']);
            $PFL = '<span class="time" title="Sitewide Freeleech for '. time_diff($Sitewide_Freeleech,2,false,false,0).' (until '.$TimeNow.')">Sitewide Freeleech for '.time_diff($Sitewide_Freeleech,2,false,false,0).'</span>';

        } else {

            $TimeStampNow = time();
            $PFLTimeStamp = strtotime($LoggedUser['personal_freeleech']);

            if ($PFLTimeStamp >= $TimeStampNow) {

                if (($PFLTimeStamp - $TimeStampNow) < (28*24*3600)) { // more than 28 days freeleech and the time is only specififed in the tooltip
                    $TimeAgo = time_diff($LoggedUser['personal_freeleech'],2,false,false,0);
                    $PFL = "PFL for $TimeAgo";
                } else {
                    $PFL = "Personal Freeleech";
                }
                $TimeNow = date('M d Y, H:i', $PFLTimeStamp - (int) $LoggedUser['TimeOffset']);
                $PFL = '<span class="time" title="Personal Freeleech until '.$TimeNow.'">'.$PFL.'</span>';
            }

        }
        return $PFL;
    }

    public function get_donation_drive() {
        $ActiveDrive = $this->cache->get_value('active_drive');
        if ($ActiveDrive===false) {
            $ActiveDrive = $this->db->raw_query("SELECT ID, name, start_time, target_euros, threadid
                                                 FROM donation_drives WHERE state='active' ORDER BY start_time DESC LIMIT 1")->fetch(\PDO::FETCH_NUM);
            if (!$ActiveDrive) $ActiveDrive = array('false');
            $this->cache->cache_value('active_drive' , $ActiveDrive, 0);
        }

        if (isset($ActiveDrive[1])) {
            list($ID, $name, $start_time, $target_euros, $threadid) = $ActiveDrive;
            list($raised_euros, $count) = $this->db->raw_query("SELECT SUM(amount_euro), Count(ID)
                                                                FROM bitcoin_donations WHERE state!='unused' AND received > :starttime", [':starttime' => $start_time])->fetch(\PDO::FETCH_NUM);
            $percentdone = (int) ($raised_euros * 100 / $target_euros);
            if ($percentdone>100) $percentdone=100;
            $donation_drive = new \stdClass();
            $donation_drive->name = $name;
            $donation_drive->threadid = $threadid;
            $donation_drive->raised_euros = $raised_euros;
            $donation_drive->target_euros = $target_euros;
            $donation_drive->percentdone = $percentdone;
        } else {
            $donation_drive = false;
        }
        return $donation_drive;
    }

    protected function get_performance_info() {
        global $ScriptStartTime;
        $Load = sys_getloadavg();
        $info = new \stdClass();
        $info->time = number_format(((microtime(true)-$ScriptStartTime)*1000),5);
        $info->memory = get_size(memory_get_usage(true));
        $info->load = number_format($Load[0],2).' '.number_format($Load[1],2).' '.number_format($Load[2],2);
        $info->date = time_diff(time(),2,false,false,1);
        return $info;
    }

    protected function get_debug_html() {
        global $Debug;
        ob_start();
        $Debug->flag_table();
        $Debug->error_table();
        $Debug->sphinx_table();
        $Debug->query_table();
        $Debug->cache_table();
        $Debug->vars_table();
        $debug_html = ob_get_clean();
        return $debug_html;
    }

}
