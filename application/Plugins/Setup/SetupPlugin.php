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
use Luminance\Entities\Permission;
use Luminance\Entities\Stylesheet;
use Luminance\Entities\Restriction;

class SetupPlugin extends Plugin {

    public $routes = [
        [ 'CLI',  'configure/**',     0, 'configure'   ], // Configure can take a path to a script
        [ 'CLI',  'install',          0, 'install'     ],
        [ 'CLI',  'update',           0, 'update'      ],
        [ 'CLI',  'upgrade',          0, 'upgrade'     ],
        [ 'CLI',  'fixUser/**',       0, 'migrateUser' ],
        [ 'CLI',  'remove',           0, 'remove'      ],
        [ 'CLI',  'fixForums',        0, 'fixForums'   ],
        [ 'CLI',  'decrypt/*',        0, 'decrypt'     ],
    ];

    protected static $useRepositories = [
        'users'        => 'UserRepository',
        'emails'       => 'EmailRepository',
        'ips'          => 'IPRepository',
        'permissions'  => 'PermissionRepository',
        'styles'       => 'StylesheetRepository',
        'restrictions' => 'RestrictionRepository',
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
        $master->prependRoute([ 'CLI', 'setup/**',     0, 'plugin', 'Setup'              ]);
        $master->prependRoute([ 'CLI', 'configure/**', 0, 'plugin', 'Setup', 'configure' ]);
        $master->prependRoute([ 'CLI', 'install',      0, 'plugin', 'Setup', 'install'   ]);
        $master->prependRoute([ 'CLI', 'update',       0, 'plugin', 'Setup', 'update'    ]);
        $master->prependRoute([ 'CLI', 'upgrade',      0, 'plugin', 'Setup', 'upgrade'   ]);
        $master->prependRoute([ 'CLI', 'decrypt/*',    0, 'plugin', 'Setup', 'decrypt'   ]);
    }

    protected function readline($prompt) {
        echo $prompt;
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        return trim($line);
    }

    public function configure() {
        $filename = implode('/', func_get_args());
        // Convert Gazelle config to Luminance
        if (!empty($filename)) {
            print("Migrating config file to settings.ini\n");
            @include($filename); // Ignore errors
        } else {
            // Important site related stuff
            define('SITE_NAME',          $this->readline("Site name: "));
            define('NONSSL_SITE_URL',    $this->readline("Site FQDN: "));
            define('SSL_SITE_URL',       $this->readline("Site TLS FQDN: "));

            // Database credentials
            define('SQLDB',              $this->readline("Database name: "));
            define('SQLLOGIN',           $this->readline("Database username: "));
            define('SQLPASS',            $this->readline("Database password: "));

            // Auto generated keys
            define('ENCKEY',             $this->crypto->random_string(32));
            define('RSS_HASH',           $this->crypto->random_string(32));
            define('TRACKER_SECRET',     $this->crypto->random_string(32));
            define('TRACKER_REPORTKEY',  $this->crypto->random_string(32));
        }
        $settings = $this->settings->get_legacy_constants();
        $this->settings->generateConfig($settings);
        print("Please open settings.ini and manually check its contents.\n\n");
    }

    public function install() {
        print_r("Initializing legacy database table schemas\n");
        $this->updateLegacyTables();
        print_r("Initializing ORM database table schemas\n");
        $this->orm->update_tables();
        print_r("Initializing database contents\n");
        $this->populateTables();
        print_r("Please create the admin user now\n");
        $this->createInitialUser();
    }

    public function remove() {
        # Debug only!
        if ($this->settings->site->debug_mode) {
            $this->orm->drop_tables();
        }
    }

    public function update() {
        $this->cache->disable();
        $this->cache->disable_debug();
        $this->db->disable_debug();
        $this->orm->update_tables();
        $this->updateLegacyTables();
    }

    public function upgrade() {
        $this->update();
        //$this->deduplicate();
        foreach ($this->table_migrations as $name => $migration) {
            $this->performMigration($name, $migration);
        }
    }

    protected function deduplicate() {
        $this->db->raw_query("set session old_alter_table=1");
        $this->db->raw_query("ALTER IGNORE TABLE xbt_snatched ADD UNIQUE INDEX `dedupe` (`fid`, `uid`)");
        $this->db->raw_query("ALTER TABLE xbt_snatched DROP INDEX `dedupe`");
    }

    protected function updateLegacyTables() {
        foreach (glob($this->master->application_path."/../tablespecs/*.sql") as $tablespec) {
            # Fetch the SQL and pass to ORM to do the heavy lifting
            $sql = file_get_contents($tablespec);
            $this->orm->update_tables($sql);
        }
    }

