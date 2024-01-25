<?php
namespace Luminance\Plugins\Authentication;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Errors\UserError;
use Luminance\Errors\AuthError;
use Luminance\Errors\InputError;
use Luminance\Errors\NotFoundError;

use Luminance\Entities\IP;
use Luminance\Entities\User;
use Luminance\Entities\Email;
use Luminance\Entities\Session;
use Luminance\Entities\GeoLite2ASN;
use Luminance\Entities\PublicRequest;

use Luminance\Services\Auth;

use Luminance\Responses\Redirect;
use Luminance\Responses\Rendered;

use Phayes\GeoPHP\GeoPHP;
use Phayes\GeoPHP\Geometry\Point;
use Phayes\GeoPHP\Geometry\LineString;

class AuthenticationPlugin extends Plugin {


    protected static $defaultOptions = [
        'APIEnabled'             => ['value' => false,  'section' => 'auth',  'displayRow' => 1, 'displayCol' => 1, 'type' => 'bool',  'description' => 'Enable API access'],
        'API2FA'                 => ['value' => false,  'section' => 'auth',  'displayRow' => 1, 'displayCol' => 2, 'type' => 'bool',  'description' => 'Require 2FA for API access'],
        'APIKeyLimit'            => ['value' => 1,      'section' => 'auth',  'displayRow' => 1, 'displayCol' => 3, 'type' => 'int',   'description' => 'API key limit per user'],
        'EnableApplication'      => ['value' => false,  'section' => 'Applications', 'displayRow' => 1, 'displayCol' => 1, 'type' => 'bool', 'description' => 'Enable application signups'],
        'ReactivationProofs'     => ['value' => true,  'section' => 'Applications', 'displayRow' => 1, 'displayCol' => 2, 'type' => 'bool', 'description' => 'Display Proof fields on Reactivation form'],
        'ActivationProofs'       => ['value' => true,  'section' => 'Applications', 'displayRow' => 1, 'displayCol' => 3, 'type' => 'bool', 'description' => 'Display Proof fields on Application form'],
        'EnableQuestionOne'      => ['value' => false,  'section' => 'Applications', 'displayRow' => 2, 'displayCol' => 1, 'type' => 'bool', 'description' => 'Display Question 1 on Application form'],
        'EnableQuestionTwo'      => ['value' => false,  'section' => 'Applications', 'displayRow' => 2, 'displayCol' => 1, 'type' => 'bool', 'description' => 'Display Question 2 on Application form'],
        'EnableQuestionThree'    => ['value' => false,  'section' => 'Applications', 'displayRow' => 2, 'displayCol' => 1, 'type' => 'bool', 'description' => 'Display Question 3 on Application form'],
        'ApplicationQuesOne'     => ['value' => 'Can you draw a pirate?', 'section' => 'Applications', 'displayRow' => 3, 'displayCol' => 1, 'type' => 'string', 'description' => 'Question 1'],
        'ApplicationQuesTwo'     => ['value' => 'What is our :love: emoticon?', 'section' => 'Applications', 'displayRow' => 3, 'displayCol' => 2, 'type' => 'string', 'description' => 'Question 2'],
        'ApplicationQuesThree'   => ['value' => 'Do you like turtles?', 'section' => 'Applications', 'displayRow' => 3, 'displayCol' => 3, 'type' => 'string', 'description' => 'Question 3'],
    ];

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  'login',               Auth::AUTH_NONE,  'loginForm'              ],
        [ 'POST', 'login',               Auth::AUTH_NONE,  'login'                  ],
        [ 'GET',  'twofactor/login',     Auth::AUTH_LOGIN, 'twofactorLoginForm'     ],
        [ 'POST', 'twofactor/login',     Auth::AUTH_LOGIN, 'twofactorLogin'         ],
        [ 'GET',  'twofactor/recover',   Auth::AUTH_LOGIN, 'twofactorRecover'       ],
        [ 'GET',  'logout',              Auth::AUTH_LOGIN, 'logoutTrap'             ],
        [ 'POST', 'logout',              Auth::AUTH_LOGIN, 'logout'                 ],
        [ 'GET',  'disabled',            Auth::AUTH_NONE,  'disabledForm'           ],
        [ 'GET',  'pwned',               Auth::AUTH_NONE,  'pwnedForm'              ],
        [ 'GET',  'reactivate',          Auth::AUTH_NONE,  'reactivateForm'         ],
        [ 'POST', 'reactivate',          Auth::AUTH_NONE,  'reactivate'             ],
        [ 'GET',  'application',         Auth::AUTH_NONE,  'applicationForm'        ],
        [ 'POST', 'application',         Auth::AUTH_NONE,  'application'            ],
        [ 'HEAD', 'index',               Auth::AUTH_NONE,  'isItUp'                 ],
        [ 'GET',  'index',               Auth::AUTH_NONE,  'index'                  ],
        [ 'GET',  'manage/requests/*',   Auth::AUTH_2FA,   'publicRequestsForm'     ],
        [ 'POST', 'manage/requests/*',   Auth::AUTH_2FA,   'publicRequests'         ],
    ];

    protected static $useServices = [
        'auth'          => 'Auth',
        'guardian'      => 'Guardian',
        'flasher'       => 'Flasher',
        'settings'      => 'Settings',
        'secretary'     => 'Secretary',
        'security'      => 'Security',
        'render'        => 'Render',
        'db'            => 'DB',
        'cache'         => 'Cache',
        'emailManager'  => 'EmailManager',
        'repos'         => 'Repos',
    ];

    protected static $userinfoTools = [
        [
            'admin_login_watch',              # permission
            'tools.php?action=security_logs', # action
            'Security Logs'                   # title
        ],
        [
            'admin_login_watch',              # permission
            'tools.php?action=disabled_hits', # action
            'Disabled Hits'                   # title
        ],
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ '*',    '',                   Auth::AUTH_NONE,  'plugin', 'Authentication', 'index'              ]);
        $master->prependRoute([ '*',    'index.php',          Auth::AUTH_NONE,  'plugin', 'Authentication', 'index'              ]);
        $master->prependRoute([ '*',    'login',              Auth::AUTH_NONE,  'plugin', 'Authentication', 'login'              ]);
        $master->prependRoute([ '*',    'logout',             Auth::AUTH_LOGIN, 'plugin', 'Authentication', 'logout'             ]);
        $master->prependRoute([ '*',    'disabled',           Auth::AUTH_NONE,  'plugin', 'Authentication', 'disabled'           ]);
        $master->prependRoute([ '*',    'pwned',              Auth::AUTH_NONE,  'plugin', 'Authentication', 'pwned'              ]);
        $master->prependRoute([ '*',    'twofactor/login',    Auth::AUTH_LOGIN, 'plugin', 'Authentication', 'twofactor/login'    ]);
        $master->prependRoute([ '*',    'twofactor/recover',  Auth::AUTH_LOGIN, 'plugin', 'Authentication', 'twofactor/recover'  ]);
        $master->prependRoute([ '*',    'reactivate',         Auth::AUTH_NONE,  'plugin', 'Authentication', 'reactivate'         ]);
        $master->prependRoute([ '*',    'application',        Auth::AUTH_NONE,  'plugin', 'Authentication', 'application'        ]);
        $master->prependRoute([ 'GET',  'manage/requests/*',  Auth::AUTH_2FA,   'plugin', 'Authentication', 'manage/requests'    ]);
        $master->prependRoute([ 'POST', 'manage/requests/*',  Auth::AUTH_2FA,   'plugin', 'Authentication', 'manage/requests'    ]);
    }

    public function isItUp() {
        return new Rendered('core/base.html.twig', [], 200, 'header');
    }

    public function index() {
        if ($this->request->user) {
            return $this->master->getPlugin('Legacy')->handlePath('index');
        } else {
            $csp = [
              'default-src' => ["'self'"],
              'img-src'     => ["'self'"],
              'script-src'  => ["'self'", "'unsafe-inline'"],
              'object-src'  => ["'self'"],
              'style-src'   => ["'self'", "'unsafe-inline'"],
            ];

            # Format the CSP into a string
            $header = 'Content-Security-Policy: ';
            foreach ($csp as $directive => $rules) {
                $header .= $directive.'  '.implode(' ', $rules).'; ';
            };

            # Send the header
            header(trim($header));
            return new Rendered('@Authentication/public_index.html.twig');
        }
    }

    public function loginForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        return new Rendered('@Authentication/login.html.twig');
    }

    protected static function parseClientInfo($clientInfoString) {
        $clientInfoParts = explode('|', $clientInfoString);

        if (!(count($clientInfoParts) === 4)) {
            $clientInfoParts = ['', '', '', ''];
        }
        $clientInfo = [];
        $clientInfo['width'] = (strlen($clientInfoParts[0])) ? intval($clientInfoParts[0]) : null;
        $clientInfo['height'] = (strlen($clientInfoParts[1])) ? intval($clientInfoParts[1]) : null;
        $clientInfo['colordepth'] = (strlen($clientInfoParts[2])) ? intval($clientInfoParts[2]) : null;
        $clientInfo['timezoneoffset'] = (strlen($clientInfoParts[3])) ? intval(intval($clientInfoParts[3]) / 15) : null;
        return $clientInfo;
    }

    public function login() {
        # If the user's already logged in,
        # redirect them to the index page
        if ($this->request->user) {
            return new Redirect('/');
        }

        # Abort if no token was provided
        if (empty($this->request->getPostString('token'))) {
            $this->flasher->error("Authentication failure.");
            return new Redirect('/');
        }

        # Check CSRF token
        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.login', 600, '/login');

        $username = $this->request->getPostString('username');
        $password = $this->request->getPostString('password');

        $options = self::parseClientInfo($this->request->getPostString('cinfo'));
        $options['keeploggedin'] = $this->request->getBool('keeploggedin');
        $options['iplocked'] = $this->request->getBool('iplocked');

        try {
            $session = $this->auth->authenticate($username, $password, $options);
            $user = $this->repos->users->load($session->UserID);

            if ($user->isTwoFactorEnabled() && !$session->isTwoFactor()) {
                return new Redirect('/twofactor/login');
            }

            # 303 is weird but it gives a very clear login signal in the logs
            return $this->request->back('/', true, 303);
        } catch (AuthError $e) {
            $this->flasher->error($e->publicDescription, ['username' => $username]);
            return new Redirect($e->redirect);
        }
    }

    public function twofactorLoginForm() {
        if ($this->request->session->getFlag(SESSION::TWO_FACTOR)) {
            return new Redirect('/');
        }
        $user = $this->request->user;
        if (!$user->isTwoFactorEnabled()) {
            throw new AuthError('Unauthorized', 'Two Factor Authentication is not enabled on this account', '/');
        }

        # Force public page display
        $this->request->session = null;
        $this->request->user = null;

        return new Rendered('@Authentication/twofactorLogin.html.twig', ['user' => $user]);
    }

    public function twofactorLogin() {
        $token = $this->request->getPostString('token');
        $code = $this->request->getPostString('code');

        if (empty($token) || empty($code)) {
            $this->flasher->error("Authentication failure.");
            return new Redirect('/twofactor/login');
        }
        try {
            $this->secretary->checkToken($token, 'user.twofactor.login', 600);
        } catch (UserError $e) {
            throw new AuthError($e->publicMessage, $e->publicDescription, '/twofactor/login');
        }

        $user = $this->request->user;

        $this->auth->twofactorAuthenticate($user, $code);
        return $this->request->back();
    }

    public function twofactorRecover() {
        if ($this->request->session->getFlag(SESSION::TWO_FACTOR)) {
            return new Redirect('/');
        }
        $user = $this->request->user;
        $nick = $user->Username;

        # Force public page display
        $this->request->session = null;
        $this->request->user = null;

        $nick = preg_replace('/[^a-zA-Z0-9\[\]\\`\^\{\}\|_]/', '', $nick);
        if (strlen($nick) === 0) {
            $nick = "EmpGuest?";
        }

        $webIRC="{$this->settings->site->help_url}join={$this->settings->irc->disabled_chan}&nick={$nick}";
        return new Rendered(
            '@Authentication/twofactorRecover.html.twig',
            ['web_irc' => $webIRC,
             'irc_server' => $this->settings->irc->server,
             'disabled_chan' => $this->settings->irc->disabled_chan]
        );
    }

    public function logoutTrap() {
        $now      = new \DateTime();
        $user     = $this->request->user;
        $rip      = $this->request->ip;
        $ripcc    = geoip($rip);
        $sqltime  = sqltime();
        $duration = $now->getTimestamp() - $this->request->session->Created->getTimestamp();

        if ($duration < 30 && $user instanceof User) {
            $subject = "Hacked account: malformed logout";

            $message = $this->render->template('bbcode/hack_malformed_logout.twig', [
                'user'     => $user,
                'rip'      => $rip,
                'ripcc'    => $ripcc,
                'duration' => $duration,
            ]);

            # Find the first staff class with IP permission
            $staffClass = $this->repos->permissions->getMinClassPermission('users_view_ips');
            if (!($staffClass === false)) {
                send_staff_pm($subject, $message, $staffClass->Level);
            }

            # Disable user with notes
            $adminComment = "{$sqltime} - Account Enabled->Disabled by System\nReason: Hacked {$rip}\n";
            $this->db->rawQuery(
                "UPDATE users_main as um
                   JOIN users_info AS ui ON um.ID=ui.UserID
                    SET um.Enabled='2',
                        ui.AdminComment = CONCAT(:adminComment, ui.AdminComment)
                  WHERE um.ID=:userID",
                [
                    ':userID'       => $user->ID,
                    ':adminComment' => $adminComment,
                ]
            );
            $this->repos->users->clearSessions($user);
            $this->repos->users->uncache($user->ID);

            # Do the logout
            $this->auth->unauthenticate();
            return new Redirect('/');
        }
        return new Redirect('/');
    }

    public function logout() {
        $token = $this->request->getPostString('token');
        $this->secretary->checkToken($token, 'user.logout', 600, $this->request->back());
        $this->auth->unauthenticate();
        return new Redirect('/');
    }

    public function disabledForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        $flash = $this->flasher->grabFlashes();
        if (!empty($flash)) $flash = $flash[0];

        $nick = '';
        if (isset($flash->data->username)) $nick = $flash->data->username;

        $nick = preg_replace('/[^a-zA-Z0-9\[\]\\`\^\{\}\|_]/', '', $nick);
        if (strlen($nick) === 0) {
            $nick = "EmpGuest?";
        }

        $nick = "disabled_$nick";

        $webIRC="{$this->settings->site->help_url}join={$this->settings->irc->disabled_chan}&nick={$nick}";
        return new Rendered(
            '@Authentication/disabled.html.twig',
            ['web_irc' => $webIRC,
             'irc_server' => $this->settings->irc->server,
             'disabled_chan' => $this->settings->irc->disabled_chan]
        );
    }

    public function pwnedForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        $flash = $this->flasher->grabFlashes();
        if (!empty($flash)) $flash = $flash[0];

        $nick = '';
        if (isset($flash->data->username)) $nick = $flash->data->username;

        $nick = preg_replace('/[^a-zA-Z0-9\[\]\\`\^\{\}\|_]/', '', $nick);
        if (strlen($nick) === 0) {
            $nick = "EmpGuest?";
        }

        $nick = "pwned_$nick";

        $webIRC="{$this->settings->site->help_url}join={$this->settings->irc->disabled_chan}&nick={$nick}";
        return new Rendered(
            '@Authentication/pwned.html.twig',
            ['web_irc' => $webIRC,
             'irc_server' => $this->settings->irc->server,
             'disabled_chan' => $this->settings->irc->disabled_chan]
        );
    }

    public function reactivateForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }

        return new Rendered(
            '@Authentication/reactivate.html.twig',
            []
        );
    }

    public function reactivate() {
        # If the user's already logged in,
        # redirect them to the index page
        if ($this->request->user) {
            return new Redirect('/');
        }

        # Abort if no token was provided
        if (empty($this->request->getPostString('token'))) {
            $this->flasher->error("Authentication failure.");
            return new Redirect('/');
        }

        $this->secretary->checkToken($this->request->getPostString('token'), 'auth.reactivate', 600);

        $username = $this->request->getPostString('username');
        if (empty($username)) {
            throw new InputError('You must provide a username');
        }

        $emailAddress = $this->request->getPostString('email');
        if (empty($emailAddress)) {
            throw new InputError('You must provide an email address');
        }

        $information = $this->request->getPostString('information');
        if (empty($information)) {
            throw new InputError('You must provide an inactivity estimate');
        }

        $proof = $this->request->getPostString('proof');
        //Not warning at this time
        //if (empty($proof)) {
        //    $this->flasher->notice('You did not enter at least one tracker profile link. It is not required but increases your chances.');
        //}
        $proofTwo = $this->request->getPostString('proofTwo');
        $proofThree = $this->request->getPostString('proofThree');
        $questionOne = $this->request->getPostString('customQuestion');
        $questionTwo = $this->request->getPostString('customQuestionTwo');
        $questionThree = $this->request->getPostString('customQuestionThree');

        # Load the entities
        $user = $this->repos->users->getByUsername($username);
        $email = $this->repos->emails->getByAddress($emailAddress);

        # Silently fail for unregistered or cancelled email addresses
        if (is_null($email)) {
            $this->guardian->logAttempt('reactivate', 0);
            $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
            return new Redirect("/");
        } elseif ($email->getFlag(Email::CANCELLED)) {
            $this->guardian->logAttempt('reactivate', $email->UserID);

            # Check if we loaded a user via username or not.
            if (!($user instanceof User)) {
                # Load via email
                $user = $this->repos->users->load($email->UserID);
            }

            # Something really quite wrong here...
            # TODO Work out a good way of logging this without throwing an obvious error to the user
            if (!($user instanceof User)) {
                $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
            }

            $ip = $this->request->ip;
            $subject = "Public Request Alert: User sent request with a deleted email";

            $message = $this->render->template('bbcode/invalid_reactivation.twig', [
                'user'          => $user,
                'username'      => $username,
                'email'         => $email,
                'emailAddress'  => $emailAddress,
                'information'   => $information,
                'ip'            => $ip,
                'info'          => $information,
                'proof'         => $proof,
                'proofTwo'      => $proofTwo,
                'proofThree'    => $proofThree,
            ]);

            $staffClass = $this->repos->permissions->getMinClassPermission('users_view_email');
            if (!($staffClass === false)) {
                send_staff_pm($subject, $message, $staffClass->Level);
            }
            $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
            return new Redirect("/");
        } else {
            $this->guardian->logAttempt('reactivate', $email->UserID);
        }

        # Check if we loaded a user or not.
        if (!($user instanceof User)) {
            $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
            return new Redirect("/");
        }

        # Check is the email belongs to the user or not.
        if (!($user->ID === $email->UserID)) {
            $ip = $this->request->ip;
            # If they don't match then send a StaffPM and silently fail the request
            $subject = "Duplicate account: mixed reactivation information";

            $message = $this->render->template('bbcode/mixed_reactivation_information.twig', [
                'user'     => $user,
                'email'    => $email,
                'ip'       => $ip,
                'info'     => $information,
                'proof'    => $proof,
                'proofTwo'   => $proofTwo,
                'proofThree' => $proofThree,
                'questionOne' => $questionOne,
                'questionTwo' => $questionTwo,
                'questionThree' => $questionThree,
            ]);

            $staffClass = $this->repos->permissions->getMinClassPermission('users_view_email');
            if (!($staffClass === false)) {
                send_staff_pm($subject, $message, $staffClass->Level);
            }
            $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
            return new Redirect("/");
        }

        # Check if user really is disabled
        if ($user->legacy['Enabled'] === '1') {
            return new Redirect("/");
        }

        # Check if account was disabled for inactivity
        if (!(in_array($user->legacy['BanReason'], ['0', '1', '3']))) {
            $ip = $this->request->ip;
            $subject = "Public Request Alert: User sent an invalid request";

            $message = $this->render->template('bbcode/invalid_reactivation.twig', [
                'user'       => $user,
                'email'      => $email,
                'ip'         => $ip,
                'info'       => $information,
                'proof'      => $proof,
                'proofTwo'   => $proofTwo,
                'proofThree' => $proofThree,
            ]);

            $staffClass = $this->repos->permissions->getMinClassPermission('users_view_email');
            if (!($staffClass === false)) {
                send_staff_pm($subject, $message, $staffClass->Level);
            }
            $convID = $this->db->rawQuery("SELECT ID FROM staff_pm_conversations ORDER BY ID DESC LIMIT 1")->fetchColumn();
            $sqltime = sqltime();
            $comment = "User submitted an invalid public request: ";
            $comment .= "/staffpm.php?action=viewconv&id={$convID}";
            $this->db->rawQuery(
                'UPDATE users_info
                    SET AdminComment = CONCAT_WS(CHAR(10 using utf8), ?, AdminComment)
                  WHERE UserID = ?',
                ["$sqltime - $comment", $user->ID]
            );
            throw new AuthError('Unauthorized', 'Account is disabled', '/disabled');
        }

        # Check if a request already exists
        $publicRequest = $this->repos->publicRequests->get('UserID = ? AND Date >= ?', [$user->ID, $user->legacy['BanDate']], "public_request_{$user->ID}");
        if ($publicRequest instanceof PublicRequest) {
            switch ($publicRequest->Status) {
                case 'New':
                    throw new UserError('Reactivation request already in progress', null, '/disabled');
                case 'Rejected':
                    throw new UserError('Reactivation request already rejected', null, '/disabled');
                case 'Summoned':
                    throw new UserError('We need more information from you in order to process your request, please join our IRC help channel', null, '/disabled');
                default:
                    throw new UserError('Reactivation request already approved', null, '/');
            }
        }

        $this->repos->publicRequests->reactivate($user, $email, $information, $proof, $proofTwo, $proofThree, $questionOne, $questionTwo, $questionThree);
        $this->cache->deleteValue("_query_reactivate_{$user->ID}");

        # Populate email_body stuff first
        $subject = 'Account Reactivation';
        $variables = [];
        $variables['user']     = $user;
        $variables['scheme']   = $this->request->ssl ? 'https' : 'http';

        $email->sendEmail($subject, 'account_reactivation', $variables);
        write_user_log($user->ID, 'Someone requested account reactivation');

        $this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
        return new Redirect('/');
    }

    public function applicationForm() {
        if ($this->request->user) {
            return new Redirect('/');
        }

        return new Rendered(
            '@Authentication/application.html.twig',
            []
        );
    }

    public function application() {
        # If the user's already logged in,
        # redirect them to the index page
        if ($this->request->user) {
            return new Redirect('/');
        }

        # Abort if no token was provided
        if (empty($this->request->getPostString('token'))) {
            $this->flasher->error("Authentication failure.");
            return new Redirect('/');
        }

        $this->secretary->checkToken($this->request->getPostString('token'), 'auth.application', 600);

        $email = $this->request->getPostString('email');
        if (empty($email)) {
            throw new InputError('You must provide an email address');
        }

        $information = $this->request->getPostString('information');
        if (empty($information)) {
            throw new InputError('You must provide an answer');
        }

        $proof = $this->request->getPostString('proof');
        if (empty($proof) && $this->options->ActivationProofs) {
            throw new InputError('You must provide tracker proofs');
        }
        $proofTwo = $this->request->getPostString('proofTwo');
        if (empty($proofTwo) && $this->options->ActivationProofs) {
            throw new InputError('You must provide tracker proofs');
        }
        $proofThree = $this->request->getPostString('proofThree');
        $questionOne = $this->request->getPostString('customQuestion');
        $questionTwo = $this->request->getPostString('customQuestionTwo');
        $questionThree = $this->request->getPostString('customQuestionThree');

        # Silently fail for unregistered or cancelled email addresses
        $emailSearch = trim($this->request->getString('email'));
        try {
            $this->emailManager->validate($emailSearch);
        } catch (InputError $e) {
            if ($e->getMessage() === "That email address is not available.") {
                $subject = 'User submitted an application with an exisiting email';
                $message = $this->render->template(
                    'bbcode/duplicate_email_applicant.twig',
                    [
                      'Email'           => (string) $email,
                      'Username'        => false,
                      'IP'              => $this->master->request->ip,
                      'CurrentTime'     => sqltime(),
                      'information'     => $information,
                      'proof'           => $proof,
                      'proofTwo'        => $proofTwo,
                      'proofThree'      => $proofThree,
                      'questionOne'     => $questionOne,
                      'questionTwo'     => $questionTwo,
                      'questionThree'   => $questionThree,
                    ]
                );

                $staffClass = $this->repos->permissions->getMinClassPermission('users_view_ips');
                if ($staffClass === false) {
                    throw $e;
                }

                send_staff_pm($subject, $message, $staffClass->Level);

                //$this->emailManager->sendApplicationEmail($email, 'email/application_summoned.email.twig');
                //$notes = $this->request->getPostString('notes');

                //$request = $this->repos->publicRequests->load($ID);
                //$request->summonApplicant($notes, $email, $ID)
            }
            $nick = "Applicant" . rand(0, 99);
            $webIRC="{$this->settings->site->help_url}join={$this->settings->irc->disabled_chan}&nick={$nick}";
            return new Rendered(
                '@Authentication/applicant_summon.html.twig',
                ['web_irc' => $webIRC,
                'irc_server' => $this->settings->irc->server,
                'disabled_chan' => $this->settings->irc->disabled_chan,
                'nick'  => $nick]
            );
            //$this->flasher->notice("Please visit our IRC to discuss ".$webIRC);
        }

        #TODO Check if a request already exists

        $this->repos->publicRequests->application($email, $information, $proof, $proofTwo, $proofThree, $questionOne, $questionTwo, $questionThree);
        $this->cache->deleteValue("_query_application_{$email}");

        //$subject = 'Account Application';
        //$variables['scheme']   = $this->request->ssl ? 'https' : 'http';
        //$this->email->sendEmail($subject, 'account_application', $variables);
        //$this->flasher->notice("An e-mail with further instructions has been sent to the entered address, provided it exists on {$this->settings->main->site_name}.");
        $this->flasher->notice("An email will be sent to the address you have provided once a decision has been made.");
        return new Redirect('/');
    }

    private function publicRequestsStats() {
        $this->auth->checkAllowed('admin_reports');

        $stats = [];

        $stats['Day'] = $this->db->rawQuery(
            'SELECT StaffID,
                    COUNT(ID) AS Count
               FROM public_requests
              WHERE Date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
           GROUP BY StaffID
           ORDER BY Count DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $stats['Week'] = $this->db->rawQuery(
            'SELECT StaffID,
                    COUNT(ID) AS Count
               FROM public_requests
              WHERE Date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
           GROUP BY StaffID
           ORDER BY Count DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $stats['Month'] = $this->db->rawQuery(
            'SELECT StaffID,
                    COUNT(ID) AS Count
               FROM public_requests
              WHERE Date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
           GROUP BY StaffID
           ORDER BY Count DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $stats['Total'] = $this->db->rawQuery(
            'SELECT StaffID,
                    COUNT(ID) AS Count
               FROM public_requests
           GROUP BY StaffID
           ORDER BY Count DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $stats['Status'] = $this->db->rawQuery(
            'SELECT Status,
                    COUNT(ID) AS Count
               FROM public_requests
           GROUP BY Status
           ORDER BY Count DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $stats['Type'] = $this->db->rawQuery(
            'SELECT Type,
                    COUNT(ID) AS Count
               FROM public_requests
           GROUP BY Type
           ORDER BY Count DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $stats['All'] = $this->db->rawQuery(
            'SELECT COUNT(ID) AS Count
               FROM public_requests'
        )->fetchColumn();

        $stats['TtlApp'] = $this->db->rawQuery(
            'SELECT COUNT(ID) AS Count
               FROM public_requests
               WHERE Type = "Application"'
        )->fetchColumn();

        $stats['AllApp'] = $this->db->rawQuery(
            'SELECT Status,
                    COUNT(ID) AS Count
               FROM public_requests
               WHERE Type = "Application"
            GROUP BY Status
            ORDER BY Count DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $stats['TtlReac'] = $this->db->rawQuery(
            'SELECT COUNT(ID) AS Count
               FROM public_requests
               WHERE Type = "Reactivate"'
        )->fetchColumn();

        $stats['AllReact'] = $this->db->rawQuery(
            'SELECT Status,
                    COUNT(ID) AS Count
               FROM public_requests
               WHERE Type = "Reactivate"
            GROUP BY Status
            ORDER BY Count DESC'
        )->fetchAll(\PDO::FETCH_ASSOC);

        return new Rendered('@Authentication/public_requests_stats.html.twig', ['stats' => $stats]);
    }

    public function publicRequestsForm($action) {
        $this->auth->checkAllowed('users_disable_users');

        $pageSize = $this->settings->pagination->reports;
        list($page, $limit) = page_limit($pageSize);

        switch ($action) {
            case 'new':
                list($requests, $count) = $this->repos->publicRequests->getOpenRequests($limit);
                break;

            case 'old':
                $userID = $this->request->getGetInt('userid');
                $staffID = $this->request->getGetInt('staffid');
                $status = $this->request->getGetString('status');
                $type = $this->request->getGetString('type');
                list($requests, $count) = $this->repos->publicRequests->getResolvedRequests($limit, $userID, $staffID, $status, $type);
                break;

            case 'all':
                $userID = $this->request->getGetInt('userid');
                $staffID = $this->request->getGetInt('staffid');
                $status = $this->request->getGetString('status');
                $type = $this->request->getGetString('type');
                list($requests, $count) = $this->repos->publicRequests->getAllRequests($limit, $userID, $staffID, $status, $type);
                break;

            case 'stats':
                return $this->publicRequestsStats();

            default:
                throw new UserError('Invalid Action');
        }

        foreach ((array)$requests as &$request) {
            $ip1 = $request->ip;
            $ip2 = $request->user->ip;
            $request->user->reactivationRequests = $this->repos->publicRequests->getRequestCountByUser($request->user->ID);

            if ($ip1 instanceof IP && $ip2 instanceof IP) {
                $geo1 = GeoPHP::load($this->db->rawQuery(
                    'SELECT AsText(Coordinates) FROM geolite2 WHERE ID = ?',
                    [$ip1->geoip->ID]
                )->fetchColumn());
                $geo2 = GeoPHP::load($this->db->rawQuery(
                    'SELECT AsText(Coordinates) FROM geolite2 WHERE ID = ?',
                    [$ip2->geoip->ID]
                )->fetchColumn());
                if ($geo1 instanceof Point && $geo2 instanceof Point) {
                    $line = new LineString([$geo1, $geo2]);
                    $request->distance = $line->greatCircleLength()/1609.34;
                } else {
                    $request->distance = null;
                }
                if ($ip1->network instanceof GeoLite2ASN && $ip2->network instanceof GeoLite2ASN) {
                    $request->matchingISP = ($ip1->network->ISP === $ip2->network->ISP);
                } else {
                    $request->matchingISP = false;
                }
            }
        }

        $matches = $this->db->rawQuery(
            'SELECT email.UserID as UserID, pub.ID as ID, email.Address as Address, user.Username
            FROM emails as email
            JOIN public_requests as pub
            JOIN users AS user on user.ID = email.UserID
            WHERE email.Address = pub.ApplicantEmail'
        )->fetchAll();

        $bscripts = ['bbcode'];
        $params = [
            'bscripts'     => $bscripts,
            'page'         => $page,
            'pageSize'     => $pageSize,
            'requests'     => $requests,
            'requestCount' => $count,
            'matches'      => $matches,
        ];

        return new Rendered('@Authentication/public_requests.html.twig', $params);
    }

    public function publicRequests(int $ID) {
        $this->auth->checkAllowed('users_disable_users');

        $this->secretary->checkToken($this->request->getPostString('token'), 'manage.public_requests', 600);
        $action = $this->request->getPostString('action');
        $type = $this->request->getPostString('type');
        $email = $this->request->getPostString('email');
        $notes = $this->request->getPostString('notes');

        $request = $this->repos->publicRequests->load($ID);
        if (!$request instanceof PublicRequest) {
            throw new NotFoundError();
        }

        if ($type === "Reactivate") {
            switch ($action) {
                case 'Accept':
                    $request->accept($notes);
                    break;

                case 'Reject':
                    $request->reject($notes);
                    break;

                case 'Summon':
                    $request->summon($notes);
                    break;

                default:
                    throw new UserError('Invalid Action');
            }
        } elseif ($type === "Application") {
            switch ($action) {
                case 'Accept':
                    $request->acceptApplicant($ID, $notes, $email);
                    break;

                case 'Send Invite Email':
                    $request->acceptSummApplicant($ID, $notes, $email);
                    break;

                case 'Reject':
                    $request->rejectApplicant($notes, $email, $ID);
                    break;

                case 'Summon':
                    $request->summonApplicant($notes, $email, $ID);
                    break;

                default:
                    throw new UserError('Invalid Action');
            }
        }

        return new Redirect('/manage/requests/new');
    }
}
