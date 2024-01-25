<?php
namespace Luminance\Plugins\Sandbox;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Services\Auth;

use Luminance\Responses\Rendered;

class SandboxPlugin extends Plugin {

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET',  '*',       Auth::AUTH_LOGIN,  'sandbox' ],
        [ 'GET',  'smilies', Auth::AUTH_LOGIN,  'smilies' ],
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute([ '*', 'sandbox/**', Auth::AUTH_LOGIN, 'plugin', 'Sandbox' ]);
    }

    public function sandbox() {
        return new Rendered('@Sandbox/sandbox.html.twig', ['bscripts'  => ['bbcode']]);
    }

    public function smilies() {
        $sort  = $this->request->getbool('sort');
        $order = $this->request->getbool('asc');

        $params = [
            'bscripts'  => ['bbcode'],
            'sort'      => $sort,
            'order'     => $order,
        ];

        return new Rendered('@Sandbox/smilies.html.twig', $params);
    }
}
