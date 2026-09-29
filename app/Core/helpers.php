<?php

use App\Core\Router;
use App\Core\Session;
use App\Core\Csrf;

if (!function_exists('url')) {
    /**
     * Generate an absolute path URL tailored to the application's base directory.
     * Works seamlessly on root domain, virtual hosts, and subfolder installations (e.g. XAMPP).
     *
     * @param string $path
     * @return string
     */
    function url(string $path = ''): string
    {
        $base = Router::getBaseUrl();
        $cleanPath = ltrim($path, '/');

        if ($base === '') {
            return '/' . $cleanPath;
        }

        return $cleanPath === '' ? $base . '/' : $base . '/' . $cleanPath;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate URL for a static asset.
     *
     * @param string $path
     * @return string
     */
    function asset(string $path): string
    {
        return url($path);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate hidden CSRF input field.
     *
     * @return string
     */
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('auth_user')) {
    /**
     * Get the currently authenticated user from session.
     *
     * @return array|null
     */
    function auth_user(): ?array
    {
        $user = Session::get('user');
        return (is_array($user) && !empty($user['id'])) ? $user : null;
    }
}
