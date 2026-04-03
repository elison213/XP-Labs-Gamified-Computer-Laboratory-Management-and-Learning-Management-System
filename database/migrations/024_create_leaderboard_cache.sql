-- XPLabs Migration 024: Leaderboard cache table
-- Pre-calculated rankings for performance

CREATE TABLE IF NOT EXISTS leaderboard_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    period ENUM('daily', 'weekly', 'monthly', 'all_time') NOT NULL,
    period_value VARCHAR(20) DEFAULT NULL COMMENT 'e.g., "2024-W15", "2024-04"',
    total_points INT NOT NULL DEFAULT 0,
    rank_position INT NOT NULL,
    course_id INT DEFAULT NULL COMMENT 'Course-specific leaderboard',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_period_course (user_id, period, period_value, course_id),
    INDEX idx_rank (period, period_value, course_id, rank_position),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;