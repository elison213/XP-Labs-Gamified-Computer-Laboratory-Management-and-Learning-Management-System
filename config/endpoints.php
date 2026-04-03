<?php
/**
 * XPLabs - API Endpoint Definitions
 * Maps URL patterns to handler files.
 */
return [
    // Auth
    'POST /api/auth/login'        => 'api/auth/login.php',
    'POST /api/auth/logout'       => 'api/auth/logout.php',
    'GET  /api/auth/me'           => 'api/auth/me.php',

    // Courses
    'GET    /api/courses'         => 'api/courses/list.php',
    'POST   /api/courses'         => 'api/courses/create.php',
    'GET    /api/courses/{id}'    => 'api/courses/detail.php',
    'POST   /api/courses/{id}/enroll' => 'api/courses/enroll.php',
    'GET    /api/courses/{id}/students' => 'api/courses/students.php',

    // Attendance
    'POST /api/attendance/qr-checkin'  => 'api/attendance/qr-checkin.php',
    'POST /api/attendance/qr-checkout' => 'api/attendance/qr-checkout.php',
    'GET  /api/attendance/sessions'    => 'api/attendance/sessions.php',

    // Lab
    'GET    /api/lab/stations'     => 'api/lab/stations.php',
    'PATCH  /api/lab/stations/{id}' => 'api/lab/stations.php',
    'GET    /api/lab/floors'       => 'api/lab/floors.php',
    'POST   /api/lab/floors'       => 'api/lab/floors.php',
    'GET    /api/lab/floors/{id}/layout' => 'api/lab/layout.php',
    'POST   /api/lab/floors/{id}/layout' => 'api/lab/layout.php',

    // Quizzes
    'GET    /api/quizzes'         => 'api/quizzes/list.php',
    'POST   /api/quizzes'         => 'api/quizzes/create.php',
    'GET    /api/quizzes/{id}/questions' => 'api/quizzes/questions.php',
    'POST   /api/quizzes/{id}/join' => 'api/quizzes/join.php',
    'POST   /api/quizzes/submit-answer' => 'api/quizzes/submit-answer.php',
    'GET    /api/quizzes/{id}/results' => 'api/quizzes/results.php',
    'GET    /api/quizzes/{id}/leaderboard' => 'api/quizzes/leaderboard.php',

    // Question Bank
    'GET    /api/question-bank'   => 'api/question-bank/list.php',
    'POST   /api/question-bank'   => 'api/question-bank/create.php',
    'GET    /api/question-bank/search' => 'api/question-bank/search.php',

    // Power-ups
    'GET    /api/powerups'        => 'api/powerups/list.php',
    'POST   /api/powerups/activate' => 'api/powerups/activate.php',

    // Rewards
    'GET    /api/rewards'         => 'api/rewards/catalog.php',
    'POST   /api/rewards/request' => 'api/rewards/request.php',
    'POST   /api/rewards/approve' => 'api/rewards/approve.php',

    // Leaderboard
    'GET    /api/leaderboard'     => 'api/leaderboard/list.php',

    // Notifications
    'GET    /api/notifications'   => 'api/notifications/list.php',

    // Users
    'GET    /api/users'           => 'api/users/list.php',
    'POST   /api/users/import'    => 'api/users/import.php',
    'POST   /api/users/import-preview' => 'api/users/import-preview.php',
];