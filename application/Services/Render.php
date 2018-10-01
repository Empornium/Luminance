<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Errors\InternalError;

use Luminance\Entities\User;

# The render class is a useful little minion that helps tie interface/template logic together
class Render extends Service {

    public $userinfo_tools_entries = [
        #  permission                   action                                  title
        [ 'admin_edit_articles',       'tools.php?action=articles',            'Articles'                ],
        [ 'admin_manage_awards',       'tools.php?action=awards_auto',         'Automatic Awards'        ],
        [ 'admin_manage_badges',       'tools.php?action=badges_list',         'Badges'                  ],
        [ 'admin_manage_shop',         'tools.php?action=shop_list',           'Bonus Shop'              ],
        [ 'admin_manage_events',       'tools.php?action=events_list',         'Upload Events'           ],
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
        [ 'admin_manage_tags',         'tools.php?action=official_tags',       'Official Tags'           ],
        [ 'admin_convert_tags',        'tools.php?action=official_synonyms',   'Official Synonyms'       ],
        [ 'users_fls',                 'torrents.php?action=allcomments',      'Recent Torrent Comments' ],
        [ 'users_fls',                 'requests.php?action=allcomments',      'Recent Request Comments' ],
        [ 'users_fls',                 'collages.php?action=allcomments',      'Recent Collage Comments' ],
        [ 'users_fls',                 'forums.php?action=allposts',           'Recent Forum Posts'      ],
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
        'permissions' => 'PermissionRepository',
        'users'       => 'UserRepository',
    ];

