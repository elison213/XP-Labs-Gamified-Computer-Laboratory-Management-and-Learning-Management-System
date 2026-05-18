-- Staff preview attempts: do not award points or pollute leaderboards.
ALTER TABLE quiz_attempts
    ADD COLUMN IF NOT EXISTS is_preview TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

CREATE INDEX IF NOT EXISTS idx_quiz_attempts_quiz_status_preview
    ON quiz_attempts (quiz_id, status, is_preview);
