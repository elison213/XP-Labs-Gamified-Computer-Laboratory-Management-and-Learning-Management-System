<?php
namespace XPLabs\Services;

use XPLabs\Lib\Database;

class InventoryService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all inventory items with filtering
     */
    public function getItems($filters = []) {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'i.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['lab_id'])) {
            $where[] = 'i.lab_id = ?';
            $params[] = $filters['lab_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(i.name LIKE ? OR i.item_code LIKE ? OR i.description LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if (!empty($filters['limit'])) {
            $limit = (int) $filters['limit'];
        } else {
            $limit = 200;
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT i.*, 
                    u.first_name, u.last_name,
                    l.name as lab_name, lf.name as floor_name
             FROM inventory_items i
             LEFT JOIN users u ON i.assigned_to = u.id
             LEFT JOIN labs l ON i.lab_id = l.id
             LEFT JOIN lab_floors lf ON i.floor_id = lf.id
             WHERE $whereClause
             ORDER BY i.item_code ASC
             LIMIT $limit",
            $params
        );
    }

    /**
     * Get a single item by ID
     */
    public function getItem($id) {
        return $this->db->fetch(
            "SELECT i.*, 
                    u.first_name, u.last_name,
                    l.name as lab_name, lf.name as floor_name
             FROM inventory_items i
             LEFT JOIN users u ON i.assigned_to = u.id
             LEFT JOIN labs l ON i.lab_id = l.id
             LEFT JOIN lab_floors lf ON i.floor_id = lf.id
             WHERE i.id = ?",
            [(int)$id]
        );
    }

    /**
     * Get item by item code
     */
    public function getItemByCode($code) {
        return $this->db->fetch(
            "SELECT * FROM inventory_items WHERE item_code = ?",
            [$code]
        );
    }

    /**
     * Create a new inventory item
     */
    public function createItem($data) {
        $itemData = [
            'item_code' => $data['item_code'] ?? 'ITEM-' . time(),
            'name' => $data['name'] ?? '',
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? '',
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'status' => 'available',
            'quantity' => (int)($data['quantity'] ?? 1),
            'lab_id' => !empty($data['lab_id']) ? (int)$data['lab_id'] : null,
            'floor_id' => !empty($data['floor_id']) ? (int)$data['floor_id'] : null,
            'condition_rating' => $data['condition_rating'] ?? 'good',
            'purchase_date' => !empty($data['purchase_date']) ? $data['purchase_date'] : null,
            'warranty_expiry' => !empty($data['warranty_expiry']) ? $data['warranty_expiry'] : null,
            'notes' => $data['notes'] ?? null,
        ];

        return $this->db->insert('inventory_items', $itemData);
    }

    /**
     * Update an inventory item
     */
    public function updateItem($id, $data, $performedBy) {
        $item = $this->getItem($id);
        if (!$item) {
            return false;
        }

        $updates = [];
        foreach (['name', 'category', 'description', 'brand', 'model', 'serial_number', 
                   'status', 'quantity', 'lab_id', 'floor_id', 'condition_rating',
                   'purchase_date', 'warranty_expiry', 'notes'] as $field) {
            if (isset($data[$field]) && $data[$field] !== $item[$field]) {
                $oldValue = $item[$field];
                $newValue = $data[$field];
                $this->logItemAction($id, $field . '_changed', $oldValue, $newValue, "Updated $field", $performedBy);
                $updates[$field] = $newValue;
            }
        }

        if (empty($updates)) {
            return true;
        }

        return $this->db->update('inventory_items', $updates, 'id = ?', [(int)$id]);
    }

    /**
     * Delete an inventory item
     */
    public function deleteItem($id) {
        return $this->db->delete('inventory_items', 'id = ?', [(int)$id]);
    }

    /**
     * Check out an item to a user
     */
    public function checkout($id, $userId, $performedBy) {
        $result = $this->db->update('inventory_items', [
            'status' => 'in_use',
            'assigned_to' => (int)$userId,
        ], 'id = ?', [(int)$id]);

        if ($result) {
            $this->logItemAction($id, 'checkout', null, $userId, 'Item checked out to user', $performedBy);
        }

        return $result;
    }

    /**
     * Return an item
     */
    public function returnItem($id, $performedBy) {
        $result = $this->db->update('inventory_items', [
            'status' => 'available',
            'assigned_to' => null,
        ], 'id = ?', [(int)$id]);

        if ($result) {
            $this->logItemAction($id, 'return', null, null, 'Item returned', $performedBy);
        }

        return $result;
    }

    /**
     * Get inventory statistics
     */
    public function getStats($labId = null) {
        $where = '1=1';
        $params = [];
        if ($labId) {
            $where = 'lab_id = ?';
            $params[] = (int)$labId;
        }

        return $this->db->fetch(
            "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count,
                    SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as in_use_count,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count,
                    SUM(CASE WHEN status = 'damaged' THEN 1 ELSE 0 END) as damaged_count,
                    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_count
             FROM inventory_items
             WHERE $where",
            $params
        );
    }

    /**
     * Get item categories
     */
    public static function getCategories() {
        return [
            'computer' => 'Computer/PC',
            'monitor' => 'Monitor',
            'peripheral' => 'Peripheral (Mouse/Keyboard/Headset)',
            'printer' => 'Printer/Scanner',
            'network' => 'Network Equipment',
            'furniture' => 'Furniture (Chair/Desk)',
            'software' => 'Software License',
            'general' => 'General',
        ];
    }

    /**
     * Log an inventory action
     */
    private function logItemAction($itemId, $action, $oldValue, $newValue, $notes, $performedBy) {
        $this->db->insert('inventory_logs', [
            'item_id' => (int)$itemId,
            'action' => $action,
            'old_status' => is_string($oldValue) ? substr($oldValue, 0, 255) : $oldValue,
            'new_status' => is_string($newValue) ? substr($newValue, 0, 255) : $newValue,
            'notes' => $notes,
            'performed_by' => (int)$performedBy,
        ]);
    }

    /**
     * Get item logs
     */
    public function getItemLogs($itemId) {
        return $this->db->fetchAll(
            "SELECT il.*, u.first_name, u.last_name
             FROM inventory_logs il
             JOIN users u ON il.performed_by = u.id
             WHERE il.item_id = ?
             ORDER BY il.created_at DESC",
            [(int)$itemId]
        );
    }
}