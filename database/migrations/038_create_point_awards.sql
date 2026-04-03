-- Point Awards Table
-- Teachers can manually award points to students for good behavior, participation, etc.

CREATE TABLE IF NOT EXISTS point_awards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    awarded_by INT NOT NULL COMMENT 'Teacher/admin who gave the award',
    user_id INT NOT NULL COMMENT 'Student who received the award',
    points INT NOT NULL COMMENT 'Number of points awarded',
    reason VARCHAR(255) NOT NULL COMMENT 'Reason for the award',
    award_type ENUM('behavior', 'participation', 'achievement', 'helping_others', 'improvement', 'other') DEFAULT 'other',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_awarded_by (awarded_by),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (awarded_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;