<?php

// Test Suite for Phase 4A: Authentication & Session Security
define('BASE_PATH', dirname(__DIR__));

// Autoloader for App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Core\Database;
use App\Core\Session;
use App\Core\Csrf;
use App\Models\User;
use App\Services\AuthService;
use App\Controllers\AuthController;

// Start output buffering so headers are not sent during test run
ob_start();

// Start Session before emitting any output so cookie settings and strict mode apply
Session::start();

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passed++;
    } else {
        echo "[FAIL] " . $description . ($details ? " -> " . $details : "") . "\n";
        $failed++;
    }
}

echo "============================================================\n";
echo "Starting Phase 4A Verification Tests\n";
echo "============================================================\n\n";

$pdo = Database::getConnection();

// Clean up existing test accounts if any
$pdo->exec("DELETE FROM users WHERE username LIKE 'test_user_%'");

// ---------------------------------------------------------
// Test 1: Secure Session Configuration
// ---------------------------------------------------------
echo "--- Testing Session Security Configuration ---\n";
assertTest("Session started active", session_status() === PHP_SESSION_ACTIVE);
assertTest("session.use_strict_mode is enabled", ini_get('session.use_strict_mode') === '1');
assertTest("session.use_only_cookies is enabled", ini_get('session.use_only_cookies') === '1');

$cookieParams = session_get_cookie_params();
assertTest("Session cookie httponly is true", $cookieParams['httponly'] === true);
assertTest("Session cookie samesite is Lax", strtolower($cookieParams['samesite']) === 'lax');

// ---------------------------------------------------------
// Test 2: CSRF Protection
// ---------------------------------------------------------
echo "\n--- Testing CSRF Protection ---\n";
$token = Csrf::getToken();
assertTest("CSRF token generated (64 hex characters)", strlen($token) === 64 && ctype_xdigit($token));
assertTest("CSRF token stored in session matches", Session::get('_csrf_token') === $token);
assertTest("Valid CSRF token validates true", Csrf::validate($token) === true);
assertTest("Invalid CSRF token validates false", Csrf::validate('invalid_token_12345') === false);
assertTest("Empty CSRF token validates false", Csrf::validate('') === false);
assertTest("Null CSRF token validates false", Csrf::validate(null) === false);

$fieldHtml = Csrf::field();
assertTest("CSRF field() contains hidden input and token", strpos($fieldHtml, '<input type="hidden" name="csrf_token"') !== false && strpos($fieldHtml, $token) !== false);

// ---------------------------------------------------------
// Test 3: User Registration & Default 'student' Role
// ---------------------------------------------------------
echo "\n--- Testing Registration & Role Assignment ---\n";
$auth = new AuthService();

$regInput = [
    'first_name'            => 'Ahmed',
    'last_name'             => 'Ali',
    'username'              => 'test_user_ahmed',
    'email'                 => 'test_user_ahmed@example.com',
    'password'              => 'SecretPassword123!',
    'password_confirmation' => 'SecretPassword123!',
];

$regResult = $auth->register($regInput);
assertTest("User registration succeeds", $regResult['success'] === true && !empty($regResult['user_id']));

$createdUserId = $regResult['user_id'];
$dbUser = User::findById($createdUserId);
assertTest("User record exists in database", $dbUser !== null && $dbUser['username'] === 'test_user_ahmed');
assertTest("Password hash is not plaintext", $dbUser['password_hash'] !== $regInput['password'] && strpos($dbUser['password_hash'], '$2y$') === 0);
assertTest("User status is active", $dbUser['status'] === 'active');

// Verify default role
$roles = User::getRoles($createdUserId);
assertTest("User assigned default 'student' role in user_roles", in_array('student', $roles, true));

// Test Duplicate Registration
$dupResult = $auth->register($regInput);
assertTest("Duplicate username registration is rejected", $dupResult['success'] === false && isset($dupResult['errors']['username']));
assertTest("Duplicate email registration is rejected", $dupResult['success'] === false && isset($dupResult['errors']['email']));

