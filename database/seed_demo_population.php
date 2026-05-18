<?php
/**
 * XPLabs - Realistic demo population (150+ rows across core tables).
 * Usage: php database/seed_demo_population.php
 *
 * Default password for all seeded accounts: password123
 */

require_once __DIR__ . '/../lib/Database.php';

use XPLabs\Lib\Database;

echo "=== XPLabs Demo Population Seeder ===\n\n";

$db = Database::getInstance();
$pw = password_hash('password123', PASSWORD_BCRYPT);

$counts = [];

function bump(array &$counts, string $key, int $n = 1): void
{
    $counts[$key] = ($counts[$key] ?? 0) + $n;
}

function randDate(int $daysBackMin, int $daysBackMax): string
{
    $days = random_int($daysBackMin, $daysBackMax);
    $hour = random_int(7, 16);
    $min = random_int(0, 59);
    return date('Y-m-d H:i:s', strtotime("-{$days} days") + ($hour * 3600) + ($min * 60));
}

// --- Staff ---
$staff = [
    ['ADMIN001', 'System', 'Administrator', 'admin@xplabs.edu.ph', 'admin', null, null],
    ['TEACHER01', 'Juan', 'Dela Cruz', 'juan.delacruz@xplabs.edu.ph', 'teacher', null, null],
    ['TEACHER02', 'Ana', 'Reyes', 'ana.reyes@xplabs.edu.ph', 'teacher', null, null],
    ['TEACHER03', 'Mark', 'Garcia', 'mark.garcia@xplabs.edu.ph', 'teacher', null, null],
];

$teacherIds = [];
foreach ($staff as $row) {
    [$lrn, $fn, $ln, $email, $role] = $row;
    $db->query(
        "INSERT INTO users (lrn, first_name, last_name, email, role, password_hash, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name),
           password_hash = VALUES(password_hash), is_active = 1",
        [$lrn, $fn, $ln, $email, $role, $pw, randDate(120, 200)]
    );
    $id = (int) $db->fetchOne('SELECT id FROM users WHERE lrn = ?', [$lrn]);
    if ($role === 'teacher') {
        $teacherIds[] = $id;
    }
    bump($counts, 'users');
}

$adminId = (int) $db->fetchOne("SELECT id FROM users WHERE lrn = 'ADMIN001'");

// --- Students (40) ---
$sections = ['Newton', 'Einstein', 'Curie', 'Tesla'];
$grades = ['7', '8', '9', '10'];
$firstNames = ['Maria', 'Jose', 'Angela', 'Carlos', 'Patricia', 'Miguel', 'Sofia', 'Gabriel', 'Isabella', 'Diego',
    'Andrea', 'Luis', 'Camila', 'Rafael', 'Elena', 'Antonio', 'Lucia', 'Fernando', 'Valentina', 'Ricardo',
    'Bianca', 'Emilio', 'Daniela', 'Alejandro', 'Paula', 'Sebastian', 'Natalia', 'Felipe', 'Carmen', 'Hector',
    'Rosa', 'Ivan', 'Teresa', 'Oscar', 'Monica', 'Pablo', 'Adriana', 'Victor', 'Gloria', 'Manuel'];
$lastNames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Rivera', 'Gonzales',
    'Ramos', 'Aquino', 'Castillo', 'Diaz', 'Morales', 'Ocampo', 'Soriano', 'Villanueva', 'Lim', 'Tan'];

$studentIds = [];
for ($i = 0; $i < 40; $i++) {
    $lrn = '2024' . str_pad((string) (1001 + $i), 4, '0', STR_PAD_LEFT);
    $fn = $firstNames[$i % count($firstNames)];
    $ln = $lastNames[$i % count($lastNames)];
    $grade = $grades[$i % count($grades)];
    $section = $sections[$i % count($sections)];
    $email = strtolower($fn . '.' . $ln . $i . '@student.xplabs.edu.ph');

    $db->query(
        "INSERT INTO users (lrn, first_name, last_name, email, role, grade_level, section, password_hash, is_active, created_at, last_login)
         VALUES (?, ?, ?, ?, 'student', ?, ?, ?, 1, ?, ?)
         ON DUPLICATE KEY UPDATE grade_level = VALUES(grade_level), section = VALUES(section),
           password_hash = VALUES(password_hash), is_active = 1",
        [$lrn, $fn, $ln, $email, $grade, $section, $pw, randDate(90, 180), randDate(1, 14)]
    );
    $studentIds[] = (int) $db->fetchOne('SELECT id FROM users WHERE lrn = ?', [$lrn]);
    bump($counts, 'users');
}

// --- Lab floor & stations ---
$db->query(
    "INSERT INTO lab_floors (id, name, grid_cols, grid_rows, is_active, created_at)
     VALUES (1, 'ICT Lab - Floor 1', 6, 5, 1, NOW())
     ON DUPLICATE KEY UPDATE name = VALUES(name)"
);
$floorId = (int) ($db->fetchOne('SELECT id FROM lab_floors ORDER BY id LIMIT 1') ?: 1);
bump($counts, 'lab_floors');

