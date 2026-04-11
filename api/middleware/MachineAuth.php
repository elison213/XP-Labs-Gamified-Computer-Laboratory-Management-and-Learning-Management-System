<?php
/**
 * XPLabs - Machine Auth Middleware
 * Authenticates lab PCs using API machine keys.
 * Usage: Include before endpoints that require PC authentication.
 */

namespace XPLabs\Api\Middleware;

use XPLabs\Lib\Database;

class MachineAuth
{
    private static ?array $authenticatedPC = null;

    /**
     * Authenticate a request using the X-Machine-Key header.
     * Returns the PC record if valid, exits with 401 if invalid.
     */
    public static function require(): array
    {
        $config = require __DIR__ . '/../../config/app.php';
        $headerName = $config['machine_auth']['api_key_header'] ?? 'X-Machine-Key';

        $machineKey = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($headerName))]
            ?? $_SERVER['HTTP_X_MACHINE_KEY']
            ?? null;

        // Try to get from request body JSON if not in header
        if (!$machineKey) {
            $body = json_decode(file_get_contents('php://input'), true);
            $machineKey = $body['machine_key'] ?? null;
        }

        if (!$machineKey) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Machine authentication required',
                'message' => 'Provide X-Machine-Key header',
            ]);
            exit;
        }

        $pc = self::authenticateByKey($machineKey);

        if (!$pc) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Invalid machine key',
                'message' => 'The provided machine key is not valid',
            ]);
            exit;
        }

        self::$authenticatedPC = $pc;
        return $pc;
    }

    /**
     * Validate a machine key and return PC record.
     */
    public static function authenticateByKey(string $machineKey): ?array
    {
        $db = Database::getInstance();

        $pc = $db->fetch(
            "SELECT * FROM lab_pcs WHERE machine_key = ? AND status != 'maintenance'",
            [$machineKey]
        );

        if (!$pc) {
            return null;
        }

        return $pc;
    }

    /**
     * Get the currently authenticated PC (from this request).
     */
    public static function getPC(): ?array
    {
        return self::$authenticatedPC;
    }

    /**
     * Optional authentication - returns null instead of exiting.
     */
    public static function optional(): ?array
    {
        $config = require __DIR__ . '/../../config/app.php';
        $headerName = $config['machine_auth']['api_key_header'] ?? 'X-Machine-Key';

        $machineKey = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($headerName))]
            ?? $_SERVER['HTTP_X_MACHINE_KEY']
            ?? null;

        if (!$machineKey) {
            return null;
        }

        $pc = self::authenticateByKey($machineKey);
        if ($pc) {
            self::$authenticatedPC = $pc;
        }

        return $pc;
    }
}