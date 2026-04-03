-- XPLabs Migration 001: Users table
-- Stores all system users (students, teachers, admins)
-- LRN (Learner Reference Number) is the primary identifier for students

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(20) DEFAULT NULL UNIQUE COMMENT 'Learner Reference Number (used as QR code)',
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student',
    grade_level VARCHAR(10) DEFAULT NULL,
    section VARCHAR(20) DEFAULT NULL,
    homeroom_teacher VARCHAR(150) DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,

    INDEX idx_lrn (lrn),
    INDEX idx_role (role),
    INDEX idx_section (section),
    INDEX idx_grade_section (grade_level, section),
    INDEX idx_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;