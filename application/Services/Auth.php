<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;
use Luminance\Errors\ConfigurationError;
use Luminance\Errors\SystemError;
use Luminance\Errors\UserError;
use Luminance\Errors\AuthError;
use Luminance\Errors\InternalError;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\UnauthorizedError;
use Luminance\Entities\Email;
use Luminance\Entities\Session;
use Luminance\Entities\Style;
use Luminance\Entities\User;
use Luminance\Entities\IP;
use Luminance\Services\Crypto;

class Auth extends Service {

    protected static $defaultOptions = [
        'UsersLimit'             => ['value' => 5000,  'section' => 'users',    'displayRow' => 1, 'displayCol' => 1, 'type' => 'int',  'description' => 'Maximum users'],
        'UsersStartingUpload'    => ['value' => 500,   'section' => 'users',    'displayRow' => 2, 'displayCol' => 1, 'type' => 'int',  'description' => 'Initial Upload Credit (MiB)'],
        'UsersStartingInvites'   => ['value' => 0,     'section' => 'users',    'displayRow' => 2, 'displayCol' => 2, 'type' => 'int',  'description' => 'Initial Invites'],
        'UsersStartingPFLDays'   => ['value' => 0,     'section' => 'users',    'displayRow' => 2, 'displayCol' => 3, 'type' => 'int',  'description' => 'Initial Personal Freeleech (days)'],
        'UsersStartingFLTokens'  => ['value' => 0,     'section' => 'users',    'displayRow' => 2, 'displayCol' => 4, 'type' => 'int',  'description' => 'Initial Freeleech/Doubleseed Tokens'],
        'HaveIBeenPwned'         => ['value' => false, 'section' => 'security', 'displayRow' => 1, 'displayCol' => 1, 'type' => 'bool', 'description' => 'Check passwords against HIBP api'],
        'PasswordBlacklist'      => ['value' => false, 'section' => 'security', 'displayRow' => 1, 'displayCol' => 2, 'type' => 'bool', 'description' => 'Disallow blacklisted passwords'],
        'MinPasswordLength'      => ['value' => 0,     'section' => 'security', 'displayRow' => 1, 'displayCol' => 3, 'type' => 'int',  'description' => 'Minimum password length'],
        'DisabledHits'           => ['value' => false, 'section' => 'security', 'displayRow' => 1, 'displayCol' => 4, 'type' => 'bool', 'description' => 'Log disabled accounts logins'],
        'HaveIBeenPwnedPercent'  => ['value' => 100,   'section' => 'security', 'displayRow' => 2, 'displayCol' => 1, 'type' => 'int',  'description' => 'Chance in percent that HIBP is checked for a user'],
    ];

    protected static $useRepositories = [
        'permissions' => 'PermissionRepository',
        'sessions'    => 'SessionRepository',
        'stylesheets' => 'StylesheetRepository',
        'users'       => 'UserRepository',
        'ips'         => 'IPRepository',
        'emails'      => 'EmailRepository',
    ];

    protected static $useServices = [
        'crypto'        => 'Crypto',
        'cache'         => 'Cache',
        'guardian'      => 'Guardian',
        'db'            => 'DB',
        'secretary'     => 'Secretary',
        'tracker'       => 'Tracker',
        'options'       => 'Options',
        'emailManager'  => 'EmailManager',
        'log'           => 'Log',
        'security'      => 'Security',
    ];

    protected $SessionID = null;
    protected $UserSessions = null;
    protected $CID = null;
    protected $activeUserPermissions = null;
    protected $minUserPermissions = null;

