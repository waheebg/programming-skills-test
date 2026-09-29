<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * AttemptQuestion model.
 *
 * Manages the attempt_questions snapshot table.
 * Each row captures a permanent snapshot of a question assigned to a specific attempt.
 * All queries use PDO prepared statements.
 */
class AttemptQuestion
{
    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Get all snapshotted questions for an attempt, ordered by sort_order ASC.
     *
     * @param int $attemptId
     * @return array<int, array>
     */
    public static function forAttempt(int $attemptId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT aq.*,
                    q.question_type,
                    q.difficulty,
                    q.programming_language_id,
                    q.category_id
             FROM attempt_questions aq
             JOIN questions q ON q.id = aq.question_id
             WHERE aq.attempt_id = :attempt_id
             ORDER BY aq.sort_order ASC'
        );
        $stmt->execute(['attempt_id' => $attemptId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Find a single snapshotted question by its ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT aq.*,
                    q.question_type,
                    q.difficulty
             FROM attempt_questions aq
             JOIN questions q ON q.id = aq.question_id
             WHERE aq.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find an attempt question by attempt ID and question ID.
     *
     * @param int $attemptId
     * @param int $questionId
     * @return array|null
     */
    public static function findByAttemptAndQuestion(int $attemptId, int $questionId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM attempt_questions
             WHERE attempt_id = :attempt_id AND question_id = :question_id
             LIMIT 1'
        );
        $stmt->execute([
            'attempt_id'  => $attemptId,
            'question_id' => $questionId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Count total questions assigned to an attempt.
     *
     * @param int $attemptId
     * @return int
     */
    public static function countForAttempt(int $attemptId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM attempt_questions WHERE attempt_id = :attempt_id'
        );
        $stmt->execute(['attempt_id' => $attemptId]);
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Insert a single attempt question snapshot.
     *
     * @param array $data Keys: attempt_id, question_id, sort_order, mark, question_text_snapshot
     * @return int Inserted ID
     * @throws RuntimeException
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO attempt_questions (attempt_id, question_id, sort_order, mark, question_text_snapshot)
                 VALUES (:attempt_id, :question_id, :sort_order, :mark, :question_text_snapshot)'
            );
            $stmt->execute([
                'attempt_id'             => (int) $data['attempt_id'],
                'question_id'            => (int) $data['question_id'],
                'sort_order'             => (int) $data['sort_order'],
                'mark'                   => (float) $data['mark'],
                'question_text_snapshot' => $data['question_text_snapshot'],
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log('AttemptQuestion::create error: ' . $e->getMessage());
            throw new RuntimeException('Failed to snapshot question for attempt: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Insert multiple question snapshots for an attempt.
     *
     * @param int $attemptId
     * @param array<int, array> $questions Array of items with keys: question_id, mark, question_text
     * @return int Count of questions inserted
     * @throws RuntimeException
     */
    public static function createMany(int $attemptId, array $questions): int
    {
        if (empty($questions)) {
            return 0;
        }

        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO attempt_questions (attempt_id, question_id, sort_order, mark, question_text_snapshot)
                 VALUES (:attempt_id, :question_id, :sort_order, :mark, :question_text_snapshot)'
            );

            $count = 0;
            foreach ($questions as $idx => $q) {
                $sortOrder = $idx + 1;
                $stmt->execute([
                    'attempt_id'             => $attemptId,
                    'question_id'            => (int) $q['question_id'],
                    'sort_order'             => $sortOrder,
                    'mark'                   => (float) $q['mark'],
                    'question_text_snapshot' => $q['question_text'],
                ]);
                $count++;
            }
            return $count;
        } catch (\PDOException $e) {
            error_log('AttemptQuestion::createMany error: ' . $e->getMessage());
            throw new RuntimeException('Failed to snapshot questions for attempt: ' . $e->getMessage(), 0, $e);
        }
    }
}
