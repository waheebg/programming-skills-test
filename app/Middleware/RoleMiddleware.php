<?php

namespace App\Middleware;

use App\Services\AuthorizationService;

/**
 * RoleMiddleware
 *
 * Enforces that the currently authenticated user possesses at least one
 * of the required roles.  Must be called AFTER AuthMiddleware::handle()
 * to ensure a user is present.
 *
 * Usage:
 *   RoleMiddleware::handle('admin');
 *   RoleMiddleware::handle(['admin', 'teacher']);   // any one is sufficient
 */
class RoleMiddleware
{
    /**
     * Abort with HTTP 403 if the current user does not have any of the given roles.
     *
     * @param string|string[] $roles  Required role name(s)
     * @return void
     */
    public static function handle($roles): void
    {
        $authz = new AuthorizationService();

        $required = is_array($roles) ? $roles : [$roles];

        foreach ($required as $role) {
            if ($authz->hasRole($role)) {
                return; // Access granted — at least one role matches
            }
        }

        self::forbidden();
    }

    /**
     * Send a 403 Forbidden response and halt execution.
     *
     * @return void
     */
    private static function forbidden(): void
    {
        http_response_code(403);
        $view = dirname(__DIR__) . '/Views/errors/403.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo '403 Forbidden — You do not have the required role to access this resource.';
        }
        exit;
    }
}
