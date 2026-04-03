-- XPLabs Migration 013: Quiz answers table
-- Individual question responses within a quiz attempt

CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    user_id INT NOT NULL,
    answer JSON DEFAULT NULL COMMENT 'Student answer structure',
    is_correct TINYINT(1) DEFAULT NULL COMMENT 'NULL = needs manual grading',
    points_earned DECIMAL(5,2) DEFAULT 0,
    time_taken INT DEFAULT NULL COMMENT 'seconds spent on this question',
    powerup_used VARCHAR(50) DEFAULT NULL COMMENT 'Which power-up was used',
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;