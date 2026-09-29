<?php
/**
 * Shared layout header partial.
 *
 * Variables expected:
 *  - $pageTitle   string  — <title> text
 *  - $user        array|null — session user (auto-fetched if not passed)
 */

use App\Core\Session;
use App\Services\AuthorizationService;

$sessionUser = $user ?? Session::get('user');
$authz = new AuthorizationService();

$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($pageTitle ?? 'Question Bank') ?> — Programming Skills Test</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        nav {
            background: #0f172a;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            height: 52px;
        }
        nav a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.25rem 0;
            border-bottom: 2px solid transparent;
        }
        nav a:hover, nav a.active { color: #f8fafc; border-bottom-color: #3b82f6; }
        nav .brand { color: #f8fafc; font-weight: 700; font-size: 1rem; margin-right: 0.5rem; }
        nav .spacer { flex: 1; }
        nav .nav-user { color: #64748b; font-size: 0.8rem; }
        .container { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
        h1 { font-size: 1.5rem; margin: 0 0 1.25rem; color: #0f172a; }
        h2 { font-size: 1.2rem; margin: 0 0 1rem; color: #0f172a; }
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        .alert-success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
        .alert-error   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
        .alert-warning { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
        .alert ul { margin: 0.25rem 0 0; padding-left: 1.25rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        thead th { background: #f8fafc; padding: 0.6rem 0.75rem; text-align: left;
                   color: #64748b; font-size: 0.8rem; text-transform: uppercase;
                   letter-spacing: 0.04em; border-bottom: 1px solid #e2e8f0; }
        tbody td { padding: 0.65rem 0.75rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f8fafc; }
        .badge {
            display: inline-block; padding: 0.2rem 0.55rem; border-radius: 9999px;
            font-size: 0.75rem; font-weight: 600; text-transform: capitalize;
        }
        .badge-active    { background:#dcfce7; color:#15803d; }
        .badge-inactive  { background:#f1f5f9; color:#64748b; }
        .badge-draft     { background:#fef9c3; color:#854d0e; }
        .badge-published { background:#dcfce7; color:#15803d; }
        .badge-archived  { background:#f1f5f9; color:#64748b; }
        .badge-easy   { background:#dcfce7; color:#15803d; }
        .badge-medium { background:#fef9c3; color:#854d0e; }
        .badge-hard   { background:#fee2e2; color:#991b1b; }
        .badge-sc { background:#e0f2fe; color:#0369a1; }
        .badge-tf { background:#ede9fe; color:#7c3aed; }
        .btn {
            display: inline-block; padding: 0.5rem 1rem; border-radius: 6px;
            font-size: 0.875rem; font-weight: 600; text-decoration: none; cursor: pointer;
            border: 1px solid transparent; transition: background .15s;
        }
        .btn-primary   { background:#0284c7; color:#fff; }
        .btn-primary:hover { background:#0369a1; }
        .btn-secondary { background:#fff; color:#334155; border-color:#cbd5e1; }
        .btn-secondary:hover { background:#f1f5f9; }
        .btn-danger    { background:#ef4444; color:#fff; }
        .btn-danger:hover { background:#dc2626; }
        .btn-warning   { background:#f59e0b; color:#fff; }
        .btn-warning:hover { background:#d97706; }
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
        .form-group { margin-bottom: 1.1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; color: #334155; margin-bottom: 0.3rem; }
        input[type=text], input[type=number], textarea, select {
            width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #cbd5e1;
            border-radius: 6px; font-size: 0.9rem; color: #1e293b; background:#fff;
        }
        input:focus, textarea:focus, select:focus {
            outline: none; border-color: #0284c7; box-shadow: 0 0 0 3px rgba(2,132,199,.15);
        }
        textarea { resize: vertical; min-height: 80px; }
        .form-hint { font-size: 0.78rem; color: #94a3b8; margin-top: 0.2rem; }
        .form-error { font-size: 0.82rem; color: #dc2626; margin-top: 0.2rem; }
        .actions { display:flex; gap: 0.5rem; align-items:center; flex-wrap: wrap; }
        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; }
        .page-header h1 { margin: 0; }
        .filters { display:flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .filters select, .filters input { width: auto; min-width: 140px; }
        .pagination { display:flex; gap: 0.4rem; margin-top: 1rem; }
        .pagination a, .pagination span {
            padding: 0.35rem 0.7rem; border-radius: 5px; font-size: 0.85rem;
            border: 1px solid #e2e8f0; text-decoration: none; color: #334155;
        }
        .pagination .current { background:#0284c7; color:#fff; border-color:#0284c7; }
        .pagination a:hover { background: #f1f5f9; }
        .options-table { margin-top: 0.75rem; }
        .options-table input[type=text] { padding: 0.4rem 0.6rem; }
        .radio-group { display:flex; gap: 1.5rem; align-items:center; }
        .radio-group label { display:flex; gap: 0.4rem; align-items:center; font-weight: normal; cursor: pointer; }
        .correct-badge { background:#dcfce7; color:#166534; border-radius:3px; padding:0.1rem 0.4rem; font-size:0.75rem; }
        .wrong-badge   { background:#f1f5f9; color:#64748b; border-radius:3px; padding:0.1rem 0.4rem; font-size:0.75rem; }
    </style>
</head>
<body>
<nav>
    <span class="brand">📚 Question Bank</span>
    <a href="<?= url('/') ?>">Home</a>
    <a href="<?= url('/languages') ?>">Languages</a>
    <a href="<?= url('/categories') ?>">Categories</a>
    <a href="<?= url('/questions') ?>">Questions</a>
    <a href="<?= url('/exams') ?>">Exams</a>
    <span class="spacer"></span>
    <?php if ($sessionUser): ?>
        <span class="nav-user"><?= $h($sessionUser['username'] ?? '') ?></span>
        <form action="<?= url('/logout') ?>" method="POST" style="margin:0;">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-sm" style="background:transparent;color:#94a3b8;border-color:#334155;">Log out</button>
        </form>
    <?php else: ?>
        <a href="<?= url('/login') ?>" class="btn btn-sm btn-primary">Log in</a>
    <?php endif; ?>
</nav>
<div class="container">
