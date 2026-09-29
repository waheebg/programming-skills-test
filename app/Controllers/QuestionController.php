<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Models\Category;
use App\Models\ProgrammingLanguage;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\AuthorizationService;
use App\Services\QuestionBankService;

/**
 * QuestionController
 *
 * Handles CRUD for questions and their options.
 *
 * Permission map:
 *   - questions.view          → index, show
 *   - questions.create        → create, store
 *   - questions.update        → edit, update
 *   - questions.delete        → delete (archive or hard-delete)
 */
class QuestionController extends Controller
{
    private QuestionBankService $service;
    private AuthorizationService $authz;

    public function __construct()
    {
        $this->service = new QuestionBankService();
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

    private function csrfCheck(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            Session::setFlash('error', 'Security token expired or invalid. Please try again.');
            $this->redirect('/questions');
        }
    }

    private function currentUserId(): int
    {
        return (int) (Session::get('user')['id'] ?? 0);
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * GET /questions — paginated list with filters.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('questions.view');

        $filters = [
            'status'      => $_GET['status']      ?? '',
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

        $this->render('questions/index', [
            'questions'  => $result['data'],
            'total'      => $result['total'],
            'page'       => $page,
            'perPage'    => $perPage,
            'filters'    => $filters,
            'languages'  => $languages,
            'categories' => $categories,
            'canCreate'  => $this->authz->hasPermission('questions.create'),
            'canUpdate'  => $this->authz->hasPermission('questions.update'),
            'canDelete'  => $this->authz->hasPermission('questions.delete'),
            'success'    => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
        ]);
    }

    /**
     * GET /questions/show?id=N — view a single question with options.
     */
    public function show(): void
    {
        $this->requireAuth();
        $this->requirePermission('questions.view');

        $id       = (int) ($_GET['id'] ?? 0);
        $question = Question::findById($id);

        if (!$question) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $options = QuestionOption::forQuestion($id);

        $this->render('questions/show', [
            'question'  => $question,
            'options'   => $options,
            'canUpdate' => $this->authz->hasPermission('questions.update'),
            'canDelete' => $this->authz->hasPermission('questions.delete'),
        ]);
    }

    /**
     * GET /questions/create — show create form.
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->requirePermission('questions.create');

        $languages  = ProgrammingLanguage::all('active');
        $categories = Category::all('active');

        $this->render('questions/create', [
            'languages'  => $languages,
            'categories' => $categories,
            'errors'     => Session::getFlash('errors', []),
            'old'        => Session::getFlash('old', []),
        ]);
    }

    /**
     * POST /questions/store — persist new question + options.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission('questions.create');
        $this->csrfCheck();

        $result = $this->service->createQuestion($_POST, $this->currentUserId());

        if ($result['success']) {
            Session::setFlash('success', 'Question created successfully.');
            $this->redirect('/questions/show?id=' . $result['id']);
            return;
        }

        Session::setFlash('errors', $result['errors']);
        Session::setFlash('old', $_POST);
        $this->redirect('/questions/create');
    }

    /**
     * GET /questions/edit?id=N — show edit form.
     */
    public function edit(): void
    {
        $this->requireAuth();
        $this->requirePermission('questions.update');

        $id       = (int) ($_GET['id'] ?? 0);
        $question = Question::findById($id);

        if (!$question) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $options    = QuestionOption::forQuestion($id);
        $languages  = ProgrammingLanguage::all('active');
        $categories = Category::all('active');

        $this->render('questions/edit', [
            'question'   => $question,
            'options'    => $options,
            'languages'  => $languages,
            'categories' => $categories,
            'errors'     => Session::getFlash('errors', []),
            'old'        => Session::getFlash('old', []),
        ]);
    }

    /**
     * POST /questions/update — persist edits.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->requirePermission('questions.update');
        $this->csrfCheck();

        $id       = (int) ($_POST['id'] ?? 0);
        $question = Question::findById($id);

        if (!$question) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $result = $this->service->updateQuestion($id, $_POST);

        if ($result['success']) {
            Session::setFlash('success', 'Question updated successfully.');
            $this->redirect('/questions/show?id=' . $id);
            return;
        }

        Session::setFlash('errors', $result['errors']);
        Session::setFlash('old', $_POST);
        $this->redirect('/questions/edit?id=' . $id);
    }

    /**
     * POST /questions/delete — archive or hard-delete (safe).
     */
    public function delete(): void
    {
        $this->requireAuth();
        $this->requirePermission('questions.delete');
        $this->csrfCheck();

        $id       = (int) ($_POST['id'] ?? 0);
        $question = Question::findById($id);

        if (!$question) {
            Session::setFlash('error', 'Question not found.');
            $this->redirect('/questions');
            return;
        }

        $result = Question::safeDelete($id);

        if ($result['success']) {
            $msg = $result['action'] === 'archived'
                ? 'Question archived (it is referenced by exams/attempts).'
                : 'Question deleted successfully.';
            Session::setFlash('success', $msg);
        } else {
            Session::setFlash('error', 'Could not delete/archive the question. Please try again.');
        }

        $this->redirect('/questions');
    }
}
