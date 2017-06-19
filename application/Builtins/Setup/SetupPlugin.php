<?php
namespace Luminance\Builtins\Setup;

use Luminance\Core\Master;
use Luminance\Core\Plugin;
use Luminance\Errors\SystemError;

use Luminance\Entities\User;
use Luminance\Entities\Permission;
use Luminance\Entities\Stylesheet;

class SetupPlugin extends Plugin {

    public $routes = [
        [ 'CLI',  'configure/**',     0, 'configure' ], // Configure can take a path to a script
        [ 'CLI',  'install',          0, 'install'   ],
        [ 'CLI',  'update',           0, 'update'    ],
        [ 'CLI',  'upgrade',          0, 'upgrade'   ],
    ];

    protected static $useRepositories = [
        'users'        => 'UserRepository',
        'emails'       => 'EmailRepository',
        'ips'          => 'IPRepository',
        'permissions'  => 'PermissionRepository',
        'styles'       => 'StylesheetRepository',
    ];

    protected static $useServices = [
        'crypto'        => 'Crypto',
        'db'            => 'DB',
        'cache'         => 'Cache',
        'orm'           => 'ORM',
        'settings'      => 'Settings',
        'emailManager'  => 'EmailManager',
        'auth'          => 'Auth',
    ];

    public static function register(Master $master) {
        $master->prependRoute([ 'CLI', 'setup/**', 0, 'plugin', 'Setup' ]);
    }

    protected function readline($prompt) {
        echo $prompt;
        $handle = fopen ("php://stdin","r");
        $line = fgets($handle);
        return trim($line);
    }

    public function configure($filename = null) {
        // Convert Gazelle config to Luminance
        if(!is_null($filename)) {
            echo "Migrating config file to settings.ini\n";
            echo "Please open settings.ini and manually check its contents.\n\n";
            @include($filename); // Ignore errors
        } else {

            // Important site related stuff
            define('SITE_NAME',       $this->readline("Site name: "));
            define('NONSSL_SITE_URL', $this->readline("Site URL: "));
            define('SSL_SITE_URL',    $this->readline("Site TLS URL: "));

            // Database credentials
            define('SQLDB',           $this->readline("Database name: "));
            define('SQLLOGIN',        $this->readline("Database username: "));
            define('SQLPASS',         $this->readline("Database password: "));

            // Auto generated keys
            define('ENCKEY',          $this->crypto->random_string(32));
            define('RSS_HASH',        $this->crypto->random_string(32));
            define('TRACKER_SECRET',  $this->crypto->random_string(32));

        }
        $settings = parse_ini_file($this->master->application_path."/Builtins/Setup/settings.ini.template", true);
        $this->settings->generateConfig($settings);
    }

    public function install() {
        print_r("Initializing legacy database table schemas\n");
        $this->initializeLegacyTables();
        print_r("Initializing ORM database table schemas\n");
        $this->orm->update_tables();
        print_r("Initializing database contents\n");
        $this->populateTables();
        print_r("Please create the admin user now\n");
        $this->createInitialUser();
    }

    public function update() {
        $this->cache->disable();
        $this->cache->disable_debug();
        $this->db->disable_debug();
        $this->orm->update_tables();
        $this->initializeLegacyTables();
        $this->updateLegacyTables();
    }

    public function upgrade() {
        $this->update();
        foreach ($this->table_migrations as $name => $migration) {
            $this->performMigration($name, $migration);
        }
    }

    protected function initializeLegacyTables() {
        $this->db->raw_query('SET FOREIGN_KEY_CHECKS=0');
        foreach (glob($this->master->application_path."/../tablespecs/*.sql") as $tablespec) {
            $SQLTablespec = file_get_contents($tablespec);
            $this->db->pdo->exec($SQLTablespec);
        }
    }

