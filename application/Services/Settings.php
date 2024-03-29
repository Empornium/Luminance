<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Errors\ConfigurationError;

class Settings extends Service {

    protected $defaults = [
        'main' => [
            'site_name'                => '',
            'site_short_name'          => '',
            'site_motto'               => 'Here be Porn',
            'nonssl_site_url'          => '',
            'ssl_site_url'             => '',
            'site_ip'                  => '',
            'announce_url'             => null,
            'announce_urls'            => null,
            'nonssl_static_server'     => '/static/',
            'ssl_static_server'        => '/static/',
            'site_url'                 => null,
            'mail_domain'              => null,
            'static_server'            => null,
            'additional_domains'       => null,
            'internal_urls_regex'      => null,
            'non_anon_domains'         => null,
            'non_anon_urls_regex'      => null,
            'site_logo'                => 'styles/public/images/logo.svg'
        ],
        'modes' => [
            'profiler'                 => false
        ],
        'paths' => [
            'template_cache'           => null
        ],
        'keys' => [
            'enckey'                   => null,
            'crypto_key'               => null,
            'rss_hash'                 => null,
            'apcu_prefix'              => null
        ],
        'database' => [
            'host'                     => 'localhost',
            'username'                 => '',
            'password'                 => '',
            'db'                       => 'luminance',
            'port'                     => 3306,
            'socket'                   => '/var/run/mysqld/mysqld.sock',
            'persistent_connections'   => true,
            'strict_mode'              => false,
            'buffer_size'              => 16777216
        ],
        'memcached' => [
            'host'                     => 'unix:///var/run/memcached.sock',
            'port'                     => 0
        ],
        'sphinx' => [
            'host'                     => 'localhost',
            'port'                     => 9312,
            'max_matches'              => 1000,
            'matches_start'            => 100,
            'matches_step'             => 50,
            'index'                    => 'torrents'
        ],
        'tracker' => [
            'host'                     => 'localhost',
            'port'                     => 2710,
            'ssl_port'                 => 2710,
            'secret'                   => '',
            'reportkey'                => '',
        ],
        'site' => [
            'debug_mode'               => false,
            'open_registration'        => true,
            'user_limit'               => 5000,
            'starting_invites'         => 0,
            'block_tor'                => false,
            'block_opera_mini'         => false,
            'donor_invites'            => 2,
            'user_edit_post_time'      => 900,
            'user_flood_post_time'     => 10,
            'thread_catalogue'         => 500,
            'chat_url'                 => '',
            'help_url'                 => '',
            'advert_html'              => '',
            'image_proxy_url'          => null,
            'anonymizer_url'           => 'http://anonym.es/?',
            'geoip_city'               => false,
            'geoip_license_key'        => null,
        ],
        'users' => [
            'classes'                  => 'APPRENTICE =2,PERV =3,GOOD_PERV =4,GREAT_PERV =24,DONOR =20,SEXTREME_PERV =5,SMUT_PEDDLER =6,ADMIN =1,SYSOP =15',
            'level_staff'              => 500,
            'level_admin'              => 600,
        ],
        'pagination' => [
            'torrent_comments'         => 10,
            'posts'                    => 25,
            'threads'                  => 50,
            'torrents'                 => 50,
            'collages'                 => 25,
            'requests'                 => 25,
            'messages'                 => 25,
            'log_entries'              => 50,
            'reports'                  => 20,
        ],
        'irc' => [
            'nick'                     => '',
            'server'                   => '',
            'port'                     => 6667,
            'chan'                     => '',
            'announce_chan'            => '',
            'staff_chan'               => '',
            'disabled_chan'            => '',
            'help_chan'                => '',
            'debug_chan'               => '',
            'report_chan'              => '',
            'admin_chan'               => '',
            'lab_chan'                 => '',
            'status_chan'              => '',
            'nickserv_pass'            => '',
            'listen_port'              => 6659,
            'listen_address'           => 'localhost'
        ],
        'forums' => [
            'sig_max_width'            => 800,
            'sig_max_height'           => 300,
            'title_maxword_length'     => 42,
            'announcement_forum_id'    => 5,
            'staff_forum_id'           => 0,
            'trash_forum_id'           => null,
            'exclude_forums'           => '',
            'forums_reveal_voters'     => '15,21',
            'forums_double_post'       => ''
        ],
        'torrents' => [
            'auto_freeleech_size'      => 32212254720, # 30 GB
            'bonus_torrents_cap'       => 300,
            'torrent_sig_max_height'   => 800,
            'enhanced_vote_power'      => 2,
            'exclude_dupes_after_days' => 183,
            'exclude_dupes_seeds'      => 5,
            'torrent_edit_time'        => 1209600, # 3600 * 24 * 14
            'uploader_request_time'    => 86400,
            'request_approval_size'    => 107374182400, # 100 GB,
            'request_filler_share'     => 0.5
        ],
        'logs' => [
            'page_file'                => null,
            'page_level'               => 200
        ],
        'bitcoin' => [
            'auto_generate'            => false,
            'host'                     => 'localhost',
            'port'                     => 8332,
            'username'                 => '',
            'password'                 => ''
        ],
    ];

