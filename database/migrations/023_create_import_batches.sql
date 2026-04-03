-- XPLabs Migration 023: Import batches table
-- Track CSV import operations

CREATE TABLE IF NOT EXISTS import_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    imported_by INT NOT NULL,
    total_rows INT DEFAULT NULL,
    success_count INT DEFAULT 0,
    duplicate_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    column_mapping JSON DEFAULT NULL COMMENT '{"LRN": "lrn", "First Name": "first_name"}',
    status ENUM('processing', 'completed', 'partial', 'failed') DEFAULT 'processing',
    error_log TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;