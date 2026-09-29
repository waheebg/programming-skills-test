<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Result;
use RuntimeException;

/**
 * ExamGradingService
 *
 * Implements server-side grading, score calculation, evaluation,
 * results persistence, and result visibility enforcement (Phase 8).
 *
 * Supported question types:
 *  - single_choice
 *  - true_false
 */
class ExamGradingService
{
    // =========================================================================
    // Core Grading & Evaluation
    // =========================================================================

    /**
     * Evaluate an attempt on the server.
     * Evaluates submitted and expired attempts.
     * Re-evaluations update the existing result idempotently without duplication.
     *
     * @param int $attemptId
     * @param int|null $operatorUserId Optional ID of user/system triggering grading
     * @return array{
     *   'success': bool,
     *   'attempt_id'?: int,
     *   'result_id'?: int,
     *   'total_questions'?: int,
     *   'answered_questions'?: int,
     *   'unanswered_questions'?: int,
     *   'correct_answers'?: int,
     *   'wrong_answers'?: int,
     *   'total_marks'?: float,
     *   'score'?: float,
     *   'percentage'?: float,
     *   'passed'?: bool,
     *   'pass_percentage'?: float,
     *   'status'?: string,
     *   'errors': array<string>
     * }
     */
    public function evaluateAttempt(int $attemptId, ?int $operatorUserId = null): array
    {
        $attempt = Attempt::findById($attemptId);
        if (!$attempt) {
            return [
                'success' => false,
                'errors'  => ['Attempt not found.'],
            ];
        }

        // Cancelled attempts must not be evaluated
        if ($attempt['status'] === Attempt::STATUS_CANCELLED) {
            return [
                'success' => false,
                'errors'  => ['Cannot evaluate a cancelled exam attempt.'],
            ];
        }

        $exam = Exam::findById((int) $attempt['exam_id']);
        if (!$exam) {
            return [
                'success' => false,
                'errors'  => ['Associated exam not found.'],
            ];
        }

        // If in_progress, check if time has expired
        if ($attempt['status'] === Attempt::STATUS_IN_PROGRESS) {
            $startTs = strtotime($attempt['started_at']);
            $durationSec = (int) $exam['duration_minutes'] * 60;
            $now = time();

            if ($now >= ($startTs + $durationSec)) {
                $expiryTime = date('Y-m-d H:i:s', $startTs + $durationSec);
                Attempt::expire($attemptId, $expiryTime);
                $attempt = Attempt::findById($attemptId);
            } else {
                return [
                    'success' => false,
                    'errors'  => ['Cannot evaluate an attempt that is still in progress.'],
                ];
            }
        }

        // Ensure status is submitted or expired
        if ($attempt['status'] !== Attempt::STATUS_SUBMITTED && $attempt['status'] !== Attempt::STATUS_EXPIRED) {
            return [
                'success' => false,
                'errors'  => ["Cannot evaluate attempt with status '{$attempt['status']}'."],
            ];
        }

        // Load snapshotted questions for this attempt
        $snapQuestions = AttemptQuestion::forAttempt($attemptId);
        if (empty($snapQuestions)) {
            return [
                'success' => false,
                'errors'  => ['Cannot evaluate attempt: No question snapshots found.'],
            ];
        }

        // Load stored answers for this attempt
        $storedAnswers = AttemptAnswer::forAttempt($attemptId);

        // Preload options for all questions in the snapshot to minimize queries
        $questionIds = array_unique(array_map(fn($q) => (int) $q['question_id'], $snapQuestions));
        $optionsByQuestion = [];
        $optionsById = [];

        foreach ($questionIds as $qid) {
            $opts = QuestionOption::forQuestion($qid);
            $optionsByQuestion[$qid] = $opts;
            foreach ($opts as $opt) {
                $optionsById[(int) $opt['id']] = $opt;
            }
        }

        // Initialize score counters
        $totalQuestions    = count($snapQuestions);
        $answeredQuestions = 0;
        $correctAnswers    = 0;
        $wrongAnswers      = 0;
        $totalMarks        = 0.00;
        $score             = 0.00;

        $pdo = Database::getConnection();
        $inTx = false;

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $inTx = true;
            }

