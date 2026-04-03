-- XPLabs Sample Data for Testing Student Portal
-- Run this after migrations to have test data for assignments and submissions

-- Insert a sample course (if not exists)
INSERT IGNORE INTO courses (code, name, subject, description, teacher_id, target_grade, target_section, academic_year, status)
SELECT 'WEBDEV-101', 'Web Development - Grade 7', 'web_development', 'Introduction to HTML, CSS, and JavaScript', 1, '7', 'Newton', '2024-2025', 'active'
WHERE NOT EXISTS (SELECT 1 FROM courses WHERE code = 'WEBDEV-101');

-- Insert a sample assignment (if not exists)
INSERT IGNORE INTO assignments (course_id, title, description, created_by, due_date, max_points, status)
SELECT id, 'HTML Structure Lab', 'Build a webpage using semantic HTML elements. Include a header, main content area with at least one list, and a footer. Add proper meta tags and a link to an external CSS file.', 1, DATE_ADD(NOW(), INTERVAL 7 DAY), 100, 'published'
FROM courses WHERE code = 'WEBDEV-101'
AND NOT EXISTS (SELECT 1 FROM assignments WHERE title = 'HTML Structure Lab');

-- Insert another sample assignment
INSERT IGNORE INTO assignments (course_id, title, description, created_by, due_date, max_points, status)
SELECT id, 'CSS Flexbox Practice', 'Create a responsive card layout using CSS Flexbox. Center the cards both horizontally and vertically. Include at least 3 cards with different content.', 1, DATE_ADD(NOW(), INTERVAL 14 DAY), 100, 'published'
FROM courses WHERE code = 'WEBDEV-101'
AND NOT EXISTS (SELECT 1 FROM assignments WHERE title = 'CSS Flexbox Practice');

-- Insert a past-due assignment for testing overdue status
INSERT IGNORE INTO assignments (course_id, title, description, created_by, due_date, max_points, status)
SELECT id, 'JavaScript Basics Quiz Prep', 'Review JavaScript variables, functions, and loops. Write a short summary of what you learned.', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), 50, 'published'
FROM courses WHERE code = 'WEBDEV-101'
AND NOT EXISTS (SELECT 1 FROM assignments WHERE title = 'JavaScript Basics Quiz Prep');

-- Enroll test student in the course (if not exists)
-- Assumes student with LRN '20240001' exists (from seed_test_accounts.sql)
INSERT IGNORE INTO course_enrollments (course_id, user_id, status)
SELECT c.id, u.id, 'enrolled'
FROM courses c
JOIN users u ON u.lrn = '20240001'
WHERE c.code = 'WEBDEV-101'
AND NOT EXISTS (
    SELECT 1 FROM course_enrollments ce 
    WHERE ce.course_id = c.id AND ce.user_id = u.id
);

-- Insert a sample submission for the first assignment (if not exists)
INSERT IGNORE INTO submissions (assignment_id, user_id, content, submitted_at, status, grade)
SELECT a.id, u.id, '<!DOCTYPE html>\n<html>\n<head><title>My Page</title></head>\n<body>\n  <header><h1>Welcome</h1></header>\n  <main>\n    <ul>\n      <li>Item 1</li>\n      <li>Item 2</li>\n    </ul>\n  </main>\n  <footer><p>Copyright 2024</p></footer>\n</body>\n</html>', DATE_SUB(NOW(), INTERVAL 2 DAY), 'graded', 85
FROM assignments a
JOIN users u ON u.lrn = '20240001'
WHERE a.title = 'HTML Structure Lab'
AND NOT EXISTS (
    SELECT 1 FROM submissions s 
    WHERE s.assignment_id = a.id AND s.user_id = u.id
);