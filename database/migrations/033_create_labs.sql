-- Create labs table (higher-level lab groups)
CREATE TABLE IF NOT EXISTS labs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    building VARCHAR(100),
    floor_number INT DEFAULT 1,
    grid_cols INT DEFAULT 6,
    grid_rows INT DEFAULT 5,
    layout_config JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add lab_id to lab_floors (make floors belong to labs)
ALTER TABLE lab_floors ADD COLUMN IF NOT EXISTS lab_id INT DEFAULT NULL AFTER id;
ALTER TABLE lab_floors ADD CONSTRAINT fk_floor_lab FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE SET NULL;

-- Insert default lab
INSERT INTO labs (name, description, building, floor_number, grid_cols, grid_rows) VALUES
('Main Computer Lab', 'Primary computer laboratory', 'Main Building', 1, 6, 5)
ON DUPLICATE KEY UPDATE name = name;