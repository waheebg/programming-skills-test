<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Attempt model.
 *
 * Wraps CRUD operations for the `attempts` table.
 * All queries use PDO prepared statements.
 */
class Attempt
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED   = 'submitted';
    public const STATUS_EXPIRED     = 'expired';
    public const STATUS_CANCELLED   = 'cancelled';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS,
        self::STATUS_SUBMITTED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Find an attempt by ID, joining exam metadata and user info.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT a.*,
                    e.title AS exam_title,
                    e.description AS exam_description,
                    e.duration_minutes,
                    e.selection_mode,
                    e.pass_percentage,
                    e.max_attempts,
                    e.randomize_questions,
                    e.randomize_options,
                    e.show_result_immediately,
                    e.show_correct_answers,
                    e.status AS exam_status,
                    e.starts_at AS exam_starts_at,
                    e.ends_at AS exam_ends_at,
                    u.username,
                    u.first_name,
                    u.last_name,
                    u.email
             FROM attempts a
             JOIN exams e ON e.id = a.exam_id
             JOIN users u ON u.id = a.user_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find active in_progress attempt for a user and exam.
     *
     * @param int $examId
     * @param int $userId
     * @return array|null
     */
    public static function findActive(int $examId, int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT a.*,
                    e.title AS exam_title,
                    e.duration_minutes,
                    e.selection_mode,
                    e.pass_percentage,
                    e.max_attempts,
                    e.randomize_questions,
                    e.randomize_options,
                    e.status AS exam_status,
                    e.starts_at AS exam_starts_at,
                    e.ends_at AS exam_ends_at
             FROM attempts a
             JOIN exams e ON e.id = a.exam_id
             WHERE a.exam_id = :exam_id
               AND a.user_id = :user_id
               AND a.status = :status
             ORDER BY a.id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'exam_id' => $examId,
            'user_id' => $userId,
            'status'  => self::STATUS_IN_PROGRESS,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Count total attempts made by a user for a specific exam.
     *
     * @param int $examId
     * @param int $userId
     * @return int
     */
    public static function countForUserAndExam(int $examId, int $userId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM attempts WHERE exam_id = :exam_id AND user_id = :user_id'
        );
        $stmt->execute([
            'exam_id' => $examId,
            'user_id' => $userId,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Calculate next sequential attempt number for user and exam.
     *
     * @param int $examId
     * @param int $userId
     * @return int
     */
    public static function nextAttemptNumber(int $examId, int $userId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(attempt_number), 0) + 1
             FROM attempts
             WHERE exam_id = :exam_id AND user_id = :user_id'
        );
        $stmt->execute([
            'exam_id' => $examId,
            'user_id' => $userId,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get all attempts for a specific user.
     *
     * @param int $userId
     * @return array<int, array>
     */
    public static function forUser(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT a.*,
                    e.title AS exam_title,
                    e.duration_minutes
             FROM attempts a
             JOIN exams e ON e.id = a.exam_id
             WHERE a.user_id = :user_id
             ORDER BY a.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Create a new attempt record.
     *
     * @param array $data Keys: exam_id, user_id, attempt_number, status, started_at
     * @return int Inserted attempt ID
     * @throws RuntimeException
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO attempts (exam_id, user_id, attempt_number, status, started_at)
                 VALUES (:exam_id, :user_id, :attempt_number, :status, :started_at)'
            );
            $stmt->execute([
                'exam_id'        => (int) $data['exam_id'],
                'user_id'        => (int) $data['user_id'],
                'attempt_number' => (int) $data['attempt_number'],
                'status'         => $data['status'] ?? self::STATUS_IN_PROGRESS,
                'started_at'     => $data['started_at'] ?? date('Y-m-d H:i:s'),
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\PDOException $e) {
            error_log('Attempt::create error: ' . $e->getMessage());
            throw new RuntimeException('Failed to create attempt: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update attempt status and optional submitted_at timestamp.
     *
     * @param int $id
     * @param string $status
     * @param string|null $submittedAt
     * @return bool
     */
    public static function updateStatus(int $id, string $status, ?string $submittedAt = null): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid attempt status: {$status}");
        }

        $pdo = Database::getConnection();
        try {
            if ($submittedAt !== null) {
                $stmt = $pdo->prepare(
                    'UPDATE attempts
                     SET status = :status, submitted_at = :submitted_at
                     WHERE id = :id'
                );
                return $stmt->execute([
                    'status'       => $status,
                    'submitted_at' => $submittedAt,
                    'id'           => $id,
                ]);
            }

            $stmt = $pdo->prepare(
                'UPDATE attempts SET status = :status WHERE id = :id'
            );
            return $stmt->execute([
                'status' => $status,
                'id'     => $id,
            ]);
        } catch (\PDOException $e) {
            error_log('Attempt::updateStatus error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Submit an attempt manually.
     *
     * @param int $id
     * @param string|null $submittedAt
     * @return bool
     */
    public static function submit(int $id, ?string $submittedAt = null): bool
    {
        return self::updateStatus(
            $id,
            self::STATUS_SUBMITTED,
            $submittedAt ?? date('Y-m-d H:i:s')
        );
    }

    /**
     * Expire an attempt automatically when duration ends.
     *
     * @param int $id
     * @param string|null $submittedAt
     * @return bool
     */
    public static function expire(int $id, ?string $submittedAt = null): bool
    {
        return self::updateStatus(
            $id,
            self::STATUS_EXPIRED,
            $submittedAt ?? date('Y-m-d H:i:s')
        );
    }

    /**
     * Update attempt grading results (score, percentage, passed).
     *
     * @param int $id
     * @param float $score
     * @param float $percentage
     * @param bool $passed
     * @return bool
     */
    public static function updateGrading(int $id, float $score, float $percentage, bool $passed): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'UPDATE attempts
                 SET score = :score,
                     percentage = :percentage,
                     passed = :passed
                 WHERE id = :id'
            );
            return $stmt->execute([
                'score'      => $score,
                'percentage' => $percentage,
                'passed'     => $passed ? 1 : 0,
                'id'         => $id,
            ]);
        } catch (\PDOException $e) {
            error_log('Attempt::updateGrading error: ' . $e->getMessage());
            return false;
        }
    }
}