            foreach ($snapQuestions as $aq) {
                $aqId  = (int) $aq['id'];
                $qId   = (int) $aq['question_id'];
                $mark  = (float) $aq['mark'];
                $qType = $aq['question_type'] ?? 'single_choice';

                $totalMarks += $mark;

                $ans = $storedAnswers[$aqId] ?? null;
                $hasSelection = ($ans !== null && $ans['selected_option_id'] !== null);
                $hasTextAnswer = ($ans !== null && !empty(trim($ans['answer_text'] ?? '')));

                if ($hasSelection || $hasTextAnswer) {
                    $answeredQuestions++;

                    $isCorrect = false;

                    if ($hasSelection) {
                        $selectedId = (int) $ans['selected_option_id'];
                        $opt = $optionsById[$selectedId] ?? null;

                        // Ensure option belongs to the question
                        if ($opt && (int) $opt['question_id'] === $qId && !empty($opt['is_correct'])) {
                            $isCorrect = true;
                        }
                    } elseif ($hasTextAnswer) {
                        // Text match fallback (useful for True/False text submission)
                        $trimmedText = trim($ans['answer_text']);
                        $opts = $optionsByQuestion[$qId] ?? [];
                        foreach ($opts as $opt) {
                            if (strcasecmp(trim($opt['option_text']), $trimmedText) === 0) {
                                if (!empty($opt['is_correct'])) {
                                    $isCorrect = true;
                                }
                                break;
                            }
                        }
                    }

                    if ($isCorrect) {
                        $awardedMark = $mark;
                        $correctAnswers++;
                        $score += $mark;
                    } else {
                        $awardedMark = 0.00;
                        $wrongAnswers++;
                    }

                    // Update attempt_answers record
                    AttemptAnswer::updateGrading($aqId, $isCorrect, $awardedMark);
                } else {
                    // Unanswered question:
                    // If an answer record exists with null selection, set is_correct=0, awarded_mark=0
                    if ($ans !== null) {
                        AttemptAnswer::updateGrading($aqId, false, 0.00);
                    }
                    // Does not count as answered, correct, or wrong
                }
            }

            // Calculate percentage
            $percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0.00;
            $percentage = max(0.00, min(100.00, $percentage));

            // Determine pass/fail based on exam pass_percentage
            $passPercentage = (float) ($exam['pass_percentage'] ?? 50.00);
            $passed = ($percentage >= $passPercentage);

            // Update attempts table
            Attempt::updateGrading($attemptId, $score, $percentage, $passed);

            // Create or update results table (one result per attempt)
            $resultId = Result::createOrUpdate([
                'attempt_id'         => $attemptId,
                'total_questions'    => $totalQuestions,
                'answered_questions' => $answeredQuestions,
                'correct_answers'    => $correctAnswers,
                'wrong_answers'      => $wrongAnswers,
                'total_marks'        => $totalMarks,
                'score'              => $score,
                'percentage'         => $percentage,
                'passed'             => $passed,
                'calculated_at'      => date('Y-m-d H:i:s'),
            ]);

            if ($inTx) {
                $pdo->commit();
            }

