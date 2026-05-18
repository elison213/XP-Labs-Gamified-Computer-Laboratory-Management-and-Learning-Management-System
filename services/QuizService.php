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

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pointService = new PointService();
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
                "SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? AND status IN ('completed', 'abandoned', 'submitted_late')",
                [$quizId, $userId]
            );
            if ($finishedAttempts >= $maxAttempts) {
                return ['success' => false, 'message' => 'Maximum quiz attempts reached'];
            }
        }

        $inProgress = $this->db->fetch(
            "SELECT id FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? AND status = 'in_progress' ORDER BY started_at DESC LIMIT 1",
            [$quizId, $userId]
        );
        $attemptId = $inProgress
            ? (int) $inProgress['id']
            : $this->db->insert('quiz_attempts', [
                'quiz_id' => $quizId,
                'user_id' => $userId,
                'status' => 'in_progress',
                'started_at' => date('Y-m-d H:i:s'),
            ]);

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
        ];
    }

    public function submitAnswer(int $attemptId, int $questionId, $answer, ?int $usedPowerup = null): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Invalid attempt'];
        }

        $question = $this->db->fetch("SELECT * FROM quiz_questions WHERE id = ?", [$questionId]);
        if (!$question) {
            return ['success' => false, 'message' => 'Question not found'];
        }
        if ((int) $attempt['quiz_id'] !== (int) $question['quiz_id']) {
            return ['success' => false, 'message' => 'Question does not belong to this quiz'];
        }

        $isCorrect = $this->checkAnswer($question, $answer);
        $points = $isCorrect ? (float) $question['points'] : 0;

        if ($usedPowerup) {
            $powerup = $this->db->fetch("SELECT * FROM powerups WHERE id = ?", [$usedPowerup]);
            if ($powerup && !empty($powerup['config'])) {
                $config = json_decode($powerup['config'], true) ?? [];
                if (($config['effect'] ?? '') === 'multiply_points') {
                    $points *= (float) ($config['factor'] ?? 1);
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

    public function finishAttempt(int $attemptId): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Invalid attempt'];
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
        $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $this->db->update('quiz_attempts', [
            'status' => 'completed',
            'total_score' => $totalPoints,
            'max_score' => $maxScore,
            'correct_answers' => $correctCount,
            'total_questions' => $totalQuestions,
            'finished_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$attemptId]);

        if ($percentage >= 50) {
            $this->pointService->awardPoints((int) $attempt['user_id'], (int) round($totalPoints), 'quiz', 'quiz_attempt', $attemptId);
        }

        if ($percentage == 100.0) {
            $config = require __DIR__ . '/../config/app.php';
            $bonus = (int) ($config['points']['quiz_perfect_bonus'] ?? 50);
            $this->pointService->awardPoints((int) $attempt['user_id'], $bonus, 'quiz_perfect_bonus', 'quiz_attempt', $attemptId);
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
             WHERE qa.quiz_id = ? AND qa.status = 'completed'
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
}