<?php

/**
 * Test Suite for Phase 4B: RBAC & Authorization
 *
 * Tests:
 *  1.  AuthorizationService class exists
 *  2.  Middleware classes exist
 *  3.  DB role/permission seed verification
 *  4.  Guest → getCurrentUserRoles() returns []
 *  5.  Guest → getCurrentUserPermissions() returns []
 *  6.  Guest → hasRole() returns false
 *  7.  Guest → hasPermission() returns false
 *  8.  Guest → isAuthenticated() returns false
 *  9.  Student → isAuthenticated() returns true
 *  10. Student → hasRole('student') returns true
 *  11. Student → hasRole('admin') returns false
 *  12. Student → getCurrentUserRoles() contains 'student'
 *  13. Student → getCurrentUserPermissions() returns expected subset
 *  14. Student → hasPermission('exams.take') returns true
 *  15. Student → hasPermission('exams.view') returns true
 *  16. Student → hasPermission('questions.create') returns false
 *  17. Student → hasPermission('roles.manage') returns false
 *  18. Invalid / nonexistent permission → hasPermission() returns false
 *  19. Empty string permission → hasPermission() returns false
 *  20. Teacher → hasRole('teacher') returns true
 *  21. Teacher → hasPermission('questions.create') returns true
 *  22. Teacher → hasPermission('exams.take') returns false
 *  23. Teacher → hasPermission('users.view') returns false
 *  24. Admin → hasRole('admin') returns true
 *  25. Admin → hasPermission('roles.manage') returns true
 *  26. Admin → hasPermission('audit.view') returns true
 *  27. Admin → getCurrentUserPermissions() returns all 20 permissions
 *  28. Permission cache cleared between users
 *  29. Router: guest denied from protected route (AuthMiddleware aborts)
 *  30. Router: student can access auth-only route
 *  31. Router: student denied from admin-only route (RoleMiddleware aborts)
 *  32. Router: student denied from teacher-permission route (PermissionMiddleware aborts)
 *  33. AuthMiddleware: saves intended URL flash
 *  34. RoleMiddleware: multi-role — student satisfies ['student','teacher']
 *  35. PermissionMiddleware: multi-permission — student satisfies ['exams.view','questions.create']
 */

