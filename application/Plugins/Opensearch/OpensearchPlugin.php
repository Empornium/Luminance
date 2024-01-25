<?php
namespace Luminance\Plugins\Opensearch;

use Luminance\Core\Master;
use Luminance\Core\Plugin;

use Luminance\Services\Auth;

use Luminance\Responses\Rendered;

class OpensearchPlugin extends Plugin {
    const ALLOWED_TYPES = [
        'torrents' => ['/torrents.php',           '?searchtext={searchTerms}'],
        'tags'     => ['/torrents.php',           '?taglist={searchTerms}'   ],
        'requests' => ['/requests.php',           '?search={searchTerms}'    ],
        'forums'   => ['/forum/search',           '?terms={searchTerms}'     ],
        'users'    => ['/user.php?action=search', '&search={searchTerms}'    ],
        'log'      => ['/log.php',                '?search={searchTerms}'    ],
    ];

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>
        [ 'GET', '*', Auth::AUTH_NONE, 'opensearch' ],
    ];

    protected static $useServices = [
        'settings'  => 'Settings',
    ];

    public static function register(Master $master) {
        parent::register($master);
        $master->prependRoute(['*', 'opensearch/**', Auth::AUTH_NONE, 'plugin', 'Opensearch' ]);
    }

    public function opensearch() {
        $requestedType = $this->request->getGetString('type');
        $searchType = 'torrents';
        if (array_key_exists($requestedType, self::ALLOWED_TYPES) === true) {
            $searchType = $requestedType;
        }
        $urlBase = 'http://' . $this->settings->main->site_url;
        if ($this->request->ssl === true) {
            $urlBase = 'https://' . $this->settings->main->site_url;
        }
        $templatedSearchUrl = $urlBase . self::ALLOWED_TYPES[$searchType][0] . self::ALLOWED_TYPES[$searchType][1];
        $mozSearchFormUrl = $urlBase . self::ALLOWED_TYPES[$searchType][0];
        $params = [
            'searchType' => $searchType,
            'shortName' => $this->settings->main->site_short_name . ' ' . ucfirst($searchType),
            'description' => 'Search ' . $this->settings->main->site_name . ' for ' . ucfirst($searchType),
            'urlBase' => $urlBase,
            'templatedSearchUrl' => $templatedSearchUrl,
            'mozSearchFormUrl' => $mozSearchFormUrl,
        ];
        header('Content-type: application/opensearchdescription+xml');
        return new Rendered('@Opensearch/search.xml.twig', $params);
    }
}
