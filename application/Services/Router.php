<?php
namespace Luminance\Services;

use Luminance\Core\Service;
use Luminance\Core\Request;
use Luminance\Errors\InternalError;

class Router extends Service {

    public function resolve($routes, Request $request, $requestPath) {
        $return = null;
        foreach ($routes as $route) {
            if (count($route) < 3) {
                throw new InternalError("Invalid route.");
            }
            $match = $this->matchRoute($route, $request, $requestPath);
            if (is_array($match)) {
                # exact match, return immediately
                if (count($match) === 2) {
                    return $match;
                # wilcard match, keep searching just in case
                } else {
                    $return = $match;
                }
            }
        }
        return $return;
    }

    protected function matchRoute($route, Request $request, $requestPath) {
        $requestMethod = $route[0];
        $routePath = (strlen($route[1])) ? explode('/', $route[1]) : [];
        $authLevel = $route[2];
        $func = $route[3];
        $args = array_slice($route, 4);

        if (!($requestMethod === '*') && !($requestMethod === $request->method)) {
            return null;
        }

        $pathMatches = $this->matchPath($routePath, $requestPath);
        if (is_array($pathMatches)) {
            $result = array_merge((array)[$func], (array)[$authLevel], $args, $pathMatches);
            return $result;
        } else {
            return null;
        }
    }

    protected function matchPath($routePath, $requestPath) {
        $pathMatches = [];
        for ($i = 0; $i < count($routePath); $i++) {
            if ($routePath[$i] === '**') {
                $pathMatches = array_merge($pathMatches, array_slice($requestPath, $i));
                return $pathMatches;
            } elseif ($routePath[$i] === '*') {
                if (array_key_exists($i, $requestPath)) {
                    $pathMatches[] = $requestPath[$i];
                } else {
                    return false;
                }
            } elseif (!array_key_exists($i, $requestPath) || !($requestPath[$i] === $routePath[$i])) {
                return false;
            }
            # If we got here that means a non-wildcard path element matched - we continue, but don't store any matches
        }
        if (count($requestPath) > count($routePath)) {
            return false; # '**' match would make this valid, but if that applies we never get here
        }
        return $pathMatches;
    }
}
