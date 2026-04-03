<?php
/**
 * XPLabs - Quiz Service
 * Handles quiz creation, question management, attempts, and scoring.
 */

namespace XPLabs\Services;

use XPLabs\Lib\Database;

class QuizService
{
    private Database $db;
    private PointService $pointService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pointService = new PointService();
    }

    /**
     * Create a new quiz.
     */
    public function createQuiz(array $data): int
    {
        return $this->db->insert('quizzes', [
            'course_id' => $data['course_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'quiz_code' => $data['quiz_code'] ?? strtoupper(substr(md5(uniqid()), 0, 6)),
            'time_limit_seconds' => $data['time_limit_seconds'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'max_attempts' => $data['max_attempts'] ?? 1,
            'shuffle_questions' => $data['shuffle_questions'] ?? 0,
            'show_results_immediately' => $data['show_results_immediately'] ?? 1,
            'status' => 'draft',
            'created_by' => $data['created_by'],
        ]);
    }

    /**
     * Get a quiz by ID.
     */
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

    /**
     * Get quizzes for a course.
     */
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

    /**
     * Publish a quiz.
     */
    public function publishQuiz(int $quizId): bool
    {
        $quiz = $this->getQuiz($quizId);
        if (!$quiz) {
            return false;
        }

        // Check if quiz has questions
        $questionCount = (int) $this->db->fetchOne("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?", [$quizId]);
        if ($questionCount === 0) {
            throw new \Exception('Quiz must have at least one question');
        }

        return $this->db->update('quizzes', ['status' => 'published'], 'id = ?', [$quizId]) > 0;
    }

    /**
     * Add a question to a quiz.
     */
    public function addQuestion(int $quizId, array $data): int
    {
        return $this->db->insert('quiz_questions', [
            'quiz_id' => $quizId,
            'question_text' => $data['question_text'],
            'question_type' => $data['question_type'] ?? 'multiple_choice',
            'options' => isset($data['options']) ? json_encode($data['options']) : null,
            'correct_answer' => isset($data['correct_answer']) ? json_encode($data['correct_answer']) : null,
            'points' => $data['points'] ?? 10,
            'order_num' => $data['order_num'] ?? 0,
        ]);
    }

    /**
     * Add questions from question bank.
     */
    public function addQuestionsFromBank(int $quizId, array $questionIds): int
    {
        $count = 0;
        $orderNum = (int) $this->db->fetchOne("SELECT COALESCE(MAX(order_num), 0) + 1 FROM quiz_questions WHERE quiz_id = ?", [$quizId]);

        foreach ($questionIds as $qId) {
            $bankQuestion = $this->db->fetch("SELECT * FROM question_bank WHERE id = ?", [$qId]);
            if ($bankQuestion) {
                $this->db->insert('quiz_questions', [
                    'quiz_id' => $quizId,
                    'question_text' => $bankQuestion['question_text'],
                    'question_type' => $bankQuestion['type'],
                    'code_snippet' => $bankQuestion['code_snippet'],
                    'code_language' => $bankQuestion['code_language'],
                    'options' => $bankQuestion['options'],
                    'correct_answer' => $bankQuestion['correct_answer'],
                    'explanation' => $bankQuestion['explanation'],
                    'points' => $bankQuestion['points'],
                    'order_num' => $orderNum++,
                    'source_question_id' => $qId,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get quiz questions.
     */
    public function getQuestions(int $quizId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_num ASC",
            [$quizId]
        );
    }

    /**
     * Start a quiz attempt.
     */
    public function startAttempt(int $quizId, int $userId): array
    {
        $quiz = $this->getQuiz($quizId);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found'];
        }

        if ($quiz['status'] !== 'published') {
            return ['success' => false, 'message' => 'Quiz is not published'];
        }

        // Check max attempts
        $attempts = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?",
            [$quizId, $userId]
        );
        if ($quiz['max_attempts'] > 0 && $attempts >= $quiz['max_attempts']) {
            return ['success' => false, 'message' => 'Maximum attempts reached'];
        }

        $attemptId = $this->db->insert('quiz_attempts', [
            'quiz_id' => $quizId,
            'user_id' => $userId,
            'status' => 'in_progress',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $questions = $this->getQuestions($quizId);
        if ($quiz['shuffle_questions']) {
            shuffle($questions);
        }

        return [
            'success' => true,
            'attempt_id' => $attemptId,
            'quiz' => $quiz,
            'questions' => $questions,
            'time_limit' => $quiz['time_limit_seconds'],
        ];
    }

    /**
     * Submit an answer for a question.
     */
    public function submitAnswer(int $attemptId, int $questionId, $answer, ?bool $usedPowerup = null): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Invalid attempt'];
        }

        $question = $this->db->fetch("SELECT * FROM quiz_questions WHERE id = ?", [$questionId]);
        if (!$question) {
            return ['success' => false, 'message' => 'Question not found'];
        }

        $isCorrect = $this->checkAnswer($question, $answer);
        $points = $isCorrect ? $question['points'] : 0;

        // Apply powerup multiplier if used
        if ($usedPowerup) {
            $powerup = $this->db->fetch("SELECT * FROM powerups WHERE id = ?", [$usedPowerup]);
            if ($powerup && $powerup['config']) {
                $config = json_decode($powerup['config'], true);
                if ($config['effect'] === 'multiply_points') {
                    $points *= $config['factor'];
                }
            }
        }

        $this->db->insert('quiz_answers', [
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'answer' => is_array($answer) ? json_encode($answer) : $answer,
            'is_correct' => $isCorrect ? 1 : 0,
            'points_earned' => $points,
        ]);

        return ['success' => true, 'is_correct' => $isCorrect, 'points_earned' => $points];
    }

    /**
     * Finish a quiz attempt and calculate score.
     */
    public function finishAttempt(int $attemptId): array
    {
        $attempt = $this->db->fetch("SELECT * FROM quiz_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Invalid attempt'];
        }

        $answers = $this->db->fetchAll("SELECT * FROM quiz_answers WHERE attempt_id = ?", [$attemptId]);
        $totalPoints = array_sum(array_column($answers, 'points_earned'));
        $correctCount = count(array_filter($answers, fn($a) => $a['is_correct']));
        $totalQuestions = count($answers);
        $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

        $this->db->update('quiz_attempts', [
            'status' => 'completed',
            'total_points' => $totalPoints,
            'correct_answers' => $correctCount,
            'total_questions' => $totalQuestions,
            'score_percentage' => $percentage,
            'finished_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$attemptId]);

        // Award quiz points
        if ($percentage >= 50) {
            $this->pointService->awardPoints($attempt['user_id'], $totalPoints, 'quiz', 'quiz_attempt', $attemptId);
        }

        // Check for perfect score bonus
        if ($percentage == 100) {
            $config = require __DIR__ . '/../config/app.php';
            $bonus = $config['points']['quiz_perfect_bonus'] ?? 50;
            $this->pointService->awardPoints($attempt['user_id'], $bonus, 'quiz_perfect_bonus', 'quiz_attempt', $attemptId);
        }

        return [
            'success' => true,
            'score' => $totalPoints,
            'percentage' => $percentage,
            'correct' => $correctCount,
            'total' => $totalQuestions,
        ];
    }

    /**
     * Check if an answer is correct.
     */
    private function checkAnswer(array $question, $answer): bool
    {
        $correctAnswer = json_decode($question['correct_answer'], true) ?? $question['correct_answer'];

        switch ($question['question_type']) {
            case 'multiple_choice':
                return $answer == $correctAnswer;

            case 'true_false':
                return strtolower(trim($answer)) === strtolower(trim($correctAnswer));

            case 'short_answer':
                return strtolower(trim($answer)) === strtolower(trim($correctAnswer));

            case 'code_completion':
            case 'output_prediction':
                return trim($answer) === trim($correctAnswer);

            default:
                return false;
        }
    }

    /**
     * Get quiz results for an attempt.
     */
    public function getResults(int $attemptId): ?array
    {
        $attempt = $this->db->fetch(
            "SELECT qa.*, u.first_name, u.last_name
             FROM quiz_attempts qa
             JOIN users u ON qa.user_id = u.id
             WHERE qa.id = ?",
            [$attemptId]
        );

        if (!$attempt) {
            return null;
        }

        $answers = $this->db->fetchAll(
            "SELECT qa.*, qq.question_text, qq.question_type, qq.options, qq.correct_answer, qq.explanation, qq.points
             FROM quiz_answers qa
             JOIN quiz_questions qq ON qa.question_id = qq.id
             WHERE qa.attempt_id = ?",
            [$attemptId]
        );

        return [
            'attempt' => $attempt,
            'answers' => $answers,
        ];
    }

    /**
     * Get quiz leaderboard.
     */
    public function getLeaderboard(int $quizId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT qa.*, u.first_name, u.last_name, u.lrn
             FROM quiz_attempts qa
             JOIN users u ON qa.user_id = u.id
             WHERE qa.quiz_id = ? AND qa.status = 'completed'
             ORDER BY qa.score_percentage DESC, qa.finished_at ASC
             LIMIT $limit",
            [$quizId]
        );
    }

    /**
     * Search question bank.
     */
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

    /**
     * Create a question in the bank.
     */
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
}