    protected function updateLegacyTables() {
        foreach (glob($this->master->application_path."/../tablespecs/*.sql") as $tablespec) {
            $SQLTablespec = file_get_contents($tablespec);
            $path_parts = pathinfo($tablespec);
            $columns = $this->master->db->raw_query("SHOW COLUMNS FROM `{$path_parts['filename']}`")->fetchAll(\PDO::FETCH_NUM);

            $line = strtok($SQLTablespec, PHP_EOL);
            while ($line !== false) {
                if (preg_match('/^CREATE TABLE IF NOT EXISTS/', $line) == 1) {
                    $line = strtok(PHP_EOL);
                    break;
                  }
                $line = strtok(PHP_EOL);
            }
            // Columns
            while ($line !== false) {
                if (preg_match('/^\s*`(?<name>.*?)`\s+(?<type>.*?)(,)?$/', $line, $column) !== 1) break;
                if (!in_array($column['name'], array_column($columns, 0))) {
                    print("Adding column: {$column['name']} to legacy table {$path_parts['filename']}\n");
                    $this->db->pdo->exec("ALTER TABLE {$path_parts['filename']} ADD COLUMN {$column['name']} {$column['type']}");
                }
                $line = strtok(PHP_EOL);
            }

            // Keys
            while ($line !== false) {
                if (preg_match('/^\s*(?<command>.*?KEY\s+(`(?<name>.*?)`\s*)?\(`(?<index>.*?)`\))(,)?$/', $line, $key) !== 1) break;
                $key['index'] = explode('`,`', $key['index']);
                foreach($key['index'] as $index) {
                    if(empty($key['name'])) {
                        $name = 'PRIMARY';
                    } else {
                        $name = $key['name'];
                    }
                    // Check for existance of index
                    $keys = $this->master->db->raw_query("SHOW KEYS FROM `{$path_parts['filename']}` WHERE Column_name=:index AND Key_name=:name",
                    [':index' => $index, ':name' => $name])->fetchAll(\PDO::FETCH_NUM);
                    if (count($keys) === 0) {
                        if ($name === 'PRIMARY' && count($key['index']) > 1) {
                            $keys = $this->master->db->raw_query("SHOW KEYS FROM `{$path_parts['filename']}` WHERE Key_name='PRIMARY'")->fetchAll(\PDO::FETCH_NUM);
                            if (count($keys) > 0) {
                                $this->master->db->raw_query("ALTER TABLE {$path_parts['filename']} DROP PRIMARY KEY");
                            }
                        }
                        print("Adding index: $name to legacy table {$path_parts['filename']}\n");
                        $this->db->pdo->exec("ALTER TABLE {$path_parts['filename']} ADD {$key['command']}");
                    }
                }
                $line = strtok(PHP_EOL);
            }
        }
    }

    protected function createInitialUser() {
        // Initial user creation
        $adminUsername = $this->readline("Admin Username: ");
        $adminPassword = $this->readline("Admin Password: ");
        $adminEmail = $adminUsername."@".$this->settings->main->site_url;
        $this->auth->createUser($adminUsername, $adminPassword, $adminEmail);

        // Won't work yet.
        //$adminUserID = $this->users->get('Username = :username', [':username'=>$adminUsername])->ID;
        //$adminAccount = $this->users->load($adminUserID);
        //$adminAccount->PermissionID = 1;
        //$this->users->save($adminAccount);
    }

    protected function populateTables() {
          foreach (glob($this->master->application_path."/../public/static/styles/*/meta.ini") as $stylespec) {
            $stylespec = parse_ini_file($stylespec);
            if($this->db->raw_query("SELECT COUNT(*) FROM stylesheets WHERE Name = :name", [':name' => $stylespec['name']])->fetchColumn() == 0) {
                $style = new Stylesheet();
                $style->Name = $stylespec['name'];
                $style->Description = $stylespec['description'];
                $style->Default = $stylespec['default'];
                $this->styles->save($style);
            }
        }

        if($this->db->raw_query("SELECT COUNT(*) FROM permissions")->fetchColumn() == 0) {
            $adminClass = new Permission();
            $adminClass->ID    = 1;
            $adminClass->Name  = 'Admin';
            $adminClass->Level = 1000;
            $adminClass->Color = 'FF0000';
            $adminClass->MaxSigLength = 0;
            $adminClass->MaxAvatarWidth = 0;
            $adminClass->MaxAvatarHeight = 0;
            $adminClass->DisplayStaff = 1;
            $adminClass->IsUserClass = 1;
            $adminClass->isAutoPromote = 0;

            $adminClass->Values = serialize(['admin_manage_permissions' => 1]);
            $this->permissions->save($adminClass);
        }
    }


