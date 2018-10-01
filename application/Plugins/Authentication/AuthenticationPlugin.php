<?php
namespace Luminance\Plugins\Authentication;

use Luminance\Core\Master;
use Luminance\Core\Plugin;
use Luminance\Errors\UserError;
use Luminance\Errors\AuthError;
use Luminance\Entities\Session;
use Luminance\Responses\Redirect;
use Luminance\Responses\Response;
use Luminance\Responses\Rendered;

class AuthenticationPlugin extends Plugin {

    protected static $useServices = [
        'auth'      => 'Auth',
        'guardian'  => 'Guardian',
        'flasher'   => 'Flasher',
        'settings'  => 'Settings',
        'secretary' => 'Secretary',
        'security'  => 'Security',
    ];

    protected static $useRepositories = [
        'users'         => 'UserRepository',
    ];

    public $routes = [
        # [method] [path match] [target function] [auth level] <extra arguments>
        [ 'GET',  'login',               0, 'login_form'             ],
        [ 'POST', 'login',               0, 'login'                  ],
        [ 'GET',  'twofactor/login',     2, 'twofactor_login_form'   ],
        [ 'POST', 'twofactor/login',     2, 'twofactor_login'        ],
        [ 'GET',  'twofactor/recover',   2, 'twofactor_recover_form' ],
        [ 'POST', 'twofactor/recover',   2, 'twofactor_recover'      ],
        [ 'POST', 'logout',              2, 'logout'                 ],
        [ 'GET',  'disabled',            0, 'disabled_form'          ],
        [ 'GET',  'pwned',               0, 'pwned_form'             ],
        [ 'HEAD', 'index',               0, 'is_it_up'               ],
        [ 'GET',  'index',               0, 'index'                  ],
    ];

    public static function register(Master $master) {
        $master->prependRoute([ '*', '',                   0, 'plugin', 'Authentication', 'index'             ]);
        $master->prependRoute([ '*', 'index.php',          0, 'plugin', 'Authentication', 'index'             ]);
        $master->prependRoute([ '*', 'login',              0, 'plugin', 'Authentication', 'login'             ]);
        $master->prependRoute([ '*', 'logout',             2, 'plugin', 'Authentication', 'logout'            ]);
        $master->prependRoute([ '*', 'disabled',           0, 'plugin', 'Authentication', 'disabled'          ]);
        $master->prependRoute([ '*', 'pwned',              0, 'plugin', 'Authentication', 'pwned'             ]);
        $master->prependRoute([ '*', 'twofactor/login',    2, 'plugin', 'Authentication', 'twofactor/login'   ]);
        $master->prependRoute([ '*', 'twofactor/recover',  2, 'plugin', 'Authentication', 'twofactor/recover' ]);
    }

    public function is_it_up() {
        return new Rendered('core/base.html.twig', [], 200, 'header');
    }

    public function index() {
        if ($this->request->user) {
            return $this->master->legacy('index');
        } else {
            $csp = [
              'default-src' => ["'self'"],
              'img-src'     => ["'self'"],
              'script-src'  => ["'self'"],
              'object-src'  => ["'self'"],
              'style-src'   => ["'self'", "'unsafe-inline'"],
            ];

            // Format the CSP into a string
            $header = 'Content-Security-Policy: ';
            foreach ($csp as $directive => $rules) {
                $header .= $directive.'  '.implode(' ', $rules).'; ';
            };

            // Send the header
            header(trim($header));
            return new Rendered('@Authentication/public_index.html.twig');
        }
    }