    protected $legacyConstantNames = [
        'main' => [
            'site_name'                => 'SITE_NAME',
            'nonssl_site_url'          => 'NONSSL_SITE_URL',
            'ssl_site_url'             => 'SSL_SITE_URL',
            'site_ip'                  => 'SITE_IP',
            'announce_url'             => 'ANNOUNCE_URL',
            'nonssl_static_server'     => 'NONSSL_STATIC_SERVER',
            'ssl_static_server'        => 'SSL_STATIC_SERVER',
            'site_url'                 => 'SITE_URL',
            'static_server'            => 'STATIC_SERVER',
            'internal_urls_regex'      => 'INTERNAL_URLS_REGEX'
        ],
        'keys' => [
            'enckey'                   => 'ENCKEY',
            'rss_hash'                 => 'RSS_HASH'
        ],
        'database' => [
            'host'                     => 'SQLHOST',
            'username'                 => 'SQLLOGIN',
            'password'                 => 'SQLPASS',
            'db'                       => 'SQLDB',
            'port'                     => 'SQLPORT',
            'socket'                   => 'SQLSOCK'
        ],
        'memcached' => [
            'host'                     => 'MEMCACHED_HOST',
            'port'                     => 'MEMCACHED_PORT'
        ],
        'sphinx' => [
            'host'                     => 'SPHINX_HOST',
            'port'                     => 'SPHINX_PORT',
            'max_matches'              => 'SPHINX_MAX_MATCHES',
            'matches_start'            => 'SPHINX_MATCHES_START',
            'matches_step'             => 'SPHINX_MATCHES_STEP',
            'index'                    => 'SPHINX_INDEX'
        ],
        'tracker' => [
            'host'                     => 'TRACKER_HOST',
            'port'                     => 'TRACKER_PORT',
            'secret'                   => 'TRACKER_SECRET',
            'reportkey'                => 'TRACKER_REPORTKEY'
        ],
        'site' => [
            'debug_mode'               => 'DEBUG_MODE',
            'open_registration'        => 'OPEN_REGISTRATION',
            'user_limit'               => 'USER_LIMIT',
            'starting_invites'         => 'STARTING_INVITES',
            'block_tor'                => 'BLOCK_TOR',
            'block_opera_mini'         => 'BLOCK_OPERA_MINI',
            'donor_invites'            => 'DONOR_INVITES',
            'user_edit_post_time'      => 'USER_EDIT_POST_TIME',
            'user_flood_post_time'     => 'USER_FLOOD_POST_TIME',
            'thread_catalogue'         => 'THREAD_CATALOGUE',
            'chat_url'                 => 'CHAT_URL',
            'help_url'                 => 'HELP_URL',
            'advert_html'              => 'ADVERT_HTML',
            'anonymizer_url'           => 'ANONYMIZER_URL'
        ],
        'users' => [
            'level_staff'              => 'LEVEL_STAFF',
            'level_admin'              => 'LEVEL_ADMIN'
        ],
        'pagination' => [
            'torrent_comments'         => 'TORRENT_COMMENTS_PER_PAGE',
            'posts'                    => 'POSTS_PER_PAGE',
            'threads'                  => 'TOPICS_PER_PAGE',
            'torrents'                 => 'TORRENTS_PER_PAGE',
            'requests'                 => 'REQUESTS_PER_PAGE',
            'messages'                 => 'MESSAGES_PER_PAGE',
            'log_entries'              => 'LOG_ENTRIES_PER_PAGE'
        ],
        'irc' => [
            'nick'                     => 'BOT_NICK',
            'server'                   => 'BOT_SERVER',
            'port'                     => 'BOT_PORT',
            'chan'                     => 'BOT_CHAN',
            'announce_chan'            => 'BOT_ANNOUNCE_CHAN',
            'staff_chan'               => 'BOT_STAFF_CHAN',
            'disabled_chan'            => 'BOT_DISABLED_CHAN',
            'help_chan'                => 'BOT_HELP_CHAN',
            'debug_chan'               => 'BOT_DEBUG_CHAN',
            'report_chan'              => 'BOT_REPORT_CHAN',
            'admin_chan'               => 'ADMIN_CHAN',
            'lab_chan'                 => 'LAB_CHAN',
            'status_chan'              => 'STATUS_CHAN',
            'nickserv_pass'            => 'BOT_NICKSERV_PASS',
            'listen_port'              => 'SOCKET_LISTEN_PORT',
            'listen_address'           => 'SOCKET_LISTEN_ADDRESS',
        ],
        'forums' => [
            'sig_max_width'            => 'SIG_MAX_WIDTH',
            'sig_max_height'           => 'SIG_MAX_HEIGHT',
            'title_maxword_length'     => 'TITLE_MAXWORD_LENGTH',
            'announcement_forum_id'    => 'ANNOUNCEMENT_FORUM_ID',
            'staff_forum_id'           => 'STAFF_FORUM_ID',
            'trash_forum_id'           => 'TRASH_FORUM_ID'
        ],
        'torrents' => [
            'auto_freeleech_size'      => 'AUTO_FREELEECH_SIZE',
            'bonus_torrents_cap'       => 'BONUS_TORRENTS_CAP',
            'torrent_sig_max_height'   => 'TORRENT_SIG_MAX_HEIGHT',
            'enhanced_vote_power'      => 'ENHANCED_VOTE_POWER',
            'exclude_dupes_after_days' => 'EXCLUDE_DUPES_AFTER_DAYS',
            'exclude_dupes_seeds'      => 'EXCLUDE_DUPES_SEEDS',
            'torrent_edit_time'        => 'TORRENT_EDIT_TIME',
            'uploader_request_time'    => 'UPLOADER_REQUEST_TIME',
            'request_approval_size'    => 'REQUEST_APPROVAL_SIZE',
            'request_filler_share'     => 'REQUEST_FILLER_SHARE'
        ],
        'bitcoin' => [
            'auto_generate'            => 'BTC_LOCAL',
            'host'                     => 'BTC_HOST',
            'port'                     => 'BTC_PORT',
            'username'                 => 'BTC_USER',
            'password'                 => 'BTC_PASS'
        ],
    ];

