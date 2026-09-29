<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * AttemptAnswer model.
 *
 * Manages student answer records in `attempt_answers`.
 * Each answer is tied to a specific `attempt_question_id`.
 * Supports selecting options and changing answers before submission.
 * All queries use PDO prepared statements.
 */
class AttemptAnswer
{
    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Find answer record by attempt_question_id.
     *
     * @param int $attemptQuestionId
     * @return array|null
     */
    public static function findByAttemptQuestionId(int $attemptQuestionId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM attempt_answers WHERE attempt_question_id = :aq_id LIMIT 1'
        );
        $stmt->execute(['aq_id' => $attemptQuestionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get all answers for a given attempt, keyed by attempt_question_id.
     *
     * @param int $attemptId
     * @return array<int, array> [attempt_question_id => answer_row]
     */
    public static function forAttempt(int $attemptId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT aa.*, aq.question_id, aq.sort_order
             FROM attempt_answers aa
             JOIN attempt_questions aq ON aq.id = aa.attempt_question_id
             WHERE aq.attempt_id = :attempt_id'
        );
        $stmt->execute(['attempt_id' => $attemptId]);
        $rows = $stmt->fetchAll() ?: [];

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['attempt_question_id']] = $r;
        }
        return $map;
    }

    /**
     * Count how many questions have been answered in an attempt.
     *
     * @param int $attemptId
     * @return int
     */
    public static function countAnsweredForAttempt(int $attemptId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM attempt_answers aa
             JOIN attempt_questions aq ON aq.id = aa.attempt_question_id
             WHERE aq.attempt_id = :attempt_id
               AND (aa.selected_option_id IS NOT NULL OR (aa.answer_text IS NOT NULL AND aa.answer_text != ""))'
        );
        $stmt->execute(['attempt_id' => $attemptId]);
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Save or update an answer for an attempt question (upsert).
     *
     * @param int $attemptQuestionId
     * @param int|null $selectedOptionId
     * @param string|null $answerText
     * @return bool
     * @throws RuntimeException
     */
    public static function saveAnswer(
        int $attemptQuestionId,
        ?int $selectedOptionId = null,
        ?string $answerText = null
    ): bool {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO attempt_answers (attempt_question_id, selected_option_id, answer_text, answered_at)
                 VALUES (:attempt_question_id, :selected_option_id, :answer_text, CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE
                     selected_option_id = VALUES(selected_option_id),
                     answer_text = VALUES(answer_text),
                     answered_at = CURRENT_TIMESTAMP'
            );
            return $stmt->execute([
                'attempt_question_id' => $attemptQuestionId,
                'selected_option_id'  => $selectedOptionId,
                'answer_text'         => $answerText,
            ]);
        } catch (\PDOException $e) {
            error_log('AttemptAnswer::saveAnswer error: ' . $e->getMessage());
            throw new RuntimeException('Failed to save answer: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Clear / delete an answer for an attempt question.
     *
     * @param int $attemptQuestionId
     * @return bool
     */
    public static function deleteForAttemptQuestion(int $attemptQuestionId): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare('DELETE FROM attempt_answers WHERE attempt_question_id = :aq_id');
            return $stmt->execute(['aq_id' => $attemptQuestionId]);
        } catch (\PDOException $e) {
            error_log('AttemptAnswer::deleteForAttemptQuestion error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update grading fields (is_correct, awarded_mark) for an attempt question answer.
     *
     * @param int $attemptQuestionId
     * @param bool $isCorrect
     * @param float $awardedMark
     * @return bool
     */
    public static function updateGrading(int $attemptQuestionId, bool $isCorrect, float $awardedMark): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'UPDATE attempt_answers
                 SET is_correct = :is_correct,
                     awarded_mark = :awarded_mark
                 WHERE attempt_question_id = :aq_id'
            );
            return $stmt->execute([
                'is_correct'   => $isCorrect ? 1 : 0,
                'awarded_mark' => $awardedMark,
                'aq_id'        => $attemptQuestionId,
            ]);
        } catch (\PDOException $e) {
            error_log('AttemptAnswer::updateGrading error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Record or upsert an answer with its evaluation result.
     *
     * @param int $attemptQuestionId
     * @param int|null $selectedOptionId
     * @param string|null $answerText
     * @param bool $isCorrect
     * @param float $awardedMark
     * @return bool
     */
    public static function recordGradedAnswer(
        int $attemptQuestionId,
        ?int $selectedOptionId,
        ?string $answerText,
        bool $isCorrect,
        float $awardedMark
    ): bool {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO attempt_answers (
                    attempt_question_id, selected_option_id, answer_text,
                    is_correct, awarded_mark, answered_at
                 ) VALUES (
                    :attempt_question_id, :selected_option_id, :answer_text,
                    :is_correct, :awarded_mark, CURRENT_TIMESTAMP
                 )
                 ON DUPLICATE KEY UPDATE
                    selected_option_id = VALUES(selected_option_id),
                    answer_text        = VALUES(answer_text),
                    is_correct         = VALUES(is_correct),
                    awarded_mark       = VALUES(awarded_mark)'
            );
            return $stmt->execute([
                'attempt_question_id' => $attemptQuestionId,
                'selected_option_id'  => $selectedOptionId,
                'answer_text'         => $answerText,
                'is_correct'          => $isCorrect ? 1 : 0,
                'awarded_mark'        => $awardedMark,
            ]);
        } catch (\PDOException $e) {
            error_log('AttemptAnswer::recordGradedAnswer error: ' . $e->getMessage());
            return false;
        }
    }
}
