<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * QuestionOption model.
 *
 * Manages question_options rows.
 * Key business rules enforced here:
 *  - single_choice: exactly 1 correct option required
 *  - true_false: exactly 2 options ("True" / "False"), exactly 1 correct
 *  - No duplicate option_text within the same question
 */
class QuestionOption
{
    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Return all options for a question, ordered by sort_order.
     *
     * @param int $questionId
     * @return array<int, array>
     */
    public static function forQuestion(int $questionId): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM question_options WHERE question_id = :qid ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['qid' => $questionId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Find a single option by its ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM question_options WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Replace ALL options for a question atomically inside a transaction.
     * Validates business rules before persisting.
     *
     * @param int    $questionId
     * @param string $questionType  'single_choice' | 'true_false'
     * @param array  $options       Array of ['option_text'=>string, 'is_correct'=>bool, 'sort_order'=>int]
     * @return array{'success': bool, 'errors': array<string>}
     */
    public static function replaceAll(int $questionId, string $questionType, array $options): array
    {
        // ── Business-rule validation ─────────────────────────────────────────
        $errors = self::validate($questionType, $options);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // ── Persist ──────────────────────────────────────────────────────────
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();

            // Delete existing options
            $pdo->prepare('DELETE FROM question_options WHERE question_id = :qid')
                ->execute(['qid' => $questionId]);

            // Insert new options
            $stmt = $pdo->prepare(
                'INSERT INTO question_options (question_id, option_text, is_correct, sort_order)
                 VALUES (:question_id, :option_text, :is_correct, :sort_order)'
            );

            foreach ($options as $idx => $opt) {
                $stmt->execute([
                    'question_id' => $questionId,
                    'option_text' => trim($opt['option_text']),
                    'is_correct'  => $opt['is_correct'] ? 1 : 0,
                    'sort_order'  => $opt['sort_order'] ?? ($idx + 1),
                ]);
            }

            $pdo->commit();
            return ['success' => true, 'errors' => []];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('QuestionOption::replaceAll error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['A database error occurred while saving options.']];
        }
    }

    /**
     * Delete all options for a question (used before hard-deleting a question).
     *
     * @param int $questionId
     * @return void
     */
    public static function deleteAllForQuestion(int $questionId): void
    {
        $pdo = Database::getConnection();
        $pdo->prepare('DELETE FROM question_options WHERE question_id = :qid')
            ->execute(['qid' => $questionId]);
    }

    // -------------------------------------------------------------------------
    // Validation helpers
    // -------------------------------------------------------------------------

    /**
     * Validate options array for a given question type.
     *
     * @param string $questionType
     * @param array  $options
     * @return array<string>  List of error messages (empty = valid)
     */
    public static function validate(string $questionType, array $options): array
    {
        $errors = [];

        if (empty($options)) {
            $errors[] = 'At least one option is required.';
            return $errors;
        }

        // Strip blank options
        $options = array_filter($options, fn($o) => trim($o['option_text'] ?? '') !== '');

        if (empty($options)) {
            $errors[] = 'Options cannot be empty.';
            return $errors;
        }

        // Check for duplicate option texts
        $texts = array_map(fn($o) => mb_strtolower(trim($o['option_text'])), $options);
        if (count($texts) !== count(array_unique($texts))) {
            $errors[] = 'Duplicate option text is not allowed.';
        }

        $correctCount = count(array_filter($options, fn($o) => !empty($o['is_correct'])));

        if ($questionType === 'single_choice') {
            if (count($options) < 2) {
                $errors[] = 'Single-choice questions must have at least 2 options.';
            }
            if ($correctCount !== 1) {
                $errors[] = 'Single-choice questions must have exactly one correct option.';
            }
        } elseif ($questionType === 'true_false') {
            if (count($options) !== 2) {
                $errors[] = 'True/False questions must have exactly 2 options.';
            } else {
                $optTexts = array_map(fn($o) => mb_strtolower(trim($o['option_text'])), $options);
                sort($optTexts);
                if ($optTexts !== ['false', 'true']) {
                    $errors[] = 'True/False questions must have exactly "True" and "False" as options.';
                }
            }
            if ($correctCount !== 1) {
                $errors[] = 'True/False questions must have exactly one correct option.';
            }
        } else {
            $errors[] = 'Unknown question type.';
        }

        return $errors;
    }

    /**
     * Build the canonical True/False options array for seeding a new true_false question.
     *
     * @param string $correctAnswer  'true' or 'false'
     * @return array
     */
    public static function trueFalseOptions(string $correctAnswer = 'true'): array
    {
        return [
            ['option_text' => 'True',  'is_correct' => ($correctAnswer === 'true'),  'sort_order' => 1],
            ['option_text' => 'False', 'is_correct' => ($correctAnswer === 'false'), 'sort_order' => 2],
        ];
    }
}
