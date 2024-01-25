<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Errors\InternalError;

use Luminance\Entities\User;
use Luminance\Entities\Collage;
use Luminance\Entities\ForumPost;

# The render class is a useful little minion that helps tie interface/template logic together
class Render extends Service {

    protected static $userinfoTools = [];

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
        'repos'     => 'Repos',
    ];

    protected static $tplFilters = [];
    protected static $tplFunctions = [];

    private $bbCode = null;
    private $templateVars = [];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $master->request;
        $this->bbCode = new \Luminance\Legacy\Text;
    }

    public static function registerTool($tools = null) {
        if (!is_array($tools)) {
            throw new InternalError('Tools register is not an array');
        }

        foreach ($tools as $tool) {
            if (!is_array($tool)) {
                throw new InternalError('Tool register is not an array');
            }

            if (!(count($tool) === 3)) {
                throw new InternalError('Tool register incorrect parameter count');
            }

            self::$userinfoTools[] = $tool;
        }
    }

    public function link() {
        parent::link();
        foreach (self::$tplFilters as $filterName => $filterArguments) {
            $filter = new \Twig_SimpleFilter($filterName, [$this, $filterArguments]);
            $this->tpl->addFilter($filter);
        }

        foreach (self::$tplFunctions as $functionName => $functionArguments) {
            $function = new \Twig_SimpleFunction($functionName, [$this, $functionArguments]);
            $this->tpl->addFunction($function);
        }
    }

    public function addVars($vars = []) {
        $this->templateVars = array_merge($this->templateVars, $vars);
    }

    protected function getBaseVariables() {
        global $document;

        $user = $this->request->user;
        $baseVars = $this->templateVars;
        $baseVars['master']             = $this->master;
        $baseVars['auth']               = $this->auth;
        $baseVars['request']            = $this->request;
        $baseVars['secretary']          = $this->secretary;
        $baseVars['settings']           = $this->settings;
        $baseVars['options']            = $this->options;
        $baseVars['rss_auth_string']    = $this->getRSSAuthString();
        $baseVars['render']             = $this;
        $baseVars['static_uri']         = $this->settings->main->static_server;
        $baseVars['main_uri']           = '';
        $baseVars['authenticated']      = isset($this->request->user);
        $baseVars['allow_registration'] = ($this->settings->site->open_registration) && ($this->repos->permissions->getMinUserClassID() > 0);
        $baseVars['ActiveUser']         = $user;
        $baseVars['Notifications']      = $this->getNotifications();
        if ($user instanceof User) {
            $baseVars['style']  = $user->style;
            $baseVars['UserStats']   = $this->auth->getUserStats($user->ID);
            $baseVars['Permissions'] = $this->auth->getUserPermissions($user);

            # Allow styles to override core templates
            $stylePath = $this->master->publicPath . "/static/styles/{$this->repos->stylesheets->getByUser($user)->Path}";
            if (is_dir("{$stylePath}/Templates")) {
                $this->tpl->overrideTemplatePath("{$stylePath}/Templates");
            } elseif (is_dir("{$stylePath}/templates")) {
                $this->tpl->overrideTemplatePath("{$stylePath}/templates");
            }
        }

        if (array_key_exists('wrap', $baseVars) === false) {
            $baseVars['wrap'] = true;
        }
        # Legacy stuff
        $baseVars['Document']   = $document;
        $baseVars['bbcode']     = $this->bbCode;

        return $baseVars;
    }

    protected function getRSSAuthString() {
        $user = $this->request->user;
        if (!($user instanceof User)) {
            return '';
        }
        $rssAuthString  = "user={$user->ID}";
        $rssAuthString .= "&auth={$user->legacy['RSS_Auth']}";
        $rssAuthString .= "&passkey={$user->legacy['torrent_pass']}";
        $rssAuthString .= "&authkey={$user->legacy['AuthKey']}";
        return $rssAuthString;
    }

    public function display($template, $values = [], $block = null) {
        $allValues = array_merge($this->getBaseVariables(), $values);
        $this->tpl->display($template, $allValues, $block);
    }

    public function template($template, $values = [], $block = null) {
        $allValues = array_merge($this->getBaseVariables(), $values);
        $output = $this->tpl->render($template, $allValues, $block);
        return $output;
    }

    public function fragment($template, $values = [], $block = null) {
        return new \Twig\Markup($this->template($template, $values, $block), 'UTF-8');
    }

    public function sriChecksum($input) {
        $hash = hash('sha256', $input, true);
        $hashBase64 = base64_encode($hash);
        return "sha256-{$hashBase64}";
    }

    public function displayPage($template, $values = [], $block = null) {
        if ($this->request->cli === false) {
            $values = array_merge($this->getBaseVariables(), $values);
            if (!isset($values['bscripts'])) {
                $values['bscripts'] = [];
            }
            $values = $this->getHeaderVars($values);
        }
        $this->tpl->display($template, $values, $block);
    }

    public function publicFileMtime($path) {
        $fullPath = $this->master->publicPath . '/' . $path;
        $mtime = @filemtime($fullPath);
        if ($mtime === false) {
            throw new InternalError("No mtime for {$fullPath}");
        }
        return $mtime;
    }

    public function publicFileExists($path) {
        $fullPath = $this->master->publicPath . '/' . $path;
        return !empty(glob($fullPath));
    }

    public function getAllowedUserinfoTools() {
        $entries = [];
        # Sort the tools menu alphabetically
        usort(
            self::$userinfoTools,
            function ($a, $b) {
                return strcmp($a[2], $b[2]);
            }
        );
        foreach (self::$userinfoTools as $e) {
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

    public function getScripts($extraScripts = []) {
        # Syntax is name (string), defer (bool)
        $scripts = [
            ['sizzle',        false ],
            ['svg-injector',  false ],
            ['script_start',  false ],
            ['flasher',       true  ],
            ['class_ajax',    true  ],
            ['class_storage', true  ], // needed for flasher
        ];

        # Only needed on private pages
        if (isset($this->request->user)) {
            $scripts[] = ['autocomplete', true ];
            $scripts[] = ['data_action',  true ];
        }

        $scripts[] = ['prism', true];        # handles code syntax highlighting

        # Ensure that $extraScripts is an array
        if (!is_array($extraScripts)) {
            $extraScripts = [];
        }

        # Merge the arrays
        foreach ($extraScripts as $extraScript) {
            $scripts[] = [$extraScript, true];
        }

        # inject jquery.migrate immediately after jquery
        $jquery = array_search('jquery', array_column($scripts, 0));
        if (!($jquery === false)) {
            $jquery++;
            array_splice($scripts, $jquery, 0, [['jquery.migrate', true]]);
        }

        foreach ($scripts as &$script) {
            $scriptName = trim($script[0]);
            $scriptDefer = (bool) $script[1];
            $script = [];

            if ($this->publicFileExists("static/functions/{$scriptName}.*")) {
                $script['path'] = "functions/{$scriptName}";
            } else {
                $script['path'] = "libraries/{$scriptName}";
            }

            # Load minified scripts if they exist when not in debug mode
            if (!$this->settings->site->debug_mode && $this->publicFileExists("{$script['path']}.min.js")) {
                $script['path'] .= '.min';
            }

            $script['path'] .= '.js';
            $script['defer'] = $scriptDefer;
        }

        # This should be last loaded!
        $scripts[] = [ 'path'=>'functions/global.js' ];

        foreach ($scripts as &$script) {
            $script['src'] = $this->settings->main->static_server . $script['path'] . '?v=' . $this->publicFileMtime('static/'.$script['path']);
            $script['sri'] = $this->sriChecksum(file_get_contents($this->master->publicPath.'/static/'.$script['path']));
        }

        return $scripts;
    }

    public function getNotifications() {
        $user = $this->request->user;
        if (!($user instanceof User)) {
            return null;
        }
        if (isset($user->legacy['Permissions']['site_torrents_notify'])) {
            $notifications = $this->cache->getValue('notify_filters_' . $user->ID);
            if (!is_array($notifications)) {
                $notifications = $this->db->rawQuery(
                    "SELECT ID, ID, Label FROM users_notify_filters WHERE UserID=:userid",
                    [':userid' => $user->ID]
                )->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
                $this->cache->cacheValue('notify_filters_' . $user->ID, $notifications, 2592000);
            }
        } else {
            $notifications = null;
        }
        return $notifications;
    }


    public function getHeaderVars($values = []) {
        if ($this->request->user) {
            # Set HTTP headers
            $this->request->setHttpHeaders();

            $user = $this->request->user;
            $hv = new \stdClass(); # Cheap & easy way to bundle it all

            $hv->NewSubscriptions = $this->cache->getValue('subscriptions_user_new_'.$user->ID);
            if ($hv->NewSubscriptions === false) {
                # num of params need to match query so build appropriately
                $params = [
                    ':userid1'    => $user->ID,
                    ':userclass' => $user->class->Level,
                    ':userid2'    => $user->ID,
                ];

                if (!empty($user->legacy['RestrictedForums'])) {
                    $restrictedForums = (array)explode(',', $user->legacy['RestrictedForums']);
                    $restrictedVars = $this->db->bindParamArray('rfid', $restrictedForums, $params);
                }
                if (!empty($user->legacy['PermittedForums'])) {
                    $permittedForums  = (array)explode(',', $user->legacy['PermittedForums']);
                    $permittedVars = $this->db->bindParamArray('pfid', $permittedForums, $params);
                }

                $params[':flags'] = ForumPost::TRASHED;
                if ($this->auth->isAllowed('forum_post_trash')) {
                    $params[':flags'] = 0;
                }

                $hv->NewSubscriptions = $this->db->rawQuery(
                    "SELECT COUNT(*) FROM
                        (SELECT fp2.AddedTime, MAX(fp.AddedTime) AS LastPostTime
                           FROM forums_subscriptions AS us
                           JOIN forums_last_read_threads AS flrt ON us.UserID = flrt.UserID AND us.ThreadID = flrt.ThreadID
                           JOIN forums_posts AS fp ON fp.ThreadID = us.ThreadID
                           JOIN forums_posts AS fp2 ON fp2.ID=flrt.PostID
                           JOIN forums_threads AS ft ON ft.ID = us.ThreadID
                           JOIN forums AS f ON f.ID = ft.ForumID
                          WHERE fp.AuthorID != :userid1
                            AND fp.Flags & :flags = 0
                            AND (f.MinClassRead <= :userclass".
                                (!empty($permittedVars) ? " OR f.ID IN ( $permittedVars ))" : ")").
                          " AND us.UserID = :userid2".
                                (!empty($restrictedVars) ? " AND f.ID NOT IN ( $restrictedVars )" : "").
                     " GROUP BY fp.ThreadID) AS lp
                    WHERE lp.AddedTime < lp.LastPostTime",
                    $params
                )->fetchColumn();
                $this->cache->cacheValue('subscriptions_user_new_'.$user->ID, $hv->NewSubscriptions, 0);
            }

            # Moved alert bar handling to before we draw minor stats to allow showing alert status in links too

            # Start handling alert bars
            $hv->Infos  = []; # an info alert bar (nicer color)
            $hv->Alerts = []; # warning bar (red!)
            $hv->ModBar = [];
            $hv->Urgent = [];

            # Forums
            if (empty($user->options('NoForumAlerts'))) {
                if ((int)$hv->NewSubscriptions > 0) {
                    $hv->Alerts[] = '<a href="/userhistory.php?action=subscriptions">You have '.$hv->NewSubscriptions.' unread forum subscription'.(($hv->NewSubscriptions > 1) ? 's' : '').'</a>';
                }
            }

            # News
            if (empty($user->options('NoNewsAlerts'))) {
                $currentNews = $this->cache->getValue('news_latest_id');
                if ($currentNews === false) {
                    $currentNews = $this->db->rawQuery("SELECT ID FROM news ORDER BY Time DESC LIMIT 1")->fetchColumn();
                    $this->cache->cacheValue('news_latest_id', $currentNews, 0);
                }

                if ((int)$user->legacy['LastReadNews'] < $currentNews) {
                    $hv->Alerts[] = '<a href="/index.php">New Announcement!</a>';
                }
            }

            # Blogs
            if (empty($user->options('NoBlogAlerts'))) {
                $currentBlogs = $this->cache->getValue('blog_latest_id');
                if ($currentBlogs === false) {
                    $currentBlogs = $this->db->rawQuery("SELECT ID FROM blog WHERE Section='Blog' ORDER BY Time DESC LIMIT 1")->fetchColumn();
                    $this->cache->cacheValue('blog_latest_id', $currentBlogs, 0);
                }

                if ((int)$user->legacy['LastReadBlog'] < $currentBlogs) {
                    $hv->Alerts[] = '<a href="/blog.php">New Site Blog!</a>';
                }
            }

            # Contests
            if (empty($user->options('NoContestAlerts'))) {
                $currentContests = $this->cache->getValue('contests_latest_id');
                if ($currentContests === false) {
                    $currentContests = $this->db->rawQuery("SELECT ID FROM blog WHERE Section='Contests' ORDER BY Time DESC LIMIT 1")->fetchColumn();
                    $this->cache->cacheValue('contests_latest_id', $currentContests, 0);
                }

                if ((int)$user->legacy['LastReadContests'] < $currentContests) {
                    $hv->Alerts[] = '<a href="/contests.php">New Contest!</a>';
                }
            }

            # Staff PMs for users
            $hv->NewStaffPMs = $this->cache->getValue('staff_pm_new_'.$user->ID);
            if ($hv->NewStaffPMs === false) {
                $hv->NewStaffPMs = $this->db->rawQuery("SELECT COUNT(ID) FROM staff_pm_conversations WHERE UserID=:userid AND UnRead = '1'", [':userid' => $user->ID])->fetchColumn();
                $this->cache->cacheValue('staff_pm_new_'.$user->ID, $hv->NewStaffPMs, 0);
            }

            if ($hv->NewStaffPMs > 0) {
                $hv->Alerts[] = '<a href="/staffpm.php?action=user_inbox">You have '.$hv->NewStaffPMs.' new staff message'.(($hv->NewStaffPMs > 1) ? 's' : '').'</a>';
            }

            # Urgent Staff PMs for users
            $hv->UrgentStaffPMs = $this->cache->getValue('staff_pm_urgent_'.$user->ID);
            if ($hv->UrgentStaffPMs === false) {
                $urgentPMs = $this->db->rawQuery("SELECT Urgent, Count(ID) AS NumPMs
                                                    FROM staff_pm_conversations
                                                   WHERE UserID=:userid
                                                     AND ((UnRead = '1' AND Urgent = 'Read')
                                                         OR (Status != 'Unanswered' AND Urgent = 'Respond'))
                                                GROUP BY Urgent", [':userid' => $user->ID])->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);

                # Don't generate unkown index errors
                $hv->UrgentStaffPMs = [ 'Read' => @(int)$urgentPMs['Read']['NumPMs'] , 'Respond' => @(int)$urgentPMs['Respond']['NumPMs']];
                $this->cache->cacheValue('staff_pm_urgent_'.$user->ID, $hv->UrgentStaffPMs, 0);
            }

            if (is_array($hv->UrgentStaffPMs)) {
                $numUrgentPMs = array_sum($hv->UrgentStaffPMs);
                if ($numUrgentPMs > 0) {
                    $hv->Urgent[] = '<a href="/staffpm.php?action=user_inbox">You have '.$numUrgentPMs.' urgent staff message'.($numUrgentPMs > 1 ? 's' : '').'</a>';
                    if ($numUrgentPMs === $hv->UrgentStaffPMs['Read']) {
                        $hv->Urgent[] = 'Please read '.($numUrgentPMs > 1 ? 'these' : 'this').' message'.($numUrgentPMs > 1 ? 's' : '').' immediately.';
                        $hv->Urgent[] = 'If you do not read '.($numUrgentPMs > 1 ? 'these' : 'this').' you may be restricted or banned.';
                    } elseif ($numUrgentPMs === $hv->UrgentStaffPMs['Respond']) {
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

            #Inbox
            $hv->NewMessages = $this->cache->getValue('inbox_new_'.$user->ID);
            if ($hv->NewMessages === false) {
                $hv->NewMessages = $this->db->rawQuery("SELECT COUNT(UnRead) FROM pm_conversations_users WHERE UserID=:userid AND UnRead = '1' AND InInbox = '1'", [':userid' => $user->ID])->fetchColumn();
                $this->cache->cacheValue('inbox_new_'.$user->ID, $hv->NewMessages, 0);
            }

            if ($hv->NewMessages > 0) {
                $hv->Alerts[] = '<a href="/user/inbox/received">You have '.$hv->NewMessages.(($hv->NewMessages > 1) ? ' new messages' : ' new message').'</a>';
            }

            if ($user->onRatiowatch()) {
                if ($user->legacy['can_leech'] === 1) {
                    $hv->Alerts[] = '<a href="/articles/view/ratio">'.'Ratio Watch'.'</a>: '.'You have '.time_diff($user->legacy['RatioWatchEnds'], 3, true, false, 0).' to get your ratio over your required ratio or your leeching abilities will be disabled.';
                } else {
                    $hv->Alerts[] = '<a href="/articles/view/ratio">'.'Ratio Watch'.'</a>: '.'Your downloading privileges are disabled until you meet your required ratio.';
                }
            }

            if ($this->auth->isAllowed('site_torrents_notify')) {
                $hv->NewNotifications = $this->cache->getValue('notifications_new_'.$user->ID);
                if ($hv->NewNotifications === false) {
                    $hv->NewNotifications = $this->db->rawQuery("SELECT COUNT(UserID) FROM users_notify_torrents WHERE UserID=:userid AND UnRead = '1'", [':userid' => $user->ID])->fetchColumn();
                    $this->cache->cacheValue('notifications_new_'.$user->ID, $hv->NewNotifications, 0);
                }
                if ($hv->NewNotifications > 0) {
                    $hv->Alerts[] = '<a href="/torrents.php?action=notify">'.'You have '.$hv->NewNotifications.' new torrent notification'.(($hv->NewNotifications > 1) ? 's' : '').'</a>';
                }
            }

            # Collage subscriptions
            if ($this->auth->isAllowed('collage_subscribe')) {
                $hv->NewCollages = $this->cache->getValue('collage_subs_user_new_'.$user->ID);
                if ($hv->NewCollages === false) {
                    $hv->NewCollages = $this->db->rawQuery(
                        "SELECT COUNT(DISTINCT s.CollageID)
                           FROM collages_subscriptions as s
                           JOIN collages as c ON s.CollageID = c.ID
                           JOIN collages_torrents as ct on ct.CollageID = c.ID
                          WHERE s.UserID = ?
                            AND ct.AddedOn > s.LastVisit
                            AND c.Flags & ? = '0'",
                        [$user->ID, Collage::TRASHED]
                    )->fetchColumn();
                    $this->cache->cacheValue('collage_subs_user_new_'.$user->ID, $hv->NewCollages, 0);
                }
                if ($hv->NewCollages > 0) {
                    $hv->Alerts[] = '<a href="/userhistory.php?action=subscribed_collages">You have '.$hv->NewCollages.' new collage update'.(($hv->NewCollages > 1) ? 's' : '').'</a>';
                }
            }

            # check allows FLS as well as staff to see PM's
            if (!($user->legacy['SupportFor'] === "") || $user->class->DisplayStaff === '1') {
                $hv->NumUnansweredStaffPMs = $this->db->rawQuery(
                    "SELECT COUNT(ID) FROM staff_pm_conversations
                            WHERE (AssignedToUser = ? OR Level <= ?)
                              AND Status IN ('Unanswered', 'User Resolved')
                              AND NOT StealthResolved",
                    [$user->ID, $user->class->Level]
                )->fetchColumn();

                $hv->NumOpenStaffPMs = $this->db->rawQuery(
                    "SELECT COUNT(ID) FROM staff_pm_conversations
                            WHERE (AssignedToUser = ? OR Level <= ?)
                              AND Status = 'Open'
                              AND NOT StealthResolved",
                    [$user->ID, $user->class->Level]
                )->fetchColumn();

                $hv->NumOpenStaffPMs += $hv->NumUnansweredStaffPMs;

                $hv->ModBar[] = '<a href="/staffpm.php?view=unanswered">('.$hv->NumUnansweredStaffPMs.')</a><a href="/staffpm.php?view=open">('.$hv->NumOpenStaffPMs.') Staff PMs</a>';
            }

            if ($this->auth->isAllowed('admin_reports')) {
                $hv->NumTorrentReports = $this->cache->getValue('num_torrent_reportsv2');
                if ($hv->NumTorrentReports === false) {
                    $hv->NumTorrentReports = $this->db->rawQuery("SELECT COUNT(ID) FROM reportsv2 WHERE Status='New'")->fetchColumn();
                    $this->cache->cacheValue('num_torrent_reportsv2', $hv->NumTorrentReports, 0);
                }

                $hv->ModBar[] = '<a href="/reportsv2.php">'.$hv->NumTorrentReports.' Report'.(!($hv->NumTorrentReports === 1) ? 's' : '').'</a>';
            }

            if ($this->auth->isAllowed('admin_reports')) {
                $hv->NumOtherReports = $this->cache->getValue('num_other_reports');
                if ($hv->NumOtherReports === false) {
                    $hv->NumOtherReports = $this->db->rawQuery("SELECT COUNT(ID) FROM reports WHERE Status='New'")->fetchColumn();
                    $this->cache->cacheValue('num_other_reports', $hv->NumOtherReports, 0);
                }

                $hv->ModBar[] = '<a href="/reports.php">'.$hv->NumOtherReports.' Other Report'.(!($hv->NumOtherReports === 1) ? 's' : '').'</a>';
            } elseif ($this->auth->isAllowed('site_project_team')) {
                $hv->NumUpdateReports = $this->cache->getValue('num_update_reports');
                if ($hv->NumUpdateReports === false) {
                    $hv->NumUpdateReports = $this->db->rawQuery("SELECT COUNT(ID) FROM reports WHERE Status='New' AND Type = 'request_update'")->fetchColumn();
                    $this->cache->cacheValue('num_update_reports', $hv->NumUpdateReports, 0);
                }

                $hv->ModBar[] = '<a href="/reports.php">'.$hv->NumUpdateReports.' Update Report'.(!($hv->NumUpdateReports === 1) ? 's' : '').'</a>';
            } elseif ($this->auth->isAllowed('forum_moderate') || $this->auth->isAllowed('site_view_reportsv1')) {
                $hv->NumForumReports = $this->cache->getValue('num_forum_reports');
                if ($hv->NumForumReports === false) {
                    $hv->NumForumReports = $this->db->rawQuery("SELECT COUNT(ID) FROM reports WHERE Status='New' AND Type IN('collages_comment', 'Post', 'requests_comment', 'thread', 'torrents_comment')")->fetchColumn();
                    $this->cache->cacheValue('num_forum_reports', $hv->NumForumReports, 0);
                }

                $hv->ModBar[] = '<a href="/reports.php">'.$hv->NumForumReports.' Other Report'.(!($hv->NumForumReports === 1) ? 's' : '').'</a>';
            }

            if ($this->auth->isAllowed('users_disable_users')) {
                $hv->NumPublicRequests = $this->cache->getValue('num_public_requests');
                if ($hv->NumPublicRequests === false) {
                    $hv->NumPublicRequests = $this->db->rawQuery("SELECT COUNT(ID) FROM public_requests WHERE Status='New' AND Type IN('reactivate','application')")->fetchColumn();
                    $this->cache->cacheValue('num_public_requests', $hv->NumPublicRequests, 0);
                }

                $hv->ModBar[] = '<a href="/manage/requests/new">'.$hv->NumPublicRequests.' Public Request'.(!($hv->NumPublicRequests === 1) ? 's' : '').'</a>';
            }

            $values['hv'] = $hv;
            $peerInfo = (array)user_peers($this->auth->getActiveUser()->ID);
            $values['leeching'] = $peerInfo['Leeching'] ?? 0;
            $values['seeding'] = $peerInfo['Seeding'] ?? 0;
            if ($this->auth->isAllowed('users_mod') || !($user->legacy['SupportFor'] === "") || $user->class->DisplayStaff === '1') {
                $values['userinfo_tools'] = $this->getAllowedUserinfoTools();
            }
            $values['invites'] = $user->legacy['Invites'];
            $values['sitewideFreeleech'] = $this->options->getSitewideFreeleech();
            $values['sitewideDoubleseed'] = $this->options->getSitewideDoubleseed();
            $values['donation_drive'] = $this->getDonationDrive();
        }
        if (!isset($values['bscripts'])) $values['bscripts'] = [];
        $values['scripts'] = $this->getScripts($values['bscripts']);

        return $values;
    }

    public function getDonationDrive() {
        $activeDrive = $this->cache->getValue('active_drive');
        if ($activeDrive===false) {
            $activeDrive = $this->db->rawQuery("SELECT ID, name, start_time, target_euros, threadid
                                                 FROM donation_drives WHERE state='active' ORDER BY start_time DESC LIMIT 1")->fetch(\PDO::FETCH_NUM);
            if (empty($activeDrive)) $activeDrive = ['false'];
            $this->cache->cacheValue('active_drive', $activeDrive, 0);
        }

        if (isset($activeDrive[1])) {
            list($ID, $name, $startTime, $targetEuros, $threadid) = $activeDrive;
            list($raisedEuros, $count) = $this->db->rawQuery("SELECT SUM(amount_euro), Count(ID)
                                                                FROM bitcoin_donations WHERE state!='unused' AND received > :starttime", [':starttime' => $startTime])->fetch(\PDO::FETCH_NUM);
            $percentdone = (int) ($raisedEuros * 100 / $targetEuros);
            if ($percentdone>100) $percentdone=100;
            $donationDrive = new \stdClass();
            $donationDrive->name = $name;
            $donationDrive->threadid = $threadid;
            $donationDrive->raisedEuros = $raisedEuros;
            $donationDrive->targetEuros = $targetEuros;
            $donationDrive->percentdone = $percentdone;
        } else {
            $donationDrive = false;
        }
        return $donationDrive;
    }

    public function getPerformanceInfo() {
        $load = sys_getloadavg();
        $info = new \stdClass();
        $info->time = number_format(((microtime(true)-Debug::$startTime)*1000), 5);
        $info->memory = get_size(memory_get_usage(true));
        $info->load = number_format($load[0], 2).' '.number_format($load[1], 2).' '.number_format($load[2], 2);
        $info->date = time_diff(time(), 2, false, false, 1);
        return $info;
    }

    public function username($user, $options = []) {
        # User can be user object or UserID
        $user = $this->repos->users->load($user);
        return $this->fragment('snippets/username.html.twig', ['user' => $user, 'options' => $options]);
    }

    public function userclass($class, $options = []) {
        # Class can be user object or PermissionID
        $class = $this->repos->permissions->load($class);

        return $this->fragment('snippets/userclass.html.twig', ['class' => $class, 'options' => $options]);
    }

    public function torrentUsername($user, $isAnon = false) {
        # User can be user object or UserID
        $user = $this->repos->users->load($user);

        $params = compact('user', 'isAnon');

        return $this->fragment('snippets/torrent_username.html.twig', $params);
    }

    public function latestForumThreads() {
        $user = $this->request->user;
        if (!empty($user->options('DisableLatestTopics'))) {
            return;
        }

        $latestThreads = [];

        $forums = $this->repos->forums->find(null, null, null, null, 'forums');

        $disabledLatestThreads = $user->options('DisabledLatestTopics', []);
        $restrictedForums = (array)explode(',', $user->legacy['RestrictedForums']);
        $userForums  = (array)explode(',', $user->legacy['PermittedForums']);
        $groupForums = (array)explode(',', $user->group->Forums ?? '');
        $permittedForums = array_merge($userForums, $groupForums);

        if ($this->auth->isAllowed('forum_post_trash')) {
            $checkFlags = 0;
        } else {
            $checkFlags = ForumPost::TRASHED;
        }

        foreach ($forums as $forum) {
            # Exclude forums the user is not permitted to see
            if ($forum->MinClassRead > $user->class->Level && !in_array($forum->ID, $permittedForums)) continue;

            # User has been explicitly restricted from this forum
            if (in_array($forum->ID, $restrictedForums)) continue;

            # Exclude forums the user is not interested in
            if (in_array($forum->ID, $disabledLatestThreads)) continue;

            $limit = $this->options->LatestForumThreadsNum;
            if (empty($limit)) {
                $limit = 6;
            }

            # Retrieve the forums latest posts
            $latestForumThreads = (array) $this->cache->getValue("latest_threads_forum_{$forum->ID}");
            if (($latestForumThreads[$checkFlags] ?? false) === false) {
                $latestForumThreads[$checkFlags] = $this->db->rawQuery(
                    "SELECT fp.ThreadID,
                            MAX(fp.AddedTime) AS LastPostTime
                       FROM forums_posts AS fp
                       JOIN forums_threads AS ft ON ft.ID=fp.ThreadID
                      WHERE ft.ForumID = ?
                        AND fp.Flags & ? = 0
                   GROUP BY fp.ThreadID
                   ORDER BY LastPostTime DESC
                      LIMIT {$limit}",
                    [$forum->ID, $checkFlags]
                )->fetchAll(\PDO::FETCH_ASSOC);
                $this->cache->cacheValue("latest_threads_forum_{$forum->ID}", $latestForumThreads, 0);
            }
            if (is_array($latestForumThreads[$checkFlags])) {
                $latestThreads = array_merge($latestThreads, (array) $latestForumThreads[$checkFlags]);
            }
        }

        # We need to sort the combined threads by their AddedTime
        usort($latestThreads, function ($a, $b) {
            if ($a['LastPostTime'] === $b['LastPostTime']) return 0;
            return $a['LastPostTime'] > $b['LastPostTime'] ? -1 : 1; # descending order
        });

        $latestThreads =  array_slice($latestThreads, 0, $this->options->LatestForumThreadsNum);
        $latestThreadIDs = array_column($latestThreads, 'ThreadID');
        $latestThreads = []; # Clear the latest threads array

        foreach ($latestThreadIDs as $latestThreadID) {
            $latestThreads[] = $this->repos->forumThreads->load($latestThreadID);
        }
        return $this->fragment('snippets/latest_forum_threads.html.twig', ['latestThreads' => $latestThreads]);
    }

    public function post($section, $post) {
        switch ($section) {
            case 'forum':
                # Post can be post object or PostID
                $post = $this->repos->forumPosts->load($post);
                $thread = $this->repos->forumThreads->load($post->ThreadID);
                $forum  = $this->repos->forums->load($thread->ForumID);
                $post->author = $this->repos->users->load($post->AuthorID);
                break;

            case 'collage comment':
                # Post can be post object or PostID
                $post = $this->repos->collageComments->load($post);
                break;

            case 'torrent comment':
                # Post can be post object or PostID
                $post->author = $this->repos->users->load($post->AuthorID);
                $linkID = $post->GroupID;
                $title = $post->group->Name ?? '';
                break;

            case 'user inbox':
                $post = $this->repos->userInboxMessages->load($post);
                break;
        }

        $params = [
            'forum'   => $forum ?? null,
            'linkID'  => $linkID ?? null,
            'post'    => $post,
            'section' => $section,
            'thread'  => $thread ?? null,
            'title'   => $title ?? null,
        ];

        return $this->fragment('snippets/post.html.twig', $params);
    }

    public function badges($user) {
        # User can be user object or UserID
        $user = $this->repos->users->load($user);

        # Return early if user doesn't exist
        if (empty($user)) return;

        $badges = get_user_badges($user->ID);
        return print_badges_array($badges, $user->ID);
    }

    /**
     * pagelinks returns a page list, given certain information about the pages.
     *
     * @param  integer $startPage    The current record the page you're on starts with.
     * @param  integer $totalRecords The total number of records in the result set.
     * @param  integer $itemsPerPage The number of records shown on each page.
     * @param  integer $showPages    The number of page links that are shown.
     * @param  string  $anchor       HTML anchor to include in the page URLs.
     * @param  string  $pageGetVar   HTTP parameter to fetch for page number.
     * @return string                Pre-rendered HTML containing the page list.
     */
    public function pagelinks($startPage, $totalRecords, $itemsPerPage, $showPages = 11, $anchor = '', $pageGetVar = 'page') {
        $location = $this->request->urlParts['path'];

        $queryString = get_url(array($pageGetVar, 'post'));
        if (!empty($queryString)) {
            $queryString = '&amp;' . $queryString;
        }

        $startPage = intval(ceil($startPage));
        if ($startPage < 1) {
            $startPage = 1;
        }
        $totalPages = 0;
        if ($totalRecords > 0) {
            $showPages--;
            $totalPages = intval(ceil($totalRecords / $itemsPerPage));

            if ($startPage > $totalPages) {
                $startPage = $totalPages;
            }

            if ($totalPages > $showPages) {
                $startPosition = intval($startPage - round($showPages / 2));

                if ($startPosition < 1) {
                    $startPosition = 1;
                } else {
                    if ($startPosition >= ($totalPages - $showPages)) {
                        $startPosition = $totalPages - $showPages;
                    }
                }

                $stopPage = $showPages + $startPosition;
            } else {
                $stopPage = $totalPages;
                $startPosition = 1;
            }

            if ($startPosition < 1) {
                $startPosition = 1;
            }

            $pages = $this->fragment(
                'snippets/pagelinks.html.twig',
                [
                    'pageGetVar'    => $pageGetVar,
                    'startPosition' => $startPosition,
                    'startPage'     => $startPage,
                    'stopPage'      => $stopPage,
                    'itemsPerPage'  => $itemsPerPage,
                    'totalRecords'  => $totalRecords,
                    'totalPages'    => $totalPages,
                    'queryString'   => $queryString,
                    'anchor'        => $anchor,
                    'location'      => $location,
                ]
            );
        }

        if ($totalPages > 1) {
            return $pages;
        }

        return '';
    }

    public function geoip($ip, $getHost = false, $banIPLink = false) {
        # Handle null IPs
        if (empty($ip)) {
            $ip = '0.0.0.0';
        }

        if (!($ip instanceof \Luminance\Entities\IP)) {
            $ip = $this->repos->ips->getOrNew($ip);
        }

        $params = [
            'ip'          => $ip,
            'getHost'     => $getHost,
            'banIPLink'   => $banIPLink,
        ];

        return $this->fragment('snippets/geoip.html.twig', $params);
    }

    public function forumGoto($forum) {
        $forum = $this->repos->forums->load($forum);
        $params = [
            'forum'      => $forum,
        ];
        return $this->fragment('snippets/forum_goto.html.twig', $params);
    }

    public function forumSelect($forumID, $elementID = null, $attribsRaw = null) {
        $params = [
            'forumID'    => $forumID,
            'elementID'  => $elementID,
            'attribsRaw' => $attribsRaw,
        ];

        # Create category structure
        $params['categories'] = $this->repos->forumCategories->find(null, null, 'Sort');

        # Load the forums for each category filtering out those which the user
        # lacks authorization to view.
        foreach ($params['categories'] as $categoryIndex => $category) {
            # Filter out the forums which the user lacks authorization to view.
            $forums = $category->allForums;
            foreach ($forums as $forumIndex => $forum) {
                if (!$forum->canRead($this->request->user)) {
                    unset($forums[$forumIndex]);
                }
            }

            # Also filter out empty categories
            if (empty($forums)) {
                unset($params['categories'][$categoryIndex]);
            } else {
                $category->forums = $forums;
            }
        }

        return $this->fragment('snippets/forum_select.html.twig', $params);
    }

    public function icon($classes, $glyphs, $data = []) {
        if (empty($glyphs)) {
            return;
        }

        if (!is_array($glyphs)) {
            $glyphs = [$glyphs];
        }

        $params = [
            'glyphs'  => $glyphs,
            'classes' => $classes,
            'data'    => $data,
        ];
        return $this->tpl->render('snippets/icon.html.twig', $params);
    }

    public function userLanguages($user) {
        if (!($user instanceof User)) {
            $user = $this->repos->users->load($user);
        }
        $params = compact('user');
        return $this->tpl->render('snippets/language.html.twig', $params);
    }
}
