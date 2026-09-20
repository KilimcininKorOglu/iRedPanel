<?php

declare(strict_types=1);

namespace App;

class Router
{
    /** @var array<int, array{methods: string[], pattern: string, regex: string, handler: callable}> */
    private array $routes = [];

    private $notFoundHandler;

    /**
     * Registers a route with one or more HTTP methods.
     *
     * @param string|string[] $methods HTTP method(s) (e.g., 'GET', ['GET', 'POST'])
     * @param string $pattern URL pattern with {paramName} placeholders
     * @param callable $handler Route handler function
     */
    public function addRoute(string|array $methods, string $pattern, callable $handler): void
    {
        $methods = is_string($methods) ? [$methods] : $methods;
        $methods = array_map(strtoupper(...), $methods);

        // Convert {paramName} to named regex groups
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '/?$#';

        $this->routes[] = [
            'methods' => $methods,
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
        ];
    }

    /**
     * Sets the handler for 404 Not Found.
     */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Dispatches the request to the matching route handler.
     */
    public function dispatch(string $uri, string $method): void
    {
        // Strip query string
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $method = strtoupper($method);

        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $pathMatched = true;

                if (!in_array($method, $route['methods'], true)) {
                    continue;
                }

                // Extract named parameters
                $params = array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);
                call_user_func_array($route['handler'], $params);
                return;
            }
        }

        // No handler matched
        http_response_code($pathMatched ? 405 : 404);
        if (!$pathMatched && $this->notFoundHandler !== null) {
            call_user_func($this->notFoundHandler);
        }
    }
}
