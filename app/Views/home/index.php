<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName ?? 'Application', ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1.5rem;
            box-sizing: border-box;
        }
        .container {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            max-width: 520px;
            width: 100%;
            text-align: center;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #0f172a;
        }
        p {
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        .badge {
            display: inline-block;
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        .badge-role {
            display: inline-block;
            background-color: #f1f5f9;
            color: #475569;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            text-align: left;
            line-height: 1.4;
        }
        .alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .user-panel {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .user-panel-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        .user-panel-row:last-child {
            margin-bottom: 0;
        }
        .user-panel-label {
            color: #64748b;
            font-weight: 500;
        }
        .user-panel-value {
            color: #0f172a;
            font-weight: 600;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
        }
        .btn {
            display: inline-block;
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.15s ease, border-color 0.15s ease;
            border: 1px solid transparent;
        }
        .btn-primary {
            background-color: #0284c7;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #0369a1;
        }
        .btn-secondary {
            background-color: #ffffff;
            color: #334155;
            border-color: #cbd5e1;
        }
        .btn-secondary:hover {
            background-color: #f1f5f9;
        }
        .btn-danger {
            background-color: #ef4444;
            color: #ffffff;
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= htmlspecialchars($appName ?? 'Programming Skills Test', ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Application Foundation & Core Architecture</p>
        <span class="badge"><?= htmlspecialchars($status ?? 'Online', ENT_QUOTES, 'UTF-8'); ?></span>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($user)): ?>
            <div class="user-panel">
                <div class="user-panel-row">
                    <span class="user-panel-label">Logged In As:</span>
                    <span class="user-panel-value">
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="user-panel-row">
                    <span class="user-panel-label">Username:</span>
                    <span class="user-panel-value">@<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="user-panel-row">
                    <span class="user-panel-label">Email:</span>
                    <span class="user-panel-value"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="user-panel-row">
                    <span class="user-panel-label">Role(s):</span>
                    <span class="user-panel-value">
                        <?php foreach ($user['roles'] as $role): ?>
                            <span class="badge-role"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </span>
                </div>
            </div>

            <div class="actions" style="margin-bottom: 1rem; flex-wrap: wrap;">
                <a href="<?= url('/exams') ?>" class="btn btn-primary">Browse Exams</a>
                <?php if (in_array('admin', $user['roles'] ?? [], true) || in_array('teacher', $user['roles'] ?? [], true)): ?>
                    <a href="<?= url('/questions') ?>" class="btn btn-secondary">Questions</a>
                    <a href="<?= url('/categories') ?>" class="btn btn-secondary">Categories</a>
                    <a href="<?= url('/languages') ?>" class="btn btn-secondary">Languages</a>
                <?php endif; ?>
            </div>

            <div class="actions">
                <form action="<?= url('/logout') ?>" method="POST" style="margin: 0;">
                    <?= \App\Core\Csrf::field(); ?>
                    <button type="submit" class="btn btn-danger">Log Out</button>
                </form>
            </div>
        <?php else: ?>
            <div class="actions">
                <a href="<?= url('/login') ?>" class="btn btn-primary">Sign In</a>
                <a href="<?= url('/register') ?>" class="btn btn-secondary">Register</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