    protected function createInitialUser() {
        // Initial user creation
        $adminUsername = $this->readline("Admin Username: ");
        $adminPassword = $this->readline("Admin Password: ");
        $adminEmail = $adminUsername."@".$this->settings->main->site_url;
        $this->auth->createUser($adminUsername, $adminPassword, $adminEmail);

        // Won't work yet.
        $adminUserID = $this->users->get('Username = :username', [':username'=>$adminUsername])->ID;
        $adminAccount = $this->users->load($adminUserID);
        $adminAccount->PermissionID = 1;
        $this->users->save($adminAccount);
    }

    protected function populateTables() {
        foreach (glob($this->master->application_path."/../public/static/styles/*/meta.ini") as $stylespec) {
            $stylespec = parse_ini_file($stylespec);
            if ($this->db->raw_query("SELECT COUNT(*) FROM stylesheets WHERE Name = :name", [':name' => $stylespec['name']])->fetchColumn() == 0) {
                $style = new Stylesheet();
                $style->Name = $stylespec['name'];
                $style->Description = $stylespec['description'];
                $style->Default = $stylespec['default'];
                $this->styles->save($style);
            }
        }

        if ($this->db->raw_query("SELECT COUNT(*) FROM permissions")->fetchColumn() == 0) {
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

            // Generate an array with every permission set
            include_once($this->master->application_path.'/Legacy/classes/permissions_form.php');
            $adminClass->Values = serialize(array_fill_keys(array_keys($PermissionsArray), '1'));
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
            'count_query' => "SELECT COUNT(*) FROM `torrents_files` WHERE `Version` < 1",
            'id_query' => "SELECT TorrentID FROM `torrents_files` WHERE `Version` < 1 LIMIT 1000",
        ],
        'peers' => [
            'count_query' => "SELECT COUNT(*) FROM `xbt_peers_history` WHERE ipv4 IS NULL AND ipv6 IS NULL",
            'id_query' => "SELECT id FROM `xbt_peers_history` WHERE ipv4 IS NULL AND ipv6 IS NULL ORDER BY id LIMIT 1000",
        ],
        'snatches' => [
            'count_query' => "SELECT COUNT(*) FROM `xbt_snatched` WHERE ipv4 IS NULL AND ipv6 IS NULL AND INET6_ATON(IP) IS NOT NULL",
            'id_query' => "SELECT uid, fid FROM `xbt_snatched` WHERE ipv4 IS NULL AND ipv6 IS NULL AND INET6_ATON(IP) IS NOT NULL ORDER BY uid,fid,tstamp LIMIT 1000",
        ],
        'ip bans' => [
            'count_query' => "SELECT COUNT(*) FROM `ip_bans`",
            'id_query' => "SELECT ID FROM `ip_bans` LIMIT 1000",
        ],
        'email times' => [
            'count_query' => "SELECT COUNT(*) FROM emails JOIN users_history_emails AS uhe ON uhe.Email = emails.Address WHERE Created='0000-00-00 00:00:00' or Created IS NULL",
            'id_query' => "SELECT emails.ID FROM emails JOIN users_history_emails AS uhe ON uhe.Email = emails.Address WHERE Created='0000-00-00 00:00:00' or Created IS NULL LIMIT 1000",
        ],
        'warnings' => [
            'count_query' => "SELECT COUNT(*) FROM users_info WHERE Warned >= NOW()",
            'id_query' => "SELECT UserID FROM users_info WHERE Warned >= NOW() LIMIT 1000",
        ],
        'restrictions' => [
            'count_query' => "SELECT COUNT(*) FROM users_info WHERE DisableAvatar='1' OR DisableInvites='1' OR DisablePosting='1' OR DisableForums='1' OR DisableTagging='1' OR DisableUpload='1' OR DisablePM='1' OR DisableTorrentSig='1' OR DisableSignature ='1'",
            'id_query' => "SELECT UserID FROM users_info WHERE DisableAvatar='1' OR DisableInvites='1' OR DisablePosting='1' OR DisableForums='1' OR DisableTagging='1' OR DisableUpload='1' OR DisablePM='1' OR DisableTorrentSig='1' OR DisableSignature ='1' LIMIT 1000",
        ],
    ];

