<?php

namespace App\Core;

require_once __DIR__ . '/helpers.php';

class Router
{
    private array $routes = [];

    /**
     * Register a GET route.
     *
     * @param string           $path
     * @param callable|array   $handler
     * @param callable[]       $middleware  Optional list of middleware callables to run before handler.
     *                                     Each callable should halt execution (exit/redirect) on failure.
     */
    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     *
     * @param string           $path
     * @param callable|array   $handler
     * @param callable[]       $middleware  Optional list of middleware callables to run before handler.
     */
    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Internal method to store routes.
     *
     * @param string         $method
     * @param string         $path
     * @param callable|array $handler
     * @param callable[]     $middleware
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): void
    {
        $this->routes[strtoupper($method)][$this->normalizePath($path)] = [
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Determine the base URL path of the application.
     * Handles root folder, /public subdirectory, virtual hosts, and Apache subfolder aliases.
     *
     * @return string
     */
    public static function getBaseUrl(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '';

        if ($dir !== '') {
            if (strpos($requestPath, $dir) === 0) {
                return $dir;
            }

            if (substr($dir, -7) === '/public') {
                $parentDir = substr($dir, 0, -7);
                if ($parentDir !== '' && strpos($requestPath, $parentDir) === 0) {
                    return $parentDir;
                }
            }
        }

        return $dir;
    }

    /**
     * Dispatch the current request.
     *
     * @param string $method
     * @param string $uri
     */
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path   = $this->normalizePath(parse_url($uri, PHP_URL_PATH) ?? '/');

        if (!isset($this->routes[$method][$path])) {
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $baseDir    = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

            $candidateBases = [];
            if ($baseDir !== '') {
                $candidateBases[] = $baseDir;
                if (substr($baseDir, -7) === '/public') {
                    $candidateBases[] = substr($baseDir, 0, -7);
                }
            }

            foreach ($candidateBases as $base) {
                if ($base !== '' && strpos($path, $base) === 0) {
                    $subPath = substr($path, strlen($base));
                    $subPath = $this->normalizePath($subPath ?: '/');
                    if (isset($this->routes[$method][$subPath])) {
                        $path = $subPath;
                        break;
                    }
                }
            }
        }

        if (isset($this->routes[$method][$path])) {
            $route      = $this->routes[$method][$path];
            $handler    = $route['handler'];
            $middleware = $route['middleware'] ?? [];

            // Run middleware pipeline — any middleware may exit/redirect to abort
            foreach ($middleware as $mw) {
                if (is_callable($mw)) {
                    call_user_func($mw);
                }
            }

            if (is_callable($handler)) {
                call_user_func($handler);
                return;
            }

            if (is_array($handler) && count($handler) === 2) {
                [$controllerClass, $action] = $handler;

                if (class_exists($controllerClass)) {
                    $controller = new $controllerClass();
                    if (method_exists($controller, $action)) {
                        $controller->$action();
                        return;
                    }
                }
            }
        }

        $this->sendNotFound();
    }

    /**
     * Normalize URL path (e.g. ensure leading slash, trim trailing slashes).
     *
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        $trimmed = trim($path, '/');
        return '/' . $trimmed;
    }

    /**
     * Send standard 404 response.
     */
    private function sendNotFound(): void
    {
        http_response_code(404);
        $viewPath = dirname(__DIR__) . '/Views/errors/404.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            echo "404 Not Found";
        }
    }
}
