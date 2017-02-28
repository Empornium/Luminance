<?php
namespace Luminance\Core;

use Luminance\Errors\Error;
use Luminance\Errors\UserError;
use Luminance\Errors\AdminError;
use Luminance\Errors\AuthError;
use Luminance\Errors\SystemError;
use Luminance\Errors\InternalError;
use Luminance\Errors\CLIError;
use Luminance\Errors\NotFoundError;

use Luminance\Responses\Redirect;
use Luminance\Responses\Rendered;
use Luminance\Responses\Response;


class Master {

    public $application_path;
    public $superglobals;
    public $legacy_handler;
    public $ssl;

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>

        // CLI stuff
        [ 'CLI',  'peerupdate',      0, 'legacy', 'peerupdate' ],
        [ 'CLI',  'btcupdate',       0, 'legacy', 'btcupdate'  ],
        [ 'CLI',  'schedule',        0, 'legacy', 'schedule'   ],
//        [ 'CLI',  'script',          0, 'legacy', 'script'     ],

        // Redirects
        [ '*',    'browse.php',      0, 'redirect_handler',        'torrents.php'               ],
        [ '*',    'collage.php',     0, 'redirect_handler_params', 'collages.php'               ],
        [ '*',    'details.php',     0, 'redirect_handler_params', 'torrents.php'               ],
        [ '*',    'whitelist.php',   0, 'redirect_handler',        'articles.php?topic=clients' ],

