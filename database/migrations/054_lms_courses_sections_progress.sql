-- LMS: grading track (JHS quarters vs SHS semesters), lesson progress, section presets.

ALTER TABLE courses
    ADD COLUMN grading_track ENUM('quarter', 'semester') NOT NULL DEFAULT 'semester' AFTER semester,
    ADD COLUMN grading_period_index TINYINT UNSIGNED NULL COMMENT '1-4 for quarter, 1-2 for semester' AFTER grading_track;

CREATE TABLE IF NOT EXISTS course_lesson_progress (
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    course_id INT NOT NULL,
    first_completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, lesson_id),
    KEY idx_progress_user_course (user_id, course_id),
    KEY idx_progress_lesson (lesson_id),
    CONSTRAINT fk_clp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_clp_lesson FOREIGN KEY (lesson_id) REFERENCES course_lessons(id) ON DELETE CASCADE,
    CONSTRAINT fk_clp_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS section_presets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_section_preset_name (name),
    KEY idx_section_preset_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
