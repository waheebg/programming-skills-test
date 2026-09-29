<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionRule;
use App\Models\ProgrammingLanguage;
use App\Models\Question;
use RuntimeException;

/**
 * ExamBuilderService
 *
 * Orchestrates all business logic for the Exam Builder (Phase 6):
 *  - Exam CRUD + validation
 *  - Manual question selection (with duplicate prevention)
 *  - Random question selection rules
 *  - Hybrid selection engine (combines manual + rules, no duplicates)
 *  - Exam publishing validation
 *
 * All public methods return a uniform result array:
 *   ['success' => bool, 'errors' => array<string>, 'id' => int|null]
 */
class ExamBuilderService
{
    // =========================================================================
    // Exam CRUD
    // =========================================================================

    /**
     * Create a new exam.
     *
     * @param array $data      Raw POST data
     * @param int   $createdBy Current user's ID
     * @return array
     */
    public function createExam(array $data, int $createdBy): array
    {
        [$clean, $errors] = $this->validateExam($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        $clean['created_by'] = $createdBy;

        try {
            $id = Exam::create($clean);
            return ['success' => true, 'errors' => [], 'id' => $id];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Update an existing exam (does not change status unless explicitly provided).
     *
     * @param int   $id
     * @param array $data
     * @return array
     */
    public function updateExam(int $id, array $data): array
    {
        [$clean, $errors] = $this->validateExam($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        try {
            Exam::update($id, $clean);
            return ['success' => true, 'errors' => [], 'id' => $id];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Delete (or archive if attempted) an exam.
     *
     * @param int $id
     * @return array
     */
    public function deleteExam(int $id): array
    {
        $result = Exam::safeDelete($id);
        if ($result['success']) {
            return ['success' => true, 'errors' => [], 'id' => $id];
        }
        return ['success' => false, 'errors' => ['Failed to delete exam.'], 'id' => null];
    }

    /**
     * Publish an exam after validating all requirements.
     *
     * Validation:
     *  - Required settings must be complete
     *  - Must have at least 1 configured question (manual or rule)
     *  - For random/hybrid: each rule must have enough published matching questions
     *
     * @param int $examId
     * @return array
     */
    public function publishExam(int $examId): array
    {
        $exam = Exam::findById($examId);
        if (!$exam) {
            return ['success' => false, 'errors' => ['Exam not found.'], 'id' => null];
        }

        if ($exam['status'] === 'archived') {
            return ['success' => false, 'errors' => ['Cannot publish an archived exam.'], 'id' => null];
        }

        // Validate that the exam has at least 1 configured question or rule
        $total = Exam::totalConfiguredCount($examId);
        if ($total < 1) {
            return [
                'success' => false,
                'errors'  => ['Exam must have at least one question or selection rule before publishing.'],
                'id'      => null,
            ];
        }

        // For modes that include rules, validate sufficiency
        if (in_array($exam['selection_mode'], ['random', 'hybrid'], true)) {
            $validationResult = $this->validateRuleSufficiency($examId, $exam['selection_mode']);
            if (!$validationResult['valid']) {
                return ['success' => false, 'errors' => $validationResult['errors'], 'id' => null];
            }
        }

        // For manual-only, all manually selected questions must be published
        if ($exam['selection_mode'] === 'manual') {
            $manualQuestions = ExamQuestion::forExam($examId);
            $unpublished = array_filter($manualQuestions, fn($q) => $q['question_status'] !== 'published');
            if (!empty($unpublished)) {
                $count = count($unpublished);
                return [
                    'success' => false,
                    'errors'  => ["{$count} manually selected question(s) are not published. Only published questions can be in a published exam."],
                    'id'      => null,
                ];
            }
        }

        try {
            $pdo  = \App\Core\Database::getConnection();
            $stmt = $pdo->prepare(
                "UPDATE exams SET status = 'published', updated_at = CURRENT_TIMESTAMP WHERE id = :id"
            );
            $stmt->execute(['id' => $examId]);
            return ['success' => true, 'errors' => [], 'id' => $examId];
        } catch (\Exception $e) {
            error_log('ExamBuilderService::publishExam error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Failed to publish exam.'], 'id' => null];
        }
    }

    // =========================================================================
    // Manual Selection
    // =========================================================================

    /**
     * Add a single question to an exam (manual selection).
     * Prevents duplicates. Only allows published questions.
     *
     * @param int   $examId
     * @param int   $questionId
     * @return array
     */
    public function addQuestion(int $examId, int $questionId): array
    {
        // Verify the question exists and is published
        $question = Question::findById($questionId);
        if (!$question) {
            return ['success' => false, 'errors' => ['Question not found.'], 'id' => null];
        }
        if ($question['status'] !== 'published') {
            return ['success' => false, 'errors' => ['Only published questions can be added to an exam.'], 'id' => null];
        }

        // Duplicate check
        if (ExamQuestion::exists($examId, $questionId)) {
            return ['success' => false, 'errors' => ['This question is already in the exam.'], 'id' => null];
        }

        try {
            ExamQuestion::add($examId, $questionId);
            return ['success' => true, 'errors' => [], 'id' => $questionId];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Add multiple questions to an exam (bulk manual selection).
     * Skips duplicates and unpublished questions silently.
     *
     * @param int   $examId
     * @param array $questionIds
     * @return array  includes 'inserted' => int count
     */
    public function addQuestions(int $examId, array $questionIds): array
    {
        if (empty($questionIds)) {
            return ['success' => false, 'errors' => ['No questions selected.'], 'id' => null, 'inserted' => 0];
        }

        // Filter to published-only
        $publishedIds = [];
        foreach ($questionIds as $qid) {
            $q = Question::findById((int) $qid);
            if ($q && $q['status'] === 'published') {
                $publishedIds[] = (int) $qid;
            }
        }

        if (empty($publishedIds)) {
            return [
                'success'  => false,
                'errors'   => ['None of the selected questions are published.'],
                'id'       => null,
                'inserted' => 0,
            ];
        }

        try {
            $count = ExamQuestion::addMany($examId, $publishedIds);
            return ['success' => true, 'errors' => [], 'id' => null, 'inserted' => $count];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null, 'inserted' => 0];
        }
    }

    /**
     * Remove a question from an exam.
     *
     * @param int $examId
     * @param int $questionId
     * @return array
     */
    public function removeQuestion(int $examId, int $questionId): array
    {
        $removed = ExamQuestion::remove($examId, $questionId);
        if ($removed) {
            return ['success' => true, 'errors' => [], 'id' => null];
        }
        return ['success' => false, 'errors' => ['Question not found in this exam.'], 'id' => null];
    }

    // =========================================================================
    // Random Selection Rules
    // =========================================================================

    /**
     * Add a random selection rule to an exam.
     *
     * @param int   $examId
     * @param array $data  Rule data
     * @return array
     */
    public function addRule(int $examId, array $data): array
    {
        [$clean, $errors] = $this->validateRule($data, $examId);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        $clean['exam_id']    = $examId;
        $clean['rule_order'] = ExamQuestionRule::nextRuleOrder($examId);

        try {
            $id = ExamQuestionRule::create($clean);
            return ['success' => true, 'errors' => [], 'id' => $id];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Update an existing selection rule.
     *
     * @param int   $ruleId
     * @param array $data
     * @return array
     */
    public function updateRule(int $ruleId, array $data): array
    {
        $rule = ExamQuestionRule::findById($ruleId);
        if (!$rule) {
            return ['success' => false, 'errors' => ['Rule not found.'], 'id' => null];
        }

        [$clean, $errors] = $this->validateRule($data, (int) $rule['exam_id']);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'id' => null];
        }

        // Keep the same rule_order unless explicitly changed
        $clean['rule_order'] = !empty($data['rule_order']) ? (int) $data['rule_order'] : (int) $rule['rule_order'];

        try {
            ExamQuestionRule::update($ruleId, $clean);
            return ['success' => true, 'errors' => [], 'id' => $ruleId];
        } catch (RuntimeException $e) {
            return ['success' => false, 'errors' => [$e->getMessage()], 'id' => null];
        }
    }

    /**
     * Remove a rule from an exam.
     *
     * @param int $ruleId
     * @return array
     */
    public function removeRule(int $ruleId): array
    {
        $deleted = ExamQuestionRule::delete($ruleId);
        if ($deleted) {
            return ['success' => true, 'errors' => [], 'id' => null];
        }
        return ['success' => false, 'errors' => ['Rule not found.'], 'id' => null];
    }

    // =========================================================================
    // Hybrid Selection Engine
    // =========================================================================

    /**
     * Validate that all rules for an exam have enough published matching questions,
     * accounting for hybrid mode where manual questions are already excluded.
     *
     * @param int    $examId
     * @param string $mode  'random' or 'hybrid'
     * @return array{'valid': bool, 'errors': array<string>}
     */
    public function validateRuleSufficiency(int $examId, string $mode = 'random'): array
    {
        $rules  = ExamQuestionRule::forExam($examId);
        $errors = [];

        if (empty($rules)) {
            return ['valid' => false, 'errors' => ['No selection rules defined for this exam.']];
        }

        // For hybrid: start with manually selected question IDs as exclusions
        $usedIds = ($mode === 'hybrid') ? ExamQuestion::questionIds($examId) : [];

        foreach ($rules as $rule) {
            $needed    = (int) $rule['question_count'];
            $available = ExamQuestionRule::countMatchingPublished($rule, $usedIds);

            if ($available < $needed) {
                $label = "Rule #{$rule['rule_order']}";
                if (!empty($rule['language_name'])) {
                    $label .= " ({$rule['language_name']}";
                    if (!empty($rule['category_name'])) {
                        $label .= " / {$rule['category_name']}";
                    }
                    if (!empty($rule['difficulty'])) {
                        $label .= " / {$rule['difficulty']}";
                    }
                    $label .= ')';
                }
                $errors[] = "{$label}: needs {$needed} question(s) but only {$available} published matching question(s) available.";
            } else {
                // Reserve these question IDs for subsequent rules (avoid cross-rule duplication)
                $picked  = ExamQuestionRule::pickRandom($rule, $needed, $usedIds);
                $usedIds = array_merge($usedIds, $picked);
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Preview the hybrid selection for an exam without persisting anything.
     * Returns which questions would be included (manual + random picks).
     *
     * @param int $examId
     * @return array{'success': bool, 'errors': array, 'manual': array, 'random': array, 'total': int}
     */
    public function previewHybridSelection(int $examId): array
    {
        $exam = Exam::findById($examId);
        if (!$exam) {
            return ['success' => false, 'errors' => ['Exam not found.'], 'manual' => [], 'random' => [], 'total' => 0];
        }

        $manual = ExamQuestion::forExam($examId);
        $usedIds = array_map(fn($q) => (int) $q['question_id'], $manual);

        $rules = ExamQuestionRule::forExam($examId);
        $randomPicks = [];
        $errors = [];

        foreach ($rules as $rule) {
            $needed    = (int) $rule['question_count'];
            $available = ExamQuestionRule::countMatchingPublished($rule, $usedIds);

            if ($available < $needed) {
                $errors[] = "Rule #{$rule['rule_order']}: needs {$needed}, only {$available} available.";
                continue;
            }

            $picked  = ExamQuestionRule::pickRandom($rule, $needed, $usedIds);
            $usedIds = array_merge($usedIds, $picked);

            // Fetch question details for the preview
            foreach ($picked as $qid) {
                $q = Question::findById($qid);
                if ($q) {
                    $randomPicks[] = array_merge($q, ['from_rule' => $rule['rule_order']]);
                }
            }
        }

        return [
            'success' => empty($errors),
            'errors'  => $errors,
            'manual'  => $manual,
            'random'  => $randomPicks,
            'total'   => count($manual) + count($randomPicks),
        ];
    }

    // =========================================================================
    // Private Validation Helpers
    // =========================================================================

    /**
     * Validate exam POST data.
     *
     * @param array $data
     * @return array  [cleanedData, errors]
     */
    private function validateExam(array $data): array
    {
        $errors = [];

        $title           = trim($data['title'] ?? '');
        $description     = trim($data['description'] ?? '');
        $selectionMode   = trim($data['selection_mode'] ?? 'manual');
        $durationMinutes = (int) ($data['duration_minutes'] ?? 0);
        $passPercentage  = $data['pass_percentage'] ?? '50.00';
        $maxAttempts     = isset($data['max_attempts']) && $data['max_attempts'] !== '' ? (int) $data['max_attempts'] : null;
        $status          = trim($data['status'] ?? 'draft');
        $langId          = isset($data['programming_language_id']) && $data['programming_language_id'] !== ''
                           ? (int) $data['programming_language_id']
                           : null;
        $startsAt        = trim($data['starts_at'] ?? '') ?: null;
        $endsAt          = trim($data['ends_at'] ?? '') ?: null;

        // Boolean flags
        $randomizeQuestions    = !empty($data['randomize_questions'])    ? 1 : 0;
        $randomizeOptions      = !empty($data['randomize_options'])      ? 1 : 0;
        $showResultImmediately = !empty($data['show_result_immediately']) ? 1 : 0;
        $showCorrectAnswers    = !empty($data['show_correct_answers'])    ? 1 : 0;

        // Title
        if ($title === '') {
            $errors[] = 'Exam title is required.';
        } elseif (mb_strlen($title) > 200) {
            $errors[] = 'Title must not exceed 200 characters.';
        }

        // Selection mode
        if (!in_array($selectionMode, Exam::SELECTION_MODES, true)) {
            $errors[] = 'Invalid selection mode. Allowed: manual, random, hybrid.';
            $selectionMode = 'manual';
        }

        // Duration
        if ($durationMinutes <= 0) {
            $errors[] = 'Duration must be at least 1 minute.';
        } elseif ($durationMinutes > 32767) {
            $errors[] = 'Duration is too large.';
        }

        // Pass percentage
        $passFloat = filter_var($passPercentage, FILTER_VALIDATE_FLOAT);
        if ($passFloat === false || $passFloat < 0 || $passFloat > 100) {
            $errors[] = 'Pass percentage must be between 0 and 100.';
            $passFloat = 50.00;
        }

        // Max attempts
        if ($maxAttempts !== null && $maxAttempts <= 0) {
            $errors[] = 'Max attempts must be a positive integer if set.';
            $maxAttempts = null;
        }

        // Status
        if (!in_array($status, Exam::STATUSES, true)) {
            $errors[] = 'Invalid status. Allowed: draft, published, archived.';
            $status = 'draft';
        }

        // Language (optional but must exist if provided)
        if ($langId !== null) {
            $lang = ProgrammingLanguage::findById($langId);
            if ($lang === null) {
                $errors[] = 'Selected programming language does not exist.';
                $langId   = null;
            }
        }

        // Date validation
        if ($startsAt !== null) {
            $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $startsAt)
               ?: \DateTime::createFromFormat('Y-m-d H:i:s', $startsAt)
               ?: \DateTime::createFromFormat('Y-m-d H:i', $startsAt);
            if (!$dt) {
                $errors[] = 'Invalid start date/time format.';
                $startsAt = null;
            } else {
                $startsAt = $dt->format('Y-m-d H:i:s');
            }
        }
        if ($endsAt !== null) {
            $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $endsAt)
               ?: \DateTime::createFromFormat('Y-m-d H:i:s', $endsAt)
               ?: \DateTime::createFromFormat('Y-m-d H:i', $endsAt);
            if (!$dt) {
                $errors[] = 'Invalid end date/time format.';
                $endsAt = null;
            } else {
                $endsAt = $dt->format('Y-m-d H:i:s');
            }
        }
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            $errors[] = 'End date/time must be after start date/time.';
        }

        $clean = [
            'title'                   => $title,
            'description'             => $description !== '' ? $description : null,
            'selection_mode'          => $selectionMode,
            'duration_minutes'        => $durationMinutes,
            'pass_percentage'         => number_format((float) $passFloat, 2, '.', ''),
            'max_attempts'            => $maxAttempts,
            'randomize_questions'     => $randomizeQuestions,
            'randomize_options'       => $randomizeOptions,
            'show_result_immediately' => $showResultImmediately,
            'show_correct_answers'    => $showCorrectAnswers,
            'status'                  => $status,
            'programming_language_id' => $langId,
            'starts_at'               => $startsAt,
            'ends_at'                 => $endsAt,
        ];

        return [$clean, $errors];
    }

    /**
     * Validate a rule POST data.
     *
     * @param array $data
     * @param int   $examId
     * @return array  [cleanedData, errors]
     */
    private function validateRule(array $data, int $examId): array
    {
        $errors         = [];
        $langId         = isset($data['programming_language_id']) && $data['programming_language_id'] !== ''
                          ? (int) $data['programming_language_id']
                          : null;
        $catId          = isset($data['category_id']) && $data['category_id'] !== ''
                          ? (int) $data['category_id']
                          : null;
        $difficulty     = trim($data['difficulty'] ?? '') ?: null;
        $questionCount  = (int) ($data['question_count'] ?? 0);
        $mark           = isset($data['mark']) && $data['mark'] !== '' ? (float) $data['mark'] : null;

        // Validate question count
        if ($questionCount <= 0) {
            $errors[] = 'Question count must be at least 1.';
        }

        // Validate difficulty if provided
        if ($difficulty !== null && !in_array($difficulty, Question::DIFFICULTIES, true)) {
            $errors[] = 'Invalid difficulty. Allowed: easy, medium, hard.';
            $difficulty = null;
        }

        // Validate language if provided
        if ($langId !== null) {
            $lang = ProgrammingLanguage::findById($langId);
            if (!$lang) {
                $errors[] = 'Selected programming language does not exist.';
                $langId   = null;
            }
        }

        // Validate category if provided
        if ($catId !== null) {
            $cat = \App\Models\Category::findById($catId);
            if (!$cat) {
                $errors[] = 'Selected category does not exist.';
                $catId    = null;
            }
        }

        // Validate mark if provided
        if ($mark !== null && $mark < 0) {
            $errors[] = 'Rule mark cannot be negative.';
            $mark = null;
        }

        $clean = [
            'programming_language_id' => $langId,
            'category_id'             => $catId,
            'difficulty'              => $difficulty,
            'question_count'          => $questionCount,
            'mark'                    => $mark,
        ];

        return [$clean, $errors];
    }
}
