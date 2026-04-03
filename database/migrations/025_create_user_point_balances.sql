-- XPLabs Migration 025: User point balances table
-- Cached balance for quick lookups (auto-updated via trigger)

CREATE TABLE IF NOT EXISTS user_point_balances (
    user_id INT PRIMARY KEY,
    total_earned INT DEFAULT 0 COMMENT 'Lifetime points earned',
    total_spent INT DEFAULT 0 COMMENT 'Lifetime points spent',
    balance INT DEFAULT 0 COMMENT 'total_earned - total_spent',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;