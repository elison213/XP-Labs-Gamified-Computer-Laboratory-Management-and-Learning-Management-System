<?php
/**
 * XPLabs API - POST /api/submissions/submit
 * Submit an assignment.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../services/PointService.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\PointService;
// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::require();

$db = Database::getInstance();
$userId = Auth::id();

// Determine request type (JSON API or multipart/form-data)
$isMultipart = false;
if (!empty($_SERVER['CONTENT_TYPE'])) {
    $isMultipart = stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;
}

$assignmentId = null;
$content = null;
$fileUrl = null;

if ($isMultipart) {
    // Retrieve fields from POST data
    $assignmentId = isset($_POST['assignment_id']) ? (int) $_POST['assignment_id'] : null;
    $content = $_POST['content'] ?? null;

    // Handle file upload if a file was provided
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Validate upload error
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'File upload error.']);
            exit;
        }

        // Validate file size (max 5 MB)
        $maxSize = 5 * 1024 * 1024; // 5 MB
        if ($_FILES['file']['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['error' => 'File exceeds maximum allowed size of 5 MB.']);
            exit;
        }

        // Validate MIME type (allow common types)
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'text/plain',
            'application/zip',
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowedMimes, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported file type.']);
            exit;
        }

        // Ensure upload directory exists
        $uploadDir = __DIR__ . '/../../uploads/assignments/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create upload directory.']);
                exit;
            }
        }

        // Generate a unique filename with a safe extension derived from MIME
        $mimeToExt = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'text/plain' => 'txt',
            'application/zip' => 'zip',
        ];
        $safeExt = $mimeToExt[$mime] ?? null;
        if (!$safeExt) {
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported file type.']);
            exit;
        }
        $newFileName = uniqid('sub_', true) . '.' . $safeExt;
        $destination = $uploadDir . $newFileName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move uploaded file.']);
            exit;
        }
        // Store relative path for later retrieval
        $fileUrl = 'uploads/assignments/' . $newFileName;
    }
} else {
    // JSON request handling
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $assignmentId = $input['assignment_id'] ?? null;
    $content = $input['content'] ?? null;
    $fileUrl = $input['file_url'] ?? null;
}

if (empty($assignmentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'assignment_id is required']);
    exit;
}

try {
    // Fetch assignment details
    $assignment = $db->fetch("SELECT * FROM assignments WHERE id = ?", [$assignmentId]);
    if (!$assignment) {
        http_response_code(404);
        echo json_encode(['error' => 'Assignment not found']);
        exit;
    }
    if ($assignment['status'] === 'archived') {
        http_response_code(400);
        echo json_encode(['error' => 'Assignment is archived']);
        exit;
    }

    // Check for existing submission
    $existing = $db->fetch(
        "SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ?",
        [$assignmentId, $userId]
    );
    if ($existing && $existing['status'] === 'graded') {
        http_response_code(400);
        echo json_encode(['error' => 'Assignment already graded, cannot resubmit']);
        exit;
    }

    $submissionData = [
        'assignment_id' => $assignmentId,
        'user_id' => $userId,
        'content' => $content ?? '',
        'file_url' => $fileUrl,
        'status' => 'submitted',
        'submitted_at' => date('Y-m-d H:i:s'),
    ];
    // Preserve existing file URL if no new file was uploaded during an update
    if ($existing && $fileUrl === null) {
        $submissionData['file_url'] = $existing['file_url'];
    }

    // Late submission handling using status enum in submissions table.
    if ($assignment['due_date'] && strtotime('now') > strtotime($assignment['due_date'])) {
        $submissionData['status'] = 'late';
    }

    if ($existing) {
        // Update existing record (preserve previous file if not replaced)
        $db->update('submissions', $submissionData, 'id = ?', [$existing['id']]);
        $submissionId = $existing['id'];
    } else {
        $submissionId = $db->insert('submissions', $submissionData);
    }

    // Award points for submission
    $pointService = new PointService();
    $config = require __DIR__ . '/../../config/app.php';
    $points = $config['points']['assignment_submit'] ?? 5;
    $pointService->awardPoints($userId, $points, 'assignment_submit', 'submission', $submissionId);

    $submission = $db->fetch(
        "SELECT s.*, a.title as assignment_title, a.max_points
         FROM submissions s
         LEFT JOIN assignments a ON s.assignment_id = a.id
         WHERE s.id = ?",
        [$submissionId]
    );

    echo json_encode(['success' => true, 'submission' => $submission, 'points_earned' => $points]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}