<?php

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\LanguageController;
use App\Controllers\CategoryController;
use App\Controllers\QuestionController;
use App\Controllers\ExamController;
use App\Controllers\AttemptController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\PermissionMiddleware;

/**
 * Application Routes
 *
 * @var Router $router
 */

// Home route
$router->get('/', [HomeController::class, 'index']);

// Authentication routes
$router->get('/login',    [AuthController::class, 'showLoginForm']);
$router->post('/login',   [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegisterForm']);
$router->post('/register',[AuthController::class, 'register']);
$router->post('/logout',  [AuthController::class, 'logout']);

// ---------------------------------------------------------------------------
// Protected route examples — Phase 4B RBAC
//
// Each route below demonstrates server-side enforcement via middleware.
// The middleware callables run before the route handler; any unauthorised
// access is halted before the handler body executes.
// ---------------------------------------------------------------------------

// Any authenticated user (student, teacher, admin) — requires login only
$router->get('/dashboard', [HomeController::class, 'dashboard'], [
    function () { AuthMiddleware::handle(); },
]);

// Requires 'admin' role
$router->get('/admin', [HomeController::class, 'admin'], [
    function () { AuthMiddleware::handle(); },
    function () { RoleMiddleware::handle('admin'); },
]);

// Requires 'exams.take' permission (granted to student & admin)
$router->get('/exams/take', [HomeController::class, 'takeExam'], [
    function () { AuthMiddleware::handle(); },
    function () { PermissionMiddleware::handle('exams.take'); },
]);

// ---------------------------------------------------------------------------
// Phase 5 — Question Bank
// ---------------------------------------------------------------------------

// ── Programming Languages ──────────────────────────────────────────────────
$router->get( '/languages',        [LanguageController::class, 'index']);
$router->get( '/languages/create', [LanguageController::class, 'create']);
$router->post('/languages/store',  [LanguageController::class, 'store']);
$router->get( '/languages/edit',   [LanguageController::class, 'edit']);
$router->post('/languages/update', [LanguageController::class, 'update']);
$router->post('/languages/delete', [LanguageController::class, 'delete']);

// ── Categories ─────────────────────────────────────────────────────────────
$router->get( '/categories',        [CategoryController::class, 'index']);
$router->get( '/categories/create', [CategoryController::class, 'create']);
$router->post('/categories/store',  [CategoryController::class, 'store']);
$router->get( '/categories/edit',   [CategoryController::class, 'edit']);
$router->post('/categories/update', [CategoryController::class, 'update']);
$router->post('/categories/delete', [CategoryController::class, 'delete']);

// ── Questions ──────────────────────────────────────────────────────────────
$router->get( '/questions',        [QuestionController::class, 'index']);
$router->get( '/questions/show',   [QuestionController::class, 'show']);
$router->get( '/questions/create', [QuestionController::class, 'create']);
$router->post('/questions/store',  [QuestionController::class, 'store']);
$router->get( '/questions/edit',   [QuestionController::class, 'edit']);
$router->post('/questions/update', [QuestionController::class, 'update']);
$router->post('/questions/delete', [QuestionController::class, 'delete']);

// ---------------------------------------------------------------------------
// Phase 6 — Exam Builder
// ---------------------------------------------------------------------------

// ── Exam CRUD ───────────────────────────────────────────────────────────────
$router->get( '/exams',         [ExamController::class, 'index']);
$router->get( '/exams/show',    [ExamController::class, 'show']);
$router->get( '/exams/create',  [ExamController::class, 'create']);
$router->post('/exams/store',   [ExamController::class, 'store']);
$router->get( '/exams/edit',    [ExamController::class, 'edit']);
$router->post('/exams/update',  [ExamController::class, 'update']);
$router->post('/exams/delete',  [ExamController::class, 'delete']);
$router->post('/exams/publish', [ExamController::class, 'publish']);

// ── Manual Question Selection ────────────────────────────────────────────────
$router->get( '/exams/questions',          [ExamController::class, 'questions']);
$router->post('/exams/questions/add',      [ExamController::class, 'addQuestion']);
$router->post('/exams/questions/add-bulk', [ExamController::class, 'addQuestionsBulk']);
$router->post('/exams/questions/remove',   [ExamController::class, 'removeQuestion']);

// ── Selection Rules ──────────────────────────────────────────────────────────
$router->get( '/exams/rules',         [ExamController::class, 'rules']);
$router->post('/exams/rules/store',   [ExamController::class, 'storeRule']);
$router->post('/exams/rules/update',  [ExamController::class, 'updateRule']);
$router->post('/exams/rules/delete',  [ExamController::class, 'deleteRule']);

// ── Hybrid Preview ───────────────────────────────────────────────────────────
$router->get( '/exams/preview',       [ExamController::class, 'preview']);

// ---------------------------------------------------------------------------
// Phase 7 — Exam Taking Engine & Attempts
// ---------------------------------------------------------------------------
$router->post('/attempts/start',       [AttemptController::class, 'start']);
$router->get( '/attempts/take',        [AttemptController::class, 'take']);
$router->post('/attempts/save-answer', [AttemptController::class, 'saveAnswer']);
$router->post('/attempts/submit',      [AttemptController::class, 'submit']);
$router->get( '/attempts/complete',    [AttemptController::class, 'complete']);

// ---------------------------------------------------------------------------
// Phase 8 — Grading, Evaluation & Results
// ---------------------------------------------------------------------------
$router->get('/attempts/result',       [AttemptController::class, 'result']);
$router->get('/results',               [AttemptController::class, 'result']);


