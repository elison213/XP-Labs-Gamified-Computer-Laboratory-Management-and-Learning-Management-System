-- Add missing columns to announcements table
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS is_pinned TINYINT(1) DEFAULT 0 AFTER is_active;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS content TEXT AFTER body;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS target_audience ENUM('all', 'students', 'teachers') DEFAULT 'all' AFTER is_pinned;