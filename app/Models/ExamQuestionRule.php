<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * ExamQuestionRule model.
 *
 * Manages the exam_question_rules table (random/hybrid question selection rules).
 * All queries use PDO prepared statements.
 */
class ExamQuestionRule
{
    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Return all rules for an exam, ordered by rule_order.
     *
     * @param int $examId
     * @return array
     */
    public static function forExam(int $examId): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT r.*,
                    pl.name AS language_name,
                    c.name  AS category_name
             FROM exam_question_rules r
             LEFT JOIN programming_languages pl ON pl.id = r.programming_language_id
             LEFT JOIN categories c             ON c.id  = r.category_id
             WHERE r.exam_id = :exam_id
             ORDER BY r.rule_order ASC'
        );
        $stmt->execute(['exam_id' => $examId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Find a rule by its primary key.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM exam_question_rules WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get the next available rule_order for an exam.
     *
     * @param int $examId
     * @return int
     */
    public static function nextRuleOrder(int $examId): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(rule_order), 0) FROM exam_question_rules WHERE exam_id = :exam_id'
        );
        $stmt->execute(['exam_id' => $examId]);
        return (int) $stmt->fetchColumn() + 1;
    }

    /**
     * Count how many published questions match a rule's criteria,
     * excluding a given set of already-used question IDs.
     *
     * @param array     $rule          Rule row array
     * @param array<int> $excludeIds   Question IDs to exclude
     * @return int
     */
    public static function countMatchingPublished(array $rule, array $excludeIds = []): int
    {
        $pdo   = Database::getConnection();
        $where = ["q.status = 'published'"];
        $bind  = [];

        if (!empty($rule['programming_language_id'])) {
            $where[]            = 'q.programming_language_id = :lang_id';
            $bind['lang_id']    = (int) $rule['programming_language_id'];
        }
        if (!empty($rule['category_id'])) {
            $where[]          = 'q.category_id = :cat_id';
            $bind['cat_id']   = (int) $rule['category_id'];
        }
        if (!empty($rule['difficulty'])) {
            $where[]           = 'q.difficulty = :difficulty';
            $bind['difficulty'] = $rule['difficulty'];
        }

        if (!empty($excludeIds)) {
            $exPlaceholders = [];
            foreach (array_values($excludeIds) as $idx => $eid) {
                $pName = 'ex_' . $idx;
                $exPlaceholders[] = ':' . $pName;
                $bind[$pName]     = (int) $eid;
            }
            $where[] = 'q.id NOT IN (' . implode(',', $exPlaceholders) . ')';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $sql         = "SELECT COUNT(*) FROM questions q {$whereClause}";

        $stmt = $pdo->prepare($sql);
        foreach ($bind as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Pick random published questions matching a rule's criteria,
     * excluding a given set of already-used question IDs.
     *
     * @param array      $rule
     * @param int        $count
     * @param array<int> $excludeIds
     * @return array<int>  Array of question IDs
     */
    public static function pickRandom(array $rule, int $count, array $excludeIds = []): array
    {
        $pdo   = Database::getConnection();
        $where = ["q.status = 'published'"];
        $bind  = [];

        if (!empty($rule['programming_language_id'])) {
            $where[]            = 'q.programming_language_id = :lang_id';
            $bind['lang_id']    = (int) $rule['programming_language_id'];
        }
        if (!empty($rule['category_id'])) {
            $where[]          = 'q.category_id = :cat_id';
            $bind['cat_id']   = (int) $rule['category_id'];
        }
        if (!empty($rule['difficulty'])) {
            $where[]            = 'q.difficulty = :difficulty';
            $bind['difficulty'] = $rule['difficulty'];
        }

        if (!empty($excludeIds)) {
            $exPlaceholders = [];
            foreach (array_values($excludeIds) as $idx => $eid) {
                $pName = 'ex_' . $idx;
                $exPlaceholders[] = ':' . $pName;
                $bind[$pName]     = (int) $eid;
            }
            $where[] = 'q.id NOT IN (' . implode(',', $exPlaceholders) . ')';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $sql         = "SELECT q.id FROM questions q {$whereClause} ORDER BY RAND() LIMIT :lim";

        $stmt = $pdo->prepare($sql);
        foreach ($bind as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('lim', $count, PDO::PARAM_INT);
        $stmt->execute();
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Create a new rule for an exam.
     *
     * @param array $data  Keys: exam_id, programming_language_id, category_id, difficulty, question_count, mark, rule_order
     * @return int  New rule ID
     * @throws RuntimeException
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO exam_question_rules
                    (exam_id, programming_language_id, category_id, difficulty, question_count, mark, rule_order)
                 VALUES
                    (:exam_id, :programming_language_id, :category_id, :difficulty, :question_count, :mark, :rule_order)'
            );
            $stmt->execute([
                'exam_id'                 => (int) $data['exam_id'],
                'programming_language_id' => !empty($data['programming_language_id'])
                                             ? (int) $data['programming_language_id']
                                             : null,
                'category_id'             => !empty($data['category_id'])
                                             ? (int) $data['category_id']
                                             : null,
                'difficulty'              => !empty($data['difficulty']) ? $data['difficulty'] : null,
                'question_count'          => (int) $data['question_count'],
                'mark'                    => isset($data['mark']) && $data['mark'] !== '' ? (float) $data['mark'] : null,
                'rule_order'              => (int) $data['rule_order'],
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Exception $e) {
            error_log('ExamQuestionRule::create error: ' . $e->getMessage());
            throw new RuntimeException('Failed to create exam question rule.');
        }
    }

    /**
     * Update an existing rule.
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
                'UPDATE exam_question_rules
                 SET programming_language_id = :programming_language_id,
                     category_id             = :category_id,
                     difficulty              = :difficulty,
                     question_count          = :question_count,
                     mark                    = :mark,
                     rule_order              = :rule_order
                 WHERE id = :id'
            );
            $stmt->execute([
                'id'                      => $id,
                'programming_language_id' => !empty($data['programming_language_id'])
                                             ? (int) $data['programming_language_id']
                                             : null,
                'category_id'             => !empty($data['category_id'])
                                             ? (int) $data['category_id']
                                             : null,
                'difficulty'              => !empty($data['difficulty']) ? $data['difficulty'] : null,
                'question_count'          => (int) $data['question_count'],
                'mark'                    => isset($data['mark']) && $data['mark'] !== '' ? (float) $data['mark'] : null,
                'rule_order'              => (int) $data['rule_order'],
            ]);
            return true;
        } catch (\Exception $e) {
            error_log('ExamQuestionRule::update error: ' . $e->getMessage());
            throw new RuntimeException('Failed to update exam question rule.');
        }
    }

    /**
     * Delete a rule by ID.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM exam_question_rules WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete all rules for an exam.
     *
     * @param int $examId
     * @return void
     */
    public static function removeAll(int $examId): void
    {
        $pdo = Database::getConnection();
        $pdo->prepare('DELETE FROM exam_question_rules WHERE exam_id = :exam_id')
            ->execute(['exam_id' => $examId]);
    }
}
