<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionRule;
use App\Models\Question;
use App\Models\QuestionOption;
use RuntimeException;

/**
 * ExamAttemptService
 *
 * Orchestrates the Exam Taking Engine & Attempts (Phase 7):
 *  - Validate exam status, availability dates, and max_attempts
 *  - Create attempts with status: in_progress
 *  - Permanent question snapshots in attempt_questions (manual, random, hybrid)
 *  - Duplicate question prevention across all modes
 *  - Server-side timer calculation and auto-expiration
 *  - Answer saving and changing with option & question validation
 *  - Prevent unauthorized access and cross-user tampering
 *  - Manual submission and auto-submit/expire
 *
 * NOTE: Phase 7 does NOT calculate scores or evaluation (reserved for Phase 8).
 */
class ExamAttemptService
{
    // =========================================================================
    // Attempt Lifecycle: Creation & Validation
    // =========================================================================

    /**
     * Check if a user is allowed to start or resume an exam.
     *
     * @param int $examId
     * @param int $userId
     * @return array{'allowed': bool, 'resumed': bool, 'exam': ?array, 'active_attempt': ?array, 'errors': array<string>}
     */
    public function canStartExam(int $examId, int $userId): array
    {
        $exam = Exam::findById($examId);
        if (!$exam) {
            return [
                'allowed'        => false,
                'resumed'        => false,
                'exam'           => null,
                'active_attempt' => null,
                'errors'         => ['Exam not found.'],
            ];
        }

        // Validate status: must be published
        if ($exam['status'] !== 'published') {
            return [
                'allowed'        => false,
                'resumed'        => false,
                'exam'           => $exam,
                'active_attempt' => null,
                'errors'         => ["This exam is not available for taking (status: {$exam['status']})."],
            ];
        }

        // Validate availability dates
        $now = time();
        if (!empty($exam['starts_at'])) {
            $startsAtTs = strtotime($exam['starts_at']);
            if ($startsAtTs && $now < $startsAtTs) {
                return [
                    'allowed'        => false,
                    'resumed'        => false,
                    'exam'           => $exam,
                    'active_attempt' => null,
                    'errors'         => ["This exam is not available yet. It opens at {$exam['starts_at']}."],
                ];
            }
        }

        if (!empty($exam['ends_at'])) {
            $endsAtTs = strtotime($exam['ends_at']);
            if ($endsAtTs && $now > $endsAtTs) {
                return [
                    'allowed'        => false,
                    'resumed'        => false,
                    'exam'           => $exam,
                    'active_attempt' => null,
                    'errors'         => ["This exam is no longer available. It ended at {$exam['ends_at']}."],
                ];
            }
        }

        // Check for an existing active (in_progress) attempt
        $active = Attempt::findActive($examId, $userId);
        if ($active) {
            // Check if active attempt has expired according to server-side duration
            $startTs = strtotime($active['started_at']);
            $durationSec = (int) $exam['duration_minutes'] * 60;
            if ($now >= ($startTs + $durationSec)) {
                // Auto-expire it
                $expiryTime = date('Y-m-d H:i:s', $startTs + $durationSec);
                Attempt::expire((int) $active['id'], $expiryTime);
                try {
                    (new ExamGradingService())->evaluateAttempt((int) $active['id']);
                } catch (\Throwable $e) {
                    error_log('Error evaluating expired active attempt: ' . $e->getMessage());
                }
                $active = null;
            } else {
                // Valid in-progress attempt to resume
                return [
                    'allowed'        => true,
                    'resumed'        => true,
                    'exam'           => $exam,
                    'active_attempt' => $active,
                    'errors'         => [],
                ];
            }
        }

        // Enforce max_attempts
        if ($exam['max_attempts'] !== null && (int) $exam['max_attempts'] > 0) {
            $count = Attempt::countForUserAndExam($examId, $userId);
            if ($count >= (int) $exam['max_attempts']) {
                return [
                    'allowed'        => false,
                    'resumed'        => false,
                    'exam'           => $exam,
                    'active_attempt' => null,
                    'errors'         => ["Maximum number of attempts ({$exam['max_attempts']}) reached for this exam."],
                ];
            }
        }

        return [
            'allowed'        => true,
            'resumed'        => false,
            'exam'           => $exam,
            'active_attempt' => null,
            'errors'         => [],
        ];
    }

