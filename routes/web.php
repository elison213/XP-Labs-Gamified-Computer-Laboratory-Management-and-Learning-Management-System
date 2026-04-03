<?php
/**
 * XPLabs - Web Routes
 * Define all web routes here
 */

use XPLabs\Core\Router;
use XPLabs\Controllers\DashboardController;
use XPLabs\Controllers\AuthController;
use XPLabs\Controllers\LabController;
use XPLabs\Controllers\QuizController;
use XPLabs\Controllers\AssignmentController;
use XPLabs\Controllers\AttendanceController;
use XPLabs\Controllers\LeaderboardController;
use XPLabs\Controllers\AdminController;

/** @var Router $router */

// ====================
// Public Routes
// ====================
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// ====================
// Authenticated Routes
// ====================

// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/dashboard/student', [DashboardController::class, 'student']);
$router->get('/dashboard/teacher', [DashboardController::class, 'teacher']);
$router->get('/dashboard/admin', [DashboardController::class, 'admin']);

// Lab
$router->get('/lab/seatplan', [LabController::class, 'seatPlan']);
$router->get('/lab/monitoring', [LabController::class, 'monitoring']);
$router->get('/lab/floor/{floorId}', [LabController::class, 'floorDetail']);
$router->get('/lab/station/{stationId}', [LabController::class, 'stationDetail']);

// Quiz
$router->get('/quizzes', [QuizController::class, 'index']);
$router->get('/quizzes/{id}', [QuizController::class, 'show']);
$router->get('/quizzes/{id}/join', [QuizController::class, 'join']);
$router->get('/quizzes/manage', [QuizController::class, 'manage']);
$router->get('/quizzes/manage/create', [QuizController::class, 'create']);
$router->get('/quiz-results', [QuizController::class, 'results']);

// Assignments
$router->get('/assignments', [AssignmentController::class, 'index']);
$router->get('/assignments/{id}', [AssignmentController::class, 'show']);
$router->get('/assignments/manage', [AssignmentController::class, 'manage']);
$router->get('/submissions', [AssignmentController::class, 'submissions']);
$router->get('/submissions/{id}', [AssignmentController::class, 'submissionDetail']);

// Attendance
$router->get('/attendance/history', [AttendanceController::class, 'history']);
$router->get('/attendance/scan', [AttendanceController::class, 'scan']);
$router->post('/attendance/checkin', [AttendanceController::class, 'checkin']);
$router->post('/attendance/checkout', [AttendanceController::class, 'checkout']);

// Leaderboard
$router->get('/leaderboard', [LeaderboardController::class, 'index']);

// Profile
$router->get('/profile', [DashboardController::class, 'profile']);
$router->get('/profile/student/{id}', [DashboardController::class, 'studentProfile']);

// Announcements
$router->get('/announcements', [DashboardController::class, 'announcements']);

// ====================
// Admin Routes
// ====================
$router->get('/admin/users', [AdminController::class, 'users']);
$router->get('/admin/system', [AdminController::class, 'system']);
$router->get('/admin/logs', [AdminController::class, 'logs']);
$router->get('/admin/analytics', [AdminController::class, 'analytics']);
$router->get('/admin/award-points', [AdminController::class, 'awardPoints']);
$router->get('/admin/incidents', [AdminController::class, 'incidents']);
$router->get('/admin/inventory', [AdminController::class, 'inventory']);