            return [
                'success'              => true,
                'attempt_id'           => $attemptId,
                'result_id'            => $resultId,
                'total_questions'      => $totalQuestions,
                'answered_questions'   => $answeredQuestions,
                'unanswered_questions' => $totalQuestions - $answeredQuestions,
                'correct_answers'      => $correctAnswers,
                'wrong_answers'        => $wrongAnswers,
                'total_marks'          => $totalMarks,
                'score'                => $score,
                'percentage'           => $percentage,
                'passed'               => $passed,
                'pass_percentage'      => $passPercentage,
                'status'               => $attempt['status'],
                'errors'               => [],
            ];
        } catch (\Throwable $e) {
            if ($inTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('ExamGradingService::evaluateAttempt error: ' . $e->getMessage());
            return [
                'success' => false,
                'errors'  => ['Failed to evaluate exam attempt: ' . $e->getMessage()],
            ];
        }
    }

    // =========================================================================
    // Student Result Retrieval & Visibility Enforcement
    // =========================================================================

    /**
     * Get evaluated student result and question details respecting visibility rules.
     *
     * Visibility rules:
     *  - show_result_immediately: If false, students cannot view score/percentage/pass/answers.
     *  - show_correct_answers: If false, correct answers & explanations are hidden from students.
     *  - Staff (admin, teacher) can always view all results and correct answers.
     *
     * @param int $attemptId
     * @param int $userId Requesting user ID
     * @param bool $isStaff True if requesting user is teacher or admin
     * @return array{
     *   'success': bool,
     *   'can_view_result': bool,
     *   'can_view_correct_answers': bool,
     *   'attempt': ?array,
     *   'exam': ?array,
     *   'result': ?array,
     *   'questions': array<int, array>,
     *   'errors': array<string>
     * }
     */
    public function getStudentResult(int $attemptId, int $userId, bool $isStaff = false): array
    {
        $attempt = Attempt::findById($attemptId);
        if (!$attempt) {
            return [
                'success'                  => false,
                'can_view_result'          => false,
                'can_view_correct_answers' => false,
                'attempt'                  => null,
                'exam'                     => null,
                'result'                   => null,
                'questions'                => [],
                'errors'                   => ['Attempt not found.'],
            ];
        }

        // Ownership enforcement for students
        if (!$isStaff && (int) $attempt['user_id'] !== $userId) {
            return [
                'success'                  => false,
                'can_view_result'          => false,
                'can_view_correct_answers' => false,
                'attempt'                  => null,
                'exam'                     => null,
                'result'                   => null,
                'questions'                => [],
                'errors'                   => ['Access denied: You do not own this exam attempt.'],
            ];
        }

        $exam = Exam::findById((int) $attempt['exam_id']);
        if (!$exam) {
            return [
                'success'                  => false,
                'can_view_result'          => false,
                'can_view_correct_answers' => false,
                'attempt'                  => null,
                'exam'                     => null,
                'result'                   => null,
                'questions'                => [],
                'errors'                   => ['Exam not found.'],
            ];
        }

        // Auto-evaluate if submitted or expired but result not yet calculated
        $result = Result::findByAttemptId($attemptId);
        if (!$result && ($attempt['status'] === Attempt::STATUS_SUBMITTED || $attempt['status'] === Attempt::STATUS_EXPIRED)) {
            $evalRes = $this->evaluateAttempt($attemptId, $userId);
            if ($evalRes['success']) {
                $result = Result::findByAttemptId($attemptId);
                $attempt = Attempt::findById($attemptId);
            }
        }

        // If still in_progress, check for expiry
        if ($attempt['status'] === Attempt::STATUS_IN_PROGRESS) {
            $startTs = strtotime($attempt['started_at']);
            $durationSec = (int) $exam['duration_minutes'] * 60;
            if (time() >= ($startTs + $durationSec)) {
                $expiryTime = date('Y-m-d H:i:s', $startTs + $durationSec);
                Attempt::expire($attemptId, $expiryTime);
                $this->evaluateAttempt($attemptId, $userId);
                $result = Result::findByAttemptId($attemptId);
                $attempt = Attempt::findById($attemptId);
            } else {
                return [
                    'success'                  => false,
                    'can_view_result'          => false,
                    'can_view_correct_answers' => false,
                    'attempt'                  => $attempt,
                    'exam'                     => $exam,
                    'result'                   => null,
                    'questions'                => [],
                    'errors'                   => ['Attempt is still in progress. Results are not yet available.'],
                ];
            }
        }

        // Check visibility permissions
        $showImmediately = !empty($exam['show_result_immediately']);
        $showCorrect     = !empty($exam['show_correct_answers']);

        $canViewResult          = $isStaff || $showImmediately;
        $canViewCorrectAnswers  = $isStaff || ($showImmediately && $showCorrect);

        // Load questions snapshot
        $snapQuestions = AttemptQuestion::forAttempt($attemptId);
        $storedAnswers = AttemptAnswer::forAttempt($attemptId);

        $detailedQuestions = [];
        foreach ($snapQuestions as $aq) {
            $aqId  = (int) $aq['id'];
            $qId   = (int) $aq['question_id'];
            $ans   = $storedAnswers[$aqId] ?? null;

            // Fetch question definition for explanation (if allowed)
            $qDef = null;
            if ($canViewCorrectAnswers) {
                $qDef = Question::findById($qId);
            }

            // Fetch options
            $rawOptions = QuestionOption::forQuestion($qId);
            $processedOptions = [];
            $selectedOptionText = null;
            $correctOptionText  = null;

            foreach ($rawOptions as $opt) {
                $optId = (int) $opt['id'];
                $isSelected = ($ans !== null && (int) $ans['selected_option_id'] === $optId);

                if ($isSelected) {
                    $selectedOptionText = $opt['option_text'];
                }

                if (!empty($opt['is_correct'])) {
                    $correctOptionText = $opt['option_text'];
                }

                $optionItem = [
                    'id'          => $optId,
                    'option_text' => $opt['option_text'],
                    'sort_order'  => (int) $opt['sort_order'],
                    'is_selected' => $isSelected,
                ];

                // Only include is_correct if permitted
                if ($canViewCorrectAnswers) {
                    $optionItem['is_correct'] = !empty($opt['is_correct']);
                }

                $processedOptions[] = $optionItem;
            }

            // Determine question status: correct, wrong, or unanswered
            $isAnswered = ($ans !== null && ($ans['selected_option_id'] !== null || !empty(trim($ans['answer_text'] ?? ''))));
            $isCorrect  = ($isAnswered && !empty($ans['is_correct']));

            if (!$isAnswered) {
                $statusBadge = 'unanswered';
            } elseif ($isCorrect) {
                $statusBadge = 'correct';
            } else {
                $statusBadge = 'wrong';
            }

            $questionItem = [
                'attempt_question_id'    => $aqId,
                'question_id'            => $qId,
                'question_text'          => $aq['question_text_snapshot'],
                'question_type'          => $aq['question_type'],
                'mark'                   => (float) $aq['mark'],
                'awarded_mark'           => $canViewResult && $ans ? (float) ($ans['awarded_mark'] ?? 0.00) : 0.00,
                'is_answered'            => $isAnswered,
                'status'                 => $statusBadge,
                'selected_option_id'     => $ans ? $ans['selected_option_id'] : null,
                'selected_option_text'   => $selectedOptionText,
                'answer_text'            => $ans ? $ans['answer_text'] : null,
                'options'                => $processedOptions,
            ];

            if ($canViewResult) {
                $questionItem['is_correct'] = $isCorrect;
            }

            if ($canViewCorrectAnswers) {
                $questionItem['correct_option_text'] = $correctOptionText;
                $questionItem['explanation']         = $qDef['explanation'] ?? null;
            }

            $detailedQuestions[] = $questionItem;
        }

        return [
            'success'                  => true,
            'can_view_result'          => $canViewResult,
            'can_view_correct_answers' => $canViewCorrectAnswers,
            'attempt'                  => $attempt,
            'exam'                     => $exam,
            'result'                   => $result,
            'questions'                => $detailedQuestions,
            'errors'                   => [],
        ];
    }
}
