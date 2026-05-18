<?php
/**
 * XPLabs - Quiz Service
 * Handles quiz creation, question management, attempts, and scoring.
 */

namespace XPLabs\Services;

use XPLabs\Lib\Database;
require_once __DIR__ . '/PointService.php';

class QuizService
{
    private Database $db;
    private PointService $pointService;
    private ?bool $hasAttemptIsPreview = null;
    private ?bool $hasQuizPowerupRulesTable = null;
    /** @var array<int, array<int, array{is_enabled:bool,max_uses_per_attempt:?int}>> */
    private array $quizPowerupRulesCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pointService = new PointService();
    }

    private function hasAttemptIsPreviewColumn(): bool
    {
        if ($this->hasAttemptIsPreview !== null) {
            return $this->hasAttemptIsPreview;
        }
        $this->hasAttemptIsPreview = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'quiz_attempts'
               AND column_name = 'is_preview'"
        ) > 0;

        return $this->hasAttemptIsPreview;
    }

    private function hasQuizPowerupRulesTable(): bool
    {
        if ($this->hasQuizPowerupRulesTable !== null) {
            return $this->hasQuizPowerupRulesTable;
        }
        $this->hasQuizPowerupRulesTable = $this->db->tableExists('quiz_powerup_rules');
        return $this->hasQuizPowerupRulesTable;
    }

    private function ensureQuizPowerupRulesSchema(): void
    {
        if (!$this->db->tableExists('quiz_powerup_rules')) {
            // Safe runtime guard for schema drift / not-yet-migrated environments.
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS quiz_powerup_rules (
                    quiz_id INT NOT NULL,
                    powerup_id INT NOT NULL,
                    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    max_uses_per_attempt INT NULL DEFAULT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (quiz_id, powerup_id),
                    KEY idx_qpr_quiz (quiz_id),
                    KEY idx_qpr_powerup (powerup_id),
                    CONSTRAINT fk_qpr_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
                    CONSTRAINT fk_qpr_powerup FOREIGN KEY (powerup_id) REFERENCES powerups(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } else {
            $hasMaxUsesPerAttempt = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'quiz_powerup_rules'
                   AND column_name = 'max_uses_per_attempt'"
            ) > 0;
            if (!$hasMaxUsesPerAttempt) {
                $this->db->query("ALTER TABLE quiz_powerup_rules ADD COLUMN max_uses_per_attempt INT NULL AFTER is_enabled");
            }
        }
        $this->hasQuizPowerupRulesTable = true;
    }

    /**
     * @return array{is_enabled:bool,max_uses_per_attempt:?int}
     */
    private function getQuizPowerupRule(int $quizId, int $powerupId): array
    {
        if (!$this->hasQuizPowerupRulesTable()) {
            return ['is_enabled' => true, 'max_uses_per_attempt' => null];
        }
        if (!isset($this->quizPowerupRulesCache[$quizId])) {
            $rows = $this->db->fetchAll(
                "SELECT powerup_id, is_enabled, max_uses_per_attempt FROM quiz_powerup_rules WHERE quiz_id = ?",
                [$quizId]
            );
            $map = [];
            foreach ($rows as $r) {
                $maxUses = isset($r['max_uses_per_attempt']) ? (int) $r['max_uses_per_attempt'] : null;
                if ($maxUses !== null && $maxUses <= 0) {
                    $maxUses = null;
                }
                $map[(int) $r['powerup_id']] = [
                    'is_enabled' => ((int) $r['is_enabled']) === 1,
                    'max_uses_per_attempt' => $maxUses,
                ];
            }
            $this->quizPowerupRulesCache[$quizId] = $map;
        }
        if (!array_key_exists($powerupId, $this->quizPowerupRulesCache[$quizId])) {
            return ['is_enabled' => true, 'max_uses_per_attempt' => null];
        }
        return $this->quizPowerupRulesCache[$quizId][$powerupId];
    }

    /**
     * Returns true if powerup is enabled for quiz.
     * Default is enabled when no rule row exists.
     */
    private function isPowerupEnabledForQuiz(int $quizId, int $powerupId): bool
    {
        $rule = $this->getQuizPowerupRule($quizId, $powerupId);
        return (bool) $rule['is_enabled'];
    }

    /**
     * Persist per-quiz disabled powerups. (Disabled is stored as is_enabled=0 rows.)
     */
    public function setQuizPowerupRules(int $quizId, array $rules): void
    {
        $this->ensureQuizPowerupRulesSchema();

        $this->db->delete('quiz_powerup_rules', 'quiz_id = ?', [$quizId]);
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $powerupId = (int) ($rule['powerup_id'] ?? 0);
            if ($powerupId <= 0) {
                continue;
            }
            $isEnabled = !empty($rule['is_enabled']) ? 1 : 0;
            $maxUses = isset($rule['max_uses_per_attempt']) ? (int) $rule['max_uses_per_attempt'] : null;
            if ($maxUses !== null && $maxUses <= 0) {
                $maxUses = null;
            }
            $this->db->insert('quiz_powerup_rules', [
                'quiz_id' => $quizId,
                'powerup_id' => $powerupId,
                'is_enabled' => $isEnabled,
                'max_uses_per_attempt' => $maxUses,
            ]);
        }

        // Reset cache for this quiz
        unset($this->quizPowerupRulesCache[$quizId]);
        $this->hasQuizPowerupRulesTable = true;
    }

    /**
     * Backward compatibility wrapper for existing callers.
     */
    public function setQuizDisabledPowerups(int $quizId, array $disabledPowerupIds): void
    {
        $rules = [];
        foreach ($disabledPowerupIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $rules[] = ['powerup_id' => $id, 'is_enabled' => 0, 'max_uses_per_attempt' => null];
        }
        $this->setQuizPowerupRules($quizId, $rules);
    }

    public function createQuiz(array $data): int
    {
        return $this->db->insert('quizzes', [
            'course_id' => $data['course_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'time_limit_per_q' => $data['time_limit_per_q'] ?? 30,
            'max_attempts' => isset($data['max_attempts']) ? max(1, (int) $data['max_attempts']) : 1,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'shuffle_questions' => $data['shuffle_questions'] ?? 0,
            'shuffle_answers' => $data['shuffle_answers'] ?? 1,
            'show_live_leaderboard' => $data['show_live_leaderboard'] ?? 1,
            'allow_powerups' => $data['allow_powerups'] ?? 1,
            'show_results_immediately' => $data['show_results_immediately'] ?? 1,
            'status' => 'draft',
            'created_by' => $data['created_by'],
        ]);
    }

    public function getQuiz(int $quizId): ?array
    {
        return $this->db->fetch(
            "SELECT q.*, c.name as course_name
             FROM quizzes q
             LEFT JOIN courses c ON q.course_id = c.id
             WHERE q.id = ?",
            [$quizId]
        );
    }

    public function getCourseQuizzes(int $courseId): array
    {
        return $this->db->fetchAll(
            "SELECT q.*, u.first_name, u.last_name
             FROM quizzes q
             LEFT JOIN users u ON q.created_by = u.id
             WHERE q.course_id = ?
             ORDER BY q.created_at DESC",
            [$courseId]
        );
    }

    public function publishQuiz(int $quizId): bool
    {
        $questionCount = (int) $this->db->fetchOne("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?", [$quizId]);
        if ($questionCount === 0) {
            throw new \Exception('Quiz must have at least one question');
        }

        return $this->db->update('quizzes', ['status' => 'active'], 'id = ?', [$quizId]) > 0;
    }

    public function addQuestion(int $quizId, array $data): int
    {
        return $this->db->insert('quiz_questions', [
            'quiz_id' => $quizId,
            'question_number' => $data['question_number'] ?? $this->getNextQuestionNumber($quizId),
            'question_text' => $data['question_text'],
            'type' => $data['type'] ?? 'multiple_choice',
            'code_snippet' => $data['code_snippet'] ?? null,
            'code_language' => $data['code_language'] ?? null,
            'options' => isset($data['options']) ? json_encode($data['options']) : null,
            'correct_answer' => isset($data['correct_answer']) ? json_encode($data['correct_answer']) : null,
            'points' => $data['points'] ?? 10,
            'time_limit' => $data['time_limit'] ?? null,
            'hint' => $data['hint'] ?? null,
            'explanation' => $data['explanation'] ?? null,
        ]);
    }

    public function addQuestionsFromBank(int $quizId, array $questionIds): int
    {
        $count = 0;
        $questionNumber = $this->getNextQuestionNumber($quizId);

        foreach ($questionIds as $qId) {
            $bankQuestion = $this->db->fetch("SELECT * FROM question_bank WHERE id = ?", [$qId]);
            if (!$bankQuestion) {
                continue;
            }

            $this->db->insert('quiz_questions', [
                'quiz_id' => $quizId,
                'question_number' => $questionNumber++,
                'question_text' => $bankQuestion['question_text'],
                'type' => $bankQuestion['type'],
                'code_snippet' => $bankQuestion['code_snippet'],
                'code_language' => $bankQuestion['code_language'],
                'options' => $bankQuestion['options'],
                'correct_answer' => $bankQuestion['correct_answer'],
                'hint' => $bankQuestion['hint'],
                'explanation' => $bankQuestion['explanation'],
                'points' => $bankQuestion['points'],
            ]);
            $count++;
        }

        return $count;
    }

    public function getQuestions(int $quizId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_number ASC",
            [$quizId]
        );
    }

    /**
     * Whether admin/teacher may preview (take) this quiz as themselves.
     */
    public function staffCanPreviewQuiz(int $quizId, int $userId, string $role): bool
    {
        if ($role === 'admin') {
            return $this->getQuiz($quizId) !== null;
        }
        if ($role !== 'teacher') {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT q.id FROM quizzes q
             INNER JOIN courses c ON c.id = q.course_id
             WHERE q.id = ? AND (q.created_by = ? OR c.teacher_id = ?)",
            [$quizId, $userId, $userId]
        );

        return $row !== null;
    }

    /**
     * Staff-only: start or resume an in-progress preview attempt (draft/scheduled/active; not archived).
     * Does not enforce max_attempts or schedule/closes windows.
     */
    public function startPreviewAttempt(int $quizId, int $userId, string $role): array
    {
        if (!in_array($role, ['admin', 'teacher'], true)) {
            return ['success' => false, 'message' => 'Preview is only available for staff'];
        }
        if (!$this->staffCanPreviewQuiz($quizId, $userId, $role)) {
            return ['success' => false, 'message' => 'You cannot preview this quiz'];
        }
        $quiz = $this->getQuiz($quizId);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found'];
        }
        $status = (string) ($quiz['status'] ?? '');
        if ($status === 'archived') {
            return ['success' => false, 'message' => 'Quiz is archived'];
        }

        $inProgress = $this->db->fetch(
            "SELECT id FROM quiz_attempts
             WHERE quiz_id = ? AND user_id = ? AND status = 'in_progress'"
            . ($this->hasAttemptIsPreviewColumn() ? " AND COALESCE(is_preview, 0) = 1" : '') .
            "
             ORDER BY started_at DESC LIMIT 1",
            [$quizId, $userId]
        );
        if ($inProgress) {
            $attemptId = (int) $inProgress['id'];
        } else {
            $payload = [
                'quiz_id' => $quizId,
                'user_id' => $userId,
                'status' => 'in_progress',
                'started_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->hasAttemptIsPreviewColumn()) {
                $payload['is_preview'] = 1;
            }
            $attemptId = $this->db->insert('quiz_attempts', $payload);
        }

        $questions = $this->getQuestions($quizId);
        if ((int) $quiz['shuffle_questions'] === 1) {
            shuffle($questions);
        }

        return [
            'success' => true,
            'attempt_id' => $attemptId,
            'quiz' => $quiz,
            'questions' => $questions,
            'time_limit' => (int) ($quiz['time_limit_per_q'] ?? 30) * max(1, count($questions)),
            'point_balance' => $this->pointService->getBalance($userId),
            'is_preview' => true,
        ];
    }

    /**
     * Staff may open quiz_review only for their own completed preview attempts on quizzes they may preview.
     */
    public function canStaffViewPreviewResults(int $attemptId, int $userId, string $role): bool
    {
        $attempt = $this->db->fetch(
            $this->hasAttemptIsPreviewColumn()
                ? "SELECT id, quiz_id, user_id, status, COALESCE(is_preview, 0) AS is_preview FROM quiz_attempts WHERE id = ?"
                : "SELECT id, quiz_id, user_id, status, 1 AS is_preview FROM quiz_attempts WHERE id = ?",
            [$attemptId]
        );
        if (!$attempt || (int) $attempt['is_preview'] !== 1 || (int) $attempt['user_id'] !== $userId) {
            return false;
        }
        if ($attempt['status'] === 'in_progress') {
            return false;
        }

        return $this->staffCanPreviewQuiz((int) $attempt['quiz_id'], $userId, $role);
    }

    public function startAttempt(int $quizId, int $userId): array
    {
        $quiz = $this->getQuiz($quizId);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found'];
        }
        if ($quiz['status'] !== 'active') {
            return ['success' => false, 'message' => 'Quiz is not active'];
        }
        if (!empty($quiz['scheduled_at']) && strtotime($quiz['scheduled_at']) > time()) {
            return ['success' => false, 'message' => 'Quiz has not started yet'];
        }
        if (!empty($quiz['closes_at']) && strtotime($quiz['closes_at']) < time()) {
            return ['success' => false, 'message' => 'Quiz is already closed'];
        }

        $maxAttempts = (int) ($quiz['max_attempts'] ?? 1);
        if ($maxAttempts > 0) {
            $finishedAttempts = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM quiz_attempts
                 WHERE quiz_id = ? AND user_id = ?"
                . ($this->hasAttemptIsPreviewColumn() ? " AND COALESCE(is_preview, 0) = 0" : '') .
                "
                   AND status IN ('completed', 'abandoned', 'submitted_late')",
                [$quizId, $userId]
            );
            if ($finishedAttempts >= $maxAttempts) {
                return ['success' => false, 'message' => 'Maximum quiz attempts reached'];
            }
        }

        $inProgress = $this->db->fetch(
            "SELECT id FROM quiz_attempts
             WHERE quiz_id = ? AND user_id = ? AND status = 'in_progress'"
            . ($this->hasAttemptIsPreviewColumn() ? " AND COALESCE(is_preview, 0) = 0" : '') .
            "
             ORDER BY started_at DESC LIMIT 1",
            [$quizId, $userId]
        );
        if ($inProgress) {
            $attemptId = (int) $inProgress['id'];
        } else {
            $payload = [
                'quiz_id' => $quizId,
                'user_id' => $userId,
                'status' => 'in_progress',
                'started_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->hasAttemptIsPreviewColumn()) {
                $payload['is_preview'] = 0;
            }
            $attemptId = $this->db->insert('quiz_attempts', $payload);
        }

        $questions = $this->getQuestions($quizId);
        if ((int) $quiz['shuffle_questions'] === 1) {
            shuffle($questions);
        }

        return [
            'success' => true,
            'attempt_id' => $attemptId,
            'quiz' => $quiz,
            'questions' => $questions,
            'time_limit' => (int) ($quiz['time_limit_per_q'] ?? 30) * max(1, count($questions)),
            'point_balance' => $this->pointService->getBalance($userId),
            'is_preview' => false,
        ];
    }

    public function submitAnswer(int $attemptId, int $questionId, $answer, ?int $usedPowerup = null, ?int $userId = null): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Invalid attempt'];
        }
        if ($userId !== null && (int) $attempt['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Attempt does not belong to user'];
        }

        $question = $this->db->fetch("SELECT * FROM quiz_questions WHERE id = ?", [$questionId]);
        if (!$question) {
            return ['success' => false, 'message' => 'Question not found'];
        }
        if ((int) $attempt['quiz_id'] !== (int) $question['quiz_id']) {
            return ['success' => false, 'message' => 'Question does not belong to this quiz'];
        }

        $activation = $this->getActivation($attemptId, $questionId, $usedPowerup);
        if ($activation && (($activation['effect'] ?? '') === 'quiz_exemption')) {
            $isCorrect = false;
            $points = (float) $question['points'];
        } else {
            $isCorrect = $this->checkAnswer($question, $answer);
            $points = $isCorrect ? (float) $question['points'] : 0;
        }

        if ($usedPowerup) {
            $powerup = $this->db->fetch("SELECT * FROM powerups WHERE id = ?", [$usedPowerup]);
            if ($powerup && !empty($powerup['config'])) {
                $config = json_decode($powerup['config'], true) ?? [];
                if (($config['effect'] ?? '') === 'multiply_points') {
                    $points *= (float) ($config['factor'] ?? 1);
                }
                if (($config['effect'] ?? '') === 'mc_reveal_answer' && ($config['auto_correct'] ?? false) === true) {
                    $isCorrect = true;
                    $points = (float) $question['points'];
                }
            }
        }

        $existing = $this->db->fetch(
            "SELECT id FROM quiz_answers WHERE attempt_id = ? AND question_id = ?",
            [$attemptId, $questionId]
        );

        $payload = [
            'user_id' => (int) $attempt['user_id'],
            'answer' => json_encode($answer),
            'is_correct' => $isCorrect ? 1 : 0,
            'points_earned' => $points,
            'powerup_used' => $usedPowerup ? (string) $usedPowerup : null,
            'answered_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->update('quiz_answers', $payload, 'id = ?', [(int) $existing['id']]);
        } else {
            $payload['attempt_id'] = $attemptId;
            $payload['question_id'] = $questionId;
            $this->db->insert('quiz_answers', $payload);
        }

        return ['success' => true, 'is_correct' => $isCorrect, 'points_earned' => $points];
    }

    public function finishAttempt(int $attemptId, ?int $userId = null): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Invalid attempt'];
        }
        if ($userId !== null && (int) $attempt['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Attempt does not belong to user'];
        }

        $answers = $this->db->fetchAll("SELECT * FROM quiz_answers WHERE attempt_id = ?", [$attemptId]);
        $totalPoints = (float) array_sum(array_column($answers, 'points_earned'));
        $correctCount = count(array_filter($answers, fn($a) => (int) $a['is_correct'] === 1));
        $totalQuestions = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?",
            [(int) $attempt['quiz_id']]
        );
        $maxScore = (float) $this->db->fetchOne(
            "SELECT COALESCE(SUM(points), 0) FROM quiz_questions WHERE quiz_id = ?",
            [(int) $attempt['quiz_id']]
        );
        $exemptions = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM quiz_powerup_activations qpa
             JOIN powerups p ON p.id = qpa.powerup_id
             WHERE qpa.attempt_id = ? AND p.code = 'quiz_exemption'",
            [$attemptId]
        );
        $effectiveTotalQuestions = max(1, $totalQuestions - $exemptions);
        $percentage = $effectiveTotalQuestions > 0 ? round(($correctCount / $effectiveTotalQuestions) * 100, 2) : 0.0;

        $this->db->update('quiz_attempts', [
            'status' => 'completed',
            'total_score' => $totalPoints,
            'max_score' => $maxScore,
            'correct_answers' => $correctCount,
            'total_questions' => $totalQuestions,
            'finished_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$attemptId]);

        $isPreview = (int) ($attempt['is_preview'] ?? 0) === 1;
        if (!$isPreview) {
            if ($percentage >= 50) {
                $this->pointService->awardPoints((int) $attempt['user_id'], (int) round($totalPoints), 'quiz', 'quiz_attempt', $attemptId);
            }

            if ($percentage == 100.0) {
                $config = require __DIR__ . '/../config/app.php';
                $bonus = (int) ($config['points']['quiz_perfect_bonus'] ?? 50);
                $this->pointService->awardPoints((int) $attempt['user_id'], $bonus, 'quiz_perfect_bonus', 'quiz_attempt', $attemptId);
            }
        }

        $quiz = $this->db->fetch("SELECT show_results_immediately FROM quizzes WHERE id = ?", [(int) $attempt['quiz_id']]);
        $canViewResults = (int) ($quiz['show_results_immediately'] ?? 1) === 1;

        return [
            'success' => true,
            'score' => $totalPoints,
            'percentage' => $percentage,
            'correct' => $correctCount,
            'total' => $totalQuestions,
            'can_view_results' => $canViewResults,
        ];
    }

    private function checkAnswer(array $question, $answer): bool
    {
        $decoded = json_decode((string) $question['correct_answer'], true);
        $correctAnswer = $decoded !== null ? $decoded : $question['correct_answer'];
        $normalizedAnswer = is_string($answer) ? trim($answer) : $answer;

        switch ($question['type']) {
            case 'multiple_choice':
                return $normalizedAnswer == $correctAnswer;
            case 'true_false':
            case 'short_answer':
                return strtolower((string) $normalizedAnswer) === strtolower(trim((string) $correctAnswer));
            case 'code_completion':
            case 'output_prediction':
                return trim((string) $normalizedAnswer) === trim((string) $correctAnswer);
            default:
                return false;
        }
    }

    public function getResults(int $attemptId): ?array
    {
        $attempt = $this->db->fetch(
            "SELECT qa.*, q.title, q.status as quiz_status, q.show_results_immediately, u.first_name, u.last_name
             FROM quiz_attempts qa
             JOIN quizzes q ON qa.quiz_id = q.id
             JOIN users u ON qa.user_id = u.id
             WHERE qa.id = ?",
            [$attemptId]
        );
        if (!$attempt) {
            return null;
        }

        $answers = $this->db->fetchAll(
            "SELECT qa.*, qq.question_text, qq.type as question_type, qq.options, qq.correct_answer, qq.explanation, qq.points
             FROM quiz_answers qa
             JOIN quiz_questions qq ON qa.question_id = qq.id
             WHERE qa.attempt_id = ?
             ORDER BY qq.question_number ASC",
            [$attemptId]
        );

        return [
            'attempt' => $attempt,
            'answers' => $answers,
            'score' => (float) $attempt['total_score'],
            'total' => (int) $attempt['total_questions'],
            'correct' => (int) $attempt['correct_answers'],
            'percentage' => (float) ((float) $attempt['max_score'] > 0 ? round(((float) $attempt['total_score'] / (float) $attempt['max_score']) * 100, 2) : 0),
        ];
    }

    public function getLeaderboard(int $quizId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT qa.*, u.first_name, u.last_name, u.lrn,
                    CASE WHEN qa.max_score > 0 THEN ROUND((qa.total_score / qa.max_score) * 100, 2) ELSE 0 END as percentage
             FROM quiz_attempts qa
             JOIN users u ON qa.user_id = u.id
             WHERE qa.quiz_id = ? AND qa.status = 'completed'"
            . ($this->hasAttemptIsPreviewColumn() ? " AND COALESCE(qa.is_preview, 0) = 0" : '') .
            "
             ORDER BY percentage DESC, qa.finished_at ASC
             LIMIT $limit",
            [$quizId]
        );
    }

    public function searchQuestionBank(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['subject'])) {
            $where[] = 'subject = ?';
            $params[] = $filters['subject'];
        }
        if (!empty($filters['topic'])) {
            $where[] = 'topic = ?';
            $params[] = $filters['topic'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['difficulty'])) {
            $where[] = 'difficulty = ?';
            $params[] = $filters['difficulty'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(question_text LIKE ? OR code_snippet LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM question_bank WHERE $whereClause", $params);
        $questions = $this->db->fetchAll(
            "SELECT * FROM question_bank WHERE $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
            $params
        );

        return [
            'data' => $questions,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    public function createQuestion(array $data, int $createdBy): int
    {
        return $this->db->insert('question_bank', [
            'type' => $data['type'],
            'question_text' => $data['question_text'],
            'code_snippet' => $data['code_snippet'] ?? null,
            'code_language' => $data['code_language'] ?? null,
            'options' => isset($data['options']) ? json_encode($data['options']) : null,
            'correct_answer' => isset($data['correct_answer']) ? json_encode($data['correct_answer']) : null,
            'explanation' => $data['explanation'] ?? null,
            'hint' => $data['hint'] ?? null,
            'difficulty' => $data['difficulty'] ?? 1,
            'points' => $data['points'] ?? 10,
            'subject' => $data['subject'] ?? null,
            'topic' => $data['topic'] ?? null,
            'bloom_level' => $data['bloom_level'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    private function getNextQuestionNumber(int $quizId): int
    {
        return (int) $this->db->fetchOne(
            "SELECT COALESCE(MAX(question_number), 0) + 1 FROM quiz_questions WHERE quiz_id = ?",
            [$quizId]
        );
    }

    public function listAvailablePowerupsForQuestion(int $attemptId, int $questionId, int $userId): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        $question = $this->db->fetch("SELECT * FROM quiz_questions WHERE id = ?", [$questionId]);
        if (!$attempt || !$question || (int) $attempt['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Invalid attempt or question'];
        }
        $quiz = $this->getQuiz((int) $attempt['quiz_id']);
        if (!$quiz || (int) ($quiz['allow_powerups'] ?? 1) !== 1) {
            return ['success' => true, 'powerups' => [], 'point_balance' => $this->pointService->getBalance($userId)];
        }
        $powerups = $this->db->fetchAll("SELECT * FROM powerups WHERE type = 'quiz' AND is_active = 1 ORDER BY point_cost ASC");
        $questionType = (string) ($question['type'] ?? '');
        $balance = $this->pointService->getBalance($userId);
        $available = [];
        foreach ($powerups as $p) {
            $rule = $this->getQuizPowerupRule((int) $quiz['id'], (int) $p['id']);
            if (!(bool) $rule['is_enabled']) {
                continue;
            }
            $cfg = json_decode((string) ($p['config'] ?? '{}'), true) ?? [];
            $allowedTypes = is_array($cfg['allowed_types'] ?? null) ? $cfg['allowed_types'] : [];
            if (!empty($allowedTypes) && !in_array($questionType, $allowedTypes, true)) {
                continue;
            }
            $already = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM quiz_powerup_activations WHERE attempt_id = ? AND question_id = ? AND powerup_id = ?",
                [$attemptId, $questionId, (int) $p['id']]
            );
            $canUse = $already === 0;
            $effectivePerAttemptLimit = max(0, (int) ($cfg['per_attempt_limit'] ?? 0));
            if (($rule['max_uses_per_attempt'] ?? null) !== null) {
                $effectivePerAttemptLimit = max(0, (int) $rule['max_uses_per_attempt']);
            }
            if ($effectivePerAttemptLimit > 0) {
                $attemptUseCount = (int) $this->db->fetchOne(
                    "SELECT COUNT(*) FROM quiz_powerup_activations WHERE attempt_id = ? AND powerup_id = ?",
                    [$attemptId, (int) $p['id']]
                );
                if ($attemptUseCount >= $effectivePerAttemptLimit) {
                    $canUse = false;
                }
            }
            $available[] = [
                'id' => (int) $p['id'],
                'code' => $p['code'],
                'name' => $p['name'],
                'description' => $p['description'],
                'icon' => $p['icon'],
                'point_cost' => (int) $p['point_cost'],
                'config' => $cfg,
                'can_afford' => $balance >= (int) $p['point_cost'],
                'can_use' => $canUse,
                'max_uses_per_attempt' => $effectivePerAttemptLimit > 0 ? $effectivePerAttemptLimit : null,
            ];
        }
        return ['success' => true, 'powerups' => $available, 'point_balance' => $balance];
    }

    public function purchaseOrRedeemPowerup(int $attemptId, int $powerupId, int $userId): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || (int) $attempt['user_id'] !== $userId || $attempt['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Invalid attempt'];
        }
        $quiz = $this->getQuiz((int) $attempt['quiz_id']);
        if (!$quiz || (int) ($quiz['allow_powerups'] ?? 1) !== 1) {
            return ['success' => false, 'message' => 'Powerups are disabled for this quiz'];
        }
        $powerup = $this->db->fetch("SELECT * FROM powerups WHERE id = ? AND type = 'quiz' AND is_active = 1", [$powerupId]);
        if (!$powerup) {
            return ['success' => false, 'message' => 'Powerup not found'];
        }
        $rule = $this->getQuizPowerupRule((int) $quiz['id'], $powerupId);
        if (!(bool) $rule['is_enabled']) {
            return ['success' => false, 'message' => 'This powerup is disabled for this quiz'];
        }
        if (($rule['max_uses_per_attempt'] ?? null) !== null) {
            $maxPerAttempt = max(1, (int) $rule['max_uses_per_attempt']);
            $usedCount = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM quiz_powerup_activations WHERE attempt_id = ? AND powerup_id = ?",
                [$attemptId, $powerupId]
            );
            $inventoryQty = (int) $this->db->fetchOne(
                "SELECT COALESCE(quantity, 0) FROM quiz_attempt_powerup_inventory WHERE attempt_id = ? AND user_id = ? AND powerup_id = ?",
                [$attemptId, $userId, $powerupId]
            );
            if (($usedCount + $inventoryQty) >= $maxPerAttempt) {
                return ['success' => false, 'message' => 'Per-attempt max stock for this powerup reached'];
            }
        }
        $cost = (int) $powerup['point_cost'];
        if (!$this->pointService->deductPoints($userId, $cost, 'quiz_powerup_purchase', 'quiz_attempt', $attemptId)) {
            return ['success' => false, 'message' => 'Insufficient points'];
        }
        $usageId = (int) $this->db->insert('powerup_usage', [
            'user_id' => $userId,
            'powerup_id' => $powerupId,
            'points_spent' => $cost,
            'context_type' => 'quiz_attempt',
            'context_id' => $attemptId,
            'status' => 'used',
        ]);

        $existing = $this->db->fetch(
            "SELECT id, quantity FROM quiz_attempt_powerup_inventory WHERE attempt_id = ? AND user_id = ? AND powerup_id = ?",
            [$attemptId, $userId, $powerupId]
        );
        if ($existing) {
            $this->db->update(
                'quiz_attempt_powerup_inventory',
                ['quantity' => (int) $existing['quantity'] + 1],
                'id = ?',
                [(int) $existing['id']]
            );
        } else {
            $this->db->insert('quiz_attempt_powerup_inventory', [
                'attempt_id' => $attemptId,
                'user_id' => $userId,
                'powerup_id' => $powerupId,
                'quantity' => 1,
            ]);
        }

        return ['success' => true, 'usage_id' => $usageId, 'point_balance' => $this->pointService->getBalance($userId)];
    }

    public function activatePowerup(int $attemptId, int $questionId, int $powerupId, int $userId): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        $question = $this->db->fetch("SELECT * FROM quiz_questions WHERE id = ?", [$questionId]);
        if (!$attempt || !$question || (int) $attempt['user_id'] !== $userId || $attempt['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Invalid attempt or question'];
        }
        if ((int) $attempt['quiz_id'] !== (int) $question['quiz_id']) {
            return ['success' => false, 'message' => 'Question does not belong to attempt quiz'];
        }
        $quiz = $this->getQuiz((int) $attempt['quiz_id']);
        if (!$quiz || (int) ($quiz['allow_powerups'] ?? 1) !== 1) {
            return ['success' => false, 'message' => 'Powerups are disabled for this quiz'];
        }
        $rule = $this->getQuizPowerupRule((int) $quiz['id'], $powerupId);
        if (!(bool) $rule['is_enabled']) {
            return ['success' => false, 'message' => 'This powerup is disabled for this quiz'];
        }
        $powerup = $this->db->fetch("SELECT * FROM powerups WHERE id = ? AND type = 'quiz' AND is_active = 1", [$powerupId]);
        if (!$powerup) {
            return ['success' => false, 'message' => 'Powerup not found'];
        }
        $cfg = json_decode((string) ($powerup['config'] ?? '{}'), true) ?? [];
        $allowedTypes = is_array($cfg['allowed_types'] ?? null) ? $cfg['allowed_types'] : [];
        $questionType = (string) ($question['type'] ?? '');
        if (!empty($allowedTypes) && !in_array($questionType, $allowedTypes, true)) {
            return ['success' => false, 'message' => 'Powerup cannot be used on this question type'];
        }

        $inventory = $this->db->fetch(
            "SELECT id, quantity FROM quiz_attempt_powerup_inventory WHERE attempt_id = ? AND user_id = ? AND powerup_id = ?",
            [$attemptId, $userId, $powerupId]
        );
        if (!$inventory || (int) $inventory['quantity'] <= 0) {
            return ['success' => false, 'message' => 'Powerup not purchased for this attempt'];
        }
        $already = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM quiz_powerup_activations WHERE attempt_id = ? AND question_id = ? AND powerup_id = ?",
            [$attemptId, $questionId, $powerupId]
        );
        if ($already > 0) {
            return ['success' => false, 'message' => 'Powerup already used on this question'];
        }
        $perQuestionLimit = max(1, (int) ($cfg['per_question_limit'] ?? 1));
        $questionUseCount = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM quiz_powerup_activations WHERE attempt_id = ? AND question_id = ? AND powerup_id = ?",
            [$attemptId, $questionId, $powerupId]
        );
        if ($questionUseCount >= $perQuestionLimit) {
            return ['success' => false, 'message' => 'Per-question powerup limit reached'];
        }
        $perAttemptLimit = max(0, (int) ($cfg['per_attempt_limit'] ?? 0));
        if (($rule['max_uses_per_attempt'] ?? null) !== null) {
            $perAttemptLimit = max(0, (int) $rule['max_uses_per_attempt']);
        }
        if ($perAttemptLimit > 0) {
            $attemptUseCount = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM quiz_powerup_activations WHERE attempt_id = ? AND powerup_id = ?",
                [$attemptId, $powerupId]
            );
            if ($attemptUseCount >= $perAttemptLimit) {
                return ['success' => false, 'message' => 'Per-attempt powerup limit reached'];
            }
        }
        $code = (string) ($powerup['code'] ?? '');
        if ($code === 'mc_reveal_answer') {
            $hasExemption = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM quiz_powerup_activations qpa
                 JOIN powerups p ON p.id = qpa.powerup_id
                 WHERE qpa.attempt_id = ? AND qpa.question_id = ? AND p.code = 'quiz_exemption'",
                [$attemptId, $questionId]
            );
            if ($hasExemption > 0) {
                return ['success' => false, 'message' => 'Cannot combine reveal answer with exemption on same question'];
            }
        }
        if ($code === 'quiz_exemption') {
            $hasReveal = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM quiz_powerup_activations qpa
                 JOIN powerups p ON p.id = qpa.powerup_id
                 WHERE qpa.attempt_id = ? AND qpa.question_id = ? AND p.code = 'mc_reveal_answer'",
                [$attemptId, $questionId]
            );
            if ($hasReveal > 0) {
                return ['success' => false, 'message' => 'Cannot combine exemption with reveal answer on same question'];
            }
        }

        $usageId = (int) $this->db->fetchOne(
            "SELECT id FROM powerup_usage WHERE user_id = ? AND powerup_id = ? AND context_type = 'quiz_attempt' AND context_id = ? ORDER BY id DESC LIMIT 1",
            [$userId, $powerupId, $attemptId]
        );
        $payload = $this->buildActivationPayload($cfg, $question);
        $this->db->insert('quiz_powerup_activations', [
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'powerup_id' => $powerupId,
            'usage_id' => $usageId ?: null,
            'effect_applied' => 1,
            'activation_payload' => json_encode($payload),
        ]);
        $this->db->update(
            'quiz_attempt_powerup_inventory',
            ['quantity' => max(0, (int) $inventory['quantity'] - 1)],
            'id = ?',
            [(int) $inventory['id']]
        );

        return ['success' => true, 'payload' => $payload];
    }

    public function getQuestionAssistPayload(int $attemptId, int $questionId, int $userId): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || (int) $attempt['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Invalid attempt'];
        }
        $activations = $this->db->fetchAll(
            "SELECT qpa.*, p.code, p.name FROM quiz_powerup_activations qpa
             JOIN powerups p ON p.id = qpa.powerup_id
             WHERE qpa.attempt_id = ? AND qpa.question_id = ?
             ORDER BY qpa.id ASC",
            [$attemptId, $questionId]
        );
        $out = [];
        foreach ($activations as $a) {
            $out[] = [
                'code' => $a['code'],
                'name' => $a['name'],
                'payload' => !empty($a['activation_payload']) ? (json_decode((string) $a['activation_payload'], true) ?? []) : [],
            ];
        }
        return ['success' => true, 'assist' => $out];
    }

    public function getAttemptPowerupState(int $attemptId, int $userId): array
    {
        $attempt = $this->db->fetch("SELECT id, user_id FROM quiz_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || (int) $attempt['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Invalid attempt'];
        }
        $inventory = $this->db->fetchAll(
            "SELECT i.*, p.code, p.name, p.icon, p.point_cost
             FROM quiz_attempt_powerup_inventory i
             JOIN powerups p ON p.id = i.powerup_id
             WHERE i.attempt_id = ? AND i.user_id = ?
             ORDER BY p.point_cost ASC",
            [$attemptId, $userId]
        );
        $activations = $this->db->fetchAll(
            "SELECT qpa.question_id, p.code, p.name, qpa.activated_at
             FROM quiz_powerup_activations qpa
             JOIN powerups p ON p.id = qpa.powerup_id
             WHERE qpa.attempt_id = ?
             ORDER BY qpa.activated_at DESC",
            [$attemptId]
        );
        return [
            'success' => true,
            'point_balance' => $this->pointService->getBalance($userId),
            'inventory' => $inventory,
            'activations' => $activations,
        ];
    }

    private function getActivation(int $attemptId, int $questionId, ?int $powerupId = null): ?array
    {
        $sql = "SELECT qpa.*, p.code, p.config FROM quiz_powerup_activations qpa
                JOIN powerups p ON p.id = qpa.powerup_id
                WHERE qpa.attempt_id = ? AND qpa.question_id = ?";
        $params = [$attemptId, $questionId];
        if ($powerupId !== null) {
            $sql .= " AND qpa.powerup_id = ?";
            $params[] = $powerupId;
        }
        $sql .= " ORDER BY qpa.id DESC LIMIT 1";
        $row = $this->db->fetch($sql, $params);
        if (!$row) {
            return null;
        }
        $cfg = json_decode((string) ($row['config'] ?? '{}'), true) ?? [];
        return ['effect' => $cfg['effect'] ?? '', 'row' => $row];
    }

    private function buildActivationPayload(array $cfg, array $question): array
    {
        $effect = (string) ($cfg['effect'] ?? '');
        $correctRaw = json_decode((string) ($question['correct_answer'] ?? ''), true);
        $correct = $correctRaw !== null ? $correctRaw : (string) ($question['correct_answer'] ?? '');
        if ($effect === 'mc_halve_choices') {
            $options = json_decode((string) ($question['options'] ?? '[]'), true) ?? [];
            $wrong = array_values(array_filter($options, static fn($o) => (string) $o !== (string) $correct));
            shuffle($wrong);
            $removeCount = max(1, (int) floor(count($wrong) / 2));
            $removed = array_slice($wrong, 0, $removeCount);
            return ['removed_options' => $removed];
        }
        if ($effect === 'mc_reveal_answer') {
            return ['correct_answer' => $correct];
        }
        if ($effect === 'fib_scramble_hint') {
            $word = trim((string) $correct);
            $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($chars) && count($chars) > 1) {
                shuffle($chars);
                return ['scrambled' => implode('', $chars)];
            }
            return ['scrambled' => $word];
        }
        if ($effect === 'fib_reveal_letters') {
            $word = trim((string) $correct);
            $lettersToShow = max(1, (int) ($cfg['letters'] ?? 2));
            $masked = str_repeat('_', max(0, strlen($word)));
            for ($i = 0; $i < min($lettersToShow, strlen($word)); $i++) {
                $masked[$i] = $word[$i];
            }
            return ['letter_hint' => $masked];
        }
        if ($effect === 'fib_dictionary_hint') {
            return ['dictionary_target' => trim((string) $correct)];
        }
        if ($effect === 'quiz_exemption') {
            return ['exempted' => true];
        }
        return [];
    }
}