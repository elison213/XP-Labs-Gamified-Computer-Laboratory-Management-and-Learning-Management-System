<?php
/**
 * API: Create, Update, or Delete Inventory Item
 * POST /api/inventory/create.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\InventoryService;

Auth::requireRole(['admin', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = Auth::id();
$service = new InventoryService();

try {
    $action = $input['action'] ?? 'create';
    
    if ($action === 'create') {
        // Generate item code if not provided
        if (empty($input['item_code'])) {
            $input['item_code'] = 'ITEM-' . strtoupper(substr(uniqid(), -6));
        }
        
        $itemId = $service->createItem($input);
        
        if ($itemId) {
            echo json_encode([
                'success' => true,
                'message' => 'Item added successfully',
                'item_id' => $itemId,
            ]);
        } else {
            throw new \Exception('Failed to add item');
        }
    } elseif ($action === 'update') {
        $itemId = (int)($input['item_id'] ?? 0);
        if (!$itemId) {
            throw new \Exception('Item ID is required');
        }
        
        $result = $service->updateItem($itemId, $input, $userId);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Item updated successfully',
            ]);
        } else {
            throw new \Exception('Failed to update item');
        }
    } elseif ($action === 'delete') {
        $itemId = (int)($input['item_id'] ?? 0);
        if (!$itemId) {
            throw new \Exception('Item ID is required');
        }
        
        $result = $service->deleteItem($itemId);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Item deleted successfully',
            ]);
        } else {
            throw new \Exception('Failed to delete item');
        }
    } elseif ($action === 'checkout') {
        $itemId = (int)($input['item_id'] ?? 0);
        $checkoutUserId = (int)($input['user_id'] ?? 0);
        if (!$itemId || !$checkoutUserId) {
            throw new \Exception('Item ID and User ID are required');
        }
        
        $result = $service->checkout($itemId, $checkoutUserId, $userId);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Item checked out successfully',
            ]);
        } else {
            throw new \Exception('Failed to checkout item');
        }
    } elseif ($action === 'return') {
        $itemId = (int)($input['item_id'] ?? 0);
        if (!$itemId) {
            throw new \Exception('Item ID is required');
        }
        
        $result = $service->returnItem($itemId, $userId);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Item returned successfully',
            ]);
        } else {
            throw new \Exception('Failed to return item');
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