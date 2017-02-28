<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Errors\ConfigurationError;
use Luminance\Errors\SystemError;
use Luminance\Errors\UserError;
use Luminance\Errors\AuthError;
use Luminance\Errors\InternalError;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\UnauthorizedError;
use Luminance\Entities\Session;
use Luminance\Entities\Style;
use Luminance\Entities\User;
use Luminance\Services\Crypto;

require_once(SERVER_ROOT . '/common/main_functions.php');
require_once(SERVER_ROOT . '/common/time_functions.php');
require_once(SERVER_ROOT . '/common/paranoia_functions.php');

class Auth extends Service {

    public $Session = null;

    protected static $useRepositories = [
        'permissions' => 'PermissionRepository',
        'sessions'    => 'SessionRepository',
        'stylesheets' => 'StylesheetRepository',
        'users'       => 'UserRepository',
        'ips'         => 'IPRepository',
    ];

    protected static $useServices = [
        'crypto'    => 'Crypto',
        'cache'     => 'Cache',
        'guardian'  => 'Guardian',
        'db'        => 'DB',
        'secretary' => 'Secretary',
    ];

    protected $SessionID = null;
    protected $UserSessions = null;
    protected $CID = null;
    protected $activeUserPermissions = null;

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $this->master->request;
        $this->log = $this->master->log;
    }

    public function checkSession() {
        $this->secretary->checkClient();
        $this->request->authLevel = 0;

        try {
            if (isset($this->request->cookie['sid'])) {
                $this->cookieAuth();
            }

        } catch (AuthError $e) {
            $this->guardian->detect();
            $this->handle_login_failure();
            throw new UnauthorizedError();
        }

        // At this point, we're either authenticated or we're not; it's not going to change anymore for this request.
        if ($this->request->user && strtotime($this->request->user->legacy['LastAccess']) + 600 < time()) {
            $this->db->raw_query("UPDATE users_main SET LastAccess=:lastaccess WHERE ID=:userid",
                                 [':lastaccess' => sqltime(), ':userid' => $this->request->user->ID]);

            if ($this->request->session->getFlag(Session::KEEP_LOGGED_IN)) {
                // Re-set sid cookie every 10mins
                $this->request->set_cookie('sid', $this->crypto->encrypt(strval($this->request->session->ID), 'cookie'), time()+(60*60*24)*28, true);
            }
            $this->request->session->Updated = new \DateTime();
            $this->sessions->save($this->request->session);
        }

        // Because we <3 our staff
        if ($this->isAllowed('site_disable_ip_history')) {
            $this->master->server['REMOTE_ADDR'] = '127.0.0.1';
        }

        // Throwback, for now
        //var_dump($this->request->user->legacy['IP']);
        //var_dump($this->master->server['REMOTE_ADDR']);
        if ($this->request->user && ($this->request->user->legacy['IP'] != $this->master->server['REMOTE_ADDR'])) {
            $UserID = $this->request->user->legacy['ID'];
            $CurIP  = $this->request->user->legacy['IP'];
            $NewIP  = $this->master->server['REMOTE_ADDR'];

            $this->db->raw_query("UPDATE users_history_ips SET EndTime=:endtime WHERE EndTime IS NULL AND UserID=:userid AND IP=:ip",
                              [':endtime' => sqltime(),
                               ':userid'  => $UserID,
                               ':ip'      => $CurIP]);
            $this->db->raw_query("INSERT IGNORE INTO users_history_ips (UserID, IP, StartTime) VALUES (:userid, :newip, :starttime)",
                              [':userid'    => $UserID,
                               ':newip'     => $NewIP,
                               ':starttime' => sqltime()]);

            $ipcc = geoip($NewIP);
            $this->db->raw_query("UPDATE users_main SET IP=:ip, ipcc=:ipcc WHERE ID=:userid",
                              [':ip'    => $NewIP,
                              ':ipcc'   => $ipcc,
                              ':userid' => $UserID]);

            $this->master->repos->users->uncache($UserID);
        }
    }

    public function cookieAuth() {
        $sessionID = $this->crypto->decrypt($this->request->cookie['sid'], 'cookie');
        if (!$sessionID) {
            # Corrupted cookie
            throw new AuthError();
        }

        $session = $this->sessions->load($sessionID);
        if (!$session || $session->ClientID != $this->request->client->ID || !$session->Active) {
            throw new AuthError();
        }

        $user = $this->users->load($session->UserID);
        if (!$user) {
            throw new SystemError("No User {$session->UserID} for Session {$session->ID}");
        }

        $lastip = $this->ips->load($session->IPID);
        if((!$lastip || !$lastip->match($this->request->IP)) && $session->getFlag(Session::IP_LOCKED)){
            throw new AuthError();
        }

        $this->request->session = $session;
        $this->request->user = $user;
        $this->request->authLevel = 2;
        // Not sure about this yet, but let's start here
        if ($session->getFlag(Session::IP_LOCKED)) $this->request->authLevel++;

    }

    public function authenticate($username, $password, $options) {
        $this->guardian->detect();
        if (is_null($this->request->authLevel)) {
            # Make sure auth status has been checked so we can abort if the client is already logged in.
            throw new InternalError("Attempt to call authenticate() before check_auth()");
        }
        if ($this->request->authLevel >= 2) {
            throw new UserError("Already logged in");
        }

        $this->secretary->updateClientInfo($options);

        $user = $this->users->get_by_username($username);
        if ($user && $this->check_password($user, $password)) {
            $user = $this->users->load($user->ID);
            if ($user->legacy['Enabled'] != '1') {
                throw new AuthError("Account is disabled", "Unauthorized", "/disabled");
            }
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

    public function unauthenticate() {
        if ($this->Session) {
            $this->Session->Active = false;
            $this->Session->Updated = new \DateTime();
            $this->sessions->save($this->Session);
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
        return $session;
    }

    public function handle_login_failure($user = null) {
        if ($user) {
            $Attempt = $this->guardian->log_attempt($user->ID);
        } else {
            $Attempt = $this->guardian->log_attempt("0");
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

    public function set_password($userID, $password) {
        $user = $this->users->load($userID);
        if ($user && strlen($password)) {
            $user->Password = $this->create_hash_bcrypt($password);
            $this->users->save($user);
        } else {
            throw new InternalError("Invalid set_password attempt.");
        }
    }

    public function set_user_password(User $user, $password) {
        if ($user && strlen($password)) {
            $user->Password = $this->create_hash_bcrypt($password);
        } else {
            throw new InternalError("Invalid set_user_password attempt.");
        }
    }

    public function check_password($user, $password, $allow_rehash = true) {
        if (is_null($user->Password) || !strlen($user->Password)) {
            return false;
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
                    # Re-hash as bcrypt
                    $user->Password = $this->create_hash_bcrypt($password);
                    $this->users->save($user);
                    return $this->check_password($user, $password, false);
                }
                return $result;
            case 'bcrypt':
                return $this->check_password_bcrypt($password, $password_values);
        }
        throw new SystemError("Invalid password data.");
    }

    protected function create_hash_salted_md5($password) {
        $secret = make_secret();
        $hash = md5(md5($secret) . md5($password));
        $encoded_secret = base64_encode($secret);
        $encoded_hash = base64_encode(Crypto::hex2bin($hash));
        $hash_string = "\$salted-md5\${$encoded_secret}\${$encoded_hash}";
        return $hash_string;
    }

    protected function check_password_salted_md5($password, $values) {
        list($encoded_secret, $encoded_hash) = $values;
        $secret = base64_decode($encoded_secret);
        # Use hex strings since strlen() might be risky for binary data (mbstring overload)
        $stored_hash = Crypto::bin2hex(base64_decode($encoded_hash));
        $actual_hash = md5(md5($secret) . md5($password));
        if (
            # Just a bunch of overly paranoid sanity checks
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

    protected function create_hash_bcrypt($password) {
        # password_hash does most of the work for us here
        # we use defaults for now, which is to generate a salt automatically and use cost = 10
        $encoded_hash = password_hash($password, PASSWORD_BCRYPT);
        $hash_string = "\$bcrypt{$encoded_hash}"; # dollar sign is already included
        return $hash_string;
    }

    protected function check_password_bcrypt($password, $values) {
        $hash_string = "\$" . implode("\$", $values);
        $result = password_verify($password, $hash_string);
        return ($result === true);
    }

    public function getUserPermissions(User $user) {
        $userPermID = $user->legacy['PermissionID'];
        $userPerms = $this->permissions->getLegacyPermission($userPermID);

        $groupPermID = $user->legacy['GroupPermissionID'];
        $groupPerms = $this->permissions->getLegacyPermission($groupPermID);

        if (!is_null($user->legacy['CustomPermissions'])) {
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

    public function getActiveUserPermissions() {
        if (is_null($this->activeUserPermissions)) {
            if (!$this->request->user) {
                return [];
            }
            $this->activeUserPermissions = $this->getUserPermissions($this->request->user);
        }
        return $this->activeUserPermissions;
    }

    public function isAllowed($name) {
        # Determine if something is allowed, and return the answer as a boolean.
        if (!$this->request->user) {
            return false;
        }
        $permissions = $this->getActiveUserPermissions();
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

    public function get_user_stats($userID) {
            $UserStats = $this->cache->get_value('user_stats_' . $userID);
            if (!is_array($UserStats)) {
                $UserStats = $this->db->raw_query("SELECT Uploaded AS BytesUploaded, Downloaded AS BytesDownloaded, RequiredRatio, Credits as TotalCredits
                                                          FROM users_main WHERE ID=:userid",
                                                          [':userid'=>$userID])->fetch(\PDO::FETCH_ASSOC);
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

        $LoggedUser['Permissions'] = get_permissions_for_user($user->ID, $LoggedUser['CustomPermissions']);

        $Stylesheet = $this->master->repos->stylesheets->get_by_user($user);
        $LoggedUser['StyleName'] = $Stylesheet->Name;
        return $LoggedUser;
    }

    public function legacy_enforce_login() {
        if (!$this->request->user) {
            throw new ForbiddenError();
        }
    }

    public function checkUsernameAvailable($username) {
        $user = $this->users->get_by_username($username);
        if ($user) {
            return false;
        }

        $stmt = $this->db->raw_query("SELECT ID FROM users_main WHERE Username = ?", [$username]);
        $user_legacy = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user_legacy) {
            return false;
        }
        return true;
    }

    public function createUser($username, $password, $email, $inviter=0) {
        $torrentPass = $this->crypto->random_string(32);
        list($userCount) = $this->db->raw_query("SELECT COUNT(*) FROM users_main")->fetch();
        // First user is SysOp
        if($userCount == 0) {
            $permissionID = 1; # Should be true on new installs
        } else {
            $permissionID = $this->permissions->getMinUserClassID();
        }

        if(!$permissionID) {
          throw new InternalError("No userclasses have been configured.");
        }

        $enabled = '1';
        $uploaded = 524288000; # TODO config
        $this->db->raw_query("INSERT INTO users_main (Username, Email, torrent_pass, PermissionID, Enabled, Uploaded)
                                              VALUES (:username, :email, :torrentpass, :permissionID, :enabled, :uploaded)",
                                [':username'     => $username,
                                 ':email'        => $email,
                                 ':torrentpass'  => $torrentPass,
                                 ':permissionID' => $permissionID,
                                 ':enabled'      => $enabled,
                                 ':uploaded'     => $uploaded]);
        $userID = $this->db->last_insert_id();
        $stylesheet = $this->stylesheets->getDefault();
        $this->db->raw_query("INSERT INTO users_info
                                 SET UserID   = :userID,    StyleID  = :styleID,
                                     AuthKey  = :authKey,   JoinDate = :joinDate,
                                     RunHour  = :runHour,   Inviter  = :inviter",
                             [':userID'   => intval($userID),
                              ':styleID'  => intval($stylesheet->ID),
                              ':authKey'  => $this->crypto->random_string(32),
                              ':joinDate' => sqltime(),
                              ':runHour'  => rand(0, 23),
                              ':inviter'  => $inviter]);

        $user = new User();
        $user->ID = $userID;
        $user->Username = $username;
        $user->EmailID = 0;
        $this->set_user_password($user, $password);

        // Required for legacy Tracker comms
        $this->master->settings->set_legacy_constants();
        update_tracker('add_user', array('id' => $userID, 'passkey' => $torrentPass));

        $this->users->save($user);
        return $user;
    }
}
