-- XPLabs Migration 041: Add attachment to course lessons

ALTER TABLE course_lessons
ADD COLUMN attachment_url VARCHAR(500) DEFAULT NULL AFTER content;

