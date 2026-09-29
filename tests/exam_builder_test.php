<?php

/**
 * Phase 6 — Exam Builder + Hybrid Selection Engine Test Suite
 *
 * Tests:
 *  Section 1:  Class existence (models, service, controller)
 *  Section 2:  Exam CRUD (create, read, update, delete)
 *  Section 3:  Exam validation (title, duration, pass %, status, dates)
 *  Section 4:  Manual question selection (add, duplicate prevention, remove)
 *  Section 5:  Published-question-only filtering for manual selection
 *  Section 6:  Random selection rules (create, read, delete)
 *  Section 7:  Rule validation (count, difficulty, language, category)
 *  Section 8:  Hybrid selection engine (rule sufficiency validation)
 *  Section 9:  Hybrid selection — insufficient question handling (fail-safe)
 *  Section 10: Category/difficulty filtering in rules
 *  Section 11: Duplicate prevention across manual + random (hybrid)
 *  Section 12: Exam publishing validation
 *  Section 13: RBAC authorization checks (exams.view/create/update/delete)
 *  Section 14: CSRF validation
 *  Section 15: Database integrity (schema unchanged)
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function ($class) {
    $prefix  = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Core\Database;
use App\Core\Session;
use App\Core\Csrf;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionRule;
use App\Models\ProgrammingLanguage;
use App\Models\Category;
use App\Models\Question;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\ExamBuilderService;

ob_start();
Session::start();

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] {$description}\n";
        $passed++;
    } else {
        echo "[FAIL] {$description}" . ($details ? " -> {$details}" : '') . "\n";
        $failed++;
    }
}

echo "============================================================\n";
echo "Phase 6 — Exam Builder + Hybrid Selection Engine Test Suite\n";
echo "============================================================\n\n";

$pdo     = Database::getConnection();
$service = new ExamBuilderService();
$auth    = new AuthService();

// ── Global cleanup ─────────────────────────────────────────────────────────────
$pdo->exec("DELETE FROM exam_question_rules WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST6_%')");
$pdo->exec("DELETE FROM exam_questions     WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST6_%')");
$pdo->exec("DELETE FROM exams WHERE title LIKE 'TEST6_%'");
$pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE question_text LIKE 'TEST6_%')");
$pdo->exec("DELETE FROM questions WHERE question_text LIKE 'TEST6_%'");
$pdo->exec("DELETE FROM categories WHERE slug LIKE 'test6-%'");
$pdo->exec("DELETE FROM programming_languages WHERE slug LIKE 'test6-%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'exam6_%'");

// ── Create test fixtures ───────────────────────────────────────────────────────
// Author user
$authorReg = $auth->register([
    'first_name'            => 'Exam6',
    'last_name'             => 'Author',
    'username'              => 'exam6_author',
    'email'                 => 'exam6_author@test.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
$authorId = (int) $authorReg['user_id'];

// Language
$langStmt = $pdo->prepare("INSERT INTO programming_languages (name, slug, description, status) VALUES ('TEST6_Lang','test6-lang','Test lang','active')");
$langStmt->execute();
$langId = (int) $pdo->lastInsertId();

// Categories
$catStmt = $pdo->prepare("INSERT INTO categories (name, slug, status, created_by) VALUES ('TEST6_Cat_A','test6-cat-a','active',:uid)");
$catStmt->execute(['uid' => $authorId]);
$catId = (int) $pdo->lastInsertId();

$catStmt2 = $pdo->prepare("INSERT INTO categories (name, slug, status, created_by) VALUES ('TEST6_Cat_B','test6-cat-b','active',:uid)");
$catStmt2->execute(['uid' => $authorId]);
$catId2 = (int) $pdo->lastInsertId();

// Published questions (10 easy + 10 medium + 5 hard)
$publishedIds = [];
foreach (['easy' => 10, 'medium' => 10, 'hard' => 5] as $diff => $count) {
    for ($i = 1; $i <= $count; $i++) {
        $qStmt = $pdo->prepare(
            "INSERT INTO questions (programming_language_id, category_id, created_by, question_text, question_type, difficulty, default_mark, status)
             VALUES (:lid, :cid, :uid, :txt, 'single_choice', :diff, 1.00, 'published')"
        );
        $qStmt->execute([
            'lid'  => $langId,
            'cid'  => $catId,
            'uid'  => $authorId,
            'txt'  => "TEST6_{$diff}_q{$i} question text here?",
            'diff' => $diff,
        ]);
        $publishedIds[] = (int) $pdo->lastInsertId();
    }
}

// Draft questions (should be excluded)
$draftIds = [];
for ($i = 1; $i <= 3; $i++) {
    $draftStmt = $pdo->prepare(
        "INSERT INTO questions (programming_language_id, category_id, created_by, question_text, question_type, difficulty, default_mark, status)
         VALUES (:lid, :cid, :uid, :txt, 'single_choice', 'easy', 1.00, 'draft')"
    );
    $draftStmt->execute([
        'lid' => $langId,
        'cid' => $catId,
        'uid' => $authorId,
        'txt' => "TEST6_draft_q{$i} draft question?",
    ]);
    $draftIds[] = (int) $pdo->lastInsertId();
}

// Cat-B questions (for category filtering tests)
$catBIds = [];
for ($i = 1; $i <= 5; $i++) {
    $bStmt = $pdo->prepare(
        "INSERT INTO questions (programming_language_id, category_id, created_by, question_text, question_type, difficulty, default_mark, status)
         VALUES (:lid, :cid, :uid, :txt, 'single_choice', 'medium', 1.00, 'published')"
    );
    $bStmt->execute([
        'lid' => $langId,
        'cid' => $catId2,
        'uid' => $authorId,
        'txt' => "TEST6_catB_q{$i} cat-B question?",
    ]);
    $catBIds[] = (int) $pdo->lastInsertId();
}

// =============================================================================
// Section 1: Class existence
// =============================================================================
echo "--- Section 1: Class existence ---\n";

assertTest('Exam model exists',             class_exists(Exam::class));
assertTest('ExamQuestion model exists',     class_exists(ExamQuestion::class));
assertTest('ExamQuestionRule model exists', class_exists(ExamQuestionRule::class));
assertTest('ExamBuilderService exists',     class_exists(ExamBuilderService::class));

// =============================================================================
// Section 2: Exam CRUD
// =============================================================================
echo "\n--- Section 2: Exam CRUD ---\n";

// Create
$createResult = $service->createExam([
    'title'                   => 'TEST6_Exam_Basic',
    'description'             => 'A basic test exam',
    'programming_language_id' => $langId,
    'selection_mode'          => 'manual',
    'duration_minutes'        => '60',
    'pass_percentage'         => '70.00',
    'max_attempts'            => '3',
    'randomize_questions'     => '1',
    'randomize_options'       => '1',
    'show_result_immediately' => '1',
    'show_correct_answers'    => '',
    'status'                  => 'draft',
    'starts_at'               => '',
    'ends_at'                 => '',
], $authorId);
assertTest('Exam create succeeds', $createResult['success'] === true, json_encode($createResult['errors']));
$examId = $createResult['id'];

// Read
$exam = Exam::findById($examId);
assertTest('Exam findById returns record',        $exam !== null);
assertTest('Exam title saved correctly',          $exam !== null && $exam['title'] === 'TEST6_Exam_Basic');
assertTest('Exam selection_mode = manual',        $exam !== null && $exam['selection_mode'] === 'manual');
assertTest('Exam duration_minutes = 60',          $exam !== null && (int)$exam['duration_minutes'] === 60);
assertTest('Exam pass_percentage = 70.00',        $exam !== null && (float)$exam['pass_percentage'] === 70.00);
assertTest('Exam max_attempts = 3',               $exam !== null && (int)$exam['max_attempts'] === 3);
assertTest('Exam status = draft',                 $exam !== null && $exam['status'] === 'draft');
assertTest('Exam randomize_questions = 1',        $exam !== null && (bool)$exam['randomize_questions'] === true);
assertTest('Exam show_correct_answers = 0',       $exam !== null && (bool)$exam['show_correct_answers'] === false);

// Paginate
$paginated = Exam::paginate(['status' => 'draft'], 1, 20);
$found = array_filter($paginated['data'], fn($e) => (int)$e['id'] === $examId);
assertTest('Exam appears in paginated list', count($found) === 1);

// Update
$updateResult = $service->updateExam($examId, [
    'title'                   => 'TEST6_Exam_Updated',
    'description'             => 'Updated description',
    'programming_language_id' => $langId,
    'selection_mode'          => 'hybrid',
    'duration_minutes'        => '90',
    'pass_percentage'         => '80.00',
    'max_attempts'            => '',
    'randomize_questions'     => '',
    'randomize_options'       => '1',
    'show_result_immediately' => '1',
    'show_correct_answers'    => '1',
    'status'                  => 'draft',
    'starts_at'               => '',
    'ends_at'                 => '',
]);
assertTest('Exam update succeeds', $updateResult['success'] === true, json_encode($updateResult['errors']));

$examUpdated = Exam::findById($examId);
assertTest('Exam title updated',          $examUpdated['title'] === 'TEST6_Exam_Updated');
assertTest('Exam mode updated to hybrid', $examUpdated['selection_mode'] === 'hybrid');
assertTest('Exam duration updated to 90', (int)$examUpdated['duration_minutes'] === 90);
assertTest('Exam max_attempts = null',    $examUpdated['max_attempts'] === null);
assertTest('Exam show_correct_answers updated', (bool)$examUpdated['show_correct_answers'] === true);

// Safe delete
$deleteResult = $service->deleteExam($examId);
assertTest('Exam delete succeeds', $deleteResult['success'] === true);
assertTest('Exam gone from DB',    Exam::findById($examId) === null);

// =============================================================================
// Section 3: Exam Validation
// =============================================================================
echo "\n--- Section 3: Exam validation ---\n";

// Empty title
$r = $service->createExam(['title' => '', 'duration_minutes' => 60, 'pass_percentage' => 50, 'selection_mode' => 'manual', 'status' => 'draft'], $authorId);
assertTest('Empty title rejected', $r['success'] === false && !empty($r['errors']));

// Zero duration
$r = $service->createExam(['title' => 'TEST6_Dur0', 'duration_minutes' => '0', 'pass_percentage' => 50, 'selection_mode' => 'manual', 'status' => 'draft'], $authorId);
assertTest('Zero duration rejected', $r['success'] === false);

// Invalid pass percentage
$r = $service->createExam(['title' => 'TEST6_Pass', 'duration_minutes' => 30, 'pass_percentage' => '150', 'selection_mode' => 'manual', 'status' => 'draft'], $authorId);
assertTest('Pass percentage > 100 rejected', $r['success'] === false);

// Invalid selection mode
$r = $service->createExam(['title' => 'TEST6_Mode', 'duration_minutes' => 30, 'pass_percentage' => 50, 'selection_mode' => 'magic', 'status' => 'draft'], $authorId);
assertTest('Invalid selection_mode rejected', $r['success'] === false);

// Invalid status
$r = $service->createExam(['title' => 'TEST6_Stat', 'duration_minutes' => 30, 'pass_percentage' => 50, 'selection_mode' => 'manual', 'status' => 'bogus'], $authorId);
assertTest('Invalid status rejected', $r['success'] === false);

// ends_at before starts_at
$r = $service->createExam([
    'title' => 'TEST6_Dates', 'duration_minutes' => 30, 'pass_percentage' => 50, 'selection_mode' => 'manual', 'status' => 'draft',
    'starts_at' => '2026-12-31T23:00', 'ends_at' => '2026-01-01T00:00',
], $authorId);
assertTest('ends_at before starts_at rejected', $r['success'] === false);

// Valid with null language
$r = $service->createExam([
    'title'                   => 'TEST6_NoLang',
    'duration_minutes'        => 45,
    'pass_percentage'         => 60,
    'selection_mode'          => 'manual',
    'status'                  => 'draft',
    'programming_language_id' => '',
], $authorId);
assertTest('Exam with no language accepted', $r['success'] === true, json_encode($r['errors']));
if ($r['success']) {
    Exam::safeDelete($r['id']);
}

// =============================================================================
// Section 4: Manual Question Selection
// =============================================================================
echo "\n--- Section 4: Manual question selection ---\n";

// Create a fresh exam for selection tests
$examR = $service->createExam([
    'title'                   => 'TEST6_Exam_Manual',
    'duration_minutes'        => 60,
    'pass_percentage'         => 50,
    'selection_mode'          => 'manual',
    'status'                  => 'draft',
    'programming_language_id' => $langId,
], $authorId);
$manualExamId = $examR['id'];

// Add a published question
$q1 = $publishedIds[0];
$addResult = $service->addQuestion($manualExamId, $q1);
assertTest('Add published question succeeds',    $addResult['success'] === true, json_encode($addResult['errors']));
assertTest('Question appears in exam_questions', ExamQuestion::exists($manualExamId, $q1));

// Verify count
assertTest('Exam::manualQuestionCount = 1', Exam::manualQuestionCount($manualExamId) === 1);

// Duplicate prevention
$dupResult = $service->addQuestion($manualExamId, $q1);
assertTest('Duplicate question rejected',        $dupResult['success'] === false);
assertTest('Still only 1 question after dup attempt', Exam::manualQuestionCount($manualExamId) === 1);

// Add more
$q2 = $publishedIds[1];
$q3 = $publishedIds[2];
$service->addQuestion($manualExamId, $q2);
$service->addQuestion($manualExamId, $q3);
assertTest('Total 3 questions added', Exam::manualQuestionCount($manualExamId) === 3);

// Bulk add with duplicates
$bulkResult = $service->addQuestions($manualExamId, [$q1, $q2, $publishedIds[3], $publishedIds[4]]);
assertTest('Bulk add skips duplicates', $bulkResult['success'] === true && $bulkResult['inserted'] === 2);
assertTest('Total 5 after bulk add', Exam::manualQuestionCount($manualExamId) === 5);

// Remove a question
$remResult = $service->removeQuestion($manualExamId, $q3);
assertTest('Remove question succeeds', $remResult['success'] === true);
assertTest('Total 4 after remove', Exam::manualQuestionCount($manualExamId) === 4);
assertTest('Removed question no longer in exam', !ExamQuestion::exists($manualExamId, $q3));

// Remove non-existent
$badRem = $service->removeQuestion($manualExamId, 999999);
assertTest('Remove non-existent question returns error', $badRem['success'] === false);

// =============================================================================
// Section 5: Published-question-only filtering
// =============================================================================
echo "\n--- Section 5: Published-question-only filter ---\n";

// Try to add a draft question
$draftQ = $draftIds[0];
$draftAdd = $service->addQuestion($manualExamId, $draftQ);
assertTest('Draft question rejected from exam', $draftAdd['success'] === false);
assertTest('Draft question not in exam_questions', !ExamQuestion::exists($manualExamId, $draftQ));

// Bulk add: mix of published and draft — only published get inserted
$prevCount = Exam::manualQuestionCount($manualExamId);
$mixResult = $service->addQuestions($manualExamId, [$draftIds[0], $draftIds[1], $publishedIds[5]]);
assertTest('Bulk add with drafts: only published inserted',
    $mixResult['success'] === true && $mixResult['inserted'] === 1);
assertTest('Draft questions excluded from bulk add',
    !ExamQuestion::exists($manualExamId, $draftIds[0]) && !ExamQuestion::exists($manualExamId, $draftIds[1]));

// =============================================================================
// Section 6: Random Selection Rules
// =============================================================================
echo "\n--- Section 6: Random selection rules ---\n";

// Create an exam for rule tests
$ruleExamR = $service->createExam([
    'title'            => 'TEST6_Exam_Rules',
    'duration_minutes' => 60,
    'pass_percentage'  => 50,
    'selection_mode'   => 'random',
    'status'           => 'draft',
], $authorId);
$ruleExamId = $ruleExamR['id'];

// Add rule: 5 easy questions
$ruleResult = $service->addRule($ruleExamId, [
    'programming_language_id' => $langId,
    'category_id'             => $catId,
    'difficulty'              => 'easy',
    'question_count'          => '5',
    'mark'                    => '2.00',
]);
assertTest('Rule create succeeds', $ruleResult['success'] === true, json_encode($ruleResult['errors']));
$ruleId1 = $ruleResult['id'];

// Read rules
$rules = ExamQuestionRule::forExam($ruleExamId);
assertTest('1 rule returned for exam', count($rules) === 1);
assertTest('Rule has correct question_count', (int)$rules[0]['question_count'] === 5);
assertTest('Rule has correct difficulty',     $rules[0]['difficulty'] === 'easy');
assertTest('Rule has language name joined',   $rules[0]['language_name'] !== null);

// Add second rule: 3 medium questions
$rule2Result = $service->addRule($ruleExamId, [
    'difficulty'     => 'medium',
    'question_count' => '3',
]);
assertTest('Second rule create succeeds', $rule2Result['success'] === true);
$ruleId2 = $rule2Result['id'];

assertTest('Rule count is 2', count(ExamQuestionRule::forExam($ruleExamId)) === 2);
assertTest('ruleQuestionCount = 8', Exam::ruleQuestionCount($ruleExamId) === 8);

// Remove rule
$remRule = $service->removeRule($ruleId2);
assertTest('Rule remove succeeds', $remRule['success'] === true);
assertTest('Rule count is 1 after remove', count(ExamQuestionRule::forExam($ruleExamId)) === 1);

// =============================================================================
// Section 7: Rule validation
// =============================================================================
echo "\n--- Section 7: Rule validation ---\n";

// Zero question count
$badRule = $service->addRule($ruleExamId, ['question_count' => '0']);
assertTest('Rule with 0 question_count rejected', $badRule['success'] === false);

// Invalid difficulty
$badDiff = $service->addRule($ruleExamId, ['question_count' => '3', 'difficulty' => 'impossible']);
assertTest('Rule with invalid difficulty rejected', $badDiff['success'] === false);

// Non-existent language
$badLang = $service->addRule($ruleExamId, ['question_count' => '3', 'programming_language_id' => '999999']);
assertTest('Rule with non-existent language rejected', $badLang['success'] === false);

// Non-existent category
$badCat = $service->addRule($ruleExamId, ['question_count' => '3', 'category_id' => '999999']);
assertTest('Rule with non-existent category rejected', $badCat['success'] === false);

// Negative mark
$badMark = $service->addRule($ruleExamId, ['question_count' => '3', 'mark' => '-1']);
assertTest('Rule with negative mark rejected', $badMark['success'] === false);

// =============================================================================
// Section 8: Hybrid Selection Engine — sufficient questions
// =============================================================================
echo "\n--- Section 8: Hybrid selection (sufficient questions) ---\n";

// Create hybrid exam
$hybridExamR = $service->createExam([
    'title'            => 'TEST6_Exam_Hybrid',
    'duration_minutes' => 60,
    'pass_percentage'  => 50,
    'selection_mode'   => 'hybrid',
    'status'           => 'draft',
], $authorId);
$hybridExamId = $hybridExamR['id'];

// Add 3 manual questions from the published pool
$service->addQuestion($hybridExamId, $publishedIds[0]);
$service->addQuestion($hybridExamId, $publishedIds[1]);
$service->addQuestion($hybridExamId, $publishedIds[2]);

// Add a rule: 4 easy questions (we have 10 easy minus 3 manual = 7 remaining easy in cat-A, more than enough)
$hybridRule = $service->addRule($hybridExamId, [
    'programming_language_id' => $langId,
    'category_id'             => $catId,
    'difficulty'              => 'easy',
    'question_count'          => '4',
]);
assertTest('Hybrid rule added', $hybridRule['success'] === true);

// Validate sufficiency
$suffResult = $service->validateRuleSufficiency($hybridExamId, 'hybrid');
assertTest('Hybrid rule sufficiency: valid', $suffResult['valid'] === true, json_encode($suffResult['errors']));

// Preview
$preview = $service->previewHybridSelection($hybridExamId);
assertTest('Hybrid preview succeeds',             $preview['success'] === true);
assertTest('Hybrid preview manual count = 3',     count($preview['manual']) === 3);
assertTest('Hybrid preview random count = 4',     count($preview['random']) === 4);
assertTest('Hybrid preview total = 7',            $preview['total'] === 7);

// Verify no duplicates in preview
$manualQIds = array_map(fn($q) => (int)$q['question_id'], $preview['manual']);
$randomQIds = array_map(fn($q) => (int)$q['id'], $preview['random']);
$allIds     = array_merge($manualQIds, $randomQIds);
assertTest('No duplicate IDs in hybrid preview', count($allIds) === count(array_unique($allIds)));

// =============================================================================
// Section 9: Insufficient question handling (fail-safe)
// =============================================================================
echo "\n--- Section 9: Insufficient question handling ---\n";

// Create exam with rule that asks for more questions than exist
$shortExamR = $service->createExam([
    'title'            => 'TEST6_Exam_Short',
    'duration_minutes' => 30,
    'pass_percentage'  => 50,
    'selection_mode'   => 'random',
    'status'           => 'draft',
], $authorId);
$shortExamId = $shortExamR['id'];

// Add rule asking for 100 hard questions (only 5 exist)
$shortRuleR = $service->addRule($shortExamId, [
    'programming_language_id' => $langId,
    'difficulty'              => 'hard',
    'question_count'          => '100',
]);
assertTest('Short exam rule added', $shortRuleR['success'] === true);

// Validate sufficiency must fail
$shortSuff = $service->validateRuleSufficiency($shortExamId, 'random');
assertTest('Insufficient questions detected', $shortSuff['valid'] === false);
assertTest('Error message mentions available count', !empty($shortSuff['errors']));

// Publishing must fail
$pubResult = $service->publishExam($shortExamId);
assertTest('Publishing fails when insufficient questions', $pubResult['success'] === false);
assertTest('DB status still draft after failed publish',
    Exam::findById($shortExamId)['status'] === 'draft');

// =============================================================================
// Section 10: Category and difficulty filtering
// =============================================================================
echo "\n--- Section 10: Category/difficulty filtering ---\n";

// Count published easy questions in cat-A
$rule = ['programming_language_id' => $langId, 'category_id' => $catId, 'difficulty' => 'easy'];
$countA_easy = ExamQuestionRule::countMatchingPublished($rule, []);
assertTest('Cat-A easy question count = 10', $countA_easy === 10);

// Count published medium in cat-B
$ruleB = ['programming_language_id' => $langId, 'category_id' => $catId2, 'difficulty' => 'medium'];
$countB_med = ExamQuestionRule::countMatchingPublished($ruleB, []);
assertTest('Cat-B medium question count = 5', $countB_med === 5);

// Count hard in cat-A
$ruleHard = ['programming_language_id' => $langId, 'category_id' => $catId, 'difficulty' => 'hard'];
$countHard = ExamQuestionRule::countMatchingPublished($ruleHard, []);
assertTest('Cat-A hard question count = 5', $countHard === 5);

// Exclude some IDs and verify count decreases
$countExcluded = ExamQuestionRule::countMatchingPublished(
    ['programming_language_id' => $langId, 'category_id' => $catId, 'difficulty' => 'easy'],
    [$publishedIds[0], $publishedIds[1], $publishedIds[2]]
);
assertTest('Excluded IDs reduce available count', $countExcluded === 7);

// pickRandom returns correct number
$picked = ExamQuestionRule::pickRandom(
    ['programming_language_id' => $langId, 'category_id' => $catId, 'difficulty' => 'easy'],
    5, []
);
assertTest('pickRandom returns 5 IDs', count($picked) === 5);
assertTest('pickRandom returns unique IDs', count($picked) === count(array_unique($picked)));

// Draft questions not in pickRandom results
$pickedAll = ExamQuestionRule::pickRandom(
    ['programming_language_id' => $langId, 'category_id' => $catId, 'difficulty' => 'easy'],
    10, []
);
$draftInPicked = array_intersect($pickedAll, $draftIds);
assertTest('pickRandom never returns draft questions', empty($draftInPicked));

// =============================================================================
// Section 11: Cross-rule duplicate prevention in hybrid
// =============================================================================
echo "\n--- Section 11: Cross-rule duplicate prevention ---\n";

// Create a hybrid exam with two overlapping rules
$dupExamR = $service->createExam([
    'title'            => 'TEST6_Exam_DupTest',
    'duration_minutes' => 60,
    'pass_percentage'  => 50,
    'selection_mode'   => 'hybrid',
    'status'           => 'draft',
], $authorId);
$dupExamId = $dupExamR['id'];

// Two rules both targeting easy cat-A (pool of 10)
$service->addRule($dupExamId, [
    'programming_language_id' => $langId,
    'category_id'             => $catId,
    'difficulty'              => 'easy',
    'question_count'          => '5',
]);
$service->addRule($dupExamId, [
    'programming_language_id' => $langId,
    'category_id'             => $catId,
    'difficulty'              => 'easy',
    'question_count'          => '3',
]);

// validateRuleSufficiency should account for first rule's consumption
$dupSuff = $service->validateRuleSufficiency($dupExamId, 'hybrid');
assertTest('Two overlapping rules (5+3 from 10): valid', $dupSuff['valid'] === true);

// Preview should have no cross-rule duplicates
$dupPreview = $service->previewHybridSelection($dupExamId);
$allPickedIds = array_map(fn($q) => (int)$q['id'], $dupPreview['random']);
assertTest('No duplicate IDs across rules in preview',
    count($allPickedIds) === count(array_unique($allPickedIds)));

// Two rules exceeding pool (8+5=13 from 10) should fail
$dupExamR2 = $service->createExam([
    'title'            => 'TEST6_Exam_DupFail',
    'duration_minutes' => 60,
    'pass_percentage'  => 50,
    'selection_mode'   => 'random',
    'status'           => 'draft',
], $authorId);
$dupExam2Id = $dupExamR2['id'];
$service->addRule($dupExam2Id, [
    'programming_language_id' => $langId, 'category_id' => $catId, 'difficulty' => 'easy',
    'question_count' => '8',
]);
$service->addRule($dupExam2Id, [
    'programming_language_id' => $langId, 'category_id' => $catId, 'difficulty' => 'easy',
    'question_count' => '5',
]);
$overlapSuff = $service->validateRuleSufficiency($dupExam2Id, 'random');
assertTest('Two overlapping rules (8+5 from 10) correctly fails', $overlapSuff['valid'] === false);

// =============================================================================
// Section 12: Exam publishing validation
// =============================================================================
echo "\n--- Section 12: Exam publishing ---\n";

// Cannot publish with no questions
$emptyExamR = $service->createExam([
    'title'            => 'TEST6_Exam_Empty',
    'duration_minutes' => 30,
    'pass_percentage'  => 50,
    'selection_mode'   => 'manual',
    'status'           => 'draft',
], $authorId);
$emptyPub = $service->publishExam($emptyExamR['id']);
assertTest('Empty exam cannot be published', $emptyPub['success'] === false);
assertTest('Empty exam stays draft', Exam::findById($emptyExamR['id'])['status'] === 'draft');

// Cannot publish archived exam
Exam::archive($emptyExamR['id']);
$archivedPub = $service->publishExam($emptyExamR['id']);
assertTest('Archived exam cannot be published', $archivedPub['success'] === false);

// Cannot publish manual exam if questions are not published
$manualPubExamR = $service->createExam([
    'title'            => 'TEST6_Exam_ManualPub',
    'duration_minutes' => 30,
    'pass_percentage'  => 50,
    'selection_mode'   => 'manual',
    'status'           => 'draft',
], $authorId);
$mpExamId = $manualPubExamR['id'];

// Manually insert a draft question link (bypassing service to test validation layer)
$pdo->prepare("INSERT INTO exam_questions (exam_id, question_id, sort_order) VALUES (:eid, :qid, 1)")
    ->execute(['eid' => $mpExamId, 'qid' => $draftIds[0]]);
$mpPub = $service->publishExam($mpExamId);
assertTest('Manual exam with draft question cannot be published', $mpPub['success'] === false);

// Successful publish
$readyExamR = $service->createExam([
    'title'            => 'TEST6_Exam_Ready',
    'duration_minutes' => 30,
    'pass_percentage'  => 50,
    'selection_mode'   => 'manual',
    'status'           => 'draft',
], $authorId);
$readyExamId = $readyExamR['id'];
$service->addQuestion($readyExamId, $publishedIds[0]);
$service->addQuestion($readyExamId, $publishedIds[1]);

$readyPub = $service->publishExam($readyExamId);
assertTest('Valid manual exam can be published', $readyPub['success'] === true, json_encode($readyPub['errors']));
assertTest('Published exam status = published', Exam::findById($readyExamId)['status'] === 'published');

// Successful publish with rules
$rulesReadyR = $service->createExam([
    'title'            => 'TEST6_Exam_RulesReady',
    'duration_minutes' => 45,
    'pass_percentage'  => 60,
    'selection_mode'   => 'random',
    'status'           => 'draft',
], $authorId);
$rrExamId = $rulesReadyR['id'];
$service->addRule($rrExamId, [
    'programming_language_id' => $langId,
    'category_id'             => $catId,
    'difficulty'              => 'easy',
    'question_count'          => '5',
]);
$rrPub = $service->publishExam($rrExamId);
assertTest('Valid rules-based exam can be published', $rrPub['success'] === true, json_encode($rrPub['errors']));

// =============================================================================
// Section 13: RBAC Authorization
// =============================================================================
echo "\n--- Section 13: RBAC authorization ---\n";

AuthorizationService::clearCache();

// Register test users
$pdo->exec("DELETE FROM users WHERE username LIKE 'exam6_rbac_%'");

$studentR = $auth->register([
    'first_name' => 'E6', 'last_name' => 'Student',
    'username' => 'exam6_rbac_student', 'email' => 'exam6_rbac_student@test.com',
    'password' => 'Password123!', 'password_confirmation' => 'Password123!',
]);
$teacherR = $auth->register([
    'first_name' => 'E6', 'last_name' => 'Teacher',
    'username' => 'exam6_rbac_teacher', 'email' => 'exam6_rbac_teacher@test.com',
    'password' => 'Password123!', 'password_confirmation' => 'Password123!',
]);
$adminR = $auth->register([
    'first_name' => 'E6', 'last_name' => 'Admin',
    'username' => 'exam6_rbac_admin', 'email' => 'exam6_rbac_admin@test.com',
    'password' => 'Password123!', 'password_confirmation' => 'Password123!',
]);

$teacherRoleId = $pdo->query("SELECT id FROM roles WHERE name='teacher' LIMIT 1")->fetchColumn();
$adminRoleId   = $pdo->query("SELECT id FROM roles WHERE name='admin'   LIMIT 1")->fetchColumn();
$studentRoleId = $pdo->query("SELECT id FROM roles WHERE name='student' LIMIT 1")->fetchColumn();

$pdo->prepare('UPDATE user_roles SET role_id = :tr WHERE user_id = :uid AND role_id = :sr')
    ->execute(['tr' => $teacherRoleId, 'uid' => $teacherR['user_id'], 'sr' => $studentRoleId]);
$pdo->prepare('UPDATE user_roles SET role_id = :ar WHERE user_id = :uid AND role_id = :sr')
    ->execute(['ar' => $adminRoleId, 'uid' => $adminR['user_id'], 'sr' => $studentRoleId]);

// Student permissions
$auth->logout();
AuthorizationService::clearCache();
$auth->login('exam6_rbac_student', 'Password123!');
AuthorizationService::clearCache();
$authz = new AuthorizationService();

assertTest('Student: exams.view   = true',   $authz->hasPermission('exams.view'));
assertTest('Student: exams.create = false', !$authz->hasPermission('exams.create'));
assertTest('Student: exams.update = false', !$authz->hasPermission('exams.update'));
assertTest('Student: exams.delete = false', !$authz->hasPermission('exams.delete'));
assertTest('Student: exams.take   = true',   $authz->hasPermission('exams.take'));

// Teacher permissions
$auth->logout();
AuthorizationService::clearCache();
$auth->login('exam6_rbac_teacher', 'Password123!');
AuthorizationService::clearCache();
$authz = new AuthorizationService();

assertTest('Teacher: exams.view   = true',  $authz->hasPermission('exams.view'));
assertTest('Teacher: exams.create = true',  $authz->hasPermission('exams.create'));
assertTest('Teacher: exams.update = true',  $authz->hasPermission('exams.update'));
assertTest('Teacher: exams.delete = true',  $authz->hasPermission('exams.delete'));
assertTest('Teacher: exams.take   = false', !$authz->hasPermission('exams.take'));

// Admin permissions
$auth->logout();
AuthorizationService::clearCache();
$auth->login('exam6_rbac_admin', 'Password123!');
AuthorizationService::clearCache();
$authz = new AuthorizationService();

assertTest('Admin: exams.view   = true', $authz->hasPermission('exams.view'));
assertTest('Admin: exams.create = true', $authz->hasPermission('exams.create'));
assertTest('Admin: exams.update = true', $authz->hasPermission('exams.update'));
assertTest('Admin: exams.delete = true', $authz->hasPermission('exams.delete'));
assertTest('Admin: exams.take   = true', $authz->hasPermission('exams.take'));

// =============================================================================
// Section 14: CSRF validation
// =============================================================================
echo "\n--- Section 14: CSRF validation ---\n";

$token = Csrf::getToken();
assertTest('CSRF token is 64-char hex string', strlen($token) === 64 && ctype_xdigit($token));
assertTest('CSRF validate: valid token',        Csrf::validate($token) === true);
assertTest('CSRF validate: invalid token',      Csrf::validate('bad-token') === false);
assertTest('CSRF validate: empty string',       Csrf::validate('') === false);
assertTest('CSRF validate: null',               Csrf::validate(null) === false);
assertTest('CSRF field() contains token',       strpos(Csrf::field(), $token) !== false);

// =============================================================================
// Section 15: Database integrity (schema unchanged)
// =============================================================================
echo "\n--- Section 15: Database integrity ---\n";

$tables   = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
$required = [
    'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
    'programming_languages', 'categories', 'questions', 'question_options',
    'exams', 'exam_questions', 'exam_question_rules',
    'attempts', 'attempt_questions', 'attempt_answers', 'results', 'audit_logs',
];
foreach ($required as $tbl) {
    assertTest("Table '{$tbl}' still exists (schema unchanged)", in_array($tbl, $tables, true));
}

// Verify key exam columns
$examCols = $pdo->query("SHOW COLUMNS FROM exams")->fetchAll(\PDO::FETCH_COLUMN);
$expectedExamCols = [
    'id', 'programming_language_id', 'created_by', 'title', 'description',
    'selection_mode', 'duration_minutes', 'pass_percentage', 'max_attempts',
    'randomize_questions', 'randomize_options', 'show_result_immediately',
    'show_correct_answers', 'status', 'starts_at', 'ends_at',
    'created_at', 'updated_at',
];
foreach ($expectedExamCols as $col) {
    assertTest("exams.{$col} column exists", in_array($col, $examCols, true));
}

$rulesCols = $pdo->query("SHOW COLUMNS FROM exam_question_rules")->fetchAll(\PDO::FETCH_COLUMN);
$expectedRuleCols = ['id', 'exam_id', 'programming_language_id', 'category_id', 'difficulty', 'question_count', 'mark', 'rule_order'];
foreach ($expectedRuleCols as $col) {
    assertTest("exam_question_rules.{$col} column exists", in_array($col, $rulesCols, true));
}

// =============================================================================
// Global cleanup
// =============================================================================
$auth->logout();
$pdo->exec("DELETE FROM exam_question_rules WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST6_%')");
$pdo->exec("DELETE FROM exam_questions     WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST6_%')");
$pdo->exec("DELETE FROM exams WHERE title LIKE 'TEST6_%'");
$pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE question_text LIKE 'TEST6_%')");
$pdo->exec("DELETE FROM questions WHERE question_text LIKE 'TEST6_%'");
$pdo->exec("DELETE FROM categories WHERE slug LIKE 'test6-%'");
$pdo->exec("DELETE FROM programming_languages WHERE slug LIKE 'test6-%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'exam6_%'");

echo "\n============================================================\n";
echo "Tests Passed: {$passed}\n";
echo "Tests Failed: {$failed}\n";
echo "============================================================\n";

ob_end_flush();

if ($failed > 0) {
    exit(1);
}
exit(0);
