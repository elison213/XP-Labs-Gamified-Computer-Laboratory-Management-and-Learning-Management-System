<?php
/**
 * API: List Inventory Items
 * GET /api/inventory/list.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\InventoryService;

Auth::requireRole(['admin', 'teacher']);

$service = new InventoryService();
$filters = [
    'status' => $_GET['status'] ?? null,
    'category' => $_GET['category'] ?? null,
    'lab_id' => !empty($_GET['lab_id']) ? (int)$_GET['lab_id'] : null,
    'search' => $_GET['search'] ?? null,
    'limit' => !empty($_GET['limit']) ? (int)$_GET['limit'] : 200,
];

$items = $service->getItems(array_filter($filters));

echo json_encode([
    'success' => true,
    'items' => $items,
    'total' => count($items),
    'categories' => InventoryService::getCategories(),
]);