// ---------------------------------------------------------
// Test 4: Password Verification
// ---------------------------------------------------------
echo "\n--- Testing Password Verification ---\n";
assertTest("password_verify returns true for correct password", password_verify('SecretPassword123!', $dbUser['password_hash']));
assertTest("password_verify returns false for wrong password", !password_verify('WrongPassword999', $dbUser['password_hash']));

// ---------------------------------------------------------
// Test 5: Login with Invalid Credentials
// ---------------------------------------------------------
echo "\n--- Testing Invalid Login ---\n";
$invalidLogin1 = $auth->login('non_existent_user', 'some_password');
assertTest("Login fails for non-existent user", $invalidLogin1['success'] === false && !empty($invalidLogin1['error']));

$invalidLogin2 = $auth->login('test_user_ahmed', 'wrong_password_attempt');
assertTest("Login fails for wrong password", $invalidLogin2['success'] === false && !empty($invalidLogin2['error']));

// ---------------------------------------------------------
// Test 6: Valid Login & Session Regeneration
// ---------------------------------------------------------
echo "\n--- Testing Valid Login & Session Regeneration ---\n";
$sessionIdBefore = session_id();

$loginResult = $auth->login('test_user_ahmed', 'SecretPassword123!');
$sessionIdAfter = session_id();

assertTest("Login succeeds with valid credentials (by username)", $loginResult['success'] === true && !empty($loginResult['user']));
assertTest("Session ID regenerated on login", $sessionIdBefore !== $sessionIdAfter);
assertTest("Session user payload does not expose password_hash", !isset($loginResult['user']['password_hash']));
assertTest("Session contains correct user details", $loginResult['user']['username'] === 'test_user_ahmed' && $loginResult['user']['email'] === 'test_user_ahmed@example.com');
assertTest("Session user has 'student' role", in_array('student', $loginResult['user']['roles'], true));
assertTest("AuthService::check() reports logged-in", $auth->check() === true);
assertTest("AuthService::user() returns logged-in user", $auth->user()['id'] === $createdUserId);

// Test Login by Email
$emailLogin = $auth->login('test_user_ahmed@example.com', 'SecretPassword123!');
assertTest("Login succeeds with valid credentials (by email)", $emailLogin['success'] === true);

// ---------------------------------------------------------
// Test 7: Session Persistence
// ---------------------------------------------------------
echo "\n--- Testing Session Persistence ---\n";
Session::set('custom_key', 'custom_value');
assertTest("Session value persists", Session::get('custom_key') === 'custom_value');
Session::setFlash('notice', 'Flash message test');
assertTest("Flash message retrievable", Session::getFlash('notice') === 'Flash message test');
assertTest("Flash message cleared after retrieval", Session::getFlash('notice') === null);

// ---------------------------------------------------------
// Test 8: Logout
// ---------------------------------------------------------
echo "\n--- Testing Logout ---\n";
$auth->logout();
assertTest("User logged out (check() returns false)", $auth->check() === false);
assertTest("Session user is null after logout", $auth->user() === null);

// ---------------------------------------------------------
// Test 9: Controller CSRF Rejection on POST
// ---------------------------------------------------------
echo "\n--- Testing Controller CSRF Handling ---\n";
Session::start();
$controller = new AuthController();

// Simulate POST without CSRF token
$_POST = [
    'identifier' => 'test_user_ahmed',
    'password'   => 'SecretPassword123!',
];
// Capture output
ob_start();
$controller->login();
$output = ob_get_clean();
assertTest("Controller rejects login POST with missing CSRF token", strpos($output, 'Security token expired or invalid') !== false);

// Simulate POST with invalid CSRF token
$_POST['csrf_token'] = 'tampered_csrf_token';
ob_start();
$controller->login();
$output = ob_get_clean();
assertTest("Controller rejects login POST with invalid CSRF token", strpos($output, 'Security token expired or invalid') !== false);

// Clean up test data
$pdo->exec("DELETE FROM users WHERE username LIKE 'test_user_%'");

echo "\n============================================================\n";
echo "Tests Passed: {$passed}\n";
echo "Tests Failed: {$failed}\n";
echo "============================================================\n";

ob_end_flush();

if ($failed > 0) {
    exit(1);
}
exit(0);
