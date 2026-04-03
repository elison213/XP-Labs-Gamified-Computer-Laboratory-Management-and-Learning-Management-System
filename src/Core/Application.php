<?php
/**
 * XPLabs - Application Bootstrap
 * Central application instance that bootstraps the framework
 */

namespace XPLabs\Core;

class Application
{
    private static ?self $instance = null;
    private Router $router;
    private array $config = [];
    private array $bindings = [];
    private array $instances = [];

    public function __construct(array $config = [])
    {
        self::$instance = $this;
        $this->config = $config;
        $this->router = new Router($config['app']['url'] ?? '');
    }

    /**
     * Get application instance
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * Get the router
     */
    public function router(): Router
    {
        return $this->router;
    }

    /**
     * Register a route
     */
    public function get(string $path, $handler): Router
    {
        return $this->router->get($path, $handler);
    }

    public function post(string $path, $handler): Router
    {
        return $this->router->post($path, $handler);
    }

    public function put(string $path, $handler): Router
    {
        return $this->router->put($path, $handler);
    }

    public function delete(string $path, $handler): Router
    {
        return $this->router->delete($path, $handler);
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        try {
            $this->router->dispatch();
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Handle exceptions
     */
    private function handleException(\Exception $e): void
    {
        // Log the error
        error_log($e->getMessage());
        error_log($e->getTraceAsString());

        // Return appropriate response
        if ($this->isApiRequest()) {
            Response::json([
                'success' => false,
                'message' => $this->config['app']['debug'] ? $e->getMessage() : 'Internal Server Error',
                'trace' => $this->config['app']['debug'] ? $e->getTraceAsString() : null,
            ], 500)->send();
        } else {
            http_response_code(500);
            if ($this->config['app']['debug']) {
                echo '<h1>Error</h1>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            } else {
                echo '<h1>500 - Internal Server Error</h1>';
                echo '<p>Something went wrong. Please try again later.</p>';
            }
        }
    }

    /**
     * Check if request is an API request
     */
    private function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_starts_with($uri, '/api/');
    }

    /**
     * Get configuration value
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    /**
     * Bind a class/interface to the container
     */
    public function bind(string $abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Resolve from container
     */
    public function make(string $abstract): mixed
    {
        // Return existing instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check bindings
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            
            if (is_callable($concrete)) {
                $instance = $concrete($this);
            } elseif (is_string($concrete)) {
                $instance = new $concrete();
            } else {
                $instance = $concrete;
            }
            
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        // Try to instantiate class
        if (class_exists($abstract)) {
            $instance = new $abstract();
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        throw new \RuntimeException("Cannot resolve {$abstract}");
    }

    /**
     * Set an instance
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Get base path
     */
    public function basePath(): string
    {
        return $this->config['app']['base_path'] ?? dirname(__DIR__, 2);
    }

    /**
     * Get environment
     */
    public function environment(): string
    {
        return $this->config['app']['env'] ?? 'production';
    }

    /**
     * Check if in debug mode
     */
    public function isDebug(): bool
    {
        return (bool) $this->config('app.debug', false);
    }
}