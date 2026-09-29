<?php

namespace App\Controllers;

use App\Core\Session;
use App\Services\AuthService;

class HomeController extends Controller
{
    /**
     * Handle the application home page request.
     */
    public function index(): void
    {
        $auth = new AuthService();
        $user = $auth->user();

        $this->render('home/index', [
            'appName' => 'Programming Skills Test',
            'status'  => 'Core Architecture & Auth Online',
            'user'    => $user,
            'success' => Session::getFlash('success'),
            'error'   => Session::getFlash('error'),
        ]);
    }

    /**
     * Dashboard — accessible by any authenticated user (auth middleware applied in routes).
     */
    public function dashboard(): void
    {
        $auth = new AuthService();
        $user = $auth->user();
        $this->render('home/index', [
            'appName' => 'Programming Skills Test — Dashboard',
            'status'  => 'Dashboard (authenticated)',
            'user'    => $user,
            'success' => null,
            'error'   => null,
        ]);
    }

    /**
     * Admin panel — accessible by admin role only (role middleware applied in routes).
     */
    public function admin(): void
    {
        $auth = new AuthService();
        $user = $auth->user();
        $this->render('home/index', [
            'appName' => 'Programming Skills Test — Admin',
            'status'  => 'Admin Panel',
            'user'    => $user,
            'success' => null,
            'error'   => null,
        ]);
    }

    /**
     * Take exam — requires exams.take permission (permission middleware in routes).
     */
    public function takeExam(): void
    {
        $auth = new AuthService();
        $user = $auth->user();
        $this->render('home/index', [
            'appName' => 'Programming Skills Test — Take Exam',
            'status'  => 'Take Exam placeholder',
            'user'    => $user,
            'success' => null,
            'error'   => null,
        ]);
    }

    /**
     * Create question — requires questions.create permission (permission middleware in routes).
     */
    public function createQuestion(): void
    {
        $auth = new AuthService();
        $user = $auth->user();
        $this->render('home/index', [
            'appName' => 'Programming Skills Test — Create Question',
            'status'  => 'Create Question placeholder',
            'user'    => $user,
            'success' => null,
            'error'   => null,
        ]);
    }
}

