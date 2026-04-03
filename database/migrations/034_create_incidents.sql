-- =============================================
-- XPLabs - Incident Management Tables
-- =============================================

CREATE TABLE IF NOT EXISTS incidents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL COMMENT 'Type of incident',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('reported', 'investigating', 'resolved', 'dismissed') DEFAULT 'reported',
    location VARCHAR(100) DEFAULT NULL COMMENT 'Lab/floor where incident occurred',
    lab_id INT DEFAULT NULL,
    floor_id INT DEFAULT NULL,
    station_id INT DEFAULT NULL,
    reported_by INT NOT NULL COMMENT 'User who reported the incident',
    assigned_to INT DEFAULT NULL COMMENT 'User assigned to resolve',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    resolution_notes TEXT,
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_location (lab_id, floor_id),
    INDEX idx_reported_by (reported_by),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE SET NULL,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incident_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incident_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'Action taken',
    old_value VARCHAR(255) DEFAULT NULL,
    new_value VARCHAR(255) DEFAULT NULL,
    notes TEXT,
    performed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_incident (incident_id),
    INDEX idx_performed_by (performed_by),
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;