-- XPLabs Migration 010: Quizzes table
-- Course-scoped quizzes created by teachers

CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    created_by INT NOT NULL,
    time_limit_per_q INT DEFAULT 30 COMMENT 'seconds per question',
    shuffle_questions TINYINT(1) DEFAULT 0,
    shuffle_answers TINYINT(1) DEFAULT 1,
    show_live_leaderboard TINYINT(1) DEFAULT 1,
    allow_powerups TINYINT(1) DEFAULT 1,
    status ENUM('draft', 'scheduled', 'active', 'completed', 'archived') DEFAULT 'draft',
    scheduled_at DATETIME DEFAULT NULL,
    closes_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_course (course_id, status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;