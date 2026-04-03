-- XPLabs Migration 031: Seed data
-- Default achievements, powerups, and admin user

-- Default admin user (password: admin123 - change immediately!)
INSERT INTO users (lrn, first_name, last_name, role, password_hash) VALUES
('ADMIN-001', 'System', 'Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash);

-- Default achievements
INSERT IGNORE INTO achievements (code, name, description, icon, points_reward, criteria) VALUES
('first_login', 'First Steps', 'Logged in for the first time', '🎉', 10, '{"type":"first_login"}'),
('attendance_5', 'On a Roll', '5 consecutive days of attendance', '🔥', 25, '{"type":"attendance_streak","value":5}'),
('attendance_10', 'Week Warrior', '10 consecutive days of attendance', '⚔️', 50, '{"type":"attendance_streak","value":10}'),
('attendance_30', 'Monthly Champion', '30 consecutive days of attendance', '🏆', 100, '{"type":"attendance_streak","value":30}'),
('quiz_perfect', 'Perfect Score', 'Got 100% on a quiz', '💯', 30, '{"type":"quiz_perfect"}'),
('quiz_speed', 'Speed Demon', 'Completed a quiz in under 2 minutes', '⚡', 20, '{"type":"quiz_speed","max_seconds":120}'),
('assignment_early', 'Eager Beaver', 'Submitted an assignment 2+ days early', '🐝', 15, '{"type":"assignment_early","days_before":2}'),
('top_3', 'Rising Star', 'Ranked in top 3 on the leaderboard', '⭐', 40, '{"type":"leaderboard_top","rank":3}'),
('points_500', 'Point Collector', 'Accumulated 500 total points', '💎', 50, '{"type":"total_points","value":500}'),
('points_1000', 'Point Master', 'Accumulated 1000 total points', '👑', 100, '{"type":"total_points","value":1000}');

-- Default power-ups
INSERT IGNORE INTO powerups (code, name, description, icon, point_cost, type, category, config) VALUES
('freeze_timer', 'Freeze Timer', 'Stop the timer for 10 seconds', '🧊', 50, 'quiz', 'timer', '{"effect":"freeze","duration":10}'),
('double_time', 'Double Time', 'Add 15 extra seconds to the current question', '⏰', 80, 'quiz', 'timer', '{"effect":"add_time","seconds":15}'),
('double_points', 'Double Points', 'Earn 2x points on this question', '🎯', 120, 'quiz', 'scoring', '{"effect":"multiply_points","factor":2}'),
('fifty_fifty', '50:50', 'Remove 2 wrong answer options', '✂️', 60, 'quiz', 'hints', '{"effect":"remove_wrong","count":2}'),
('skip_question', 'Skip', 'Skip this question and earn base points', '📝', 100, 'quiz', 'skip', '{"effect":"skip","base_points":5}'),
('reveal_hint', 'Hint', 'Show a hint for this question', '💡', 40, 'quiz', 'hints', '{"effect":"show_hint"}'),
('bonus_rush', 'Bonus Rush', 'Next question is worth 3x points (wrong = 0)', '🎰', 300, 'quiz', 'scoring', '{"effect":"multiply_points","factor":3,"risk":true}');

-- Default rewards (created_by = 1, the admin user inserted above)
INSERT IGNORE INTO rewards (name, description, icon, point_cost, category, requires_approval, created_by) VALUES
('Quiz Exemption Card', 'Skip 1 question on any future quiz', '📋', 1000, 'exemption', 1, 1),
('Free Lab Pass', 'Skip lab check-in for one session', '💻', 500, 'privilege', 1, 1),
('10-Min Game Time', 'Play games for the last 10 minutes of class', '🎮', 300, 'fun', 1, 1),
('No Homework Pass', 'Skip 1 homework assignment', '🏆', 2000, 'exemption', 1, 1),
('Extra Attempt', 'Re-submit a failed assignment', '📝', 250, 'privilege', 1, 1),
('Choose Your Seat', 'Pick your own seat for 1 week', '🌟', 150, 'privilege', 0, 1),
('Custom Desktop', 'Set your lab PC wallpaper for 1 week', '🎨', 400, 'fun', 1, 1),
('Play Music', 'Play your music during lab work time', '🎤', 200, 'fun', 1, 1);
