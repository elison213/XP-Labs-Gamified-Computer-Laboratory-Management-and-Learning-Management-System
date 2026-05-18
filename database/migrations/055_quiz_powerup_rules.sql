-- XPLabs Migration 055: Per-quiz powerup rules (teacher shop customization)
-- Allows teachers/admins to disable specific quiz powerups (ex: quiz_exemption) per quiz.

CREATE TABLE IF NOT EXISTS quiz_powerup_rules (
    quiz_id INT NOT NULL,
    powerup_id INT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (quiz_id, powerup_id),
    KEY idx_qpr_quiz (quiz_id),
    KEY idx_qpr_powerup (powerup_id),
    CONSTRAINT fk_qpr_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_qpr_powerup FOREIGN KEY (powerup_id) REFERENCES powerups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

