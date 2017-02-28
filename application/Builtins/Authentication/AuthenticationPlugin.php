<?php
namespace Luminance\Builtins\Authentication;

use Luminance\Core\Master;
use Luminance\Core\Plugin;
use Luminance\Errors\AuthError;
use Luminance\Responses\Redirect;
use Luminance\Responses\Response;
use Luminance\Responses\Rendered;

class AuthenticationPlugin extends Plugin {

    protected static $useServices = [
        'auth'     => 'Auth',
        'guardian' => 'Guardian',
        'flasher'  => 'Flasher',
        'settings' => 'Settings',
    ];

    public $routes = [
        # [method] [path match] [target function] [auth level] <extra arguments>
        [ 'GET',  'login',    0, 'login_form'    ],
        [ 'POST', 'login',    0, 'login'         ],
        [ 'GET',  'logout',   2, 'logout'        ],
        [ 'GET',  'disabled', 0, 'disabled_form' ],
        [ 'GET',  'index',    0, 'index'         ],
    ];

    public static function register(Master $master) {
        $master->prependRoute([ '*', '',          0, 'plugin', 'Authentication', 'index' ]);
        $master->prependRoute([ '*', 'index.php', 0, 'plugin', 'Authentication', 'index' ]);
        $master->prependRoute([ '*', 'login',     0, 'plugin', 'Authentication', 'login' ]);
        $master->prependRoute([ '*', 'logout',    0, 'plugin', 'Authentication', 'logout' ]);
        $master->prependRoute([ '*', 'disabled',  0, 'plugin', 'Authentication', 'disabled' ]);
    }

    public function index() {
        if ($this->request->user) {
            return $this->master->legacy('index');
        } else {
            header('Content-Security-Policy: "default-src \'none\'; img-src \'self\'; script-src \'self\'; object-src \'self\';"');
            return new Rendered('@Authentication/public_index.html');
        }
    }

    public function login_form() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        $this->guardian->check_ip_ban();
        $this->guardian->detect();
        return new Rendered('@Authentication/login.html');
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
        if ($this->request->user) {
            return new Redirect('/');
        }
        $Username = $this->request->post['username'];
        $Password = $this->request->post['password'];
        $Options = self::parse_cinfo($this->request->post['cinfo']);
        $Options['keeploggedin'] = $this->request->get_bool('keeploggedin');
        $Options['iplocked'] = $this->request->get_bool('iplocked');

        try {
            $Session = $this->auth->authenticate($Username, $Password, $Options);
            return new Redirect('/');
        } catch (AuthError $e) {
            $this->flasher->error($e->public_message, ['username' => $Username]);
            return new Redirect($e->redirect);
        }
    }

    public function logout() {
        $this->auth->unauthenticate();
        return new Redirect('/');
    }

    public function disabled_form() {
        if ($this->request->user) {
            return new Redirect('/');
        }
        $this->guardian->check_ip_ban();
        $this->guardian->detect();
	$flash = $this->flasher->grabFlashes()[0];

	$nick = '';
        if (isset($flash->data->username)) $nick = $flash->data->username;

        $nick = preg_replace('/[^a-zA-Z0-9\[\]\\`\^\{\}\|_]/', '', $nick);
        if (strlen($nick) == 0) {
            $nick = "EmpGuest?";
        }

        $nick = "disabled_$nick";

	$web_irc=$this->settings->site->help_url."nick=$nick".$this->settings->irc->disabled_chan;
        return new Rendered('@Authentication/disabled.html',
                                ['web_irc' => $web_irc,
                                 'irc_server' => $this->settings->irc->server,
                                 'disabled_chan' => $this->settings->irc->disabled_chan]);
    }

}
