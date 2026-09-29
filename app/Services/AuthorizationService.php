<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use PDO;

/**
 * AuthorizationService
 *
 * Provides server-side Role-Based Access Control (RBAC).
 * Uses the session-backed authenticated user and looks up permissions
 * directly against the database (roles, permissions, user_roles,
 * role_permissions) without touching any UI layer.
 *
 * All public methods are safe to call even when no user is authenticated;
 * they simply return empty collections or false.
 */
class AuthorizationService
{
    /**
     * Cached permissions for the current request lifecycle, keyed by user id.
     * Avoids repeated DB round-trips within the same request.
     *
     * @var array<int, array<string>>
     */
    private static array $permissionCache = [];

    /**
     * Return the authenticated user array from session, or null.
     *
     * @return array|null
     */
    private function getSessionUser(): ?array
    {
        $user = Session::get('user');
        return (is_array($user) && !empty($user['id'])) ? $user : null;
    }

    /**
     * Get all role names assigned to the currently authenticated user.
     * Returns an empty array for guests.
     *
     * @return array<string>
     */
    public function getCurrentUserRoles(): array
    {
        $user = $this->getSessionUser();
        if ($user === null) {
            return [];
        }

        // Roles are stored in the session at login time (see AuthService::login)
        return $user['roles'] ?? [];
    }

    /**
     * Get all permission names available to the currently authenticated user
     * (union of all permissions across all assigned roles).
     * Returns an empty array for guests.
     *
     * @return array<string>
     */
    public function getCurrentUserPermissions(): array
    {
        $user = $this->getSessionUser();
        if ($user === null) {
            return [];
        }

        $userId = (int) $user['id'];

        // Return cached value if available for this request
        if (isset(self::$permissionCache[$userId])) {
            return self::$permissionCache[$userId];
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT DISTINCT p.name
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur       ON ur.role_id       = rp.role_id
             WHERE ur.user_id = :user_id
             ORDER BY p.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);

        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        self::$permissionCache[$userId] = $permissions;

        return $permissions;
    }

    /**
     * Check whether the currently authenticated user has the given role.
     *
     * @param string $role  Exact role name (e.g. 'admin', 'teacher', 'student')
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getCurrentUserRoles(), true);
    }

    /**
     * Check whether the currently authenticated user has the given permission.
     * The permission must exist in the database; an unknown permission string
     * will always return false (no implicit grant).
     *
     * @param string $permission  Exact permission name (e.g. 'exams.take')
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        if (trim($permission) === '') {
            return false;
        }
        return in_array($permission, $this->getCurrentUserPermissions(), true);
    }

    /**
     * Check whether the current user is authenticated (not a guest).
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->getSessionUser() !== null;
    }

    /**
     * Clear the in-memory permission cache.
     * Useful in tests between different user sessions.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$permissionCache = [];
    }
}
