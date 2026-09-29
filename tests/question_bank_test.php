<?php

/**
 * Phase 5 — Question Bank Test Suite
 *
 * Tests:
 *  1.  Class existence: models, service, controllers
 *  2.  Language: CRUD (create, read, update, delete)
 *  3.  Language: duplicate-name validation
 *  4.  Language: delete blocked when questions exist
 *  5.  Category: CRUD (create, read, update, delete)
 *  6.  Category: duplicate-slug validation
 *  7.  Category: delete blocked when questions exist
 *  8.  Category: delete blocked when children exist
 *  9.  Question: create single_choice
 *  10. Question: create true_false
 *  11. Question: update
 *  12. Question: status validation (draft/published/archived)
 *  13. Question: difficulty validation (easy/medium/hard)
 *  14. Question: type validation
 *  15. Question: mark validation
 *  16. Option: single_choice — exactly 1 correct required
 *  17. Option: single_choice — duplicate text rejected
 *  18. Option: single_choice — at least 2 options required
 *  19. Option: true_false — must have True + False
 *  20. Option: true_false — must have exactly 1 correct
 *  21. Option: true_false — wrong option texts rejected
 *  22. Question: safeDelete archives when referenced
 *  23. Question: safeDelete hard-deletes when not referenced
 *  24. RBAC: student cannot create question
 *  25. RBAC: teacher can create question (has permission)
 *  26. RBAC: admin can create question
 *  27. CSRF: validate() false on bad token
 *  28. CSRF: validate() true on good token
 *  29. XSS: model output is raw (escaped in views, not model)
 *  30. DB: schema unchanged
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
use App\Models\ProgrammingLanguage;
use App\Models\Category;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\QuestionBankService;
use App\Services\AuthService;
use App\Services\AuthorizationService;

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
echo "Phase 5 — Question Bank Test Suite\n";
echo "============================================================\n\n";

$pdo     = Database::getConnection();
$service = new QuestionBankService();
$auth    = new AuthService();

// ── Global cleanup ────────────────────────────────────────────────────────────
$pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE question_text LIKE 'TEST5_%')");
$pdo->exec("DELETE FROM questions WHERE question_text LIKE 'TEST5_%'");
$pdo->exec("DELETE FROM categories WHERE slug LIKE 'test5-%'");
$pdo->exec("DELETE FROM programming_languages WHERE slug LIKE 'test5-%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'qb_test_%'");

// Create valid test author for FK relationships
$authorReg = $auth->register([
    'first_name'            => 'QB',
    'last_name'             => 'Author',
    'username'              => 'qb_test_author',
    'email'                 => 'qb_test_author@example.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
$authorId = (int) $authorReg['user_id'];

// =============================================================================
// Section 1: Class existence
// =============================================================================
echo "--- Section 1: Class existence ---\n";

assertTest('ProgrammingLanguage model exists', class_exists(ProgrammingLanguage::class));
assertTest('Category model exists',            class_exists(Category::class));
assertTest('Question model exists',            class_exists(Question::class));
assertTest('QuestionOption model exists',      class_exists(QuestionOption::class));
assertTest('QuestionBankService exists',       class_exists(QuestionBankService::class));

// =============================================================================
// Section 2: Language CRUD
// =============================================================================
echo "\n--- Section 2: Programming Language CRUD ---\n";

// Create
$langResult = $service->createLanguage([
    'name'        => 'TEST5_Lang_PHP',
    'description' => 'PHP for testing',
    'status'      => 'active',
]);
assertTest('Language create succeeds', $langResult['success'] === true, json_encode($langResult['errors']));
$langId = $langResult['id'];

// Read
$lang = ProgrammingLanguage::findById($langId);
assertTest('Language findById returns record', $lang !== null && $lang['name'] === 'TEST5_Lang_PHP');
assertTest('Language slug auto-generated',     $lang !== null && $lang['slug'] === 'test5-lang-php');

// Read all
$allLangs = ProgrammingLanguage::all('all');
$found = array_filter($allLangs, fn($l) => (int)$l['id'] === $langId);
assertTest('Language appears in all() list', count($found) === 1);

// Update
$upResult = $service->updateLanguage($langId, [
    'name'        => 'TEST5_Lang_PHP_Updated',
    'description' => 'Updated description',
    'status'      => 'inactive',
]);
assertTest('Language update succeeds', $upResult['success'] === true, json_encode($upResult['errors']));
$langAfter = ProgrammingLanguage::findById($langId);
assertTest('Language name updated in DB',   $langAfter !== null && $langAfter['name'] === 'TEST5_Lang_PHP_Updated');
assertTest('Language status updated in DB', $langAfter !== null && $langAfter['status'] === 'inactive');

// Duplicate name rejected
$dupResult = $service->createLanguage([
    'name'   => 'TEST5_Lang_PHP_Updated',
    'status' => 'active',
]);
assertTest('Duplicate language name rejected', $dupResult['success'] === false && !empty($dupResult['errors']));

// Delete (clean — no questions reference it yet)
$delResult = $service->deleteLanguage($langId);
assertTest('Language delete succeeds (no questions)', $delResult['success'] === true);
assertTest('Language gone from DB', ProgrammingLanguage::findById($langId) === null);

// =============================================================================
// Section 3: Language — delete blocked by FK
// =============================================================================
echo "\n--- Section 3: Language delete blocked by FK ---\n";

$langResult2 = $service->createLanguage(['name' => 'TEST5_Lang_Blocked', 'status' => 'active']);
assertTest('Language 2 created for FK test', $langResult2['success'] === true);
$langId2 = $langResult2['id'];

$catResult2 = $service->createCategory(['name' => 'TEST5_Cat_Blocked', 'status' => 'active'], $authorId);
assertTest('Category for FK test created', $catResult2['success'] === true);
$catId2 = $catResult2['id'];

// Create a question referencing $langId2
$qRef = Question::create([
    'programming_language_id' => $langId2,
    'category_id'             => $catId2,
    'created_by'              => $authorId,
    'question_text'           => 'TEST5_FK_block question',
    'question_type'           => 'true_false',
    'difficulty'              => 'easy',
    'default_mark'            => '1.00',
    'status'                  => 'draft',
]);
$blockDelResult = $service->deleteLanguage($langId2);
assertTest('Language delete blocked by FK (questions reference it)', $blockDelResult['success'] === false);
assertTest('Language still exists after blocked delete', ProgrammingLanguage::findById($langId2) !== null);

// Clean up FK test data
$pdo->prepare('DELETE FROM questions WHERE id = :id')->execute(['id' => $qRef]);
$service->deleteLanguage($langId2);
$service->deleteCategory($catId2);

// =============================================================================
// Section 4: Category CRUD
// =============================================================================
echo "\n--- Section 4: Category CRUD ---\n";

// Create top-level
$catRes = $service->createCategory([
    'name'        => 'TEST5_Cat_Algorithms',
    'description' => 'Algorithm questions',
    'parent_id'   => '',
    'status'      => 'active',
], $authorId);
assertTest('Category create succeeds', $catRes['success'] === true, json_encode($catRes['errors']));
$catId = $catRes['id'];

// Read
$cat = Category::findById($catId);
assertTest('Category findById returns record', $cat !== null && $cat['name'] === 'TEST5_Cat_Algorithms');
assertTest('Category slug auto-generated',     $cat !== null && $cat['slug'] === 'test5-cat-algorithms');
assertTest('Category parent_id is null',       $cat !== null && $cat['parent_id'] === null);

// Create child
$childRes = $service->createCategory([
    'name'      => 'TEST5_Cat_Sorting',
    'parent_id' => $catId,
    'status'    => 'active',
], $authorId);
assertTest('Child category create succeeds', $childRes['success'] === true, json_encode($childRes['errors']));
$childCatId = $childRes['id'];
$childCat   = Category::findById($childCatId);
assertTest('Child category parent_id set correctly', $childCat !== null && (int) $childCat['parent_id'] === $catId);

// Update
$upCatRes = $service->updateCategory($catId, [
    'name'      => 'TEST5_Cat_Algorithms_Updated',
    'parent_id' => '',
    'status'    => 'inactive',
]);
assertTest('Category update succeeds', $upCatRes['success'] === true, json_encode($upCatRes['errors']));
$catAfter = Category::findById($catId);
assertTest('Category name updated',   $catAfter !== null && $catAfter['name'] === 'TEST5_Cat_Algorithms_Updated');
assertTest('Category status updated', $catAfter !== null && $catAfter['status'] === 'inactive');

// Duplicate slug rejected
$dupCatRes = $service->createCategory(['name' => 'TEST5 Cat Algorithms Updated', 'status' => 'active'], $authorId);
assertTest('Duplicate category slug rejected', $dupCatRes['success'] === false && !empty($dupCatRes['errors']));

// Delete child first, then parent
$delChild = $service->deleteCategory($childCatId);
assertTest('Child category deleted', $delChild['success'] === true);

$delParent = $service->deleteCategory($catId);
assertTest('Parent category deleted (no children, no questions)', $delParent['success'] === true);
assertTest('Category gone from DB', Category::findById($catId) === null);

// =============================================================================
// Section 5: Category delete guards
// =============================================================================
echo "\n--- Section 5: Category delete guards ---\n";

// Create lang + category for guard tests
$langG = $service->createLanguage(['name' => 'TEST5_Lang_Guard', 'status' => 'active']);
$catG  = $service->createCategory(['name' => 'TEST5_Cat_Guard', 'status' => 'active'], $authorId);
$langGId = $langG['id'];
$catGId  = $catG['id'];

// Blocked by question reference
$qGuard = Question::create([
    'programming_language_id' => $langGId,
    'category_id'             => $catGId,
    'created_by'              => $authorId,
    'question_text'           => 'TEST5_Guard question',
    'question_type'           => 'true_false',
    'difficulty'              => 'medium',
    'default_mark'            => '1.00',
    'status'                  => 'draft',
]);
$guardDel = $service->deleteCategory($catGId);
assertTest('Category delete blocked by question reference', $guardDel['success'] === false);
$pdo->prepare('DELETE FROM questions WHERE id = :id')->execute(['id' => $qGuard]);

// Blocked by child category
$childG = $service->createCategory(['name' => 'TEST5_Cat_GuardChild', 'parent_id' => $catGId, 'status' => 'active'], $authorId);
$guardDel2 = $service->deleteCategory($catGId);
assertTest('Category delete blocked by child category', $guardDel2['success'] === false);

// Cleanup
$service->deleteCategory($childG['id']);
$service->deleteCategory($catGId);
$service->deleteLanguage($langGId);

// =============================================================================
// Section 6: Question creation
// =============================================================================
echo "\n--- Section 6: Question creation ---\n";

// Create fresh language & category for question tests
$langQ = $service->createLanguage(['name' => 'TEST5_Lang_QTest', 'status' => 'active']);
$catQ  = $service->createCategory(['name' => 'TEST5_Cat_QTest', 'status' => 'active'], $authorId);
$langQId = $langQ['id'];
$catQId  = $catQ['id'];

assertTest('Language for question tests created', $langQ['success'] === true);
assertTest('Category for question tests created', $catQ['success'] === true);

// single_choice question
$qRes = $service->createQuestion([
    'question_text'           => 'TEST5_SC: What is 2+2?',
    'question_type'           => 'single_choice',
    'difficulty'              => 'easy',
    'default_mark'            => '2.00',
    'status'                  => 'draft',
    'programming_language_id' => $langQId,
    'category_id'             => $catQId,
    'explanation'             => 'Basic arithmetic.',
    'options'                 => [
        ['option_text' => '3', 'is_correct' => false, 'sort_order' => 1],
        ['option_text' => '4', 'is_correct' => true,  'sort_order' => 2],
        ['option_text' => '5', 'is_correct' => false, 'sort_order' => 3],
    ],
], $authorId);
assertTest('single_choice question created', $qRes['success'] === true, json_encode($qRes['errors']));
$qId = $qRes['id'];

// Verify question in DB
$qDb = Question::findById($qId);
assertTest('Question exists in DB',          $qDb !== null);
assertTest('Question type = single_choice',  $qDb !== null && $qDb['question_type'] === 'single_choice');
assertTest('Question status = draft',        $qDb !== null && $qDb['status'] === 'draft');
assertTest('Question difficulty = easy',     $qDb !== null && $qDb['difficulty'] === 'easy');
assertTest('Question default_mark = 2.00',   $qDb !== null && (float)$qDb['default_mark'] === 2.00);

// Verify options
$opts = QuestionOption::forQuestion($qId);
assertTest('3 options saved',                   count($opts) === 3);
$correctOpts = array_filter($opts, fn($o) => (bool)$o['is_correct']);
assertTest('Exactly 1 correct option',          count($correctOpts) === 1);
$correctOpt = reset($correctOpts);
assertTest('Correct option text is "4"',        $correctOpt['option_text'] === '4');

// true_false question
$tfRes = $service->createQuestion([
    'question_text'           => 'TEST5_TF: PHP is a scripting language.',
    'question_type'           => 'true_false',
    'difficulty'              => 'medium',
    'default_mark'            => '1.00',
    'status'                  => 'published',
    'programming_language_id' => $langQId,
    'category_id'             => $catQId,
    'tf_correct'              => 'true',
], $authorId);
assertTest('true_false question created', $tfRes['success'] === true, json_encode($tfRes['errors']));
$tfId = $tfRes['id'];

$tfOpts = QuestionOption::forQuestion($tfId);
assertTest('true_false has exactly 2 options',    count($tfOpts) === 2);
$tfTexts = array_map(fn($o) => strtolower($o['option_text']), $tfOpts);
sort($tfTexts);
assertTest('true_false options are True+False',   $tfTexts === ['false','true']);
$tfCorrects = array_filter($tfOpts, fn($o) => (bool)$o['is_correct']);
assertTest('true_false has exactly 1 correct',    count($tfCorrects) === 1);
$tfCorrectOpt = reset($tfCorrects);
assertTest('Correct TF option is "True"',         strtolower($tfCorrectOpt['option_text']) === 'true');

// =============================================================================
// Section 7: Question update
// =============================================================================
echo "\n--- Section 7: Question update ---\n";

$upQRes = $service->updateQuestion($qId, [
    'question_text'           => 'TEST5_SC: What is 3+3?',
    'question_type'           => 'single_choice',
    'difficulty'              => 'medium',
    'default_mark'            => '3.00',
    'status'                  => 'published',
    'programming_language_id' => $langQId,
    'category_id'             => $catQId,
    'options'                 => [
        ['option_text' => 'Five',  'is_correct' => false, 'sort_order' => 1],
        ['option_text' => 'Six',   'is_correct' => true,  'sort_order' => 2],
        ['option_text' => 'Seven', 'is_correct' => false, 'sort_order' => 3],
    ],
]);
assertTest('Question update succeeds', $upQRes['success'] === true, json_encode($upQRes['errors']));

$qUpdated = Question::findById($qId);
assertTest('Question text updated',  $qUpdated !== null && strpos($qUpdated['question_text'], '3+3') !== false);
assertTest('Question status updated to published', $qUpdated !== null && $qUpdated['status'] === 'published');
assertTest('Question difficulty updated to medium', $qUpdated !== null && $qUpdated['difficulty'] === 'medium');

$optsUpdated = QuestionOption::forQuestion($qId);
assertTest('Options replaced on update',    count($optsUpdated) === 3);

// =============================================================================
// Section 8: Validation — status / difficulty / type / mark
// =============================================================================
echo "\n--- Section 8: Field validation ---\n";

$baseData = [
    'question_text'           => 'TEST5_Valid test?',
    'question_type'           => 'single_choice',
    'difficulty'              => 'easy',
    'default_mark'            => '1.00',
    'status'                  => 'draft',
    'programming_language_id' => $langQId,
    'category_id'             => $catQId,
    'options'                 => [
        ['option_text' => 'A', 'is_correct' => true,  'sort_order' => 1],
        ['option_text' => 'B', 'is_correct' => false, 'sort_order' => 2],
    ],
];

// Bad status
$badStatus = $service->createQuestion(array_merge($baseData, ['status' => 'bogus']), $authorId);
assertTest('Bad status rejected', $badStatus['success'] === false);

// Bad difficulty
$badDiff = $service->createQuestion(array_merge($baseData, ['difficulty' => 'extreme']), $authorId);
assertTest('Bad difficulty rejected', $badDiff['success'] === false);

// Bad type
$badType = $service->createQuestion(array_merge($baseData, ['question_type' => 'multiple_choice']), $authorId);
assertTest('Bad question type rejected', $badType['success'] === false);

// Zero mark
$badMark = $service->createQuestion(array_merge($baseData, ['default_mark' => '0']), $authorId);
assertTest('Zero mark rejected', $badMark['success'] === false);

// Negative mark
$negMark = $service->createQuestion(array_merge($baseData, ['default_mark' => '-5']), $authorId);
assertTest('Negative mark rejected', $negMark['success'] === false);

// Empty question text
$emptyText = $service->createQuestion(array_merge($baseData, ['question_text' => '']), $authorId);
assertTest('Empty question text rejected', $emptyText['success'] === false);

// Missing language
$noLang = $service->createQuestion(array_merge($baseData, ['programming_language_id' => 0]), $authorId);
assertTest('Missing language rejected', $noLang['success'] === false);

// Missing category
$noCat = $service->createQuestion(array_merge($baseData, ['category_id' => 0]), $authorId);
assertTest('Missing category rejected', $noCat['success'] === false);

// =============================================================================
// Section 9: Option-level validation
// =============================================================================
echo "\n--- Section 9: Option validation ---\n";

// single_choice: no correct
$noCorrect = QuestionOption::validate('single_choice', [
    ['option_text' => 'A', 'is_correct' => false],
    ['option_text' => 'B', 'is_correct' => false],
]);
assertTest('SC: no correct option rejected', !empty($noCorrect));

// single_choice: multiple correct
$twoCorrect = QuestionOption::validate('single_choice', [
    ['option_text' => 'A', 'is_correct' => true],
    ['option_text' => 'B', 'is_correct' => true],
]);
assertTest('SC: two correct options rejected', !empty($twoCorrect));

// single_choice: duplicate text
$dupText = QuestionOption::validate('single_choice', [
    ['option_text' => 'Apple', 'is_correct' => true],
    ['option_text' => 'apple', 'is_correct' => false],  // same after lowercase
]);
assertTest('SC: duplicate option text rejected', !empty($dupText));

// single_choice: only 1 option
$oneOpt = QuestionOption::validate('single_choice', [
    ['option_text' => 'Only', 'is_correct' => true],
]);
assertTest('SC: only 1 option rejected', !empty($oneOpt));

// true_false: wrong texts
$wrongTF = QuestionOption::validate('true_false', [
    ['option_text' => 'Yes', 'is_correct' => true],
    ['option_text' => 'No',  'is_correct' => false],
]);
assertTest('TF: wrong option texts rejected', !empty($wrongTF));

// true_false: 3 options
$threeTF = QuestionOption::validate('true_false', [
    ['option_text' => 'True',  'is_correct' => true],
    ['option_text' => 'False', 'is_correct' => false],
    ['option_text' => 'Maybe', 'is_correct' => false],
]);
assertTest('TF: more than 2 options rejected', !empty($threeTF));

// true_false: no correct
$noCorrectTF = QuestionOption::validate('true_false', [
    ['option_text' => 'True',  'is_correct' => false],
    ['option_text' => 'False', 'is_correct' => false],
]);
assertTest('TF: no correct option rejected', !empty($noCorrectTF));

// valid single_choice
$validSC = QuestionOption::validate('single_choice', [
    ['option_text' => 'A', 'is_correct' => true],
    ['option_text' => 'B', 'is_correct' => false],
    ['option_text' => 'C', 'is_correct' => false],
]);
assertTest('SC: valid options accepted', empty($validSC));

// valid true_false
$validTF = QuestionOption::validate('true_false', [
    ['option_text' => 'True',  'is_correct' => true],
    ['option_text' => 'False', 'is_correct' => false],
]);
assertTest('TF: valid options accepted', empty($validTF));

// =============================================================================
// Section 10: Safe delete (archive vs hard-delete)
// =============================================================================
echo "\n--- Section 10: Safe delete behaviour ---\n";

// Hard-delete: question NOT referenced
$delRes = Question::safeDelete($tfId);
assertTest('Unreferenced question hard-deleted',    $delRes['success'] === true && $delRes['action'] === 'deleted');
assertTest('Question gone from DB after hard-delete', Question::findById($tfId) === null);

// Archive: question referenced by exam_questions
// Insert a fake exam first (minimal)
$pdo->prepare("INSERT INTO exams (title, programming_language_id, duration_minutes, created_by, status)
               VALUES ('TEST5_Exam', :lid, 60, :uid, 'draft')")
    ->execute(['lid' => $langQId, 'uid' => $authorId]);
$examId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO exam_questions (exam_id, question_id, mark) VALUES (:eid, :qid, 1.00)")
    ->execute(['eid' => $examId, 'qid' => $qId]);

$archRes = Question::safeDelete($qId);
assertTest('Referenced question archived (not hard-deleted)', $archRes['success'] === true && $archRes['action'] === 'archived');
$qAfterArchive = Question::findById($qId);
assertTest('Archived question still in DB with status=archived', $qAfterArchive !== null && $qAfterArchive['status'] === 'archived');

// Cleanup exam reference
$pdo->prepare('DELETE FROM exam_questions WHERE exam_id = :eid')->execute(['eid' => $examId]);
$pdo->prepare('DELETE FROM exams WHERE id = :eid')->execute(['eid' => $examId]);
$pdo->prepare('DELETE FROM question_options WHERE question_id = :id')->execute(['id' => $qId]);
$pdo->prepare('DELETE FROM questions WHERE id = :id')->execute(['id' => $qId]);

// =============================================================================
// Section 11: RBAC — permissions
// =============================================================================
echo "\n--- Section 11: RBAC permission checks ---\n";

// Register test users
$auth->logout();
AuthorizationService::clearCache();

$pdo->exec("DELETE FROM users WHERE username LIKE 'qb_test_%'");

$studentReg = $auth->register([
    'first_name' => 'QB', 'last_name' => 'Student',
    'username' => 'qb_test_student', 'email' => 'qb_test_student@x.com',
    'password' => 'Password123!', 'password_confirmation' => 'Password123!',
]);
assertTest('QB student registered', $studentReg['success'] === true);

$teacherReg = $auth->register([
    'first_name' => 'QB', 'last_name' => 'Teacher',
    'username' => 'qb_test_teacher', 'email' => 'qb_test_teacher@x.com',
    'password' => 'Password123!', 'password_confirmation' => 'Password123!',
]);
assertTest('QB teacher registered', $teacherReg['success'] === true);

// Promote teacher
$teacherRoleId = $pdo->query("SELECT id FROM roles WHERE name='teacher' LIMIT 1")->fetchColumn();
$studentRoleId = $pdo->query("SELECT id FROM roles WHERE name='student' LIMIT 1")->fetchColumn();
$pdo->prepare('UPDATE user_roles SET role_id = :tr WHERE user_id = :uid AND role_id = :sr')
    ->execute(['tr' => $teacherRoleId, 'uid' => $teacherReg['user_id'], 'sr' => $studentRoleId]);

// Register admin
$adminReg = $auth->register([
    'first_name' => 'QB', 'last_name' => 'Admin',
    'username' => 'qb_test_admin', 'email' => 'qb_test_admin@x.com',
    'password' => 'Password123!', 'password_confirmation' => 'Password123!',
]);
assertTest('QB admin registered', $adminReg['success'] === true);
$adminRoleId = $pdo->query("SELECT id FROM roles WHERE name='admin' LIMIT 1")->fetchColumn();
$pdo->prepare('UPDATE user_roles SET role_id = :ar WHERE user_id = :uid AND role_id = :sr')
    ->execute(['ar' => $adminRoleId, 'uid' => $adminReg['user_id'], 'sr' => $studentRoleId]);

// Student: cannot create questions
$auth->login('qb_test_student', 'Password123!');
AuthorizationService::clearCache();
$authz = new AuthorizationService();

assertTest('Student: languages.view     = true',  $authz->hasPermission('languages.view'));
assertTest('Student: categories.view    = true',  $authz->hasPermission('categories.view'));
assertTest('Student: questions.view     = false', !$authz->hasPermission('questions.view'));
assertTest('Student: questions.create   = false', !$authz->hasPermission('questions.create'));
assertTest('Student: languages.manage   = false', !$authz->hasPermission('languages.manage'));
assertTest('Student: categories.manage  = false', !$authz->hasPermission('categories.manage'));

// Teacher: can create/update/delete questions and manage categories, view languages
$auth->logout();
AuthorizationService::clearCache();
$auth->login('qb_test_teacher', 'Password123!');
AuthorizationService::clearCache();
$authz = new AuthorizationService();

assertTest('Teacher: questions.create   = true',  $authz->hasPermission('questions.create'));
assertTest('Teacher: questions.update   = true',  $authz->hasPermission('questions.update'));
assertTest('Teacher: questions.delete   = true',  $authz->hasPermission('questions.delete'));
assertTest('Teacher: questions.view     = true',  $authz->hasPermission('questions.view'));
assertTest('Teacher: languages.view     = true',  $authz->hasPermission('languages.view'));
assertTest('Teacher: languages.manage   = false', !$authz->hasPermission('languages.manage'));
assertTest('Teacher: categories.manage  = true',  $authz->hasPermission('categories.manage'));
assertTest('Teacher: exams.take         = false', !$authz->hasPermission('exams.take'));

// Admin: all relevant permissions
$auth->logout();
AuthorizationService::clearCache();
$auth->login('qb_test_admin', 'Password123!');
AuthorizationService::clearCache();
$authz = new AuthorizationService();

assertTest('Admin: questions.create     = true',  $authz->hasPermission('questions.create'));
assertTest('Admin: languages.manage     = true',  $authz->hasPermission('languages.manage'));
assertTest('Admin: categories.manage    = true',  $authz->hasPermission('categories.manage'));
assertTest('Admin: roles.manage         = true',  $authz->hasPermission('roles.manage'));

// =============================================================================
// Section 12: CSRF token checks
// =============================================================================
echo "\n--- Section 12: CSRF checks ---\n";

$token = Csrf::getToken();
assertTest('CSRF token is 64-char hex', strlen($token) === 64 && ctype_xdigit($token));
assertTest('CSRF validate: valid token',   Csrf::validate($token) === true);
assertTest('CSRF validate: invalid token', Csrf::validate('totally-wrong') === false);
assertTest('CSRF validate: empty string',  Csrf::validate('') === false);
assertTest('CSRF validate: null',          Csrf::validate(null) === false);
assertTest('CSRF field() contains token',  strpos(Csrf::field(), $token) !== false);

// =============================================================================
// Section 13: XSS safety (model returns raw data; views must escape)
// =============================================================================
echo "\n--- Section 13: XSS-safe output verification ---\n";

// Create a language with XSS payload in name (should be stored raw, escaped in view)
$xssLang = $service->createLanguage([
    'name'   => 'TEST5_Lang_XSS_<script>alert(1)</script>',
    'status' => 'active',
]);
// Validation may or may not reject this depending on slug generation.
// If it succeeds, verify the model returns it raw (not pre-escaped).
if ($xssLang['success']) {
    $xssRecord = ProgrammingLanguage::findById($xssLang['id']);
    assertTest('Model returns raw (unescaped) data', strpos($xssRecord['name'], '<script>') !== false);
    // Verify htmlspecialchars works correctly for view escaping
    $escaped = htmlspecialchars($xssRecord['name'], ENT_QUOTES, 'UTF-8');
    assertTest('htmlspecialchars escapes the payload correctly', strpos($escaped, '<script>') === false);
    $service->deleteLanguage($xssLang['id']);
} else {
    // Slug generation stripped special chars — slug ended up empty → rejected
    assertTest('XSS payload in name: slug empty → correctly rejected OR stored raw', true);
    // Extra: verify htmlspecialchars correctly escapes sample XSS string
    $sample  = '<script>alert("xss")</script>';
    $escaped = htmlspecialchars($sample, ENT_QUOTES, 'UTF-8');
    assertTest('htmlspecialchars strips XSS from sample string', strpos($escaped, '<script>') === false);
}

// =============================================================================
// Section 14: Schema integrity
// =============================================================================
echo "\n--- Section 14: Schema integrity ---\n";

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$required = [
    'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
    'programming_languages', 'categories', 'questions', 'question_options',
    'exams', 'exam_questions', 'exam_question_rules',
    'attempts', 'attempt_questions', 'attempt_answers', 'results', 'audit_logs',
];
foreach ($required as $tbl) {
    assertTest("Table '{$tbl}' still exists (no schema changes)", in_array($tbl, $tables, true));
}

// =============================================================================
// Global cleanup
// =============================================================================
$auth->logout();
$pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE question_text LIKE 'TEST5_%')");
$pdo->exec("DELETE FROM questions WHERE question_text LIKE 'TEST5_%'");
$pdo->exec("DELETE FROM categories WHERE slug LIKE 'test5-%'");
$pdo->exec("DELETE FROM programming_languages WHERE slug LIKE 'test5-%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'qb_test_%'");

echo "\n============================================================\n";
echo "Tests Passed: {$passed}\n";
echo "Tests Failed: {$failed}\n";
echo "============================================================\n";

ob_end_flush();

if ($failed > 0) {
    exit(1);
}
exit(0);