    protected static $useServices = [
        'auth'      => 'Auth',
        'debug'     => 'Debug',
        'flasher'   => 'Flasher',
        'secretary' => 'Secretary',
        'settings'  => 'Settings',
        'options'   => 'Options',
        'tpl'       => 'TPL',
        'cache'     => 'Cache',
        'db'        => 'DB',
    ];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $master->request;
    }

    protected function get_base_variables() {
        global $Document;

        $user = $this->request->user;
        $base_variables = [];
        $base_variables['master']             = $this->master;
        $base_variables['auth']               = $this->auth;
        $base_variables['flashes']            = $this->flasher->grabFlashes();
        $base_variables['flashStyleClasses']  = $this->flashStyleClasses;
        $base_variables['request']            = $this->request;
        $base_variables['secretary']          = $this->secretary;
        $base_variables['settings']           = $this->settings;
        $base_variables['options']            = $this->options;
        $base_variables['rss_auth_string']    = $this->getRSSAuthString();
        $base_variables['render']             = $this;
        $base_variables['static_uri']         = $this->settings->main->static_server;
        $base_variables['main_uri']           = '';
        $base_variables['authenticated']      = isset($this->request->user);
        $base_variables['allow_registration'] = ($this->settings->site->open_registration) && ($this->permissions->getMinUserClassID() > 0);
        $base_variables['ActiveUser']         = $user;
        $base_variables['site_name']          = $this->settings->main->site_name;
        $base_variables['site_url']           = $this->settings->main->site_url;
        $base_variables['Notifications']      = $this->get_notifications();
        if ($user) {
            $user->perm = $this->permissions->load($user->legacy['PermissionID']);

            $base_variables['UserStats']   = $this->auth->get_user_stats($user->ID);
            $base_variables['Stylesheet']  = $this->stylesheets->get_by_user($user);
            $base_variables['Permissions'] = $this->auth->get_user_permissions($user);

            $tplPath = $this->master->base_path . "/public/static/styles/{$this->stylesheets->get_by_user($user)->Name}/templates";
            if (is_dir($tplPath)) {
                $this->tpl->override_template_path($tplPath);
            }
        }
        $base_variables['Document']   = $Document;
        $base_variables['headeronly'] = false;
        $base_variables['footeronly'] = false;

        # Legacy stuff
        $base_variables['bbcode']     = new \Luminance\Legacy\Text;

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

    public function sri_checksum($input) {
        $hash = hash('sha256', $input, true);
        $hash_base64 = base64_encode($hash);
        return "sha256-$hash_base64";
    }

    public function display_header($values = []) {
        $values = $this->get_header_vars($values);
        $values['headeronly'] = true;
        $this->display('core/base.html.twig', $values);
    }

    public function display_page($template, $values, $block = null) {
        $values = array_merge($this->get_base_variables(), $values);
        if (!isset($values['bscripts'])) {
            $values['bscripts'] = '';
        }
        $values = $this->get_header_vars($values);
        $values = $this->get_footer_vars($values);
        $this->tpl->display($template, $values, $block);
    }

    public function display_footer() {
        $values = $this->get_footer_vars();
        $values['footeronly'] = true;
        $this->display('core/base.html.twig', $values);
    }

    public function public_file_mtime($path) {
        $full_path = $this->master->public_path . '/' . $path;
        return filemtime($full_path);
    }

    public function public_file_exists($path) {
        $full_path = $this->master->public_path . '/' . $path;
        return file_exists($full_path);
    }

    public function get_allowed_userinfo_tools() {
        $entries = [];
        foreach ($this->userinfo_tools_entries as $e) {
            list($privilege, $target, $title) = $e;
            if ($this->auth->isAllowed($privilege)) {
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
        if (!isset($this->request->user)) {
            $scripts[] = [ 'path'=>'functions/class_cookie.js' ];
            $scripts[] = [ 'path'=>'functions/class_storage.js' ];
        }

        //$extra_scripts[] = 'jquery';       // dependency
        //$extra_scripts[] = 'srchbr';       // handles funky search bars
        $extra_scripts[] = 'svg-injector'; // handles icon injections
        $extra_scripts[] = 'prism';        // handles code syntax highlighting

        foreach ($extra_scripts as $extra_script) {
            $script = [];

            // Load minified scripts if they exist when not in debug mode
            if (!$this->settings->site->debug_mode && $this->public_file_exists("static/functions/{$extra_script}.min.js")) {
                $script['path'] = "functions/{$extra_script}.min.js";
            } else {
                $script['path'] = "functions/{$extra_script}.js";
            }

            if ($extra_script == 'jquery') {
                $script['append'] = "<script type=\"text/javascript\">\n$.noConflict();\n</script>";
            } elseif ($extra_script == 'charts') {
                $script['append'] = '<script type="text/javascript" src="https://www.google.com/jsapi"></script>';
            }

            // Add script to array
            $scripts[] = $script;

            // Load jQuery migrate *AFTER* jQuery
            if ($extra_script == 'jquery') {
                if (!$this->settings->site->debug_mode && $this->public_file_exists("static/functions/jquery.migrate.min.js")) {
                    $scripts[]['path'] = "functions/jquery.migrate.min.js";
                } else {
                    $scripts[]['path'] = "functions/jquery.migrate.js";
                }
            }
        }

        // This should be last loaded!
        $scripts[] = [ 'path'=>'functions/global.js' ];

        foreach ($scripts as &$script) {
            $script['src'] = $this->settings->main->static_server . $script['path'] . '?v=' . $this->public_file_mtime('static/'.$script['path']);
            $script['sri'] = $this->sri_checksum(file_get_contents($this->master->public_path.'/static/'.$script['path']));
        }

        return $scripts;
    }

    public function get_notifications() {
        $user = $this->request->user;
        if (!$user) {
            return null;
        }
        if (isset($user->legacy['Permissions']['site_torrents_notify'])) {
            $Notifications = $this->cache->get_value('notify_filters_' . $user->ID);
            if (!is_array($Notifications)) {
                $Notifications = $this->db->raw_query(
                    "SELECT ID, ID, Label FROM users_notify_filters WHERE UserID=:userid",
                    [':userid' => $user->ID]
                )->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
                $this->cache->cache_value('notify_filters_' . $user->ID, $Notifications, 2592000);
            }
        } else {
            $Notifications = null;
        }
        return $Notifications;
    }


    public function get_header_vars($values = []) {
        if ($this->request->user) {
            // Set HTTP headers
            $this->request->setHttpHeaders();

            $user = $this->request->user;
            $hv = new \stdClass(); # Cheap & easy way to bundle it all

            $hv->NewSubscriptions = $this->cache->get_value('subscriptions_user_new_'.$user->ID);
            if ($hv->NewSubscriptions === false) {
                $RestrictedForums = (array)explode(',', $user->legacy['RestrictedForums']);
                $PermittedForums  = (array)explode(',', $user->legacy['PermittedForums']);

                // num of params need to match query so build appropriately
                $params = [':userclass' => $user->legacy['Class'],
                          ':userid'    => $user->ID];

                if (is_array($PermittedForums)) $permittedvars = $this->db->bindParamArray('pfid', $PermittedForums, $params);
                if (is_array($RestrictedForums)) $restrictedvars = $this->db->bindParamArray('rfid', $RestrictedForums, $params);

                $hv->NewSubscriptions = $this->db->raw_query("SELECT COUNT(s.TopicID)
                           FROM users_subscriptions AS s
                                   JOIN forums_last_read_topics AS l ON s.UserID = l.UserID AND s.TopicID = l.TopicID
                                   JOIN forums_topics AS t ON l.TopicID = t.ID
                                   JOIN forums AS f ON t.ForumID = f.ID
                           WHERE (f.MinClassRead <= :userclass".
                           (!empty($permittedvars) ? " OR f.ID IN ( $permittedvars ))" : ")")."
                                   AND l.PostID < t.LastPostID
                                   AND s.UserID = :userid".
                           (!empty($restrictedvars) ? " AND f.ID NOT IN ( $restrictedvars )" : ""), $params)->fetchColumn();

                $this->cache->cache_value('subscriptions_user_new_'.$user->ID, $hv->NewSubscriptions, 0);
            }

           // Moved alert bar handling to before we draw minor stats to allow showing alert status in links too

           //Start handling alert bars
            $hv->Infos  = []; // an info alert bar (nicer color)
            $hv->Alerts = []; // warning bar (red!)
            $hv->ModBar = [];
            $hv->Urgent = [];

           // News
            if (empty($user->legacy['NoNewsAlerts'])) {
                $CurrentNews = $this->cache->get_value('news_latest_id');
                if ($CurrentNews === false) {
                    $CurrentNews = $this->db->raw_query("SELECT ID FROM news ORDER BY Time DESC LIMIT 1")->fetchColumn();
                    $this->cache->cache_value('news_latest_id', $CurrentNews, 0);
                }

                if ((int)$user->legacy['LastReadNews'] < $CurrentNews) {
                    $hv->Alerts[] = '<a href="/index.php">New Announcement!</a>';
                }
            }

           // Blogs
            if (empty($user->legacy['NoBlogAlerts'])) {
                $CurrentBlogs = $this->cache->get_value('blog_latest_id');
                if ($CurrentBlogs === false) {
                    $CurrentBlogs = $this->db->raw_query("SELECT ID FROM blog WHERE Section='Blog' ORDER BY Time DESC LIMIT 1")->fetchColumn();
                    $this->cache->cache_value('blog_latest_id', $CurrentBlogs, 0);
                }

                if ((int)$user->legacy['LastReadBlog'] < $CurrentBlogs) {
                    $hv->Alerts[] = '<a href="/blog.php">New Site Blog!</a>';
                }
            }

           // Contests
            if (empty($user->legacy['NoContestAlerts'])) {
                $CurrentContests = $this->cache->get_value('contests_latest_id');
                if ($CurrentContests === false) {
                    $CurrentContests = $this->db->raw_query("SELECT ID FROM blog WHERE Section='Contests' ORDER BY Time DESC LIMIT 1")->fetchColumn();
                    $this->cache->cache_value('contests_latest_id', $CurrentContests, 0);
                }

                if ((int)$user->legacy['LastReadContests'] < $CurrentContests) {
                    $hv->Alerts[] = '<a href="/contests.php">New Contest!</a>';
                }
            }

           // Staff PMs for users
            $hv->NewStaffPMs = $this->cache->get_value('staff_pm_new_'.$user->ID);
            if ($hv->NewStaffPMs === false) {
                $hv->NewStaffPMs = $this->db->raw_query("SELECT COUNT(ID) FROM staff_pm_conversations WHERE UserID=:userid AND UnRead = '1'", [':userid' => $user->ID])->fetchColumn();
                $this->cache->cache_value('staff_pm_new_'.$user->ID, $hv->NewStaffPMs, 0);
            }

            if ($hv->NewStaffPMs > 0) {
                $hv->Alerts[] = '<a href="/staffpm.php?action=user_inbox">You have '.$hv->NewStaffPMs.' new staff message'.(($hv->NewStaffPMs > 1) ? 's' : '').'</a>';
            }

           // Urgent Staff PMs for users
            $hv->UrgentStaffPMs = $this->cache->get_value('staff_pm_urgent_'.$user->ID);
            if ($hv->UrgentStaffPMs === false) {
                $urgentPMs = $this->db->raw_query("SELECT Urgent, Count(ID) AS NumPMs
                                                    FROM staff_pm_conversations
                                                   WHERE UserID=:userid
                                                     AND ((UnRead = '1' AND Urgent = 'Read')
                                                         OR (Status != 'Unanswered' AND Urgent = 'Respond'))
                                                GROUP BY Urgent", [':userid' => $user->ID])->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE);

                // Don't generate unkown index errors
                $hv->UrgentStaffPMs = [ 'Read' => @(int)$urgentPMs['Read']['NumPMs'] , 'Respond' => @(int)$urgentPMs['Respond']['NumPMs']];
                $this->cache->cache_value('staff_pm_urgent_'.$user->ID, $hv->UrgentStaffPMs, 0);
            }

            if (is_array($hv->UrgentStaffPMs)) {
                $numUrgentPMs = array_sum($hv->UrgentStaffPMs);
                if ($numUrgentPMs > 0) {
                    $hv->Urgent[] = '<a href="/staffpm.php?action=user_inbox">You have '.$numUrgentPMs.' urgent staff message'.($numUrgentPMs > 1 ? 's' : '').'</a>';
                    if ($numUrgentPMs == $hv->UrgentStaffPMs['Read']) {
                        $hv->Urgent[] = 'Please read '.($numUrgentPMs > 1 ? 'these' : 'this').' message'.($numUrgentPMs > 1 ? 's' : '').' immediately.';
                        $hv->Urgent[] = 'If you do not read '.($numUrgentPMs > 1 ? 'these' : 'this').' you may be restricted or banned.';
                    } elseif ($numUrgentPMs == $hv->UrgentStaffPMs['Respond']) {
                        $hv->Urgent[] = 'Please respond to '.($numUrgentPMs > 1 ? 'these' : 'this').' message'.($numUrgentPMs > 1 ? 's' : '').' immediately.';
                        $hv->Urgent[] = 'If you do not respond to '.($numUrgentPMs > 1 ? 'these' : 'this').' you may be restricted or banned.';
                    } else {
                        $hv->Urgent[] = 'You have '.$hv->UrgentStaffPMs['Read'].' message'.($hv->UrgentStaffPMs['Read'] > 1 ? 's' : '').' to read immediately.';
                        $hv->Urgent[] = 'You have '.$hv->UrgentStaffPMs['Respond'].' message'.($hv->UrgentStaffPMs['Respond'] > 1 ? 's' : '').' to respond to immediately.';
                        $hv->Urgent[] = 'If you do not read and respond to these messages you may be restricted or banned.';
                    }
                    $hv->Urgent[] = '<a class="btn" href="/staffpm.php?action=user_inbox">View</a>';
                }
            }

           //Inbox
            $hv->NewMessages = $this->cache->get_value('inbox_new_'.$user->ID);
            if ($hv->NewMessages === false) {
                $hv->NewMessages = $this->db->raw_query("SELECT COUNT(UnRead) FROM pm_conversations_users WHERE UserID=:userid AND UnRead = '1' AND InInbox = '1'", [':userid' => $user->ID])->fetchColumn();
                $this->cache->cache_value('inbox_new_'.$user->ID, $hv->NewMessages, 0);
            }

            if ($hv->NewMessages > 0) {
                $hv->Alerts[] = '<a href="/inbox.php">You have '.$hv->NewMessages.(($hv->NewMessages > 1) ? ' new messages' : ' new message').'</a>';
            }

            if ($user->on_ratiowatch()) {
                if ($user->legacy['can_leech'] == 1) {
                    $hv->Alerts[] = '<a href="/articles.php?topic=ratio">'.'Ratio Watch'.'</a>: '.'You have '.time_diff($user->legacy['RatioWatchEnds'], 3, true, false, 0).' to get your ratio over your required ratio or your leeching abilities will be disabled.';
                } else {
                    $hv->Alerts[] = '<a href="/articles.php?topic=ratio">'.'Ratio Watch'.'</a>: '.'Your downloading privileges are disabled until you meet your required ratio.';
                }
            }

            if ($this->auth->isAllowed('site_torrents_notify')) {
                $hv->NewNotifications = $this->cache->get_value('notifications_new_'.$user->ID);
                if ($hv->NewNotifications === false) {
                    $hv->NewNotifications = $this->db->raw_query("SELECT COUNT(UserID) FROM users_notify_torrents WHERE UserID=:userid AND UnRead = '1'", [':userid' => $user->ID])->fetchColumn();
                    $this->cache->cache_value('notifications_new_'.$user->ID, $hv->NewNotifications, 0);
                }
                if ($hv->NewNotifications > 0) {
                    $hv->Alerts[] = '<a href="/torrents.php?action=notify">'.'You have '.$hv->NewNotifications.' new torrent notification'.(($hv->NewNotifications > 1) ? 's' : '').'</a>';
                }
            }

           // Collage subscriptions
            if ($this->auth->isAllowed('site_collages_subscribe')) {
                $hv->NewCollages = $this->cache->get_value('collage_subs_user_new_'.$user->ID);
                if ($hv->NewCollages === false) {
                    $hv->NewCollages = $this->db->raw_query("SELECT COUNT(DISTINCT s.CollageID)
                               FROM users_collage_subs as s
                               JOIN collages as c ON s.CollageID = c.ID
                               JOIN collages_torrents as ct on ct.CollageID = c.ID
                               WHERE s.UserID =:userid AND ct.AddedOn > s.LastVisit AND c.Deleted = '0'", [':userid' => $user->ID])->fetchColumn();
                    $this->cache->cache_value('collage_subs_user_new_'.$user->ID, $hv->NewCollages, 0);
                }
                if ($hv->NewCollages > 0) {
                    $hv->Alerts[] = '<a href="/userhistory.php?action=subscribed_collages">You have '.$hv->NewCollages.' new collage update'.(($hv->NewCollages > 1) ? 's' : '').'</a>';
                }
            }

            if ($this->auth->isAllowed('users_mod')) {
                $hv->ModBar[] = '<a href="/tools.php">Toolbox</a>';
            }

           // check allows FLS as well as staff to see PM's
            if ($user->legacy['SupportFor'] !="" || $this->permissions->load($user->legacy['PermissionID'])->DisplayStaff == 1) {
                $hv->NumUnansweredStaffPMs = $this->db->raw_query(
                    "SELECT COUNT(ID) FROM staff_pm_conversations
                            WHERE (AssignedToUser=:userid OR Level <=:userclass)
                              AND Status IN ('Unanswered', 'User Resolved')
                              AND NOT StealthResolved",
                    [':userid'    => $user->ID,
                    ':userclass' => $this->permissions->load($user->legacy['PermissionID'])->Level]
                )->fetchColumn();

                $hv->NumOpenStaffPMs = $this->db->raw_query(
                    "SELECT COUNT(ID) FROM staff_pm_conversations
                            WHERE (AssignedToUser=:userid OR Level<=:userclass)
                              AND Status = 'Open'
                              AND NOT StealthResolved",
                    [':userid'    => $user->ID,
                    ':userclass' => $this->permissions->load($user->legacy['PermissionID'])->Level]
                )->fetchColumn();

                $hv->NumOpenStaffPMs += $hv->NumUnansweredStaffPMs;

                $hv->ModBar[] = '<a href="/staffpm.php?view=unanswered">('.$hv->NumUnansweredStaffPMs.')</a><a href="/staffpm.php?view=open">('.$hv->NumOpenStaffPMs.') Staff PMs</a>';
            }

            if ($this->auth->isAllowed('admin_reports')) {
                $hv->NumTorrentReports = $this->cache->get_value('num_torrent_reportsv2');
                if ($hv->NumTorrentReports === false) {
                    $hv->NumTorrentReports = $this->db->raw_query("SELECT COUNT(ID) FROM reportsv2 WHERE Status='New'")->fetchColumn();
                    $this->cache->cache_value('num_torrent_reportsv2', $hv->NumTorrentReports, 0);
                }

                $hv->ModBar[] = '<a href="/reportsv2.php">'.$hv->NumTorrentReports.' Report'.(($hv->NumTorrentReports != 1) ? 's' : '').'</a>';
            }

            if ($this->auth->isAllowed('admin_reports')) {
                $hv->NumOtherReports = $this->cache->get_value('num_other_reports');
                if ($hv->NumOtherReports === false) {
                    $hv->NumOtherReports = $this->db->raw_query("SELECT COUNT(ID) FROM reports WHERE Status='New'")->fetchColumn();
                    $this->cache->cache_value('num_other_reports', $hv->NumOtherReports, 0);
                }

                $hv->ModBar[] = '<a href="/reports.php">'.$hv->NumOtherReports.' Other Report'.(($hv->NumOtherReports != 1) ? 's' : '').'</a>';
            } elseif ($this->auth->isAllowed('site_project_team')) {
                $hv->NumUpdateReports = $this->cache->get_value('num_update_reports');
                if ($hv->NumUpdateReports === false) {
                    $hv->NumUpdateReports = $this->db->raw_query("SELECT COUNT(ID) FROM reports WHERE Status='New' AND Type = 'request_update'")->fetchColumn();
                    $this->cache->cache_value('num_update_reports', $hv->NumUpdateReports, 0);
                }

                $hv->ModBar[] = '<a href="/reports.php">'.$hv->NumUpdateReports.' Update Report'.(($hv->NumUpdateReports != 1) ? 's' : '').'</a>';
            } elseif ($this->auth->isAllowed('site_moderate_forums') || $this->auth->isAllowed('site_view_reportsv1')) {
                $hv->NumForumReports = $this->cache->get_value('num_forum_reports');
                if ($hv->NumForumReports === false) {
                    $hv->NumForumReports = $this->db->raw_query("SELECT COUNT(ID) FROM reports WHERE Status='New' AND Type IN('collages_comment', 'Post', 'requests_comment', 'thread', 'torrents_comment')")->fetchColumn();
                    $this->cache->cache_value('num_forum_reports', $hv->NumForumReports, 0);
                }

                $hv->ModBar[] = '<a href="/reports.php">'.$hv->NumForumReports.' Other Report'.(($hv->NumForumReports != 1) ? 's' : '').'</a>';
            }
            $values['hv'] = $hv;
            list($values['seeding'], $values['leeching']) = array_values(user_peers($this->auth->get_active_user()->ID));
            if ($this->auth->isAllowed('users_mod') || $user->legacy['SupportFor'] !="" || $this->permissions->load($user->legacy['PermissionID'])->DisplayStaff == 1) {
                $values['userinfo_tools'] = $this->get_allowed_userinfo_tools();
            }
            $values['userinfo_invites'] = $this->auth->isAllowed('site_can_invite_always');
            $values['userinfo_unlimited_invites'] = $this->auth->isAllowed('site_send_unlimited_invites');
            $values['invites'] = $user->legacy['Invites'];
            $values['freeleech_html'] = $this->get_freeleech_html();
            $values['doubleseed_html'] = $this->get_doubleseed_html();
            $values['donation_drive'] = $this->get_donation_drive();
        }
        if (!isset($values['bscripts'])) $values['bscripts'] = [];
        $values['scripts'] = $this->get_scripts($values['bscripts']);
        $values['flashes'] = $this->flasher->grabFlashes();
        $values['flashStyleClasses'] = $this->flashStyleClasses;

        return $values;
    }

    public function get_footer_vars($values = []) {
        if ($this->auth->isAllowed('users_mod')) {
            $values['performance_info'] = $this->get_performance_info();
        }
        if ($this->settings->site->debug_mode || $this->auth->isAllowed('site_debug')) {
            $values['debug_html'] = $this->get_debug_html();
        }

        return $values;
    }

    public function get_freeleech_html() {
        global $Sitewide_Freeleech_On, $Sitewide_Freeleech;

        $user = $this->request->user;

        $PFL = null;
        if (($this->options->SitewideFreeleechStartTime < strtotime(sqltime())) &&
            ($this->options->SitewideFreeleechEndTime > strtotime(sqltime())) &&
            ($this->options->SitewideFreeleechMode == 'timed')) {
            $Sitewide_Freeleech_On = true;
            $Sitewide_Freeleech    = $this->options->SitewideFreeleechEndTime;
            $TimeNow = date('M d Y, H:i', $this->options->SitewideFreeleechEndTime - (int) $user->heavy_info()['TimeOffset']);
            $PFL = '<span class="time" title="Sitewide Freeleech for '. time_diff($this->options->SitewideFreeleechEndTime, 2, false, false, 0).' (until '.$TimeNow.')">Sitewide Freeleech for '.time_diff($this->options->SitewideFreeleechEndTime, 2, false, false, 0).'</span>';
        } elseif ($this->options->SitewideFreeleechMode == 'perma') {
            $Sitewide_Freeleech_On = true;
           //$PFL = '<span class="time" title="Sitewide Freeleech for '. time_diff($this->options->SitewideFreeleechEndTime,2,false,false,0).' (until '.$TimeNow.')">Sitewide Freeleech for '.time_diff($this->options->SitewideFreeleechEndTime,2,false,false,0).'</span>';
        } else {
            $TimeStampNow = time();
            $PFLTimeStamp = strtotime($user->legacy['personal_freeleech']);

            if ($PFLTimeStamp >= $TimeStampNow) {
                if (($PFLTimeStamp - $TimeStampNow) < (28*24*3600)) { // more than 28 days freeleech and the time is only specififed in the tooltip
                    $TimeAgo = time_diff($user->legacy['personal_freeleech'], 2, false, false, 0);
                    $PFL = "PFL for $TimeAgo";
                } else {
                    $PFL = "Personal Freeleech";
                }
                $TimeNow = date('M d Y, H:i', $PFLTimeStamp - (int) $user->heavy_info()['TimeOffset']);
                $PFL = '<span class="time" title="Personal Freeleech until '.$TimeNow.'">'.$PFL.'</span>';
            }
        }
        return $PFL;
    }

    public function get_doubleseed_html() {
        global $Sitewide_Doubleseed_On, $Sitewide_Doubleseed;

        $user = $this->request->user;

        $PDS = null;
        if (($this->options->SitewideDoubleseedStartTime < strtotime(sqltime())) &&
            ($this->options->SitewideDoubleseedEndTime > strtotime(sqltime())) &&
            ($this->options->SitewideDoubleseedMode == 'timed')) {
            $Sitewide_Doubleseed_On = true;
            $Sitewide_Doubleseed    = $this->options->SitewideDoubleseedEndTime;
            $TimeNow = date('M d Y, H:i', $this->options->SitewideDoubleseedEndTime - (int) $user->heavy_info()['TimeOffset']);
            $PDS = '<span class="time" title="Sitewide Doubleseed for '. time_diff($this->options->SitewideDoubleseedEndTime, 2, false, false, 0).' (until '.$TimeNow.')">Sitewide Doubleseed for '.time_diff($this->options->SitewideDoubleseedEndTime, 2, false, false, 0).'</span>';
        } elseif ($this->options->SitewideDoubleseedMode == 'perma') {
            $Sitewide_Doubleseed_On = true;
           //$PDS = '<span class="time" title="Sitewide Doubleseed for '. time_diff($this->options->SitewideDoubleseedEndTime,2,false,false,0).' (until '.$TimeNow.')">Sitewide Doubleseed for '.time_diff($this->options->SitewideDoubleseedEndTime,2,false,false,0).'</span>';
        } else {
            $TimeStampNow = time();
            $PDSTimeStamp = strtotime($user->legacy['personal_doubleseed']);

            if ($PDSTimeStamp >= $TimeStampNow) {
                if (($PDSTimeStamp - $TimeStampNow) < (28*24*3600)) { // more than 28 days doubleseed and the time is only specififed in the tooltip
                    $TimeAgo = time_diff($user->legacy['personal_doubleseed'], 2, false, false, 0);
                    $PDS = "PDS for $TimeAgo";
                } else {
                    $PDS = "Personal Doubleseed";
                }
                $TimeNow = date('M d Y, H:i', $PDSTimeStamp - (int) $user->heavy_info()['TimeOffset']);
                $PDS = '<span class="time" title="Personal Doubleseed until '.$TimeNow.'">'.$PDS.'</span>';
            }
        }
        return $PDS;
    }

    public function get_donation_drive() {
        $ActiveDrive = $this->cache->get_value('active_drive');
        if ($ActiveDrive===false) {
            $ActiveDrive = $this->db->raw_query("SELECT ID, name, start_time, target_euros, threadid
                                                 FROM donation_drives WHERE state='active' ORDER BY start_time DESC LIMIT 1")->fetch(\PDO::FETCH_NUM);
            if (!$ActiveDrive) $ActiveDrive = array('false');
            $this->cache->cache_value('active_drive', $ActiveDrive, 0);
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
        $Load = sys_getloadavg();
        $info = new \stdClass();
        $info->time = number_format(((microtime(true)-$this->debug->StartTime)*1000), 5);
        $info->memory = get_size(memory_get_usage(true));
        $info->load = number_format($Load[0], 2).' '.number_format($Load[1], 2).' '.number_format($Load[2], 2);
        $info->date = time_diff(time(), 2, false, false, 1);
        return $info;
    }

    protected function get_debug_html() {
        /*
         * Prevent var_dump from being clipped in debug info
         */
        ini_set('xdebug.var_display_max_depth', -1);
        ini_set('xdebug.var_display_max_children', -1);
        ini_set('xdebug.var_display_max_data', -1);

        ob_start();
        $this->debug->git_commit();
        $this->debug->flag_table();
        $this->debug->error_table();
        $this->debug->permission_table();
        $this->debug->sphinx_table();
        $this->debug->query_table();
        $this->debug->cache_table();
        $this->debug->vars_table();
        $debug_html = ob_get_clean();
        return $debug_html;
    }

    public function username($user, $options = []) {
        // User can be user object or UserID
        $user = $this->users->load($user);
        if (!empty($user)) {
            $class = $this->permissions->load($user->legacy['PermissionID']);
            $group = $this->permissions->load($user->legacy['GroupPermissionID']);

            if (is_null($this->request->user->friends)) {
                $user->friends = $this->cache->get_value("user_friends_{$this->request->user->ID}");
                if (!$this->request->user->friends) {
                    $this->request->user->friends = $this->db->raw_query("SELECT FriendID, Type FROM friends WHERE UserID=?", [$this->request->user->ID])->fetchAll(\PDO::FETCH_ASSOC|\PDO::FETCH_UNIQUE);
                    $this->cache->cache_value("user_friends_{$this->request->user->ID}", $this->request->user->friends);
                }
            }
            if (!empty($this->request->user->friends[$user->ID])) {
                $user->friends = ($this->request->user->friends[$user->ID]['Type'] == 'friends');
                $user->blocked = ($this->request->user->friends[$user->ID]['Type'] == 'blocked');
            } else {
                $user->friends = false;
                $user->blocked = false;
            }
        } else {
            $class = null;
            $group = null;
        }

        return $this->render('snippets/username.html.twig', ['user' => $user, 'class' => $class, 'group' => $group, 'options' => $options]);
    }
}