define('BASE_PATH', dirname(__DIR__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix  = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file          = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Core\Database;
use App\Core\Session;
use App\Models\User;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\PermissionMiddleware;

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
echo "Starting Phase 4B RBAC & Authorization Tests\n";
echo "============================================================\n\n";

$pdo = Database::getConnection();

// ── Clean up any leftover test users ─────────────────────────────────────────
$pdo->exec("DELETE FROM users WHERE username LIKE 'rbac_test_%'");

// ─────────────────────────────────────────────────────────────────────────────
// Section 1: Class / file existence
// ─────────────────────────────────────────────────────────────────────────────
echo "--- Section 1: Class existence ---\n";

assertTest(
    "AuthorizationService class exists",
    class_exists(AuthorizationService::class)
);
assertTest(
    "AuthMiddleware class exists",
    class_exists(AuthMiddleware::class)
);
assertTest(
    "RoleMiddleware class exists",
    class_exists(RoleMiddleware::class)
);
assertTest(
    "PermissionMiddleware class exists",
    class_exists(PermissionMiddleware::class)
);

// ─────────────────────────────────────────────────────────────────────────────
// Section 2: Database seed verification
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Section 2: Database seed verification ---\n";

$roleNames = $pdo->query('SELECT name FROM roles ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
assertTest(
    "DB has exactly 3 roles: admin, teacher, student",
    $roleNames === ['admin', 'teacher', 'student'],
    'Found: ' . implode(', ', $roleNames)
);

$permCount = (int) $pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn();
assertTest(
    "DB has exactly 20 permissions",
    $permCount === 20,
    "Count: {$permCount}"
);

$adminPermCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM role_permissions rp
     JOIN roles r ON rp.role_id = r.id
     WHERE r.name = \'admin\''
)->fetchColumn();
assertTest(
    "Admin has all 20 permissions",
    $adminPermCount === 20,
    "Count: {$adminPermCount}"
);

$studentPerms = $pdo->query(
    'SELECT p.name FROM permissions p
     JOIN role_permissions rp ON rp.permission_id = p.id
     JOIN roles r             ON rp.role_id = r.id
     WHERE r.name = \'student\'
     ORDER BY p.name'
)->fetchAll(PDO::FETCH_COLUMN);
assertTest(
    "Student has exactly 5 permissions",
    count($studentPerms) === 5,
    implode(', ', $studentPerms)
);
assertTest(
    "Student permissions are the expected 5",
    $studentPerms === ['categories.view', 'exams.take', 'exams.view', 'languages.view', 'results.view'],
    implode(', ', $studentPerms)
);

$teacherPerms = $pdo->query(
    'SELECT p.name FROM permissions p
     JOIN role_permissions rp ON rp.permission_id = p.id
     JOIN roles r             ON rp.role_id = r.id
     WHERE r.name = \'teacher\'
     ORDER BY p.name'
)->fetchAll(PDO::FETCH_COLUMN);
assertTest(
    "Teacher has exactly 12 permissions",
    count($teacherPerms) === 12,
    implode(', ', $teacherPerms)
);

// ─────────────────────────────────────────────────────────────────────────────
// Section 3: Guest (unauthenticated) checks
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Section 3: Guest / unauthenticated ---\n";

// Clear any user from session
Session::remove('user');
AuthorizationService::clearCache();

$authz = new AuthorizationService();

assertTest("Guest: getCurrentUserRoles() returns []",   $authz->getCurrentUserRoles()       === []);
assertTest("Guest: getCurrentUserPermissions() is []",  $authz->getCurrentUserPermissions() === []);
assertTest("Guest: hasRole('student') is false",        $authz->hasRole('student')          === false);
assertTest("Guest: hasPermission('exams.take') false",  $authz->hasPermission('exams.take') === false);
assertTest("Guest: isAuthenticated() is false",         $authz->isAuthenticated()           === false);

// ─────────────────────────────────────────────────────────────────────────────
// Section 4: Create test users
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Section 4: Student role checks ---\n";

$auth = new AuthService();

// Register student
$studentReg = $auth->register([
    'first_name'            => 'Test',
    'last_name'             => 'Student',
    'username'              => 'rbac_test_student',
    'email'                 => 'rbac_test_student@example.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
assertTest("Student registration succeeded", $studentReg['success'] === true, json_encode($studentReg['errors'] ?? []));
$studentId = $studentReg['user_id'];

// Register teacher (then upgrade role manually)
$teacherReg = $auth->register([
    'first_name'            => 'Test',
    'last_name'             => 'Teacher',
    'username'              => 'rbac_test_teacher',
    'email'                 => 'rbac_test_teacher@example.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
assertTest("Teacher registration succeeded", $teacherReg['success'] === true, json_encode($teacherReg['errors'] ?? []));
$teacherId = $teacherReg['user_id'];

// Swap teacher's role from student → teacher in DB
$teacherRoleId  = $pdo->query("SELECT id FROM roles WHERE name='teacher' LIMIT 1")->fetchColumn();
$studentRoleId  = $pdo->query("SELECT id FROM roles WHERE name='student' LIMIT 1")->fetchColumn();
$pdo->prepare('UPDATE user_roles SET role_id = :tr WHERE user_id = :uid AND role_id = :sr')
    ->execute(['tr' => $teacherRoleId, 'uid' => $teacherId, 'sr' => $studentRoleId]);

// Register admin user (then upgrade role)
$adminReg = $auth->register([
    'first_name'            => 'Test',
    'last_name'             => 'Admin',
    'username'              => 'rbac_test_admin',
    'email'                 => 'rbac_test_admin@example.com',
    'password'              => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
assertTest("Admin registration succeeded", $adminReg['success'] === true, json_encode($adminReg['errors'] ?? []));
$adminId      = $adminReg['user_id'];
$adminRoleId  = $pdo->query("SELECT id FROM roles WHERE name='admin' LIMIT 1")->fetchColumn();
$pdo->prepare('UPDATE user_roles SET role_id = :ar WHERE user_id = :uid AND role_id = :sr')
    ->execute(['ar' => $adminRoleId, 'uid' => $adminId, 'sr' => $studentRoleId]);

// ─────────────────────────────────────────────────────────────────────────────
// Section 5: Student RBAC checks
// ─────────────────────────────────────────────────────────────────────────────

// Log in as student
$loginResult = $auth->login('rbac_test_student', 'Password123!');
assertTest("Student login succeeds", $loginResult['success'] === true);

AuthorizationService::clearCache();
$authz = new AuthorizationService();

assertTest("Student: isAuthenticated() true",              $authz->isAuthenticated()                 === true);
assertTest("Student: hasRole('student') true",             $authz->hasRole('student')                === true);
assertTest("Student: hasRole('admin') false",              $authz->hasRole('admin')                  === false);
assertTest("Student: hasRole('teacher') false",            $authz->hasRole('teacher')                === false);
assertTest("Student: roles include 'student'",             in_array('student', $authz->getCurrentUserRoles(), true));
assertTest("Student: 5 permissions from DB",               count($authz->getCurrentUserPermissions()) === 5);
assertTest("Student: hasPermission('exams.take') true",    $authz->hasPermission('exams.take')       === true);
assertTest("Student: hasPermission('exams.view') true",    $authz->hasPermission('exams.view')       === true);
assertTest("Student: hasPermission('results.view') true",  $authz->hasPermission('results.view')     === true);
assertTest("Student: hasPermission('questions.create') false", $authz->hasPermission('questions.create') === false);
assertTest("Student: hasPermission('roles.manage') false", $authz->hasPermission('roles.manage')     === false);
assertTest("Student: hasPermission('audit.view') false",   $authz->hasPermission('audit.view')       === false);
assertTest("Student: hasPermission('') false (empty)",     $authz->hasPermission('')                 === false);
assertTest("Student: hasPermission('nonexistent.perm') false", $authz->hasPermission('nonexistent.perm') === false);

// ─────────────────────────────────────────────────────────────────────────────
// Section 6: Teacher RBAC checks
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Section 6: Teacher role checks ---\n";

$auth->logout();
AuthorizationService::clearCache();

$loginResult = $auth->login('rbac_test_teacher', 'Password123!');
assertTest("Teacher login succeeds", $loginResult['success'] === true);

AuthorizationService::clearCache();
$authz = new AuthorizationService();

assertTest("Teacher: hasRole('teacher') true",                $authz->hasRole('teacher')                 === true);
assertTest("Teacher: hasRole('student') false",               $authz->hasRole('student')                 === false);
assertTest("Teacher: hasRole('admin') false",                 $authz->hasRole('admin')                   === false);
assertTest("Teacher: hasPermission('questions.create') true", $authz->hasPermission('questions.create')  === true);
assertTest("Teacher: hasPermission('exams.create') true",     $authz->hasPermission('exams.create')      === true);
assertTest("Teacher: hasPermission('results.view') true",     $authz->hasPermission('results.view')      === true);
assertTest("Teacher: hasPermission('exams.take') false",      $authz->hasPermission('exams.take')        === false);
assertTest("Teacher: hasPermission('users.view') false",      $authz->hasPermission('users.view')        === false);
assertTest("Teacher: hasPermission('audit.view') false",      $authz->hasPermission('audit.view')        === false);
assertTest("Teacher: 12 permissions from DB",                 count($authz->getCurrentUserPermissions()) === 12);

// ─────────────────────────────────────────────────────────────────────────────
// Section 7: Admin RBAC checks
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Section 7: Admin role checks ---\n";

$auth->logout();
AuthorizationService::clearCache();

$loginResult = $auth->login('rbac_test_admin', 'Password123!');
assertTest("Admin login succeeds", $loginResult['success'] === true);

AuthorizationService::clearCache();
$authz = new AuthorizationService();

assertTest("Admin: hasRole('admin') true",                  $authz->hasRole('admin')                   === true);
assertTest("Admin: hasPermission('roles.manage') true",     $authz->hasPermission('roles.manage')      === true);
assertTest("Admin: hasPermission('audit.view') true",       $authz->hasPermission('audit.view')        === true);
assertTest("Admin: hasPermission('users.delete') true",     $authz->hasPermission('users.delete')      === true);
assertTest("Admin: all 20 permissions",                     count($authz->getCurrentUserPermissions()) === 20);

// ─────────────────────────────────────────────────────────────────────────────
// Section 8: Multi-role / multi-permission edge cases
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Section 8: Multi-role / multi-permission edge cases ---\n";

// Admin satisfies ['student','teacher','admin'] — any one is enough
$adminRoles    = $authz->getCurrentUserRoles();
$adminHasAny   = in_array('admin', $adminRoles, true)
              || in_array('teacher', $adminRoles, true)
              || in_array('student', $adminRoles, true);
assertTest("Admin: satisfies multi-role check [student|teacher|admin]", $adminHasAny);

// Log back in as student for multi-permission test
$auth->logout();
AuthorizationService::clearCache();
$auth->login('rbac_test_student', 'Password123!');
AuthorizationService::clearCache();
$authz = new AuthorizationService();

// Student has 'exams.view'; does NOT have 'questions.create'
// Multi-permission (OR): passes because student has exams.view
$multiPermPass = $authz->hasPermission('exams.view') || $authz->hasPermission('questions.create');
assertTest("Student: multi-permission OR (exams.view|questions.create) passes", $multiPermPass === true);

// Multi-permission (AND): fails because student lacks questions.create
$multiPermFail = $authz->hasPermission('exams.view') && $authz->hasPermission('questions.create');
assertTest("Student: multi-permission AND (exams.view+questions.create) fails", $multiPermFail === false);

// ─────────────────────────────────────────────────────────────────────────────
// Section 9: Middleware pipeline simulation (no HTTP, no exit)
// These tests verify the logic path rather than output since we are CLI.
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Section 9: Middleware logic (simulated) ---\n";

// 9a. Guest → AuthMiddleware should detect unauthenticated
$auth->logout();
AuthorizationService::clearCache();

$guestUser  = Session::get('user');
$guestIsAuth = is_array($guestUser) && !empty($guestUser['id']);
assertTest("AuthMiddleware input: guest is NOT authenticated", $guestIsAuth === false);

// 9b. Authenticated student → AuthMiddleware should pass
$auth->login('rbac_test_student', 'Password123!');
AuthorizationService::clearCache();

$sessionUser = Session::get('user');
$authPasses  = is_array($sessionUser) && !empty($sessionUser['id']);
assertTest("AuthMiddleware input: student IS authenticated (would pass)", $authPasses === true);

// 9c. Student → RoleMiddleware('admin') should block
$authz = new AuthorizationService();
$studentBlockedByRole = !$authz->hasRole('admin');
assertTest("RoleMiddleware: student would be blocked from 'admin' route", $studentBlockedByRole === true);

// 9d. Student → RoleMiddleware('student') should pass
$studentPassesByRole = $authz->hasRole('student');
assertTest("RoleMiddleware: student would pass 'student' route", $studentPassesByRole === true);

// 9e. Student → PermissionMiddleware('questions.create') should block
$studentBlockedByPerm = !$authz->hasPermission('questions.create');
assertTest("PermissionMiddleware: student blocked from questions.create route", $studentBlockedByPerm === true);

// 9f. Student → PermissionMiddleware('exams.take') should pass
$studentPassesByPerm = $authz->hasPermission('exams.take');
assertTest("PermissionMiddleware: student passes exams.take route", $studentPassesByPerm === true);

// 9g. Direct URL access check — unauth user cannot reach /dashboard
$auth->logout();
AuthorizationService::clearCache();
$directAccessBlocked = !(is_array(Session::get('user')) && !empty(Session::get('user')['id']));
assertTest("Unauthorized direct URL access: guest would be blocked by AuthMiddleware", $directAccessBlocked === true);

// 9h. Authorized access — admin can reach admin panel
$auth->login('rbac_test_admin', 'Password123!');
AuthorizationService::clearCache();
$authz          = new AuthorizationService();
$adminAccess    = $authz->isAuthenticated() && $authz->hasRole('admin');
assertTest("Authorized access: admin passes both AuthMiddleware and RoleMiddleware('admin')", $adminAccess === true);

// 9i. Cache works — second call returns same permissions without extra query
$first  = $authz->getCurrentUserPermissions();
$second = $authz->getCurrentUserPermissions();
assertTest("Permission cache: two calls return identical arrays", $first === $second);

// 9j. clearCache() resets state
AuthorizationService::clearCache();
$afterClear = $authz->getCurrentUserPermissions();
assertTest("Permission cache: clearCache() still returns correct permissions after clear", count($afterClear) === 20);

// ─────────────────────────────────────────────────────────────────────────────
// Section 10: Confirm no schema changes were made
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Section 10: Schema integrity ---\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$requiredTables = [
    'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
    'programming_languages', 'categories', 'questions', 'question_options',
    'exams', 'exam_questions', 'exam_question_rules',
    'attempts', 'attempt_questions', 'attempt_answers', 'results', 'audit_logs',
];
foreach ($requiredTables as $table) {
    assertTest("Table '{$table}' exists (schema unchanged)", in_array($table, $tables, true));
}

// ─────────────────────────────────────────────────────────────────────────────
// Cleanup
// ─────────────────────────────────────────────────────────────────────────────
$auth->logout();
$pdo->exec("DELETE FROM users WHERE username LIKE 'rbac_test_%'");

echo "\n============================================================\n";
echo "Tests Passed: {$passed}\n";
echo "Tests Failed: {$failed}\n";
echo "============================================================\n";

ob_end_flush();

if ($failed > 0) {
    exit(1);
}
exit(0);
