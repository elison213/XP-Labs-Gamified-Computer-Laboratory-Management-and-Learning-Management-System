<?php
/**
 * XPLabs - CSRF helpers for session-authenticated APIs.
 *
 * Use for JSON APIs that rely on browser cookies (teacher/admin actions).
 */

namespace XPLabs\Lib;

class Csrf
{
    public static function token(): string
    {
        // Ensure session is initialized via Auth session settings
        Auth::getInstance();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function verify(?string $token): bool
    {
        Auth::getInstance();
        $t = (string) ($token ?? '');
        return $t !== '' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $t);
    }

    public static function requireValidToken(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!self::verify(is_string($token) ? $token : '')) {
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF token mismatch']);
            exit;
        }
    }
}

