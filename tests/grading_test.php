<?php
/**
 * Phase 8 — Grading, Evaluation & Results Test Suite
 *
 * Verifies all Phase 8 requirements:
 *  - Server-side grading of single_choice and true_false questions
 *  - Correct answers, wrong answers, and unanswered questions
 *  - Mark accumulation, percentage calculation, and pass/fail determination
 *  - Attempt lifecycle evaluation (submitted, expired, in-progress, cancelled)
 *  - Results table creation, update, and duplicate evaluation protection (idempotency)
 *  - Result visibility (show_result_immediately and show_correct_answers)
 *  - Authorization, ownership validation, and tamper prevention
 *  - Database integrity and schema preservation
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
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ProgrammingLanguage;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Result;
use App\Models\User;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\ExamAttemptService;
use App\Services\ExamGradingService;
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
echo "Phase 8 — Grading, Evaluation & Results Test Suite\n";
echo "============================================================\n\n";

$pdo            = Database::getConnection();
$attemptService = new ExamAttemptService();
$gradingService = new ExamGradingService();
$auth           = new AuthService();

// ── Global cleanup ─────────────────────────────────────────────────────────────
$pdo->exec("DELETE FROM results WHERE attempt_id IN (SELECT id FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST8_%'))");
$pdo->exec("DELETE FROM attempt_answers WHERE attempt_question_id IN (SELECT id FROM attempt_questions WHERE attempt_id IN (SELECT id FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST8_%')))");
$pdo->exec("DELETE FROM attempt_questions WHERE attempt_id IN (SELECT id FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST8_%'))");
$pdo->exec("DELETE FROM attempts WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST8_%')");
$pdo->exec("DELETE FROM exam_question_rules WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST8_%')");
$pdo->exec("DELETE FROM exam_questions WHERE exam_id IN (SELECT id FROM exams WHERE title LIKE 'TEST8_%')");
$pdo->exec("DELETE FROM exams WHERE title LIKE 'TEST8_%'");
$pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE question_text LIKE 'TEST8_%')");
$pdo->exec("DELETE FROM questions WHERE question_text LIKE 'TEST8_%'");
$pdo->exec("DELETE FROM categories WHERE slug LIKE 'test8-%'");
$pdo->exec("DELETE FROM programming_languages WHERE slug LIKE 'test8-%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'grade8_%'");

// ── Create test fixtures ───────────────────────────────────────────────────────
// 1. Users: Student 1, Student 2, Teacher
$st1Reg = $auth->register([
    'first_name'            => 'Student',
    'last_name'             => 'One',
    'username'              => 'grade8_student1',
    'email'                 => 'grade8_student1@test.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
$student1Id = (int) $st1Reg['user_id'];

$st2Reg = $auth->register([
    'first_name'            => 'Student',
    'last_name'             => 'Two',
    'username'              => 'grade8_student2',
    'email'                 => 'grade8_student2@test.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
$student2Id = (int) $st2Reg['user_id'];

$teacherReg = $auth->register([
    'first_name'            => 'Teacher',
    'last_name'             => 'Eight',
    'username'              => 'grade8_teacher',
    'email'                 => 'grade8_teacher@test.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
$teacherId = (int) $teacherReg['user_id'];

// Set teacher role
$teacherRoleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'teacher'")->fetchColumn();
$studentRoleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'student'")->fetchColumn();

$pdo->prepare("UPDATE user_roles SET role_id = :role_id WHERE user_id = :user_id")
    ->execute(['role_id' => $teacherRoleId, 'user_id' => $teacherId]);

// 2. Language & Category
$pdo->prepare("INSERT INTO programming_languages (name, slug, description, status) VALUES ('TEST8_PHP', 'test8-php', 'Test PHP', 'active')")->execute();
$langId = (int) $pdo->lastInsertId();

$pdo->prepare("INSERT INTO categories (name, slug, description, status) VALUES ('TEST8_Web', 'test8-web', 'Test Web', 'active')")->execute();
$catId = (int) $pdo->lastInsertId();

// Helper to create published single-choice or true-false questions
function createQuestion(string $text, string $type, float $mark, array $options, ?string $explanation = null): int
{
    global $pdo, $langId, $catId, $teacherId;
    $stmt = $pdo->prepare(
        "INSERT INTO questions (programming_language_id, category_id, created_by, question_text, question_type, difficulty, default_mark, explanation, status)
         VALUES (:lang, :cat, :user, :text, :type, 'medium', :mark, :expl, 'published')"
    );
    $stmt->execute([
        'lang' => $langId,
        'cat'  => $catId,
        'user' => $teacherId,
        'text' => $text,
        'type' => $type,
        'mark' => $mark,
        'expl' => $explanation,
    ]);
    $qid = (int) $pdo->lastInsertId();

    $optStmt = $pdo->prepare(
        "INSERT INTO question_options (question_id, option_text, is_correct, sort_order)
         VALUES (:qid, :text, :correct, :order)"
    );
    foreach ($options as $order => $opt) {
        $optStmt->execute([
            'qid'     => $qid,
            'text'    => $opt['text'],
            'correct' => !empty($opt['is_correct']) ? 1 : 0,
            'order'   => $order + 1,
        ]);
    }
    return $qid;
}

// Helper to create published exam
function createExam(string $title, float $passPercentage = 50.0, bool $showResult = true, bool $showCorrect = false, int $duration = 30): int
{
    global $pdo, $langId, $teacherId;
    $stmt = $pdo->prepare(
        "INSERT INTO exams (
            programming_language_id, created_by, title, description,
            selection_mode, duration_minutes, pass_percentage,
            randomize_questions, randomize_options,
            show_result_immediately, show_correct_answers, status
         ) VALUES (
            :lang, :user, :title, 'Test Exam',
            'manual', :duration, :pass_percentage,
            0, 0,
            :show_result, :show_correct, 'published'
         )"
    );
    $stmt->execute([
        'lang'            => $langId,
        'user'            => $teacherId,
        'title'           => $title,
        'duration'        => $duration,
        'pass_percentage' => $passPercentage,
        'show_result'     => $showResult ? 1 : 0,
        'show_correct'    => $showCorrect ? 1 : 0,
    ]);
    return (int) $pdo->lastInsertId();
}

function getAqMap(int $attemptId): array
{
    $snap = AttemptQuestion::forAttempt($attemptId);
    $map = [];
    foreach ($snap as $s) {
        $map[(int) $s['question_id']] = (int) $s['id'];
    }
    return $map;
}

function addQuestionToExam(int $examId, int $questionId, float $mark, int $order = 1): void
{
    global $pdo;
    $stmt = $pdo->prepare(
        "INSERT INTO exam_questions (exam_id, question_id, sort_order, mark)
         VALUES (:exam_id, :question_id, :sort_order, :mark)"
    );
    $stmt->execute([
        'exam_id'     => $examId,
        'question_id' => $questionId,
        'sort_order'  => $order,
        'mark'        => $mark,
    ]);
}

// =============================================================================
// Section 1: Class & Model Existence
// =============================================================================
echo "--- Section 1: Class & Model Existence ---\n";
assertTest('Result model exists', class_exists(Result::class));
assertTest('ExamGradingService exists', class_exists(ExamGradingService::class));
assertTest('AttemptController exists', class_exists(AttemptController::class));
assertTest('Attempt::updateGrading method exists', method_exists(Attempt::class, 'updateGrading'));
assertTest('AttemptAnswer::updateGrading method exists', method_exists(AttemptAnswer::class, 'updateGrading'));
assertTest('AttemptAnswer::recordGradedAnswer method exists', method_exists(AttemptAnswer::class, 'recordGradedAnswer'));
assertTest('Result::createOrUpdate method exists', method_exists(Result::class, 'createOrUpdate'));
assertTest('Result::findByAttemptId method exists', method_exists(Result::class, 'findByAttemptId'));
assertTest('ExamGradingService::evaluateAttempt method exists', method_exists(ExamGradingService::class, 'evaluateAttempt'));
assertTest('ExamGradingService::getStudentResult method exists', method_exists(ExamGradingService::class, 'getStudentResult'));

// =============================================================================
// Section 2: Database Schema & Integrity Check
// =============================================================================
echo "\n--- Section 2: Database Schema & Integrity Check ---\n";
$tables = [
    'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
    'programming_languages', 'categories', 'questions', 'question_options',
    'exams', 'exam_questions', 'exam_question_rules',
    'attempts', 'attempt_questions', 'attempt_answers', 'results', 'audit_logs'
];
foreach ($tables as $t) {
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$t}'")->fetchColumn();
    assertTest("Table '{$t}' schema intact", $exists === 1);
}

$resultsColumns = [
    'id', 'attempt_id', 'total_questions', 'answered_questions', 'correct_answers',
    'wrong_answers', 'total_marks', 'score', 'percentage', 'passed', 'calculated_at'
];
foreach ($resultsColumns as $col) {
    $cExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'results' AND column_name = '{$col}'")->fetchColumn();
    assertTest("results.{$col} column exists", $cExists === 1);
}

// =============================================================================
// Section 3: Single Choice Question Grading (Correct, Wrong, Unanswered)
// =============================================================================
echo "\n--- Section 3: Single Choice Question Grading ---\n";

// Q1: What does PHP stand for? (2.0 marks)
$q1 = createQuestion('TEST8_Q1: What does PHP stand for?', 'single_choice', 2.0, [
    ['text' => 'Hypertext Preprocessor', 'is_correct' => true],
    ['text' => 'Private Home Page',     'is_correct' => false],
    ['text' => 'Personal Hypertext',    'is_correct' => false],
], 'PHP originally stood for Personal Home Page, but now stands for Hypertext Preprocessor.');

// Q2: Which superglobal holds GET data? (3.0 marks)
$q2 = createQuestion('TEST8_Q2: Which superglobal holds GET data?', 'single_choice', 3.0, [
    ['text' => '$_POST',    'is_correct' => false],
    ['text' => '$_GET',     'is_correct' => true],
    ['text' => '$_REQUEST', 'is_correct' => false],
], '$_GET is used for query string parameters.');

// Q3: Which keyword defines a class constant? (5.0 marks)
$q3 = createQuestion('TEST8_Q3: Which keyword defines a class constant?', 'single_choice', 5.0, [
    ['text' => 'const',    'is_correct' => true],
    ['text' => 'define',   'is_correct' => false],
    ['text' => 'constant', 'is_correct' => false],
], 'Class constants are defined using the const keyword.');

// Exam 1: 3 questions, Total Marks = 10.0, Pass Percentage = 50.0%
$exam1Id = createExam('TEST8_Exam_SingleChoice', 50.0, true, true);
addQuestionToExam($exam1Id, $q1, 2.0, 1);
addQuestionToExam($exam1Id, $q2, 3.0, 2);
addQuestionToExam($exam1Id, $q3, 5.0, 3);

// Start attempt for student 1
$startRes = $attemptService->startAttempt($exam1Id, $student1Id);
assertTest('Start attempt succeeds for single choice exam', $startRes['success'] === true);
$attempt1Id = (int) $startRes['attempt']['id'];

$snap1 = AttemptQuestion::forAttempt($attempt1Id);
$aqMap1 = getAqMap($attempt1Id);
$aq1Id = $aqMap1[$q1];
$aq2Id = $aqMap1[$q2];
$aq3Id = $aqMap1[$q3];

// Get options
$q1Opts = QuestionOption::forQuestion($q1);
$q1CorrectOptId = null;
foreach ($q1Opts as $o) {
    if ($o['is_correct']) $q1CorrectOptId = (int) $o['id'];
}

$q2Opts = QuestionOption::forQuestion($q2);
$q2WrongOptId = null;
foreach ($q2Opts as $o) {
    if (!$o['is_correct']) {
        $q2WrongOptId = (int) $o['id'];
        break;
    }
}

// Student answers:
// Q1: Correct (awarded 2.0)
$attemptService->saveAnswer($attempt1Id, $student1Id, $aq1Id, $q1CorrectOptId);
// Q2: Wrong (awarded 0.0)
$attemptService->saveAnswer($attempt1Id, $student1Id, $aq2Id, $q2WrongOptId);
// Q3: Unanswered (awarded 0.0)

// Submit attempt
$subRes1 = $attemptService->submitAttempt($attempt1Id, $student1Id);
assertTest('Attempt 1 submit returns success', $subRes1['success'] === true);

// Verify evaluation
$eval1 = $gradingService->evaluateAttempt($attempt1Id);
assertTest('Grading service evaluateAttempt succeeds', $eval1['success'] === true);
assertTest('Total questions is 3', $eval1['total_questions'] === 3);
assertTest('Answered questions is 2', $eval1['answered_questions'] === 2);
assertTest('Unanswered questions is 1', $eval1['unanswered_questions'] === 1);
assertTest('Correct answers is 1', $eval1['correct_answers'] === 1);
assertTest('Wrong answers is 1', $eval1['wrong_answers'] === 1);
assertTest('Total marks is 10.00', abs($eval1['total_marks'] - 10.00) < 0.001);
assertTest('Score is 2.00', abs($eval1['score'] - 2.00) < 0.001);
assertTest('Percentage is 20.00%', abs($eval1['percentage'] - 20.00) < 0.001);
assertTest('Passed is false (20% < 50%)', $eval1['passed'] === false);

// Check attempt_answers table records
$ans1 = AttemptAnswer::findByAttemptQuestionId($aq1Id);
assertTest('Q1 answer is_correct = 1', (int) $ans1['is_correct'] === 1);
assertTest('Q1 answer awarded_mark = 2.00', abs((float) $ans1['awarded_mark'] - 2.00) < 0.001);

$ans2 = AttemptAnswer::findByAttemptQuestionId($aq2Id);
assertTest('Q2 answer is_correct = 0', (int) $ans2['is_correct'] === 0);
assertTest('Q2 answer awarded_mark = 0.00', abs((float) $ans2['awarded_mark'] - 0.00) < 0.001);

// =============================================================================
// Section 4: True/False Question Grading
// =============================================================================
echo "\n--- Section 4: True/False Question Grading ---\n";

// Q_TF1: PHP is interpreted (True is correct, 4.0 marks)
$qTf1 = createQuestion('TEST8_TF1: PHP is an interpreted scripting language.', 'true_false', 4.0, [
    ['text' => 'True',  'is_correct' => true],
    ['text' => 'False', 'is_correct' => false],
], 'PHP scripts are interpreted by the PHP engine.');

// Q_TF2: PHP cannot connect to MySQL (False is correct, 6.0 marks)
$qTf2 = createQuestion('TEST8_TF2: PHP has no built-in support for MySQL.', 'true_false', 6.0, [
    ['text' => 'True',  'is_correct' => false],
    ['text' => 'False', 'is_correct' => true],
], 'PHP provides PDO_MySQL and mysqli extensions.');

$exam2Id = createExam('TEST8_Exam_TrueFalse', 60.0, true, true);
addQuestionToExam($exam2Id, $qTf1, 4.0, 1);
addQuestionToExam($exam2Id, $qTf2, 6.0, 2);

$startRes2 = $attemptService->startAttempt($exam2Id, $student1Id);
assertTest('Start attempt succeeds for true_false exam', $startRes2['success'] === true);
$attempt2Id = (int) $startRes2['attempt']['id'];

$aqMap2 = getAqMap($attempt2Id);
$aqTf1Id = $aqMap2[$qTf1];
$aqTf2Id = $aqMap2[$qTf2];

$tf1Opts = QuestionOption::forQuestion($qTf1);
$tf1TrueId = null;
foreach ($tf1Opts as $o) {
    if ($o['option_text'] === 'True') $tf1TrueId = (int) $o['id'];
}

$tf2Opts = QuestionOption::forQuestion($qTf2);
$tf2FalseId = null;
foreach ($tf2Opts as $o) {
    if ($o['option_text'] === 'False') $tf2FalseId = (int) $o['id'];
}

// Student answers both correctly
$attemptService->saveAnswer($attempt2Id, $student1Id, $aqTf1Id, $tf1TrueId);
$attemptService->saveAnswer($attempt2Id, $student1Id, $aqTf2Id, $tf2FalseId);

$subRes2 = $attemptService->submitAttempt($attempt2Id, $student1Id);
assertTest('Attempt 2 submit succeeds', $subRes2['success'] === true);

$eval2 = $gradingService->evaluateAttempt($attempt2Id);
assertTest('True/False evaluation succeeds', $eval2['success'] === true);
assertTest('TF Total questions is 2', $eval2['total_questions'] === 2);
assertTest('TF Answered questions is 2', $eval2['answered_questions'] === 2);
assertTest('TF Correct answers is 2', $eval2['correct_answers'] === 2);
assertTest('TF Wrong answers is 0', $eval2['wrong_answers'] === 0);
assertTest('TF Total marks is 10.00', abs($eval2['total_marks'] - 10.00) < 0.001);
assertTest('TF Score is 10.00', abs($eval2['score'] - 10.00) < 0.001);
assertTest('TF Percentage is 100.00%', abs($eval2['percentage'] - 100.00) < 0.001);
assertTest('TF Passed is true (100% >= 60%)', $eval2['passed'] === true);

$ansTf1 = AttemptAnswer::findByAttemptQuestionId($aqTf1Id);
assertTest('TF1 answer is_correct = 1', (int) $ansTf1['is_correct'] === 1);
assertTest('TF1 awarded_mark = 4.00', abs((float) $ansTf1['awarded_mark'] - 4.00) < 0.001);

$ansTf2 = AttemptAnswer::findByAttemptQuestionId($aqTf2Id);
assertTest('TF2 answer is_correct = 1', (int) $ansTf2['is_correct'] === 1);
assertTest('TF2 awarded_mark = 6.00', abs((float) $ansTf2['awarded_mark'] - 6.00) < 0.001);

// =============================================================================
// Section 5: Score Calculation, Percentage & Pass/Fail Threshold
// =============================================================================
echo "\n--- Section 5: Score Calculation & Pass/Fail Threshold ---\n";

// Exam 3: Pass threshold exactly 50.00%
$exam3Id = createExam('TEST8_Exam_Threshold', 50.0, true, true);
addQuestionToExam($exam3Id, $q1, 5.0, 1);
addQuestionToExam($exam3Id, $q2, 5.0, 2);

// Case A: Student gets exactly 50% (Q1 correct, Q2 wrong)
$startRes3A = $attemptService->startAttempt($exam3Id, $student1Id);
$att3AId = (int) $startRes3A['attempt']['id'];
$aqMap3A = getAqMap($att3AId);
$attemptService->saveAnswer($att3AId, $student1Id, $aqMap3A[$q1], $q1CorrectOptId);
$attemptService->saveAnswer($att3AId, $student1Id, $aqMap3A[$q2], $q2WrongOptId);
$attemptService->submitAttempt($att3AId, $student1Id);

$eval3A = $gradingService->evaluateAttempt($att3AId);
assertTest('Score is 5.00 / 10.00', abs($eval3A['score'] - 5.00) < 0.001);
assertTest('Percentage is exactly 50.00%', abs($eval3A['percentage'] - 50.00) < 0.001);
assertTest('Exactly 50% meets 50% pass_percentage -> passed = true', $eval3A['passed'] === true);

// Case B: Zero score (both wrong)
$startRes3B = $attemptService->startAttempt($exam3Id, $student2Id);
$att3BId = (int) $startRes3B['attempt']['id'];
$aqMap3B = getAqMap($att3BId);
$q1Opts = QuestionOption::forQuestion($q1);
$q1WrongId = null;
foreach ($q1Opts as $o) {
    if (!$o['is_correct']) { $q1WrongId = (int) $o['id']; break; }
}
$attemptService->saveAnswer($att3BId, $student2Id, $aqMap3B[$q1], $q1WrongId);
$attemptService->saveAnswer($att3BId, $student2Id, $aqMap3B[$q2], $q2WrongOptId);
$attemptService->submitAttempt($att3BId, $student2Id);

$eval3B = $gradingService->evaluateAttempt($att3BId);
assertTest('Score is 0.00 for all wrong answers', abs($eval3B['score'] - 0.00) < 0.001);
assertTest('Percentage is 0.00%', abs($eval3B['percentage'] - 0.00) < 0.001);
assertTest('Zero score results in passed = false', $eval3B['passed'] === false);

// Case C: All questions unanswered
// Student 1 starts another attempt
$exam4Id = createExam('TEST8_Exam_Unanswered', 50.0, true, true);
addQuestionToExam($exam4Id, $q1, 5.0, 1);
addQuestionToExam($exam4Id, $q2, 5.0, 2);

$startRes4 = $attemptService->startAttempt($exam4Id, $student1Id);
$att4Id = (int) $startRes4['attempt']['id'];
$attemptService->submitAttempt($att4Id, $student1Id);

$eval4 = $gradingService->evaluateAttempt($att4Id);
assertTest('All unanswered: total_questions is 2', $eval4['total_questions'] === 2);
assertTest('All unanswered: answered_questions is 0', $eval4['answered_questions'] === 0);
assertTest('All unanswered: unanswered_questions is 2', $eval4['unanswered_questions'] === 2);
assertTest('All unanswered: correct_answers is 0', $eval4['correct_answers'] === 0);
assertTest('All unanswered: wrong_answers is 0', $eval4['wrong_answers'] === 0);
assertTest('All unanswered: score is 0.00', abs($eval4['score'] - 0.00) < 0.001);
assertTest('All unanswered: passed is false', $eval4['passed'] === false);

// =============================================================================
// Section 6: Attempt Lifecycle & Auto-Evaluation (Submitted vs Expired vs Cancelled)
// =============================================================================
echo "\n--- Section 6: Attempt Lifecycle & Auto-Evaluation ---\n";

// Expired attempt evaluation
$examExpId = createExam('TEST8_Exam_Expiry', 50.0, true, true, 10);
addQuestionToExam($examExpId, $q1, 10.0, 1);

$startExp = $attemptService->startAttempt($examExpId, $student1Id);
$attExpId = (int) $startExp['attempt']['id'];
$aqMapExp = getAqMap($attExpId);
// Answer question correctly
$attemptService->saveAnswer($attExpId, $student1Id, $aqMapExp[$q1], $q1CorrectOptId);

// Simulate time passing (expire attempt)
Attempt::expire($attExpId, date('Y-m-d H:i:s', time() - 60));
$expAtt = Attempt::findById($attExpId);
assertTest('Simulated attempt status is expired', $expAtt['status'] === Attempt::STATUS_EXPIRED);

$evalExp = $gradingService->evaluateAttempt($attExpId);
assertTest('Expired attempt evaluates successfully', $evalExp['success'] === true);
assertTest('Expired attempt score is 10.00', abs($evalExp['score'] - 10.00) < 0.001);
assertTest('Expired attempt percentage is 100.00%', abs($evalExp['percentage'] - 100.00) < 0.001);
assertTest('Expired attempt passed is true', $evalExp['passed'] === true);

// In-progress attempt evaluation rejected (when time has not ended)
$examProgId = createExam('TEST8_Exam_InProgress', 50.0, true, true, 60);
addQuestionToExam($examProgId, $q1, 10.0, 1);
$startProg = $attemptService->startAttempt($examProgId, $student1Id);
$attProgId = (int) $startProg['attempt']['id'];

$evalProg = $gradingService->evaluateAttempt($attProgId);
assertTest('Evaluating in_progress attempt is rejected', $evalProg['success'] === false);
assertTest('Error message mentions in progress', strpos($evalProg['errors'][0], 'in progress') !== false);

// Cancelled attempt evaluation rejected
$examCancId = createExam('TEST8_Exam_Cancelled', 50.0, true, true, 60);
addQuestionToExam($examCancId, $q1, 10.0, 1);
$startCanc = $attemptService->startAttempt($examCancId, $student1Id);
$attCancId = (int) $startCanc['attempt']['id'];
Attempt::updateStatus($attCancId, Attempt::STATUS_CANCELLED);

$evalCanc = $gradingService->evaluateAttempt($attCancId);
assertTest('Evaluating cancelled attempt is rejected', $evalCanc['success'] === false);
assertTest('Error message mentions cancelled', strpos($evalCanc['errors'][0], 'cancelled') !== false);

// =============================================================================
// Section 7: Result Record Creation & Persistence in DB
// =============================================================================
echo "\n--- Section 7: Result Record Creation & Persistence ---\n";

$resRow = Result::findByAttemptId($attempt1Id);
assertTest('Result record found in DB for Attempt 1', $resRow !== null);
assertTest('Result attempt_id matches', (int) $resRow['attempt_id'] === $attempt1Id);
assertTest('Result total_questions matches DB', (int) $resRow['total_questions'] === 3);
assertTest('Result answered_questions matches DB', (int) $resRow['answered_questions'] === 2);
assertTest('Result correct_answers matches DB', (int) $resRow['correct_answers'] === 1);
assertTest('Result wrong_answers matches DB', (int) $resRow['wrong_answers'] === 1);
assertTest('Result score matches DB', abs((float) $resRow['score'] - 2.00) < 0.001);
assertTest('Result percentage matches DB', abs((float) $resRow['percentage'] - 20.00) < 0.001);
assertTest('Result passed is 0 in DB', (int) $resRow['passed'] === 0);

// Check attempts table updated
$attDb = Attempt::findById($attempt1Id);
assertTest('Attempt record score updated in DB', abs((float) $attDb['score'] - 2.00) < 0.001);
assertTest('Attempt record percentage updated in DB', abs((float) $attDb['percentage'] - 20.00) < 0.001);
assertTest('Attempt record passed updated in DB', (int) $attDb['passed'] === 0);

// =============================================================================
// Section 8: Duplicate Evaluation Protection (Idempotency)
// =============================================================================
echo "\n--- Section 8: Duplicate Evaluation Protection ---\n";

// Count results before re-evaluation
$countBefore = (int) $pdo->query("SELECT COUNT(*) FROM results WHERE attempt_id = {$attempt1Id}")->fetchColumn();
assertTest('Exactly 1 result record exists initially', $countBefore === 1);

// Call evaluateAttempt a second and third time
$evalRe1 = $gradingService->evaluateAttempt($attempt1Id);
$evalRe2 = $gradingService->evaluateAttempt($attempt1Id);

assertTest('Re-evaluation 1 succeeds', $evalRe1['success'] === true);
assertTest('Re-evaluation 2 succeeds', $evalRe2['success'] === true);

$countAfter = (int) $pdo->query("SELECT COUNT(*) FROM results WHERE attempt_id = {$attempt1Id}")->fetchColumn();
assertTest('Still exactly 1 result record after multiple evaluations (no duplicates)', $countAfter === 1);
assertTest('Score remains identical after re-evaluation', abs((float) $evalRe2['score'] - 2.00) < 0.001);

// =============================================================================
// Section 9: Result Visibility (show_result_immediately)
// =============================================================================
echo "\n--- Section 9: Result Visibility (show_result_immediately) ---\n";

// Exam with show_result_immediately = false
$examHiddenId = createExam('TEST8_Exam_HiddenResult', 50.0, false, false);
addQuestionToExam($examHiddenId, $q1, 10.0, 1);

$startHidden = $attemptService->startAttempt($examHiddenId, $student1Id);
$attHiddenId = (int) $startHidden['attempt']['id'];
$aqMapHidden = getAqMap($attHiddenId);
$attemptService->saveAnswer($attHiddenId, $student1Id, $aqMapHidden[$q1], $q1CorrectOptId);
$attemptService->submitAttempt($attHiddenId, $student1Id);

// Student requests result
$studentView = $gradingService->getStudentResult($attHiddenId, $student1Id, false);
assertTest('Student result request succeeds', $studentView['success'] === true);
assertTest('can_view_result is false for student when show_result_immediately = false', $studentView['can_view_result'] === false);
assertTest('can_view_correct_answers is false for student when show_result_immediately = false', $studentView['can_view_correct_answers'] === false);

// Staff (teacher/admin) requests result for same attempt
$staffView = $gradingService->getStudentResult($attHiddenId, $teacherId, true);
assertTest('can_view_result is true for staff even when show_result_immediately = false', $staffView['can_view_result'] === true);

// =============================================================================
// Section 10: Correct Answers Visibility (show_correct_answers)
// =============================================================================
echo "\n--- Section 10: Correct Answers Visibility (show_correct_answers) ---\n";

// Exam with show_result_immediately = true, show_correct_answers = false
$examNoAnsId = createExam('TEST8_Exam_NoCorrectAns', 50.0, true, false);
addQuestionToExam($examNoAnsId, $q1, 10.0, 1);

$startNoAns = $attemptService->startAttempt($examNoAnsId, $student1Id);
$attNoAnsId = (int) $startNoAns['attempt']['id'];
$aqMapNoAns = getAqMap($attNoAnsId);
$attemptService->saveAnswer($attNoAnsId, $student1Id, $aqMapNoAns[$q1], $q1WrongId);
$attemptService->submitAttempt($attNoAnsId, $student1Id);

$studentNoAnsView = $gradingService->getStudentResult($attNoAnsId, $student1Id, false);
assertTest('Student can view score (can_view_result = true)', $studentNoAnsView['can_view_result'] === true);
assertTest('Student CANNOT view correct answers (can_view_correct_answers = false)', $studentNoAnsView['can_view_correct_answers'] === false);

$qItem = $studentNoAnsView['questions'][0];
assertTest('Question item does NOT expose correct_option_text', !isset($qItem['correct_option_text']));
assertTest('Question item does NOT expose explanation', !isset($qItem['explanation']));
assertTest('Options do NOT expose is_correct', !isset($qItem['options'][0]['is_correct']));

// Exam with show_result_immediately = true, show_correct_answers = true
$examWithAnsId = createExam('TEST8_Exam_WithCorrectAns', 50.0, true, true);
addQuestionToExam($examWithAnsId, $q1, 10.0, 1);

$startWithAns = $attemptService->startAttempt($examWithAnsId, $student1Id);
$attWithAnsId = (int) $startWithAns['attempt']['id'];
$aqMapWithAns = getAqMap($attWithAnsId);
$attemptService->saveAnswer($attWithAnsId, $student1Id, $aqMapWithAns[$q1], $q1WrongId);
$attemptService->submitAttempt($attWithAnsId, $student1Id);

$studentWithAnsView = $gradingService->getStudentResult($attWithAnsId, $student1Id, false);
assertTest('Student can view correct answers when permitted (can_view_correct_answers = true)', $studentWithAnsView['can_view_correct_answers'] === true);

$qItemPermitted = $studentWithAnsView['questions'][0];
assertTest('Question item exposes correct_option_text', isset($qItemPermitted['correct_option_text']) && !empty($qItemPermitted['correct_option_text']));
assertTest('Question item exposes explanation', isset($qItemPermitted['explanation']) && !empty($qItemPermitted['explanation']));
assertTest('Options expose is_correct boolean', isset($qItemPermitted['options'][0]['is_correct']));

// =============================================================================
// Section 11: Security & Unauthorized Access
// =============================================================================
echo "\n--- Section 11: Security & Unauthorized Access ---\n";

// Student 2 attempts to view Student 1's result
$tamperView = $gradingService->getStudentResult($attempt1Id, $student2Id, false);
assertTest('Student 2 cannot view Student 1 result (access denied)', $tamperView['success'] === false);
assertTest('Error message mentions access denied', strpos($tamperView['errors'][0], 'Access denied') !== false);

// Attempting to modify answer after evaluation/submission
$postEvalSave = $attemptService->saveAnswer($attempt1Id, $student1Id, $aq1Id, $q1CorrectOptId);
assertTest('Cannot modify answer after submission and evaluation', $postEvalSave['success'] === false);

// =============================================================================
// Section 12: Data Integrity & Check Constraints
// =============================================================================
echo "\n--- Section 12: Data Integrity & Check Constraints ---\n";

// Verify check constraints on all result records
$allResults = $pdo->query("SELECT * FROM results WHERE attempt_id IN ({$attempt1Id}, {$attempt2Id}, {$att3AId}, {$att3BId}, {$att4Id})")->fetchAll();
foreach ($allResults as $res) {
    $attId = $res['attempt_id'];
    assertTest("Result #{$attId} percentage within [0, 100]", (float)$res['percentage'] >= 0 && (float)$res['percentage'] <= 100);
    assertTest("Result #{$attId} answered_questions <= total_questions", (int)$res['answered_questions'] <= (int)$res['total_questions']);
    assertTest("Result #{$attId} correct_answers <= answered_questions", (int)$res['correct_answers'] <= (int)$res['answered_questions']);
    assertTest("Result #{$attId} wrong_answers <= answered_questions", (int)$res['wrong_answers'] <= (int)$res['answered_questions']);
    assertTest("Result #{$attId} score >= 0", (float)$res['score'] >= 0);
    assertTest("Result #{$attId} total_marks >= 0", (float)$res['total_marks'] >= 0);
}

// Result model CRUD tests
$resById = Result::findById((int) $resRow['id']);
assertTest('Result::findById returns valid row', $resById !== null && (int)$resById['id'] === (int)$resRow['id']);

$resWithDetails = Result::findWithDetailsByAttemptId($attempt1Id);
assertTest('Result::findWithDetailsByAttemptId joins exam title', !empty($resWithDetails['exam_title']));
assertTest('Result::findWithDetailsByAttemptId joins user username', !empty($resWithDetails['username']));

$userResults = Result::forUser($student1Id);
assertTest('Result::forUser returns student results array', count($userResults) >= 3);

$examResults = Result::forExam($exam1Id);
assertTest('Result::forExam returns exam results array', count($examResults) >= 1);

// Final tally
echo "\n============================================================\n";
echo "Tests Passed: {$passed}\n";
echo "Tests Failed: {$failed}\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
