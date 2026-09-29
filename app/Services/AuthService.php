<?php

namespace App\Services;

use App\Core\Session;
use App\Models\User;
use Exception;

class AuthService
{
    /**
     * Register a new user with the default 'student' role.
     *
     * @param array $input
     * @return array Result array ['success' => bool, 'errors' => array, 'user_id' => int|null]
     */
    public function register(array $input): array
    {
        $errors = [];

        $firstName = trim($input['first_name'] ?? '');
        $lastName  = trim($input['last_name'] ?? '');
        $username  = trim($input['username'] ?? '');
        $email     = trim($input['email'] ?? '');
        $password  = $input['password'] ?? '';
        $confirm   = $input['password_confirmation'] ?? '';

        // Validation: First Name
        if ($firstName === '') {
            $errors['first_name'] = 'First name is required.';
        } elseif (mb_strlen($firstName) < 2 || mb_strlen($firstName) > 100) {
            $errors['first_name'] = 'First name must be between 2 and 100 characters.';
        }

        // Validation: Last Name
        if ($lastName === '') {
            $errors['last_name'] = 'Last name is required.';
        } elseif (mb_strlen($lastName) < 2 || mb_strlen($lastName) > 100) {
            $errors['last_name'] = 'Last name must be between 2 and 100 characters.';
        }

        // Validation: Username
        if ($username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $errors['username'] = 'Username must be 3-50 characters and contain only letters, numbers, and underscores.';
        }

        // Validation: Email
        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Validation: Password
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        }

        // Validation: Password Confirmation
        if ($password !== $confirm) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        // Check uniqueness if basic validation passes
        if (empty($errors)) {
            $uniqueness = User::checkUniqueness($username, $email);
            if ($uniqueness['username_taken']) {
                $errors['username'] = 'This username is already taken.';
            }
            if ($uniqueness['email_taken']) {
                $errors['email'] = 'This email address is already registered.';
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
                'user_id' => null,
            ];
        }

        // Secure password hashing
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $userId = User::createWithRole([
                'username'      => $username,
                'email'         => $email,
                'password_hash' => $passwordHash,
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'status'        => 'active',
            ], 'student');

            return [
                'success' => true,
                'errors'  => [],
                'user_id' => $userId,
            ];
        } catch (Exception $e) {
            error_log('Registration failure: ' . $e->getMessage());
            return [
                'success' => false,
                'errors'  => ['general' => 'Registration could not be completed. Please try again later.'],
                'user_id' => null,
            ];
        }
    }

    /**
     * Authenticate user credentials and establish session.
     *
     * @param string $identifier Username or Email
     * @param string $password Plaintext password
     * @return array Result array ['success' => bool, 'error' => string|null, 'user' => array|null]
     */
    public function login(string $identifier, string $password): array
    {
        $identifier = trim($identifier);

        if ($identifier === '' || $password === '') {
            return [
                'success' => false,
                'error'   => 'Please provide both username/email and password.',
                'user'    => null,
            ];
        }

        $user = User::findByIdentifier($identifier);

        // Timing-safe comparison defense: verify hash or a dummy hash if user not found
        $hash = $user['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012';
        $passwordValid = password_verify($password, $hash);

        if (!$user || !$passwordValid) {
            return [
                'success' => false,
                'error'   => 'Invalid credentials. Please check your username/email and password.',
                'user'    => null,
            ];
        }

        if ($user['status'] !== 'active') {
            return [
                'success' => false,
                'error'   => 'Your account is not active. Please contact administration.',
                'user'    => null,
            ];
        }

        // Regenerate session ID to prevent session fixation attacks
        Session::regenerate(true);

        // Fetch user roles
        $roles = User::getRoles((int)$user['id']);

        // Build session payload without sensitive data (no password_hash)
        $sessionUser = [
            'id'         => (int)$user['id'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'status'     => $user['status'],
            'roles'      => $roles,
        ];

        Session::set('user', $sessionUser);

        return [
            'success' => true,
            'error'   => null,
            'user'    => $sessionUser,
        ];
    }

    /**
     * Log out current user and destroy session.
     */
    public function logout(): void
    {
        Session::destroy();
    }

    /**
     * Check if a user is currently authenticated.
     *
     * @return bool
     */
    public function check(): bool
    {
        $user = Session::get('user');
        return is_array($user) && !empty($user['id']);
    }

    /**
     * Get the authenticated user's data from session.
     *
     * @return array|null
     */
    public function user(): ?array
    {
        $user = Session::get('user');
        return (is_array($user) && !empty($user['id'])) ? $user : null;
    }
}