for ($r = 0; $r < 5; $r++) {
    for ($c = 1; $c <= 6; $c++) {
        $code = chr(65 + $r) . '-' . $c;
        $db->query(
            "INSERT INTO lab_stations (floor_id, station_code, row_label, col_number, status)
             VALUES (?, ?, ?, ?, 'idle')
             ON DUPLICATE KEY UPDATE status = VALUES(status)",
            [$floorId, 'PC-' . $code, chr(65 + $r), $c]
        );
        bump($counts, 'lab_stations');
    }
}

// --- Courses ---
$courseDefs = [
    ['WEBDEV-7N', 'Web Development - Grade 7 Newton', 'web_development', '7', 'Newton', 0],
    ['WEBDEV-8E', 'Web Development - Grade 8 Einstein', 'web_development', '8', 'Einstein', 1],
    ['CP-9C', 'Computer Programming - Grade 9 Curie', 'computer_programming', '9', 'Curie', 2],
    ['ITF-10T', 'IT Fundamentals - Grade 10 Tesla', 'it_fundamentals', '10', 'Tesla', 0],
    ['CS-8N', 'CS Concepts - Grade 8 Newton', 'cs_concepts', '8', 'Newton', 1],
];
$courseIds = [];
foreach ($courseDefs as $c) {
    [$code, $name, $subject, $grade, $section, $tIdx] = $c;
    $teacherId = $teacherIds[$tIdx % count($teacherIds)];
    $db->query(
        "INSERT INTO courses (code, name, subject, description, teacher_id, target_grade, target_section, academic_year, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, '2025-2026', 'active', ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), teacher_id = VALUES(teacher_id)",
        [$code, $name, $subject, "Lab and lecture sessions for {$name}.", $teacherId, $grade, $section, randDate(60, 120)]
    );
    $courseIds[] = (int) $db->fetchOne('SELECT id FROM courses WHERE code = ?', [$code]);
    bump($counts, 'courses');
}

// --- Enrollments ---
foreach ($studentIds as $sid) {
    $student = $db->fetch('SELECT grade_level, section FROM users WHERE id = ?', [$sid]);
    foreach ($courseIds as $cid) {
        $course = $db->fetch('SELECT target_grade, target_section FROM courses WHERE id = ?', [$cid]);
        if ($course['target_grade'] === $student['grade_level'] && $course['target_section'] === $student['section']) {
            $db->query(
                "INSERT INTO course_enrollments (course_id, user_id, status, enrolled_at)
                 VALUES (?, ?, 'enrolled', ?)
                 ON DUPLICATE KEY UPDATE status = 'enrolled'",
                [$cid, $sid, randDate(45, 90)]
            );
            bump($counts, 'course_enrollments');
        }
    }
}

// --- Assignments & submissions ---
$assignmentTitles = [
    'HTML Portfolio Page', 'CSS Flexbox Layout', 'JavaScript Calculator', 'Responsive Navbar',
    'Database ER Diagram', 'PHP Login Form', 'Group Project Milestone 1', 'Midterm Review Sheet',
];
foreach ($courseIds as $cid) {
    $teacherId = (int) $db->fetchOne('SELECT teacher_id FROM courses WHERE id = ?', [$cid]);
    foreach (array_slice($assignmentTitles, 0, 4) as $idx => $title) {
        $due = randDate(-14, 21);
        $created = randDate(30, 60);
        $db->query(
            "INSERT INTO assignments (course_id, title, description, created_by, due_date, max_points, status, created_at)
             VALUES (?, ?, ?, ?, ?, 100, 'published', ?)",
            [$cid, $title, "Complete the {$title} activity and submit before the due date.", $teacherId, $due, $created]
        );
        $aid = (int) $db->lastInsertId();
        bump($counts, 'assignments');

        $enrolled = $db->fetchAll('SELECT user_id FROM course_enrollments WHERE course_id = ? AND status = ?', [$cid, 'enrolled']);
        foreach ($enrolled as $en) {
            if (random_int(0, 100) > 35) {
                $submitted = randDate(5, 40);
                $status = strtotime($submitted) <= strtotime($due) ? 'submitted' : 'late';
                $score = random_int(70, 98);
                if (random_int(0, 10) > 6) {
                    $status = 'graded';
                }
                $db->query(
                    "INSERT INTO submissions (assignment_id, user_id, content, submitted_at, status, score)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE submitted_at = VALUES(submitted_at), status = VALUES(status), score = VALUES(score)",
                    [$aid, $en['user_id'], "<p>Submission for {$title}</p><pre>// sample work</pre>", $submitted, $status, $status === 'graded' ? $score : null]
                );
                bump($counts, 'submissions');
            }
        }
    }
}

