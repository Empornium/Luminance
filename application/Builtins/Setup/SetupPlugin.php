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
        'orm'           => 'ORM',
        'settings'      => 'Settings',
        'emailManager'  => 'EmailManager',
        'auth'          => 'Auth',
    ];

    protected $table_migrations = [
        # Order matters!
        'users' => [
            'count_query' => "SELECT COUNT(*) FROM `users_main` AS um LEFT JOIN `users` AS u ON um.ID = u.ID WHERE u.ID IS NULL OR u.EmailID=0",
            'id_query' => "SELECT um.`ID` FROM `users_main` AS um LEFT JOIN `users` AS u ON um.ID = u.ID WHERE u.ID IS NULL OR u.EmailID=0 ORDER BY um.ID LIMIT 1000",
        ],
        'emails' => [
            'count_query' => "SELECT COUNT(*) FROM `users_history_emails` AS uhe LEFT JOIN `emails` AS e on uhe.`Email` = e.`Address` WHERE e.`Address` IS NULL",
            'id_query' => "SELECT uhe.`Email` FROM `users_history_emails` AS uhe LEFT JOIN `emails` AS e on uhe.`Email` = e.`Address` WHERE e.`Address` IS NULL ORDER BY uhe.`UserID` LIMIT 1000",
        ],
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
        $this->orm->update_tables();
    }

    public function upgrade($filename) {
        $this->orm->update_tables();
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

    public function performMigration($name, $migration) {
        list($rowCount) = $this->db->raw_query($migration['count_query'])->fetch();
        if ($rowCount == 0) {
            return;
        }
        print("Migrating {$name}, number of records: {$rowCount}\n");

        while (True) {
            if (time() >= $this->endTime) {
                break;
            }
            $results = $this->db->raw_query($migration['id_query'])->fetchAll();
            if (count($results) == 0) {
                print("Done migrating {$name}.\n");
                break;
            }
            foreach ($results as $result) {
                list($ID) = $result;
                $this->migrateEntity($name, $migration, $ID);
            }
        }
    }

    public function migrateEntity($name, $migration, $ID) {
        switch ($name) {
            case 'users':
                $this->migrateUser($ID);
                break;
            case 'emails':
                $this->migrateEmail($ID);
                break;
        }
    }

    public function migrateEmail($Email) {
        print("Migrating email historical address {$Email}\n");
        $email = $this->emails->get('Address = :email', [':email'=>$Email]);
        $oldEmail = $this->db->raw_query("SELECT uhe.`UserID`, uhe.`Email`, uhe.`Time` FROM `users_history_emails` AS uhe WHERE uhe.`Email` = ?", [$Email])->fetch();
        if (!$email) {
            $email = $this->emailManager->newEmail($oldEmail['UserID'], $oldEmail['Email']);
            $email->Changed = $oldEmail['Time'];
            $this->emails->save($email);
        }
    }

    public function migrateUser($ID) {
        print("Migrating user ID {$ID}\n");
        $user = $this->users->get('ID = :id', [':id' => $ID]);
        $oldUser = $this->db->raw_query("SELECT um.`ID`, um.`Username`, um.`PassHash`, um.`Secret`, um.`Email`, um.`IP` FROM `users_main` AS um WHERE um.`ID` = ?", [$ID])->fetch();
        if (!strlen($oldUser['Username'])) {
            throw new SystemError("Found invalid old user: ID {$ID}");
        }
        if (!$user) {
            $user = new User();
            $email = $this->emailManager->newEmail(intval($ID), $oldUser['Email']);
            $ip = $this->ips->get_or_new($oldUser['IP']);
            $ip->LastUserID = $ID;
            $this->ips->save($ip);

            $encodedSecret = base64_encode($oldUser['Secret']);
            $encodedHash   = base64_encode(hex2bin($oldUser['PassHash']));

            $user->ID = $ID;
            $user->Username = $oldUser['Username'];
            $user->Password = "\$salted-md5\${$encodedSecret}\${$encodedHash}";
            $user->EmailID = $email->ID;
            $user->IPID = $ip->ID;
            $this->users->save($user);
        }
    }
}