    /**
     * Start a new attempt for a published exam (or resume active attempt).
     * Creates permanent snapshot in attempt_questions.
     *
     * @param int $examId
     * @param int $userId
     * @return array{'success': bool, 'resumed': bool, 'attempt': ?array, 'errors': array<string>}
     */
    public function startAttempt(int $examId, int $userId): array
    {
        $check = $this->canStartExam($examId, $userId);
        if (!$check['allowed']) {
            return [
                'success' => false,
                'resumed' => false,
                'attempt' => null,
                'errors'  => $check['errors'],
            ];
        }

        // If already active and valid, resume without creating duplicate attempt
        if ($check['resumed'] && $check['active_attempt']) {
            return [
                'success' => true,
                'resumed' => true,
                'attempt' => $check['active_attempt'],
                'errors'  => [],
            ];
        }

        $exam = $check['exam'];

        // Build question snapshot
        try {
            $selectedQuestions = $this->buildQuestionSnapshot($exam);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'resumed' => false,
                'attempt' => null,
                'errors'  => [$e->getMessage()],
            ];
        }

        if (empty($selectedQuestions)) {
            return [
                'success' => false,
                'resumed' => false,
                'attempt' => null,
                'errors'  => ['Cannot start exam: No questions available for this exam.'],
            ];
        }

        // Begin transaction to create attempt + snapshot atomically
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $attemptNumber = Attempt::nextAttemptNumber($examId, $userId);
            $startedAt = date('Y-m-d H:i:s');

            $attemptId = Attempt::create([
                'exam_id'        => $examId,
                'user_id'        => $userId,
                'attempt_number' => $attemptNumber,
                'status'         => Attempt::STATUS_IN_PROGRESS,
                'started_at'     => $startedAt,
            ]);

            AttemptQuestion::createMany($attemptId, $selectedQuestions);

            $pdo->commit();

            $attempt = Attempt::findById($attemptId);

