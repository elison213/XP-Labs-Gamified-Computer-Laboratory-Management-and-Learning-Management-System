-- XPLabs Migration 012: Quiz attempts table
-- Student quiz sessions

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    station_id INT DEFAULT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    total_score DECIMAL(8,2) DEFAULT 0,
    max_score DECIMAL(8,2) DEFAULT 0,
    correct_answers INT DEFAULT 0,
    total_questions INT DEFAULT 0,
    status ENUM('in_progress', 'completed', 'abandoned', 'submitted_late') DEFAULT 'in_progress',
    is_reviewed TINYINT(1) DEFAULT 0,

    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES lab_stations(id) ON DELETE SET NULL,
    INDEX idx_user_quiz (user_id, quiz_id),
    INDEX idx_quiz_status (quiz_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;