-- XPLabs Migration 018: Rewards table
-- Teacher-defined redeemable perks

CREATE TABLE IF NOT EXISTS rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT NULL COMMENT 'emoji',
    point_cost INT NOT NULL,
    category VARCHAR(50) DEFAULT NULL COMMENT 'exemption, privilege, item, fun',
    requires_approval TINYINT(1) DEFAULT 1,
    max_redemptions INT DEFAULT NULL COMMENT 'NULL = unlimited',
    times_redeemed INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    valid_until DATETIME DEFAULT NULL COMMENT 'NULL = no expiry',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;