    public function login_form() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        return new Rendered('@Authentication/login.html.twig');
    }

    protected static function parse_cinfo($CInfoString) {
        $CParts = explode('|', $CInfoString);

        if (count($CParts) != 4) {
            $CParts = ['', '', '', ''];
        }
        $CInfo = [];
        $CInfo['width'] = (strlen($CParts[0])) ? intval($CParts[0]) : null;
        $CInfo['height'] = (strlen($CParts[1])) ? intval($CParts[1]) : null;
        $CInfo['colordepth'] = (strlen($CParts[2])) ? intval($CParts[2]) : null;
        $CInfo['timezoneoffset'] = (strlen($CParts[3])) ? intval(intval($CParts[3]) / 15) : null;
        return $CInfo;
    }

    public function login() {
        // If the user's already logged in,
        // redirect them to the index page
        if ($this->request->user) {
            return new Redirect('/');
        }

        // Abort if no token was provided
        if (!isset($this->request->post['token'])) {
            $this->flasher->error("Authentication failure.");
            return new Redirect('/');
        }

        // Check CSRF token
        $token = $this->request->post['token'];
        $this->secretary->checkToken($token, 'users.login', 600);

        $Username = $this->request->post['username'];
        $Password = $this->request->post['password'];

        //
        $Options = self::parse_cinfo($this->request->post['cinfo']);
        $Options['keeploggedin'] = $this->request->get_bool('keeploggedin');
        $Options['iplocked'] = $this->request->get_bool('iplocked');

        try {
            $Session = $this->auth->authenticate($Username, $Password, $Options);
            $user = $this->users->load($Session->UserID);

            if (isset($user->twoFactorSecret) && !$Session->getFlag(SESSION::TWO_FACTOR)) {
                return new Redirect('/twofactor/login');
            }

            return $this->request->back();
        } catch (AuthError $e) {
            $this->flasher->error($e->public_message, ['username' => $Username]);
            return new Redirect($e->redirect);
        }
    }

    public function twofactor_login_form() {
        if ($this->request->session->getFlag(SESSION::TWO_FACTOR)) {
            return new Redirect('/');
        }
        $user = $this->request->user;
        if (!isset($user->twoFactorSecret)) {
            throw new AuthError('Two Factor Authentication is not enabled on this account', 'Unauthorized', '/');
        }

        // Force public page display
        $this->request->session = null;
        $this->request->user = null;

        return new Rendered('@Authentication/twofactor_login.html.twig', ['user' => $user]);
    }

    public function twofactor_login() {
        if (!isset($this->request->post['token']) || !isset($this->request->post['code'])) {
            $this->flasher->error("Authentication failure.");
            return new Redirect('/twofactor/login');
        }
        try {
            $token=$this->request->post['token'];
            $this->secretary->checkToken($token, 'users.twofactor.login', 600);
        } catch (UserError $e) {
            throw new AuthError($e->public_message, $e->public_description, '/twofactor/login');
        }

        $user = $this->request->user;
        $code = $this->request->post['code'];

        $this->auth->twofactor_authenticate($user, $code);
        return $this->request->back();
    }

    public function twofactor_recover_form() {
        if ($this->request->session->getFlag(SESSION::TWO_FACTOR)) {
            return new Redirect('/');
        }
        $user = $this->request->user;
        $nick = $user->Username;

        // Force public page display
        $this->request->session = null;
        $this->request->user = null;

        $nick = preg_replace('/[^a-zA-Z0-9\[\]\\`\^\{\}\|_]/', '', $nick);
        if (strlen($nick) == 0) {
            $nick = "EmpGuest?";
        }

        $web_irc="{$this->settings->site->help_url}join={$this->settings->irc->disabled_chan}&nick=${nick}";
        return new Rendered(
            '@Authentication/twofactor_recover.html.twig',
            ['web_irc' => $web_irc,
             'irc_server' => $this->settings->irc->server,
             'disabled_chan' => $this->settings->irc->disabled_chan]
        );
    }

    public function twofactor_recover() {
    }

    public function logout() {
        $token=$this->request->post['token'];
        $this->secretary->checkToken($token, 'users.logout', 600);
        $this->auth->unauthenticate();
        return new Redirect('/');
    }

    public function disabled_form() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        $flash = $this->flasher->grabFlashes();
        if (!empty($flash)) $flash = $flash[0];

        $nick = '';
        if (isset($flash->data->username)) $nick = $flash->data->username;

        $nick = preg_replace('/[^a-zA-Z0-9\[\]\\`\^\{\}\|_]/', '', $nick);
        if (strlen($nick) == 0) {
            $nick = "EmpGuest?";
        }

        $nick = "disabled_$nick";

        $web_irc="{$this->settings->site->help_url}join={$this->settings->irc->disabled_chan}&nick=${nick}";
        return new Rendered(
            '@Authentication/disabled.html.twig',
            ['web_irc' => $web_irc,
             'irc_server' => $this->settings->irc->server,
             'disabled_chan' => $this->settings->irc->disabled_chan]
        );
    }

    public function pwned_form() {
        if ($this->request->user) {
            return new Redirect('/');
        }

        return new Rendered('@Authentication/pwned.html.twig');
    }
}
