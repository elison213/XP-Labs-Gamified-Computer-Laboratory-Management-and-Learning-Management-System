<?php
/**
 * XPLabs - Simple Router
 * Handles URL routing to controllers
 */

namespace XPLabs\Core;

class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private string $basePath = '';

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Register a GET route
     */
    public function get(string $path, $handler): self
    {
        $this->addRoute('GET', $path, $handler);
        return $this;
    }

    /**
     * Register a POST route
     */
    public function post(string $path, $handler): self
    {
        $this->addRoute('POST', $path, $handler);
        return $this;
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, $handler): self
    {
        $this->addRoute('PUT', $path, $handler);
        return $this;
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, $handler): self
    {
        $this->addRoute('DELETE', $path, $handler);
        return $this;
    }

    /**
     * Register a route for any method
     */
    public function any(string $path, $handler): self
    {
        $this->addRoute('ANY', $path, $handler);
        return $this;
    }

    /**
     * Add middleware
     */
    public function middleware(string $middleware, array $except = []): self
    {
        $this->middlewares[] = ['middleware' => $middleware, 'except' => $except];
        return $this;
    }

    /**
     * Add a route
     */
    private function addRoute(string $method, string $path, $handler): void
    {
        $path = $this->basePath . rtrim($path, '/');
        
        // Convert route parameters to regex
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'path' => $path,
        ];
    }

    /**
     * Dispatch the router
     */
    public function dispatch(?string $uri = null, ?string $method = null): mixed
    {
        $uri = $uri ?? $_SERVER['REQUEST_URI'] ?? '/';
        $method = $method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Remove query string
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract named parameters
                $params = array_filter(
                    $matches,
                    'is_string',
                    ARRAY_FILTER_USE_KEY
                );

                return $this->callHandler($route['handler'], $params);
            }
        }

        // No route found
        http_response_code(404);
        return $this->notFound();
    }

    /**
     * Call the route handler
     */
    private function callHandler($handler, array $params): mixed
    {
        // Handle controller@method syntax
        if (is_string($handler) && str_contains($handler, '@')) {
            [$controller, $method] = explode('@', $handler, 2);
            
            if (!class_exists($controller)) {
                throw new \RuntimeException("Controller {$controller} not found");
            }
            
            $instance = new $controller();
            
            if (!method_exists($instance, $method)) {
                throw new \RuntimeException("Method {$method} not found in {$controller}");
            }
            
            return $instance->$method(...array_values($params));
        }

        // Handle callable
        if (is_callable($handler)) {
            return $handler(...array_values($params));
        }

        // Handle array [Controller::class, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$controller, $method] = $handler;
            $instance = is_object($controller) ? $controller : new $controller();
            return $instance->$method(...array_values($params));
        }

        throw new \RuntimeException("Invalid route handler");
    }

    /**
     * Return 404 response
     */
    private function notFound(): void
    {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found', 'message' => 'The requested resource was not found']);
        } else {
            http_response_code(404);
            echo '<h1>404 - Page Not Found</h1>';
        }
        exit;
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}