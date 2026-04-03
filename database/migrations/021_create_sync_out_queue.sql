-- XPLabs Migration 021: Sync out queue table
-- Outbox pattern for local-to-cloud sync

CREATE TABLE IF NOT EXISTS sync_out_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    operation ENUM('insert', 'update', 'delete') NOT NULL,
    payload JSON DEFAULT NULL,
    status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
    retry_count INT DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL DEFAULT NULL,

    INDEX idx_pending (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;