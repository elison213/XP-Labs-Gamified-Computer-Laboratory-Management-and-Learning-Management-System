-- XPLabs Migration 011: Quiz questions table
-- Individual questions within quizzes

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_number INT NOT NULL,
    type ENUM('multiple_choice', 'true_false', 'code_completion', 'code_ordering', 'output_prediction', 'short_answer', 'file_upload') NOT NULL,
    question_text TEXT NOT NULL,
    code_snippet TEXT DEFAULT NULL COMMENT 'Code block for programming questions',
    code_language VARCHAR(20) DEFAULT NULL COMMENT 'html, css, js, python',
    options JSON DEFAULT NULL COMMENT 'MCQ options array',
    correct_answer JSON DEFAULT NULL COMMENT 'Correct answer structure',
    points INT DEFAULT 10,
    time_limit INT DEFAULT NULL COMMENT 'Override quiz default seconds',
    hint TEXT DEFAULT NULL,
    explanation TEXT DEFAULT NULL COMMENT 'Shown after quiz ends',

    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz (quiz_id, question_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;