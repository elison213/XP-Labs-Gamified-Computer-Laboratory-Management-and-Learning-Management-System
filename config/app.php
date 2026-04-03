<?php
/**
 * XPLabs - Application Configuration
 */
return [
    'name' => 'XPLabs',
    'version' => '1.0.0',
    'timezone' => 'Asia/Manila',
    'debug' => false,

    // Session settings
    'session' => [
        'lifetime' => 3600, // 1 hour
        'name' => 'XPLABS_SESSION',
        'cookie_secure' => false, // Set true for HTTPS
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ],

    // File upload settings
    'uploads' => [
        'max_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['csv', 'xlsx', 'xls', 'pdf', 'docx', 'pptx', 'html', 'css', 'js'],
        'path' => __DIR__ . '/../uploads',
    ],

    // Pagination
    'per_page' => 25,

    // Point rules
    'points' => [
        'attendance_clock_in' => 5,
        'attendance_on_time_bonus' => 2, // Before 8 AM
        'attendance_full_session' => 3,  // 45+ minutes
        'attendance_perfect_week' => 25, // 5 consecutive days
        'assignment_on_time' => 10,
        'assignment_early_bonus' => 10,  // 2+ days before due
        'quiz_correct_answer' => 10,
        'quiz_perfect_bonus' => 50,
        'quiz_top_1' => 30,
        'quiz_top_2' => 20,
        'quiz_top_3' => 10,
        'peer_help' => 5,
    ],
];