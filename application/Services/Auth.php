<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Errors\SystemError;
use Luminance\Errors\UserError;
use Luminance\Errors\AuthError;
use Luminance\Errors\InternalError;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\UnauthorizedError;
use Luminance\Errors\InputError;

use Luminance\Entities\APIKey;
use Luminance\Entities\Email;
use Luminance\Entities\Invite;
use Luminance\Entities\Session;
use Luminance\Entities\User;
use Luminance\Entities\IP;
use Luminance\Entities\Permission;
use Luminance\Entities\Restriction;
use Luminance\Entities\UserHistoryPassword;
use Luminance\Entities\UserWallet;

class Auth extends Service {

    protected static $defaultOptions = [
        'HaveIBeenPwned'         => ['value' => false, 'section' => 'security', 'displayRow' => 1, 'displayCol' => 1, 'type' => 'bool', 'description' => 'Check passwords against HIBP api'],
        'PasswordBlacklist'      => ['value' => false, 'section' => 'security', 'displayRow' => 1, 'displayCol' => 2, 'type' => 'bool', 'description' => 'Disallow blacklisted passwords'],
        'MinPasswordLength'      => ['value' => 0,     'section' => 'security', 'displayRow' => 1, 'displayCol' => 3, 'type' => 'int',  'description' => 'Minimum password length'],
        'DisabledHits'           => ['value' => false, 'section' => 'security', 'displayRow' => 1, 'displayCol' => 4, 'type' => 'bool', 'description' => 'Log disabled accounts logins'],
        'HaveIBeenPwnedPercent'  => ['value' => 100,   'section' => 'security', 'displayRow' => 2, 'displayCol' => 1, 'type' => 'int',  'description' => 'Chance in percent that HIBP is checked for a user'],
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
        'settings'      => 'Settings',
        'render'        => 'Render',
        'repos'         => 'Repos',
    ];

    protected $sessionID = null;
    protected $userSessions = null;
    protected $CID = null;
    protected $activeUserPermissions = null;
    protected $minUserPermissions = null;

