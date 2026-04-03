-- Activity Feedback Table
-- Students rate activities for fun/difficulty to help teachers improve

CREATE TABLE IF NOT EXISTS activity_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('assignment', 'quiz', 'lab_session') NOT NULL,
    activity_id INT NOT NULL,
    fun_rating TINYINT NOT NULL COMMENT '1-5 stars rating for how fun the activity was',
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL,
    feedback TEXT COMMENT 'Optional text feedback from student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_activity (activity_type, activity_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;