            return [
                'success' => true,
                'resumed' => false,
                'attempt' => $attempt,
                'errors'  => [],
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ExamAttemptService::startAttempt error: ' . $e->getMessage());
            return [
                'success' => false,
                'resumed' => false,
                'attempt' => null,
                'errors'  => ['Failed to start exam attempt: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Build the question snapshot list for an exam (manual, random, or hybrid).
     * Ensures NO duplicate questions across all selection modes.
     *
     * @param array $exam
     * @return array<int, array{'question_id': int, 'mark': float, 'question_text': string}>
     * @throws RuntimeException
     */
    public function buildQuestionSnapshot(array $exam): array
    {
        $examId = (int) $exam['id'];
        $mode   = $exam['selection_mode'] ?? 'manual';
        $selected = [];
        $usedIds  = [];

        // 1. Manual Questions
        if (in_array($mode, ['manual', 'hybrid'], true)) {
            $manual = ExamQuestion::forExam($examId);
            foreach ($manual as $mq) {
                if (($mq['question_status'] ?? '') === 'published') {
                    $qid = (int) $mq['question_id'];
                    if (!in_array($qid, $usedIds, true)) {
                        $usedIds[] = $qid;
                        $mark = $mq['mark'] !== null ? (float) $mq['mark'] : (float) $mq['default_mark'];
                        $selected[] = [
                            'question_id'   => $qid,
                            'mark'          => $mark,
                            'question_text' => $mq['question_text'],
                        ];
                    }
                }
            }
        }

        // 2. Selection Rules (Random / Hybrid)
        if (in_array($mode, ['random', 'hybrid'], true)) {
            $rules = ExamQuestionRule::forExam($examId);
            foreach ($rules as $rule) {
                $needed = (int) $rule['question_count'];
                $picked = ExamQuestionRule::pickRandom($rule, $needed, $usedIds);

                if (count($picked) < $needed) {
                    throw new RuntimeException(
                        "Not enough published questions available to fulfill rule #{$rule['rule_order']}."
                    );
                }

                $usedIds = array_merge($usedIds, $picked);

                foreach ($picked as $qid) {
                    $q = Question::findById($qid);
                    if (!$q || $q['status'] !== 'published') {
                        continue;
                    }
                    $mark = $rule['mark'] !== null ? (float) $rule['mark'] : (float) $q['default_mark'];
                    $selected[] = [
                        'question_id'   => $qid,
                        'mark'          => $mark,
                        'question_text' => $q['question_text'],
                    ];
                }
            }
        }

        // Randomize question order if configured
        if (!empty($exam['randomize_questions'])) {
            shuffle($selected);
        }

        return $selected;
    }

    // =========================================================================
    // Exam Interface & Timer
    // =========================================================================

    /**
     * Calculate server-side remaining seconds for an attempt.
     * Does NOT trust any client-provided timer.
     *
     * @param array $attempt
     * @param array $exam
     * @return int Remaining seconds (>= 0)
     */
    public function getRemainingSeconds(array $attempt, array $exam): int
    {
        $startTs = strtotime($attempt['started_at']);
        $totalSeconds = (int) $exam['duration_minutes'] * 60;
        $endTs = $startTs + $totalSeconds;
        $now = time();

        $remaining = $endTs - $now;
        return max(0, $remaining);
    }

    /**
     * Check if attempt time has ended. If so, automatically expire it.
     *
     * @param array $attempt
     * @param array $exam
     * @return bool True if expired
     */
    public function checkAndExpireIfTimeEnded(array $attempt, array $exam): bool
    {
        if ($attempt['status'] !== Attempt::STATUS_IN_PROGRESS) {
            return $attempt['status'] === Attempt::STATUS_EXPIRED;
        }

        $remaining = $this->getRemainingSeconds($attempt, $exam);
        if ($remaining <= 0) {
            $startTs = strtotime($attempt['started_at']);
            $expiryTime = date('Y-m-d H:i:s', $startTs + ((int) $exam['duration_minutes'] * 60));
            Attempt::expire((int) $attempt['id'], $expiryTime);
            try {
                (new ExamGradingService())->evaluateAttempt((int) $attempt['id']);
            } catch (\Throwable $e) {
                error_log('Error evaluating expired attempt: ' . $e->getMessage());
            }
            return true;
        }

        return false;
    }

    /**
     * Load attempt data for the student exam interface.
     * Does NOT expose correct answers.
     *
     * @param int $attemptId
     * @param int $userId
     * @return array
     */
    public function getAttemptForTaking(int $attemptId, int $userId): array
    {
        $attempt = Attempt::findById($attemptId);
        if (!$attempt) {
            return [
                'success' => false,
                'errors'  => ['Attempt not found.'],
            ];
        }

        // Ownership protection
        if ((int) $attempt['user_id'] !== $userId) {
            return [
                'success' => false,
                'errors'  => ['Access denied: You do not own this exam attempt.'],
            ];
        }

        $exam = [
            'id'                      => (int) $attempt['exam_id'],
            'title'                   => $attempt['exam_title'],
            'duration_minutes'        => (int) $attempt['duration_minutes'],
            'randomize_options'       => !empty($attempt['randomize_options']),
            'randomize_questions'     => !empty($attempt['randomize_questions']),
            'pass_percentage'         => $attempt['pass_percentage'],
            'show_result_immediately' => !empty($attempt['show_result_immediately']),
            'show_correct_answers'    => !empty($attempt['show_correct_answers']),
        ];

        // Check expiration
        if ($this->checkAndExpireIfTimeEnded($attempt, $exam)) {
            $attempt['status'] = Attempt::STATUS_EXPIRED;
            return [
                'success'           => false,
                'expired'           => true,
                'completed'         => true,
                'attempt'           => $attempt,
                'exam'              => $exam,
                'remaining_seconds' => 0,
                'errors'            => ['Exam duration has ended. This attempt has expired.'],
            ];
        }

        // Check if already submitted or cancelled
        if ($attempt['status'] !== Attempt::STATUS_IN_PROGRESS) {
            return [
                'success'           => false,
                'expired'           => false,
                'completed'         => true,
                'attempt'           => $attempt,
                'exam'              => $exam,
                'remaining_seconds' => 0,
                'errors'            => ["Attempt is already {$attempt['status']}."],
            ];
        }

        // Load snapshotted questions
        $questions = AttemptQuestion::forAttempt($attemptId);

        // Load options for each question without revealing correct answers!
        $pdo = Database::getConnection();
        $optStmt = $pdo->prepare(
            'SELECT id, question_id, option_text, sort_order
             FROM question_options
             WHERE question_id = :qid
             ORDER BY sort_order ASC, id ASC'
        );

        foreach ($questions as &$q) {
            $optStmt->execute(['qid' => (int) $q['question_id']]);
            $options = $optStmt->fetchAll() ?: [];

            // Randomize options deterministically per attempt if configured
            if ($exam['randomize_options']) {
                usort($options, function ($a, $b) use ($attemptId) {
                    return strcmp(
                        md5($attemptId . ':' . $a['id']),
                        md5($attemptId . ':' . $b['id'])
                    );
                });
            }

            $q['options'] = $options;
        }
        unset($q);

        // Load saved answers
        $answers = AttemptAnswer::forAttempt($attemptId);
        $remainingSeconds = $this->getRemainingSeconds($attempt, $exam);

        return [
            'success'           => true,
            'expired'           => false,
            'completed'         => false,
            'attempt'           => $attempt,
            'exam'              => $exam,
            'questions'         => $questions,
            'answers'           => $answers,
            'remaining_seconds' => $remainingSeconds,
            'errors'            => [],
        ];
    }

    // =========================================================================
    // Answer Saving
    // =========================================================================

    /**
     * Save or update an answer for an attempt question.
     *
     * Validations:
     *  - Attempt exists and belongs to current user
     *  - Attempt status is in_progress
     *  - Time has not expired (server-side check)
     *  - Question belongs to this attempt
     *  - Option belongs to this question
     *
     * @param int $attemptId
     * @param int $userId
     * @param int $attemptQuestionId
     * @param int|null $selectedOptionId
     * @param string|null $answerText
     * @return array{'success': bool, 'expired'?: bool, 'errors': array<string>}
     */
    public function saveAnswer(
        int $attemptId,
        int $userId,
        int $attemptQuestionId,
        ?int $selectedOptionId = null,
        ?string $answerText = null
    ): array {
        $attempt = Attempt::findById($attemptId);
        if (!$attempt) {
            return ['success' => false, 'errors' => ['Attempt not found.']];
        }

        // Ownership protection
        if ((int) $attempt['user_id'] !== $userId) {
            return ['success' => false, 'errors' => ['Access denied: You do not own this attempt.']];
        }

        // Check attempt status
        if ($attempt['status'] !== Attempt::STATUS_IN_PROGRESS) {
            return ['success' => false, 'errors' => ["Cannot save answer: Attempt is {$attempt['status']}."]];
        }

        // Server-side timer verification
        $exam = Exam::findById((int) $attempt['exam_id']);
        if (!$exam || $this->checkAndExpireIfTimeEnded($attempt, $exam)) {
            return [
                'success' => false,
                'expired' => true,
                'errors'  => ['Exam duration has ended. This attempt has expired.'],
            ];
        }

        // Validate that attemptQuestionId belongs to this attempt
        $aq = AttemptQuestion::findById($attemptQuestionId);
        if (!$aq || (int) $aq['attempt_id'] !== $attemptId) {
            return ['success' => false, 'errors' => ['Question does not belong to this exam attempt.']];
        }

        // Validate that selectedOptionId belongs to this question
        if ($selectedOptionId !== null) {
            $opt = QuestionOption::findById($selectedOptionId);
            if (!$opt || (int) $opt['question_id'] !== (int) $aq['question_id']) {
                return ['success' => false, 'errors' => ['Selected option does not belong to this question.']];
            }
        }

        try {
            AttemptAnswer::saveAnswer($attemptQuestionId, $selectedOptionId, $answerText);
            return ['success' => true, 'errors' => []];
        } catch (\Throwable $e) {
            error_log('ExamAttemptService::saveAnswer error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Failed to save answer.']];
        }
    }

    // =========================================================================
    // Submission
    // =========================================================================

    /**
     * Submit an attempt manually or via auto-expiration.
     * Prevents further answer modifications.
     *
     * @param int $attemptId
     * @param int $userId
     * @return array{'success': bool, 'status': string, 'submitted_at': ?string, 'errors': array<string>}
     */
    public function submitAttempt(int $attemptId, int $userId): array
    {
        $attempt = Attempt::findById($attemptId);
        if (!$attempt) {
            return ['success' => false, 'status' => '', 'submitted_at' => null, 'errors' => ['Attempt not found.']];
        }

        // Ownership protection
        if ((int) $attempt['user_id'] !== $userId) {
            return ['success' => false, 'status' => '', 'submitted_at' => null, 'errors' => ['Access denied: You do not own this attempt.']];
        }

        // If already submitted or expired, return current status
        if ($attempt['status'] !== Attempt::STATUS_IN_PROGRESS) {
            return [
                'success'      => true,
                'status'       => $attempt['status'],
                'submitted_at' => $attempt['submitted_at'],
                'errors'       => [],
            ];
        }

        $exam = Exam::findById((int) $attempt['exam_id']);
        $remaining = $exam ? $this->getRemainingSeconds($attempt, $exam) : 0;

        if ($remaining <= 0) {
            $status = Attempt::STATUS_EXPIRED;
            $startTs = strtotime($attempt['started_at']);
            $submittedAt = date('Y-m-d H:i:s', $startTs + ((int) ($exam['duration_minutes'] ?? 0) * 60));
            Attempt::expire($attemptId, $submittedAt);
        } else {
            $status = Attempt::STATUS_SUBMITTED;
            $submittedAt = date('Y-m-d H:i:s');
            Attempt::submit($attemptId, $submittedAt);
        }

        // Phase 8: Trigger server-side evaluation immediately upon submission / expiration
        try {
            (new ExamGradingService())->evaluateAttempt($attemptId, $userId);
        } catch (\Throwable $e) {
            error_log('Error evaluating attempt during submitAttempt: ' . $e->getMessage());
        }

        return [
            'success'      => true,
            'status'       => $status,
            'submitted_at' => $submittedAt,
            'errors'       => [],
        ];
    }

    /**
     * Get attempt summary for display upon completion.
     *
     * @param int $attemptId
     * @param int $userId
     * @return array|null
     */
    public function getAttemptSummary(int $attemptId, int $userId): ?array
    {
        $attempt = Attempt::findById($attemptId);
        if (!$attempt || (int) $attempt['user_id'] !== $userId) {
            return null;
        }

        $totalQuestions = AttemptQuestion::countForAttempt($attemptId);
        $answeredQuestions = AttemptAnswer::countAnsweredForAttempt($attemptId);

        return [
            'attempt'            => $attempt,
            'total_questions'    => $totalQuestions,
            'answered_questions' => $answeredQuestions,
        ];
    }
}
