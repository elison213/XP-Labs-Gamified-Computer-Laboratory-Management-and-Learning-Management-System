<?php
/**
 * XPLabs - Quiz Review Page (Student)
 * Allows a student to view the detailed results of a completed quiz attempt.
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Services\QuizService;

// Only students can view their own quiz review
Auth::requireRole('student');

$userId = Auth::id();

// Get the attempt ID from the query string
$attemptId = (int) ($_GET['attempt_id'] ?? 0);
if (!$attemptId) {
    // No attempt ID provided – redirect back to the quizzes list
    header('Location: my_quizzes.php');
    exit;
}

$quizService = new QuizService();
$result = $quizService->getResults($attemptId);

// Ensure the attempt belongs to the current user and is in a reviewable state.
$attemptStatus = $result['attempt']['status'] ?? '';
if (!$result || $result['attempt']['user_id'] != $userId || $attemptStatus === 'in_progress') {
    $error = 'Unable to retrieve quiz review. The attempt may not exist or is not reviewable yet.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Review - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #f1f5f9;
            --bg-card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
            --accent: #6366f1;
            --green: #22c55e;
            --red: #ef4444;
        }
        body { background: var(--bg-main); color: var(--text); font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .main-content { max-width: 900px; margin: 2rem auto; }
        .card { border: 1px solid var(--border); border-radius: 8px; }
        .question-card { border: 1px solid var(--border); border-radius: 6px; padding: 1rem; margin-bottom: 1rem; background: var(--bg-card); }
        .correct { color: var(--green); font-weight: 600; }
        .incorrect { color: var(--red); font-weight: 600; }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
            <a href="my_quizzes.php" class="btn btn-secondary mt-3">Back to My Quizzes</a>
        <?php else: ?>
            <h2 class="mb-4"><i class="bi bi-journal-check me-2"></i>Quiz Review</h2>
            <div class="card p-4 mb-4">
                <h4 class="mb-2"><?= e($result['attempt']['title'] ?? 'Quiz') ?></h4>
                <p class="mb-1"><strong>Score:</strong> <?= $result['score'] ?> points (<?= $result['percentage'] ?>%)</p>
                <p class="mb-1"><strong>Correct Answers:</strong> <?= $result['correct'] ?> / <?= $result['total'] ?></p>
                <p class="mb-1"><strong>Finished At:</strong> <?= e(date('M j, Y H:i', strtotime($result['attempt']['finished_at']))) ?></p>
            </div>
            <?php foreach ($result['answers'] as $ans): ?>
                <div class="question-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div><strong>Question:</strong> <?= e($ans['question_text']) ?></div>
                        <span class="badge bg-<?= $ans['is_correct'] ? 'success' : 'danger' ?>">
                            <?= $ans['is_correct'] ? 'Correct' : 'Wrong' ?>
                        </span>
                    </div>
                    <?php
                    $type = $ans['question_type'];
                    $options = $ans['options'] ? json_decode($ans['options'], true) : [];
                    $correct = $ans['correct_answer'] ? json_decode($ans['correct_answer'], true) : $ans['correct_answer'];
                    $decodedStudentAns = json_decode((string) $ans['answer'], true);
                    $studentAns = (json_last_error() === JSON_ERROR_NONE) ? $decodedStudentAns : $ans['answer'];
                    ?>
                    <?php if ($type === 'multiple_choice' && $options): ?>
                        <ul class="list-group mb-2">
                            <?php foreach ($options as $opt): ?>
                                <li class="list-group-item <?= $opt == $correct ? 'list-group-item-success' : '' ?>">
                                    <?= e($opt) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="mb-1"><strong>Your Answer:</strong> <span class="<?= $ans['is_correct'] ? 'correct' : 'incorrect' ?>"><?= e(is_array($studentAns) ? implode(', ', $studentAns) : $studentAns) ?></span></p>
                    <p class="mb-1"><strong>Correct Answer:</strong> <?= e(is_array($correct) ? implode(', ', $correct) : $correct) ?></p>
                    <p class="mb-1"><strong>Points Earned:</strong> <?= $ans['points_earned'] ?></p>
                    <?php if (!empty($ans['explanation'])): ?>
                        <p class="text-muted"><strong>Explanation:</strong> <?= e($ans['explanation']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <a href="my_quizzes.php" class="btn btn-primary mt-3">Back to My Quizzes</a>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
