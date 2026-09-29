<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Models\Attempt;
use App\Services\AuthorizationService;
use App\Services\ExamAttemptService;
use App\Services\ExamGradingService;

/**
 * AttemptController
 *
 * Handles student exam attempts, taking interface, answer saving,
 * and exam submission (Phase 7).
 *
 * Permission required: exams.take (granted to student and admin)
 */
class AttemptController extends Controller
{
    private ExamAttemptService $service;
    private AuthorizationService $authz;

    public function __construct()
    {
        $this->service = new ExamAttemptService();
        $this->authz   = new AuthorizationService();
    }

    // ── Security & Auth Helpers ────────────────────────────────────────────────

    private function requireAuth(): void
    {
        if (!$this->authz->isAuthenticated()) {
            Session::setFlash('error', 'You must be logged in.');
            $this->redirect('/login');
        }
    }

    private function requirePermission(string $perm): void
    {
        if (!$this->authz->hasPermission($perm)) {
            http_response_code(403);
            $this->render('errors/403');
            exit;
        }
    }

    private function csrfCheck(string $redirectOnFail = '/exams'): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::validate($token)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'errors' => ['Security token expired or invalid.']], 419);
                exit;
            }
            http_response_code(419);
            Session::setFlash('error', 'Security token expired or invalid. Please try again.');
            $this->redirect($redirectOnFail);
        }
    }

    private function currentUserId(): int
    {
        return (int) (Session::get('user')['id'] ?? 0);
    }

    private function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (!empty($_POST['ajax']))
            || (!empty($_GET['json']));
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * POST /attempts/start — Start a new exam attempt (or resume active attempt).
     */
    public function start(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.take');
        $this->csrfCheck();

        $examId = (int) ($_POST['exam_id'] ?? 0);
        if (!$examId) {
            Session::setFlash('error', 'Invalid exam ID.');
            $this->redirect('/exams');
        }

        $userId = $this->currentUserId();
        $result = $this->service->startAttempt($examId, $userId);

        if (!$result['success']) {
            Session::setFlash('error', implode(' ', $result['errors']));
            $this->redirect('/exams/show?id=' . $examId);
        }

        $attempt = $result['attempt'];
        if ($result['resumed']) {
            Session::setFlash('info', 'Resuming your active exam attempt.');
        } else {
            Session::setFlash('success', 'Exam started. Good luck!');
        }

        $this->redirect('/attempts/take?id=' . (int) $attempt['id']);
    }

    /**
     * GET /attempts/take — Student exam taking interface.
     */
    public function take(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.take');

        $attemptId = (int) ($_GET['id'] ?? 0);
        if (!$attemptId) {
            Session::setFlash('error', 'Invalid attempt ID.');
            $this->redirect('/exams');
        }

        $userId = $this->currentUserId();
        $result = $this->service->getAttemptForTaking($attemptId, $userId);

        if (!$result['success']) {
            if (!empty($result['expired']) || !empty($result['completed'])) {
                Session::setFlash('info', implode(' ', $result['errors'] ?? []));
                $this->redirect('/attempts/complete?id=' . $attemptId);
            }
            Session::setFlash('error', implode(' ', $result['errors'] ?? []));
            $this->redirect('/exams');
        }

        $this->render('attempts/take', [
            'attempt'           => $result['attempt'],
            'exam'              => $result['exam'],
            'questions'         => $result['questions'],
            'answers'           => $result['answers'],
            'remaining_seconds' => $result['remaining_seconds'],
            'success'           => Session::getFlash('success'),
            'error'             => Session::getFlash('error'),
            'info'              => Session::getFlash('info'),
        ]);
    }

    /**
     * POST /attempts/save-answer — Save or update an answer (supports AJAX and form post).
     */
    public function saveAnswer(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.take');

        $attemptId = (int) ($_POST['attempt_id'] ?? 0);
        $this->csrfCheck('/attempts/take?id=' . $attemptId);

        $attemptQuestionId = (int) ($_POST['attempt_question_id'] ?? 0);
        $selectedOptionId  = isset($_POST['selected_option_id']) && $_POST['selected_option_id'] !== ''
                             ? (int) $_POST['selected_option_id']
                             : null;
        $answerText        = isset($_POST['answer_text']) ? trim($_POST['answer_text']) : null;

        $userId = $this->currentUserId();
        $result = $this->service->saveAnswer(
            $attemptId,
            $userId,
            $attemptQuestionId,
            $selectedOptionId,
            $answerText
        );

        if ($this->isAjax()) {
            $status = $result['success'] ? 200 : 400;
            $this->json($result, $status);
            return;
        }

        if (!$result['success']) {
            Session::setFlash('error', implode(' ', $result['errors']));
        }

        $this->redirect('/attempts/take?id=' . $attemptId);
    }

    /**
     * POST /attempts/submit — Manually submit or auto-expire an exam attempt.
     */
    public function submit(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.take');

        $attemptId = (int) ($_POST['attempt_id'] ?? 0);
        $this->csrfCheck('/attempts/take?id=' . $attemptId);

        $userId = $this->currentUserId();
        $result = $this->service->submitAttempt($attemptId, $userId);

        if (!$result['success']) {
            Session::setFlash('error', implode(' ', $result['errors']));
            $this->redirect('/attempts/take?id=' . $attemptId);
        }

        if ($result['status'] === Attempt::STATUS_EXPIRED) {
            Session::setFlash('warning', 'Exam time has expired. Your attempt has been submitted.');
        } else {
            Session::setFlash('success', 'Exam submitted successfully.');
        }

        $this->redirect('/attempts/complete?id=' . $attemptId);
    }

    /**
     * GET /attempts/complete — Attempt summary after submission or expiration.
     */
    public function complete(): void
    {
        $this->requireAuth();

        $attemptId = (int) ($_GET['id'] ?? 0);
        if (!$attemptId) {
            $this->redirect('/exams');
        }

        $userId  = $this->currentUserId();
        $summary = $this->service->getAttemptSummary($attemptId, $userId);

        if (!$summary) {
            Session::setFlash('error', 'Attempt summary not found or access denied.');
            $this->redirect('/exams');
        }

        $this->render('attempts/complete', [
            'attempt'           => $summary['attempt'],
            'total_questions'   => $summary['total_questions'],
            'answered_questions'=> $summary['answered_questions'],
            'success'           => Session::getFlash('success'),
            'warning'           => Session::getFlash('warning'),
            'error'             => Session::getFlash('error'),
            'info'              => Session::getFlash('info'),
        ]);
    }

    /**
     * GET /attempts/result — View student result page (Phase 8).
     */
    public function result(): void
    {
        $this->requireAuth();
        $this->requirePermission('results.view');

        $attemptId = (int) ($_GET['id'] ?? 0);
        if (!$attemptId) {
            Session::setFlash('error', 'Invalid attempt ID.');
            $this->redirect('/exams');
        }

        $userId  = $this->currentUserId();
        $isStaff = $this->authz->hasRole('admin') || $this->authz->hasRole('teacher');

        $gradingService = new ExamGradingService();
        $data = $gradingService->getStudentResult($attemptId, $userId, $isStaff);

        if (!$data['success']) {
            if (isset($data['errors'][0]) && strpos($data['errors'][0], 'Access denied') !== false) {
                http_response_code(403);
                $this->render('errors/403');
                exit;
            }
            Session::setFlash('error', implode(' ', $data['errors']));
            $this->redirect('/exams');
        }

        $this->render('attempts/result', [
            'attempt'                  => $data['attempt'],
            'exam'                     => $data['exam'],
            'result'                   => $data['result'],
            'questions'                => $data['questions'],
            'can_view_result'          => $data['can_view_result'],
            'can_view_correct_answers' => $data['can_view_correct_answers'],
            'is_staff'                 => $isStaff,
            'success'                  => Session::getFlash('success'),
            'warning'                  => Session::getFlash('warning'),
            'error'                    => Session::getFlash('error'),
            'info'                     => Session::getFlash('info'),
        ]);
    }
}
