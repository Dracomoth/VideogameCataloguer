<?php
/**
 * src/Services/Router.php
 * Lightweight Zero-Dependency HTTP Request Router & Dispatcher.
 */

declare(strict_types=1);

namespace Vault\Services;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class Router
{
    /**
     * Registered routes grouped by HTTP method.
     * @var array<string, array<int, array{pattern: string, regex: string, paramNames: array<int, string>, handler: callable|array{0: class-string, 1: string}}>>
     */
    private array $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'DELETE' => [],
    ];

    /**
     * Register a GET route.
     *
     * @param string $path
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param string $path
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register a PUT route.
     *
     * @param string $path
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function put(string $path, callable|array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $path
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function delete(string $path, callable|array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Compiles route pattern into regex and registers route definition.
     *
     * @param string $method
     * @param string $path
     * @param callable|array{0: class-string, 1: string} $handler
     */
    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $normalized = '/' . trim($path, '/');
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        $paramNames = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $normalized);

        $regex = '#^' . $pattern . '$#';

        $this->routes[$method][] = [
            'pattern'    => $normalized,
            'regex'      => $regex,
            'paramNames' => $paramNames,
            'handler'    => $handler,
        ];
    }

    /**
     * Dispatches current HTTP request against registered routes.
     *
     * @param string $uri
     * @param string $method
     */
    public function dispatch(string $uri, string $method): void
    {
        $cleanUri = '/' . trim($uri, '/');
        if ($cleanUri !== '/') {
            $cleanUri = rtrim($cleanUri, '/');
        }

        // Method override support for HTML forms via hidden '_method' field
        if ($method === 'POST' && !empty($_POST['_method'])) {
            $method = strtoupper((string)$_POST['_method']);
        }

        $methodMatchesExist = false;

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route) {
                if (preg_match($route['regex'], $cleanUri, $matches)) {
                    array_shift($matches); // Remove full match

                    $params = [];
                    foreach ($route['paramNames'] as $index => $name) {
                        $params[$name] = $matches[$index] ?? null;
                    }

                    $this->executeHandler($route['handler'], $params);
                    return;
                }
            }
        }

        // Check if route exists on another HTTP method to return 405 Method Not Allowed
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $method) {
                continue;
            }
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $cleanUri)) {
                    $methodMatchesExist = true;
                    break 2;
                }
            }
        }

        if ($methodMatchesExist) {
            Response::error('Method not allowed.', 405);
        }

        Response::error("Route '{$cleanUri}' not found.", 404);
    }

    /**
     * Executes the route handler and injects parsed inputs and route parameters.
     *
     * @param callable|array{0: class-string, 1: string} $handler
     * @param array<string, mixed> $params
     */
    private function executeHandler(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $action] = $handler;
            if (!class_exists($class)) {
                Response::error("Controller {$class} not found.", 500);
            }
            $controller = new $class();
            if (!method_exists($controller, $action)) {
                Response::error("Action {$action} not found on controller {$class}.", 500);
            }
            $handler = [$controller, $action];
        }

        // Resolve request body (JSON payload or form POST values)
        $rawInput = file_get_contents('php://input');
        $json = json_decode($rawInput ?: '', true);
        $body = is_array($json) ? $json : $_POST;

        $requestData = [
            'params' => $params,
            'query'  => $_GET,
            'body'   => $body,
            'files'  => $_FILES,
        ];

        call_user_func($handler, $requestData);
    }
}