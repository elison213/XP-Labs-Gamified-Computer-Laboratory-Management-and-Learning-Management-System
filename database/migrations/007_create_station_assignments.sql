-- XPLabs Migration 007: Station assignments table
-- Tracks who is currently sitting at which station

CREATE TABLE IF NOT EXISTS station_assignments (
    station_id INT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    task VARCHAR(255) DEFAULT NULL COMMENT 'Current assigned activity',

    FOREIGN KEY (station_id) REFERENCES lab_stations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;