-- XPLabs Migration 002: Courses table
-- Courses represent subject+section combinations managed by teachers

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Course code e.g., WEBDEV-7N',
    name VARCHAR(150) NOT NULL COMMENT 'Full name e.g., Web Development - Grade 7 Newton',
    subject ENUM('computer_programming', 'web_development', 'visual_graphics', 'it_fundamentals', 'cs_concepts', 'other') NOT NULL,
    description TEXT DEFAULT NULL,
    teacher_id INT NOT NULL,
    target_grade VARCHAR(10) DEFAULT NULL,
    target_section VARCHAR(20) DEFAULT NULL,
    academic_year VARCHAR(20) DEFAULT NULL COMMENT 'e.g., 2024-2025',
    semester VARCHAR(20) DEFAULT NULL,
    status ENUM('active', 'archived', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_teacher_section (teacher_id, target_section, academic_year),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_subject (subject),
    INDEX idx_status (status),
    INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;