<?php
/**
 * XPLabs API - GET /api/leaderboard
 * Get leaderboard with rankings.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PointService.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\PointService;

Auth::require();

$period = $_GET['period'] ?? 'all_time';
$courseId = !empty($_GET['course_id']) ? (int) $_GET['course_id'] : null;
$limit = min((int) ($_GET['limit'] ?? 50), 100);

$pointService = new PointService();
$leaderboard = $pointService->getLeaderboard($period, $courseId, $limit);

// Get current user's rank
$userId = Auth::id();
$userRank = $pointService->getUserRank($userId, $courseId);
$userBalance = $pointService->getTotalEarned($userId);

echo json_encode([
    'leaderboard' => $leaderboard,
    'user_rank' => [
        'rank' => $userRank,
        'total_points' => $userBalance,
    ],
    'period' => $period,
]);