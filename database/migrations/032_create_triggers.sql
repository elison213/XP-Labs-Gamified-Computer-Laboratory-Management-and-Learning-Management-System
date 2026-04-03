-- XPLabs Migration 032: Triggers for point balance auto-update
-- Keeps user_point_balances in sync with user_points ledger

-- Trigger: After INSERT on user_points
CREATE TRIGGER trg_user_points_after_insert
AFTER INSERT ON user_points
FOR EACH ROW
BEGIN
    INSERT INTO user_point_balances (user_id, total_earned, total_spent, balance)
    VALUES (NEW.user_id, 0, 0, 0)
    ON DUPLICATE KEY UPDATE
        total_earned = total_earned + IF(NEW.points > 0, NEW.points, 0),
        total_spent = total_spent + IF(NEW.points < 0, ABS(NEW.points), 0),
        balance = balance + NEW.points,
        updated_at = NOW();
END