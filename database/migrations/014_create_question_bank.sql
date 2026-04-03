-- XPLabs Migration 014: Question bank table
-- Reusable questions across quizzes

CREATE TABLE IF NOT EXISTS question_bank (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('multiple_choice', 'true_false', 'code_completion', 'code_ordering', 'output_prediction', 'short_answer', 'file_upload') NOT NULL,
    question_text TEXT NOT NULL,
    code_snippet TEXT DEFAULT NULL,
    code_language VARCHAR(20) DEFAULT NULL,
    options JSON DEFAULT NULL,
    correct_answer JSON DEFAULT NULL,
    explanation TEXT DEFAULT NULL,
    hint TEXT DEFAULT NULL,
    difficulty TINYINT DEFAULT 1 COMMENT '1=easy, 2=medium, 3=hard',
    points INT DEFAULT 10,
    subject VARCHAR(100) DEFAULT NULL,
    topic VARCHAR(100) DEFAULT NULL,
    tags JSON DEFAULT NULL,
    bloom_level VARCHAR(50) DEFAULT NULL,
    source_type ENUM('manual', 'csv_import', 'file_generated') DEFAULT 'manual',
    source_id INT DEFAULT NULL,
    usage_count INT DEFAULT 0,
    success_rate DECIMAL(5,2) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FULLTEXT KEY ft_question (question_text, code_snippet),
    INDEX idx_subject_topic (subject, topic),
    INDEX idx_difficulty (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;