<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use Exception;
use RuntimeException;

class User
{
    /**
     * Find a user by their unique ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, first_name, last_name, status, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Find a user by their username.
     *
     * @param string $username
     * @return array|null
     */
    public static function findByUsername(string $username): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, first_name, last_name, status, created_at, updated_at FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Find a user by their email address.
     *
     * @param string $email
     * @return array|null
     */
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, first_name, last_name, status, created_at, updated_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Find a user by either username or email (useful for login).
     *
     * @param string $identifier
     * @return array|null
     */
    public static function findByIdentifier(string $identifier): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, first_name, last_name, status, created_at, updated_at FROM users WHERE username = :ident_u OR email = :ident_e LIMIT 1');
        $stmt->execute([
            'ident_u' => $identifier,
            'ident_e' => $identifier,
        ]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Check if a username or email is already registered.
     *
     * @param string $username
     * @param string $email
     * @return array An array containing flags ['username_taken' => bool, 'email_taken' => bool]
     */
    public static function checkUniqueness(string $username, string $email): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = :username OR email = :email');
        $stmt->execute([
            'username' => $username,
            'email'    => $email,
        ]);
        $rows = $stmt->fetchAll();

        $usernameTaken = false;
        $emailTaken = false;

        foreach ($rows as $row) {
            if (strcasecmp($row['username'], $username) === 0) {
                $usernameTaken = true;
            }
            if (strcasecmp($row['email'], $email) === 0) {
                $emailTaken = true;
            }
        }

        return [
            'username_taken' => $usernameTaken,
            'email_taken'    => $emailTaken,
        ];
    }

    /**
     * Create a new user and assign a default role within a transaction.
     *
     * @param array $data ['username', 'email', 'password_hash', 'first_name', 'last_name', 'status']
     * @param string $roleName Default role to assign (default: 'student')
     * @return int The ID of the newly created user.
     * @throws RuntimeException If role is not found or database operation fails.
     */
    public static function createWithRole(array $data, string $roleName = 'student'): int
    {
        $pdo = Database::getConnection();

        try {
            $pdo->beginTransaction();

            // 1. Insert user
            $stmt = $pdo->prepare('
                INSERT INTO users (username, email, password_hash, first_name, last_name, status)
                VALUES (:username, :email, :password_hash, :first_name, :last_name, :status)
            ');

            $stmt->execute([
                'username'      => $data['username'],
                'email'         => $data['email'],
                'password_hash' => $data['password_hash'],
                'first_name'    => $data['first_name'],
                'last_name'     => $data['last_name'],
                'status'        => $data['status'] ?? 'active',
            ]);

            $userId = (int)$pdo->lastInsertId();

            // 2. Fetch role ID
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
            $roleStmt->execute(['name' => $roleName]);
            $roleId = $roleStmt->fetchColumn();

            if (!$roleId) {
                throw new RuntimeException("Default role '{$roleName}' does not exist.");
            }

            // 3. Assign role in user_roles
            $userRoleStmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
            $userRoleStmt->execute([
                'user_id' => $userId,
                'role_id' => (int)$roleId,
            ]);

            $pdo->commit();
            return $userId;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('User creation error: ' . $e->getMessage());
            throw new RuntimeException('Failed to create user account.');
        }
    }

    /**
     * Get all assigned roles for a given user ID.
     *
     * @param int $userId
     * @return array List of role names
     */
    public static function getRoles(int $userId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT r.name
            FROM roles r
            INNER JOIN user_roles ur ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
            ORDER BY r.id ASC
        ');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
