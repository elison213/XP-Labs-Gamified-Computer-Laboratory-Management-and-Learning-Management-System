<?php
/**
 * PSR-4-style autoload for XPLabs (API entrypoints that skip includes/bootstrap.php).
 */
if (defined('XPLABS_AUTOLOAD_REGISTERED')) {
    return;
}
define('XPLABS_AUTOLOAD_REGISTERED', true);

spl_autoload_register(static function (string $class): void {
    $prefix = 'XPLabs\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $root = dirname(__DIR__);

    if (str_starts_with($relative, 'Services\\')) {
        $file = $root . '/services/' . str_replace('\\', '/', substr($relative, 9)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }

    if (str_starts_with($relative, 'Lib\\')) {
        $file = $root . '/lib/' . str_replace('\\', '/', substr($relative, 4)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