    public $usedPermissions = [];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $this->master->request;
        $this->log = $this->master->log;
    }

    public function checkSession() {
        $this->secretary->checkClient();
        $this->request->authLevel = 0;

        if (isset($this->request->cookie['sid'])) {
            $this->cookieAuth();
        }

        if (isset($this->request->user->twoFactorSecret)
            && !$this->request->session->getFlag(SESSION::TWO_FACTOR)
            && implode('/', $this->request->path) != 'twofactor/login'
            && implode('/', $this->request->path) != 'twofactor/recover') {
            throw new AuthError('Two Factor Login Required', 'Two Factor Login Required', '/twofactor/login');
        }

        // Because we <3 our staff
        if ($this->isAllowed('site_disable_ip_history')) {
            $this->master->server['REMOTE_ADDR'] = '127.0.0.1';
            $this->request->ip = new IP('127.0.0.1');
        }

        // At this point, we're either authenticated or we're not; it's not going to change anymore for this request.
        if ($this->request->user &&
            (!isset($this->request->user->twoFactorSecret) || (isset($this->request->user->twoFactorSecret) && $this->request->session->getFlag(SESSION::TWO_FACTOR))) &&
            strtotime($this->request->user->legacy['LastAccess']) + 600 < time()) {
            $this->db->raw_query(
                "UPDATE users_main SET LastAccess=:lastaccess WHERE ID=:userid",
                [':lastaccess' => sqltime(), ':userid' => $this->request->user->ID]
            );

            if ($this->request->session->getFlag(Session::KEEP_LOGGED_IN)) {
                // Re-set sid cookie every 10mins
                $this->request->set_cookie('sid', $this->crypto->encrypt(strval($this->request->session->ID), 'cookie'), time()+(60*60*24)*28, true);
            }
            $this->request->session->Updated = new \DateTime();
            $this->sessions->save($this->request->session);
        }

        // Throwback, for now
        if ($this->request->user && ($this->request->user->IPID != $this->request->ip->ID)) {
            $UserID = $this->request->user->ID;
            $CurIP  = $this->ips->load($this->request->user->IPID);
            $NewIP  = $this->request->ip;

            $this->db->raw_query(
                "UPDATE users_history_ips SET EndTime=:endtime WHERE EndTime IS NULL AND UserID=:userid AND IP=:ip",
                [':endtime' => sqltime(),
                 ':userid'  => $UserID,
                 ':ip'      => (string)$CurIP]
            );
            $this->db->raw_query(
                "INSERT IGNORE INTO users_history_ips (UserID, IP, StartTime) VALUES (:userid, :newip, :starttime)",
                [':userid'    => $UserID,
                 ':newip'     => (string)$NewIP,
                 ':starttime' => sqltime()]
            );

            $this->request->user->IPID = $NewIP->ID;
            $this->users->save($this->request->user);
            $this->db->raw_query(
                "UPDATE users_main SET ipcc=:ipcc WHERE ID=:userid",
                [':ipcc'   => geoip((string) $NewIP),
                 ':userid' => $UserID]
            );

            $asn = $this->db->raw_query("SELECT ASN FROM geoip_asn WHERE INET_ATON(:ip) BETWEEN StartIP AND EndIP", [':ip' => $this->request->ip])->fetchColumn();
            if (!empty($asn)) {
                $this->db->raw_query(
                    "INSERT INTO users_history_asns VALUES(:userID, :asn, :startTime, :endTime) ON DUPLICATE KEY UPDATE EndTime=VALUES(EndTime)",
                    [':userID'    => $this->request->user->ID,
                     ':asn'       => $asn,
                     ':startTime' => sqltime(),
                     ':endTime'   => sqltime()]
                );
            }

            $this->master->repos->users->uncache($UserID);
        }
    }

    public function cookieAuth() {
        $sessionID = $this->crypto->decrypt($this->request->cookie['sid'], 'cookie');
        if (!$sessionID) {
            # Corrupted cookie - delete it!
            $this->unauthenticate();
            throw new AuthError('Corrupted session cookie', 'Unauthorized', '/login');
        }

        $session = $this->sessions->load($sessionID);
        if (!$session|| !$session->Active) {
            # Expired or invalid session - delete it!
            $this->unauthenticate();
            throw new AuthError('Expired session', 'Unauthorized', '/login');
        }

        $user = $this->users->load($session->UserID);
        if (!$user) {
            throw new SystemError("No User {$session->UserID} for Session {$session->ID}");
        }

        $lastip = $this->ips->load($session->IPID);
        if ((!$lastip || !$lastip->match(new IP($this->request->ip))) && $session->getFlag(Session::IP_LOCKED)) {
            # Locked session moved IP
            $this->unauthenticate();
            throw new AuthError('IP changed on locked session', 'IP changed on locked session', '/login');
        }

        $this->request->session = $session;
        $this->request->user = $user;
        $this->request->authLevel = 2;
        // Not sure about this yet, but let's start here
        if ($session->getFlag(Session::IP_LOCKED)) $this->request->authLevel++;
    }

    public function authenticate($username, $password, $options) {
        if (is_null($this->request->authLevel)) {
            # Make sure auth status has been checked so we can abort if the client is already logged in.
            throw new InternalError("Attempt to call authenticate() before check_auth()");
        }
        if ($this->request->authLevel >= 2) {
            throw new UserError("Already logged in");
        }
        if (empty($options['width']) || empty($options['height']) || empty($options['colordepth'])) {
            # Deny access to bots
            throw new AuthError("Bots are forbidden", "Unauthorized", "/login");
        }

        $this->secretary->updateClientInfo($options);

        $user = $this->users->get_by_username($username);
        if ($user && $this->check_password($user, $password)) {
            $user = $this->users->load($user->ID);
            if ($user->legacy['Enabled'] != '1') {
                $this->guardian->log_disabled($user, $this->request->ip);
                throw new AuthError("Account is disabled", "Unauthorized", "/disabled");
            }

            // Check if password appeared in breaches
            if ($this->security->passwordIsPwned($password, $user)) {
                throw new AuthError("Access refused", "Unauthorized", "/pwned");
            }

            // Check if password appeared in breaches
            $this->security->checkDisabledHits($user, $this->request->ip);

            $session = $this->create_session($user, $options);
            $this->request->session = $session;
            if ($session->getFlag(Session::KEEP_LOGGED_IN)) {
                $expires = time()+(60*60*24)*28;
            } else {
                $expires = 0;
            }
            $this->request->set_cookie('sid', $this->crypto->encrypt(strval($session->ID), 'cookie'), $expires, true);
        } else {
            $this->handle_login_failure($user);
            throw new AuthError("Invalid username or password", "Unauthorized", "/login");
        }

        return $session;
    }

    public function twofactor_check($user, $code, $secret = null) {
        if (is_null($secret)) {
            $secret = $this->crypto->decrypt($user->twoFactorSecret);
        }
        $ga = new \PHPGangsta_GoogleAuthenticator();
        if ($ga->verifyCode($secret, $code, 4)) {
            return true;
        } else {
            return false;
        }
    }

    public function twofactor_authenticate($user, $code) {
        if ($this->twofactor_check($user, $code)) {
            $this->request->session->setFlags(Session::TWO_FACTOR);
            $this->sessions->save($this->request->session);
        } else {
            $Attempt = $this->guardian->log_attempt('2fa', $user->ID);
            throw new AuthError('Invalid or expired code', 'Unauthorized', '/twofactor/login');
        }
    }

    public function twofactor_enable($user, $secret, $code) {
        if (!is_null($user->twoFactorSecret)) {
            throw new UserError('Two Factor Authentication already enabled');
        }
        if ($this->twofactor_check($user, $code, $secret)) {
            $user->twoFactorSecret = $this->crypto->encrypt($secret);
            $this->users->save($user);
            if ($this->request->user->ID == $user->ID) {
                $this->request->session->setFlags(Session::TWO_FACTOR);
                $this->sessions->save($this->request->session);
            }
            return true;
        } else {
            return false;
        }
    }

    public function twofactor_disable($user, $code) {
        if ($this->twofactor_check($user, $code) || $this->isAllowed('users_edit_2fa')) {
            $user->twoFactorSecret = null;
            $this->users->save($user);
            $sessions = $this->sessions->find('UserID=? AND FLAGS&?=?', [$user->ID, SESSION::TWO_FACTOR, SESSION::TWO_FACTOR]);
            foreach ($sessions as $session) {
                $session->unsetFlags(Session::TWO_FACTOR);
                $this->sessions->save($session);
            }
            return true;
        } else {
            return false;
        }
    }

    public function twofactor_createSecret() {
        $ga = new \PHPGangsta_GoogleAuthenticator();
        return $ga->createSecret();
    }

    public function unauthenticate() {
        if ($this->request->session) {
            $this->request->session->Active = false;
            $this->request->session->Updated = new \DateTime();
            $this->sessions->save($this->request->session);
        }
        $this->purge_session();
    }

    public function create_session($user, $options) {
        $session = new Session();
        $session->UserID = $user->ID;
        $session->ClientID = $this->request->client->ID;
        $session->IPID = $this->request->client->IPID;
        $session->Active = true;
        $session->Created = new \DateTime();
        $session->Updated = new \DateTime();
        $session->setFlagStatus(Session::KEEP_LOGGED_IN, $options['keeploggedin']);
        $session->setFlagStatus(Session::IP_LOCKED, $options['iplocked']);

        $this->sessions->save($session);
        $this->cache->delete_value('users_sessions_'.$user->ID);
        return $session;
    }

    public function handle_login_failure($user = null) {
        if ($user) {
            $Attempt = $this->guardian->log_attempt('login', $user->ID);
        } else {
            $Attempt = $this->guardian->log_attempt('login', '0');
        }
        $this->purge_session();
        return $Attempt;
    }

    public function purge_session() {
        if (array_key_exists('sid', $this->request->cookie)) {
            $this->request->delete_cookie('sid');
        }
    }

    public function setLegacySessionGlobals() {
        global $User, $SessionID, $UserSessions, $UserID, $Enabled, $UserStats,
            $Permissions, $LightInfo, $HeavyInfo, $LoggedUser, $Browser, $OperatingSystem, $Mobile;

        $UA = $this->master->secretary;
        $Browser = $UA->browser($_SERVER['HTTP_USER_AGENT']);
        $OperatingSystem = $UA->operating_system($_SERVER['HTTP_USER_AGENT']);
        $Mobile = false;

        $User = $this->request->user;
        $SessionID = $this->SessionID;
        $UserSessions = $this->UserSessions;

        $UserID = ($User) ? $User->ID : null;
        $Enabled = ($User) ? $User->legacy['Enabled'] : null;
        if ($this->request->user) {
            $UserStats = $this->get_user_stats($UserID);
            $Permissions = $this->get_user_permissions($User);
            $LightInfo = $User->info();
            $HeavyInfo = $User->heavy_info();
            $LoggedUser = $this->get_legacy_logged_user();
        }
    }

    public function check_login($userID, $password) {
        $user = $this->users->load($userID);
        return $this->check_password($user, $password);
    }

    public function set_password($user, $password) {
        // If we're passed a userID the load the user object
        $userID = false;
        if (is_numeric($user)) {
            $userID = $user;
            $user = $this->users->load($user);
        }

        // Handle setting the password
        if ($user && strlen($password)) {
            $user->Password = $this->create_hash_bcrypt($password);
            // Only save the user object if we loaded it
            if ($userID && is_numeric($userID)) {
                $this->users->save($user);
            }

            if (!empty($this->request->ip)) {
                // Save the password change
                $this->db->raw_query(
                    "INSERT INTO users_history_passwords
                    (UserID, ChangerIP, ChangeTime) VALUES
                    (:userid, :changerIP, :changetime)",
                    [':userid'     => $user->ID,
                     ':changerIP'  => $this->request->ip,
                     ':changetime' => sqltime()]
                );
            }
        } else {
            throw new InternalError("Invalid set_password attempt.");
        }
    }

    protected function rehash_password($user, $password) {
            # Re-hash as bcrypt
            $user->Password = $this->create_hash_bcrypt($password);
            $this->users->save($user);
            return $this->check_password($user, $password, false);
    }

    public function check_password($user, $password, $allow_rehash = true) {
        if (is_null($user->Password) || !strlen($user->Password)) {
            throw new UserError("You must set a new password.");
        }
        if (is_null($password) || !strlen($password)) {
            throw new UserError("Password cannot be empty.");
        }
        $password_fields = explode('$', $user->Password); # string should start with '$', so the first field is always an empty string
        if (count($password_fields) < 3) {
            throw new SystemError("Invalid password data.");
        }
        $password_type = $password_fields[1];
        $password_values = array_slice($password_fields, 2);
        switch ($password_type) {
            case 'salted-md5':
                $result = $this->check_password_salted_md5($password, $password_values);
                if ($result === true && $allow_rehash) {
                    $this->rehash_password($user, $password);
                }
                break;
            case 'md5':
                $result = $this->check_password_md5($password, $password_values);
                if ($result === true && $allow_rehash) {
                    $this->rehash_password($user, $password);
                }
                break;
            case 'salted-sha1':
                $result = $this->check_password_salted_sha1($password, $password_values);
                if ($result === true && $allow_rehash) {
                    $this->rehash_password($user, $password);
                }
                break;
            case 'sha1':
                $result = $this->check_password_sha1($password, $password_values);
                if ($result === true && $allow_rehash) {
                    $this->rehash_password($user, $password);
                }
                break;
            case 'bcrypt':
                $result = $this->check_password_bcrypt($password, $password_values);
                break;
            default:
                throw new SystemError("Invalid password data.");
        }
        return $result;
    }

    protected function check_password_salted_md5($password, $values) {
        list($encoded_secret, $encoded_hash) = $values;
        $secret = base64_decode($encoded_secret);
        # Use hex strings since strlen() might be risky for binary data (mbstring overload)
        $stored_hash = Crypto::bin2hex(base64_decode($encoded_hash));
        $actual_hash = md5(md5($secret) . md5($password));
        if (# Just a bunch of overly paranoid sanity checks
            !strlen($password) ||
            !strlen($secret) ||
            !strlen($stored_hash) ||
            strlen($actual_hash) != 32
        ) {
            throw new SystemError("Invalid password data.");
        }
        $result = ($actual_hash === $stored_hash);
        return $result;
    }

    protected function check_password_md5($password, $values) {
        list(, $encoded_hash) = $values;
        # Use hex strings since strlen() might be risky for binary data (mbstring overload)
        $stored_hash = Crypto::bin2hex(base64_decode($encoded_hash));
        $actual_hash = md5($password);
        if (# Just a bunch of overly paranoid sanity checks
            !strlen($password) ||
            !strlen($stored_hash) ||
            strlen($actual_hash) != 32
        ) {
            throw new SystemError("Invalid password data.");
        }
        $result = ($actual_hash === $stored_hash);
        return $result;
    }

    protected function check_password_salted_sha1($password, $values) {
        list($encoded_secret, $encoded_hash) = $values;
        $secret = base64_decode($encoded_secret);
        # Use hex strings since strlen() might be risky for binary data (mbstring overload)
        $stored_hash = Crypto::bin2hex(base64_decode($encoded_hash));
        $actual_hash = sha1(sha1($secret) . sha1($password));
        if (# Just a bunch of overly paranoid sanity checks
            !strlen($password) ||
            !strlen($secret) ||
            !strlen($stored_hash) ||
            strlen($actual_hash) != 40
        ) {
            throw new SystemError("Invalid password data.");
        }
        $result = ($actual_hash === $stored_hash);
        return $result;
    }

    protected function check_password_sha1($password, $values) {
        list(, $encoded_hash) = $values;
        # Use hex strings since strlen() might be risky for binary data (mbstring overload)
        $stored_hash = Crypto::bin2hex(base64_decode($encoded_hash));
        $actual_hash = sha1($password);
        if (# Just a bunch of overly paranoid sanity checks
            !strlen($password) ||
            !strlen($stored_hash) ||
            strlen($actual_hash) != 40
        ) {
            throw new SystemError("Invalid password data.");
        }
        $result = ($actual_hash === $stored_hash);
        return $result;
    }

    protected function create_hash_bcrypt($password) {
        # password_hash does most of the work for us here
        # we use defaults for now, which is to generate a salt automatically and use cost = 10
        $encoded_hash = password_hash($password, PASSWORD_DEFAULT);
        $hash_string = "\$bcrypt{$encoded_hash}"; # dollar sign is already included
        return $hash_string;
    }

    protected function check_password_bcrypt($password, $values) {
        $hash_string = "\$" . implode("\$", $values);
        $result = password_verify($password, $hash_string);
        return ($result === true);
    }

    public function getUserPermissions(User $user, $includeCustom = true) {
        $userPermID = $user->legacy['PermissionID'];
        $userPerms = $this->permissions->getLegacyPermission($userPermID);

        $groupPermID = $user->legacy['GroupPermissionID'];
        $groupPerms = $this->permissions->getLegacyPermission($groupPermID);

        if (!is_null($user->legacy['CustomPermissions']) && ($includeCustom === true)) {
            $customPerms = (array) unserialize($user->legacy['CustomPermissions']);
        } else {
            $customPerms = [];
        }

        $maxCollages = 0;
        $maxCollages += (array_key_exists('MaxCollages', $userPerms['Permissions'])) ? $userPerms['Permissions']['MaxCollages'] : 0;
        $maxCollages += (array_key_exists('MaxCollages', $groupPerms['Permissions'])) ? $groupPerms['Permissions']['MaxCollages'] : 0;
        $maxCollages += (array_key_exists('MaxCollages', $customPerms)) ? $customPerms['MaxCollages'] : 0;

        $userPermissions = array_merge($userPerms['Permissions'], $groupPerms['Permissions'], $customPerms, ['MaxCollages'=>$maxCollages]);
        return $userPermissions;
    }

    public function getMinUserPermissions() {
        if (is_null($this->minUserPermissions)) {
            $userPerms = $this->permissions->getLegacyPermission($this->permissions->getMinUserClassID());
            $this->minUserPermissions = $userPerms['Permissions'];
        }
        return $this->minUserPermissions;
    }

    public function getActiveUserPermissions() {
        if (is_null($this->activeUserPermissions)) {
            if (!$this->request->user) {
                return [];
            }
            $this->activeUserPermissions = $this->getUserPermissions($this->request->user);
        }
        return $this->activeUserPermissions;
    }

    protected function recordCheck($name) {
        if (empty($this->usedPermissions[$name])) {
            $this->usedPermissions[$name] = 1;
        } else {
            $this->usedPermissions[$name]++;
        }
    }

    public function isAllowed($name) {
        # Determine if something is allowed, and return the answer as a boolean.
        if (!$this->request->user) {
            return false;
        }
        $this->recordCheck($name);
        $permissions = $this->getActiveUserPermissions();
        $allowed = array_key_exists($name, $permissions) && $permissions[$name];
        return $allowed;
    }

    public function isAllowedByMinUser($name) {
        # Determine if something is allowed, and return the answer as a boolean.
        $this->recordCheck($name);
        $permissions = $this->getMinUserPermissions();
        $allowed = array_key_exists($name, $permissions) && $permissions[$name];
        return $allowed;
    }

    public function checkAllowed($name) {
        # Determine if something is allowed, and throw an exception if it's not the case.
        if (!$this->request->user) {
            throw new UnauthorizedError();
        }
        if (!$this->isAllowed($name)) {
            throw new ForbiddenError();
        }
    }

    public function checkLevel($userID) {
        $user = $this->users->load($userID);
        $userLevel = $this->permissions->load($user->legacy['PermissionID'])->Level;
        $staffLevel = $this->permissions->load($this->request->user->legacy['PermissionID'])->Level;
        if ($userLevel > $staffLevel) {
            throw new ForbiddenError();
        }
    }

    public function get_user_stats($userID) {
            $UserStats = $this->cache->get_value('user_stats_' . $userID);
        if (!is_array($UserStats)) {
            $UserStats = $this->db->raw_query(
                "SELECT Uploaded AS BytesUploaded,
                        Downloaded AS BytesDownloaded,
                        RequiredRatio, Credits as TotalCredits
                   FROM users_main WHERE ID=:userid",
                [':userid'=>$userID]
            )->fetch(\PDO::FETCH_ASSOC);
            $this->cache->cache_value('user_stats_' . $userID, $UserStats, 3600);
        }
            return $UserStats;
    }

    public function get_user_permissions($user) {
        $Permission = $this->permissions->getLegacyPermission($user->legacy['PermissionID']);
        return $Permission;
    }

    public function get_active_user() {
        return $this->request->user;
    }

    public function get_legacy_logged_user() {
        if (!$this->request->user) {
            return [];
        }
        $user = $this->request->user;
        $LoggedUser = array_merge(
            $user->heavy_info(),
            $user->info(),
            $this->permissions->getLegacyPermission($user->legacy['PermissionID']),
            $user->stats()
        );
        $LoggedUser['RSS_Auth'] = $user->legacy['RSS_Auth'];
        $LoggedUser['RatioWatch'] = $user->on_ratiowatch();

        $LoggedUser['Permissions'] = $this->getUserPermissions($user);

        $Stylesheet = $this->master->repos->stylesheets->get_by_user($user);
        $LoggedUser['StyleName'] = $Stylesheet->Name;
        return $LoggedUser;
    }

    public function legacy_enforce_login() {
        if (!$this->request->user) {
            $this->request->saveIntendedRoute();
            throw new ForbiddenError();
        }
    }

    public function createUser($username, $password, $email, $inviter = 0) {
        $this->users->checkAvailable($username);
        $this->emails->checkAvailable($email);

        $torrentPass = $this->crypto->random_string(32);
        $userCount = $this->db->raw_query("SELECT COUNT(*) FROM users_main")->fetchColumn();
        // First user is SysOp
        if ($userCount == 0) {
            $permissionID = 1; # Should be true on new installs
        } else {
            $permissionID = $this->permissions->getMinUserClassID();
        }

        if (!$permissionID) {
            throw new UserError("No userclasses have been configured.");
        }

        $user = new User();

        $enabled = '1';
        try {
            $transactionInScope = false;
            if (!$this->db->in_transaction()) {
                $this->db->begin_transaction();
                $transactionInScope = true;
            }
            $this->db->raw_query(
                "INSERT INTO users_main (Username, torrent_pass, PermissionID, Enabled, Uploaded, FLTokens, Invites, personal_freeleech)
                 VALUES (:username, :torrentpass, :permissionID, :enabled, :uploaded, :fltokens, :invites, :personalFL)",
                [':username'     => $username,
                 ':torrentpass'  => $torrentPass,
                 ':permissionID' => $permissionID,
                 ':enabled'      => $enabled,
                 ':uploaded'     => $this->options->UsersStartingUpload * 1024*1024,
                 ':fltokens'     => $this->options->UsersStartingFLTokens,
                 ':invites'      => $this->options->UsersStartingInvites,
                 ':personalFL'   => date(
                     'Y-m-d H:i:s',
                     strtotime("+{$this->options->UsersStartingPFLDays} days")
                 )]
            );
            $userID = $this->db->last_insert_id();
            $stylesheet = $this->stylesheets->getDefault();
            $this->db->raw_query(
                "INSERT INTO users_info
                    SET UserID   = :userID,    StyleID  = :styleID,
                        AuthKey  = :authKey,   JoinDate = :joinDate,
                        RunHour  = :runHour,   Inviter  = :inviter",
                [':userID'   => intval($userID),
                 ':styleID'  => intval($stylesheet->ID),
                 ':authKey'  => $this->crypto->random_string(32),
                 ':joinDate' => sqltime(),
                 ':runHour'  => rand(0, 23),
                 ':inviter'  => $inviter]
            );

            $user->ID = $userID;
            $user->Username = $username;
            $this->set_password($user, $password);
            $email = $this->emailManager->newEmail(intval($userID), $email);
            $email->setFlags(Email::IS_DEFAULT | Email::VALIDATED);
            $this->emails->save($email);
            $user->EmailID = $email->ID;
            $this->users->save($user);
            if ($transactionInScope) {
                $this->db->commit_transaction();
            }
        } catch (\PDOException $e) {
            error_log("Failed to create user, SQL error: ".$e->getMessage()."\n");
            if ($transactionInScope) {
                $this->db->rollback_transaction();
            }
            return false;
        }
        sendIntroPM($user->ID);

        // Add user before setting PFL on the tracker
        $this->tracker->addUser($torrentPass, $userID);
        $this->tracker->setPersonalFreeleech($torrentPass, strtotime("+{$this->options->UsersStartingPFLDays} days"));

        return $user;
    }
}
