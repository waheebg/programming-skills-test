<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Programming Skills Test</title>
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
        .card {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            max-width: 420px;
            width: 100%;
        }
        h1 {
            font-size: 1.5rem;
            margin-top: 0;
            margin-bottom: 0.5rem;
            color: #0f172a;
            text-align: center;
        }
        .subtitle {
            color: #64748b;
            font-size: 0.875rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            line-height: 1.4;
        }
        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }
        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #334155;
            margin-bottom: 0.35rem;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #1e293b;
            box-sizing: border-box;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        input:focus {
            outline: none;
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }
        .btn-primary {
            display: block;
            width: 100%;
            background-color: #0284c7;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.75rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        .btn-primary:hover {
            background-color: #0369a1;
        }
        .footer-links {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: #64748b;
        }
        .footer-links a {
            color: #0284c7;
            text-decoration: none;
            font-weight: 500;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Sign In</h1>
        <p class="subtitle">Enter your credentials to access the examination platform</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="<?= url('/login') ?>" method="POST" autocomplete="off">
            <?= \App\Core\Csrf::field(); ?>

            <div class="form-group">
                <label for="identifier">Username or Email</label>
                <input
                    type="text"
                    id="identifier"
                    name="identifier"
                    required
                    autofocus
                    value="<?= htmlspecialchars($old['identifier'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="e.g. jdoe or john@example.com"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    placeholder="Enter your password"
                >
            </div>

            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div class="footer-links">
            <p>Don't have an account? <a href="<?= url('/register') ?>">Register here</a></p>
            <p><a href="<?= url('/') ?>">Back to Home</a></p>
        </div>
    </div>
</body>
</html>
