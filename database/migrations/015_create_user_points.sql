-- XPLabs Migration 015: User points ledger (append-only)
-- All point earning and spending is recorded here

CREATE TABLE IF NOT EXISTS user_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL COMMENT 'Positive for earned, negative for spent',
    reason VARCHAR(100) DEFAULT NULL COMMENT 'attendance, assignment, quiz, bonus, penalty, reward',
    reference_type VARCHAR(50) DEFAULT NULL COMMENT 'attendance_session, submission, quiz_attempt, achievement, reward',
    reference_id INT DEFAULT NULL,
    awarded_by INT DEFAULT NULL COMMENT 'Teacher/admin ID or NULL for system',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (awarded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id, created_at),
    INDEX idx_reason (reason)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;