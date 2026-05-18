<?php
/**
 * XPLabs API - POST /api/kiosk/unlock
 * Door/tablet kiosk endpoint:
 * - Records attendance (if there is an active class session)
 * - Assigns a lab PC (by station_id or auto-assign by floor)
 * - Creates a PC session
 * - Queues an unlock command for the target PC
 *
 * Security model (LAN): restrict by allowed kiosk IPs (config/app.php).
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/UserService.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../../services/AttendanceService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';
require_once __DIR__ . '/../../src/Core/Request.php';

use XPLabs\Lib\Database;
use XPLabs\Services\UserService;
use XPLabs\Services\PCService;
use XPLabs\Services\AttendanceService;
use XPLabs\Api\Middleware\CorsMiddleware;
use XPLabs\Core\Request;

CorsMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$config = require __DIR__ . '/../../config/app.php';
$req = Request::fromGlobals();
$clientIp = $req->ip();
$allowedIps = $config['kiosk']['allowed_ips'] ?? [];
if (!is_array($allowedIps) || count($allowedIps) === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Kiosk allowlist is not configured']);
    exit;
}
if (!in_array($clientIp, $allowedIps, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Kiosk not allowed from this IP', 'ip' => $clientIp]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$lrn = trim($input['lrn'] ?? '');
$floorId = isset($input['floor_id']) ? (int) $input['floor_id'] : null;
$stationId = isset($input['station_id']) ? (int) $input['station_id'] : null;

if ($lrn === '') {
    http_response_code(400);
    echo json_encode(['error' => 'LRN is required']);
    exit;
}
if ($floorId !== null && $floorId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid floor_id']);
    exit;
}
if ($stationId !== null && $stationId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid station_id']);
    exit;
}

$userService = new UserService();
$user = $userService->findByLrn($lrn);
if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Student not found', 'lrn' => $lrn]);
    exit;
}
if (($user['role'] ?? '') !== 'student') {
    http_response_code(400);
    echo json_encode(['error' => 'Only students can unlock via kiosk']);
    exit;
}

$db = Database::getInstance();
$pcService = new PCService();

// -----------------------------------------------------
// Select target PC
// -----------------------------------------------------
$pc = null;
if ($stationId) {
    $pc = $db->fetch("SELECT * FROM lab_pcs WHERE station_id = ? AND status != 'maintenance' LIMIT 1", [$stationId]);
    if (!$pc) {
        http_response_code(404);
        echo json_encode(['error' => 'No PC registered for this station', 'station_id' => $stationId]);
        exit;
    }
} else {
    // Auto-assign: first available PC on the floor that has no active PC session.
    $params = [];
    $whereFloor = '';
    if ($floorId) {
        $whereFloor = 'AND lp.floor_id = ?';
        $params[] = $floorId;
    }

    $pc = $db->fetch(
        "SELECT lp.*
         FROM lab_pcs lp
         LEFT JOIN pc_sessions ps ON ps.pc_id = lp.id AND ps.status = 'active'
         WHERE ps.id IS NULL
           AND lp.status IN ('online','locked','idle')
           AND lp.status != 'maintenance'
           $whereFloor
         ORDER BY lp.status = 'online' DESC, lp.last_heartbeat DESC
         LIMIT 1",
        $params
    );

    if (!$pc) {
        http_response_code(409);
        echo json_encode(['error' => 'No available PC found']);
        exit;
    }
    $stationId = (int) ($pc['station_id'] ?? 0) ?: null;
}

// -----------------------------------------------------
// Record attendance (best effort)
// -----------------------------------------------------
$attendanceService = new AttendanceService();
$attendanceRecorded = false;
$attendancePoints = 0;
$attendanceError = null;

try {
    $enrollments = $db->fetchAll(
        "SELECT ce.course_id FROM course_enrollments ce WHERE ce.user_id = ? AND ce.status = 'enrolled'",
        [$user['id']]
    );
    $sessionId = 0;
    foreach ($enrollments as $enrollment) {
        $activeSession = $attendanceService->getActiveSession($enrollment['course_id']);
        if ($activeSession) {
            $sessionId = (int) $activeSession['id'];
            break;
        }
    }

    if ($sessionId) {
        $att = $attendanceService->checkIn($sessionId, (int) $user['id'], $stationId);
        if (!empty($att['success'])) {
            $attendanceRecorded = true;
            $attendancePoints = (int) ($att['points_earned'] ?? 0);
        } else {
            $attendanceError = $att['message'] ?? 'Attendance check-in failed';
        }
    } else {
        $attendanceError = 'No active class session found';
    }
} catch (\Throwable $e) {
    $attendanceError = 'Attendance error: ' . $e->getMessage();
}

// -----------------------------------------------------
// Create PC session
// -----------------------------------------------------
$sessionResult = $pcService->createSession((int) $user['id'], (int) $pc['id'], $stationId);
if (empty($sessionResult['success'])) {
    http_response_code(409);
    echo json_encode([
        'error' => $sessionResult['error'] ?? 'Failed to create PC session',
        'pc' => ['id' => (int) $pc['id'], 'hostname' => $pc['hostname']],
    ]);
    exit;
}

// -----------------------------------------------------
// Queue unlock command
// -----------------------------------------------------
$issuedBy = (int) ($config['kiosk']['issued_by_user_id'] ?? 0);
if (!$issuedBy) {
    $admin = $db->fetch("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1");
    $issuedBy = (int) ($admin['id'] ?? 0);
}
if (!$issuedBy) {
    http_response_code(500);
    echo json_encode(['error' => 'No issued_by_user_id configured for kiosk (and no admin user found)']);
    exit;
}

$cmd = $pcService->queueCommand((int) $pc['id'], $issuedBy, 'unlock', [
    'lrn' => $lrn,
    'user_id' => (int) $user['id'],
    'station_id' => $stationId,
]);

if (empty($cmd['success'])) {
    http_response_code(500);
    echo json_encode(['error' => $cmd['error'] ?? 'Failed to queue unlock command']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => "Welcome, {$user['first_name']}! PC unlocking...",
    'user' => [
        'id' => (int) $user['id'],
        'lrn' => $user['lrn'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
    ],
    'pc' => [
        'id' => (int) $pc['id'],
        'hostname' => $pc['hostname'],
        'floor_id' => (int) ($pc['floor_id'] ?? 0),
        'station_id' => $stationId,
    ],
    'pc_session' => [
        'id' => (int) ($sessionResult['session_id'] ?? 0),
        'checkin_time' => $sessionResult['checkin_time'] ?? null,
    ],
    'unlock_command' => [
        'id' => (int) ($cmd['command_id'] ?? 0),
    ],
    'attendance' => [
        'recorded' => $attendanceRecorded,
        'points_earned' => $attendancePoints,
        'error' => $attendanceRecorded ? null : $attendanceError,
    ],
]);

