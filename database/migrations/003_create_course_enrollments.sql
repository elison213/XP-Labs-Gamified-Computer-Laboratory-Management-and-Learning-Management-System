-- XPLabs Migration 003: Course enrollments table
-- Maps students to courses they are enrolled in

CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    enrolled_by INT DEFAULT NULL COMMENT 'Teacher who enrolled (NULL = admin/import)',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('enrolled', 'dropped', 'completed') DEFAULT 'enrolled',
    final_score DECIMAL(5,2) DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,

    UNIQUE KEY unique_course_user (course_id, user_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (enrolled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id, status),
    INDEX idx_course (course_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;