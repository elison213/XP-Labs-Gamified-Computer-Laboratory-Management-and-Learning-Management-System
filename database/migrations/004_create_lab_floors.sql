-- XPLabs Migration 004: Lab floors table
-- Defines lab rooms and their grid layout configuration

CREATE TABLE IF NOT EXISTS lab_floors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'e.g., Computer Lab 1, CS Lab 2',
    building VARCHAR(50) DEFAULT NULL,
    floor_number INT DEFAULT 1,
    teacher_id INT DEFAULT NULL,
    grid_rows TINYINT NOT NULL DEFAULT 4,
    grid_cols TINYINT NOT NULL DEFAULT 10,
    total_stations INT GENERATED ALWAYS AS (grid_rows * grid_cols) STORED,
    layout_config JSON COMMENT 'Grid config: aisle positions, zones, canvas settings',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active),
    INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;