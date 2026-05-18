-- PC desktop / session activity (widget + agent), not admin CRUD audit
CREATE TABLE IF NOT EXISTS pc_activity_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pc_id INT NOT NULL,
    user_id INT NULL COMMENT 'Student user_id when known',
    event_type VARCHAR(64) NOT NULL,
    event_payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pc_created (pc_id, created_at),
    INDEX idx_pc_type (pc_id, event_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
