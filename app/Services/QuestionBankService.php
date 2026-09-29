<?php

namespace App\Services;

use App\Models\ProgrammingLanguage;
use App\Models\Category;
use App\Models\Question;
use App\Models\QuestionOption;
use RuntimeException;

/**
 * QuestionBankService
 *
 * Orchestrates business logic for the Question Bank:
 *  - Programming Language CRUD + validation
 *  - Category CRUD + validation
 *  - Question CRUD + option validation in a single transaction
 *
 * All methods return a uniform result array:
 *   ['success' => bool, 'errors' => array<string>, 'id' => int|null]
 */
class QuestionBankService
{
    // =========================================================================
    // Programming Languages
    // =========================================================================

    /**
     * Create a new programming language with validation.
     *
     * @param array $data  Raw POST data: name, description, status
     * @return array
     */
    public function createLanguage(array $data): array
    {
        [$clean, $errors] = $this->validateLanguage($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        try {
            $id = ProgrammingLanguage::create($clean);
            return ['success' => true, 'errors' => [], 'id' => $id];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Update an existing programming language with validation.
     *
     * @param int   $id
     * @param array $data
     * @return array
     */
    public function updateLanguage(int $id, array $data): array
    {
        [$clean, $errors] = $this->validateLanguage($data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        try {
            ProgrammingLanguage::update($id, $clean);
            return ['success' => true, 'errors' => [], 'id' => $id];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Delete a language, refusing if questions still reference it.
     *
     * @param int $id
     * @return array
     */
    public function deleteLanguage(int $id): array
    {
        $count = ProgrammingLanguage::questionCount($id);
        if ($count > 0) {
            return [
                'success' => false,
                'errors'  => ["Cannot delete: {$count} question(s) still reference this language."],
                'id'      => null,
            ];
        }

        try {
            ProgrammingLanguage::delete($id);
            return ['success' => true, 'errors' => [], 'id' => null];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Validate language POST data and return cleaned data + errors.
     *
     * @param array    $data
     * @param int|null $excludeId
     * @return array  [cleanedData, errors]
     */
    private function validateLanguage(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        $name   = trim($data['name'] ?? '');
        $desc   = trim($data['description'] ?? '');
        $status = trim($data['status'] ?? 'active');

        if ($name === '') {
            $errors[] = 'Language name is required.';
        } elseif (mb_strlen($name) > 80) {
            $errors[] = 'Language name must not exceed 80 characters.';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Invalid status value.';
            $status   = 'active';
        }

        $slug = ProgrammingLanguage::toSlug($name);
        if ($slug === '' && $name !== '') {
            $errors[] = 'Language name produced an empty slug — please use alphanumeric characters.';
        }

        if (empty($errors)) {
            $uniqueness = ProgrammingLanguage::checkUniqueness($name, $slug, $excludeId);
            if ($uniqueness['name_taken']) {
                $errors[] = 'A language with this name already exists.';
            } elseif ($uniqueness['slug_taken']) {
                $errors[] = 'A language with a similar name (same URL slug) already exists.';
            }
        }

        $clean = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc !== '' ? $desc : null,
            'status'      => $status,
        ];

        return [$clean, $errors];
    }

    // =========================================================================
    // Categories
    // =========================================================================

    /**
     * Create a new category with validation.
     *
     * @param array $data      Raw POST data: name, description, parent_id, status
     * @param int   $createdBy User ID of creator
     * @return array
     */
    public function createCategory(array $data, int $createdBy): array
    {
        [$clean, $errors] = $this->validateCategory($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        $clean['created_by'] = $createdBy;

        try {
            $id = Category::create($clean);
            return ['success' => true, 'errors' => [], 'id' => $id];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Update an existing category with validation.
     *
     * @param int   $id
     * @param array $data
     * @return array
     */
    public function updateCategory(int $id, array $data): array
    {
        [$clean, $errors] = $this->validateCategory($data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        // Prevent category from being its own parent
        $parentId = (int) ($clean['parent_id'] ?? 0);
        if ($parentId === $id) {
            return ['success' => false, 'errors' => ['A category cannot be its own parent.'], 'id' => null];
        }

        try {
            Category::update($id, $clean);
            return ['success' => true, 'errors' => [], 'id' => $id];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Delete a category, refusing if it has children or questions.
     *
     * @param int $id
     * @return array
     */
    public function deleteCategory(int $id): array
    {
        $qCount = Category::questionCount($id);
        if ($qCount > 0) {
            return [
                'success' => false,
                'errors'  => ["Cannot delete: {$qCount} question(s) still reference this category."],
                'id'      => null,
            ];
        }

        $cCount = Category::childCount($id);
        if ($cCount > 0) {
            return [
                'success' => false,
                'errors'  => ["Cannot delete: {$cCount} sub-categor" . ($cCount === 1 ? 'y' : 'ies') . " exist."],
                'id'      => null,
            ];
        }

        try {
            Category::delete($id);
            return ['success' => true, 'errors' => [], 'id' => null];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Validate category POST data.
     *
     * @param array    $data
     * @param int|null $excludeId
     * @return array  [cleanedData, errors]
     */
    private function validateCategory(array $data, ?int $excludeId = null): array
    {
        $errors   = [];
        $name     = trim($data['name'] ?? '');
        $desc     = trim($data['description'] ?? '');
        $status   = trim($data['status'] ?? 'active');
        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;

        if ($name === '') {
            $errors[] = 'Category name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Category name must not exceed 100 characters.';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Invalid status value.';
            $status   = 'active';
        }

        $slug = Category::toSlug($name);
        if ($slug === '' && $name !== '') {
            $errors[] = 'Category name produced an empty slug.';
        }

        if (empty($errors) && Category::slugExists($slug, $excludeId)) {
            $errors[] = 'A category with a similar name (same URL slug) already exists.';
        }

        // Validate parent exists if provided
        if ($parentId !== null && empty($errors)) {
            $parent = Category::findById($parentId);
            if ($parent === null) {
                $errors[] = 'The selected parent category does not exist.';
                $parentId = null;
            }
        }

        $clean = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc !== '' ? $desc : null,
            'parent_id'   => $parentId,
            'status'      => $status,
        ];

        return [$clean, $errors];
    }

    // =========================================================================
    // Questions
    // =========================================================================

    /**
     * Create a question together with its options atomically.
     *
     * @param array $data      Question + option POST data
     * @param int   $createdBy Current user's ID
     * @return array
     */
    public function createQuestion(array $data, int $createdBy): array
    {
        [$clean, $errors] = $this->validateQuestion($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        $options = $this->extractOptions($data, $clean['question_type']);
        $optResult = QuestionOption::validate($clean['question_type'], $options);
        if (!empty($optResult)) {
            return ['success' => false, 'errors' => $optResult, 'id' => null];
        }

        $clean['created_by'] = $createdBy;

        try {
            $questionId = Question::create($clean);
            $saveResult = QuestionOption::replaceAll($questionId, $clean['question_type'], $options);
            if (!$saveResult['success']) {
                // Roll back the question if options failed
                Question::safeDelete($questionId);
                return ['success' => false, 'errors' => $saveResult['errors'], 'id' => null];
            }
            return ['success' => true, 'errors' => [], 'id' => $questionId];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Update a question and replace all of its options atomically.
     *
     * @param int   $id
     * @param array $data
     * @return array
     */
    public function updateQuestion(int $id, array $data): array
    {
        [$clean, $errors] = $this->validateQuestion($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        $options = $this->extractOptions($data, $clean['question_type']);
        $optResult = QuestionOption::validate($clean['question_type'], $options);
        if (!empty($optResult)) {
            return ['success' => false, 'errors' => $optResult, 'id' => null];
        }

        try {
            Question::update($id, $clean);
            $saveResult = QuestionOption::replaceAll($id, $clean['question_type'], $options);
            if (!$saveResult['success']) {
                return ['success' => false, 'errors' => $saveResult['errors'], 'id' => null];
            }
            return ['success' => true, 'errors' => [], 'id' => $id];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Validate question scalar fields.
     *
     * @param array    $data
     * @return array  [cleanedData, errors]
     */
    private function validateQuestion(array $data): array
    {
        $errors = [];

        $text       = trim($data['question_text'] ?? '');
        $type       = trim($data['question_type'] ?? '');
        $difficulty = trim($data['difficulty'] ?? '');
        $status     = trim($data['status'] ?? 'draft');
        $markRaw    = $data['default_mark'] ?? '1.00';
        $langId     = (int) ($data['programming_language_id'] ?? 0);
        $catId      = (int) ($data['category_id'] ?? 0);
        $explanation = trim($data['explanation'] ?? '');

        if ($text === '') {
            $errors[] = 'Question text is required.';
        } elseif (mb_strlen($text) < 5) {
            $errors[] = 'Question text must be at least 5 characters long.';
        }

        if (!in_array($type, Question::TYPES, true)) {
            $errors[] = 'Invalid question type. Allowed: single_choice, true_false.';
        }

        if (!in_array($difficulty, Question::DIFFICULTIES, true)) {
            $errors[] = 'Invalid difficulty. Allowed: easy, medium, hard.';
        }

        if (!in_array($status, Question::STATUSES, true)) {
            $errors[] = 'Invalid status. Allowed: draft, published, archived.';
        }

        $mark = filter_var($markRaw, FILTER_VALIDATE_FLOAT);
        if ($mark === false || $mark <= 0 || $mark > 99999.99) {
            $errors[] = 'Default mark must be a positive number (max 99999.99).';
            $mark = 1.00;
        }

        if ($langId <= 0) {
            $errors[] = 'Programming language is required.';
        } elseif (ProgrammingLanguage::findById($langId) === null) {
            $errors[] = 'Selected programming language does not exist.';
        }

        if ($catId <= 0) {
            $errors[] = 'Category is required.';
        } elseif (Category::findById($catId) === null) {
            $errors[] = 'Selected category does not exist.';
        }

        $clean = [
            'question_text'           => $text,
            'question_type'           => $type,
            'difficulty'              => $difficulty,
            'status'                  => $status,
            'default_mark'            => number_format((float) $mark, 2, '.', ''),
            'programming_language_id' => $langId,
            'category_id'             => $catId,
            'explanation'             => $explanation !== '' ? $explanation : null,
        ];

        return [$clean, $errors];
    }

    /**
     * Extract options from POST data.
     *
     * Supports two formats:
     *  - options[0][option_text], options[0][is_correct], options[0][sort_order]  (general form)
     *  - tf_correct (for true/false: value 'true' or 'false')
     *
     * @param array  $data
     * @param string $type
     * @return array
     */
    private function extractOptions(array $data, string $type): array
    {
        if ($type === 'true_false') {
            $correct = trim($data['tf_correct'] ?? 'true');
            if (!in_array($correct, ['true', 'false'], true)) {
                $correct = 'true';
            }
            return QuestionOption::trueFalseOptions($correct);
        }

        // single_choice: read options[] array from POST
        $raw = $data['options'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $idx => $opt) {
            $text = trim($opt['option_text'] ?? '');
            if ($text === '') {
                continue; // skip blank rows
            }
            $options[] = [
                'option_text' => $text,
                'is_correct'  => !empty($opt['is_correct']),
                'sort_order'  => (int) ($opt['sort_order'] ?? ($idx + 1)),
            ];
        }

        return $options;
    }
}
