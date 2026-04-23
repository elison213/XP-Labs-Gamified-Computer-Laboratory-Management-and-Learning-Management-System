<?php
/**
 * XPLabs - API endpoint map (documentation + optional router consumers).
 *
 * Only routes whose handler files exist are listed below.
 * Pretty paths are logical names; Apache typically serves the .php file directly (e.g. /api/courses/list.php).
 */
return [
    // Auth
    'POST /api/auth/login'        => 'api/auth/login.php',
    'POST /api/auth/logout'       => 'api/auth/logout.php',
    'GET  /api/auth/me'           => 'api/auth/me.php',

    // Attendance
    'POST /api/attendance/qr-checkin'  => 'api/attendance/qr-checkin.php',
    'POST /api/attendance/qr-checkout' => 'api/attendance/qr-checkout.php',
    'POST /api/attendance/start-session' => 'api/attendance/start-session.php',
    'GET  /api/attendance/sessions' => 'api/attendance/sessions.php',

    // Courses
    'GET  /api/courses/list'   => 'api/courses/list.php',
    'GET  /api/courses/detail' => 'api/courses/detail.php',
    'POST /api/courses/create' => 'api/courses/create.php',
    'POST /api/courses/enroll' => 'api/courses/enroll.php',
    'GET  /api/courses/students' => 'api/courses/students.php',

    // Lab
    'GET    /api/lab/stations'     => 'api/lab/stations.php',
    'PATCH  /api/lab/stations/{id}' => 'api/lab/stations.php',
    'POST   /api/lab/queue-command' => 'api/lab/queue-command.php',
    'GET    /api/lab/floors'       => 'api/lab/floors.php',
    'POST   /api/lab/floors'       => 'api/lab/floors.php',
    'GET    /api/lab/layout'       => 'api/lab/layout.php',
    'POST   /api/lab/layout'       => 'api/lab/layout.php',

    // Door Kiosk
    'POST /api/kiosk/unlock' => 'api/kiosk/unlock.php',

    // Quizzes
    'GET    /api/quizzes/list'         => 'api/quizzes/list.php',
    'GET    /api/quizzes/questions'    => 'api/quizzes/questions.php',
    'GET    /api/quizzes/results'      => 'api/quizzes/results.php',
    'GET    /api/quizzes/leaderboard'  => 'api/quizzes/leaderboard.php',
    'POST   /api/quizzes'              => 'api/quizzes/create.php',
    'POST   /api/quizzes/{id}/join'    => 'api/quizzes/join.php',
    'POST   /api/quizzes/submit-answer' => 'api/quizzes/submit-answer.php',
    'POST   /api/quizzes/finish-attempt' => 'api/quizzes/finish-attempt.php',

    // Assignments & submissions
    'GET  /api/assignments/list'   => 'api/assignments/list.php',
    'POST /api/assignments/create' => 'api/assignments/create.php',
    'POST /api/submissions/submit' => 'api/submissions/submit.php',

    // Leaderboard
    'GET    /api/leaderboard'     => 'api/leaderboard/list.php',

    // Notifications
    'GET    /api/notifications'   => 'api/notifications/list.php',

    // Users
    'GET    /api/users'           => 'api/users/list.php',
    'POST   /api/users/import'    => 'api/users/import.php',
    'POST   /api/users/import-preview' => 'api/users/import-preview.php',

    // Announcements
    'GET    /api/announcements'   => 'api/announcements/list.php',
    'POST   /api/announcements'   => 'api/announcements/create.php',

    // Incidents
    'GET    /api/incidents'       => 'api/incidents/list.php',
    'POST   /api/incidents/create' => 'api/incidents/create.php',

    // Inventory
    'GET    /api/inventory'       => 'api/inventory/list.php',
    'POST   /api/inventory/create' => 'api/inventory/create.php',

    // Analytics
    'GET    /api/analytics/attendance' => 'api/analytics/attendance.php',
    'GET    /api/analytics/quizzes'    => 'api/analytics/quizzes.php',
    'GET    /api/analytics/lab-usage'  => 'api/analytics/lab-usage.php',
    'GET    /api/analytics/feedback'   => 'api/analytics/feedback.php',

    // Lab PCs (machine auth)
    'POST /api/pc/register'  => 'api/pc/register.php',
    'GET  /api/pc/config'    => 'api/pc/config.php',
    'POST /api/pc/heartbeat' => 'api/pc/heartbeat.php',
    'GET  /api/pc/commands'  => 'api/pc/commands.php',
    'POST /api/pc/commands'  => 'api/pc/commands.php',

    // Session / access (machine or user auth per file)
    'GET  /api/session/validate'   => 'api/session/validate.php',
    'POST /api/session/pc-checkin' => 'api/session/pc-checkin.php',
    'POST /api/session/pc-checkout' => 'api/session/pc-checkout.php',
    'POST /api/session/force-logout' => 'api/session/force-logout.php',
    'POST /api/session/override-unlock' => 'api/session/override-unlock.php',
    'GET  /api/access/drive-maps'  => 'api/access/drive-maps.php',
    'GET  /api/access/folder-rules' => 'api/access/folder-rules.php',

    // Awards
    'POST /api/awards/create' => 'api/awards/create.php',
];