    public function performMigration($name, $migration) {
        try {
            list($rowCount) = $this->db->raw_query($migration['count_query'])->fetch();
        } catch (\PDOException $e) {
            # assume exception means that the table no longer exists
            return;
        }
        if ($rowCount == 0) {
            return;
        } else {
            $rowCount = number_format($rowCount);
        }
        print("Migrating {$name}, number of records: {$rowCount}\n");

        while (true) {
            $results = $this->db->raw_query($migration['id_query'])->fetchAll();
            if (count($results) == 0) {
                print("\nDone migrating {$name}.\n");
                break;
            }
            foreach ($results as $result) {
                try {
                    # Run the migration as a transaction as it can touch multiple tables
                    $this->db->begin_transaction();
                    $success = $this->migrateEntity($name, $migration, $result);
                    $this->db->commit_transaction();
                } catch (\Exception $e) {
                    $this->db->rollback_transaction();
                    print('Failed migration\n');
                    throw $e;
                }
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
            case 'ip bans':
                list($ID) = $result;
                return $this->migrateIPBan($ID);
                break;
            case 'email times':
                list($ID) = $result;
                return $this->updateEmailTime($ID);
                break;
            case 'warnings':
                list($ID) = $result;
                return $this->updateWarnings($ID);
                break;
            case 'restrictions':
                list($ID) = $result;
                return $this->updateRestrictions($ID);
                break;
        }
    }

    public function migrateUser($ID) {
        print("Migrating user ID {$ID}\r");
        $user = $this->users->get('ID = :id', [':id' => $ID]);
        $oldUser = $this->db->raw_query("SELECT * FROM `users_main` AS um WHERE um.`ID` = ?", [$ID])->fetch();
        if (!strlen($oldUser['Username'])) {
            throw new SystemError("Found invalid old user: ID {$ID}");
        }
        if (!$user) {
            $user = new User();

            // Handle email migration
            try {
                if (!empty($oldUser['Email'])) {
                    $parts = explode('@', $oldUser['Email']);
                    if (count($parts) != 2) {
                        $oldUser['Email'] = $oldUser['Email']."@".$this->settings->main->site_url;
                    }
                    $email = $this->emailManager->newEmail(intval($ID), $oldUser['Email']);
                    $email->setFlags(Email::IS_DEFAULT);
                    $this->emails->save($email);
                } else {
                    $email = $this->emailManager->newEmail(intval($ID), $oldUser['Username']."@".$this->settings->main->site_url);
                    $email->setFlags(Email::IS_DEFAULT);
                    $this->emails->save($email);
                }
            } catch (UserError $e) {
                $email = $this->emailManager->newEmail(intval($ID), $oldUser['Username']."@".$this->settings->main->site_url);
                $email->setFlags(Email::IS_DEFAULT);
                $this->emails->save($email);
            }

            $user->EmailID = $email->ID;

            // Handle IP migration
            if (!empty($oldUser['IP'])) {
                $ip = $this->ips->get_or_new($oldUser['IP']);
                if ($ip instanceof IP) {
                    $ip->LastUserID = $ID;
                    $this->ips->save($ip);
                    $user->IPID = $ip->ID;
                }
            }

            $user->ID = $ID;
            $user->Username = $oldUser['Username'];

            // Not all password schemes salted hashes
            if (!empty($oldUser['Secret'])) {
                $encodedSecret = base64_encode($oldUser['Secret']);
            }

            // If bcrypt then it won't be Hex
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
                $user->twoFactorSecret = $this->crypto->encrypt($oldUser['2fa_secret']);
            }

            $this->users->save($user);
        }

        try {
            $email = $this->emails->load($user->EmailID);
            if (!$email) {
                $email = $this->emailManager->newEmail(intval($ID), $oldUser['Email']);
                $email->setFlags(Email::IS_DEFAULT);
                $this->emails->save($email);
                $user->EmailID = $email->ID;
                $this->users->save($user);
            }
        } catch (UserError $e) {}
    }

