-- XPLabs Migration 041: Add attachment to course lessons

ALTER TABLE course_lessons
ADD COLUMN IF NOT EXISTS attachment_url VARCHAR(500) DEFAULT NULL AFTER content;

