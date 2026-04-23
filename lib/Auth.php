<?php
/**
 * XPLabs - Authentication & Session Management
 */

namespace XPLabs\Lib;

class Auth
{
    private static ?Auth $instance = null;
    private ?array $user = null;
    private bool $initialized = false;

    private function __construct()
    {
        $this->initSession();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize session with secure settings.
     */
    private function initSession(): void
    {
        if ($this->initialized) {
            return;
        }

        $config = require __DIR__ . '/../config/app.php';
        $sessionConfig = $config['session'] ?? [];

        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            ini_set('session.cookie_secure', $isHttps ? '1' : ($sessionConfig['cookie_secure'] ? '1' : '0'));
            ini_set('session.cookie_httponly', $sessionConfig['cookie_httponly'] ? '1' : '0');
            ini_set('session.cookie_samesite', $sessionConfig['cookie_samesite'] ?? 'Lax');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_path', '/xplabs/');

            session_name($sessionConfig['name'] ?? 'XPLABS_SESSION');
            session_start();
        }

        $this->initialized = true;
    }

    /**
     * Attempt to login a user.
     */
    public function login(int $userId, string $role): bool
    {
        $this->initSession();

        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE id = ? AND role = ? AND is_active = 1", [$userId, $role]);

        if (!$user) {
            return false;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_lrn'] = $user['lrn'];
        $_SESSION['login_time'] = time();

        // Update last login
        $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$userId]);

        $this->user = $user;
        return true;
    }

    /**
     * Logout the current user.
     */
    public function logout(): void
    {
        $this->initSession();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
        $this->user = null;
    }

    /**
     * Get the current authenticated user.
     */
    public function user(): ?array
    {
        $this->initSession();

        if ($this->user !== null) {
            return $this->user;
        }

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $db = Database::getInstance();
        $this->user = $db->fetch("SELECT * FROM users WHERE id = ? AND is_active = 1", [$_SESSION['user_id']]);

        return $this->user;
    }

    /**
     * Get the current user ID.
     */
    public static function id(): ?int
    {
        $instance = self::getInstance();
        $instance->initSession();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Get the current user role.
     */
    public static function role(): ?string
    {
        $instance = self::getInstance();
        $instance->initSession();
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Check if user is authenticated.
     */
    public static function check(): bool
    {
        $instance = self::getInstance();
        $instance->initSession();
        return isset($_SESSION['user_id']) && self::getInstance()->user() !== null;
    }

    /**
     * Require authentication or redirect to login.
     */
    public static function require(string $redirectUrl = null): void
    {
        if (!self::check()) {
            if (self::isApiRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            $url = $redirectUrl ?? self::url('/login.php');
            header("Location: $url");
            exit;
        }
    }

    /**
     * Require a specific role or one of multiple roles.
     */
    public static function requireRole(string|array $role, string $redirectUrl = null): void
    {
        self::require($redirectUrl);

        $roles = is_array($role) ? $role : [$role];
        $userRole = self::role();
        
        if (!in_array($userRole, $roles) && $userRole !== 'admin') {
            if (self::isApiRequest()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Insufficient permissions']);
                exit;
            }
            header("Location: " . self::url('/index.php'));
            exit;
        }
    }

    /**
     * Require one of multiple roles.
     */
    public static function requireRoles(array $roles, string $redirectUrl = null): void
    {
        self::require($redirectUrl);

        $userRole = self::role();
        if (!in_array($userRole, $roles) && $userRole !== 'admin') {
            if (self::isApiRequest()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Insufficient permissions']);
                exit;
            }
            header("Location: " . self::url('/index.php'));
            exit;
        }
    }

    /**
     * Build a URL relative to the app base.
     */
    private static function url(string $path): string
    {
        $base = rtrim(self::getBasePath(), '/');
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Detect the app base path from the request.
     */
    private static function getBasePath(): string
    {
        $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        return rtrim($scriptName, '/');
    }

    /**
     * Check if the current request is an API request.
     */
    private static function isApiRequest(): bool
    {
        return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    }

    /**
     * Regenerate session ID (call after login for security).
     */
    public function regenerateSession(): void
    {
        $this->initSession();
        session_regenerate_id(true);
    }

    /**
     * Get session lifetime remaining.
     */
    public function sessionTimeRemaining(): int
    {
        $this->initSession();
        $config = require __DIR__ . '/../config/app.php';
        $lifetime = $config['session']['lifetime'] ?? 3600;
        $elapsed = time() - ($_SESSION['login_time'] ?? time());
        return max(0, $lifetime - $elapsed);
    }

    /**
     * Check whether current user can use PC override unlock.
     */
    public static function canUnlockPcOverride(): bool
    {
        if (!self::check()) {
            return false;
        }
        $user = self::getInstance()->user();
        return (int) ($user['can_unlock_pc_override'] ?? 0) === 1;
    }
}