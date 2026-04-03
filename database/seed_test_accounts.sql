-- XPLabs - Test Accounts
-- Run this after migrations to create test accounts for each role
-- Passwords are all: password123

-- Clear existing test data
DELETE FROM user_points WHERE user_id IN (100, 101, 102);
DELETE FROM users WHERE id IN (100, 101, 102);

-- Admin Account
INSERT INTO users (id, lrn, first_name, last_name, email, role, password_hash, is_active, created_at)
VALUES (100, 'ADMIN001', 'System', 'Administrator', 'admin@xplabs.local', 'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW())
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1;

-- Teacher Account
INSERT INTO users (id, lrn, first_name, last_name, email, role, password_hash, is_active, created_at)
VALUES (101, 'TEACHER01', 'Juan', 'Dela Cruz', 'teacher@xplabs.local', 'teacher',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW())
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1;

-- Student Account
INSERT INTO users (id, lrn, first_name, last_name, email, role, password_hash, is_active, created_at)
VALUES (102, '20240001', 'Maria', 'Santos', 'student@xplabs.local', 'student',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW())
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1;

-- Create a test course for the teacher
INSERT INTO courses (id, name, code, teacher_id, status, created_at)
VALUES (200, 'Computer Science 101', 'CS101', 101, 'active', NOW())
ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id);

-- Enroll the student in the course
INSERT INTO course_enrollments (course_id, user_id, status, enrolled_at)
VALUES (200, 102, 'enrolled', NOW())
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- Create a test lab floor
INSERT INTO lab_floors (id, name, grid_cols, grid_rows, is_active, created_at)
VALUES (300, 'Main Lab', 6, 5, 1, NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Create test stations
INSERT INTO lab_stations (floor_id, station_code, row_label, col_number, status)
VALUES 
(300, 'PC-01', 'A', 1, 'offline'),
(300, 'PC-02', 'A', 2, 'offline'),
(300, 'PC-03', 'A', 3, 'offline'),
(300, 'PC-04', 'A', 4, 'offline'),
(300, 'PC-05', 'A', 5, 'offline'),
(300, 'PC-06', 'A', 6, 'offline'),
(300, 'PC-07', 'B', 1, 'offline'),
(300, 'PC-08', 'B', 2, 'offline'),
(300, 'PC-09', 'B', 3, 'offline'),
(300, 'PC-10', 'B', 4, 'offline'),
(300, 'PC-11', 'B', 5, 'offline'),
(300, 'PC-12', 'B', 6, 'offline')
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- Give the student some initial points
INSERT INTO user_points (user_id, points, reason, created_at)
VALUES (102, 100, 'initial_bonus', NOW());

-- Give the student an achievement
INSERT INTO user_achievements (user_id, achievement_id, earned_at)
SELECT 102, id, NOW() FROM achievements WHERE code = 'first_login' LIMIT 1;