    public $usedPermissions = [];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->log = $this->master->log;
    }

    const AUTH_NONE    = 0;
    const AUTH_API     = 1;
    const AUTH_LOGIN   = 2;
    const AUTH_IPLOCK  = 3;
    const AUTH_2FA     = 6;

    public function checkSession() {
        $this->secretary->checkClient();
        $this->request->authLevel = self::AUTH_NONE;

        if (isset($this->request->cookie['sid'])) {
            $this->cookieAuth();
        } else {
            return;
        }

        $user = $this->request->user;
        if (!($user instanceof User)) {
            throw new AuthError('Unauthorized', 'Authentication Failure');
        }

        $session = $this->request->session;
        if (!($session instanceof Session)) {
            throw new AuthError('Unauthorized', 'Authentication Failure');
        }

        if ($user->isTwoFactorEnabled() && !$session->isTwoFactor()) {
            $path = implode('/', $this->request->path);
            if ($path === 'twofactor/login' || $path === 'twofactor/recover') {
                return;
            } else {
                throw new AuthError('Unauthorized', 'Two Factor Login Required', '/twofactor/login');
            }
        }

        # Because we <3 our staff
        if ($this->isAllowed('site_disable_ip_history')) {
            $this->master->server['REMOTE_ADDR'] = '127.0.0.1';
            $this->request->ip = $this->repos->ips->getOrNew('127.0.0.1');
        }

        # At this point, we're either authenticated or we're not; it's not going to change anymore for this request.
        if ((!$user->isTwoFactorEnabled() || $session->isTwoFactor()) &&
            strtotime($user->legacy['LastAccess']) + 600 < time()) {
            $this->db->rawQuery(
                "UPDATE users_main
                    SET LastAccess = ?
                  WHERE ID = ?",
                [sqltime(), $user->ID]
            );

            # Clear cache key for legacy user as we've changed the last access time
            $this->cache->deleteValue("_entity_User_legacy_{$user->ID}");

            if ($session->getFlag(Session::KEEP_LOGGED_IN)) {
                # Re-set sid cookie every 10mins
                $this->request->setCookie('sid', $this->crypto->encrypt(strval($session->ID), 'cookie'), time()+(60*60*24)*28, true);
            }

            $session->ClientID = $this->request->client->ID;
            $session->Updated = new \DateTime();
            $this->repos->sessions->save($session);
        }

        # Throwback, for now
        if (($user instanceof User) && !($user->IPID === $this->request->ip->ID)) {
            $curIP  = $this->repos->ips->load($user->IPID);
            $newIP  = $this->request->ip;

            if (!($curIP instanceof IP)) {
                # Probably first login of a new user, log it so we can debug more
                trigger_error($user->IPID." IPID not found in the DB.");
                $curIP = new \stdClass;
                $curIP->ID = $user->IPID;
            }

            $this->db->rawQuery(
                "UPDATE users_history_ips
                    SET EndTime = ?
                  WHERE EndTime IS NULL
                    AND UserID = ?
                    AND IPID = ?",
                [sqltime(), $user->ID, $curIP->ID]
            );
            $this->db->rawQuery(
                "INSERT IGNORE INTO users_history_ips (UserID, IPID, StartTime)
                             VALUES (?, ?, ?)",
                [$user->ID, $newIP->ID, sqltime()]
            );

            $user->IPID = $newIP->ID;
            $this->repos->users->save($user);
            $this->db->rawQuery(
                "UPDATE users_main
                    SET ipcc = ?
                  WHERE ID = ?",
                [geoip((string) $newIP), $user->ID]
            );

            if (!empty($this->request->ip->ASN)) {
                $this->db->rawQuery(
                    "INSERT INTO users_history_asns
                          VALUES (?, ?, ?, ?)
                              ON DUPLICATE KEY
                          UPDATE EndTime = VALUES(EndTime)",
                    [$user->ID, $this->request->ip->ASN, sqltime(), sqltime()]
                );
            }

            $this->repos->users->uncache($user->ID);
        }
    }

    public function cookieAuth() {
        $sessionID = $this->crypto->decrypt($this->request->cookie['sid'], 'cookie', false);
        if (empty($sessionID)) {
            # Corrupted cookie - delete it!
            $this->unauthenticate();
            throw new AuthError('Unauthorized', 'Corrupted session cookie', '/login');
        }

        #$this->repos->sessions->disableCache();
        $session = $this->repos->sessions->load($sessionID);
        if (!($session instanceof Session) || !($session->Active === true)) {
            # Expired or invalid session - delete it!
            $this->unauthenticate();
            throw new AuthError('Unauthorized', 'Expired session', '/login');
        }

        if (!($session->ClientID === $this->request->client->ID)) {
            # Disabled exception for now, activate later once all current sessions update
            // # Client changed, copied cookie to a different browser?
            // $this->unauthenticate();
            // throw new AuthError('Unauthorized', 'Corrupted session', '/login');

            # Just create some log spam for the time being:
            trigger_error("Session <> Client mismatch");
        }

        $user = $this->repos->users->load($session->UserID);
        if (!($user instanceof User)) {
            throw new SystemError("No User {$session->UserID} for Session {$session->ID}");
        }

        # Check if user is enabled
        if (!($user->legacy['Enabled'] === '1')) {
            if ($user->legacy['BanReason'] === '3') {
                throw new AuthError('Unauthorized', 'Account is disabled', '/reactivate');
            } else {
                throw new AuthError('Unauthorized', 'Account is disabled', '/disabled');
            }
        }

        $lastip = $this->repos->ips->load($session->IPID);
        if ((!($lastip instanceof IP) || !$lastip->match(IP::fromCIDR($this->request->ip))) && $session->getFlag(Session::IP_LOCKED)) {
            # Locked session moved IP
            $this->unauthenticate();
            throw new AuthError('Unauthorized', 'IP changed on locked session', '/login');
        }

        $this->request->session = $session;
        $this->request->user = $user;
        $this->request->authLevel = self::AUTH_LOGIN;

        $this->secretary->updateClient($this->request->client);
        $this->secretary->updateClientInfo();

        # Emulate 2FA auth in debug mode
        if ($this->settings->site->debug_mode) {
            $this->request->authLevel = self::AUTH_2FA;
        } else {
            if ($session->getFlag(Session::IP_LOCKED))  $this->request->authLevel = self::AUTH_IPLOCK;
            if ($session->getFlag(Session::TWO_FACTOR)) $this->request->authLevel = self::AUTH_2FA;
        }
    }

    public function apiAuth() {
        $apiKey = $this->request->getGetString('apikey');
        if (strlen($apiKey)) {
            $apiKey = $this->apiKeys->get('`Key` = ?', [$apiKey]);
            if ($apiKey instanceof APIKey) {
                $user = $apiKey->user;

                # Check if API key is cancelled
                if ($apiKey->isCancelled()) {
                    throw new AuthError('Unauthorized', 'Unauthorized API key');
                }

                # Check if user is enabled
                if (!($user->legacy['Enabled'] === '1')) {
                    throw new AuthError('Unauthorized', 'Account is disabled');
                }

                # Check if user can access the API
                if ($this->isAllowed('site_api_access', $user)) {
                    throw new AuthError('Unauthorized', 'API access forbidden');
                }

                # Check if user has an API restriction
                if ($this->restrictions->isRestricted($user, Restriction::API)) {
                    throw new AuthError('Unauthorized', 'API access restricted');
                }

                $this->request->user = $user;
                $this->request->authLevel = self::AUTH_API;
            } else {
                throw new AuthError('Unauthorized', 'Unauthorized API key');
            }
        }
    }

    public function authenticate($username, $password, $options) {
        if (is_null($this->request->authLevel)) {
            # Make sure auth status has been checked so we can abort if the client is already logged in.
            throw new InternalError('Attempt to call authenticate() before check_auth()');
        }
        if ($this->request->authLevel >= self::AUTH_LOGIN) {
            throw new UserError('Already logged in');
        }

        # No cinfo means no JS, so it's a script or we're blocked.
        if (empty($options['width']) || empty($options['height']) || empty($options['colordepth'])) {
            # Deny access to bots
            throw new AuthError('Unauthorized', 'Unsupported platform', '/login');
        }

        # Less than 16bit color? I don't believe you!
        if ($options['colordepth'] < 16) {
            # Deny access to bots
            throw new AuthError('Unauthorized', 'Unsupported platform', '/login');
        }

        $this->secretary->updateClientInfo($options);

        $user = $this->repos->users->getByUsername($username);
        if ($user && $this->checkPassword($user, $password)) {
            $user = $this->repos->users->load($user->ID);
            if (!($user->legacy['Enabled'] === '1')) {
                $this->guardian->logDisabled($user, $this->request->ip);
                if ($user->legacy['BanReason'] === '3') {
                    throw new AuthError('Unauthorized', 'Account is disabled', '/reactivate');
                } else {
                    throw new AuthError('Unauthorized', 'Account is disabled', '/disabled');
                }
            }

            # Check if password appeared in breaches
            if ($this->security->passwordIsPwned($password, $user)) {
                throw new AuthError('Unauthorized', 'Access refused', '/pwned');
            }

            # Check if password appeared in breaches
            $this->security->checkDisabledHits($user, $this->request->ip);

            $session = $this->createSession($user, $options);
            $this->request->session = $session;
            if ($session->getFlag(Session::KEEP_LOGGED_IN)) {
                $expires = time()+(60*60*24)*28;
            } else {
                $expires = 0;
            }
            $this->request->setCookie('sid', $this->crypto->encrypt(strval($session->ID), 'cookie'), $expires, true);
        } else {
            $this->handleLoginFailure($user);
            throw new AuthError('Unauthorized', 'Invalid username or password', '/login');
        }

        return $session;
    }

    public function twofactorCheck($user, $code, $secret = null) {
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

    public function twofactorAuthenticate($user, $code) {
        if ($this->twofactorCheck($user, $code)) {
            $this->request->session->setFlags(Session::TWO_FACTOR);
            $this->repos->sessions->save($this->request->session);
        } else {
            $this->guardian->logAttempt('failed 2fa login', $user->ID);
            throw new AuthError('Unauthorized', 'Invalid or expired code', '/twofactor/login');
        }
    }

    public function twofactorEnable($user, $secret, $code) {
        if ($user->isTwoFactorEnabled()) {
            throw new UserError('Two Factor Authentication already enabled');
        }
        if ($this->twofactorCheck($user, $code, $secret)) {
            $user->twoFactorSecret = $this->crypto->encrypt($secret);
            $this->repos->users->save($user);
            if ($this->request->user->ID === $user->ID) {
                $this->request->session->setFlags(Session::TWO_FACTOR);
                $this->repos->sessions->save($this->request->session);
            }
            return true;
        } else {
            return false;
        }
    }

    public function twofactorDisable($user, $code) {
        if ($this->twofactorCheck($user, $code) || $this->isAllowed('users_edit_2fa')) {
            $user->twoFactorSecret = null;
            $this->repos->users->save($user);
            $sessions = $this->repos->sessions->find('userID=? AND FLAGS&?=?', [$user->ID, SESSION::TWO_FACTOR, SESSION::TWO_FACTOR]);
            foreach ($sessions as $session) {
                $session->unsetFlags(Session::TWO_FACTOR);
                $this->repos->sessions->save($session);
            }
            return true;
        } else {
            return false;
        }
    }

    public function twofactorCreateSecret() {
        $ga = new \PHPGangsta_GoogleAuthenticator();
        return $ga->createSecret();
    }

    public function unauthenticate() {
        if ($this->request->session) {
            $this->request->session->Active = false;
            $this->request->session->Updated = new \DateTime();
            $this->repos->sessions->save($this->request->session);
        }
        if ($this->request->client) {
            $this->repos->clients->delete($this->request->client);
        }

        $this->purgeSession();
    }

    public function createSession($user, $options) {
        $session = new Session();
        $session->UserID = $user->ID;
        $session->ClientID = $this->request->client->ID;
        $session->IPID = $this->request->client->IPID;
        $session->Active = true;
        $session->Created = new \DateTime();
        $session->Updated = new \DateTime();
        $session->setFlagStatus(Session::KEEP_LOGGED_IN, $options['keeploggedin']);
        $session->setFlagStatus(Session::IP_LOCKED, $options['iplocked']);

        $this->repos->sessions->save($session);
        $this->cache->deleteValue('users_sessions_'.$user->ID);
        return $session;
    }

    public function handleLoginFailure($user = null) {
        if ($user instanceof User) {
            $attempt = $this->guardian->logAttempt('failed login', $user->ID);
        } else {
            $attempt = $this->guardian->logAttempt('failed login', '0');
        }
        $this->purgeSession();
        return $attempt;
    }

    public function purgeSession() {
        if (array_key_exists('sid', $this->request->cookie)) {
            $this->request->deleteCookie('sid');
        }
        if (array_key_exists('cid', $this->request->cookie)) {
            $this->request->deleteCookie('cid');
        }
    }

    public function setLegacySessionGlobals() {
        global $user, $sessionID, $userSessions, $userID, $enabled, $userStats,
            $permissions, $lightInfo, $heavyInfo, $activeUser, $browser, $operatingSystem, $mobile;

        $useragent = $this->master->secretary;
        $browser = $useragent->browser($_SERVER['HTTP_USER_AGENT']);
        $operatingSystem = $useragent->operatingSystem($_SERVER['HTTP_USER_AGENT']);
        $mobile = false;

        $user = $this->request->user;
        $sessionID = $this->sessionID;
        $userSessions = $this->userSessions;

        $userID  = ($user instanceof User) ? $user->ID : null;
        $enabled = ($user instanceof User) ? $user->legacy['Enabled'] : null;
        if ($this->request->user) {
            $userStats = $this->getUserStats($userID);
            $permissions = $this->getUserPermissions($user);
            $lightInfo = $user->info();
            $heavyInfo = $user->heavyInfo();
            $activeUser = $this->getLegacyLoggedUser();
        }
    }

    public function checkLogin($userID, $password) {
        $user = $this->repos->users->load($userID);
        return $this->checkPassword($user, $password);
    }

    public function setPassword($user, $password) {
        # If we're passed a userID the load the user object
        $userID = false;
        if (is_integer_string($user)) {
            $userID = $user;
            $user = $this->repos->users->load($user);
        }

        # Handle setting the password
        if ($user && strlen($password)) {
            $user->Password = $this->createHashBcrypt($password);
            # Only save the user object if we loaded it
            if ($userID && is_integer_string($userID)) {
                $this->repos->users->save($user);
            }

            if (!empty($this->request->ip)) {
                # Save the password change
                $passwordHistory = new UserHistoryPassword;
                $passwordHistory->UserID = $user->ID;
                $passwordHistory->IPID = $this->request->ip->ID;
                $passwordHistory->Time = new \DateTime;

                $this->repos->userHistoryPasswords->save($passwordHistory);
                $this->repos->users->uncache($user);
            }
        } else {
            throw new InternalError("Invalid setPassword attempt.");
        }
    }

    protected function rehashPassword($user, $password) {
            # Re-hash as bcrypt
            $user->Password = $this->createHashBcrypt($password);
            $this->repos->users->save($user);
            return $this->checkPassword($user, $password, false);
    }

    public function checkPassword($user, $password, $allowRehash = true) {
        if (is_null($user->Password) || !strlen($user->Password)) {
            throw new UserError("You must set a new password.");
        }
        if (is_null($password) || !strlen($password)) {
            throw new UserError("Password cannot be empty.");
        }
        $passwordFields = explode('$', $user->Password); # string should start with '$', so the first field is always an empty string
        if (count($passwordFields) < 3) {
            throw new SystemError("Invalid password data.");
        }
        $passwordType = $passwordFields[1];
        $passwordValues = array_slice($passwordFields, 2);
        switch ($passwordType) {
            case 'salted-md5':
                $result = $this->checkPasswordSaltedMD5($password, $passwordValues);
                if ($result === true && $allowRehash === true) {
                    $this->rehashPassword($user, $password);
                }
                break;
            case 'md5':
                $result = $this->checkPasswordMD5($password, $passwordValues);
                if ($result === true && $allowRehash === true) {
                    $this->rehashPassword($user, $password);
                }
                break;
            case 'salted-sha1':
                $result = $this->checkPasswordSaltedSHA1($password, $passwordValues);
                if ($result === true && $allowRehash === true) {
                    $this->rehashPassword($user, $password);
                }
                break;
            case 'sha1':
                $result = $this->checkPasswordSHA1($password, $passwordValues);
                if ($result === true && $allowRehash === true) {
                    $this->rehashPassword($user, $password);
                }
                break;
            case 'bcrypt':
                $result = $this->checkPasswordBcrypt($password, $passwordValues);
                break;
            default:
                throw new SystemError("Invalid password data.");
        }
        return $result;
    }

    protected function checkPasswordSaltedMD5($password, $values) {
        list($encodedSecret, $encodedHash) = $values;
        $secret = base64_decode($encodedSecret);
        # Use hex strings since strlen() might be risky for binary data (mbstring overload)
        $storedHash = Crypto::bin2hex(base64_decode($encodedHash));
        $actualHash = md5(md5($secret) . md5($password));
        if (# Just a bunch of overly paranoid sanity checks
            !strlen($password)    ||
            !strlen($secret)      ||
            !strlen($storedHash) ||
            !(strlen($actualHash) === 32)
        ) {
            throw new SystemError("Invalid password data.");
        }
        $result = ($actualHash === $storedHash);
        return $result;
    }

    protected function checkPasswordMD5($password, $values) {
        list(, $encodedHash) = $values;
        # Use hex strings since strlen() might be risky for binary data (mbstring overload)
        $storedHash = Crypto::bin2hex(base64_decode($encodedHash));
        $actualHash = md5($password);
        if (# Just a bunch of overly paranoid sanity checks
            !strlen($password)    ||
            !strlen($storedHash) ||
            !(strlen($actualHash) === 32)
        ) {
            throw new SystemError("Invalid password data.");
        }
        $result = ($actualHash === $storedHash);
        return $result;
    }

    protected function checkPasswordSaltedSHA1($password, $values) {
        list($encodedSecret, $encodedHash) = $values;
        $secret = base64_decode($encodedSecret);
        # Use hex strings since strlen() might be risky for binary data (mbstring overload)
        $storedHash = Crypto::bin2hex(base64_decode($encodedHash));
        $actualHash = sha1(sha1($secret) . sha1($password));
        if (# Just a bunch of overly paranoid sanity checks
            !strlen($password)    ||
            !strlen($secret)      ||
            !strlen($storedHash) ||
            !(strlen($actualHash) === 40)
        ) {
            throw new SystemError("Invalid password data.");
        }
        $result = ($actualHash === $storedHash);
        return $result;
    }

    protected function checkPasswordSHA1($password, $values) {
        list(, $encodedHash) = $values;
        # Use hex strings since strlen() might be risky for binary data (mbstring overload)
        $storedHash = Crypto::bin2hex(base64_decode($encodedHash));
        $actualHash = sha1($password);
        if (# Just a bunch of overly paranoid sanity checks
            !strlen($password)    ||
            !strlen($storedHash) ||
            !(strlen($actualHash) === 40)
        ) {
            throw new SystemError("Invalid password data.");
        }
        $result = ($actualHash === $storedHash);
        return $result;
    }

    protected function createHashBcrypt($password) {
        # password_hash does most of the work for us here
        # we use defaults for now, which is to generate a salt automatically and use cost = 10
        $encodedHash = password_hash($password, PASSWORD_DEFAULT);
        $hashString = "\$bcrypt{$encodedHash}"; # dollar sign is already included
        return $hashString;
    }

    protected function checkPasswordBcrypt($password, $values) {
        $hashString = "\$" . implode("\$", $values);
        $result = password_verify($password, $hashString);
        return ($result === true);
    }

    public function getUserPermissions(User $user, $includeCustom = true) {
        $userPerms = $this->repos->permissions->getLegacyPermission($user->class->ID);
        if ($user->group instanceof Permission) {
            $groupPerms = $this->repos->permissions->getLegacyPermission($user->group->ID);
        } else {
            $groupPerms = [];
            $groupPerms['Permissions'] = [];
        }

        if (!is_null($user->legacy['CustomPermissions']) && ($includeCustom === true)) {
            $customPerms = (array) unserialize($user->legacy['CustomPermissions']);
        } else {
            $customPerms = [];
        }

        $maxCollages = 0;
        $maxCollages += intval((array_key_exists('MaxCollages', $userPerms['Permissions'])) ? $userPerms['Permissions']['MaxCollages'] : 0);
        $maxCollages += intval((array_key_exists('MaxCollages', $groupPerms['Permissions'])) ? $groupPerms['Permissions']['MaxCollages'] : 0);
        $maxCollages += intval((array_key_exists('MaxCollages', $customPerms)) ? $customPerms['MaxCollages'] : 0);

        $userPermissions = array_merge($userPerms['Permissions'], $groupPerms['Permissions'], $customPerms, ['MaxCollages'=>$maxCollages]);
        return $userPermissions;
    }

    public function getMinUserPermissions() {
        if (is_null($this->minUserPermissions)) {
            $userPerms = $this->repos->permissions->getLegacyPermission($this->repos->permissions->getMinUserClassID());
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
        if (array_key_exists($name, $this->usedPermissions)) {
            $this->usedPermissions[$name]++;
        } else {
            $this->usedPermissions[$name] = 1;
        }
    }

    public function isAllowed($name, $user = null) {
        # Determine if something is allowed, and return the answer as a boolean.
        $user = $this->repos->users->load($user);
        if (!($user instanceof User)) {
            $user = $this->request->user;
        }
        if (!($user instanceof User)) {
            return false;
        }
        $permissions = $this->getActiveUserPermissions();
        if (is_array($name)) {
            $kosher = [];
            foreach ($name as $key => $perm) {
                $this->recordCheck($perm);
                $kosher[] = array_key_exists($perm, $permissions) && $permissions[$perm];
                if (count(array_unique($kosher)) === 1) {
                    $allowed = current($kosher);
                }
            }
        } else {
            $this->recordCheck($name);
            $allowed = array_key_exists($name, $permissions) && $permissions[$name];
        }
        return $allowed;
    }

    public function isAllowedByMinUser($name) {
        # Determine if something is allowed, and return the answer as a boolean.
        $this->recordCheck($name);
        $permissions = $this->getMinUserPermissions();
        $allowed = array_key_exists($name, $permissions) && $permissions[$name];
        return $allowed;
    }

    public function checkAllowed($name, $user = null) {
        # Determine if something is allowed, and throw an exception if it's not the case.
        $user = $this->repos->users->load($user);
        if (!$user instanceof User) {
            $user = $this->request->user;
        }
        if (!$user instanceof User) {
            throw new UnauthorizedError();
        }
        if (!$this->isAllowed($name)) {
            throw new ForbiddenError();
        }
    }

    public function isLevel($level) {
        $userLevel = $this->request->user->class->Level;
        if ($level > $userLevel) {
            return false;
        }
        return true;
    }

    public function checkLevel($level) {
        # Determine if something is allowed, and throw an exception if it's not the case.
        if (!$this->request->user) {
            throw new UnauthorizedError();
        }
        if (!$this->isLevel($level)) {
            throw new ForbiddenError();
        }
    }

    public function isUserLevel($user) {
        # User can be user object or userID
        $user = $this->repos->users->load($user);
        $level = $user->class->Level;
        return $this->isLevel($level);
    }

    public function checkUserLevel($user) {
        # User can be user object or userID
        $user = $this->repos->users->load($user);
        $level = $user->class->Level;
        $this->checkLevel($level);
    }

    public function getUserStats($userID) {
        $userStats = $this->cache->getValue('user_stats_' . $userID);
        if (!is_array($userStats)) {
            $userStats = $this->db->rawQuery(
                "SELECT m.Uploaded AS BytesUploaded,
                        m.Downloaded AS BytesDownloaded,
                        m.RequiredRatio AS RequiredRatio,
                        w.Balance AS TotalCredits
                   FROM users_main AS m
                   JOIN users_wallets AS w ON m.ID = w.UserID
                  WHERE m.ID = ?",
                [$userID]
            )->fetch(\PDO::FETCH_ASSOC);
            $this->cache->cacheValue('user_stats_' . $userID, $userStats, 3600);
        }
        return $userStats;
    }

    public function getActiveUser() {
        return $this->request->user;
    }

    public function getLegacyLoggedUser() {
        if (!$this->request->user) {
            return [];
        }
        $user = $this->request->user;
        $activeUser = array_merge(
            $user->heavyInfo(),
            $user->info(),
            $this->repos->permissions->getLegacyPermission($user->class),
            $user->stats()
        );
        $activeUser['RSS_Auth'] = $user->legacy['RSS_Auth'];
        $activeUser['RatioWatch'] = $user->onRatiowatch();

        $activeUser['Permissions'] = $this->getUserPermissions($user);

        $stylesheet = $this->repos->stylesheets->getByUser($user);
        $activeUser['StyleName'] = $stylesheet->Path;
        return $activeUser;
    }

    public function legacyEnforceLogin() {
        if (!$this->request->user) {
            $this->request->saveIntendedRoute();
            throw new AuthError(null, null, '/login');
        }
    }

    public function createUser($username, $password, $email, $invite = null) {
        $username = trim($username);
        $email = trim($email);
        $this->repos->users->checkAvailable($username);
        $this->repos->users->checkValid($username);
        try {
            $this->emailManager->validate($email);
        } catch (InputError $e) {
            if ($e->getMessage() === "That email address is not available.") {
                $subject = 'User attempted to register with a duplicate email address';
                $message = $this->render->template(
                    'bbcode/duplicate_email.twig',
                    [
                        'Email'       => (string) $email,
                        'Username'    => (string) $username,
                        'IP'          => (string) $this->request->ip,
                        'CurrentTime' => sqltime(),
                        'Inviter'     => false,
                    ]
                );

                $staffClass = $this->repos->permissions->getMinClassPermission('users_view_ips');
                if ($staffClass === false) {
                    throw $e;
                }

                send_staff_pm($subject, $message, $staffClass->Level);
            }
            throw $e;
        }

        $torrentPass = $this->crypto->randomString(32);
        $userCount = $this->db->rawQuery("SELECT COUNT(*) FROM users_main")->fetchColumn();
        # First user is SysOp
        if ($userCount === 0) {
            $permissionID = 1; # Should be true on new installs
        } else {
            $permissionID = $this->repos->permissions->getMinUserClassID();
        }

        if (empty($permissionID)) {
            throw new UserError("No userclasses have been configured.");
        }

        if ($invite instanceof Invite) {
            $inviter  = $invite->InviterID;
            $sqltime  = sqltime();
            $comment  = $invite->Comment;
            $comment  = "{$sqltime} - {$comment}".PHP_EOL;
            //$comment .= $time." invite sent";
        } else {
            $inviter = 0;
            $comment = '';
        }

        $user = new User();

        $enabled = '1';
        try {
            $transactionInScope = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $transactionInScope = true;
            }
            $this->db->rawQuery(
                "INSERT INTO users_main (torrent_pass, PermissionID, Enabled, Uploaded, FLTokens, Invites, personal_freeleech)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $torrentPass,
                    $permissionID,
                    $enabled,
                    $this->options->UsersStartingUpload * 1024*1024,
                    $this->options->UsersStartingFLTokens,
                    $this->options->UsersStartingInvites,
                    date(
                        'Y-m-d H:i:s',
                        strtotime("+{$this->options->UsersStartingPFLDays} days")
                    )
                ]
            );
            $userID = $this->db->lastInsertID();
            if (!is_integer_string($userID)) {
                throw new SystemError('Database failure');
            }
            $stylesheet = $this->repos->stylesheets->getDefault();
            $this->db->rawQuery(
                "INSERT INTO users_info
                    SET UserID       = ?,
                        StyleID      = ?,
                        AdminComment = ?,
                        JoinDate     = ?,
                        RunHour      = ?,
                        Inviter      = ?",
                [
                    intval($userID),
                    intval($stylesheet->ID),
                    $comment,
                    sqltime(),
                    rand(0, 23),
                    $inviter
                ]
            );

            $user->ID = $userID;
            $user->Username = $username;
            $this->setPassword($user, $password);
            if (!($inviter === 0)) {
                $this->repos->inviteLogs->updateOnJoin($email, $userID, $inviter);
            }
            $email = $this->emailManager->newEmail(intval($userID), $email);
            $email->setFlags(Email::IS_DEFAULT | Email::VALIDATED);
            $this->repos->emails->save($email);
            $user->EmailID = $email->ID;
            $this->repos->users->save($user);
            $this->repos->inviteTrees->new($user);
            $wallet = new UserWallet;
            $wallet->UserID = $user->ID;
            $this->repos->userWallets->save($wallet);
            if ($transactionInScope === true) {
                $this->db->commit();
            }

            $this->cache->deleteValue("pending_invites_{$inviter}");
            $this->cache->deleteValue("invitees_{$inviter}");
            $usernameMD5 = md5($username);
            $this->cache->deleteValue("_query_User_Username_{$usernameMD5}");
        } catch (\PDOException $e) {
            error_log("Failed to create user, SQL error: ".$e->getMessage().PHP_EOL);
            if ($transactionInScope === true) {
                $this->db->rollback();
            }
            return false;
        }
        sendIntroPM($user->ID);

        # Add user before setting PFL on the tracker
        $this->tracker->addUser($torrentPass, $userID);
        $this->tracker->setPersonalFreeleech($torrentPass, strtotime("+{$this->options->UsersStartingPFLDays} days"));

        return $user;
    }
}
