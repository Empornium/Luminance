<?php
namespace Luminance\Core;

use Luminance\Errors\AuthError;
use Luminance\Errors\InternalError;
use Luminance\Errors\NotFoundError;
use Luminance\Responses\Response;
use Luminance\Responses\Rendered;

use Luminance\Services\Auth;

/**
 * Controller
 * Implements the common functions of the controller role in the MVC architecture.
 */
abstract class Controller extends Slave {

    public $routes = [];

    /**
     * __construct
     * Wrapper around parent constructor.
     *
     * @param Master $master Core handler reference
     *
     * @return self
     *
     * @access public
     */
    public function __construct(Master $master) {
        parent::__construct($master);
    }

    /**
     * handlePath
     * Default path handler for Controllers (Slaves).
     *
     * @return Rendered|Response Object to instruct Master how to respond
     * @throws AuthError If the users authentication level is insufficient to access this resource
     * @throws InternalError If the path handler returns an unexpected type
     * @throws InternalError If the route resolved to a function which does not exist
     * @throws NotFoundError If the route could not be resolved
     *
     * @access public
     */
    public function handlePath() {
        $path = func_get_args();
        # implode/explode to ensure path is as we expect
        $path = explode('/', implode('/', $path));
        $routeMatch = $this->master->router->resolve($this->routes, $this->request, $path);

        if (is_array($routeMatch)) {
            $func = $routeMatch[0];
            $authLevel = $routeMatch[1];
            $args = array_slice($routeMatch, 2);

            if ($this->request->authLevel < $authLevel) {
                $this->request->saveIntendedRoute();
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

            if (method_exists($this, $func)) {
                $result = call_user_func_array(array($this, $func), $args);

                if (is_array($result)) {
                    $template = "{$func}.html.twig";
                    return new Rendered($template, $result);
                } elseif (is_string($result)) {
                    return new Response($result);
                } elseif ($result instanceof Response) {
                    return $result;
                } elseif (is_null($result)) {
                    return new Response('');
                } else {
                    throw new InternalError("View function {$func} returned invalid result.");
                }
            } else {
                throw new InternalError("Route resolved to nonexistent Controller method: {$func}");
            }
        } else {
            throw new NotFoundError();
        }
    }
}
