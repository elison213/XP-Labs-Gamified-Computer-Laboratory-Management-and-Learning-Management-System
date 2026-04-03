-- =============================================
-- XPLabs - Equipment/Inventory Management Tables
-- =============================================

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique identifier for the item',
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL COMMENT 'Equipment category',
    description TEXT,
    brand VARCHAR(100) DEFAULT NULL,
    model VARCHAR(100) DEFAULT NULL,
    serial_number VARCHAR(100) DEFAULT NULL,
    status ENUM('available', 'in_use', 'reserved', 'maintenance', 'damaged', 'lost') DEFAULT 'available',
    quantity INT UNSIGNED DEFAULT 1,
    lab_id INT UNSIGNED DEFAULT NULL COMMENT 'Lab where item is located',
    floor_id INT UNSIGNED DEFAULT NULL COMMENT 'Floor where item is located',
    station_id INT UNSIGNED DEFAULT NULL COMMENT 'Station/item assignment',
    assigned_to INT UNSIGNED DEFAULT NULL COMMENT 'User currently using the item',
    condition_rating ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
    purchase_date DATE DEFAULT NULL,
    warranty_expiry DATE DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_location (lab_id, floor_id),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_item_code (item_code),
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'Action taken (checkout, return, maintenance, etc)',
    old_status VARCHAR(50) DEFAULT NULL,
    new_status VARCHAR(50) DEFAULT NULL,
    notes TEXT,
    performed_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item (item_id),
    INDEX idx_action (action),
    INDEX idx_performed_by (performed_by),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;