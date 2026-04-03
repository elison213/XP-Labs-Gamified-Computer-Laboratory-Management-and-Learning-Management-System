<?php
/**
 * XPLabs - Bootstrap File
 * Autoloads classes and sets up the application environment.
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', true);
ini_set('log_errors', true);
ini_set('error_log', __DIR__ . '/../storage/logs/error.log');

// Timezone
date_default_timezone_set('Asia/Manila');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'XPLabs\\';
    $baseDir = __DIR__ . '/../';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Helper functions
function e($string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function flash(string $key, ?string $message = null): ?string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function asset(string $path): string {
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    // Remove subdirectory suffix (e.g., /xplabs/dashboard_teacher.php -> /xplabs)
    $basePath = preg_replace('#/[^/]+\.php$#', '', $basePath);
    return $basePath . '/assets/' . ltrim($path, '/');
}

function url(string $path): string {
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $basePath = preg_replace('#/[^/]+\.php$#', '', $basePath);
    return $basePath . '/' . ltrim($path, '/');
}

function old(string $key, $default = '') {
    return $_POST[$key] ?? $default;
}