// --- Quizzes & attempts ---
foreach (array_slice($courseIds, 0, 3) as $cid) {
    $teacherId = (int) $db->fetchOne('SELECT teacher_id FROM courses WHERE id = ?', [$cid]);
    $db->query(
        "INSERT INTO quizzes (course_id, title, description, created_by, status, scheduled_at, closes_at, created_at)
         VALUES (?, ?, ?, ?, 'completed', ?, ?, ?)",
        [$cid, 'Weekly Lab Quiz', 'Multiple choice review of recent topics.', $teacherId, randDate(20, 30), randDate(10, 18), randDate(35, 50)]
    );
    $qid = (int) $db->lastInsertId();
    bump($counts, 'quizzes');

    $enrolled = $db->fetchAll('SELECT user_id FROM course_enrollments WHERE course_id = ?', [$cid]);
    foreach ($enrolled as $en) {
        if (random_int(0, 100) > 25) {
            $started = randDate(12, 25);
            $db->query(
                "INSERT INTO quiz_attempts (quiz_id, user_id, started_at, finished_at, total_score, max_score, correct_answers, total_questions, status)
                 VALUES (?, ?, ?, ?, ?, 100, ?, 10, 'completed')",
                [$qid, $en['user_id'], $started, $started, random_int(55, 100), random_int(5, 10)]
            );
            bump($counts, 'quiz_attempts');
        }
    }
}

// --- Attendance (kiosk-style sessions) ---
foreach ($studentIds as $sid) {
    $sessions = random_int(4, 10);
    for ($s = 0; $s < $sessions; $s++) {
        $clockIn = randDate(3, 55);
        $clockOut = date('Y-m-d H:i:s', strtotime($clockIn) + random_int(45, 90) * 60);
        $db->query(
            "INSERT INTO attendance_sessions (user_id, floor_id, clock_in, clock_out, status, qr_scan_method)
             VALUES (?, ?, ?, ?, 'completed', 'lrn_card')",
            [$sid, $floorId, $clockIn, $clockOut]
        );
        bump($counts, 'attendance_sessions');
    }
}

// --- Points ---
$reasons = ['attendance_clock_in', 'assignment_on_time', 'quiz_correct_answer', 'participation', 'helping_others'];
foreach ($studentIds as $sid) {
    for ($p = 0; $p < random_int(3, 8); $p++) {
        $db->query(
            "INSERT INTO user_points (user_id, points, reason, created_at) VALUES (?, ?, ?, ?)",
            [$sid, random_int(5, 50), $reasons[array_rand($reasons)], randDate(2, 60)]
        );
        bump($counts, 'user_points');
    }
}

foreach (array_slice($studentIds, 0, 25) as $sid) {
    $db->query(
        "INSERT INTO point_awards (awarded_by, user_id, points, reason, award_type, created_at)
         VALUES (?, ?, ?, ?, 'participation', ?)",
        [$teacherIds[array_rand($teacherIds)], $sid, random_int(10, 40), 'Active participation in lab discussion', randDate(5, 40)]
    );
    bump($counts, 'point_awards');
}

// --- Announcements ---
foreach ($courseIds as $cid) {
    $teacherId = (int) $db->fetchOne('SELECT teacher_id FROM courses WHERE id = ?', [$cid]);
    $db->query(
        "INSERT INTO announcements (course_id, title, body, created_by, priority, publish_at, is_active, created_at)
         VALUES (?, ?, ?, ?, 'normal', ?, 1, ?)",
        [$cid, 'Lab schedule update', 'Please check the posted lab dates for this week. Bring your LRN card.', $teacherId, randDate(1, 20), randDate(10, 30)]
    );
    bump($counts, 'announcements');
}

// --- Notifications ---
foreach (array_slice($studentIds, 0, 30) as $sid) {
    $db->query(
        "INSERT INTO notifications (user_id, title, body, type, is_read, created_at)
         VALUES (?, ?, ?, 'assignment_due', ?, ?)",
        [$sid, 'Assignment graded', 'Your recent lab submission has been graded.', random_int(0, 1), randDate(1, 25)]
    );
    bump($counts, 'notifications');
}

// --- Lab PCs (sample) ---
$stations = $db->fetchAll('SELECT id, station_code FROM lab_stations WHERE floor_id = ? LIMIT 12', [$floorId]);
$statuses = ['locked', 'online', 'idle', 'offline'];
foreach ($stations as $i => $st) {
    $hostname = 'LAB-' . str_replace('-', '', $st['station_code']) . '-' . ($i + 1);
    $machineKey = bin2hex(random_bytes(16));
    $db->query(
        "INSERT INTO lab_pcs (hostname, floor_id, station_id, status, last_heartbeat, machine_key, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), last_heartbeat = VALUES(last_heartbeat)",
        [$hostname, $floorId, $st['id'], $statuses[$i % count($statuses)], randDate(0, 2), $machineKey, randDate(30, 90)]
    );
    bump($counts, 'lab_pcs');
}

$total = array_sum($counts);
echo "Inserted/updated rows by table:\n";
foreach ($counts as $table => $n) {
    echo "  {$table}: {$n}\n";
}
echo "\nTotal records touched: {$total}\n\n";
echo "Login samples (password: password123):\n";
echo "  Admin:   ADMIN001\n";
echo "  Teacher: TEACHER01\n";
echo "  Student: 20241001 (and 20241002 .. 20241040)\n";
echo "\nDone.\n";
