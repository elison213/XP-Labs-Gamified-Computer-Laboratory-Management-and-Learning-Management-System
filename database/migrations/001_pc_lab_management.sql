-- =====================================================
-- XPLabs - PC Lab Management Module
-- Migration: 001_pc_lab_management.sql
-- Date: 2026-04-07
-- Description: Creates tables for PC lab management,
--              session tracking, drive mappings, folder
--              access rules, and remote commands.
-- =====================================================

-- =====================================================
-- Table: lab_pcs
-- Inventory of all lab computers registered in the system
-- =====================================================
CREATE TABLE IF NOT EXISTS lab_pcs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(100) NOT NULL UNIQUE COMMENT 'Computer hostname',
    floor_id INT NULL COMMENT 'Links to lab_floors table',
    station_id INT NULL COMMENT 'Links to lab_stations table',
    ip_address VARCHAR(45) NULL,
    mac_address VARCHAR(17) NULL,
    machine_key VARCHAR(64) NOT NULL UNIQUE COMMENT 'API key for machine authentication',
    status ENUM('online', 'offline', 'locked', 'maintenance', 'idle') DEFAULT 'offline',
    last_heartbeat DATETIME NULL COMMENT 'Last heartbeat timestamp',
    config JSON NULL COMMENT 'PC-specific configuration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (floor_id) REFERENCES lab_floors(id) ON DELETE SET NULL,
    FOREIGN KEY (station_id) REFERENCES lab_stations(id) ON DELETE SET NULL,
    INDEX idx_hostname (hostname),
    INDEX idx_status (status),
    INDEX idx_heartbeat (last_heartbeat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: pc_sessions
-- Tracks student login sessions at specific PCs
-- =====================================================
CREATE TABLE IF NOT EXISTS pc_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pc_id INT NOT NULL,
    station_id INT NULL,
    checkin_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checkout_time DATETIME NULL,
    status ENUM('active', 'completed', 'forced_logout', 'timeout') DEFAULT 'active',
    checkout_reason VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pc_id) REFERENCES lab_pcs(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES lab_stations(id) ON DELETE SET NULL,
    INDEX idx_user_status (user_id, status),
    INDEX idx_pc_status (pc_id, status),
    INDEX idx_checkin (checkin_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: drive_mappings
-- Role-based drive letter assignments for lab users
-- =====================================================
CREATE TABLE IF NOT EXISTS drive_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL COMMENT 'User role: student, teacher, admin',
    drive_letter VARCHAR(1) NOT NULL,
    network_path VARCHAR(255) NOT NULL COMMENT 'UNC path with variables like %USERNAME%',
    label VARCHAR(50) NULL COMMENT 'Display label for the drive',
    is_persistent TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_drive (role, drive_letter),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: folder_access_rules
-- NTFS/folder permissions per lab floor and user role
-- =====================================================
CREATE TABLE IF NOT EXISTS folder_access_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    floor_id INT NULL COMMENT 'Applies to specific lab floor, NULL = global',
    role VARCHAR(50) NOT NULL COMMENT 'User role: student, teacher, admin',
    folder_path VARCHAR(255) NOT NULL COMMENT 'Full folder path or UNC path',
    permission ENUM('read', 'write', 'modify', 'full') DEFAULT 'read',
    apply_to_group VARCHAR(100) NULL COMMENT 'Windows group name to apply permissions to',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (floor_id) REFERENCES lab_floors(id) ON DELETE SET NULL,
    INDEX idx_floor_role (floor_id, role),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: remote_commands
-- Teacher-to-PC command queue (lock, unlock, message)
-- =====================================================
CREATE TABLE IF NOT EXISTS remote_commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pc_id INT NOT NULL,
    issued_by INT NOT NULL COMMENT 'Teacher/admin user_id who issued the command',
    command_type ENUM('lock', 'unlock', 'shutdown', 'restart', 'message', 'screenshot') NOT NULL,
    params JSON NULL COMMENT 'Additional parameters (e.g., message text)',
    status ENUM('pending', 'executed', 'failed', 'expired') DEFAULT 'pending',
    result TEXT NULL COMMENT 'Execution result or error message',
    executed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL COMMENT 'Command expires after this time',
    FOREIGN KEY (pc_id) REFERENCES lab_pcs(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pc_status (pc_id, status),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Seed Data: Default drive mappings for students
-- =====================================================
INSERT IGNORE INTO drive_mappings (role, drive_letter, network_path, label, sort_order, is_active) VALUES
('student', 'H', '\\\\SERVER\\home\\%USERNAME%', 'Home Drive', 1, 1),
('student', 'S', '\\\\SERVER\\shared', 'Shared Files', 2, 1),
('student', 'L', '\\\\SERVER\\lab\\%LABNAME%', 'Lab Resources', 3, 1),
('teacher', 'H', '\\\\SERVER\\home\\%USERNAME%', 'Home Drive', 1, 1),
('teacher', 'T', '\\\\SERVER\\teaching', 'Teaching Materials', 2, 1),
('teacher', 'L', '\\\\SERVER\\lab\\%LABNAME%', 'Lab Resources', 3, 1);

-- =====================================================
-- Seed Data: Default folder access rules
-- =====================================================
INSERT IGNORE INTO folder_access_rules (floor_id, role, folder_path, permission, apply_to_group, is_active) VALUES
(NULL, 'student', 'C:\\Temp', 'modify', 'Students', 1),
(NULL, 'student', 'C:\\Program Files', 'read', 'Students', 1),
(NULL, 'student', 'C:\\Windows', 'read', 'Students', 1),
(NULL, 'teacher', 'C:\\Temp', 'full', 'Teachers', 1),
(NULL, 'teacher', 'C:\\Program Files', 'read', 'Teachers', 1),
(NULL, 'admin', 'C:\\', 'full', 'Administrators', 1);

-- =====================================================
-- Verification Queries (run after migration)
-- =====================================================
-- SELECT 'Migration complete. Tables created:' AS status;
-- SHOW TABLES LIKE 'lab_pcs';
-- SHOW TABLES LIKE 'pc_sessions';
-- SHOW TABLES LIKE 'drive_mappings';
-- SHOW TABLES LIKE 'folder_access_rules';
-- SHOW TABLES LIKE 'remote_commands';
-- SELECT COUNT(*) AS drive_mappings_count FROM drive_mappings;
-- SELECT COUNT(*) AS folder_rules_count FROM folder_access_rules;