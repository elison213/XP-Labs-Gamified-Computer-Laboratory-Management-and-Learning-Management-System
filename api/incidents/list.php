<?php
/**
 * API: List Incidents
 * GET /api/incidents/list.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\IncidentService;

Auth::requireRole(['admin', 'teacher']);

$service = new IncidentService();
$filters = [
    'status' => $_GET['status'] ?? null,
    'severity' => $_GET['severity'] ?? null,
    'type' => $_GET['type'] ?? null,
    'lab_id' => !empty($_GET['lab_id']) ? (int)$_GET['lab_id'] : null,
    'limit' => !empty($_GET['limit']) ? (int)$_GET['limit'] : 100,
];

$incidents = $service->getIncidents(array_filter($filters));

echo json_encode([
    'success' => true,
    'incidents' => $incidents,
    'total' => count($incidents),
    'types' => IncidentService::getIncidentTypes(),
]);