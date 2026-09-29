<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Services\AuthService;

class AuthController extends Controller
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    /**
     * Display the login form.
     */
    public function showLoginForm(): void
    {
        if ($this->auth->check()) {
            $this->redirect('/');
            return;
        }

        $this->render('auth/login', [
            'error'   => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    /**
     * Handle login authentication request.
     */
    public function login(): void
    {
        if ($this->auth->check()) {
            $this->redirect('/');
            return;
        }

        // Verify CSRF Token
        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Csrf::validate($csrfToken)) {
            $this->render('auth/login', [
                'error'   => 'Security token expired or invalid. Please try again.',
                'success' => null,
                'old'     => ['identifier' => trim($_POST['identifier'] ?? '')],
            ]);
            return;
        }

        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

        $result = $this->auth->login($identifier, $password);

        if ($result['success']) {
            Session::setFlash('success', 'Welcome back, ' . htmlspecialchars($result['user']['first_name'], ENT_QUOTES, 'UTF-8') . '!');
            $this->redirect('/');
            return;
        }

        $this->render('auth/login', [
            'error'   => $result['error'],
            'success' => null,
            'old'     => ['identifier' => $identifier],
        ]);
    }

    /**
     * Display registration form.
     */
    public function showRegisterForm(): void
    {
        if ($this->auth->check()) {
            $this->redirect('/');
            return;
        }

        $this->render('auth/register', [
            'errors'  => Session::getFlash('errors', []),
            'general' => Session::getFlash('error'),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    /**
     * Handle user registration request.
     */
    public function register(): void
    {
        if ($this->auth->check()) {
            $this->redirect('/');
            return;
        }

        // Retained input fields for repopulation (never retain passwords)
        $old = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name'  => trim($_POST['last_name'] ?? ''),
            'username'   => trim($_POST['username'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
        ];

        // Verify CSRF Token
        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Csrf::validate($csrfToken)) {
            $this->render('auth/register', [
                'errors'  => [],
                'general' => 'Security token expired or invalid. Please try again.',
                'old'     => $old,
            ]);
            return;
        }

        $result = $this->auth->register($_POST);

        if ($result['success']) {
            Session::setFlash('success', 'Registration completed successfully! Please log in.');
            $this->redirect('/login');
            return;
        }

        $generalError = $result['errors']['general'] ?? null;
        unset($result['errors']['general']);

        $this->render('auth/register', [
            'errors'  => $result['errors'],
            'general' => $generalError,
            'old'     => $old,
        ]);
    }

    /**
     * Handle user logout request.
     */
    public function logout(): void
    {
        // Enforce CSRF protection on logout
        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!Csrf::validate($csrfToken)) {
            Session::setFlash('error', 'Invalid security token for logout request.');
            $this->redirect('/');
            return;
        }

        $this->auth->logout();

        // Start a fresh session for the post-logout flash message
        Session::start();
        Session::setFlash('success', 'You have been successfully logged out.');
        $this->redirect('/login');
    }
}
