<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Exam model.
 *
 * Wraps CRUD for `exams`. All queries use PDO prepared statements.
 *
 * Supported status values:        draft, published, archived
 * Supported selection_mode values: manual, random, hybrid
 */
class Exam
{
    public const STATUSES        = ['draft', 'published', 'archived'];
    public const SELECTION_MODES = ['manual', 'random', 'hybrid'];

    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Return a paginated list of exams with optional filters.
     *
     * @param array $filters  Accepted keys: status, selection_mode, language_id, search
     * @param int   $page
     * @param int   $perPage
     * @return array{'data': array, 'total': int}
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo   = Database::getConnection();
        $where = [];
        $bind  = [];

        if (!empty($filters['status'])) {
            $where[]        = 'e.status = :status';
            $bind['status'] = $filters['status'];
        }
        if (!empty($filters['selection_mode'])) {
            $where[]                 = 'e.selection_mode = :selection_mode';
            $bind['selection_mode']  = $filters['selection_mode'];
        }
        if (!empty($filters['language_id'])) {
            $where[]              = 'e.programming_language_id = :language_id';
            $bind['language_id']  = (int) $filters['language_id'];
        }
        if (!empty($filters['search'])) {
            $where[]         = 'e.title LIKE :search';
            $bind['search']  = '%' . $filters['search'] . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM exams e {$whereClause}");
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT e.*,
                   pl.name        AS language_name,
                   u.username     AS created_by_username,
                   (SELECT COUNT(*) FROM exam_questions    WHERE exam_id = e.id) AS manual_count,
                   (SELECT COALESCE(SUM(question_count), 0) FROM exam_question_rules WHERE exam_id = e.id) AS rule_question_count,
                   (SELECT COUNT(*) FROM exam_question_rules WHERE exam_id = e.id) AS rule_count
            FROM exams e
            LEFT JOIN programming_languages pl ON pl.id = e.programming_language_id
            LEFT JOIN users u                  ON u.id  = e.created_by
            {$whereClause}
            ORDER BY e.created_at DESC
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
     * Find an exam by primary key with language and author names joined.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT e.*,
                    pl.name    AS language_name,
                    u.username AS created_by_username
             FROM exams e
             LEFT JOIN programming_languages pl ON pl.id = e.programming_language_id
             LEFT JOIN users u                  ON u.id  = e.created_by
             WHERE e.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Create a new exam record.
     *
     * @param array $data
     * @return int  New exam ID
     * @throws RuntimeException
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO exams
                    (programming_language_id, created_by, title, description,
                     selection_mode, duration_minutes, pass_percentage, max_attempts,
                     randomize_questions, randomize_options,
                     show_result_immediately, show_correct_answers,
                     status, starts_at, ends_at)
                 VALUES
                    (:programming_language_id, :created_by, :title, :description,
                     :selection_mode, :duration_minutes, :pass_percentage, :max_attempts,
                     :randomize_questions, :randomize_options,
                     :show_result_immediately, :show_correct_answers,
                     :status, :starts_at, :ends_at)'
            );
            $stmt->execute([
                'programming_language_id' => $data['programming_language_id'],
                'created_by'              => (int) $data['created_by'],
                'title'                   => $data['title'],
                'description'             => $data['description'] ?? null,
                'selection_mode'          => $data['selection_mode'],
                'duration_minutes'        => (int) $data['duration_minutes'],
                'pass_percentage'         => $data['pass_percentage'],
                'max_attempts'            => $data['max_attempts'] ?? null,
                'randomize_questions'     => (int) $data['randomize_questions'],
                'randomize_options'       => (int) $data['randomize_options'],
                'show_result_immediately' => (int) $data['show_result_immediately'],
                'show_correct_answers'    => (int) $data['show_correct_answers'],
                'status'                  => $data['status'],
                'starts_at'               => $data['starts_at'] ?? null,
                'ends_at'                 => $data['ends_at'] ?? null,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Exception $e) {
            error_log('Exam::create error: ' . $e->getMessage());
            throw new RuntimeException('Failed to create exam.');
        }
    }

    /**
     * Update an exam record.
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
                'UPDATE exams
                 SET programming_language_id = :programming_language_id,
                     title                   = :title,
                     description             = :description,
                     selection_mode          = :selection_mode,
                     duration_minutes        = :duration_minutes,
                     pass_percentage         = :pass_percentage,
                     max_attempts            = :max_attempts,
                     randomize_questions     = :randomize_questions,
                     randomize_options       = :randomize_options,
                     show_result_immediately = :show_result_immediately,
                     show_correct_answers    = :show_correct_answers,
                     status                  = :status,
                     starts_at               = :starts_at,
                     ends_at                 = :ends_at,
                     updated_at              = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                'id'                      => $id,
                'programming_language_id' => $data['programming_language_id'],
                'title'                   => $data['title'],
                'description'             => $data['description'] ?? null,
                'selection_mode'          => $data['selection_mode'],
                'duration_minutes'        => (int) $data['duration_minutes'],
                'pass_percentage'         => $data['pass_percentage'],
                'max_attempts'            => $data['max_attempts'] ?? null,
                'randomize_questions'     => (int) $data['randomize_questions'],
                'randomize_options'       => (int) $data['randomize_options'],
                'show_result_immediately' => (int) $data['show_result_immediately'],
                'show_correct_answers'    => (int) $data['show_correct_answers'],
                'status'                  => $data['status'],
                'starts_at'               => $data['starts_at'] ?? null,
                'ends_at'                 => $data['ends_at'] ?? null,
            ]);
            return true;
        } catch (\Exception $e) {
            error_log('Exam::update error: ' . $e->getMessage());
            throw new RuntimeException('Failed to update exam.');
        }
    }

    /**
     * Archive an exam (soft delete).
     *
     * @param int $id
     * @return bool
     */
    public static function archive(int $id): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE exams SET status = 'archived', updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() >= 0;
    }

    /**
     * Delete an exam if it has no attempts; archive otherwise.
     *
     * @param int $id
     * @return array{'action': string, 'success': bool}
     */
    public static function safeDelete(int $id): array
    {
        $pdo = Database::getConnection();

        $refStmt = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE exam_id = :id');
        $refStmt->execute(['id' => $id]);
        $referenced = (int) $refStmt->fetchColumn();

        if ($referenced > 0) {
            self::archive($id);
            return ['action' => 'archived', 'success' => true];
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM exam_question_rules WHERE exam_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM exam_questions WHERE exam_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM exams WHERE id = :id')->execute(['id' => $id]);
            $pdo->commit();
            return ['action' => 'deleted', 'success' => true];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Exam::safeDelete error: ' . $e->getMessage());
            return ['action' => 'error', 'success' => false];
        }
    }

    // -------------------------------------------------------------------------
    // Question counts
    // -------------------------------------------------------------------------

    /**
     * Count manually attached questions for an exam.
     *
     * @param int $id
     * @return int
     */
    public static function manualQuestionCount(int $id): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM exam_questions WHERE exam_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Sum of question_count from all rules for an exam.
     *
     * @param int $id
     * @return int
     */
    public static function ruleQuestionCount(int $id): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(question_count), 0) FROM exam_question_rules WHERE exam_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Total configured questions (manual + rules).
     *
     * @param int $id
     * @return int
     */
    public static function totalConfiguredCount(int $id): int
    {
        return self::manualQuestionCount($id) + self::ruleQuestionCount($id);
    }
}
