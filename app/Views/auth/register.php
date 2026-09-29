<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Programming Skills Test</title>
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
            max-width: 480px;
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
        .form-row {
            display: flex;
            gap: 1rem;
        }
        .form-row .form-group {
            flex: 1;
        }
        .form-group {
            margin-bottom: 1.15rem;
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
        input.is-invalid {
            border-color: #ef4444;
        }
        input:focus {
            outline: none;
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }
        .field-error {
            font-size: 0.8rem;
            color: #dc2626;
            margin-top: 0.25rem;
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
            margin-top: 0.5rem;
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
        <h1>Create Student Account</h1>
        <p class="subtitle">Join the Programming Skills Test platform</p>

        <?php if (!empty($general)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($general, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="<?= url('/register') ?>" method="POST" autocomplete="off">
            <?= \App\Core\Csrf::field(); ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        class="<?= !empty($errors['first_name']) ? 'is-invalid' : ''; ?>"
                        required
                        value="<?= htmlspecialchars($old['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="John"
                    >
                    <?php if (!empty($errors['first_name'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['first_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        class="<?= !empty($errors['last_name']) ? 'is-invalid' : ''; ?>"
                        required
                        value="<?= htmlspecialchars($old['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Doe"
                    >
                    <?php if (!empty($errors['last_name'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['last_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="<?= !empty($errors['username']) ? 'is-invalid' : ''; ?>"
                    required
                    value="<?= htmlspecialchars($old['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="johndoe"
                >
                <?php if (!empty($errors['username'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="<?= !empty($errors['email']) ? 'is-invalid' : ''; ?>"
                    required
                    value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="john@example.com"
                >
                <?php if (!empty($errors['email'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password (min 8 characters)</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="<?= !empty($errors['password']) ? 'is-invalid' : ''; ?>"
                    required
                    placeholder="Choose a strong password"
                >
                <?php if (!empty($errors['password'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    class="<?= !empty($errors['password_confirmation']) ? 'is-invalid' : ''; ?>"
                    required
                    placeholder="Repeat your password"
                >
                <?php if (!empty($errors['password_confirmation'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['password_confirmation'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-primary">Register</button>
        </form>

        <div class="footer-links">
            <p>Already have an account? <a href="<?= url('/login') ?>">Sign in</a></p>
            <p><a href="<?= url('/') ?>">Back to Home</a></p>
        </div>
    </div>
</body>
</html>