    public function migrateEmail($Email) {
        print("Migrating email historical address {$Email}              \r");
        $oldEmail = $this->db->raw_query("SELECT uhe.`UserID`, uhe.`Email`, uhe.`Time`, uhe.`IP` FROM `users_history_emails` AS uhe WHERE uhe.`Email` = ? ORDER BY Time ASC LIMIT 1", [$Email])->fetch();
        try {
            $email = $this->emailManager->newEmail($oldEmail['UserID'], $oldEmail['Email']);
            $IP = $this->ips->get_or_new($oldEmail['IP']);
            $this->ips->save($IP);
            $email->IPID = $IP->ID;
            $email->Changed = $oldEmail['Time'];
            $email->Created = $oldEmail['Time'];
            $this->emails->save($email);
            $this->db->raw_query("DELETE FROM users_history_emails WHERE Email = ?", [$Email]);
            unset($IP);
            unset($email);
        } catch (Error $e) {
            $this->db->raw_query("DELETE FROM users_history_emails WHERE Email = ?", [$Email]);
        }
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
                        ),
                    Version=1
              WHERE TorrentID=:torrentid",
            [':torrentid' => $ID]
        );
    }

    public function migratePeers($ID) {
        print("Migrating peer record ID {$ID}\r");
        $this->db->raw_query("UPDATE xbt_peers_history SET ipv4=INET6_ATON(ip) WHERE id=:id", [':id' => $ID]);
    }

    public function migrateSnatches($uid, $fid) {
        print("Migrating snatch record UserID {$uid}, TorrentID {$fid}\r");
        $this->db->raw_query("UPDATE xbt_snatched SET ipv4=INET6_ATON(IP) WHERE uid=:uid AND fid=:fid AND ipv4 IS NULL and ipv6 IS NULL AND INET6_ATON(IP) IS NOT NULL", [':uid' => $uid, ':fid' => $fid]);
    }

    public function migrateIPBan($ID) {
        $oldBan = $this->db->raw_query("SELECT INET_NTOA(`FromIP`) AS `FromIP`, INET_NTOA(`ToIP`) AS `ToIP`, `UserID`, `StaffID`, `EndTime`, `Reason` FROM `ip_bans` WHERE `ID` = ?", [$ID])->fetch();
        $range = \IPLib\Factory::rangeFromBoundaries($oldBan['FromIP'], $oldBan['ToIP']);
        print("Migrating IP ban {$ID}: {$range}                  \r");
        $newBan = $this->ips->ban((string) $range, $oldBan['Reason']);
        $newBan->LastUserID   = $oldBan['UserID'];
        $newBan->ActingUserID = $oldBan['StaffID'];
        if (!is_null($oldBan['EndTime']) && $oldBan['EndTime'] != '0000-00-00 00:00:00') {
            $newBan->BannedUntil = $oldBan['EndTime'];
        }
        $this->ips->save($newBan);
        $this->db->raw_query("DELETE FROM `ip_bans` WHERE `ID` = ?", [$ID]);
        return true;
    }

    public function updateEmailTime($ID) {
        print("Update Email Record {$ID}                  \r");
        try {
            $email = $this->emails->load($ID);
            $oldEmail = $this->db->raw_query("SELECT `ID`, Time FROM users_history_emails WHERE Email = ? ORDER BY Time ASC LIMIT 1", [$email->Address])->fetch();
            $email->Created = $oldEmail->Time;
            $this->emails->save($email);
            $this->db->raw_query("DELETE FROM users_history_emails WHERE ID = ?", [$oldEmail->ID]);
        } catch (Error $e) {
            $this->db->raw_query("DELETE FROM users_history_emails WHERE Email = ?", [$oldEmail->ID]);
        }
    }

    public function updateWarnings($ID) {
        print("Update User Warning Record {$ID}                  \r");
        list($Warned) = $this->db->raw_query("SELECT Warned FROM users_info WHERE UserID = ?", [$ID])->fetch();
        $restriction = new Restriction;
        $restriction->setFlags(Restriction::WARNED);
        $restriction->UserID  = $ID;
        $restriction->StaffID = 0;
        $restriction->Created = new \DateTime();
        $restriction->Expires = $Warned;
        $restriction->Comment = "Migrated from Gazelle";
        $this->restrictions->save($restriction);
        $this->db->raw_query("UPDATE users_info SET Warned=NULL WHERE UserID = ?", [$ID]);
    }

    public function updateRestrictions($ID) {
        print("Update User Restriction Record {$ID}                  \r");
        $oldRestrictions = $this->db->raw_query("SELECT DisableAvatar, DisableInvites, DisablePosting, DisableForums, DisableTagging, DisableUpload, DisablePM, DisableTorrentSig, DisableSignature FROM users_info WHERE UserID = ?", [$ID])->fetch(\PDO::FETCH_ASSOC);

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
            if ($notAllowed == '1') {
                $restriction->setFlags($decode[$oldRestriction]);
            }
        }

        $restriction->UserID  = $ID;
        $restriction->StaffID = 0;
        $restriction->Created = new \DateTime();
        $restriction->Comment = "Migrated from Gazelle";
        $this->restrictions->save($restriction);
        $oldRestrictions = $this->db->raw_query("UPDATE users_info SET DisableAvatar='0', DisableInvites='0', DisablePosting='0', DisableForums='0', DisableTagging='0', DisableUpload='0', DisablePM='0', DisableTorrentSig='0', DisableSignature='0' WHERE UserID = ?", [$ID])->fetch(\PDO::FETCH_ASSOC);
    }

    public function fixForums() {
        $threadIDs = $this->db->raw_query("SELECT ID FROM forums_topics")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($threadIDs as $threadID) {
            print("Updating thread ${threadID}\n");
            $lastPostID = $this->db->raw_query("SELECT ID FROM forums_posts WHERE TopicID=${threadID} ORDER BY AddedTime DESC LIMIT 1")->fetch(\PDO::FETCH_COLUMN);
            $this->db->raw_query("UPDATE forums_topics SET LastPostID=${lastPostID} WHERE ID=${threadID}");
        }
    }

    public function decrypt($data) {
        var_dump($data);
        var_dump($this->crypto->decrypt($data, 'default', true));
    }

}
