<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Models\ProgrammingLanguage;
use App\Services\AuthorizationService;
use App\Services\QuestionBankService;

/**
 * LanguageController
 *
 * Handles CRUD for programming_languages.
 * Requires `languages.manage` permission for create/update/delete.
 * Requires `languages.view` permission (or manage) for index/show.
 */
class LanguageController extends Controller
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
            $this->redirect('/languages');
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * GET /languages — list all languages.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('languages.view');

        $languages = ProgrammingLanguage::all('all');

        $this->render('languages/index', [
            'languages' => $languages,
            'canManage' => $this->authz->hasPermission('languages.manage'),
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    /**
     * GET /languages/create — show create form.
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->requirePermission('languages.manage');

        $this->render('languages/create', [
            'errors' => Session::getFlash('errors', []),
            'old'    => Session::getFlash('old', []),
        ]);
    }

    /**
     * POST /languages/store — persist new language.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission('languages.manage');
        $this->csrfCheck();

        $result = $this->service->createLanguage($_POST);

        if ($result['success']) {
            Session::setFlash('success', 'Programming language created successfully.');
            $this->redirect('/languages');
            return;
        }

        Session::setFlash('errors', $result['errors']);
        Session::setFlash('old', [
            'name'        => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'status'      => trim($_POST['status'] ?? 'active'),
        ]);
        $this->redirect('/languages/create');
    }

    /**
     * GET /languages/edit?id=N — show edit form.
     */
    public function edit(): void
    {
        $this->requireAuth();
        $this->requirePermission('languages.manage');

        $id   = (int) ($_GET['id'] ?? 0);
        $lang = ProgrammingLanguage::findById($id);

        if (!$lang) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $this->render('languages/edit', [
            'language' => $lang,
            'errors'   => Session::getFlash('errors', []),
            'old'      => Session::getFlash('old', []),
        ]);
    }

    /**
     * POST /languages/update — persist edit.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->requirePermission('languages.manage');
        $this->csrfCheck();

        $id   = (int) ($_POST['id'] ?? 0);
        $lang = ProgrammingLanguage::findById($id);

        if (!$lang) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $result = $this->service->updateLanguage($id, $_POST);

        if ($result['success']) {
            Session::setFlash('success', 'Programming language updated successfully.');
            $this->redirect('/languages');
            return;
        }

        Session::setFlash('errors', $result['errors']);
        Session::setFlash('old', [
            'name'        => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'status'      => trim($_POST['status'] ?? 'active'),
        ]);
        $this->redirect('/languages/edit?id=' . $id);
    }

    /**
     * POST /languages/delete — delete a language (with FK guard).
     */
    public function delete(): void
    {
        $this->requireAuth();
        $this->requirePermission('languages.manage');
        $this->csrfCheck();

        $id   = (int) ($_POST['id'] ?? 0);
        $lang = ProgrammingLanguage::findById($id);

        if (!$lang) {
            Session::setFlash('error', 'Language not found.');
            $this->redirect('/languages');
            return;
        }

        $result = $this->service->deleteLanguage($id);

        if ($result['success']) {
            Session::setFlash('success', 'Programming language deleted successfully.');
        } else {
            Session::setFlash('error', implode(' ', $result['errors']));
        }

        $this->redirect('/languages');
    }
}
