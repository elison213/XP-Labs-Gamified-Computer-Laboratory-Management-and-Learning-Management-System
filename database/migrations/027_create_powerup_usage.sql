-- XPLabs Migration 027: Power-up usage table
-- Log of power-ups used by students

CREATE TABLE IF NOT EXISTS powerup_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    powerup_id INT NOT NULL,
    points_spent INT NOT NULL,
    context_type VARCHAR(50) DEFAULT NULL COMMENT 'quiz_attempt, reward_redemption',
    context_id INT DEFAULT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('used', 'cancelled', 'refund') DEFAULT 'used',
    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (powerup_id) REFERENCES powerups(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;