<?php
/**
 * XPLabs - Logout Handler
 * End user session and redirect to login.
 */

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';

use XPLabs\Lib\Auth;

Auth::getInstance()->logout();

// Redirect to login page
header('Location: ../../login.php');
exit;
