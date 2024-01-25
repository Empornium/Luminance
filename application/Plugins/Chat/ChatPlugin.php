<?php
namespace Luminance\Plugins\Chat;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Services\Auth;

use Luminance\Responses\Rendered;

class ChatPlugin extends Plugin {

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  '*',       Auth::AUTH_LOGIN,  'chat' ],
        [ 'POST', '*',       Auth::AUTH_LOGIN,  'chat' ],
    ];

    protected static $useServices = [
        'secretary' => 'Secretary',
        'settings'  => 'Settings',
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ '*', 'chat/**', Auth::AUTH_LOGIN, 'plugin', 'Chat' ]);
    }

    public function chat() {
        $user    = $this->request->user;

        $connect = $this->request->getPostString('connect');
        $main    = $this->request->getPostBool('main');
        $help    = $this->request->getPostBool('help');
        $staff   = $this->request->getPostBool('staff');
        if ((empty($connect) || (empty($main) && empty($help) && empty($staff))) === true) {
            return new Rendered('@Chat/rules.html.twig');
        } else {
            $token = $this->request->getPostString('token');
            $this->secretary->checkToken($token, 'irc.rules', 1800);
            $nick = $user->Username;

            $nick = preg_replace('/[^a-zA-Z0-9\[\]\\`\^\{\}\|_]/', '', $nick);
            if (strlen($nick) === 0) {
                $nick = "{$this->settings->main->site_short_name}Guest?";
            }

            $channels = '';
            $div = '';
            if ($main && !empty($this->settings->irc->chan)) {
                $channels .= $div . $this->settings->irc->chan;
                $div = ',';
            }
            if ($help && !empty($this->settings->irc->help_chan)) {
                $channels .= $div . $this->settings->irc->help_chan;
                $div=',';
            }
            if ($staff && !empty($this->settings->irc->staff_chan)) {
                if (!empty($user->legacy['SupportFor']) || $user->class->DisplayStaff === '1') {
                      $channels .= $div . $this->settings->irc->staff_chan;
                      $div=',';
                }
            }

            $webIRC="{$this->settings->site->chat_url}join={$channels}&nick={$nick}";
            return new Rendered('@Chat/irc.html.twig', ['web_irc' => $webIRC]);
        }
    }
}