        // Legacy sections
//        [ '*',    '',                0, 'legacy', 'index'       ],
        [ '*',    'ajax.php',        2, 'legacy', 'ajax'        ],
        [ '*',    'articles.php',    2, 'legacy', 'articles'    ],
        [ '*',    'blog.php',        2, 'legacy', 'blog'        ],
        [ '*',    'bonus.php',       2, 'legacy', 'bonus'       ],
        [ '*',    'bookmarks.php',   2, 'legacy', 'bookmarks'   ],
        [ '*',    'captcha.php',     2, 'legacy', 'captcha'     ],
        [ '*',    'chat.php',        2, 'legacy', 'chat'        ],
        [ '*',    'collages.php',    2, 'legacy', 'collages'    ],
        [ '*',    'donate.php',      2, 'legacy', 'donate'      ],
        [ '*',    'error.php',       2, 'legacy', 'error'       ],
        [ '*',    'feeds.php',       0, 'legacy', 'feeds'       ],
        [ '*',    'forums.php',      2, 'legacy', 'forums'      ],
        [ '*',    'friends.php',     2, 'legacy', 'friends'     ],
        [ '*',    'groups.php',      2, 'legacy', 'groups'      ],
        [ '*',    'inbox.php',       2, 'legacy', 'inbox'       ],
        [ '*',    'index.php',       2, 'legacy', 'index'       ],
        [ '*',    'irc.php',         2, 'legacy', 'irc'         ],
        [ '*',    'log.php',         2, 'legacy', 'log'         ],
        [ '*',    'opensearch.php',  2, 'legacy', 'opensearch'  ],
        [ '*',    'reports.php',     2, 'legacy', 'reports'     ],
        [ '*',    'reportsv2.php',   2, 'legacy', 'reportsv2'   ],
        [ '*',    'requests.php',    2, 'legacy', 'requests'    ],
        [ '*',    'sandbox.php',     2, 'legacy', 'sandbox'     ],
        [ '*',    'staff.php',       2, 'legacy', 'staff'       ],
        [ '*',    'staffblog.php',   2, 'legacy', 'staffblog'   ],
        [ '*',    'staffpm.php',     2, 'legacy', 'staffpm'     ],
        [ '*',    'stats.php',       2, 'legacy', 'stats'       ],
        [ '*',    'tags.php',        2, 'legacy', 'tags'        ],
        [ '*',    'tools.php',       2, 'legacy', 'tools'       ],
        [ '*',    'top10.php',       2, 'legacy', 'top10'       ],
        [ '*',    'torrents.php',    0, 'legacy', 'torrents'    ], // AutoDL links
        [ '*',    'upload.php',      2, 'legacy', 'upload'      ],
        [ '*',    'user.php',        2, 'legacy', 'user'        ],
        [ '*',    'userhistory.php', 2, 'legacy', 'userhistory' ],
    ];


    protected $builtinNames = [
        'Setup',
        'Authentication',
        'Users',
    ];

    protected $repositories = [];
    protected $services = [];
    protected $plugins = [];


    public function __construct($application_path, array $superglobals, $start_time = null) {
        $this->start_time = $start_time;
        $this->application_path = $application_path;
        $this->base_path = dirname($this->application_path);
        $this->library_path = $this->base_path . '/library';
        $this->public_path = $this->base_path . '/public';

        $this->superglobals = $superglobals;
        $this->server = $this->superglobals['server'];

        if (!$this->settings->modes->profiler) {
            $this->profiler->disable();
        }
        $this->request = new Request($this);
        $this->registerPlugins();

        require_once($this->application_path . '/common/main_functions.php');
        require_once($this->application_path . '/common/time_functions.php');
        require_once($this->application_path . '/common/paranoia_functions.php');

    }

    public function getRepository($name) {
        if (!array_key_exists($name, $this->repositories)) {
            $cls = "\\Luminance\\Repositories\\{$name}";
            $this->repositories[$name] = new $cls($this);
        }
        return $this->repositories[$name];
    }

    public function getService($name) {
        if (!array_key_exists($name, $this->services)) {
            $cls = "\\Luminance\\Services\\{$name}";
            $this->services[$name] = new $cls($this);
        }
        return $this->services[$name];
    }

    public function getPlugin($name) {
        if (!array_key_exists($name, $this->plugins)) {
            $cls = "\\Luminance\\Builtins\\{$name}\\{$name}Plugin";
            $this->plugins[$name] = new $cls($this);
        }
        return $this->plugins[$name];
    }

    public function run() {
        try {
            if ($this->request->method === 'CLI') {
                $errorTemplate = 'clierror.html';
                $this->request->authLevel = 0;
            } else {
                $errorTemplate = 'error.html';
                $this->auth->checkSession();
                $this->log->log_request($this->request);
            }
            $response = $this->handle_request($this->request);
            $this->process_response($response);
            return null; # We're done now

        } catch (AuthError $e) {
            $response = new Rendered($errorTemplate, $e->get_template_vars(), $e->http_status);

        } catch (UserError $e) {
            $response = new Rendered($errorTemplate, $e->get_template_vars(), $e->http_status);

        } catch (Error $e) {
            $response = new Rendered($errorTemplate, $e->get_template_vars(), $e->http_status);
            error_log("Caught " . get_class($e) . ": ". $e->getMessage() . "\n" . $e->getTraceAsString());

        } catch (\Exception $e) {
            # This one was thrown by PHP itself, it won't follow our Error structure
            $response = new Rendered($errorTemplate, ['message'=>'Internal Server Error', 'http_status'=>500], 500);
            error_log("Caught " . get_class($e) . ": ". $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        # This point is only reached if an exception was thrown
        $this->process_response($response);

    }

    public function process_response($response) {
        if ($response instanceof Rendered) {
            http_response_code($response->status);
            $this->peon->display($response->template, $response->variables);

        } elseif ($response instanceof Redirect) {
            $this->redirect($response->target, $response->parameters, $response->status);

        } elseif ($response instanceof Response) {
            http_response_code($response->status);
            print($response->content);
            if (!$this->request->cli) {
                ob_end_flush();
            }
        } else {
            throw new InternalError("Invalid response passed to Master->process_response().");
            if (!$this->request->cli) {
                ob_end_flush();
            }
        }

    }

    public function __get($name) {
        switch ($name) {
            case 'auth':
            case 'cache':
            case 'crypto':
            case 'guardian':
            case 'log':
            case 'peon':
            case 'profiler':
            case 'repos':
            case 'router':
            case 'secretary':
            case 'emailManager':
            case 'settings':
                return $this->getService(ucfirst($name));
            case 'db':
            case 'orm':
            case 'tpl':
                return $this->getService(strtoupper($name));
            case 'olddb':
                return $this->getService('OldDB');
            default:
                throw new InternalError("Attempt to access undefined \$master->{$name}");
        }
    }

    public function prependRoute($route) {
        array_unshift($this->routes, $route);
    }

    protected function registerPlugins() {
        foreach ($this->builtinNames as $builtinName) {
            $pluginClass = "\\Luminance\\Builtins\\{$builtinName}\\{$builtinName}Plugin";
            #$plugin = new $pluginClass($this);
            $tplPath = $this->application_path . "/Builtins/{$builtinName}/templates";
            if (is_dir($tplPath)) {
                $this->tpl->add_template_path($tplPath, $builtinName);
            }
            $pluginClass::register($this);
            #$this->plugins[$builtinName] = $plugin;
        }
    }

    public function handle_request($request) {
        if (!$this->request->cli) {
            ob_start();
            $this->handle_trivial_cases();
        }
        $route_match = $this->router->resolve($this->routes, $this->request, $this->request->path);
        if (is_array($route_match)) {
            $func = $route_match[0];
            $authLevel = $route_match[1];
            $args = array_slice($route_match, 2);
            if ($this->request->authLevel < $authLevel)
                throw new AuthError("Insufficient authentication level", "Unauthorized");
            if (method_exists($this, $func)) {
                return call_user_func_array(array($this, $func), $args);
            } else {
                throw new InternalError("Route resolved to nonexistant Master method: {$func}");
            }
        } else {
            throw new NotFoundError();
        }
    }

    public function plugin($pluginName, $path = []) {
        $args = func_get_args();
        $pluginName = $args[0];
        $path = array_slice($args, 1);
        $plugin = $this->getPlugin($pluginName);
        return call_user_func_array(array($plugin, 'handle_path'), $path);
    }

    # Legacy style route handler
    public function legacy($section) {
        $this->settings->set_legacy_constants();
        $legacy_handler = new LegacyHandler($this);
        return $legacy_handler->handle_legacy_request($section);
    }

    public function handle_trivial_cases() {
        //Deal with dumbasses
        if (isset($this->request->values['info_hash']) && isset($this->request->values['peer_id'])) {
            die('d14:failure reason40:Invalid .torrent, try downloading again.e');
        }
        $url_path = basename(parse_url($this->server['SCRIPT_NAME'], PHP_URL_PATH));
        if ($url_path == 'announce.php' || $url_path == 'scrape.php') {
            print("d14:failure reason40:Invalid .torrent, try downloading again.e\n");
            exit;
        }
        $http_host = $this->server['HTTP_HOST'];
        $request_uri = $this->server['REQUEST_URI'];
        $nonssl_url = $this->settings->main->nonssl_site_url;
        $ssl_url = $this->settings->main->ssl_site_url;

        if (!$this->request->ssl && $http_host == "www.{$nonssl_url}") {
            $this->redirect("http://{$nonssl_url}{$request_uri}");
        }
        if ($this->request->ssl && $http_host == "www.{$nonssl_url}") {
            $this->redirect("https://{$ssl_url}{$request_uri}");
        }
        if ($ssl_url != $nonssl_url && (
            (!$this->request->ssl && $http_host == $ssl_url) ||
            ($this->request->ssl && $http_host == $nonssl_url)
        )) {
            $this->redirect("https://{$ssl_url}{$request_uri}");
        }
    }

    public function redirect($target, $parameters = null, $status = 301) {
        if (is_array($parameters) && count($parameters)) {
            $query_string = '?' . http_build_query($parameters);
        } elseif (strlen($parameters)) {
            $query_string = '?' . strval($parameters);
        } else {
            $query_string = '';
        }
        header('Location: ' . $target . $query_string, true, $status);
        exit();
    }

    public function redirect_handler($path) {
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        return $this->redirect('/' . $path);
    }

    public function redirect_handler_params($path) {
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        return $this->redirect('/' . $path, $this->request->values);
    }

}
