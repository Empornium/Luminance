<?php
namespace Luminance\Core;

use Luminance\Core\Master;
use Luminance\Core\Slave;
use Luminance\Errors\AuthError;
use Luminance\Errors\InternalError;
use Luminance\Errors\NotFoundError;

use Luminance\Responses\Response;
use Luminance\Responses\Rendered;


abstract class Controller extends Slave {

    public $routes = [];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->request = $this->master->request;
    }

    public function handle_path() {
        $path = func_get_args();
        $route_match = $this->master->router->resolve($this->routes, $this->request, $path);
        if (is_array($route_match)) {
            $func = $route_match[0];
            $authLevel = $route_match[1];
            $args = array_slice($route_match, 2);
            if ($this->request->authLevel < $authLevel)
                throw new AuthError("Insufficient authentication level", "Unauthorized");
            if (method_exists($this, $func)) {
                $result = call_user_func_array(array($this, $func), $args);

                if (is_array($result)) {
                    $template = "{$func}.html";
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
