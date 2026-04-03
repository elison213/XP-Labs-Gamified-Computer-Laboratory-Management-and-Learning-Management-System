<?php
/**
 * XPLabs - Request Object
 * Encapsulates HTTP request data
 */

namespace XPLabs\Core;

class Request
{
    private string $method;
    private string $uri;
    private array $params = [];
    private array $query = [];
    private array $body = [];
    private array $headers = [];
    private array $files = [];
    private array $cookies = [];

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->query = $_GET ?? [];
        $this->body = $_POST ?? [];
        $this->headers = $this->getHeaders();
        $this->files = $_FILES ?? [];
        $this->cookies = $_COOKIE ?? [];

        // Parse JSON body for API requests
        if ($this->isJson()) {
            $json = file_get_contents('php://input');
            $this->body = json_decode($json, true) ?? [];
        }
    }

    /**
     * Create from globals
     */
    public static function fromGlobals(): self
    {
        return new self();
    }

    /**
     * Get request method
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Check if method matches
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }

    /**
     * Get URI
     */
    public function uri(): string
    {
        return parse_url($this->uri, PHP_URL_PATH);
    }

    /**
     * Get full URL
     */
    public function url(): string
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $this->uri;
    }

    /**
     * Get route parameters
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Get a route parameter
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Set route parameters
     */
    public function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Get query parameters
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * Get a query parameter
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get all input (query + body)
     */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * Get input value
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Get only specific inputs
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Get all except specific inputs
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /**
     * Check if input exists
     */
    public function has(string $key): bool
    {
        return isset($this->body[$key]) || isset($this->query[$key]);
    }

    /**
     * Get headers
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get a header
     */
    public function header(string $key, mixed $default = null): mixed
    {
        $key = str_replace('-', '_', strtoupper($key));
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get bearer token
     */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    /**
     * Get uploaded files
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * Get a file
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Check if request has file
     */
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Get cookies
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * Get a cookie
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Check if request expects JSON
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '');
        return str_contains($accept, 'application/json');
    }

    /**
     * Check if request is JSON
     */
    public function isJson(): bool
    {
        $contentType = $this->header('Content-Type', '');
        return str_contains($contentType, 'application/json');
    }

    /**
     * Check if request is secure (HTTPS)
     */
    public function isSecure(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    /**
     * Get client IP
     */
    public function ip(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent
     */
    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Parse headers from $_SERVER
     */
    private function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower($key))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
}