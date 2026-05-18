<?php
/**
 * XPLabs - CORS Middleware
 * Handles Cross-Origin Resource Sharing for machine-to-machine API calls.
 * Usage: Include at the top of API endpoints that PowerShell scripts will call.
 */

namespace XPLabs\Api\Middleware;

class CorsMiddleware
{
    /**
     * Send CORS headers and handle preflight requests.
     * Call this before any API response is sent.
     */
    public static function handle(array $allowedOrigins = []): void
    {
        // Default: allow same-origin and lab subnet
        if (empty($allowedOrigins)) {
            $allowedOrigins = self::getDefaultAllowedOrigins();
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if origin is allowed or if it's a same-origin request
        if (in_array($origin, $allowedOrigins) || self::isSameOrigin($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }

        // Preflight request handling
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Machine-Key, X-Requested-With');
            header('Access-Control-Max-Age: 3600');
            http_response_code(204);
            exit;
        }

        // Standard CORS headers
        header('Access-Control-Expose-Headers: X-Request-Status');
    }

    /**
     * Get default allowed origins from config.
     */
    protected static function getDefaultAllowedOrigins(): array
    {
        $configFile = __DIR__ . '/../../config/app.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            return $config['cors_allowed_origins'] ?? [];
        }
        return [];
    }

    /**
     * Check if the request is from the same origin.
     */
    protected static function isSameOrigin(string $origin): bool
    {
        if (empty($origin)) {
            return true; // No origin header (same-origin request)
        }

        $requestHost = $_SERVER['HTTP_HOST'] ?? '';
        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);

        if ($originPort) {
            $originHost .= ':' . $originPort;
        }

        return $originHost === $requestHost;
    }

    /**
     * Allow requests from lab PC subnet.
     * Call this for endpoints that only lab PCs should access.
     */
    public static function allowLabPCs(): void
    {
        // Allow any origin with valid machine key (security is in the key, not origin)
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!empty($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Machine-Key, X-Requested-With');
        header('Access-Control-Max-Age: 3600');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}