-- Mobile/browser kiosk phones: admin-entered MAC + API token (browser cannot read MAC).
CREATE TABLE IF NOT EXISTS kiosk_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL DEFAULT '',
    mac_address VARCHAR(17) NOT NULL COMMENT 'Normalized aa:bb:cc — admin-entered from phone settings',
    floor_id INT NOT NULL,
    token_hash VARCHAR(64) NULL COMMENT 'SHA-256 hex of full kiosk token',
    pairing_code_hash VARCHAR(255) NULL,
    pairing_expires_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at DATETIME NULL,
    last_seen_ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_kiosk_mac (mac_address),
    KEY idx_kiosk_floor (floor_id),
    KEY idx_kiosk_active (is_active),
    CONSTRAINT fk_kiosk_devices_floor FOREIGN KEY (floor_id) REFERENCES lab_floors(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