    # for counting filetypes
    public static $knownFileTypes = [
        'audio' => [
            'flac', 'mp3', 'mpa', 'ogg', 'wav', 'wma'
        ],
        'video' => [
            '3gp', 'aaf', 'asf', 'avi', 'divx', 'f4v', 'flv', 'hdmov',
            'm2v', 'm4v', 'mpeg', 'm1v', 'mkv', 'mov', 'mp4', 'mpg', 'ogv',
            'qt', 'rm', 'rmvb', 'swf', 'ts', 'vid', 'webm', 'wmv'
        ],
        'image' => [
            'bmp', 'gif', 'jfif', 'jpe', 'jpeg', 'jpg', 'jxl', 'png'
        ],
        'disc'  => [
            'bdmv', 'bup', 'clpi', 'img', 'iso', 'm2ts', 'mds', 'mpls', 'vob'
        ],
        'text'  => [
            'ass', 'azw', 'azw3', 'doc', 'docx', 'epub', 'inf', 'ini', 'js', 'json', 'log', 'mobi', 'nfo', 'opf', 'pdf', 'rtf', 'srt', 'txt'
        ],
        'executable'  => [
            'app', 'bat', 'com', 'exe', 'scr'
        ],
        'compressed'  => [
            '7', '7z', 'gz', 'gzip', 'rar', 'z', 'zip'
        ],
    ];