    protected $table_migrations = [
        # Order matters!
        'users' => [
            'count_query' => "SELECT COUNT(*) FROM `users_main` AS um LEFT JOIN `users` AS u ON um.ID = u.ID WHERE u.ID IS NULL OR u.EmailID=0",
            'id_query' => "SELECT um.`ID` FROM `users_main` AS um LEFT JOIN `users` AS u ON um.ID = u.ID WHERE u.ID IS NULL OR u.EmailID=0 ORDER BY um.ID LIMIT 1000",
        ],
        'emails' => [
            'count_query' => "SELECT COUNT(*) FROM `users_history_emails` AS uhe LEFT JOIN `emails` AS e on uhe.`Email` = e.`Address` WHERE e.`Address` IS NULL AND uhe.`Email` != ''",
            'id_query' => "SELECT uhe.`Email` FROM `users_history_emails` AS uhe LEFT JOIN `emails` AS e on uhe.`Email` = e.`Address` WHERE e.`Address` IS NULL AND uhe.`Email` != '' ORDER BY uhe.`UserID` LIMIT 1000",
        ],
        'torrents' => [
            'count_query' => "SELECT COUNT(*) FROM `torrents_files` WHERE FROM_BASE64(File) LIKE '%BENCODE_DICT%' OR FROM_BASE64(File) LIKE '%BENCODE_LIST%'",
            'id_query' => "SELECT TorrentID FROM `torrents_files` WHERE FROM_BASE64(File) LIKE '%BENCODE_DICT%' OR FROM_BASE64(File) LIKE '%BENCODE_LIST%' ORDER BY TorrentID LIMIT 1000",
        ],
        'peers' => [
            'count_query' => "SELECT COUNT(*) FROM `xbt_peers_history` WHERE ipv4 IS NULL AND ipv6 IS NULL",
            'id_query' => "SELECT id FROM `xbt_peers_history` WHERE ipv4 IS NULL AND ipv6 IS NULL ORDER BY id LIMIT 1000",
        ],
        'snatches' => [
            'count_query' => "SELECT COUNT(*) FROM `xbt_snatched` WHERE ipv4 IS NULL AND ipv6 IS NULL",
            'id_query' => "SELECT uid, fid FROM `xbt_snatched` WHERE ipv4 IS NULL AND ipv6 IS NULL LIMIT 1000",
        ],
    ];

    public function performMigration($name, $migration) {
        list($rowCount) = $this->db->raw_query($migration['count_query'])->fetch();
        if ($rowCount == 0) {
            return;
        } else {
          $rowCount = number_format($rowCount);
        }
        print("Migrating {$name}, number of records: {$rowCount}\n");

        while (True) {
            $results = $this->db->raw_query($migration['id_query'])->fetchAll();
            if (count($results) == 0) {
                print("\nDone migrating {$name}.\n");
                break;
            }
            foreach ($results as $result) {
                $success = $this->migrateEntity($name, $migration, $result);
                if (!$success) break(2);
            }
        }
    }

    public function migrateEntity($name, $migration, $result) {
        switch ($name) {
            case 'users':
                list($ID) = $result;
                return $this->migrateUser($ID);
                break;
            case 'emails':
                list($ID) = $result;
                return $this->migrateEmail($ID);
                break;
            case 'torrents':
                list($ID) = $result;
                return $this->migrateTorrent($ID);
                break;
            case 'peers':
                list($ID) = $result;
                return $this->migratePeers($ID);
                break;
            case 'snatches':
                list($uid, $fid) = $result;
                return $this->migrateSnatches($uid, $fid);
                break;
        }
    }

