<?php

namespace App\Middleware;

use App\Services\AuthorizationService;

/**
 * PermissionMiddleware
 *
 * Enforces that the currently authenticated user has been granted
 * a specific permission (via their assigned roles in role_permissions).
 * Must be called AFTER AuthMiddleware::handle().
 *
 * Usage:
 *   PermissionMiddleware::handle('exams.take');
 *   PermissionMiddleware::handle(['questions.create', 'questions.update']); // any one
 */
class PermissionMiddleware
{
    /**
     * Abort with HTTP 403 if the current user does not have any of the required permissions.
     *
     * @param string|string[] $permissions  Required permission name(s)
     * @return void
     */
    public static function handle($permissions): void
    {
        $authz = new AuthorizationService();

        $required = is_array($permissions) ? $permissions : [$permissions];

        foreach ($required as $permission) {
            if ($authz->hasPermission($permission)) {
                return; // Access granted — at least one permission matches
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
            echo '403 Forbidden — You do not have permission to access this resource.';
        }
        exit;
    }
}
