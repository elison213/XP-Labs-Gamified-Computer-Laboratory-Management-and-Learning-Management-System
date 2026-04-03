-- XPLabs Migration 005: Lab stations table
-- Individual PC/seats within each lab floor with grid positions

CREATE TABLE IF NOT EXISTS lab_stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    floor_id INT NOT NULL,
    station_code VARCHAR(10) NOT NULL COMMENT 'e.g., PC-01, A-01',
    row_label VARCHAR(5) DEFAULT NULL COMMENT 'A, B, C, D',
    col_number TINYINT DEFAULT NULL,
    hostname VARCHAR(50) DEFAULT NULL COMMENT 'Windows hostname of PC',
    mac_address VARCHAR(17) DEFAULT NULL COMMENT 'For network-based detection',
    ip_address VARCHAR(15) DEFAULT NULL,
    status ENUM('offline', 'idle', 'active', 'maintenance') DEFAULT 'offline',
    grid_x SMALLINT DEFAULT NULL COMMENT 'Pixel X position on floor canvas',
    grid_y SMALLINT DEFAULT NULL COMMENT 'Pixel Y position on floor canvas',
    zone_id INT DEFAULT NULL,
    sort_order INT DEFAULT NULL COMMENT 'Display order override',
    notes TEXT DEFAULT NULL,

    UNIQUE KEY unique_station_code (floor_id, station_code),
    UNIQUE KEY unique_position (floor_id, row_label, col_number),
    FOREIGN KEY (floor_id) REFERENCES lab_floors(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_floor (floor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;