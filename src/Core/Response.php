<?php
/**
 * XPLabs - Response Object
 * Handles HTTP responses
 */

namespace XPLabs\Core;

class Response
{
    private string $content = '';
    private int $statusCode = 200;
    private array $headers = [];
    private array $cookies = [];

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Create a new response
     */
    public static function make(string $content = '', int $statusCode = 200, array $headers = []): self
    {
        return new self($content, $statusCode, $headers);
    }

    /**
     * Create a JSON response
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers['Content-Type'] = 'application/json';
        return new self($content, $statusCode, $headers);
    }

    /**
     * Create a view response
     */
    public static function view(string $view, array $data = [], int $statusCode = 200): self
    {
        $content = self::renderView($view, $data);
        $headers = ['Content-Type' => 'text/html; charset=utf-8'];
        return new self($content, $statusCode, $headers);
    }

    /**
     * Create a redirect response
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        $response = new self('', $statusCode, ['Location' => $url]);
        return $response;
    }

    /**
     * Create a download response
     */
    public static function download(string $filePath, ?string $filename = null, array $headers = []): self
    {
        if (!file_exists($filePath)) {
            return self::json(['error' => 'File not found'], 404);
        }

        $filename = $filename ?? basename($filePath);
        $headers['Content-Type'] = 'application/octet-stream';
        $headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
        $headers['Content-Length'] = filesize($filePath);

        return new self(file_get_contents($filePath), 200, $headers);
    }

    /**
     * Set status code
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Set header
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set content type
     */
    public function contentType(string $type): self
    {
        return $this->header('Content-Type', $type);
    }

    /**
     * Set cookie
     */
    public function cookie(string $name, string $value, int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = true): self
    {
        $this->cookies[] = compact('name', 'value', 'expire', 'path', 'domain', 'secure', 'httpOnly');
        return $this;
    }

    /**
     * Get status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get content
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Send the response
     */
    public function send(): void
    {
        // Set status code
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Send cookies
        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie['name'],
                $cookie['value'],
                $cookie['expire'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httpOnly']
            );
        }

        // Send content
        echo $this->content;
    }

    /**
     * Render a view file
     */
    private static function renderView(string $view, array $data = []): string
    {
        // Convert view path to file path
        $viewPath = str_replace('.', '/', $view);
        $file = __DIR__ . '/../Views/' . $viewPath . '.php';

        if (!file_exists($file)) {
            return "<h1>View Not Found</h1><p>The view '{$view}' was not found.</p>";
        }

        // Extract data to variables
        extract($data);

        // Start output buffering
        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Success response
     */
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): self
    {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Error response
     */
    public static function error(string $message, int $statusCode = 400, mixed $data = null): self
    {
        return self::json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): self
    {
        return self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Not found response
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::error($message, 404);
    }

    /**
     * Unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::error($message, 401);
    }

    /**
     * Forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::error($message, 403);
    }

    /**
     * Server error response
     */
    public static function serverError(string $message = 'Internal Server Error'): self
    {
        return self::error($message, 500);
    }
}