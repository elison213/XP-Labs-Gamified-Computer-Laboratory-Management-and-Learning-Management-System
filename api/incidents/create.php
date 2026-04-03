<?php
/**
 * API: Create or Update Incident
 * POST /api/incidents/create.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\IncidentService;

Auth::requireRole(['admin', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = Auth::id();
$service = new IncidentService();

try {
    $action = $input['action'] ?? 'create';
    
    if ($action === 'create') {
        $incidentId = $service->createIncident($input, $userId);
        
        if ($incidentId) {
            echo json_encode([
                'success' => true,
                'message' => 'Incident reported successfully',
                'incident_id' => $incidentId,
            ]);
        } else {
            throw new \Exception('Failed to create incident');
        }
    } elseif ($action === 'update') {
        $incidentId = (int)($input['incident_id'] ?? 0);
        if (!$incidentId) {
            throw new \Exception('Incident ID is required');
        }
        
        $result = $service->updateIncident($incidentId, $input, $userId);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Incident updated successfully',
            ]);
        } else {
            throw new \Exception('Failed to update incident');
        }
    } elseif ($action === 'delete') {
        $incidentId = (int)($input['incident_id'] ?? 0);
        if (!$incidentId) {
            throw new \Exception('Incident ID is required');
        }
        
        $result = $service->deleteIncident($incidentId);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Incident deleted successfully',
            ]);
        } else {
            throw new \Exception('Failed to delete incident');
        }
    } else {
        throw new \Exception('Invalid action');
    }
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}