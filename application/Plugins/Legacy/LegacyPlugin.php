<?php
namespace Luminance\Plugins\Legacy;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Errors\Error;
use Luminance\Errors\NotFoundError;

use Luminance\Legacy\SphinxSearch;

use Luminance\Services\Auth;
use Luminance\Services\Debug;

use Luminance\Responses\Rendered;

class LegacyPlugin extends Plugin {

    protected static $defaultOptions = [
        'MFDReviewHours'     => ['value' => 24,    'section' => 'legacy',  'displayRow' => 1, 'displayCol' => 1, 'type' => 'int',  'perm' => 'torrent_review_manage',   'description' => 'MFD fix time (hours)'],
        'MFDAutoDelete'      => ['value' => true,  'section' => 'legacy',  'displayRow' => 1, 'displayCol' => 2, 'type' => 'bool', 'perm' => 'torrent_review_manage',   'description' => 'Auto Delete MFD Torrents'],
        'UnseededAutoDelete' => ['value' => true,  'section' => 'legacy',  'displayRow' => 1, 'displayCol' => 3, 'type' => 'bool',                                       'description' => 'Auto Delete Unseeded Torrents'],
        'DeleteRecordsMins'  => ['value' => 360,   'section' => 'legacy',  'displayRow' => 2, 'displayCol' => 1, 'type' => 'int',  'perm' => 'admin_manage_cheats',      'description' => 'Speedrecord keep time (mins)'],
        'KeepSpeed'          => ['value' => 512,   'section' => 'legacy',  'displayRow' => 2, 'displayCol' => 2, 'type' => 'int',  'perm' => 'admin_manage_cheats',      'description' => 'Speedcheat threshold (bytes/s)'],
        'MinTagLength'       => ['value' => 3,     'section' => 'legacy',  'displayRow' => 4, 'displayCol' => 1, 'type' => 'int',                                        'description' => 'Minimum length of tags'],
        'MaxTagLength'       => ['value' => 32,    'section' => 'legacy',  'displayRow' => 4, 'displayCol' => 2, 'type' => 'int',                                        'description' => 'Maximum length of tags'],
        'MinTagNumber'       => ['value' => 1,     'section' => 'legacy',  'displayRow' => 4, 'displayCol' => 3, 'type' => 'int',                                        'description' => 'Minimum number of tags'],
        'RequireCover'       => ['value' => false, 'section' => 'legacy',  'displayRow' => 4, 'displayCol' => 4, 'type' => 'bool',                                       'description' => 'Require uploads have a cover image'],
        'IntroPMArticle'     => ['value' => '',    'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 1, 'type' => 'string', 'validation' => ['minlength' => 0], 'description' => 'Tag link for article with intro PM'],
        'EnableUploads'      => ['value' => true,  'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 2, 'type' => 'bool',                                       'description' => 'Global enable for uploads'],
        'EnableDownloads'    => ['value' => true,  'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 3, 'type' => 'bool',                                       'description' => 'Global enable for downloads'],
        'UniqueTorrents'     => ['value' => false, 'section' => 'legacy',  'displayRow' => 5, 'displayCol' => 4, 'type' => 'bool',                                       'description' => 'Inject unique mark into uploaded torrents'],
        'LeakingClients'     => ['value' => 3,     'section' => 'legacy',  'displayRow' => 6, 'displayCol' => 1, 'type' => 'int',                                        'description' => 'Passkey leak detection client threshold'],
        'LeakingIPs'         => ['value' => 10,    'section' => 'legacy',  'displayRow' => 6, 'displayCol' => 2, 'type' => 'int',                                        'description' => 'Passkey leak detection IP threshold'],
        'MinCreateBounty'    => ['value' => 1024*1024*1024,    'section' => 'legacy',  'displayRow' => 7, 'displayCol' => 1, 'type' => 'int',                            'description' => 'Minimum request starting bounty'],
        'MinVoteBounty'      => ['value' => 100*1024*1024,     'section' => 'legacy',  'displayRow' => 7, 'displayCol' => 2, 'type' => 'int',                            'description' => 'Minimum request voting bounty'],
        'BountySplit'        => ['value' => 50,    'section' => 'legacy',  'displayRow' => 7, 'displayCol' => 3, 'type' => 'int',                                        'description' => '% of bounty paid to the filler'],
        'RatioWatchEnabled'  => ['value' => true,  'section' => 'legacy',  'displayRow' => 1, 'displayCol' => 1, 'type' => 'bool',                                       'description' => 'Enable site-wide ratio watch.'],
        'MinimumRatio'       => ['value' => 0.5,   'section' => 'legacy',  'displayRow' => 1, 'displayCol' => 2, 'type' => 'numeric',                                    'description' => 'Min ratio for ratio watch' ],
];

    private static $legacyRoutes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'CLI',  'schedule',        Auth::AUTH_NONE,  'plugin', 'Legacy', 'schedule'    ],
        [ 'CLI',  'schedulev2',      Auth::AUTH_NONE,  'plugin', 'Legacy', 'schedulev2'  ],
        [ '*',    'ajax.php',        Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'ajax'        ],
        [ '*',    'blog.php',        Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'blog'        ],
        [ '*',    'bonus.php',       Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'bonus'       ],
        [ '*',    'bookmarks.php',   Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'bookmarks'   ],
        [ '*',    'contests.php',    Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'contests'    ],
        [ '*',    'donate.php',      Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'donate'      ],
        [ '*',    'feeds.php',       Auth::AUTH_NONE,  'plugin', 'Legacy', 'feeds'       ],
        [ '*',    'friends.php',     Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'friends'     ],
        [ '*',    'groups.php',      Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'groups'      ],
        [ '*',    'index.php',       Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'index'       ],
        [ '*',    'log.php',         Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'log'         ],
        [ '*',    'reports.php',     Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'reports'     ],
        [ '*',    'reportsv2.php',   Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'reportsv2'   ],
        [ '*',    'requests.php',    Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'requests'    ],
        [ '*',    'staffpm.php',     Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'staffpm'     ],
        [ '*',    'tags.php',        Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'tags'        ],
        [ '*',    'tools.php',       Auth::AUTH_2FA,   'plugin', 'Legacy', 'tools'       ],
        [ '*',    'top10.php',       Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'top10'       ],
        [ '*',    'torrents.php',    Auth::AUTH_NONE,  'plugin', 'Legacy', 'torrents'    ],
        [ '*',    'upload.php',      Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'upload'      ],
        [ '*',    'user.php',        Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'user'        ],
        [ '*',    'userhistory.php', Auth::AUTH_LOGIN, 'plugin', 'Legacy', 'userhistory' ],
    ];

    protected static $useServices = [
        'auth'          => 'Auth',
        'cache'         => 'Cache',
        'db'            => 'DB',
        'settings'      => 'Settings',
        'security'      => 'Security',
        'debug'         => 'Debug',
        'search'        => 'Search',
        'irker'         => 'Irker',
        'tracker'       => 'Tracker',
        'inviteManager' => 'InviteManager',
        'repos'         => 'Repos',
    ];

    protected static $userinfoTools = [
        #  permission                   action                                  title
        [ 'admin_manage_awards',       'tools.php?action=awards_auto',                 'Automatic Awards'        ],
        [ 'admin_manage_badges',       'tools.php?action=badges_list',                 'Badges'                  ],
        [ 'admin_manage_shop',         'tools.php?action=shop_list',                   'Bonus Shop'              ],
        [ 'admin_manage_categories',   'tools.php?action=categories',                  'Categories'              ],
        [ 'admin_whitelist',           'tools.php?action=client_blacklist',            'Client Blacklist'        ],
        [ 'admin_dnu',                 'tools.php?action=dnu',                         'Do not upload list'      ],
        [ 'admin_email_blacklist',     'tools.php?action=email_blacklist',             'Email Blacklist'         ],
        [ 'admin_imagehosts',          'tools.php?action=imghost_whitelist',           'Imagehost Whitelist'     ],
        [ 'admin_manage_ipbans',       'tools.php?action=ip_ban',                      'IP Bans'                 ],
        [ 'users_view_ips',            'tools.php?action=login_watch',                 'Login Watch'             ],
        [ 'users_mod',                 'tools.php?action=tokens',                      'Manage freeleech tokens' ],
        [ 'torrent_review',            'tools.php?action=marked_for_deletion',         'Marked for Deletion'     ],
        [ 'admin_manage_news',         'tools.php?action=news',                        'News'                    ],
        [ 'admin_manage_tags',         'tools.php?action=official_tags',               'Official Tags'           ],
        [ 'admin_convert_tags',        'tools.php?action=official_synonyms',           'Official Synonyms'       ],
        [ 'users_fls',                 'torrents.php?action=allcomments',              'Recent Torrent Comments' ],
        [ 'users_fls',                 'requests.php?action=allcomments',              'Recent Request Comments' ],
        [ 'admin_manage_languages',    'tools.php?action=languages',                   'Site Languages'          ],
        [ 'users_manage_cheats',       'tools.php?action=speed_cheats',                'Speed Cheats'            ],
        [ 'users_manage_cheats',       'tools.php?action=speed_records',               'Speed Reports'           ],
        [ 'admin_manage_site_options', 'tools.php?action=site_options',                'Site Options'            ],
        [ 'admin_manage_events',       'tools.php?action=events_list',                 'Upload Events'           ],
        [ 'torrent_review_manage',     'tools.php?action=marked_for_deletion_reasons', 'MFD Reasons'             ],
        [ 'admin_manage_permissions',  'tools.php?action=permissions',                 'User Classes'            ],
        [ 'users_groups',              'groups.php',                                   'User Groups'             ],
        [ 'admin_manage_blog',         'contests.php',                                 'Manage Contests'         ],
        [ 'admin_manage_blog',         'staffblog.php',                                'Manage Staff Blog'       ],
        [ 'admin_manage_blog',         'blog.php',                                     'Manage Blog'             ],
    ];

    public $renderPage = false;

    public static function register(Master $master) {
        parent::register($master);
        foreach (self::$legacyRoutes as $route) {
            $master->prependRoute($route);
        }
    }

    public function handlePath() {
        $section = func_get_arg(0);
        $this->settings->setLegacyConstants();

        # Start a new buffer to catch the page content
        ob_start();
        $this->scriptStart();
        try {
            $this->loadSection($section);
        } catch (Error $e) {
            ob_end_clean();
            throw $e;
        }
        $this->scriptFinish();
        $content = ob_get_contents();
        ob_end_clean();

        if ($this->renderPage === true) {
            return new Rendered('@Legacy/page.html.twig', ['content' => $content]);
        } else {
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            echo $content;
            die();
        }
    }

    public function loadSection($section) {
        global $autoAwardTypes, $badgeTypes, $browser, $class, $classes, $classLevels,
            $defaults, $document, $donateLevels, $dupeResults, $excludeBytesDupeCheck, $excludeForums, $feed,
            $forumsDoublePost, $forumsRevealVoters, $heavyInfo, $imagefiles, $languages,
            $activeUser, $master, $match, $media, $method, $newCategories, $openCategories, $orderBy, $orderWay,
            $paranoia, $permissions, $shopActions, $specialChars, $search,
            $bbCode, $time, $torrentID, $tracker, $userID, $values, $knownFileTypes;

        $document = $section;
        $path = "{$this->master->applicationPath}/Legacy/sections/{$section}/index.php";
        if (file_exists($path) === true) {
            require($path);
        } else {
            throw new NotFoundError();
        }
    }

    public function scriptStart() {
        # This code was originally part of script_start.php

        # First mark a whole lot of vars global since they were previously not inside a class context
        global $search, $browser, $classes, $classLevels, $newCategories, $openCategories, $activeUser, $userID,
               $heavyInfo, $permissions, $tracker, $document;

        $this->debug->handleErrors();
        Debug::setFlag('Debug constructed');
        $irker = $this->irker;
        $tracker = $this->tracker;
        $search = $this->search;

        Debug::setFlag('start user handling');

        $classes = $this->repos->permissions->getClasses();
        $classLevels = $this->repos->permissions->getLevels();

        Debug::setFlag('Loaded permissions');

        $newCategories = getNewCategories();
        $openCategories = getOpenCategories();
        Debug::setFlag('Loaded categories');

        $this->auth->setLegacySessionGlobals();

        Debug::setFlag('end user handling');
    }

    public function scriptFinish() {
        # This code was originally part of script_start.php

        Debug::setFlag('completed module execution');

        if (!headers_sent()) {
            # Required in the absence of session_start() for providing that pages will change
            # upon hit rather than being browser cache'd for changing content.
            header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
            header('Pragma: no-cache');

            Debug::setFlag('set headers and send to user');
        }
    }
}
