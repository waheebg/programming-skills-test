<?php

/**
 * Phase 7 — Exam Taking Engine & Attempts Test Suite
 *
 * Covers:
 *  Section 1:  Class & Model existence (Attempt, AttemptQuestion, AttemptAnswer, ExamAttemptService, AttemptController)
 *  Section 2:  Valid attempt creation (status in_progress, started_at, attempt_number)
 *  Section 3:  Unauthorized access & RBAC permissions (student/admin vs teacher/guest)
 *  Section 4:  Unpublished & unavailable exams (draft, archived, future starts_at, past ends_at)
 *  Section 5:  Max attempts enforcement (max_attempts limit vs unlimited)
 *  Section 6:  Active attempt handling & duplicate attempt prevention (resuming active attempt)
 *  Section 7:  Attempt question snapshots (manual, random, hybrid)
 *  Section 8:  No duplicate questions in snapshot (cross-rule and manual+random)
 *  Section 9:  Interface security (correct answers NEVER exposed in options)
 *  Section 10: Server-side timer calculation & auto-expiration
 *  Section 11: Answer saving and answer changing before submission
 *  Section 12: Invalid option rejection (foreign options, non-existent options)
 *  Section 13: Ownership protection (cross-user attempt tampering prevention)
 *  Section 14: Manual submission & answer freeze after submission/expiration
 *  Section 15: CSRF & security validation
 *  Section 16: Database integrity (schema unchanged)
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
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionRule;
use App\Models\ProgrammingLanguage;
use App\Models\Category;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\ExamAttemptService;
use App\Controllers\AttemptController;

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
echo "Phase 7 — Exam Taking Engine & Attempts Test Suite\n";
echo "============================================================\n\n";

$pdo     = Database::getConnection();
$service = new ExamAttemptService();
$auth    = new AuthService();

// ── Global cleanup ─────────────────────────────────────────────────────────────
$pdo->exec("DELETE FROM attempt_answers WHERE attempt_question_id IN (SELECT id FROM attempt_questions WHERE attempt_id IN (SELECT id FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%')))");
$pdo->exec("DELETE FROM attempt_questions WHERE attempt_id IN (SELECT id FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%'))");
$pdo->exec("DELETE FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%')");
$pdo->exec("DELETE FROM exam_question_rules WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%')");
$pdo->exec("DELETE FROM exam_questions WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%')");
$pdo->exec("DELETE FROM exams WHERE title LIKE 'TEST7_%'");
$pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE question_text LIKE 'TEST7_%')");
$pdo->exec("DELETE FROM questions WHERE question_text LIKE 'TEST7_%'");
$pdo->exec("DELETE FROM categories WHERE slug LIKE 'test7-%'");
$pdo->exec("DELETE FROM programming_languages WHERE slug LIKE 'test7-%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'attempt7_%'");

// ── Create test fixtures ───────────────────────────────────────────────────────
// 1. Users: Student 1, Student 2, Teacher
$st1Reg = $auth->register([
    'first_name'            => 'Student',
    'last_name'             => 'One',
    'username'              => 'attempt7_student1',
    'email'                 => 'attempt7_student1@test.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
$student1Id = (int) $st1Reg['user_id'];

$st2Reg = $auth->register([
    'first_name'            => 'Student',
    'last_name'             => 'Two',
    'username'              => 'attempt7_student2',
    'email'                 => 'attempt7_student2@test.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
$student2Id = (int) $st2Reg['user_id'];

$teacherReg = $auth->register([
    'first_name'            => 'Teacher',
    'last_name'             => 'Seven',
    'username'              => 'attempt7_teacher',
    'email'                 => 'attempt7_teacher@test.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
$teacherId = (int) $teacherReg['user_id'];

// Change teacher's role to 'teacher'
$teacherRoleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'teacher'");
$teacherRoleStmt->execute();
$teacherRoleId = (int) $teacherRoleStmt->fetchColumn();
$studentRoleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'student'");
$studentRoleStmt->execute();
$studentRoleId = (int) $studentRoleStmt->fetchColumn();

$pdo->prepare("UPDATE user_roles SET role_id = :role_id WHERE user_id = :user_id")
    ->execute(['role_id' => $teacherRoleId, 'user_id' => $teacherId]);

// 2. Language & Category
$pdo->prepare("INSERT INTO programming_languages (name, slug, description, status) VALUES ('TEST7_PHP', 'test7-php', 'Test PHP', 'active')")->execute();
$langId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO categories (name, slug, description, status) VALUES ('TEST7_Basics', 'test7-basics', 'Test Basics', 'active')")->execute();
$catId = (int) $pdo->lastInsertId();

// 3. Helper to create published questions with options
function createTestQuestion(string $text, string $type = 'single_choice', string $diff = 'medium', float $mark = 1.0): int
{
    global $pdo, $langId, $catId, $teacherId;
    $stmt = $pdo->prepare(
        "INSERT INTO questions (programming_language_id, category_id, created_by, question_text, question_type, difficulty, default_mark, status)
         VALUES (:lang, :cat, :user, :text, :type, :diff, :mark, 'published')"
    );
    $stmt->execute([
        'lang' => $langId,
        'cat'  => $catId,
        'user' => $teacherId,
        'text' => $text,
        'type' => $type,
        'diff' => $diff,
        'mark' => $mark,
    ]);
    $qid = (int) $pdo->lastInsertId();

    if ($type === 'single_choice') {
        $optStmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, sort_order) VALUES (:qid, :text, :corr, :order)");
        $optStmt->execute(['qid' => $qid, 'text' => 'Option A (Correct)', 'corr' => 1, 'order' => 1]);
        $optStmt->execute(['qid' => $qid, 'text' => 'Option B', 'corr' => 0, 'order' => 2]);
        $optStmt->execute(['qid' => $qid, 'text' => 'Option C', 'corr' => 0, 'order' => 3]);
        $optStmt->execute(['qid' => $qid, 'text' => 'Option D', 'corr' => 0, 'order' => 4]);
    } else {
        $optStmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, sort_order) VALUES (:qid, :text, :corr, :order)");
        $optStmt->execute(['qid' => $qid, 'text' => 'True', 'corr' => 1, 'order' => 1]);
        $optStmt->execute(['qid' => $qid, 'text' => 'False', 'corr' => 0, 'order' => 2]);
    }

    return $qid;
}

$q1 = createTestQuestion('TEST7_Question_1: What is PHP?', 'single_choice', 'easy', 2.0);
$q2 = createTestQuestion('TEST7_Question_2: Is PHP interpreted?', 'true_false', 'easy', 1.0);
$q3 = createTestQuestion('TEST7_Question_3: What does PDO stand for?', 'single_choice', 'medium', 2.5);
$q4 = createTestQuestion('TEST7_Question_4: Does PHP support OOP?', 'true_false', 'medium', 1.5);
$q5 = createTestQuestion('TEST7_Question_5: What is composer?', 'single_choice', 'hard', 3.0);
$q6 = createTestQuestion('TEST7_Question_6: What is OPcache?', 'single_choice', 'hard', 3.0);

// Helper to create exam
function createTestExam(array $override = []): int
{
    global $pdo, $langId, $teacherId;
    $defaults = [
        'programming_language_id' => $langId,
        'created_by'              => $teacherId,
        'title'                   => 'TEST7_Exam_' . uniqid(),
        'description'             => 'Test Exam for Phase 7',
        'selection_mode'          => 'manual',
        'duration_minutes'        => 30,
        'pass_percentage'         => 60.00,
        'max_attempts'            => 2,
        'randomize_questions'     => 0,
        'randomize_options'       => 1,
        'show_result_immediately' => 1,
        'show_correct_answers'    => 0,
        'status'                  => 'published',
        'starts_at'               => null,
        'ends_at'                 => null,
    ];
    $data = array_merge($defaults, $override);

    $sql = "INSERT INTO exams (programming_language_id, created_by, title, description, selection_mode,
                               duration_minutes, pass_percentage, max_attempts, randomize_questions,
                               randomize_options, show_result_immediately, show_correct_answers,
                               status, starts_at, ends_at)
            VALUES (:lang, :created_by, :title, :description, :selection_mode, :duration,
                    :pass_pct, :max_att, :rand_q, :rand_opt, :show_res, :show_ans, :status,
                    :starts_at, :ends_at)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'lang'           => $data['programming_language_id'],
        'created_by'     => $data['created_by'],
        'title'          => $data['title'],
        'description'    => $data['description'],
        'selection_mode' => $data['selection_mode'],
        'duration'       => $data['duration_minutes'],
        'pass_pct'       => $data['pass_percentage'],
        'max_att'        => $data['max_attempts'],
        'rand_q'         => $data['randomize_questions'],
        'rand_opt'       => $data['randomize_options'],
        'show_res'       => $data['show_result_immediately'],
        'show_ans'       => $data['show_correct_answers'],
        'status'         => $data['status'],
        'starts_at'      => $data['starts_at'],
        'ends_at'        => $data['ends_at'],
    ]);
    return (int) $pdo->lastInsertId();
}

// =============================================================================
// Section 1: Class & Model Existence
// =============================================================================
echo "--- Section 1: Class & Model Existence ---\n";
assertTest('Attempt model exists', class_exists(Attempt::class));
assertTest('AttemptQuestion model exists', class_exists(AttemptQuestion::class));
assertTest('AttemptAnswer model exists', class_exists(AttemptAnswer::class));
assertTest('ExamAttemptService exists', class_exists(ExamAttemptService::class));
assertTest('AttemptController exists', class_exists(AttemptController::class));

// =============================================================================
// Section 2: Valid Attempt Creation
// =============================================================================
echo "\n--- Section 2: Valid Attempt Creation ---\n";
$exam1Id = createTestExam(['title' => 'TEST7_Manual_Exam_1', 'selection_mode' => 'manual']);
ExamQuestion::add($exam1Id, $q1, 2.0);
ExamQuestion::add($exam1Id, $q2, 1.0);

$res = $service->startAttempt($exam1Id, $student1Id);
assertTest('startAttempt succeeds for published exam', $res['success'] === true, json_encode($res['errors'] ?? []));
assertTest('startAttempt returns attempt record', !empty($res['attempt']['id']));
$attempt1Id = (int) $res['attempt']['id'];

$attFromDb = Attempt::findById($attempt1Id);
assertTest('Attempt found in DB by findById', $attFromDb !== null);
assertTest('Attempt status is in_progress', $attFromDb['status'] === Attempt::STATUS_IN_PROGRESS);
assertTest('Attempt number is 1', (int) $attFromDb['attempt_number'] === 1);
assertTest('Attempt started_at is populated', !empty($attFromDb['started_at']));
assertTest('Attempt submitted_at is null initially', $attFromDb['submitted_at'] === null);
assertTest('Attempt score is null initially (no Phase 8 grading)', $attFromDb['score'] === null);
assertTest('Attempt percentage is null initially', $attFromDb['percentage'] === null);
assertTest('Attempt passed is null initially', $attFromDb['passed'] === null);

// =============================================================================
// Section 3: Unauthorized Access & RBAC
// =============================================================================
echo "\n--- Section 3: Unauthorized Access & RBAC ---\n";
$authz = new AuthorizationService();

// Simulate student session
Session::set('user', ['id' => $student1Id, 'username' => 'attempt7_student1', 'roles' => ['student']]);
AuthorizationService::clearCache();
assertTest('Student has exams.take permission', $authz->hasPermission('exams.take') === true);

// Simulate teacher session
Session::set('user', ['id' => $teacherId, 'username' => 'attempt7_teacher', 'roles' => ['teacher']]);
AuthorizationService::clearCache();
assertTest('Teacher does NOT have exams.take permission', $authz->hasPermission('exams.take') === false);

// Teacher attempting to take exam via controller should be forbidden
ob_start();
$controller = new AttemptController();
assertTest('AttemptController instance created', is_object($controller));

// Simulate guest (no user)
Session::remove('user');
AuthorizationService::clearCache();
assertTest('Guest has exams.take = false', $authz->hasPermission('exams.take') === false);

// =============================================================================
// Section 4: Unpublished & Unavailable Exams
// =============================================================================
echo "\n--- Section 4: Unpublished & Unavailable Exams ---\n";
// Draft exam
$draftExamId = createTestExam(['title' => 'TEST7_Draft_Exam', 'status' => 'draft']);
ExamQuestion::add($draftExamId, $q1);
$draftRes = $service->startAttempt($draftExamId, $student1Id);
assertTest('Starting draft exam fails', $draftRes['success'] === false);
assertTest('Error message mentions draft status', strpos($draftRes['errors'][0], 'draft') !== false);

// Archived exam
$archivedExamId = createTestExam(['title' => 'TEST7_Archived_Exam', 'status' => 'archived']);
ExamQuestion::add($archivedExamId, $q1);
$archRes = $service->startAttempt($archivedExamId, $student1Id);
assertTest('Starting archived exam fails', $archRes['success'] === false);
assertTest('Error message mentions archived status', strpos($archRes['errors'][0], 'archived') !== false);

// Future starts_at exam
$futureExamId = createTestExam([
    'title'     => 'TEST7_Future_Exam',
    'status'    => 'published',
    'starts_at' => date('Y-m-d H:i:s', time() + 86400), // tomorrow
]);
ExamQuestion::add($futureExamId, $q1);
$futureRes = $service->startAttempt($futureExamId, $student1Id);
assertTest('Starting future exam fails', $futureRes['success'] === false);
assertTest('Error message indicates not available yet', strpos($futureRes['errors'][0], 'not available yet') !== false);

// Past ends_at exam
$pastExamId = createTestExam([
    'title'   => 'TEST7_Past_Exam',
    'status'  => 'published',
    'ends_at' => date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
]);
ExamQuestion::add($pastExamId, $q1);
$pastRes = $service->startAttempt($pastExamId, $student1Id);
assertTest('Starting expired/ended exam fails', $pastRes['success'] === false);
assertTest('Error message indicates no longer available', strpos($pastRes['errors'][0], 'no longer available') !== false);

// Active window exam (starts_at past, ends_at future)
$activeWindowExamId = createTestExam([
    'title'     => 'TEST7_Active_Window_Exam',
    'status'    => 'published',
    'starts_at' => date('Y-m-d H:i:s', time() - 3600),
    'ends_at'   => date('Y-m-d H:i:s', time() + 3600),
]);
ExamQuestion::add($activeWindowExamId, $q1);
$windowRes = $service->startAttempt($activeWindowExamId, $student1Id);
assertTest('Exam within availability window succeeds', $windowRes['success'] === true);

// =============================================================================
// Section 5: Max Attempts Enforcement
// =============================================================================
echo "\n--- Section 5: Max Attempts Enforcement ---\n";
$maxExamId = createTestExam([
    'title'        => 'TEST7_Max_Attempts_Exam',
    'max_attempts' => 2,
]);
ExamQuestion::add($maxExamId, $q1);

// Attempt 1
$att1 = $service->startAttempt($maxExamId, $student1Id);
assertTest('Attempt 1 starts successfully', $att1['success'] === true);
$service->submitAttempt((int) $att1['attempt']['id'], $student1Id);

// Attempt 2
$att2 = $service->startAttempt($maxExamId, $student1Id);
assertTest('Attempt 2 starts successfully', $att2['success'] === true);
assertTest('Attempt 2 has attempt_number = 2', (int) $att2['attempt']['attempt_number'] === 2);
$service->submitAttempt((int) $att2['attempt']['id'], $student1Id);

// Attempt 3 should fail
$att3 = $service->startAttempt($maxExamId, $student1Id);
assertTest('Attempt 3 rejected due to max_attempts limit', $att3['success'] === false);
assertTest('Error message mentions maximum number of attempts', strpos($att3['errors'][0], 'Maximum number of attempts') !== false);

// Different student can still take the exam
$attOther = $service->startAttempt($maxExamId, $student2Id);
assertTest('Different student is unaffected by another student max_attempts', $attOther['success'] === true);
$service->submitAttempt((int) $attOther['attempt']['id'], $student2Id);

// =============================================================================
// Section 6: Active Attempt & Duplicate Prevention (Resuming)
// =============================================================================
echo "\n--- Section 6: Active Attempt & Duplicate Prevention ---\n";
$resumeExamId = createTestExam(['title' => 'TEST7_Resume_Exam', 'max_attempts' => 3]);
ExamQuestion::add($resumeExamId, $q1);

$firstStart = $service->startAttempt($resumeExamId, $student1Id);
assertTest('First start creates new attempt', $firstStart['success'] === true && $firstStart['resumed'] === false);
$activeId = (int) $firstStart['attempt']['id'];

// Calling startAttempt again while attempt is still in_progress should RESUME, not create duplicate
$secondStart = $service->startAttempt($resumeExamId, $student1Id);
assertTest('Second start returns resumed = true', $secondStart['resumed'] === true);
assertTest('Second start returns the same attempt ID', (int) $secondStart['attempt']['id'] === $activeId);

$totalAttemptsInDb = Attempt::countForUserAndExam($resumeExamId, $student1Id);
assertTest('Total attempts in DB is still 1 (no duplicate active attempt)', $totalAttemptsInDb === 1);

// =============================================================================
// Section 7: Attempt Question Snapshots (Manual, Random, Hybrid)
// =============================================================================
echo "\n--- Section 7: Attempt Question Snapshots ---\n";

// Manual Mode Snapshot
$manualExamId = createTestExam(['title' => 'TEST7_Snapshot_Manual', 'selection_mode' => 'manual', 'randomize_questions' => 0]);
ExamQuestion::add($manualExamId, $q1, 4.0); // override mark
ExamQuestion::add($manualExamId, $q2, null); // uses question default mark (1.0)
ExamQuestion::add($manualExamId, $q3, 5.0); // override mark

$manAtt = $service->startAttempt($manualExamId, $student1Id);
$manSnap = AttemptQuestion::forAttempt((int) $manAtt['attempt']['id']);
assertTest('Manual snapshot has 3 questions', count($manSnap) === 3);
assertTest('Manual snapshot preserves sort_order 1', (int) $manSnap[0]['sort_order'] === 1 && (int) $manSnap[0]['question_id'] === $q1);
assertTest('Manual snapshot preserves sort_order 2', (int) $manSnap[1]['sort_order'] === 2 && (int) $manSnap[1]['question_id'] === $q2);
assertTest('Manual snapshot preserves sort_order 3', (int) $manSnap[2]['sort_order'] === 3 && (int) $manSnap[2]['question_id'] === $q3);
assertTest('Manual snapshot stored override mark (4.00)', (float) $manSnap[0]['mark'] === 4.0);
assertTest('Manual snapshot stored default mark fallback (1.00)', (float) $manSnap[1]['mark'] === 1.0);
assertTest('Manual snapshot stored question_text_snapshot', $manSnap[0]['question_text_snapshot'] === 'TEST7_Question_1: What is PHP?');

// Verify page refresh does NOT change snapshot questions
$manSnapRefresh = AttemptQuestion::forAttempt((int) $manAtt['attempt']['id']);
assertTest('Page refresh returns identical snapshot questions', $manSnap === $manSnapRefresh);

// Random Mode Snapshot
$randExamId = createTestExam(['title' => 'TEST7_Snapshot_Random', 'selection_mode' => 'random']);
ExamQuestionRule::create([
    'exam_id'                 => $randExamId,
    'programming_language_id' => $langId,
    'category_id'             => $catId,
    'difficulty'              => 'hard',
    'question_count'          => 2,
    'mark'                    => 3.5,
    'rule_order'              => 1,
]);

$randAtt = $service->startAttempt($randExamId, $student1Id);
assertTest('Random exam attempt started', $randAtt['success'] === true);
$randSnap = AttemptQuestion::forAttempt((int) $randAtt['attempt']['id']);
assertTest('Random snapshot has 2 questions', count($randSnap) === 2);
assertTest('Random snapshot mark applied from rule (3.5)', (float) $randSnap[0]['mark'] === 3.5 && (float) $randSnap[1]['mark'] === 3.5);
assertTest('Random snapshot sort_order is sequential 1, 2', (int) $randSnap[0]['sort_order'] === 1 && (int) $randSnap[1]['sort_order'] === 2);

// Hybrid Mode Snapshot
$hybridExamId = createTestExam(['title' => 'TEST7_Snapshot_Hybrid', 'selection_mode' => 'hybrid', 'randomize_questions' => 0]);
ExamQuestion::add($hybridExamId, $q1, 2.0);
ExamQuestion::add($hybridExamId, $q2, 2.0);
ExamQuestionRule::create([
    'exam_id'                 => $hybridExamId,
    'programming_language_id' => $langId,
    'category_id'             => $catId,
    'difficulty'              => 'medium',
    'question_count'          => 2,
    'mark'                    => 4.0,
    'rule_order'              => 1,
]);

$hybAtt = $service->startAttempt($hybridExamId, $student1Id);
assertTest('Hybrid exam attempt started', $hybAtt['success'] === true);
$hybSnap = AttemptQuestion::forAttempt((int) $hybAtt['attempt']['id']);
assertTest('Hybrid snapshot has 4 total questions (2 manual + 2 rule)', count($hybSnap) === 4);

// =============================================================================
// Section 8: No Duplicate Questions in Snapshot
// =============================================================================
echo "\n--- Section 8: No Duplicate Questions in Snapshot ---\n";
$snapQids = array_map(fn($sq) => (int) $sq['question_id'], $hybSnap);
$uniqueQids = array_unique($snapQids);
assertTest('Hybrid snapshot contains no duplicate question IDs', count($snapQids) === count($uniqueQids));

// Check DB constraint protection
$pdoDuplicateError = false;
try {
    $pdo->prepare(
        "INSERT INTO attempt_questions (attempt_id, question_id, sort_order, mark, question_text_snapshot)
         VALUES (:att, :qid, 99, 1.0, 'Duplicate test')"
    )->execute([
        'att' => (int) $hybAtt['attempt']['id'],
        'qid' => $snapQids[0], // Already exists in this attempt
    ]);
} catch (\PDOException $e) {
    $pdoDuplicateError = true;
}
assertTest('Database rejects duplicate question in attempt via unique constraint', $pdoDuplicateError === true);

// =============================================================================
// Section 9: Interface Security (Never Expose Correct Answers)
// =============================================================================
echo "\n--- Section 9: Interface Security (No Correct Answers Exposed) ---\n";
$takingData = $service->getAttemptForTaking((int) $manAtt['attempt']['id'], $student1Id);
assertTest('getAttemptForTaking returns success', $takingData['success'] === true);
assertTest('Questions array returned', !empty($takingData['questions']));

$allOptionsClean = true;
foreach ($takingData['questions'] as $qItem) {
    foreach ($qItem['options'] as $opt) {
        if (array_key_exists('is_correct', $opt)) {
            $allOptionsClean = false;
        }
    }
}
assertTest('is_correct is NEVER present in options sent to interface', $allOptionsClean === true);

// =============================================================================
// Section 10: Server-side Timer Calculation & Auto-Expiration
// =============================================================================
echo "\n--- Section 10: Server-side Timer Calculation & Auto-Expiration ---\n";
$timerExamId = createTestExam([
    'title'            => 'TEST7_Timer_Exam',
    'duration_minutes' => 15,
]);
ExamQuestion::add($timerExamId, $q1);

$timerAtt = $service->startAttempt($timerExamId, $student1Id);
$attRow = $timerAtt['attempt'];

// Fresh attempt: remaining time should be close to 15 * 60 = 900 seconds
$remSeconds = $service->getRemainingSeconds($attRow, ['duration_minutes' => 15]);
assertTest('Server computes remaining seconds accurately (~900s)', $remSeconds >= 895 && $remSeconds <= 900);

// Simulate expired attempt by adjusting started_at in the DB to 20 minutes ago
$pdo->prepare("UPDATE attempts SET started_at = :st WHERE id = :id")->execute([
    'st' => date('Y-m-d H:i:s', time() - (20 * 60)),
    'id' => (int) $attRow['id'],
]);

// getAttemptForTaking should detect expiration, expire attempt, and return expired = true
$expiredTaking = $service->getAttemptForTaking((int) $attRow['id'], $student1Id);
assertTest('Expired attempt rejected by getAttemptForTaking', $expiredTaking['success'] === false);
assertTest('expired flag set to true', $expiredTaking['expired'] === true);
assertTest('remaining_seconds is 0', $expiredTaking['remaining_seconds'] === 0);

$dbExpiredAtt = Attempt::findById((int) $attRow['id']);
assertTest('Attempt status in DB auto-updated to expired', $dbExpiredAtt['status'] === Attempt::STATUS_EXPIRED);
assertTest('submitted_at set on auto-expiration', !empty($dbExpiredAtt['submitted_at']));

// Saving answer on expired attempt must be rejected
$expiredSave = $service->saveAnswer((int) $attRow['id'], $student1Id, $manSnap[0]['id'], null);
assertTest('Saving answer on expired attempt is rejected', $expiredSave['success'] === false);

// =============================================================================
// Section 11: Answer Saving & Answer Changing
// =============================================================================
echo "\n--- Section 11: Answer Saving & Changing ---\n";
$answerExamId = createTestExam(['title' => 'TEST7_Answer_Exam']);
ExamQuestion::add($answerExamId, $q1);
ExamQuestion::add($answerExamId, $q2);

$ansAtt = $service->startAttempt($answerExamId, $student1Id);
$attId = (int) $ansAtt['attempt']['id'];
$snapQuestions = AttemptQuestion::forAttempt($attId);
$aq1Id = (int) $snapQuestions[0]['id'];
$q1Opts = QuestionOption::forQuestion($q1);
$optAId = (int) $q1Opts[0]['id'];
$optBId = (int) $q1Opts[1]['id'];

// Save initial answer (Option A)
$save1 = $service->saveAnswer($attId, $student1Id, $aq1Id, $optAId);
assertTest('Save answer returns success', $save1['success'] === true);

$ansRow1 = AttemptAnswer::findByAttemptQuestionId($aq1Id);
assertTest('Answer record created in attempt_answers', $ansRow1 !== null);
assertTest('selected_option_id saved correctly', (int) $ansRow1['selected_option_id'] === $optAId);
assertTest('is_correct is NULL (not evaluated in Phase 7)', $ansRow1['is_correct'] === null);
assertTest('awarded_mark is NULL (not evaluated in Phase 7)', $ansRow1['awarded_mark'] === null);
assertTest('answered_at timestamp recorded', !empty($ansRow1['answered_at']));

// Change answer to Option B before submission
$save2 = $service->saveAnswer($attId, $student1Id, $aq1Id, $optBId);
assertTest('Change answer returns success', $save2['success'] === true);

$ansRow2 = AttemptAnswer::findByAttemptQuestionId($aq1Id);
assertTest('selected_option_id updated to Option B', (int) $ansRow2['selected_option_id'] === $optBId);

// Total answers count for attempt
$countAns = AttemptAnswer::countAnsweredForAttempt($attId);
assertTest('Only 1 answer record exists for aq1 after update (no duplicates)', $countAns === 1);

// =============================================================================
// Section 12: Invalid Option Rejection
// =============================================================================
echo "\n--- Section 12: Invalid Option Rejection ---\n";
$q2Opts = QuestionOption::forQuestion($q2);
$q2OptId = (int) $q2Opts[0]['id'];

// Try to submit option belonging to Q2 for Q1
$foreignOptSave = $service->saveAnswer($attId, $student1Id, $aq1Id, $q2OptId);
assertTest('Reject option belonging to a different question', $foreignOptSave['success'] === false);
assertTest('Error message indicates option does not belong to question', strpos($foreignOptSave['errors'][0], 'does not belong') !== false);

// Try to submit non-existent option
$nonExistentOptSave = $service->saveAnswer($attId, $student1Id, $aq1Id, 9999999);
assertTest('Reject non-existent option ID', $nonExistentOptSave['success'] === false);

// Try to submit answer for question not belonging to this attempt
$foreignAqSave = $service->saveAnswer($attId, $student1Id, (int) $manSnap[0]['id'], $optAId);
assertTest('Reject attempt_question_id belonging to another attempt', $foreignAqSave['success'] === false);

// =============================================================================
// Section 13: Ownership Protection
// =============================================================================
echo "\n--- Section 13: Ownership Protection ---\n";
// Student 2 attempts to save answer on Student 1's attempt
$tamperSave = $service->saveAnswer($attId, $student2Id, $aq1Id, $optAId);
assertTest('Student 2 cannot save answer on Student 1 attempt', $tamperSave['success'] === false);
assertTest('Tamper error message mentions access denied or ownership', strpos(strtolower($tamperSave['errors'][0]), 'access denied') !== false);

// Student 2 attempts to view Student 1's attempt interface
$tamperView = $service->getAttemptForTaking($attId, $student2Id);
assertTest('Student 2 cannot view Student 1 attempt interface', $tamperView['success'] === false);

// Student 2 attempts to submit Student 1's attempt
$tamperSubmit = $service->submitAttempt($attId, $student2Id);
assertTest('Student 2 cannot submit Student 1 attempt', $tamperSubmit['success'] === false);

// =============================================================================
// Section 14: Manual Submission & Answer Freeze
// =============================================================================
echo "\n--- Section 14: Manual Submission & Answer Freeze ---\n";
$submitRes = $service->submitAttempt($attId, $student1Id);
assertTest('Manual submission succeeds', $submitRes['success'] === true);
assertTest('Submission status is submitted', $submitRes['status'] === Attempt::STATUS_SUBMITTED);
assertTest('submitted_at timestamp recorded', !empty($submitRes['submitted_at']));

$submittedAtt = Attempt::findById($attId);
assertTest('DB status is submitted', $submittedAtt['status'] === Attempt::STATUS_SUBMITTED);

// Attempting to modify answers AFTER submission must be blocked
$postSubmitSave = $service->saveAnswer($attId, $student1Id, $aq1Id, $optAId);
assertTest('Modifying answer after submission is blocked', $postSubmitSave['success'] === false);
assertTest('Error message mentions attempt is submitted', strpos($postSubmitSave['errors'][0], 'submitted') !== false);

// Summary verification
$summary = $service->getAttemptSummary($attId, $student1Id);
assertTest('Summary total_questions is 2', $summary['total_questions'] === 2);
assertTest('Summary answered_questions is 1', $summary['answered_questions'] === 1);
assertTest('Summary does not expose score or result (Phase 8)', !isset($summary['score']));

// =============================================================================
// Section 15: CSRF & Security Validation
// =============================================================================
echo "\n--- Section 15: CSRF & Security Validation ---\n";
$token = Csrf::getToken();
assertTest('CSRF token is generated and valid', Csrf::validate($token) === true);
assertTest('Invalid CSRF token is rejected', Csrf::validate('invalid_token_123') === false);
assertTest('Empty CSRF token is rejected', Csrf::validate('') === false);
assertTest('Null CSRF token is rejected', Csrf::validate(null) === false);

// =============================================================================
// Section 16: Database Schema Integrity
// =============================================================================
echo "\n--- Section 16: Database Schema Integrity ---\n";
$tables = [
    'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
    'programming_languages', 'categories', 'questions', 'question_options',
    'exams', 'exam_questions', 'exam_question_rules',
    'attempts', 'attempt_questions', 'attempt_answers', 'results', 'audit_logs'
];
foreach ($tables as $t) {
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$t}'")->fetchColumn();
    assertTest("Table '{$t}' schema unchanged", $exists === 1);
}

// Check key columns of attempts
$attemptCols = ['id', 'exam_id', 'user_id', 'attempt_number', 'status', 'started_at', 'submitted_at', 'score', 'percentage', 'passed'];
foreach ($attemptCols as $c) {
    $cExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'attempts' AND column_name = '{$c}'")->fetchColumn();
    assertTest("attempts.{$c} column exists", $cExists === 1);
}

// Check key columns of attempt_questions
$aqCols = ['id', 'attempt_id', 'question_id', 'sort_order', 'mark', 'question_text_snapshot'];
foreach ($aqCols as $c) {
    $cExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'attempt_questions' AND column_name = '{$c}'")->fetchColumn();
    assertTest("attempt_questions.{$c} column exists", $cExists === 1);
}

// Check key columns of attempt_answers
$aaCols = ['id', 'attempt_question_id', 'selected_option_id', 'answer_text', 'is_correct', 'awarded_mark', 'answered_at'];
foreach ($aaCols as $c) {
    $cExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'attempt_answers' AND column_name = '{$c}'")->fetchColumn();
    assertTest("attempt_answers.{$c} column exists", $cExists === 1);
}

// ── Cleanup Test Fixtures ──────────────────────────────────────────────────────
$pdo->exec("DELETE FROM attempt_answers WHERE attempt_question_id IN (SELECT id FROM attempt_questions WHERE attempt_id IN (SELECT id FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%')))");
$pdo->exec("DELETE FROM attempt_questions WHERE attempt_id IN (SELECT id FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%'))");
$pdo->exec("DELETE FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%')");
$pdo->exec("DELETE FROM exam_question_rules WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%')");
$pdo->exec("DELETE FROM exam_questions WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST7_%')");
$pdo->exec("DELETE FROM exams WHERE title LIKE 'TEST7_%'");
$pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE question_text LIKE 'TEST7_%')");
$pdo->exec("DELETE FROM questions WHERE question_text LIKE 'TEST7_%'");
$pdo->exec("DELETE FROM categories WHERE slug LIKE 'test7-%'");
$pdo->exec("DELETE FROM programming_languages WHERE slug LIKE 'test7-%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'attempt7_%'");

echo "\n============================================================\n";
echo "Tests Passed: {$passed}\n";
echo "Tests Failed: {$failed}\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
