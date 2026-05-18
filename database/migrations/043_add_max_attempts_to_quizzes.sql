-- XPLabs Migration 043: teacher/admin attempt limit control

ALTER TABLE quizzes
ADD COLUMN max_attempts INT DEFAULT 1 AFTER time_limit_per_q;