    public function migrateUser($ID) {
        print("Migrating user ID {$ID}\r");
        $user = $this->users->get('ID = :id', [':id' => $ID]);
        $oldUser = $this->db->raw_query("SELECT um.`ID`, um.`Username`, um.`PassHash`, um.`Secret`, um.`Email`, um.`IP` FROM `users_main` AS um WHERE um.`ID` = ?", [$ID])->fetch();
        if (!strlen($oldUser['Username'])) {
            throw new SystemError("Found invalid old user: ID {$ID}");
        }
        if (!$user) {
            $user = new User();

            // Handle email migration
            if (!empty($oldUser['Email'])) {
                $parts = explode('@', $oldUser['Email']);
                if (count($parts) != 2) {
                    $oldUser['Email'] = $oldUser['Email']."@".$this->settings->main->site_url;
                }
                $email = $this->emailManager->newEmail(intval($ID), $oldUser['Email']);
            } else {
                $email = $this->emailManager->newEmail(intval($ID), $oldUser['Username']."@".$this->settings->main->site_url);
            }
            $user->EmailID = $email->ID;

            // Handle IP migration
            $ip = $this->ips->get_or_new($oldUser['IP']);
            if ($ip !== false) {
                $ip->LastUserID = $ID;
                $this->ips->save($ip);
                $user->IPID = $ip->ID;
            }

            $encodedSecret = base64_encode($oldUser['Secret']);
            $encodedHash   = base64_encode(hex2bin($oldUser['PassHash']));

            $user->ID = $ID;
            $user->Username = $oldUser['Username'];
            $user->Password = "\$salted-md5\${$encodedSecret}\${$encodedHash}";
            $this->users->save($user);
        }

        $email = $this->emails->load($user->EmailID);
        if (!$email) {
            $email = $this->emailManager->newEmail(intval($ID), $oldUser['Email']);
            $user->EmailID = $email->ID;
            $this->users->save($user);
        }
        return true;
    }

    public function migrateEmail($Email) {
        print("Migrating email historical address {$Email}\r");
        $email = $this->emails->get('Address = :email', [':email'=>$Email]);
        $oldEmail = $this->db->raw_query("SELECT uhe.`UserID`, uhe.`Email`, uhe.`Time` FROM `users_history_emails` AS uhe WHERE uhe.`Email` = ?", [$Email])->fetch();
        if (!$email) {
            $email = $this->emailManager->newEmail($oldEmail['UserID'], $oldEmail['Email']);
            $email->Changed = $oldEmail['Time'];
            $this->emails->save($email);
        }
        return true;
    }

    // Super hacky
    public function migrateTorrent($ID) {
        print("Migrating torrent ID {$ID}\r");
        $this->db->raw_query(
            "UPDATE torrents_files
                SET File=TO_BASE64(
                            REPLACE(
                                REPLACE(
                                    FROM_BASE64(File),
                                    'O:12:\"BENCODE_LIST\"',
                                    'O:28:\"Luminance\\\\Legacy\\\\BencodeList\"'
                                ),
                                'O:12:\"BENCODE_DICT\"',
                                'O:28:\"Luminance\\\\Legacy\\\\BencodeDict\"'
                            )
                        )
              WHERE TorrentID=:torrentid",
              [':torrentid' => $ID]);
        return true;
    }

    public function migratePeers($ID) {
        print("Migrating peer record ID {$ID}\r");
        try {
            $this->db->raw_query("UPDATE xbt_peers_history SET ipv4=INET6_ATON(ip) WHERE id=:id", [':id' => $ID]);
        } catch(\PDOException $e) {
            print("Error encountered - exiting migration!\n");
            return false;
        }
        return true;
    }

    public function migrateSnatches($uid, $fid) {
        print("Migrating snatch record UserID {$uid}, TorrentID {$fid}\r");
        try {
            $this->db->raw_query("UPDATE xbt_snatched SET ipv4=INET6_ATON(ip) WHERE uid=:uid AND fid=:fid", [':uid' => $uid, ':fid' => $fid]);
        } catch(\PDOException $e) {
            print("Error encountered - exiting migration!\n");
            return false;
        }
        return true;
    }
}
