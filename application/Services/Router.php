<?php
namespace Luminance\Services;

use Luminance\Core\Request;
use Luminance\Errors\InternalError;

class Router extends Service {

    public function resolve($routes, Request $request, $request_path) {
        foreach ($routes as $route) {
            if (count($route) < 3) {
                throw new InternalError("Invalid route.");
            }
            $match = $this->match_route($route, $request, $request_path);
            if (is_array($match)) {
                return $match;
            }
        }
        return null;
    }

    protected function match_route($route, Request $request, $request_path) {
        $route_method = $route[0];
        $route_path = (strlen($route[1])) ? explode('/', $route[1]) : [];
        $auth_level = $route[2];
        $func = $route[3];
        $args = array_slice($route, 4);

        if ($route_method != '*' && $route_method != $request->method) {
            return null;
        }

        $path_matches = $this->match_path($route_path, $request_path);
        if (is_array($path_matches)) {
            $result = array_merge((array)[$func], (array)[$auth_level], $args, $path_matches);
            return $result;
        } else {
            return null;
        }

    }

    protected function match_path($route_path, $request_path) {
        $path_matches = [];
        for ($i = 0; $i < count($route_path); $i++) {
            if ($route_path[$i] == '**') {
                $path_matches = array_merge($path_matches, array_slice($request_path, $i));
                return $path_matches;
            } elseif ($route_path[$i] == '*') {
                if (array_key_exists($i, $request_path)) {
                    $path_matches[] = $request_path[$i];
                } else {
                    return false;
                }
            } elseif (!array_key_exists($i, $request_path) || $request_path[$i] != $route_path[$i]) {
                return false;
            }
            # If we got here that means a non-wildcard path element matched - we continue, but don't store any matches
        }
        if (count($request_path) > count($route_path)) {
            return false; # '**' match would make this valid, but if that applies we never get here
        }
        return $path_matches;
    }

}
