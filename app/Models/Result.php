<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Result model.
 *
 * Wraps CRUD and query operations for the `results` table.
 * All queries use PDO prepared statements.
 */
class Result
{
    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Find a result record by its primary key.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM results WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find a result record by attempt_id.
     *
     * @param int $attemptId
     * @return array|null
     */
    public static function findByAttemptId(int $attemptId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM results WHERE attempt_id = :attempt_id LIMIT 1');
        $stmt->execute(['attempt_id' => $attemptId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find result with joined attempt, exam, and user details by attempt_id.
     *
     * @param int $attemptId
     * @return array|null
     */
    public static function findWithDetailsByAttemptId(int $attemptId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT r.*,
                    a.exam_id,
                    a.user_id,
                    a.attempt_number,
                    a.status AS attempt_status,
                    a.started_at,
                    a.submitted_at,
                    e.title AS exam_title,
                    e.description AS exam_description,
                    e.duration_minutes,
                    e.pass_percentage,
                    e.show_result_immediately,
                    e.show_correct_answers,
                    u.username,
                    u.first_name,
                    u.last_name,
                    u.email
             FROM results r
             JOIN attempts a ON a.id = r.attempt_id
             JOIN exams e ON e.id = a.exam_id
             JOIN users u ON u.id = a.user_id
             WHERE r.attempt_id = :attempt_id
             LIMIT 1'
        );
        $stmt->execute(['attempt_id' => $attemptId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get all results for a specific user.
     *
     * @param int $userId
     * @return array<int, array>
     */
    public static function forUser(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT r.*,
                    a.exam_id,
                    a.attempt_number,
                    a.status AS attempt_status,
                    a.started_at,
                    a.submitted_at,
                    e.title AS exam_title,
                    e.show_result_immediately
             FROM results r
             JOIN attempts a ON a.id = r.attempt_id
             JOIN exams e ON e.id = a.exam_id
             WHERE a.user_id = :user_id
             ORDER BY r.calculated_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get all results for a specific exam.
     *
     * @param int $examId
     * @return array<int, array>
     */
    public static function forExam(int $examId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT r.*,
                    a.user_id,
                    a.attempt_number,
                    a.status AS attempt_status,
                    a.started_at,
                    a.submitted_at,
                    u.username,
                    u.first_name,
                    u.last_name
             FROM results r
             JOIN attempts a ON a.id = r.attempt_id
             JOIN users u ON u.id = a.user_id
             WHERE a.exam_id = :exam_id
             ORDER BY r.calculated_at DESC'
        );
        $stmt->execute(['exam_id' => $examId]);
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Create or update a result record (upsert).
     * Guarantees exactly one result per attempt.
     *
     * @param array $data Keys:
     *   - attempt_id
     *   - total_questions
     *   - answered_questions
     *   - correct_answers
     *   - wrong_answers
     *   - total_marks
     *   - score
     *   - percentage
     *   - passed
     *   - calculated_at (optional)
     * @return int Result ID
     * @throws RuntimeException
     */
    public static function createOrUpdate(array $data): int
    {
        $pdo = Database::getConnection();
        $calculatedAt = $data['calculated_at'] ?? date('Y-m-d H:i:s');

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO results (
                    attempt_id, total_questions, answered_questions, correct_answers,
                    wrong_answers, total_marks, score, percentage, passed, calculated_at
                 ) VALUES (
                    :attempt_id, :total_questions, :answered_questions, :correct_answers,
                    :wrong_answers, :total_marks, :score, :percentage, :passed, :calculated_at
                 )
                 ON DUPLICATE KEY UPDATE
                    total_questions    = VALUES(total_questions),
                    answered_questions = VALUES(answered_questions),
                    correct_answers    = VALUES(correct_answers),
                    wrong_answers      = VALUES(wrong_answers),
                    total_marks        = VALUES(total_marks),
                    score              = VALUES(score),
                    percentage         = VALUES(percentage),
                    passed             = VALUES(passed),
                    calculated_at      = VALUES(calculated_at)'
            );

            $stmt->execute([
                'attempt_id'         => (int) $data['attempt_id'],
                'total_questions'    => (int) $data['total_questions'],
                'answered_questions' => (int) $data['answered_questions'],
                'correct_answers'    => (int) $data['correct_answers'],
                'wrong_answers'      => (int) $data['wrong_answers'],
                'total_marks'        => (float) $data['total_marks'],
                'score'              => (float) $data['score'],
                'percentage'         => (float) $data['percentage'],
                'passed'             => !empty($data['passed']) ? 1 : 0,
                'calculated_at'      => $calculatedAt,
            ]);

            // If updated or inserted, find the ID
            $existing = self::findByAttemptId((int) $data['attempt_id']);
            return $existing ? (int) $existing['id'] : (int) $pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log('Result::createOrUpdate error: ' . $e->getMessage());
            throw new RuntimeException('Failed to save result record: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a result record by attempt_id.
     *
     * @param int $attemptId
     * @return bool
     */
    public static function deleteByAttemptId(int $attemptId): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare('DELETE FROM results WHERE attempt_id = :attempt_id');
            return $stmt->execute(['attempt_id' => $attemptId]);
        } catch (\PDOException $e) {
            error_log('Result::deleteByAttemptId error: ' . $e->getMessage());
            return false;
        }
    }
}
