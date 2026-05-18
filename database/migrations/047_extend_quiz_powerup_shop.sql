-- XPLabs Migration 047: Extend quiz powerup shop support
-- Adds inventory + anti-abuse constraints + default quiz powerups for shop UX.

CREATE TABLE IF NOT EXISTS quiz_attempt_powerup_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    powerup_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_attempt_user_powerup (attempt_id, user_id, powerup_id),
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (powerup_id) REFERENCES powerups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE quiz_powerup_activations
    ADD COLUMN usage_id INT NULL AFTER powerup_id,
    ADD COLUMN activation_payload JSON NULL AFTER effect_applied,
    ADD UNIQUE KEY uq_attempt_question_powerup (attempt_id, question_id, powerup_id),
    ADD KEY idx_qpa_attempt (attempt_id),
    ADD KEY idx_qpa_question (question_id),
    ADD CONSTRAINT fk_qpa_usage FOREIGN KEY (usage_id) REFERENCES powerup_usage(id) ON DELETE SET NULL;

-- Default quiz assist powerups for shop
INSERT INTO powerups (code, name, description, icon, point_cost, type, category, config, is_active)
VALUES
('mc_halve_choices', 'Half Choices', 'Remove half of incorrect options for a multiple choice question.', '✂️', 35, 'quiz', 'hints', '{"effect":"mc_halve_choices","allowed_types":["multiple_choice"],"per_question_limit":1}', 1),
('mc_reveal_answer', 'Reveal Answer', 'Reveal the correct option for a multiple choice question.', '✅', 120, 'quiz', 'hints', '{"effect":"mc_reveal_answer","allowed_types":["multiple_choice"],"per_question_limit":1,"auto_correct":false}', 1),
('quiz_exemption', 'Quiz Exemption', 'Skip one question without penalty.', '🛡️', 150, 'quiz', 'skip', '{"effect":"quiz_exemption","allowed_types":["multiple_choice","true_false","short_answer","code_completion","output_prediction"],"per_attempt_limit":1}', 1),
('fib_scramble_hint', 'Scrambled Word', 'Show a scrambled version of the correct word.', '🔀', 45, 'quiz', 'hints', '{"effect":"fib_scramble_hint","allowed_types":["short_answer"],"per_question_limit":1}', 1),
('fib_reveal_letters', 'Reveal Letters', 'Reveal some letters of the correct answer.', '🔍', 60, 'quiz', 'hints', '{"effect":"fib_reveal_letters","allowed_types":["short_answer"],"per_question_limit":1,"letters":2}', 1),
('fib_dictionary_hint', 'Dictionary Hint', 'Show dictionary meaning of the target word.', '📖', 70, 'quiz', 'hints', '{"effect":"fib_dictionary_hint","allowed_types":["short_answer"],"per_question_limit":1}', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    icon = VALUES(icon),
    point_cost = VALUES(point_cost),
    type = VALUES(type),
    category = VALUES(category),
    config = VALUES(config),
    is_active = VALUES(is_active);
