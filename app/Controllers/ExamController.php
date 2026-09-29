<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Models\Attempt;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionRule;
use App\Models\ProgrammingLanguage;
use App\Models\Question;
use App\Services\AuthorizationService;
use App\Services\ExamBuilderService;

/**
 * ExamController
 *
 * Handles Exam CRUD, manual question selection, rule management,
 * and exam publishing (Phase 6).
 *
 * Permission map:
 *   - exams.view    → index, show
 *   - exams.create  → create, store
 *   - exams.update  → edit, update, addQuestion, removeQuestion,
 *                     addRule, updateRule, removeRule, publish
 *   - exams.delete  → delete
 */
class ExamController extends Controller
{
    private ExamBuilderService $service;
    private AuthorizationService $authz;

    public function __construct()
    {
        $this->service = new ExamBuilderService();
        $this->authz   = new AuthorizationService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function csrfCheck(string $redirectOn403 = '/exams'): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            Session::setFlash('error', 'Security token expired or invalid. Please try again.');
            $this->redirect($redirectOn403);
        }
    }

    private function currentUserId(): int
    {
        return (int) (Session::get('user')['id'] ?? 0);
    }

    private function findExamOrFail(int $id): array
    {
        $exam = Exam::findById($id);
        if (!$exam) {
            http_response_code(404);
            $this->render('errors/404');
            exit;
        }
        return $exam;
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * GET /exams — paginated list with filters.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.view');

        $filters = [
            'status'         => $_GET['status']         ?? '',
            'selection_mode' => $_GET['selection_mode'] ?? '',
            'language_id'    => $_GET['language_id']    ?? '',
            'search'         => trim($_GET['search']    ?? ''),
        ];

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;

        $result    = Exam::paginate($filters, $page, $perPage);
        $languages = ProgrammingLanguage::all('active');

        $this->render('exams/index', [
            'exams'     => $result['data'],
            'total'     => $result['total'],
            'page'      => $page,
            'perPage'   => $perPage,
            'filters'   => $filters,
            'languages' => $languages,
            'canCreate' => $this->authz->hasPermission('exams.create'),
            'canUpdate' => $this->authz->hasPermission('exams.update'),
            'canDelete' => $this->authz->hasPermission('exams.delete'),
            'canTake'   => $this->authz->hasPermission('exams.take'),
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    /**
     * GET /exams/show?id=N — view a single exam with questions and rules.
     */
    public function show(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.view');

        $id   = (int) ($_GET['id'] ?? 0);
        $exam = $this->findExamOrFail($id);

        $manualQuestions = ExamQuestion::forExam($id);
        $rules           = ExamQuestionRule::forExam($id);

        $canTake = $this->authz->hasPermission('exams.take');
        $activeAttempt = null;
        $attemptsUsed = 0;
        if ($canTake) {
            $userId = $this->currentUserId();
            $activeAttempt = Attempt::findActive($id, $userId);
            $attemptsUsed  = Attempt::countForUserAndExam($id, $userId);
        }

        $this->render('exams/show', [
            'exam'            => $exam,
            'manualQuestions' => $manualQuestions,
            'rules'           => $rules,
            'canUpdate'       => $this->authz->hasPermission('exams.update'),
            'canDelete'       => $this->authz->hasPermission('exams.delete'),
            'canTake'         => $canTake,
            'activeAttempt'   => $activeAttempt,
            'attemptsUsed'    => $attemptsUsed,
            'success'         => Session::getFlash('success'),
            'error'           => Session::getFlash('error'),
        ]);
    }

    /**
     * GET /exams/create — show create form.
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.create');

        $languages = ProgrammingLanguage::all('active');

        $this->render('exams/create', [
            'languages' => $languages,
            'errors'    => Session::getFlash('errors', []),
            'old'       => Session::getFlash('old', []),
        ]);
    }

    /**
     * POST /exams/store — persist new exam.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.create');
        $this->csrfCheck('/exams/create');

        $result = $this->service->createExam($_POST, $this->currentUserId());

        if ($result['success']) {
            Session::setFlash('success', 'Exam created successfully.');
            $this->redirect('/exams/show?id=' . $result['id']);
            return;
        }

        Session::setFlash('errors', $result['errors']);
        Session::setFlash('old', $_POST);
        $this->redirect('/exams/create');
    }

    /**
     * GET /exams/edit?id=N — show edit form.
     */
    public function edit(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $id   = (int) ($_GET['id'] ?? 0);
        $exam = $this->findExamOrFail($id);

        $languages = ProgrammingLanguage::all('active');

        $this->render('exams/edit', [
            'exam'      => $exam,
            'languages' => $languages,
            'errors'    => Session::getFlash('errors', []),
            'old'       => Session::getFlash('old', []),
        ]);
    }

    /**
     * POST /exams/update — persist exam edits.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $id   = (int) ($_POST['id'] ?? 0);
        $exam = $this->findExamOrFail($id);

        $this->csrfCheck('/exams/edit?id=' . $id);

        $result = $this->service->updateExam($id, $_POST);

        if ($result['success']) {
            Session::setFlash('success', 'Exam updated successfully.');
            $this->redirect('/exams/show?id=' . $id);
            return;
        }

        Session::setFlash('errors', $result['errors']);
        Session::setFlash('old', $_POST);
        $this->redirect('/exams/edit?id=' . $id);
    }

    /**
     * POST /exams/delete — archive or delete an exam.
     */
    public function delete(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.delete');
        $this->csrfCheck('/exams');

        $id   = (int) ($_POST['id'] ?? 0);
        $exam = Exam::findById($id);

        if (!$exam) {
            Session::setFlash('error', 'Exam not found.');
            $this->redirect('/exams');
            return;
        }

        $result = $this->service->deleteExam($id);

        if ($result['success']) {
            Session::setFlash('success', 'Exam deleted successfully.');
        } else {
            Session::setFlash('error', 'Could not delete the exam. Please try again.');
        }

        $this->redirect('/exams');
    }

    /**
     * POST /exams/publish — publish an exam after full validation.
     */
    public function publish(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $id = (int) ($_POST['id'] ?? 0);
        $this->findExamOrFail($id);

        $this->csrfCheck('/exams/show?id=' . $id);

        $result = $this->service->publishExam($id);

        if ($result['success']) {
            Session::setFlash('success', 'Exam published successfully.');
        } else {
            Session::setFlash('error', implode(' ', $result['errors']));
        }

        $this->redirect('/exams/show?id=' . $id);
    }

    // ── Manual Question Selection ─────────────────────────────────────────────

    /**
     * GET /exams/questions?id=N — question picker for manual selection.
     */
    public function questions(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $examId = (int) ($_GET['id'] ?? 0);
        $exam   = $this->findExamOrFail($examId);

        $filters = [
            'status'      => 'published', // Only published questions can be selected
            'difficulty'  => $_GET['difficulty']  ?? '',
            'language_id' => $_GET['language_id'] ?? '',
            'category_id' => $_GET['category_id'] ?? '',
            'type'        => $_GET['type']         ?? '',
            'search'      => trim($_GET['search']  ?? ''),
        ];

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;

        $result    = Question::paginate($filters, $page, $perPage);
        $languages = ProgrammingLanguage::all('active');
        $categories = Category::all('active');

        // Get already-selected question IDs to highlight them
        $selectedIds = ExamQuestion::questionIds($examId);

        $this->render('exams/questions', [
            'exam'        => $exam,
            'questions'   => $result['data'],
            'total'       => $result['total'],
            'page'        => $page,
            'perPage'     => $perPage,
            'filters'     => $filters,
            'languages'   => $languages,
            'categories'  => $categories,
            'selectedIds' => $selectedIds,
            'success'     => Session::getFlash('success'),
            'error'       => Session::getFlash('error'),
        ]);
    }

    /**
     * POST /exams/questions/add — add a question to an exam.
     */
    public function addQuestion(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $examId     = (int) ($_POST['exam_id']     ?? 0);
        $questionId = (int) ($_POST['question_id'] ?? 0);

        $this->findExamOrFail($examId);
        $this->csrfCheck('/exams/questions?id=' . $examId);

        $result = $this->service->addQuestion($examId, $questionId);

        if ($result['success']) {
            Session::setFlash('success', 'Question added to exam.');
        } else {
            Session::setFlash('error', implode(' ', $result['errors']));
        }

        // Validate $back to prevent open redirect — only allow internal relative paths
        $back = $_POST['back'] ?? '';
        if (empty($back) || !preg_match('#^/[^/\\\\]#', $back)) {
            $back = '/exams/questions?id=' . $examId;
        }
        $this->redirect($back);
    }

    /**
     * POST /exams/questions/add-bulk — add multiple questions at once.
     */
    public function addQuestionsBulk(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $examId = (int) ($_POST['exam_id'] ?? 0);
        $this->findExamOrFail($examId);
        $this->csrfCheck('/exams/questions?id=' . $examId);

        $questionIds = $_POST['question_ids'] ?? [];
        if (!is_array($questionIds)) {
            $questionIds = [];
        }

        $result = $this->service->addQuestions($examId, $questionIds);

        if ($result['success']) {
            Session::setFlash('success', "{$result['inserted']} question(s) added to exam.");
        } else {
            Session::setFlash('error', implode(' ', $result['errors']));
        }

        $this->redirect('/exams/questions?id=' . $examId);
    }

    /**
     * POST /exams/questions/remove — remove a question from an exam.
     */
    public function removeQuestion(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $examId     = (int) ($_POST['exam_id']     ?? 0);
        $questionId = (int) ($_POST['question_id'] ?? 0);

        $this->findExamOrFail($examId);
        $this->csrfCheck('/exams/show?id=' . $examId);

        $result = $this->service->removeQuestion($examId, $questionId);

        if ($result['success']) {
            Session::setFlash('success', 'Question removed from exam.');
        } else {
            Session::setFlash('error', implode(' ', $result['errors']));
        }

        $this->redirect('/exams/show?id=' . $examId);
    }

    // ── Selection Rules ────────────────────────────────────────────────────────

    /**
     * GET /exams/rules?id=N — rule management page.
     */
    public function rules(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $examId = (int) ($_GET['id'] ?? 0);
        $exam   = $this->findExamOrFail($examId);

        $rules      = ExamQuestionRule::forExam($examId);
        $languages  = ProgrammingLanguage::all('active');
        $categories = Category::all('active');

        $this->render('exams/rules', [
            'exam'       => $exam,
            'rules'      => $rules,
            'languages'  => $languages,
            'categories' => $categories,
            'success'    => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
            'errors'     => Session::getFlash('errors', []),
            'old'        => Session::getFlash('old', []),
        ]);
    }

    /**
     * POST /exams/rules/store — add a new rule.
     */
    public function storeRule(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $examId = (int) ($_POST['exam_id'] ?? 0);
        $this->findExamOrFail($examId);
        $this->csrfCheck('/exams/rules?id=' . $examId);

        $result = $this->service->addRule($examId, $_POST);

        if ($result['success']) {
            Session::setFlash('success', 'Rule added successfully.');
        } else {
            Session::setFlash('errors', $result['errors']);
            Session::setFlash('old', $_POST);
        }

        $this->redirect('/exams/rules?id=' . $examId);
    }

    /**
     * POST /exams/rules/update — update an existing rule.
     */
    public function updateRule(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        $rule   = ExamQuestionRule::findById($ruleId);

        if (!$rule) {
            Session::setFlash('error', 'Rule not found.');
            $this->redirect('/exams');
            return;
        }

        $examId = (int) $rule['exam_id'];
        $this->findExamOrFail($examId);
        $this->csrfCheck('/exams/rules?id=' . $examId);

        $result = $this->service->updateRule($ruleId, $_POST);

        if ($result['success']) {
            Session::setFlash('success', 'Rule updated successfully.');
        } else {
            Session::setFlash('errors', $result['errors']);
            Session::setFlash('old', $_POST);
        }

        $this->redirect('/exams/rules?id=' . $examId);
    }

    /**
     * POST /exams/rules/delete — remove a rule.
     */
    public function deleteRule(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.update');

        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        $rule   = ExamQuestionRule::findById($ruleId);

        if (!$rule) {
            Session::setFlash('error', 'Rule not found.');
            $this->redirect('/exams');
            return;
        }

        $examId = (int) $rule['exam_id'];
        $this->findExamOrFail($examId);
        $this->csrfCheck('/exams/rules?id=' . $examId);

        $result = $this->service->removeRule($ruleId);

        if ($result['success']) {
            Session::setFlash('success', 'Rule removed.');
        } else {
            Session::setFlash('error', implode(' ', $result['errors']));
        }

        $this->redirect('/exams/rules?id=' . $examId);
    }

    /**
     * GET /exams/preview?id=N — preview hybrid selection result.
     */
    public function preview(): void
    {
        $this->requireAuth();
        $this->requirePermission('exams.view');

        $examId = (int) ($_GET['id'] ?? 0);
        $exam   = $this->findExamOrFail($examId);

        $preview = $this->service->previewHybridSelection($examId);

        $this->render('exams/preview', [
            'exam'    => $exam,
            'preview' => $preview,
            'success' => Session::getFlash('success'),
            'error'   => Session::getFlash('error'),
        ]);
    }
}
