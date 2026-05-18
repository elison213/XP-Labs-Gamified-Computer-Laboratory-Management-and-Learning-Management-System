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

    // Door kiosk settings (QR scanning tablet)
    'kiosk' => [
        // If non-empty, only these IPs can call /api/kiosk/* endpoints
        'allowed_ips' => [
            // e.g. '192.168.10.50',
        ],

        // Used as remote_commands.issued_by (must be a valid users.id).
        // If left 0, the API will fall back to the first active admin user.
        'issued_by_user_id' => 0,
    ],

    // Auto-deployment policy for lab agent rollout
    'pc_auto_deploy' => [
        'enabled' => true,
        // If non-empty, only IPs in these CIDR ranges are eligible.
        'allow_subnets' => [
            // e.g. '192.168.100.0/24'
        ],
        // Always excluded from auto deployment.
        'deny_subnets' => [
            // e.g. '192.168.200.0/24' // AP/infra VLAN
        ],
        // Optional tag blocks from discovery/manual labeling.
        'deny_tags' => ['access_point_vlan', 'infra', 'exclude_auto_deploy'],
        // If true and no allow_subnets match, deny unknown networks.
        'default_deny_unknown_networks' => false,
        // Safety controls for bulk operations.
        'max_bulk_jobs_per_request' => 25,
        'max_parallel_jobs' => 5,
    ],

    // Protocol debug trace settings
    'pc_protocol_debug' => [
        'enabled' => true,
        'retention_days' => 14,
    ],
];