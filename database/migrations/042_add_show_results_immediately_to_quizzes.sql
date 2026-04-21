-- XPLabs Migration 042: teacher control for immediate quiz results

ALTER TABLE quizzes
ADD COLUMN show_results_immediately TINYINT(1) DEFAULT 1 AFTER allow_powerups;

