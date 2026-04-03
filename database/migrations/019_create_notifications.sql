-- XPLabs Migration 019: Notifications table
-- In-app notifications for users

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'achievement, assignment_due, announcement, quiz_result',
    title VARCHAR(200) NOT NULL,
    body TEXT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    action_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_unread (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;