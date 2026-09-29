<?php

namespace App\Middleware;

use App\Core\Session;

/**
 * AuthMiddleware
 *
 * Enforces that the current visitor is authenticated.
 * If not, redirects to the login page (server-side, before any output).
 *
 * Usage:
 *   AuthMiddleware::handle();           // redirect to /login on failure
 *   AuthMiddleware::handle('/custom');  // redirect to custom path on failure
 */
class AuthMiddleware
{
    /**
     * Enforce authentication.
     * Terminates the request with a 302 redirect if the user is not logged in.
     *
     * @param string $redirectTo  Destination on auth failure (default: '/login')
     * @return void
     */
    public static function handle(string $redirectTo = '/login'): void
    {
        Session::start();

        $user = Session::get('user');
        $isAuthenticated = is_array($user) && !empty($user['id']);

        if (!$isAuthenticated) {
            // Preserve the intended URL as a flash so the login controller can
            // redirect the user back after a successful login (optional future use)
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if ($requestUri !== '' && $requestUri !== $redirectTo) {
                Session::setFlash('intended_url', $requestUri);
            }

            self::redirect($redirectTo);
        }
    }

    /**
     * Perform a redirect and terminate execution.
     *
     * @param string $path
     * @return void
     */
    private static function redirect(string $path): void
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($baseDir !== '' && strpos($path, '/') === 0 && strpos($path, $baseDir) !== 0) {
            $path = $baseDir . $path;
        }

        http_response_code(302);
        header('Location: ' . $path);
        exit;
    }
}
