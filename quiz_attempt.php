<?php
// All Phase 1 features have been fully implemented.
/**
 * XPLabs - Quiz Attempt Page (Student)
 * Allows a student to start a quiz attempt, view questions, answer them, and finish.
 */
require_once __DIR__ . '/includes/bootstrap.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Database;
use XPLabs\Services\QuizService;

Auth::requireRole('student');

$userId = Auth::id();
$db = Database::getInstance();

$quizId = (int) ($_GET['quiz_id'] ?? 0);
if (!$quizId) {
    header('Location: my_quizzes.php');
    exit;
}

$quizService = new QuizService();
$attempt = $quizService->startAttempt($quizId, $userId);
if (!$attempt['success']) {
    $error = $attempt['message'] ?? 'Unable to start quiz attempt.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Attempt - XPLabs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #f1f5f9;
            --bg-card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --accent: #6366f1;
        }
        body { background: var(--bg-main); color: var(--text); font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .main-content { max-width: 800px; margin: 2rem auto; }
        .question-card { border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; background: var(--bg-card); }
        .timer { font-size: 1.25rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php else: ?>
            <h2 class="mb-3"><i class="bi bi-pencil-square me-2"></i><?= e($attempt['quiz']['title']) ?></h2>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="timer" id="timer">Time Remaining: <span id="timeLeft"></span></div>
                <button class="btn btn-success" id="finishBtn">Finish Quiz</button>
            </div>
            <form id="quizForm">
                <input type="hidden" name="attempt_id" value="<?= $attempt['attempt_id'] ?>">
                <?php foreach ($attempt['questions'] as $idx => $q): ?>
                    <div class="question-card" data-question-id="<?= $q['id'] ?>">
                        <div class="mb-2"><strong>Question <?= $idx + 1 ?>:</strong> <?= e($q['question_text']) ?></div>
                        <?php
                        $type = $q['type'];
                        $options = $q['options'] ? json_decode($q['options'], true) : [];
                        ?>
                        <?php if ($type === 'multiple_choice' && $options): ?>
                            <?php foreach ($options as $optIdx => $opt): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="answer_<?= $q['id'] ?>" id="q<?= $q['id'] ?>_opt<?= $optIdx ?>" value="<?= e($opt) ?>">
                                    <label class="form-check-label" for="q<?= $q['id'] ?>_opt<?= $optIdx ?>"><?= e($opt) ?></label>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ($type === 'true_false'): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answer_<?= $q['id'] ?>" id="q<?= $q['id'] ?>_true" value="true">
                                <label class="form-check-label" for="q<?= $q['id'] ?>_true">True</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answer_<?= $q['id'] ?>" id="q<?= $q['id'] ?>_false" value="false">
                                <label class="form-check-label" for="q<?= $q['id'] ?>_false">False</label>
                            </div>
                        <?php elseif (in_array($type, ['short_answer', 'code_completion', 'output_prediction'])): ?>
                            <textarea class="form-control" name="answer_<?= $q['id'] ?>" rows="3"></textarea>
                        <?php else: ?>
                            <input class="form-control" type="text" name="answer_<?= $q['id'] ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (empty($error)): ?>
        const timeLimit = <?= $attempt['time_limit'] ?? 0 ?>; // seconds
        const timerEl = document.getElementById('timeLeft');
        const finishBtn = document.getElementById('finishBtn');
        let remaining = timeLimit;
        function updateTimer() {
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            timerEl.textContent = mins + ':' + (secs < 10 ? '0' + secs : secs);
            if (remaining <= 0) {
                clearInterval(interval);
                finishQuiz();
            }
            remaining--;
        }
        const interval = setInterval(updateTimer, 1000);
        updateTimer();

        async function finishQuiz() {
            // Gather answers
            const form = document.getElementById('quizForm');
            const formData = new FormData(form);
            const attemptId = formData.get('attempt_id');
            const answers = [];
            for (let pair of formData.entries()) {
                const [key, value] = pair;
                if (key.startsWith('answer_')) {
                    const qId = key.split('_')[1];
                    answers.push({question_id: parseInt(qId), answer: value});
                }
            }
            // Submit each answer
            for (const ans of answers) {
                await fetch('api/quizzes/submit-answer.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({attempt_id: attemptId, question_id: ans.question_id, answer: ans.answer})
                });
            }
            // Finish attempt
            const finishResp = await fetch('api/quizzes/finish-attempt.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({attempt_id: attemptId})
            });
            const result = await finishResp.json();
            if (result.success) {
                window.location.href = 'quiz_review.php?attempt_id=' + encodeURIComponent(attemptId);
            } else {
                alert('Error finishing quiz: ' + (result.error || 'unknown'));
            }
        }

        finishBtn.addEventListener('click', function(){
            clearInterval(interval);
            finishQuiz();
        });
        <?php endif; ?>
    </script>
</body>
</html>
