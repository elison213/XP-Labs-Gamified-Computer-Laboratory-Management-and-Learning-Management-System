<?php
/**
 * XPLabs - Database Seeder
 * Run this after migrations to create test accounts.
 * Usage: php database/seed.php
 */

require_once __DIR__ . '/../lib/Database.php';

use XPLabs\Lib\Database;

echo "=== XPLabs Database Seeder ===\n\n";

try {
    $db = Database::getInstance();
    echo "Connected to database.\n\n";

    $passwordHash = password_hash('password123', PASSWORD_BCRYPT);

    // Create Admin
    echo "Creating Admin account...\n";
    $db->query(
        "INSERT INTO users (id, lrn, first_name, last_name, email, role, password_hash, is_active, created_at)
         VALUES (100, 'ADMIN001', 'System', 'Administrator', 'admin@xplabs.local', 'admin', ?, 1, NOW())
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1",
        [$passwordHash]
    );
    echo "  Admin: lrn=ADMIN001, password=password123\n";

    // Create Teacher
    echo "Creating Teacher account...\n";
    $db->query(
        "INSERT INTO users (id, lrn, first_name, last_name, email, role, password_hash, is_active, created_at)
         VALUES (101, 'TEACHER01', 'Juan', 'Dela Cruz', 'teacher@xplabs.local', 'teacher', ?, 1, NOW())
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1",
        [$passwordHash]
    );
    echo "  Teacher: lrn=TEACHER01, password=password123\n";

    // Create Student
    echo "Creating Student account...\n";
    $db->query(
        "INSERT INTO users (id, lrn, first_name, last_name, email, role, password_hash, is_active, created_at)
         VALUES (102, '20240001', 'Maria', 'Santos', 'student@xplabs.local', 'student', ?, 1, NOW())
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1",
        [$passwordHash]
    );
    echo "  Student: lrn=20240001, password=password123\n";

    // Create test course
    echo "\nCreating test course...\n";
    $db->query(
        "INSERT INTO courses (id, name, code, teacher_id, status)
         VALUES (200, 'Computer Science 101', 'CS101', 101, 'active')
         ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)"
    );
    echo "  Course: CS101 - Computer Science 101\n";

    // Enroll student
    echo "Enrolling student...\n";
    $db->query(
        "INSERT INTO course_enrollments (course_id, user_id, status, enrolled_at)
         VALUES (200, 102, 'enrolled', NOW())
         ON DUPLICATE KEY UPDATE status = VALUES(status)"
    );
    echo "  Student enrolled in CS101\n";

    // Create test lab floor
    echo "\nCreating test lab floor...\n";
    $db->query(
        "INSERT INTO lab_floors (id, name, grid_cols, grid_rows, is_active, created_at)
         VALUES (300, 'Main Lab', 6, 5, 1, NOW())
         ON DUPLICATE KEY UPDATE name = VALUES(name)"
    );
    echo "  Floor: Main Lab (6x5 grid)\n";

    // Create test stations
    echo "Creating test stations...\n";
    $stations = [
        ['PC-01', 'A', 1], ['PC-02', 'A', 2], ['PC-03', 'A', 3],
        ['PC-04', 'A', 4], ['PC-05', 'A', 5], ['PC-06', 'A', 6],
        ['PC-07', 'B', 1], ['PC-08', 'B', 2], ['PC-09', 'B', 3],
        ['PC-10', 'B', 4], ['PC-11', 'B', 5], ['PC-12', 'B', 6],
    ];
    foreach ($stations as [$code, $row, $col]) {
        $db->query(
            "INSERT INTO lab_stations (floor_id, station_code, row_label, col_number, status)
             VALUES (300, ?, ?, ?, 'offline')
             ON DUPLICATE KEY UPDATE status = VALUES(status)",
            [$code, $row, $col]
        );
    }
    echo "  Created " . count($stations) . " stations\n";

    // Give student initial points
    echo "\nGiving student initial points...\n";
    $db->query(
        "INSERT INTO user_points (user_id, points, reason, created_at)
         VALUES (102, 100, 'initial_bonus', NOW())
         ON DUPLICATE KEY UPDATE points = VALUES(points)"
    );
    echo "  Student has 100 points\n";

    echo "\n=== Seeding Complete! ===\n";
    echo "\nTest Accounts:\n";
    echo "  Admin:     lrn=ADMIN001, password=password123\n";
    echo "  Teacher:   lrn=TEACHER01, password=password123\n";
    echo "  Student:   lrn=20240001, password=password123\n";

} catch (\Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    exit(1);
}