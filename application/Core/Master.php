<?php
namespace Luminance\Core;

use Luminance\Errors\Error;
use Luminance\Errors\UserError;
use Luminance\Errors\AuthError;
use Luminance\Errors\AdminError;
use Luminance\Errors\InputError;
use Luminance\Errors\BBCodeError;
use Luminance\Errors\SystemError;
use Luminance\Errors\LegacyError;
use Luminance\Errors\InternalError;
use Luminance\Errors\NotFoundError;
use Luminance\Errors\ForbiddenError;
use Luminance\Errors\UnauthorizedError;

use Luminance\Services\Auth;
use Luminance\Services\Debug;

use Luminance\Responses\JSON;
use Luminance\Responses\Redirect;
use Luminance\Responses\Rendered;
use Luminance\Responses\Response;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Master {

    public $applicationPath;
    public $superglobals;
    public $ssl;
    public $request;

    public $routes = [
        # [method] [path match] [auth level] [target function] <extra arguments>

        # CLI stuff
        [ 'CLI',  'tests',           Auth::AUTH_NONE,  'func',   'tests'      ],

        # Small stuff
        [ 'GET',  'render/qr/**',    Auth::AUTH_NONE,  'func', 'renderQrcode' ],

        # Redirects
        [ '*',    'browse.php',      Auth::AUTH_NONE,  'redirectHandler',       'torrents.php'     ],
        [ '*',    'details.php',     Auth::AUTH_NONE,  'redirectHandlerParams', 'torrents.php'     ],
        [ '*',    'whitelist.php',   Auth::AUTH_NONE,  'redirectHandler',       'articles/view/clients' ],
    ];


    protected $repositories = [];
    protected $services = [];
    protected $plugins = [];


    public function __construct($applicationPath, array $superglobals, $startTime = null) {
        # Required for any legacy functions which access Master during construction
        global $master;
        $master = $this;

        $this->startTime = $startTime;
        $this->applicationPath = $applicationPath;
        $this->basePath = dirname($this->applicationPath);
        $this->resourcePath = $this->basePath . '/resources';
        $this->publicPath = $this->basePath . '/public';

        $this->superglobals = $superglobals;
        $this->server = $this->superglobals['server'];

        if (!$this->settings->modes->profiler) {
            $this->profiler->disable();
        }
        try {
            $this->request = new Request($this);

            # Any non-cli request must have an IP, inject it *AFTER* the
            # request object has been created as request is required elsewhere
            # during construction.
            if (!$this->request->cli) {
                $this->request->ip = $this->repos->ips->getOrNew($this->server['REMOTE_ADDR']);
            }
        } catch (SystemError $e) {
            error_log("Caught " . get_class($e) . ": ". $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            die();
        }

        $this->debug->start();
        $this->registerPlugins();

        # For legacy functions included below
        define('SERVER_ROOT', $applicationPath);
        require_once($this->applicationPath . '/common/main_functions.php');
        require_once($this->applicationPath . '/common/time_functions.php');
        require_once($this->applicationPath . '/common/paranoia_functions.php');
    }

    protected function checkExtensions() {
        $extensions = [
            'curl',
            'date',
            'mbstring',
            'memcached',
            'mysqlnd',
            'zip',
        ];
        foreach ($extensions as $extension) {
            if (extension_loaded($extension) === false) {
                $error = "You must install the {$extension} PHP extension.";
                throw new InternalError('', $error);
            }
        }
    }

    public function &getRepository($name) {
        if (!array_key_exists($name, $this->repositories)) {
            $cls = "\\Luminance\\Repositories\\{$name}";
            if (class_exists($cls)) {
                $this->repositories[$name] = new $cls($this);
            } else {
                $this->repositories[$name] = new class($this, $name) extends Repository {
                    protected $entityName;
                    public function __construct(Master $master, $name) {
                        $this->entityName = str_replace('Repository', '', $name);
                        parent::__construct($master);
                    }
                };
            }
            #$this->repositories[$name]->link();
        }
        return $this->repositories[$name];
    }

    public function &getService($name) {
        if (!array_key_exists($name, $this->services)) {
            $cls = "\\Luminance\\Services\\{$name}";
            $this->services[$name] = new $cls($this);
            $this->services[$name]->link();
        }
        return $this->services[$name];
    }

    public function &getPlugin($name) {
        if (!array_key_exists($name, $this->plugins) || !$this->plugins[$name] instanceof Plugin) {
            $cls = "\\Luminance\\Plugins\\{$name}\\{$name}Plugin";
            $this->plugins[$name] = new $cls($this);
            $this->plugins[$name]->link();
        }
        return $this->plugins[$name];
    }

    public function run() {
        try {
            $this->checkExtensions();
            Debug::setFlag('extension check complete');
            if ($this->request->method === 'CLI') {
                $this->request->authLevel = 0;
            } else {
                $this->auth->checkSession();
                Debug::setFlag('login complete');
                $this->log->logRequest($this->request);
                Debug::setFlag('access logged');

                # Disable debug for regular users
                if (!($this->auth->isAllowed('site_debug') || $this->settings->site->debug_mode)) {
                    Debug::disable();
                }
            }
            $response = $this->handleRequest();
            Debug::setFlag('request handled');
            $this->processResponse($response);
            return null; # We're done now

        # AuthError is for login/auth redirects
        } catch (AuthError $e) {
            $this->flasher->error("{$e->publicMessage}: {$e->publicDescription}");
            //$response = new Redirect($e->redirect, null, 303);
            $response =  $this->request->back();


        # These errors should only be seen by users and not logged
        } catch (UserError | AdminError | BBCodeError | InputError | ForbiddenError | UnauthorizedError | LegacyError $e) {
            $response = $this->processError($e);
            #Intended for debugging usage
            $this->irker->deepDebugAnnounce($e);

        # Any other Luminance or PHP exception error should be logged and displayed
        } catch (Error $e) {
            error_log("Caught " . get_class($e) . ": ". $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            $response = $this->processError($e);
            $this->irker->deepDebugPHPAnnounce($e);

        # Internal PHP exceptions
        } catch (\Error | \Exception $e) {
            # This one was thrown by PHP itself, it won't follow our Error structure
            if ($e instanceof \ParseError) {
                # Catch parser errors so we can log them better
                error_log(
                    "Caught " . get_class($e) . ": ". $e->getMessage() . PHP_EOL .
                    $e->getFile() . " [" . $e->getLine() . "]" . PHP_EOL .
                    $e->getTraceAsString()
                );
                $this->irker->deepDebugParseAnnounce($e);
            }
            error_log("Caught " . get_class($e) . ": ". $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            $response = $this->processError($e, ['message'=>'Internal Server Error', 'http_status'=>500], 500);
        }

        # This point is only reached if an exception was thrown
        $this->processResponse($response);
        $this->debug->profile();
    }

    protected function processError($error, $variables = null, $httpStatus = null) {
        $response = null;

        if (empty($variables)) {
            $variables = $error->getTemplateVars();
        }
        if (empty($httpStatus)) {
            $httpStatus = $error->httpStatus;
        }

        # BBCode errors are only ever displayed on the error page (for now)
        if ($error instanceof BBCodeError) {
            return new Rendered('error.html.twig', $variables, $httpStatus);
        }

        # Other errors appear on error page or flasher
        switch ($this->request->method) {
            case 'CLI':
                # Render a CLI error
                $response = new Rendered('clierror.html.twig', $variables, $httpStatus);
                break;
            case 'GET':
            case 'HEAD':
                # Set the error cookie
                $this->request->setErrorCookie();

                # Render a GET error
                if ($error instanceof Error) {
                    // Load the flasher
                    if (property_exists($error, 'publicMessage')) {
                        $this->flasher->error($error->publicMessage.PHP_EOL.$error->publicDescription);
                    } else {
                        $this->flasher->error($variables['message']);
                    }

                    if ($error->returnJSON() === true) {
                        $response = new JSON($this->flasher->grabFlashes(), $httpStatus);
                    } else {
                        $response = new Rendered('error.html.twig', $variables, $httpStatus);
                    }
                } else {
                    $response = new Rendered('error.html.twig', $variables, $httpStatus);
                }
                break;
            case 'POST':
            case 'PUT':
                # Set the error cookie
                $this->request->setErrorCookie();

                # Use the flasher for POST/PUT errors
                if ($error instanceof Error) {
                    // Load the flasher
                    if (property_exists($error, 'publicMessage')) {
                        $this->flasher->error($error->publicMessage.PHP_EOL.$error->publicDescription);
                    } else {
                        $this->flasher->error($variables['message']);
                    }

                    if ($error->returnJSON() === true) {
                        $response = new JSON($this->flasher->grabFlashes(), $httpStatus);
                    } else if (!empty($error->redirect)) {
                        $response = new Redirect($error->redirect);
                    } else {
                        $response = $this->request->back();
                    }
                }
                break;
        }
        return $response;
    }

    public function processResponse($response) {
        if ($response instanceof Rendered) {
            http_response_code($response->status);
            $this->render->displayPage($response->template, $response->variables, $response->block);
            if (!empty($response->callback)) {
                if (is_callable($response->callback) === true) {
                    if (!empty($response->callbackParams)) {
                            call_user_func_array($response->callback, (array)$response->callbackParams);
                    } else {
                        call_user_func($response->callback);
                    }
                }
            }
        } elseif ($response instanceof Redirect) {
            $this->redirect($response->target, $response->parameters, $response->status);
        } elseif ($response instanceof Response) {
            if (property_exists($response, 'contentType')) {
                header("Content-type: {$response::$contentType}");
            }
            http_response_code($response->status);
            print($response->content);
            if (!$this->request->cli) {
                @ob_end_flush();
            }
        } else {
            throw new InternalError("Invalid response passed to Master->processResponse().");
            if (!$this->request->cli) {
                @ob_end_flush();
            }
        }
    }

    protected static $serviceLookup = [
        'auth'         => 'Auth',
        'cache'        => 'Cache',
        'crypto'       => 'Crypto',
        'debug'        => 'Debug',
        'flasher'      => 'Flasher',
        'geoip'        => 'GeoIP',
        'guardian'     => 'Guardian',
        'irker'        => 'Irker',
        'log'          => 'Log',
        'options'      => 'Options',
        'render'       => 'Render',
        'profiler'     => 'Profiler',
        'plotly'       => 'Plotly',
        'repos'        => 'Repos',
        'router'       => 'Router',
        'search'       => 'Search',
        'secretary'    => 'Secretary',
        'emailManager' => 'EmailManager',
        'userManager'  => 'UserManager',
        'settings'     => 'Settings',
        'tracker'      => 'Tracker',
        'tagManager'   => 'TagManager',
        'testing'      => 'Testing',
        'security'     => 'Security',
        'db'           => 'DB',
        'orm'          => 'ORM',
        'tpl'          => 'TPL',
    ];

    public function __isset($name) {
        return array_key_exists($name, self::$serviceLookup);
    }

    public function __get($name) {
        if ($this->__isset($name)) {
            return $this->getService(self::$serviceLookup[$name]);
        } else {
            throw new InternalError("Attempt to access undefined \$master->{$name}");
        }
    }

    public function prependRoute($route) {
        array_unshift($this->routes, $route);
    }

    protected function registerPlugins() {
        $this->plugins = $this->cache->getValue('system_plugins');
        if (!is_array($this->plugins) || empty($this->plugins)) {
            foreach (glob($this->applicationPath."/Plugins/*/*Plugin.php") as $plugin) {
                $plugin = preg_replace('~(.*)/(.*)Plugin.php~su', '${2}', $plugin);
                $this->plugins[$plugin] = null;
            }
            $this->cache->cacheValue('system_plugins', $this->plugins, 3600);
        }
        foreach (array_keys($this->plugins) as $plugin) {
            $pluginClass = "\\Luminance\\Plugins\\{$plugin}\\{$plugin}Plugin";
            $tplPath = $this->applicationPath . "/Plugins/{$plugin}/templates";
            if (is_dir($tplPath)) {
                $this->tpl->addTemplatePath($tplPath, $plugin);
            }
            $pluginClass::register($this);
            Debug::setFlag("{$plugin} plugin registered");
        }
    }

    public function handleRequest() {
        if (!$this->request->cli) {
            ob_start();
            $this->handleTrivialCases();
        }

        $routeMatch = $this->router->resolve($this->routes, $this->request, $this->request->path);

        if (is_array($routeMatch)) {
            $func = $routeMatch[0];
            $authLevel = $routeMatch[1];
            $args = array_slice($routeMatch, 2);

            if ($this->request->authLevel < $authLevel) {
                $this->request->saveIntendedRoute();
                if ($this->request->authLevel === Auth::AUTH_NONE) {
                    return new Redirect('/login');
                } else {
                    $message = '';
                    switch ($authLevel) {
                        case Auth::AUTH_NONE:
                        case Auth::AUTH_API:
                            break;

                        case Auth::AUTH_LOGIN:
                            $message = "(must be logged in)";
                            break;

                        case Auth::AUTH_IPLOCK:
                            $message = "(must be logged in with IP lock enabled)";
                            break;

                        case Auth::AUTH_2FA:
                            $message = "(must be logged in with 2FA enabled)";
                            break;
                    }

                    throw new AuthError("Unauthorized", "Insufficient authentication level {$message}");
                }
            }

            if (method_exists($this, $func)) {
                return call_user_func_array([$this, $func], $args);
            } else {
                throw new InternalError("Route resolved to nonexistent Master method: {$func}");
            }
        } else {
            throw new NotFoundError();
        }
    }

    public function func($func) {
        $args = array_slice(func_get_args(), 1);
        if (method_exists($this, $func)) {
            return call_user_func_array([$this, $func], (array)$args);
        } else {
            throw new NotFoundError();
        }
    }

    public function plugin($pluginName, $path = []) {
        $args = func_get_args();
        $pluginName = $args[0];
        $path = array_slice($args, 1);
        $plugin = $this->getPlugin($pluginName);
        return call_user_func_array([$plugin, 'handlePath'], $path);
    }

    public function handleTrivialCases() {
        # Deal with dumbasses
        if (isset($this->request->values['info_hash']) && isset($this->request->values['peer_id'])) {
            die('d14:failure reason40:Invalid .torrent, try downloading again.e');
        }
        $urlPath = basename(parse_url($this->server['SCRIPT_NAME'], PHP_URL_PATH));
        if ($urlPath === 'announce.php' || $urlPath === 'scrape.php') {
            print("d14:failure reason40:Invalid .torrent, try downloading again.e\n");
            exit;
        }
        $httpHost = $this->server['HTTP_HOST'] ?? null;
        $requestURI = $this->server['REQUEST_URI'];
        $nonsslURL = $this->settings->main->nonssl_site_url;
        $sslURL = $this->settings->main->ssl_site_url;
        if (empty($sslURL)) {
            $sslURL = $nonsslURL;
        }

        if (!$this->request->ssl && $httpHost === "www.{$nonsslURL}") {
            $this->redirect("http://{$nonsslURL}{$requestURI}");
        }
        if ($this->request->ssl && $httpHost === "www.{$nonsslURL}") {
            $this->redirect("https://{$sslURL}{$requestURI}");
        }
        if (!($sslURL === $nonsslURL) && (
            (!$this->request->ssl && $httpHost === $sslURL) ||
            ($this->request->ssl && $httpHost === $nonsslURL)
        )) {
            $this->redirect("https://{$sslURL}{$requestURI}");
        }
    }

    public function redirect($target, $parameters = null, $status = 301) {
        if (is_array($parameters) && count($parameters)) {
            $queryString = '?' . http_build_query($parameters);
        } elseif (!empty($parameters)) {
            $queryString = '?' . strval($parameters);
        } else {
            $queryString = '';
        }
        header('Location: ' . $target . $queryString, true, $status);
        exit();
    }

    public function redirectHandler($path) {
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        return $this->redirect('/' . $path);
    }

    public function redirectHandlerParams($path) {
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        return $this->redirect('/' . $path, $this->request->values);
    }

    public function renderQrcode() {
        # QR code data to be encoded can contain slashes
        $data = $this->request->uri;
        $prefix = '/render/qr/';

        if (substr($data, 0, strlen($prefix)) === $prefix) {
            $data = substr($data, strlen($prefix));
        }

        # Skip when $data is empty or false because
        # PHPQRCode returns an inelegant 500 error (Bug: Class not found)
        if (!empty($data)) {
            $options = new QROptions([
                'outputType'     => QRCode::OUTPUT_MARKUP_SVG,
                'imageBase64'    => false,
                'eccLevel'       => QRCode::ECC_H,
                'addQuietzone'   => true,
            ]);
            header('Content-type: image/svg+xml');
            echo (new QRcode($options))->render($data);
        }
        die();
    }

    public function getRouteRegex() {
        $relativeURLs = [];
        foreach ($this->routes as $route) {
            if (!($route[0] === 'CLI')) {
                $route = $route[1];
                $route = str_replace('/**', '', $route);
                $route = str_replace('/*', '', $route);
                $route = str_replace('/', '\/', $route);
                $route = str_replace('.', '\.', $route);
                if (!empty($route)) {
                    $relativeURLs[] = '(^|(?<=\s))\/' .$route;
                }
            }
        }
        return '/('.implode('|', $relativeURLs).')/i';
    }

    public function tests() {
        return new Response($this->testing->run());
    }
}
