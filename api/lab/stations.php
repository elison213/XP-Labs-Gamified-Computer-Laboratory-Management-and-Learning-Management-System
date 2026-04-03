<?php
/**
 * XPLabs API - GET/PATCH /api/lab/stations
 * GET: List all stations (optionally filtered by floor_id)
 * PATCH: Update a station's status
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/LabService.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\LabService;

Auth::require();

$labService = new LabService();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $floorId = isset($_GET['floor_id']) ? (int) $_GET['floor_id'] : null;
    $stations = $labService->getStations($floorId);

    // Format for frontend
    $formatted = array_map(function ($s) {
        $user = null;
        if (!empty($s['first_name'])) {
            $user = trim($s['first_name'] . ' ' . $s['last_name']);
        }

        return [
            'id' => $s['id'],
            'station_code' => $s['station_code'],
            'floor_id' => $s['floor_id'],
            'status' => $s['is_maintenance'] ? 'maintenance' : ($s['status'] ?? 'offline'),
            'user' => $user,
            'since' => $s['checkin_time'] ? date('H:i', strtotime($s['checkin_time'])) : null,
            'task' => null,
            'hostname' => $s['hostname'] ?? null,
            'ip_address' => $s['ip_address'] ?? null,
        ];
    }, $stations);

    echo json_encode(['stations' => $formatted]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    Auth::requireRoles(['admin', 'teacher']);

    $input = json_decode(file_get_contents('php://input'), true);
    $stationId = (int) ($input['id'] ?? ($_GET['id'] ?? 0));

    if (!$stationId) {
        http_response_code(400);
        echo json_encode(['error' => 'Station ID required']);
        exit;
    }

    $allowed = ['status', 'station_code', 'hostname', 'ip_address', 'is_maintenance'];
    $update = array_intersect_key($input, array_flip($allowed));

    if ($labService->updateStation($stationId, $update)) {
        echo json_encode(['success' => true, 'message' => 'Station updated']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update station']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);