    protected $settings;

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->settings = $this->defaults;
        $this->settingsFile = $this->master->applicationPath . '/settings.ini';
        $this->readSettingsFile();
        $this->fillSettings();
    }

    protected function checkCryptoKey() {
        if (is_null($this->settings['keys']['crypto_key'])) {
            $cryptoKey = bin2hex(openssl_random_pseudo_bytes(16, $strong));
            if (!($strong === true)) throw new ConfigurationError("Cannot generate strong crypto_key!");
            $this->settings['keys']['crypto_key'] = $cryptoKey;
        }
    }

    protected function readSettingsFile() {
        $filename = $this->settingsFile;
        if (!is_file($filename) || !is_readable($filename)) {
            # Test for CLI mode, no request object is ready yet though
            if (php_sapi_name() === "cli") {
                print_r("CAUTION! No settings file is present, generating new crypto key.\n\n");
                $this->checkCryptoKey();
                return;
            } else {
                throw new ConfigurationError("Unable to read settings file: {$filename}");
            }
        }
        $fileSettings = parse_ini_file($filename, true);
        foreach ($fileSettings as $sectionName => $section) {
            foreach ($section as $setting => $value) {
                $this->settings[$sectionName][$setting] = $value;
            }
        }
    }

    protected function writeSettingsFile($fileSettings) {
        $filename = $this->settingsFile;
        if (!is_writable(dirname($filename))) {
            throw new ConfigurationError("Unable to write settings file: {$filename}");
        }
        if (is_file($filename)) {
            throw new ConfigurationError("Settings file: {$filename} already exists!");
        }
        $settingsFile = fopen($filename, "w");
        fwrite($settingsFile, $fileSettings);
        fclose($settingsFile);
    }

    public function generateConfig($settings, $compact = true) {
        $this->settings = $this->defaults;
        foreach ($this->settings as $sectionName => $section) {
            foreach ($section as $setting => $value) {
                if (isset($settings[$sectionName][$setting]))
                    $this->settings[$sectionName][$setting] = $settings[$sectionName][$setting];
            }
        }
        $this->checkCryptoKey();
        $this->fillSettings();
        ob_start();
        $this->printSettings(false, $compact);
        $fileSettings = ob_get_contents();
        ob_end_clean();
        $this->writeSettingsFile($fileSettings);
    }

    public function printSettings($printLegacy = true, $printMinimal = false) {
        print("; Settings which don't have to be changed from the default can be left commented\n"
             ."; out or deleted entirely.\n");

        foreach ($this->settings as $sectionName => $section) {
            $sectionHasContent = false;
            foreach ($section as $setting => $value) {
                # Filter out constants and defaults
                if ($value===@$this->defaults[$sectionName][$setting] && $printMinimal === true) continue;
                if ($value===@$this->legacyConstantNames[$sectionName][$setting] && $printMinimal === true) continue;
                if (!($sectionHasContent === true)) {
                     print("\n[{$sectionName}]\n");
                     $sectionHasContent = true;
                }
                if ($value===true) {
                    $valstr = 'On';
                } elseif ($value===false) {
                    $valstr = 'Off';
                } elseif (is_int($value) || is_float($value)) {
                    $valstr = strval($value);
                } else {
                    $valstr = "'" . strval($value) . "'";
                }
                print("{$setting} = {$valstr}\n");
                if (array_key_exists($sectionName, $this->legacyConstantNames) === true
                 && array_key_exists($setting, $this->legacyConstantNames[$sectionName]) === true
                 && $printLegacy === true) {
                    print("; (was: {$this->legacyConstantNames[$sectionName][$setting]})\n");
                }
            }
        }
    }

    public function __get($sectionName) {
        # this allows settings to be read as ->section_name->setting_name
        if (array_key_exists($sectionName, $this->settings)) {
            if (is_array($this->settings[$sectionName])) {
                $sectionObject = (object) $this->settings[$sectionName];
                return $sectionObject;
            }
        }
        return parent::__get($sectionName);
    }

    public function __isset($sectionName) {
        if (is_array($this->settings[$sectionName])) {
            return true;
        }
        return parent::__isset($sectionName);
    }

    protected function fillSettings() {
        # Remove http(s):// from site URLs
        if (!is_null($this->settings['main']['nonssl_site_url'])) {
            $this->settings['main']['nonssl_site_url'] = preg_replace('|http(s)?://|i', '', $this->settings['main']['nonssl_site_url']);
        }
        if (!is_null($this->settings['main']['ssl_site_url'])) {
            $this->settings['main']['ssl_site_url'] = preg_replace('|http(s)?://|i', '', $this->settings['main']['ssl_site_url']);
        }
        if (!is_null($this->settings['main']['site_url'])) {
            $this->settings['main']['site_url'] = preg_replace('|http(s)?://|i', '', $this->settings['main']['site_url']);
        }

        if (is_null($this->settings['main']['announce_url'])) {
            $this->settings['main']['announce_url'] = 'http://' . $this->settings['main']['nonssl_site_url'] . ':' . $this->settings['tracker']['port'];
        }

        $isHTTPS = (array_key_exists('SERVER_PORT', $this->master->server) === true && !(intval($this->master->server['SERVER_PORT']) === 80));
        if (is_null($this->settings['main']['site_url'])) {
            $this->settings['main']['site_url'] = ($isHTTPS === true) ? $this->settings['main']['ssl_site_url'] : $this->settings['main']['nonssl_site_url'];
        }
        if (is_null($this->settings['main']['static_server'])) {
            $this->settings['main']['static_server'] = ($isHTTPS === true) ? $this->settings['main']['ssl_static_server'] : $this->settings['main']['nonssl_static_server'];
        }
        if (is_null($this->settings['main']['mail_domain'])) {
            $this->settings['main']['mail_domain'] = $this->settings['main']['site_url'];
        }


        if (is_null($this->settings['main']['internal_urls_regex'])) {
            $internalURLsRegex = '@^https?:\/\/' . $this->settings['main']['site_url'] . '\/';
            foreach (explode(',', $this->main->additional_domains) as $domain) {
                $internalURLsRegex .= '|^https?:\/\/' .str_replace('.', '\.', $domain). '\/';
            }
            $internalURLsRegex .= '@';
            $this->settings['main']['internal_urls_regex'] = $internalURLsRegex;
        }

        if (is_null($this->settings['main']['non_anon_urls_regex'])) {
            $nonAnonURLsRegex = '@^https?:\/\/' . $this->settings['main']['site_url'];
            foreach (explode(',', $this->main->non_anon_domains) as $domain) {
                $nonAnonURLsRegex .= '|^https?:\/\/' . str_replace('.', '\.', $domain);
            }
            $nonAnonURLsRegex .= '@';
            $this->settings['main']['non_anon_urls_regex'] = $nonAnonURLsRegex;
        }

        # bit of a bodge, but necessary for older sites when migrating
        $this->settings['keys']['enckey'] = str_pad($this->settings['keys']['enckey'], 16, '\0');
    }

    public function getLegacyConstants() {
        $settings = $this->defaults;
        foreach ($this->legacyConstantNames as $sectionName => $section) {
            foreach ($section as $key => $constantName) {
                if (defined($constantName)) {
                    $settings[$sectionName][$key] = constant($constantName);
                }
            }
        }
        return $settings;
    }

    public function setLegacyConstants() {
        global $forumsRevealVoters, $forumsDoublePost,
               $badgeTypes, $autoAwardTypes,
               $shopActions, $excludeForums, $donateLevels,
               $excludeBytesDupeCheck, $specialChars, $knownFileTypes;

        foreach ($this->legacyConstantNames as $sectionName => $section) {
            foreach ($section as $key => $constantName) {
                $value = $this->settings[$sectionName][$key];
                define($constantName, $value);
            }
        }

        $userclasses = $this->settings['users']['classes'];
        foreach (explode(',', $userclasses) as $userclass) {
            list($constantName, $value) = explode('=', $userclass);
            define($constantName, $value);
        }

        $excludeForums = explode(',', $this->settings['forums']['exclude_forums']);
        $forumsRevealVoters = explode(',', $this->settings['forums']['forums_reveal_voters']);
        $forumsDoublePost = explode(',', $this->settings['forums']['forums_double_post']);

        define('STAFF_LEVEL', LEVEL_STAFF);
        define('ADMIN_LEVEL', LEVEL_ADMIN);

        # The rest we just leave plain & hardcoded for now
        # To either be changed into a setting in the future, or solved in a different way altogether

        define('MAX_FILE_SIZE_BYTES', 2097152); # the max filesize (enforced in client side and server side using this value)

        # BTC STUFF
        define('BTC_ADDRESS_REGEX', "/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,59}$/");

        # badge types
        $badgeTypes = ['Single', 'Multiple', 'Shop', 'Unique','Donor'];
        $autoAwardTypes  = ['NumPosts', 'NumComments', 'NumUploaded', 'NumNewTags', 'NumTags', 'NumTagVotes',
                          'RequestsFilled', 'UploadedTB', 'DownloadedTB', 'MaxSnatches', 'NumBounties', 'AccountAge'];

        $shopActions = ['gb','givegb','givecredits','slot','title','badge','pfl','ufl','invite'];

        $donateLevels = [1 => 1.0, 10 => 1.5, 50 => 2.0, 100 => 5];

        # key should be bytesize to exclude from dupe via bytesize check, value is reason displayed to user
        $excludeBytesDupeCheck = [734015488=>'a standard cd size', 1065353216=>'a standard vob size',  1073739776 => 'a standard vob size'];

        # Special characters, and what they should be converted to
        # Used for torrent searching
        $specialChars = [
                '&' => 'and'
        ];

        $knownFileTypes = self::$knownFileTypes;

        $this->setRegexConstants();
    }

    public function setRegexConstants() {
        # resource_type://username:password@domain:port/path?query_string#anchor
        define('RESOURCE_REGEX', '(https?|ftps?):\/\/');
        define('IP_REGEX', '(\d{1,3}\.){3}\d{1,3}');
        define('DOMAIN_REGEX', '(ssl.)?(www.)?[a-z0-9-\.]{1,255}\.[a-zA-Z]{2,6}');
        define('PORT_REGEX', '\d{1,5}');
        define('URL_REGEX', '('.RESOURCE_REGEX.')('.IP_REGEX.'|'.DOMAIN_REGEX.')(:'.PORT_REGEX.')?(\/\S*)*');
        define('EMAIL_REGEX', '[_a-z0-9-]+([.+][_a-z0-9-]+)*@'.DOMAIN_REGEX);
        define('IMAGE_REGEX', URL_REGEX.'\/\S+\.(jpg|jpeg|tif|tiff|png|gif|bmp)(\?\S*)?');
        define('SITELINK_REGEX', $this->settings['main']['internal_urls_regex']);
        define('TORRENT_REGEX', '('.SITELINK_REGEX.')?\/torrents.php\?id=([0-9]+)');
        define('TORRENT_GROUP_REGEX', SITELINK_REGEX.'\/torrents.php\?id=\d{1,10}\&(torrentid=\d{1,10})?');
    }
}
