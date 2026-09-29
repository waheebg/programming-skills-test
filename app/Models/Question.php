<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Question model.
 *
 * Wraps CRUD for `questions` and read operations for related
 * `question_options`. All queries use PDO prepared statements.
 *
 * Supported question_type values: single_choice, true_false
 * Supported difficulty values:    easy, medium, hard
 * Supported status values:        draft, published, archived
 */
class Question
{
    public const TYPES       = ['single_choice', 'true_false'];
    public const DIFFICULTIES = ['easy', 'medium', 'hard'];
    public const STATUSES    = ['draft', 'published', 'archived'];

    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Return a paginated list of questions with optional filters.
     *
     * @param array $filters  Accepted keys: status, difficulty, language_id, category_id, type, search
     * @param int   $page
     * @param int   $perPage
     * @return array{'data': array, 'total': int}
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo    = Database::getConnection();
        $where  = [];
        $bind   = [];

        if (!empty($filters['status'])) {
            $where[]         = 'q.status = :status';
            $bind['status']  = $filters['status'];
        }
        if (!empty($filters['difficulty'])) {
            $where[]              = 'q.difficulty = :difficulty';
            $bind['difficulty']   = $filters['difficulty'];
        }
        if (!empty($filters['language_id'])) {
            $where[]                = 'q.programming_language_id = :language_id';
            $bind['language_id']    = (int) $filters['language_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[]                = 'q.category_id = :category_id';
            $bind['category_id']    = (int) $filters['category_id'];
        }
        if (!empty($filters['type'])) {
            $where[]         = 'q.question_type = :type';
            $bind['type']    = $filters['type'];
        }
        if (!empty($filters['search'])) {
            $where[]          = 'q.question_text LIKE :search';
            $bind['search']   = '%' . $filters['search'] . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM questions q {$whereClause}");
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        // Fetch page
        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT q.*,
                   pl.name AS language_name,
                   c.name  AS category_name,
                   u.username AS created_by_username
            FROM questions q
            LEFT JOIN programming_languages pl ON pl.id = q.programming_language_id
            LEFT JOIN categories c             ON c.id  = q.category_id
            LEFT JOIN users u                  ON u.id  = q.created_by
            {$whereClause}
            ORDER BY q.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        foreach ($bind as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'  => $stmt->fetchAll() ?: [],
            'total' => $total,
        ];
    }

    /**
     * Find a question by primary key, with language/category names joined.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT q.*,
                    pl.name AS language_name,
                    c.name  AS category_name,
                    u.username AS created_by_username
             FROM questions q
             LEFT JOIN programming_languages pl ON pl.id = q.programming_language_id
             LEFT JOIN categories c             ON c.id  = q.category_id
             LEFT JOIN users u                  ON u.id  = q.created_by
             WHERE q.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Create a question (without options — use QuestionOption::replaceAll after).
     *
     * @param array $data  Keys: programming_language_id, category_id, created_by,
     *                           question_text, question_type, difficulty,
     *                           default_mark, explanation, status
     * @return int  New question ID
     * @throws RuntimeException
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO questions
                    (programming_language_id, category_id, created_by, question_text,
                     question_type, difficulty, default_mark, explanation, status)
                 VALUES
                    (:programming_language_id, :category_id, :created_by, :question_text,
                     :question_type, :difficulty, :default_mark, :explanation, :status)'
            );
            $stmt->execute([
                'programming_language_id' => (int) $data['programming_language_id'],
                'category_id'             => (int) $data['category_id'],
                'created_by'              => (int) $data['created_by'],
                'question_text'           => $data['question_text'],
                'question_type'           => $data['question_type'],
                'difficulty'              => $data['difficulty'],
                'default_mark'            => $data['default_mark'] ?? '1.00',
                'explanation'             => $data['explanation'] ?? null,
                'status'                  => $data['status'] ?? 'draft',
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Exception $e) {
            error_log('Question::create error: ' . $e->getMessage());
            throw new RuntimeException('Failed to create question.');
        }
    }

    /**
     * Update question fields (does NOT touch question_options).
     *
     * @param int   $id
     * @param array $data
     * @return bool
     * @throws RuntimeException
     */
    public static function update(int $id, array $data): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'UPDATE questions
                 SET programming_language_id = :programming_language_id,
                     category_id             = :category_id,
                     question_text           = :question_text,
                     question_type           = :question_type,
                     difficulty              = :difficulty,
                     default_mark            = :default_mark,
                     explanation             = :explanation,
                     status                  = :status,
                     updated_at              = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                'id'                      => $id,
                'programming_language_id' => (int) $data['programming_language_id'],
                'category_id'             => (int) $data['category_id'],
                'question_text'           => $data['question_text'],
                'question_type'           => $data['question_type'],
                'difficulty'              => $data['difficulty'],
                'default_mark'            => $data['default_mark'] ?? '1.00',
                'explanation'             => $data['explanation'] ?? null,
                'status'                  => $data['status'] ?? 'draft',
            ]);
            return true;
        } catch (\Exception $e) {
            error_log('Question::update error: ' . $e->getMessage());
            throw new RuntimeException('Failed to update question.');
        }
    }

    /**
     * Archive a question (soft delete — sets status to archived).
     *
     * @param int $id
     * @return bool
     */
    public static function archive(int $id): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE questions SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() >= 0;
    }

    /**
     * Hard-delete only if the question is NOT referenced by any exam or attempt.
     * Otherwise archives it.
     *
     * @param int $id
     * @return array{'action': string, 'success': bool}
     */
    public static function safeDelete(int $id): array
    {
        $pdo = Database::getConnection();

        // Check references
        $refStmt = $pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM exam_questions    WHERE question_id = :id) +
                    (SELECT COUNT(*) FROM attempt_questions WHERE question_id = :id2) AS total'
        );
        $refStmt->execute(['id' => $id, 'id2' => $id]);
        $referenced = (int) $refStmt->fetchColumn();

        if ($referenced > 0) {
            self::archive($id);
            return ['action' => 'archived', 'success' => true];
        }

        // Safe to hard-delete (options are CASCADE-deleted by FK)
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM question_options WHERE question_id = :id')->execute(['id' => $id]);
            $delStmt = $pdo->prepare('DELETE FROM questions WHERE id = :id');
            $delStmt->execute(['id' => $id]);
            $pdo->commit();
            return ['action' => 'deleted', 'success' => true];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Question::safeDelete error: ' . $e->getMessage());
            return ['action' => 'error', 'success' => false];
        }
    }

    /**
     * Check if a question is referenced by any exam or attempt.
     *
     * @param int $id
     * @return bool
     */
    public static function isReferenced(int $id): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM exam_questions    WHERE question_id = :id) +
                    (SELECT COUNT(*) FROM attempt_questions WHERE question_id = :id2) AS total'
        );
        $stmt->execute(['id' => $id, 'id2' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
