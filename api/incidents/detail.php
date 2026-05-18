<?php
/**
 * GET /api/incidents/detail.php?id=
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\IncidentService;

Auth::requireRole(['admin', 'teacher']);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'id required']);
    exit;
}

$service = new IncidentService();
$incident = $service->getIncident($id);
if (!$incident) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}

$logs = $service->getIncidentLogs($id);

echo json_encode([
    'success' => true,
    'incident' => $incident,
    'logs' => $logs,
]);
