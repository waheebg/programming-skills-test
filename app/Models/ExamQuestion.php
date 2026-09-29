<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * ExamQuestion model.
 *
 * Manages the exam_questions join table (manual question selection).
 * All queries use PDO prepared statements.
 */
class ExamQuestion
{
    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Return all manually selected questions for an exam, with question details.
     *
     * @param int $examId
     * @return array
     */
    public static function forExam(int $examId): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT eq.*,
                    q.question_text, q.question_type, q.difficulty, q.status AS question_status,
                    q.default_mark,
                    pl.name AS language_name,
                    c.name  AS category_name
             FROM exam_questions eq
             JOIN questions q               ON q.id  = eq.question_id
             LEFT JOIN programming_languages pl ON pl.id = q.programming_language_id
             LEFT JOIN categories c             ON c.id  = q.category_id
             WHERE eq.exam_id = :exam_id
             ORDER BY eq.sort_order ASC'
        );
        $stmt->execute(['exam_id' => $examId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Check whether a specific question is already attached to an exam.
     *
     * @param int $examId
     * @param int $questionId
     * @return bool
     */
    public static function exists(int $examId, int $questionId): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM exam_questions WHERE exam_id = :exam_id AND question_id = :question_id'
        );
        $stmt->execute(['exam_id' => $examId, 'question_id' => $questionId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Get the highest current sort_order for an exam's manual questions.
     *
     * @param int $examId
     * @return int
     */
    public static function maxSortOrder(int $examId): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) FROM exam_questions WHERE exam_id = :exam_id'
        );
        $stmt->execute(['exam_id' => $examId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Return all question IDs already attached to an exam.
     *
     * @param int $examId
     * @return array<int>
     */
    public static function questionIds(int $examId): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT question_id FROM exam_questions WHERE exam_id = :exam_id');
        $stmt->execute(['exam_id' => $examId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Add a single question to an exam (manual selection).
     * Silently skips if already present (duplicate prevention).
     *
     * @param int        $examId
     * @param int        $questionId
     * @param float|null $mark         Override mark; null = use question default
     * @return bool  true if inserted, false if duplicate
     * @throws RuntimeException
     */
    public static function add(int $examId, int $questionId, ?float $mark = null): bool
    {
        if (self::exists($examId, $questionId)) {
            return false;
        }

        $pdo       = Database::getConnection();
        $nextOrder = self::maxSortOrder($examId) + 1;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO exam_questions (exam_id, question_id, sort_order, mark)
                 VALUES (:exam_id, :question_id, :sort_order, :mark)'
            );
            $stmt->execute([
                'exam_id'     => $examId,
                'question_id' => $questionId,
                'sort_order'  => $nextOrder,
                'mark'        => $mark,
            ]);
            return true;
        } catch (\Exception $e) {
            error_log('ExamQuestion::add error: ' . $e->getMessage());
            throw new RuntimeException('Failed to add question to exam.');
        }
    }

    /**
     * Add multiple questions to an exam at once.
     * Skips duplicates automatically.
     *
     * @param int   $examId
     * @param array $questionIds
     * @return int  Number of questions actually inserted
     * @throws RuntimeException
     */
    public static function addMany(int $examId, array $questionIds): int
    {
        $inserted   = 0;
        $existingIds = self::questionIds($examId);
        $nextOrder  = self::maxSortOrder($examId) + 1;
        $pdo        = Database::getConnection();

        foreach ($questionIds as $questionId) {
            $qid = (int) $questionId;
            if (in_array($qid, $existingIds, true)) {
                continue;
            }
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO exam_questions (exam_id, question_id, sort_order, mark)
                     VALUES (:exam_id, :question_id, :sort_order, NULL)'
                );
                $stmt->execute([
                    'exam_id'     => $examId,
                    'question_id' => $qid,
                    'sort_order'  => $nextOrder,
                ]);
                $existingIds[] = $qid;
                $nextOrder++;
                $inserted++;
            } catch (\Exception $e) {
                error_log('ExamQuestion::addMany error: ' . $e->getMessage());
                throw new RuntimeException("Failed to add question {$qid} to exam.");
            }
        }

        return $inserted;
    }

    /**
     * Remove a single question from an exam.
     *
     * @param int $examId
     * @param int $questionId
     * @return bool
     */
    public static function remove(int $examId, int $questionId): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'DELETE FROM exam_questions WHERE exam_id = :exam_id AND question_id = :question_id'
        );
        $stmt->execute(['exam_id' => $examId, 'question_id' => $questionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Remove all questions from an exam.
     *
     * @param int $examId
     * @return void
     */
    public static function removeAll(int $examId): void
    {
        $pdo = Database::getConnection();
        $pdo->prepare('DELETE FROM exam_questions WHERE exam_id = :exam_id')
            ->execute(['exam_id' => $examId]);
    }
}
