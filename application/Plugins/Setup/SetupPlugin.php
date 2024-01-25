<?php
namespace Luminance\Plugins\Setup;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Errors\Error;
use Luminance\Errors\UserError;
use Luminance\Errors\SystemError;

use Luminance\Entities\IP;
use Luminance\Entities\User;
use Luminance\Entities\Email;
use Luminance\Entities\Torrent;
use Luminance\Entities\Collage;
use Luminance\Entities\ForumPost;
use Luminance\Entities\ForumPoll;
use Luminance\Entities\ForumThread;
use Luminance\Entities\Permission;
use Luminance\Entities\Stylesheet;
use Luminance\Entities\Restriction;
use Luminance\Entities\TorrentGroup;
use Luminance\Entities\UserWallet;
use Luminance\Entities\UserHistoryIP;
use Luminance\Entities\UserHistoryPasskey;
use Luminance\Entities\UserHistoryPassword;

use Luminance\Services\Auth;
use Luminance\Services\Debug;
use Luminance\Services\Cache;

class SetupPlugin extends Plugin {

    use \Luminance\Legacy\Permissions;

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'CLI',  'configure',        Auth::AUTH_NONE, 'configure'      ],
        [ 'CLI',  'configure/**',     Auth::AUTH_NONE, 'configure'      ], # Configure can take a path to a script
        [ 'CLI',  'install',          Auth::AUTH_NONE, 'install'        ],
        [ 'CLI',  'install/*',        Auth::AUTH_NONE, 'install'        ],
        [ 'CLI',  'update',           Auth::AUTH_NONE, 'update'         ],
        [ 'CLI',  'update/*',         Auth::AUTH_NONE, 'update'         ],
        [ 'CLI',  'upgrade',          Auth::AUTH_NONE, 'upgrade'        ],
        [ 'CLI',  'upgrade/**',       Auth::AUTH_NONE, 'upgrade'        ],
        [ 'CLI',  'prune',            Auth::AUTH_NONE, 'prune'          ],
        [ 'CLI',  'remove',           Auth::AUTH_NONE, 'remove'         ],
        [ 'CLI',  'fixUser/*',        Auth::AUTH_NONE, 'migrateUser'    ],
        [ 'CLI',  'migratePerms',     Auth::AUTH_NONE, 'migratePerms'   ],
        [ 'CLI',  'cleanupBBCode',    Auth::AUTH_NONE, 'cleanupBBCode'  ],
        [ 'CLI',  'cleanupOptions',   Auth::AUTH_NONE, 'cleanupOptions' ],
        [ 'CLI',  'deduplicate',      Auth::AUTH_NONE, 'deduplicate'    ],
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ 'CLI', 'setup/**',       Auth::AUTH_NONE, 'plugin', 'Setup'                   ]);
        $master->prependRoute([ 'CLI', 'configure',      Auth::AUTH_NONE, 'plugin', 'Setup', 'configure'      ]);
        $master->prependRoute([ 'CLI', 'configure/**',   Auth::AUTH_NONE, 'plugin', 'Setup', 'configure'      ]);
        $master->prependRoute([ 'CLI', 'install',        Auth::AUTH_NONE, 'plugin', 'Setup', 'install'        ]);
        $master->prependRoute([ 'CLI', 'install/*',      Auth::AUTH_NONE, 'plugin', 'Setup', 'install'        ]);
        $master->prependRoute([ 'CLI', 'update',         Auth::AUTH_NONE, 'plugin', 'Setup', 'update'         ]);
        $master->prependRoute([ 'CLI', 'update/*',       Auth::AUTH_NONE, 'plugin', 'Setup', 'update'         ]);
        $master->prependRoute([ 'CLI', 'upgrade',        Auth::AUTH_NONE, 'plugin', 'Setup', 'upgrade'        ]);
        $master->prependRoute([ 'CLI', 'upgrade/**',     Auth::AUTH_NONE, 'plugin', 'Setup', 'upgrade'        ]);
        $master->prependRoute([ 'CLI', 'prune',          Auth::AUTH_NONE, 'plugin', 'Setup', 'prune'          ]);
        $master->prependRoute([ 'CLI', 'remove',         Auth::AUTH_NONE, 'plugin', 'Setup', 'remove'         ]);
        $master->prependRoute([ 'CLI', 'fixUser/*',      Auth::AUTH_NONE, 'plugin', 'Setup', 'fixUser'        ]);
        $master->prependRoute([ 'CLI', 'migratePerms',   Auth::AUTH_NONE, 'plugin', 'Setup', 'migratePerms'   ]);
        $master->prependRoute([ 'CLI', 'cleanupBBCode',  Auth::AUTH_NONE, 'plugin', 'Setup', 'cleanupBBCode'  ]);
        $master->prependRoute([ 'CLI', 'cleanupOptions', Auth::AUTH_NONE, 'plugin', 'Setup', 'cleanupOptions' ]);
        $master->prependRoute([ 'CLI', 'deduplicate',    Auth::AUTH_NONE, 'plugin', 'Setup', 'deduplicate'    ]);
    }

    protected function readline($prompt) {
        echo $prompt;
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        return trim($line);
    }

    protected function fqdn($input) {
        return preg_replace('|^http(s)://|', '', $input);
    }

    public function configure() {
        $filename = implode('/', func_get_args());
        # Convert Gazelle config to Luminance
        if (!empty($filename)) {
            print("Migrating config file to settings.ini".PHP_EOL);
            $motto = null;
            $compact = 'n';
            @include($filename); # Ignore errors
        } else {
            # Important site related stuff
            define('SITE_NAME',          $this->readline("Site name: "));
            $shortName = $this->readline("Site short name:");
            define('NONSSL_SITE_URL',    $this->fqdn($this->readline("Site FQDN: ")));
            define('SSL_SITE_URL',       $this->fqdn($this->readline("Site TLS FQDN: ")));
            $motto = $this->readline("Site Motto:");

            # Database credentials
            define('SQLDB',              $this->readline("Database name: "));
            define('SQLLOGIN',           $this->readline("Database username: "));
            define('SQLPASS',            $this->readline("Database password: "));

            # Auto generated keys
            define('ENCKEY',             $this->master->crypto->randomString(32));
            define('RSS_HASH',           $this->master->crypto->randomString(32));
            define('TRACKER_SECRET',     $this->master->crypto->randomString(32));
            define('TRACKER_REPORTKEY',  $this->master->crypto->randomString(32));

            $compact = $this->readline("Generate compact settings file? [y/n]");
        }
        $settings = $this->master->settings->getLegacyConstants();
        $settings['main']['site_short_name'] = $shortName;
        $settings['main']['site_motto'] = $motto;
        $this->master->settings->generateConfig($settings, $compact === 'y');
        print("Please open settings.ini and manually check its contents.".PHP_EOL.PHP_EOL);
        print("Done with configure!".PHP_EOL);
    }

    public function install($component = null) {
        switch ($component) {
            case 'styles':
                $this->updateStyles();
                break;

            case 'admin':
                $this->installAdminClass();
                $this->createInitialUser();
                break;

            default:
                # Check for existing install first
                print("Checking database".PHP_EOL);
                if (!empty($this->master->orm->getTables())) {
                    throw new SystemError('', "Database is already installed!  If you are running this script more than once, drop the luminance DB and create a new, empty DB");
                }
                print("Initializing legacy database table schemas".PHP_EOL);
                $this->updateLegacyTables();
                print("Initializing ORM database table schemas".PHP_EOL);
                $this->master->orm->updateTables();
                print("Installing stylesheets".PHP_EOL);
                $this->updateStyles();
                print("Installing Admin class".PHP_EOL);
                $this->installAdminClass();
                print("Updating GeoIP".PHP_EOL);
                $this->update('geoip');
                print("Fetching javascript libraries".PHP_EOL);
                $this->update('plotly');
                print("Please create the admin user now".PHP_EOL);
                $this->createInitialUser();
                print("Done with install!".PHP_EOL);
        }
    }

    public function remove() {
        # Debug only!
        if ($this->master->settings->site->debug_mode) {
            $this->master->orm->dropTables();
        }
    }

    # renames happen *BEFORE* migrations
    protected static $tableRenames = [
        'forums_topics'             => 'forums_threads',
        'forums_last_read_topics'   => 'forums_last_read_threads',
        'forums_specific_rules'     => 'forums_rules',
        'users_subscriptions'       => 'forums_subscriptions',
        'users_collage_subs'        => 'collages_subscriptions',
        'tag_synomyns'              => 'tags_synonyms',
    ];

    # Column renames can only be done on entities
    protected static $columnRenames = [
        'ForumPost'           => ['TopicID'    => 'ThreadID' ],
        'ForumPoll'           => ['TopicID'    => 'ThreadID' ],
        'ForumPollVote'       => ['TopicID'    => 'ThreadID' ],
        'ForumLastRead'       => ['TopicID'    => 'ThreadID' ],
        'ForumSubscription'   => ['TopicID'    => 'ThreadID' ],
        'UserHistoryPasskey'  => ['ChangeTime' => 'Time'     ],
        'UserHistoryPassword' => ['ChangeTime' => 'Time'     ],
        'CollageComment'      => [
            'UserID'     => 'AuthorID',
            'Time'       => 'AddedTime'
        ],
        'TagSynonym'          => ['Synomyn'    => 'Synonym'  ],
    ];

    private function databaseUpdate() {
        Cache::disable();
        Debug::disable();

        $currentTables = $this->master->orm->getTables();
        foreach (self::$tableRenames as $oldName => $newName) {
            if (in_array($oldName, $currentTables)) {
                try {
                    print("renaming {$oldName} to {$newName}".PHP_EOL);
                    $sql = "RENAME TABLE `{$oldName}` TO `{$newName}`";
                    $this->master->db->rawQuery($sql, []);
                } catch (SystemError $e) {
                    print("Error occured while renaming a table".PHP_EOL);
                    print("    old table name: {$oldName}".PHP_EOL);
                    print("    new table name: {$newName}".PHP_EOL);
                    print("    {$sql}".PHP_EOL);
                    print($e->getMessage().PHP_EOL);
                    die();
                }
            }
        }

        $entities = $this->master->orm->getEntityClasses();
        foreach (self::$columnRenames as $entityName => $columns) {
            try {
                $entity = preg_grep("/$entityName/", $entities);
                $entity = reset($entity);
                $table = $entity::$table;
            } catch (\Error $e) {
                print("Error while handling column rename for {$entityName}".PHP_EOL);
                print($e->getMessage().PHP_EOL);
                die();
            }
            $currentTableColumns = $this->master->orm->getTableColumns($table);
            foreach ($columns as $oldName => $newName) {
                if (in_array($oldName, $currentTableColumns)) {
                    try {
                        print("renaming {$table}.{$oldName} to {$table}.{$newName}".PHP_EOL);
                        $newColumn = $this->master->orm->getColumnSQL($newName, $entity::$properties[$newName]);
                        $sql = "ALTER TABLE `{$table}` CHANGE `{$oldName}` {$newColumn}";
                        $this->master->db->rawQuery($sql, []);
                    } catch (SystemError $e) {
                        print("Error occured while renaming a table column".PHP_EOL);
                        print("              table: {$table}".PHP_EOL);
                        print("    old column name: {$oldName}".PHP_EOL);
                        print("    new column name: {$newName}".PHP_EOL);
                        print("    {$sql}".PHP_EOL);
                        print($e->getMessage().PHP_EOL);
                        die();
                    }
                }
            }
        }

        $this->deduplicate();
        $this->master->orm->updateTables();
        $this->updateLegacyTables();
    }

    public function update($action = null) {
        switch ($action) {
            case 'db':
            case 'database':
                $this->databaseUpdate();
                break;

            case 'geoip':
                $this->master->geoip->update();
                break;

            case 'plotly':
                $this->master->plotly->update();
                break;

            case 'styles':
                $this->updateStyles();
                break;

            default:
                Cache::disable();
                Debug::disable();

                $this->deduplicate();
                $this->master->orm->updateTables();
                $this->updateLegacyTables();
                $this->master->geoip->update();
                $this->master->plotly->update();
                break;
        }
        $this->master->cache->flush();
    }

    protected function updateLegacyTables() {
        foreach (glob($this->master->basePath."/tablespecs/*.sql") as $tablespec) {
            # Fetch the SQL and pass to ORM to do the heavy lifting
            $sql = file_get_contents($tablespec);
            $this->master->orm->updateTables($sql);
        }
    }

    public function prune() {
        # Debug only!
        if ($this->master->settings->site->debug_mode) {
            $this->master->orm->pruneTables();
            $this->pruneLegacyTables();
        }
    }

    protected function pruneLegacyTables() {
        foreach (glob($this->master->basePath."/tablespecs/*.sql") as $tablespec) {
            # Fetch the SQL and pass to ORM to do the heavy lifting
            $sql = file_get_contents($tablespec);
            $this->master->orm->pruneTables($sql);
        }
    }

    public function upgrade() {
        $name = implode(' ', func_get_args());
        if (empty($name)) {
            $this->databaseUpdate();
            foreach (self::$tableMigrations as $name => $migration) {
                $this->performMigration($name, $migration);
            }
        } else if (array_key_exists($name, self::$tableMigrations)) {
            $migration = self::$tableMigrations[$name];
            $this->performMigration($name, $migration);
            return;
        } else {
            print("Unknown migration \"{$name}\"".PHP_EOL.PHP_EOL);
            print("Known migrations:".PHP_EOL);
            foreach (array_keys(self::$tableMigrations) as $name) {
                print($name.PHP_EOL);
            }
            return;
        }
        $this->cleanupOptions();
        $this->master->cache->flush();
    }

    private function checkDuplication(string $table, array $columns): int {
        $tables = $this->master->orm->getTables();
        if (in_array($table, $tables)) {
            if ($this->checkUniqueIndexes($table, $columns) === false) {
                print("Checking {$table} for duplicates".PHP_EOL);

                $groupColumns = implode(',', $columns);

                $rows = $this->master->db->rawQuery(
                    "SELECT MAX(`rows`)
                       FROM (
                          SELECT COUNT(*) AS `rows`
                            FROM `{$table}`
                        GROUP BY {$groupColumns}
                       ) AS `results`"
                )->fetchColumn();

                return intval($rows);
            }
        }
        return 0;
    }

    private function deduplicateIndex(string $table, array $columns) {
        if ($this->checkDuplication($table, $columns) > 1) {
            print("Deduplicating {$table}".PHP_EOL);
            $query  = "ALTER IGNORE TABLE {$table} ADD UNIQUE INDEX `dedupe` (`". implode('`, `', $columns) ."`)";
            $this->master->db->rawQuery($query);
            $this->master->db->rawQuery("ALTER TABLE {$table} DROP INDEX `dedupe`");
        }
    }

    private static $tableDeduplications = [
        'users_downloads'     => ['UserID', 'TorrentID'],
        'xbt_snatched'        => ['uid', 'fid'],
        'forums_polls_votes'  => ['UserID', 'ThreadID'],
        'ips'                 => ['StartAddress', 'EndAddress'],
    ];

    public function deduplicate() {
        # Force table copying for alter queries
        $this->master->db->rawQuery("set session old_alter_table=1");

        foreach (self::$tableDeduplications as $table => $columns) {
            $this->deduplicateIndex($table, $columns);
        }

        # Deduplicate sm_results, we do this in a special way
        if ($this->checkDuplication('sm_results', ['UserID']) > 1) {
            print("Deduplicating sm_results".PHP_EOL);
            $this->master->db->rawQuery("DROP TABLE IF EXISTS sm_results_old");
            $this->master->db->rawQuery("RENAME TABLE sm_results TO sm_results_old");
            $this->master->db->rawQuery("CREATE TABLE sm_results LIKE sm_results_old");
        }

        $this->master->db->rawQuery("set session old_alter_table=0");
    }

    protected function createInitialUser() {
        # Initial user creation
        $adminUsername = $this->readline("Admin Username: ");
        $adminPassword = $this->readline("Admin Password: ");
        $adminEmail = $adminUsername."@".$this->master->settings->main->site_url;
        $this->master->auth->createUser($adminUsername, $adminPassword, $adminEmail);

        # Won't work yet.
        $adminUserID = $this->master->repos->users->getByUsername($adminUsername)->ID;
        $adminAccount = $this->master->repos->users->load($adminUserID);
        $adminAccount->PermissionID = 1;
        $this->master->repos->users->save($adminAccount);
    }

    public function updateStyles() {
        foreach (glob($this->master->applicationPath."/../public/static/styles/*/meta.ini") as $styleFile) {
            $styleSpec = parse_ini_file($styleFile);
            $stylePath = basename(dirname($styleFile));
            $style = $this->master->repos->stylesheets->get('Name = ?', [$styleSpec['name']]);
            if (!($style instanceof Stylesheet)) {
                print("Installing {$styleSpec['name']} style".PHP_EOL);
                $style = new Stylesheet;
            } else {
                print("Updating {$styleSpec['name']} style".PHP_EOL);
            }

            $style->Path = $stylePath;
            $style->Name = $styleSpec['name'];
            $style->Description = $styleSpec['description'];
            $style->Default = $styleSpec['default'];
            $this->master->repos->stylesheets->save($style);
        }
    }

    public function installAdminClass() {
        if ($this->master->db->rawQuery("SELECT COUNT(*) FROM permissions")->fetchColumn() === 0) {
            $adminClass = new Permission();
            $adminClass->ID    = 1;
            $adminClass->Name  = 'SysOp';
            $adminClass->Level = 1000;
            $adminClass->Color = 'FF0000';
            $adminClass->MaxSigLength = 0;
            $adminClass->MaxAvatarWidth = 0;
            $adminClass->MaxAvatarHeight = 0;
            $adminClass->DisplayStaff = 1;
            $adminClass->IsUserClass = 1;
            $adminClass->isAutoPromote = 0;

            # Generate an array with every permission set
            $adminClass->Values = serialize(array_fill_keys(array_keys(self::$permissionsArray), '1'));
            $this->master->repos->permissions->save($adminClass);
        }
    }

    protected static $tableMigrations = [
        # Order matters!
        'users' => [
            'count_query' => "SELECT COUNT(*) FROM `users_main` AS um LEFT JOIN `users` AS u ON um.ID = u.ID WHERE u.ID IS NULL",
            'id_query' => "SELECT um.`ID` FROM `users_main` AS um LEFT JOIN `users` AS u ON um.ID = u.ID WHERE u.ID IS NULL ORDER BY um.ID LIMIT 1000",
        ],
        'emails' => [
            'count_query' => "SELECT COUNT(*) FROM `users_history_emails` AS uhe LEFT JOIN `emails` AS e on uhe.`Email` = e.`Address` WHERE e.`Address` IS NULL AND uhe.`Email` != ''",
            'id_query' => "SELECT uhe.`Email` FROM `users_history_emails` AS uhe LEFT JOIN `emails` AS e on uhe.`Email` = e.`Address` WHERE e.`Address` IS NULL AND uhe.`Email` != '' ORDER BY uhe.`UserID` LIMIT 1000",
            'indexes' => [
                'users_history_emails' => [
                    'Email' => ['Email'],
                ],
            ],
        ],
        'user ips' => [
            'count_query' => "SELECT COUNT(DISTINCT u.`ID`) FROM `users` AS u JOIN `users_history_ips` AS uhi ON u.`ID` = uhi.`UserID` LEFT JOIN `ips` ON u.`IPID` = ips.`ID` WHERE INET6_NTOA(`StartAddress`) IS NULL AND `IP` != ''",
            'id_query' => "SELECT DISTINCT u.`ID` FROM `users` AS u JOIN `users_history_ips` AS uhi ON u.`ID` = uhi.`UserID` LEFT JOIN `ips` ON u.`IPID` = ips.`ID` WHERE INET6_NTOA(`StartAddress`) IS NULL  AND `IP` != '' LIMIT 1000",
        ],
        'ip history' => [
            'count_query' => "SELECT COUNT(*) FROM `users_history_ips` AS uhi LEFT JOIN `ips` ON uhi.`IPID` = ips.`ID` WHERE INET6_NTOA(ips.`StartAddress`) IS NULL AND uhi.`IP` != ''",
            'id_query' => "SELECT uhi.`ID` FROM `users_history_ips` AS uhi LEFT JOIN `ips` ON uhi.`IPID` = ips.`ID` WHERE INET6_NTOA(ips.`StartAddress`) IS NULL AND uhi.`IP` != ''",
        ],
        'passkey ips' => [
            'count_query' => "SELECT COUNT(*) FROM `users_history_passkeys` AS uhp LEFT JOIN `ips` ON uhp.`IPID`=ips.`ID` WHERE INET6_NTOA(`StartAddress`) IS NULL AND `ChangerIP` != ''",
            'id_query' => "SELECT uhp.`ID` FROM `users_history_passkeys` AS uhp LEFT JOIN `ips` ON uhp.`IPID`=ips.`ID` WHERE INET6_NTOA(`StartAddress`) IS NULL AND `ChangerIP` != '' LIMIT 1000",
        ],
        'password ips' => [
            'count_query' => "SELECT COUNT(*) FROM `users_history_passwords` AS uhp LEFT JOIN `ips` ON uhp.`IPID`=ips.`ID` WHERE INET6_NTOA(`StartAddress`) IS NULL AND `ChangerIP` != ''",
            'id_query' => "SELECT uhp.`ID` FROM `users_history_passwords` AS uhp LEFT JOIN `ips` ON uhp.`IPID`=ips.`ID` WHERE INET6_NTOA(`StartAddress`) IS NULL AND `ChangerIP` != '' LIMIT 1000",
        ],
        'torrents' => [
            'count_query' => "SELECT COUNT(*) FROM `torrents_files` WHERE `Version` < 1",
            'id_query' => "SELECT `TorrentID` FROM `torrents_files` WHERE `Version` < 1 LIMIT 1000",
        ],
        'peers' => [
            'count_query' => "SELECT COUNT(*) FROM `xbt_peers_history` WHERE `ipv4` IS NULL AND `ipv6` IS NULL",
            'id_query' => "SELECT `id` FROM `xbt_peers_history` WHERE `ipv4` IS NULL AND `ipv6` IS NULL ORDER BY `id` LIMIT 1000",
        ],
        'snatches' => [
            'count_query' => "SELECT COUNT(*) FROM `xbt_snatched` WHERE `ipv4` IS NULL AND `ipv6` IS NULL AND INET6_ATON(`IP`) IS NOT NULL",
            'id_query' => "SELECT COUNT(*) AS snatches FROM (SELECT `IP` FROM `xbt_snatched` WHERE `ipv4` IS NULL AND `ipv6` IS NULL AND INET6_ATON(`IP`) IS NOT NULL LIMIT 1000) AS subquery HAVING snatches > 0",
            'indexes' => [
                'xbt_snatched' => [
                    'ips' => ['ipv4', 'ipv6', 'IP'],
                ],
            ],
        ],
        'ip bans' => [
            'count_query' => "SELECT COUNT(*) FROM `ip_bans`",
            'id_query' => "SELECT `ID` FROM `ip_bans` LIMIT 1000",
        ],
        'email times' => [
            'count_query' => "SELECT COUNT(*) FROM `emails` JOIN `users_history_emails` AS uhe ON uhe.`Email` = emails.`Address` WHERE `Created`='0000-00-00 00:00:00' or `Created` IS NULL",
            'id_query' => "SELECT emails.`ID` FROM `emails` JOIN `users_history_emails` AS uhe ON uhe.`Email` = emails.`Address` WHERE `Created`='0000-00-00 00:00:00' or `Created` IS NULL LIMIT 1000",
        ],
        'warnings' => [
            'count_query' => "SELECT COUNT(*) FROM `users_info` WHERE `Warned` >= NOW()",
            'id_query' => "SELECT `UserID` FROM `users_info` WHERE `Warned` >= NOW() LIMIT 1000",
        ],
        'restrictions' => [
            'count_query' => "SELECT COUNT(*) FROM `users_info` WHERE `DisableAvatar`='1' OR `DisableInvites`='1' OR `DisablePosting`='1' OR `DisableForums`='1' OR `DisableTagging`='1' OR `DisableUpload`='1' OR `DisablePM`='1' OR `DisableTorrentSig`='1' OR `DisableSignature` ='1'",
            'id_query' => "SELECT `UserID` FROM `users_info` WHERE `DisableAvatar`='1' OR `DisableInvites`='1' OR `DisablePosting`='1' OR `DisableForums`='1' OR `DisableTagging`='1' OR `DisableUpload`='1' OR `DisablePM`='1' OR `DisableTorrentSig`='1' OR `DisableSignature` ='1' LIMIT 1000",
        ],
        'slotMachine' => [
            'count_query' => "SELECT COUNT(DISTINCT `UserID`) FROM `sm_results_old`",
            'id_query' => "SELECT DISTINCT `UserID` FROM `sm_results_old` LIMIT 1000",
        ],
        'forum post locks' => [
            'count_query' => "SELECT COUNT(*) FROM `forums_posts` WHERE `TimeLock` < 2 AND `FLAGS` = ".ForumPost::TIMELOCKED,
            'id_query' => "SELECT `ID` FROM `forums_posts` WHERE `TimeLock` < 2 AND `FLAGS` = ".ForumPost::TIMELOCKED." LIMIT 1000",
            'indexes' => [
                'forums_posts' => [
                    'TimeLock' => ['TimeLock'],
                ],
            ],
        ],
        'sticky forum posts' => [
            'count_query' => "SELECT COUNT(*) FROM `forums_threads` WHERE `StickyPostID` > 0 AND `StickyPostID` is NOT NULL",
            'id_query' => "SELECT `ID` FROM `forums_threads` WHERE `StickyPostID` > 0 AND `StickyPostID` is NOT NULL LIMIT 1000",
        ],
        'forum titles encoding' => [
            'count_query' => "SELECT COUNT(*) FROM `forums_threads` WHERE Title REGEXP '&([a-zA-Z]+|#[0-9]{2,6});'",
            'id_query' => "SELECT `ID` FROM `forums_threads` WHERE Title REGEXP '&([a-zA-Z]+|#[0-9]{2,6})+;' LIMIT 1000",
        ],
        'forum poll indexes' => [
            'count_query' => "SELECT COUNT(*) FROM `forums_polls` WHERE Answers REGEXP '^a:[0-9]+:{i:0.*'",
            'id_query' => "SELECT `ID` FROM `forums_polls` WHERE Answers REGEXP '^a:[0-9]+:{i:0.*' LIMIT 1000",
        ],
        'torrent users' => [
            'count_query' => "SELECT COUNT(*) FROM `torrents_group` WHERE UserID IS NULL",
            'id_query' => "SELECT `ID` FROM `torrents_group` WHERE UserID IS NULL LIMIT 1000",
        ],
        'torrent thanks' => [
            'count_query' => "SELECT COUNT(*) FROM `torrents` WHERE Thanks != ''",
            'id_query' => "SELECT `ID` FROM `torrents` WHERE Thanks != '' LIMIT 1000",
        ],
        'credits' => [
            'count_query' => "SELECT COUNT(*) FROM `users_main` AS um LEFT JOIN `users_wallets` AS uw ON um.`ID` = uw.`UserID` WHERE uw.`UserID` IS NULL",
            'id_query' => "SELECT um.`ID` FROM `users_main` AS um LEFT JOIN `users_wallets` AS uw ON um.`ID` = uw.`UserID` WHERE uw.`UserID` IS NULL LIMIT 1000",
        ],
        'collage deleted' => [
            'count_query' => "SELECT COUNT(*) FROM `collages` WHERE `Deleted` = '1'",
            'id_query' => "SELECT `ID` FROM `collages` WHERE `Deleted` = '1' LIMIT 1000",
        ],
        'collage locked' => [
            'count_query' => "SELECT COUNT(*) FROM `collages` WHERE `Locked` = '1'",
            'id_query' => "SELECT `ID` FROM `collages` WHERE `Locked` = '1' LIMIT 1000",
        ],
        'collage featured' => [
            'count_query' => "SELECT COUNT(*) FROM `collages` WHERE `Featured` = '1'",
            'id_query' => "SELECT `ID` FROM `collages` WHERE `Featured` = '1' LIMIT 1000",
        ],
        'collage start date' => [
            'count_query' => "SELECT COUNT(*) FROM `collages` WHERE `StartDate` IS NULL AND NumTorrents > 0",
            'id_query' => "SELECT `ID` FROM `collages` WHERE `StartDate` IS NULL AND NumTorrents > 0 LIMIT 1000",
        ],
    ];

    private function checkUniqueIndexes($table, $columns) {
        # Primary indexes are unique
        if ($this->checkPrimaryIndex($table, $columns) === true) {
            return true;
        }

        # Check unique indexes
        $tableSpec = $this->master->orm->getTableSpecification($table);
        foreach ($tableSpec['indexes'] as $index) {
            if (array_key_exists('type', $index)) {
                if ($index['type'] === 'unique') {
                    # A unique index will match only if all the columns match
                    return empty(array_diff($columns, $index['columns']));
                }
            }
        }

        return false;
    }

    private function checkPrimaryIndex($table, $columns) {
        $tableSpec = $this->master->orm->getTableSpecification($table);
        foreach ($columns as $column) {
            if (array_key_exists($column, $tableSpec['properties'])) {
                if (array_key_exists('primary', $tableSpec['properties'][$column])) {
                    if ($tableSpec['properties'][$column]['primary'] === false) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    private function createIndexes($indexes) {
        foreach ($indexes as $table => $index) {
            $tableSpec = $this->master->orm->getTableSpecification($table);
            foreach ($index as $indexName => $indexColumns) {
                if (array_key_exists($indexName, $tableSpec['indexes']) === false) {
                    print("Adding temporty index {$indexName} to table {$table}".PHP_EOL);
                    $query  = "ALTER TABLE {$table} ADD INDEX `{$indexName}` (`";
                    $query .= implode('`, `', $indexColumns);
                    $query .= "`)";
                    $this->master->db->rawQuery($query);
                }
            }
        }
    }

    private function cleanupIndexes($indexes) {
        foreach ($indexes as $table => $index) {
            $tableSpec = $this->master->orm->getTableSpecification($table);
            foreach ($index as $indexName => $indexColumns) {
                if (array_key_exists($indexName, $tableSpec['indexes']) === true) {
                    print("Removing temporty index {$indexName} from table {$table}".PHP_EOL);
                    $query  = "ALTER TABLE {$table} DROP INDEX `{$indexName}`";
                    try {
                        $this->master->db->rawQuery($query, []);
                    } catch (SystemError $e) {
                        # assume exception means that the index no longer exists
                        return;
                    }
                }
            }
        }
    }

    public function performMigration($name, $migration) {
        print("Checking {$name} for data to migrate".PHP_EOL);
        try {
            list($rowCount) = $this->master->db->rawQuery($migration['count_query'], [])->fetch();
        } catch (SystemError $e) {
            # assume exception means that the table cannot be upgraded (pruned?)
            return;
        }

        # Check that there's something to migrate
        if ($rowCount === 0) {
            return;
        } else {
            $rowCount = number_format($rowCount);
        }

        # Ensure that we can index easily during the migration batches
        if (array_key_exists('indexes', $migration)) {
            $this->createIndexes($migration['indexes']);
        }
        print("Migrating {$name}, number of records: {$rowCount}".PHP_EOL);

        while (true) {
            try {
                $results = $this->master->db->rawQuery($migration['id_query'], [])->fetchAll();
            } catch (SystemError $e) {
                # assume exception means that the table cannot be upgraded (pruned?)
                return;
            }
            if (count($results) === 0) {
                print(PHP_EOL."Done migrating {$name}.".PHP_EOL);
                if (array_key_exists('indexes', $migration)) {
                    $this->cleanupIndexes($migration['indexes']);
                }
                break;
            }
            foreach ($results as $result) {
                try {
                    # Run the migration as a transaction as it can touch multiple tables
                    $this->master->db->beginTransaction();
                    $this->migrateEntity($name, $migration, $result);
                    $this->master->db->commit();
                } catch (\Exception $e) {
                    $this->master->db->rollback();
                    print("Failed migration".PHP_EOL);
                    print($e->getMessage().PHP_EOL);
                    throw $e;
                }
            }
        }
    }

    public function migrateEntity($name, $migration, $result) {
        switch ($name) {
            case 'users':
                list($ID) = $result;
                $this->migrateUser($ID);
                break;
            case 'emails':
                list($ID) = $result;
                $this->migrateEmail($ID);
                break;
            case 'user ips':
                list($ID) = $result;
                $this->migrateUserIPs($ID);
                break;
            case 'ip history':
                list($ID) = $result;
                $this->migrateIPHistory($ID);
                break;
            case 'passkey ips':
                list($ID) = $result;
                $this->migratePasskeyIPs($ID);
                break;
            case 'password ips':
                list($ID) = $result;
                $this->migratePasswordIPs($ID);
                break;
            case 'torrents':
                list($ID) = $result;
                $this->migrateTorrent($ID);
                break;
            case 'peers':
                list($ID) = $result;
                $this->migratePeers($ID);
                break;
            case 'snatches':
                list($count) = $result;
                $this->migrateSnatches($count);
                break;
            case 'ip bans':
                list($ID) = $result;
                $this->migrateIPBan($ID);
                break;
            case 'email times':
                list($ID) = $result;
                $this->migrateEmailTime($ID);
                break;
            case 'warnings':
                list($ID) = $result;
                $this->migrateWarnings($ID);
                break;
            case 'restrictions':
                list($ID) = $result;
                $this->migrateRestrictions($ID);
                break;
            case 'slotMachine':
                list($ID) = $result;
                $this->migrateSlotMachine($ID);
                break;
            case 'forum post locks':
                list($ID) = $result;
                $this->migrateForumPostLocks($ID);
                break;
            case 'sticky forum posts':
                list($ID) = $result;
                $this->migrateStickyForumPosts($ID);
                break;
            case 'forum titles encoding':
                list($ID) = $result;
                $this->migrateForumTitle($ID);
                break;
            case 'forum poll indexes':
                list($ID) = $result;
                $this->migrateForumPollIndex($ID);
                break;
            case 'torrent users':
                list($ID) = $result;
                $this->migrateTorrentUsers($ID);
                break;
            case 'torrent thanks':
                list($ID) = $result;
                $this->migrateTorrentThanks($ID);
                break;
            case 'credits':
                list($ID) = $result;
                $this->migrateCredits($ID);
                break;
            case 'collage deleted':
                list($ID) = $result;
                $this->migrateCollageDeleted($ID);
                break;
            case 'collage locked':
                list($ID) = $result;
                $this->migrateCollageLocked($ID);
                break;
            case 'collage featured':
                list($ID) = $result;
                $this->migrateCollageFeatured($ID);
                break;
            case 'collage start date':
                list($ID) = $result;
                $this->migrateCollageStarts($ID);
                break;
            default:
                break;
        }
    }

    public function migrateUser($ID) {
        print("Migrating user ID {$ID}\r");
        $user = $this->master->repos->users->get('ID = :id', [':id' => $ID]);
        $oldUser = $this->master->db->rawQuery("SELECT * FROM `users_main` AS um WHERE um.`ID` = ?", [$ID])->fetch();
        if (!strlen($oldUser['Username'])) {
            throw new SystemError('', "Found invalid old user: ID {$ID}");
        }
        if (!($user instanceof User)) {
            $user = new User();

            # Handle email migration
            try {
                if (!empty($oldUser['Email'])) {
                    $parts = explode('@', $oldUser['Email']);
                    if (!(count($parts) === 2)) {
                        $oldUser['Email'] = $oldUser['Email']."@".$this->master->settings->main->site_url;
                    }
                    $email = $this->master->emailManager->newEmail(intval($ID), $oldUser['Email']);
                    $email->setFlags(Email::IS_DEFAULT);
                    $this->master->repos->emails->save($email);
                } else {
                    $email = $this->master->emailManager->newEmail(intval($ID), $oldUser['Username']."@".$this->master->settings->main->site_url);
                    $email->setFlags(Email::IS_DEFAULT);
                    $this->master->repos->emails->save($email);
                }
            } catch (UserError $e) {
                $email = $this->master->emailManager->newEmail(intval($ID), $oldUser['Username']."@".$this->master->settings->main->site_url);
                $email->setFlags(Email::IS_DEFAULT);
                $this->master->repos->emails->save($email);
            }

            $user->EmailID = $email->ID;

            # Handle IP migration
            if (!empty($oldUser['IP'])) {
                $ip = $this->master->repos->ips->getOrNew($oldUser['IP']);
                if ($ip instanceof IP) {
                    $ip->LastUserID = $ID;
                    $this->master->repos->ips->save($ip);
                    $user->IPID = $ip->ID;
                }
            }

            $user->ID = $ID;
            $user->Username = $oldUser['Username'];

            # Not all password schemes salted hashes
            if (!empty($oldUser['Secret'])) {
                $encodedSecret = base64_encode($oldUser['Secret']);
            }

            # If bcrypt then it won't be Hex
            if (ctype_xdigit($oldUser['PassHash'])) {
                $encodedHash   = base64_encode(hex2bin($oldUser['PassHash']));

                if (strlen($oldUser['PassHash']) === 32) {
                    if (!empty($oldUser['Secret'])) {
                        $user->Password = "\$salted-md5\${$encodedSecret}\${$encodedHash}";
                    } else {
                        $user->Password = "\$md5\${$encodedSecret}\${$encodedHash}";
                    }
                } elseif (strlen($oldUser['PassHash']) === 40) {
                    if (!empty($oldUser['Secret'])) {
                        $user->Password = "\$salted-sha1\$\${$encodedHash}";
                    } else {
                        $user->Password = "\$sha1\$\${$encodedHash}";
                    }
                }
            } else {
                $hashInfo = password_get_info($oldUser['PassHash']);
                switch ($hashInfo['algoName']) {
                    case 'bcrypt':
                        $user->Password = "\$bcrypt{$oldUser['PassHash']}";
                        break;
                    default:
                        $user->Password = $oldUser['PassHash'];
                }
            }

            if (!empty($oldUser['2fa_secret'])) {
                $user->twoFactorSecret = $this->master->crypto->encrypt($oldUser['2fa_secret']);
            }

            $this->master->repos->users->save($user);
        }

        try {
            $email = $this->master->repos->emails->load($user->EmailID);
            if (!($email instanceof Email)) {
                $email = $this->master->emailManager->newEmail(intval($ID), $oldUser['Email']);
                $email->setFlags(Email::IS_DEFAULT);
                $this->master->repos->emails->save($email);
                $user->EmailID = $email->ID;
                $this->master->repos->users->save($user);
            }
        } catch (UserError $e) {}
    }

    private function migrateEmail($email) {
        print("Migrating email historical address {$email}              \r");
        $oldEmail = $this->master->db->rawQuery("SELECT uhe.`UserID`, uhe.`Email`, uhe.`Time`, uhe.`IP` FROM `users_history_emails` AS uhe WHERE uhe.`Email` = ? ORDER BY Time ASC LIMIT 1", [$email])->fetch();
        # This shouldn't be necessary.
        if ($oldEmail === false) {
            return;
        }
        try {
            $email = $this->master->emailManager->newEmail($oldEmail['UserID'], $oldEmail['Email']);
            $ip = $this->master->repos->ips->getOrNew($oldEmail['IP']);
            if ($ip instanceof IP) {
                $this->master->repos->ips->save($ip);
                $email->IPID = $ip->ID;
            }
            $email->Changed = $oldEmail['Time'];
            $email->Created = $oldEmail['Time'];
            $this->master->repos->emails->save($email);
            $this->master->db->rawQuery("DELETE FROM users_history_emails WHERE Email = ?", [$email]);
            unset($ip);
            unset($email);
        } catch (Error $e) {
            $this->master->db->rawQuery("DELETE FROM users_history_emails WHERE Email = ?", [$email]);
        }
    }

    private function migrateUserIPs($ID) {
        print("Migrating user IP for user ID {$ID}\r");
        try {
            $user = $this->master->repos->users->load($ID);
            if (!$user instanceof User) {
                return;
            }
            $ip = $this->master->db->rawQuery(
                'SELECT IP
                   FROM users_history_ips
                  WHERE UserID = ?
               ORDER BY StartTime DESC
                  LIMIT 1',
                [$ID]
            )->fetchColumn();

            $ip = $this->master->repos->ips->getOrNew($ip);
            if ($ip instanceof IP) {
                $user->IPID = $ip->ID;
                $this->master->repos->users->save($user);
            }
        } catch (SystemError $e) {
            return;
        }
    }

    private function migrateIPHistory($ID) {
        print("Migrating IP History record {$ID}\r");
        try {
            $history = $this->master->repos->userhistoryips->load($ID);
            if (!$history instanceof UserHistoryIP) {
                return;
            }

            $ip = $this->master->db->rawQuery(
                "SELECT IP
                   FROM users_history_ips
                  WHERE ID = ?",
                [$ID]
            )->fetchColumn();

            $ip = $this->master->repos->ips->getOrNew($ip);
            if ($ip instanceof IP) {
                $history->IPID = $ip->ID;
                $this->master->repos->userhistoryips->save($history);
            }
        } catch (SystemError $e) {
            return;
        }
    }

    private function migratePasskeyIPs($ID) {
        print("Migrating passkey IP ID {$ID}\r");
        try {
            $passkey = $this->master->repos->userhistorypasskeys->load($ID);
            if (!$passkey instanceof UserHistoryPasskey) {
                return;
            }
            $ip = $this->master->db->rawQuery(
                'SELECT ChangerIP
                   FROM users_history_passkeys
                  WHERE ID = ?',
                [$ID]
            )->fetchColumn();

            $ip = $this->master->repos->ips->getOrNew($ip);
            if ($ip instanceof IP) {
                $passkey->IPID = $ip->ID;
                $this->master->repos->userhistorypasskeys->save($passkey);
            }
        } catch (SystemError $e) {
            return;
        }
    }

    private function migratePasswordIPs($ID) {
        print("Migrating password IP ID {$ID}\r");
        try {
            $password = $this->master->repos->userhistorypasswords->load($ID);
            if (!$password instanceof UserHistoryPassword) {
                return;
            }
            $ip = $this->master->db->rawQuery(
                'SELECT ChangerIP
                   FROM users_history_passwords
                  WHERE ID = ?',
                [$ID]
            )->fetchColumn();

            $ip = $this->master->repos->ips->getOrNew($ip);
            if ($ip instanceof IP) {
                $password->IPID = $ip->ID;
                $this->master->repos->userhistorypasswords->save($password);
            }
        } catch (SystemError $e) {
            return;
        }
    }

    # kinda hacky
    private function migrateTorrent($ID) {
        print("Migrating torrent ID {$ID}\r");
        $file = $this->master->db->rawQuery(
            "SELECT File
               FROM torrents_files
              WHERE TorrentID=?",
            [$ID]
        )->fetchColumn();

        $file = base64_decode($file);
        $file = str_replace(
            [
                'O:12:"BENCODE_LIST"',
                'O:12:"BENCODE_DICT"',
            ],
            [
                'O:28:"Luminance\Legacy\BencodeList"',
                'O:28:"Luminance\Legacy\BencodeDict"',
            ],
            $file
        );
        $file = base64_encode($file);

        $this->master->db->rawQuery(
            "UPDATE torrents_files
                SET File = ?,
                    Version = 1
              WHERE TorrentID=?",
            [$file, $ID]
        );
    }

    private function migratePeers($ID) {
        print("Migrating peer record ID {$ID}\r");
        try {
            $this->master->db->rawQuery(
                "UPDATE xbt_peers_history
                    SET ipv4 = INET6_ATON(ip)
                  WHERE id = ?",
                [$ID]
            );
        } catch (SystemError $e) {
            # Well, shit.
            $this->master->db->rawQuery(
                "UPDATE xbt_peers_history
                    SET ipv4 = INET6_ATON('0.0.0.0')
                  WHERE id = ?",
                [$ID]
            );
            return;
        }
    }

    # This needs v4/v6 support added
    private function migrateSnatches($count) {
        # Hacky, but the SQL for this huge table needs to be fast
        static $migratedRows = 0;
        $migratedRows += $count;
        $migratedRowsStr = number_format($migratedRows);
        print("Migrating snatches {$migratedRowsStr}\r");
        $this->master->db->rawQuery(
            "UPDATE xbt_snatched
                SET ipv4 = INET6_ATON(IP),
                    IP = ''
              WHERE ipv4 IS NULL
                AND ipv6 IS NULL
                AND INET6_ATON(IP) IS NOT NULL
              LIMIT 1000"
        );
    }

    private function migrateIPBan($ID) {
        $oldBan = $this->master->db->rawQuery(
            "SELECT INET_NTOA(`FromIP`) AS `FromIP`,
                    INET_NTOA(`ToIP`) AS `ToIP`,
                    `UserID`,
                    `StaffID`,
                    `EndTime`,
                    `Reason`
               FROM `ip_bans`
              WHERE `ID` = ?",
            [$ID]
        )->fetch();
        $range = \IPLib\Factory::rangeFromBoundaries($oldBan['FromIP'], $oldBan['ToIP']);
        print("Migrating IP ban {$ID}: {$range}                  \r");
        $newBan = $this->master->repos->ips->ban((string) $range, $oldBan['Reason']);
        if (!is_null($newBan)) {
            $newBan->LastUserID   = $oldBan['UserID'];
            $newBan->ActingUserID = $oldBan['StaffID'];
            if (!is_null($oldBan['EndTime']) && !($oldBan['EndTime'] === '0000-00-00 00:00:00')) {
                $newBan->BannedUntil = $oldBan['EndTime'];
            }
            $this->master->repos->ips->save($newBan);
        }
        $this->master->db->rawQuery("DELETE FROM `ip_bans` WHERE `ID` = ?", [$ID]);
    }

    private function migrateEmailTime($ID) {
        print("Update Email Record {$ID}                  \r");
        try {
            $email = $this->master->repos->emails->load($ID);
            $oldEmail = $this->master->db->rawQuery(
                "SELECT `ID`,
                        `Time`
                   FROM users_history_emails
                  WHERE `Email` = ?
               ORDER BY `Time` ASC
                  LIMIT 1",
                [$email->Address]
            )->fetch();
            $email->Created = $oldEmail->Time;
            $this->master->repos->emails->save($email);
            $this->master->db->rawQuery("DELETE FROM users_history_emails WHERE ID = ?", [$oldEmail->ID]);
        } catch (Error $e) {
            $this->master->db->rawQuery("DELETE FROM users_history_emails WHERE Email = ?", [$oldEmail->ID]);
        }
    }

    private function migrateWarnings($ID) {
        print("Update User Warning Record {$ID}                  \r");
        list($warned) = $this->master->db->rawQuery("SELECT Warned FROM users_info WHERE UserID = ?", [$ID])->fetch();
        $restriction = new Restriction;
        $restriction->setFlags(Restriction::WARNED);
        $restriction->UserID  = $ID;
        $restriction->StaffID = 0;
        $restriction->Created = new \DateTime();
        $restriction->Expires = $warned;
        $restriction->Comment = "Migrated from Gazelle";
        $this->master->repos->restrictions->save($restriction);
        $this->master->db->rawQuery("UPDATE users_info SET Warned=NULL WHERE UserID = ?", [$ID]);
    }

    private function migrateRestrictions($ID) {
        print("Update User Restriction Record {$ID}                  \r");
        $oldRestrictions = $this->master->db->rawQuery(
            "SELECT `DisableAvatar`,
                    `DisableInvites`,
                    `DisablePosting`,
                    `DisableForums`,
                    `DisableTagging`,
                    `DisableUpload`,
                    `DisablePM`,
                    `DisableTorrentSig`,
                    `DisableSignature`
               FROM `users_info`
              WHERE `UserID` = ?",
            [$ID]
        )->fetch(\PDO::FETCH_ASSOC);

        $decode = [
            'DisableAvatar'     => Restriction::AVATAR,
            'DisableInvites'    => Restriction::INVITE,
            'DisablePosting'    => Restriction::POST,
            'DisableForums'     => Restriction::FORUM,
            'DisableTagging'    => Restriction::TAGGING,
            'DisableUpload'     => Restriction::UPLOAD,
            'DisablePM'         => Restriction::PM,
            'DisableSignature'  => Restriction::SIGNATURE,
            'DisableTorrentSig' => Restriction::TORRENTSIGNATURE,
        ];

        $restriction = new Restriction;
        foreach ($oldRestrictions as $oldRestriction => $notAllowed) {
            if ($notAllowed === '1') {
                $restriction->setFlags($decode[$oldRestriction]);
            }
        }

        $restriction->UserID  = $ID;
        $restriction->StaffID = 0;
        $restriction->Created = new \DateTime();
        $restriction->Comment = "Migrated from Gazelle";
        $this->master->repos->restrictions->save($restriction);
        $this->master->db->rawQuery(
            "UPDATE `users_info`
                SET `DisableAvatar`     = '0',
                    `DisableInvites`    = '0',
                    `DisablePosting`    = '0',
                    `DisableForums`     = '0',
                    `DisableTagging`    = '0',
                    `DisableUpload`     = '0',
                    `DisablePM`         = '0',
                    `DisableTorrentSig` = '0',
                    `DisableSignature`  = '0'
              WHERE `UserID` = ?",
            [$ID]
        )->fetch(\PDO::FETCH_ASSOC);
    }

    private function migrateSlotMachine($ID) {
        print("Update Slot Machine Record {$ID}                  \r");
        try {
            $this->master->db->rawQuery("INSERT INTO sm_results (SELECT UserID, SUM(Spins), SUM(Won), SUM(Bet*Spins), MAX(Time) FROM sm_results_old WHERE UserID=?)", [$ID]);
            $this->master->db->rawQuery("DELETE FROM sm_results_old WHERE UserID=?", [$ID]);
        } catch (SystemError $e) {
            print($e->getMessage().PHP_EOL);
            return;
        }
    }

    private function migrateForumPostLocks($ID) {
        print("Update lock on forum post {$ID}                  \r");
        # Do it this way to ensure that all DB columns are present
        $post = $this->master->db->rawQuery("SELECT * FROM forums_posts WHERE ID = ?", [$ID])->fetch(\PDO::FETCH_ASSOC);
        $flags = 0;
        if (is_null($post['ID']) === false) {
            if (in_array($post['EditedUserID'], [null, 0, $post['AuthorID']]) === false) {
                $flags |= ForumPost::EDITLOCKED;
            }
            if ($post['TimeLock'] === 1) {
                $flags |= ForumPost::TIMELOCKED;
            } else {
                $flags &= ForumPost::TIMELOCKED;
            }
            $this->master->db->rawQuery(
                "UPDATE forums_posts
                    SET Flags = ?,
                        TimeLock = TimeLock + 2
                  WHERE ID = ?",
                [$flags, $ID]
            );
        }
    }

    private function migrateStickyForumPosts($threadID) {
        print("Update sticky flag on forum thread {$threadID}                  \r");
        $postID = $this->master->db->rawQuery(
            'SELECT StickyPostID
               FROM forums_threads
              WHERE ID = ?',
            [$threadID]
        )->fetchColumn();
        $post = $this->master->repos->forumposts->load($postID);
        if ($post instanceof ForumPost) {
              $post->setFlags(ForumPost::PINNED);
              $this->master->repos->forumposts->save($post);
              $this->master->db->rawQuery(
                  'UPDATE forums_threads
                      SET StickyPostID = NULL
                    WHERE ID = ?',
                  [$threadID]
              );
        # Forum post was deleted? Double check!
        } else {
            $postID = $this->master->db->rawQuery(
                'SELECT ID
                   FROM forums_posts
                  WHERE ID = ?',
                [$postID]
            )->fetchColumn();

            if ($postID === false) {
                $this->master->db->rawQuery(
                    'UPDATE forums_threads
                        SET StickyPostID = NULL
                      WHERE ID = ?',
                    [$threadID]
                );
            }
        }
    }

    public function migrateForumTitle($ID) {
        print("Update title on forum thread {$ID}                  \r");
        $thread = $this->master->repos->forumthreads->load($ID);
        if ($thread instanceof ForumThread) {
            $thread->Title = html_entity_decode($thread->Title, ENT_QUOTES|ENT_HTML5, 'UTF-8');
            $this->master->repos->forumthreads->save($thread);
        }
    }

    public function migrateForumPollIndex($ID) {
        print("Update index on forum poll {$ID}                  \r");
        $poll = $this->master->repos->forumpolls->load($ID);
        if ($poll instanceof ForumPoll) {
            $answers = unserialize($poll->Answers);
            array_unshift($answers, '');
            unset($answers[0]);
            $poll->Answers = serialize($answers);
            $this->master->repos->forumpolls->save($poll);

            $this->master->db->rawQuery(
                'UPDATE forums_polls_votes
                    SET Vote = Vote+1
                  WHERE ThreadID = ?',
                [$poll->ThreadID]
            );
        }
    }

    public function migrateTorrentUsers($ID) {
        print("Update UserID on torrent group {$ID}                  \r");
        $group = $this->master->repos->torrentgroups->load($ID);
        if ($group instanceof TorrentGroup) {
            $torrent = $this->master->repos->torrents->get('GroupID = ?', [$group->ID]);
            $group->UserID = $torrent->UserID;
            $this->master->repos->torrentgroups->save($group);
        }
    }

    public function migrateTorrentThanks($ID) {
        print("Update Thanks on torrent group {$ID}                  \r");
        $torrent = $this->master->repos->torrents->load($ID);
        if ($torrent instanceof Torrent) {
            $group = $this->master->repos->torrentgroups->load($torrent->GroupID);
            if ($group instanceof TorrentGroup) {
                $torrentThanks = $this->master->db->rawQuery(
                    "SELECT Thanks
                       FROM torrents
                      WHERE ID = ?",
                    [$ID]
                )->fetchColumn();
                $torrentThanks = explode(', ', $torrentThanks);
                $groupThanks = explode(', ', $group->Thanks);
                $thanks = array_filter(array_unique(array_merge($torrentThanks, $groupThanks)));
                $group->Thanks = implode(', ', $thanks);
                $this->master->repos->torrentgroups->save($group);
                $this->master->db->rawQuery(
                    "UPDATE torrents
                        SET Thanks = ''
                      WHERE ID = ?",
                    [$ID]
                );
            }
        }
    }

    public function migrateCredits($ID) {
        print("Migrate credits for User {$ID}                  \r");
        $user = $this->master->repos->users->load($ID);
        if ($user instanceof User) {
            if ($user->wallet instanceof UserWallet) {
                $wallet = $user->wallet;
            } else {
                $wallet = new UserWallet;
            }

            $wallet->UserID = $user->ID;
            $wallet->Balance = $user->legacy['Credits'];
            $wallet->BalanceDaily = $user->legacy['CreditsDaily'];
            $wallet->SeedHours = $user->legacy['SeedHours'];
            $wallet->SeedHoursDaily = $user->legacy['SeedHoursDaily'];
            $wallet->Log = $user->legacy['BonusLog'];
            $this->master->repos->userWallets->save($wallet);

            $this->master->db->rawQuery(
                "UPDATE users_main AS um
                   JOIN users_info AS ui ON um.ID=ui.UserID
                    SET um.Credits = 0.0,
                        um.CreditsDaily = 0.0,
                        um.SeedHours = 0.0,
                        um.SeedHoursDaily = 0.0,
                        ui.BonusLog = ''
                  WHERE um.ID = ?",
                [$ID]
            );
        }
    }

    public function migrateCollageDeleted($ID) {
        print("Migrate deleted status for Collage {$ID}                  \r");
        $collage = $this->master->repos->collages->load($ID);
        if ($collage instanceof Collage) {
            $collage->setFlags(Collage::TRASHED);
            $this->master->repos->collages->save($collage);

            $this->master->db->rawQuery(
                "UPDATE collages
                    SET Deleted = '0'
                  WHERE ID = ?",
                [$collage->ID]
            );
        }
    }

    public function migrateCollageLocked($ID) {
        print("Migrate locked status for Collage {$ID}                  \r");
        $collage = $this->master->repos->collages->load($ID);
        if ($collage instanceof Collage) {
            $collage->setFlags(Collage::LOCKED);
            $this->master->repos->collages->save($collage);

            $this->master->db->rawQuery(
                "UPDATE collages
                    SET Locked = '0'
                  WHERE ID = ?",
                [$collage->ID]
            );
        }
    }

    public function migrateCollageFeatured($ID) {
        print("Migrate featured status for Collage {$ID}                  \r");
        $collage = $this->master->repos->collages->load($ID);
        if ($collage instanceof Collage) {
            $collage->setFlags(Collage::FEATURED);
            $this->master->repos->collages->save($collage);

            $this->master->db->rawQuery(
                "UPDATE collages
                    SET Featured = '0'
                  WHERE ID = ?",
                [$collage->ID]
            );
        }
    }

    public function migrateCollageStarts($ID) {
        print("Migrate start date for Collage {$ID}                  \r");
        $collage = $this->master->repos->collages->load($ID);
        if ($collage instanceof Collage) {
            $startDate = $this->master->db->rawQuery(
                "SELECT MIN(AddedOn)
                   FROM collages_torrents
                  WHERE CollageID = ?",
                [$collage->ID]
            )->fetchColumn();
            $collage->StartDate = $startDate;
            $this->master->repos->collages->save($collage);
        }
    }

    #TODO go back through the commit history and expand this
    use \Luminance\Legacy\Permissions;
    private static $permissionsMigrations = [
        'site_admin_forums'             => [
                                                'forum_admin',
                                                'forum_post_delete',
                                                'collage_post_delete',
                                                'torrent_post_delete',
                                                'request_post_delete',
                                           ],
        # Gadzooks this was overloaded
        'site_moderate_forums'          => [
                                                'forum_moderate',
                                                'forum_set_rules',
                                                'forum_post_restore',
                                                'forum_post_trash',
                                                'forum_post_lock',
                                                'forum_post_pin',
                                                'forum_post_edit',
                                                'forum_thread_trash',
                                                'forum_thread_move',
                                                'forum_thread_delete',
                                                'forum_thread_rename',
                                                'forum_thread_split',
                                                'forum_thread_merge',
                                                'forum_thread_pin',
                                                'forum_thread_lock',

                                                # Also did for collages
                                                'collage_post_trash',
                                                'collage_post_restore',
                                                'collage_post_lock',
                                                'collage_post_pin',
                                                'collage_post_edit',

                                                # Also did for torrents
                                                'torrent_post_trash',
                                                'torrent_post_restore',
                                                'torrent_post_lock',
                                                'torrent_post_pin',
                                                'torrent_post_edit'
                                           ],
        'site_polls_create'             => ['forum_polls_create'       ],
        'site_polls_moderate'           => ['forum_polls_moderate'     ],
        'site_forums_double_post'       => ['forum_thread_double_post' ],

        'site_collages_manage'          => ['collage_moderate'         ],
        'site_collages_create'          => ['collage_create'           ],
        'site_collages_delete'          => ['collage_delete'           ],
        'site_collages_subscribe'       => ['collage_subscribe'        ],
        'site_collages_personal'        => ['collage_personal'         ],
        'site_collages_renamepersonal'  => ['collage_renamepersonal'   ],
        'site_collages_recover'         => ['collage_trash'            ],

        'torrents_edit'                 => ['torrent_edit'             ],
        'torrents_review'               => ['torrent_review'           ],
        'torrents_review_override'      => ['torrent_review_override'  ],
        'torrents_review_manage'        => ['torrent_review_manage'    ],
        'torrents_download_override'    => ['torrent_download_override'],
        'torrents_delete'               => ['torrent_delete'           ],
        'torrents_delete_fast'          => ['torrent_delete_fast'      ],
        'torrents_freeleech'            => ['torrent_freeleech'        ],
        'torrents_hide_dnu'             => ['torrent_hide_dnu'         ],
        'torrents_hide_imagehosts'      => ['torrent_hide_imagehosts'  ],
    ];
    public function migratePerms() {
        print("Migrating permissions for users and classes");
        $permissions = $this->master->repos->permissions->find();
        foreach ($permissions as $permission) {
            $grants = $permission->getUnserialized();
            foreach ($grants as $granted => $access) {
                if (array_key_exists($granted, static::$permissionsMigrations)) {
                    foreach (static::$permissionsMigrations[$granted] as $newGrant) {
                        $grants[$newGrant] = $access;
                    }
                }
            }
            $permission->Values = serialize($grants);
            $this->master->repos->permissions->save($permission);
        }

        $users = $this->master->db->rawQuery(
            "SELECT ID,
                    CustomPermissions
               FROM users_main
              WHERE CustomPermissions IS NOT NULL
                AND CustomPermissions != ''"
        )->fetchAll(\PDO::FETCH_OBJ);

        foreach ($users as $user) {
            $grants = unserialize($user->CustomPermissions);
            foreach ($grants as $granted => $access) {
                if (array_key_exists($granted, static::$permissionsMigrations)) {
                    foreach (static::$permissionsMigrations[$granted] as $newGrant) {
                        $grants[$newGrant] = $access;
                    }
                }
            }
            $this->master->db->rawQuery(
                "UPDATE users_main
                    SET CustomPermissions = ?
                  WHERE ID = ?",
                [serialize($grants), $user->ID]
            );
        }
    }

    private static $bbCodeColumns = [
        'forums_posts'        => ['Body'],
        'news'                => ['Body'],
        'pm_messages'         => ['Body'],
        'torrents_comments'   => ['Body'],
        'torrents_group'      => ['Body', 'Image'],
        'collages_comments'   => ['Body'],
        'staff_blog'          => ['Body'],
        'articles'            => ['Body'],
        'systempm_templates'  => ['Body'],
        'blog'                => ['Body'],
        'requests_comments'   => ['Body'],
        'comments_edits'      => ['Body'],
        'upload_templates'    => ['Body'],
        'staff_pm_messages'   => ['Message'],
        'do_not_upload'       => ['Comment'],
        'imagehost_whitelist' => ['Comment'],
        'collages'            => ['Description'],
        'requests'            => ['Description', 'Image'],
        'users_main'          => ['Signature'],
        'users_info'          => ['AdminComment', 'Info', 'Avatar', 'TorrentSignature'],
        'restrictions'        => ['Comment'],
    ];

    private static $bbCodeReplace = [
        # BBCode smilies
        ':allbetter:'                => ':console:',
        ':hat:'                      => ':tiphat:',
        ':underweather:'             => ':badday:',

        # Articles links
        'articles\\.php\\?topic='    => 'articles/view/',

        # Very *VERY* old collage links
        'collage\\.php'              => 'collages.php',

        # Gazelle collage links
        'collages\\.php?page=([[:digit:]]+)&id=([[:digit:]]+)'      => 'collage/\\2?page=\\1',
        'collages\\.php?id='                                        => 'collage/',
        'collages\\.php?userid='                                    => 'collage/user/',
        'collages\\.php?action=search&search='                      => 'collage?terms=',
        'collages\\.php'                                            => 'collage',

        # Inbox links
        'inbox\\.php\\?action=compose&to=(.*)'                      => 'user/\\1/inbox/compose',
        'inbox\\.php'                                               => 'user/inbox',

        # Forum links
        'forums\\.php\\?page=1&action='                             => 'forums.php?action=',
        'forums\\.php\\?action=unread'                              => 'forum/unread',
        'forums\\.php\\?action=allposts&'                           => 'forum/recent?',
        'forums\\.php\\?action=allposts'                            => 'forum/recent',
        'forums\\.php\\?action=search&(.*?)search='                 => 'forum/search?\\1terms=',
        'forums\\.php\\?action=search'                              => 'forum/search',
        'forums\\.php\\?action=viewthread&threadid=([[:digit:]]+)&' => 'forum/thread/\\1?',
        'forums\\.php\\?action=viewthread&threadid=([[:digit:]]+)'  => 'forum/thread/\\1',
        'forums\\.php\\?action=viewtopic&topicid=([[:digit:]]+)&'   => 'forum/thread/\\1?',
        'forums\\.php\\?action=viewtopic&topicid=([[:digit:]]+)'    => 'forum/thread/\\1',
        'forums\\.php\\?action=viewforum&forumid=([[:digit:]]+)&'   => 'forum/\\1?',
        'forums\\.php\\?action=viewforum&forumid=([[:digit:]]+)'    => 'forum/\\1',

        # Staff page links
        'staff\\.php'                => 'staff',
        'staffblog\\.php'            => 'staff/blog',
    ];

    public function cleanupBBCode() {
        foreach (self::$bbCodeColumns as $table => $columns) {
            foreach ($columns as $column) {
                print("Cleaning {$table}:{$column}".PHP_EOL);
                foreach (self::$bbCodeReplace as $replace => $with) {
                    $this->master->db->rawQuery(
                        "UPDATE `{$table}`
                            SET `{$column}` = REGEXP_REPLACE({$column}, ?, ?)
                          WHERE `{$column}` REGEXP ?",
                        [$replace, $with, $replace]
                    );
                }
            }
        }
    }

    private static $siteOptionsRenames = [
        'announceAddress' => 'serverAddress',
    ];

    public function cleanupOptions() {
        print("Cleaning site options".PHP_EOL);
        foreach (self::$siteOptionsRenames as $replace => $with) {
            $this->master->db->rawQuery(
                "UPDATE `options`
                    SET `Name` = ?
                  WHERE `Name` = ?",
                [$with, $replace]
            );
        }
    }
}
