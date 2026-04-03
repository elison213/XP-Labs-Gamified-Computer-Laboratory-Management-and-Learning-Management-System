-- XPLabs Migration 017: Power-ups table
-- Power-up definitions for quiz gameplay

CREATE TABLE IF NOT EXISTS powerups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT NULL COMMENT 'emoji',
    point_cost INT NOT NULL,
    type ENUM('quiz', 'reward') NOT NULL DEFAULT 'quiz',
    category VARCHAR(50) DEFAULT NULL COMMENT 'timer, hints, scoring, skip, exemption, privilege',
    config JSON DEFAULT NULL COMMENT '{"effect":"freeze_timer","duration":10}',
    is_active TINYINT(1) DEFAULT 1,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;