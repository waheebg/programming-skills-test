<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Models\Category;
use App\Services\AuthorizationService;
use App\Services\QuestionBankService;

/**
 * CategoryController
 *
 * Handles CRUD for categories.
 * Requires `categories.manage` permission for create/update/delete.
 * Requires `categories.view` for index.
 */
class CategoryController extends Controller
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
            $this->redirect('/categories');
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * GET /categories — list all categories.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission('categories.view');

        $categories = Category::all('all');

        $this->render('categories/index', [
            'categories' => $categories,
            'canManage'  => $this->authz->hasPermission('categories.manage'),
            'success'    => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
        ]);
    }

    /**
     * GET /categories/create — show create form.
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->requirePermission('categories.manage');

        $parents = Category::topLevel();

        $this->render('categories/create', [
            'parents' => $parents,
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    /**
     * POST /categories/store — persist new category.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission('categories.manage');
        $this->csrfCheck();

        $user   = Session::get('user');
        $userId = (int) ($user['id'] ?? 0);

        $result = $this->service->createCategory($_POST, $userId);

        if ($result['success']) {
            Session::setFlash('success', 'Category created successfully.');
            $this->redirect('/categories');
            return;
        }

        Session::setFlash('errors', $result['errors']);
        Session::setFlash('old', [
            'name'        => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'parent_id'   => trim($_POST['parent_id'] ?? ''),
            'status'      => trim($_POST['status'] ?? 'active'),
        ]);
        $this->redirect('/categories/create');
    }

    /**
     * GET /categories/edit?id=N — show edit form.
     */
    public function edit(): void
    {
        $this->requireAuth();
        $this->requirePermission('categories.manage');

        $id       = (int) ($_GET['id'] ?? 0);
        $category = Category::findById($id);

        if (!$category) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        // Top-level parents excluding self
        $parents = array_filter(
            Category::topLevel(),
            fn($p) => (int) $p['id'] !== $id
        );

        $this->render('categories/edit', [
            'category' => $category,
            'parents'  => $parents,
            'errors'   => Session::getFlash('errors', []),
            'old'      => Session::getFlash('old', []),
        ]);
    }

    /**
     * POST /categories/update — persist edit.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->requirePermission('categories.manage');
        $this->csrfCheck();

        $id       = (int) ($_POST['id'] ?? 0);
        $category = Category::findById($id);

        if (!$category) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $result = $this->service->updateCategory($id, $_POST);

        if ($result['success']) {
            Session::setFlash('success', 'Category updated successfully.');
            $this->redirect('/categories');
            return;
        }

        Session::setFlash('errors', $result['errors']);
        Session::setFlash('old', [
            'name'        => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'parent_id'   => trim($_POST['parent_id'] ?? ''),
            'status'      => trim($_POST['status'] ?? 'active'),
        ]);
        $this->redirect('/categories/edit?id=' . $id);
    }

    /**
     * POST /categories/delete — delete (with child/question guard).
     */
    public function delete(): void
    {
        $this->requireAuth();
        $this->requirePermission('categories.manage');
        $this->csrfCheck();

        $id       = (int) ($_POST['id'] ?? 0);
        $category = Category::findById($id);

        if (!$category) {
            Session::setFlash('error', 'Category not found.');
            $this->redirect('/categories');
            return;
        }

        $result = $this->service->deleteCategory($id);

        if ($result['success']) {
            Session::setFlash('success', 'Category deleted successfully.');
        } else {
            Session::setFlash('error', implode(' ', $result['errors']));
        }

        $this->redirect('/categories');
    }
}
