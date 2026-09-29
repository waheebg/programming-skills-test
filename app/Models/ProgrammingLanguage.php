<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * ProgrammingLanguage model.
 *
 * Wraps CRUD operations for the `programming_languages` table.
 * All queries use PDO prepared statements. No raw DB errors are exposed.
 */
class ProgrammingLanguage
{
    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Return all languages ordered by name.
     *
     * @param string $status  'active', 'inactive', or 'all'
     * @return array<int, array>
     */
    public static function all(string $status = 'all'): array
    {
        $pdo = Database::getConnection();

        if ($status === 'all') {
            $stmt = $pdo->prepare('SELECT * FROM programming_languages ORDER BY name ASC');
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare('SELECT * FROM programming_languages WHERE status = :status ORDER BY name ASC');
            $stmt->execute(['status' => $status]);
        }

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Find a language by its primary key.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM programming_languages WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find a language by its unique slug.
     *
     * @param string $slug
     * @return array|null
     */
    public static function findBySlug(string $slug): ?array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM programming_languages WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Check whether a name or slug is already taken (excluding a given ID for updates).
     *
     * @param string   $name
     * @param string   $slug
     * @param int|null $excludeId
     * @return array{'name_taken': bool, 'slug_taken': bool}
     */
    public static function checkUniqueness(string $name, string $slug, ?int $excludeId = null): array
    {
        $pdo = Database::getConnection();

        $sql  = 'SELECT name, slug FROM programming_languages WHERE (name = :name OR slug = :slug)';
        $bind = ['name' => $name, 'slug' => $slug];

        if ($excludeId !== null) {
            $sql  .= ' AND id != :exclude_id';
            $bind['exclude_id'] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();

        $nameTaken = false;
        $slugTaken = false;

        foreach ($rows as $row) {
            if (strcasecmp($row['name'], $name) === 0) {
                $nameTaken = true;
            }
            if ($row['slug'] === $slug) {
                $slugTaken = true;
            }
        }

        return ['name_taken' => $nameTaken, 'slug_taken' => $slugTaken];
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Insert a new language record.
     *
     * @param array $data  Keys: name, slug, description, status
     * @return int  New record ID
     * @throws RuntimeException on DB failure
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO programming_languages (name, slug, description, status)
                 VALUES (:name, :slug, :description, :status)'
            );
            $stmt->execute([
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'] ?? null,
                'status'      => $data['status'] ?? 'active',
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Exception $e) {
            error_log('ProgrammingLanguage::create error: ' . $e->getMessage());
            throw new RuntimeException('Failed to create programming language.');
        }
    }

    /**
     * Update an existing language record.
     *
     * @param int   $id
     * @param array $data  Keys: name, slug, description, status
     * @return bool
     * @throws RuntimeException on DB failure
     */
    public static function update(int $id, array $data): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'UPDATE programming_languages
                 SET name = :name, slug = :slug, description = :description, status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                'id'          => $id,
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'] ?? null,
                'status'      => $data['status'] ?? 'active',
            ]);
            return $stmt->rowCount() >= 0; // rowCount 0 is fine if nothing changed
        } catch (\Exception $e) {
            error_log('ProgrammingLanguage::update error: ' . $e->getMessage());
            throw new RuntimeException('Failed to update programming language.');
        }
    }

    /**
     * Delete a language record. Fails gracefully if FK constraints prevent it.
     *
     * @param int $id
     * @return bool
     * @throws RuntimeException on DB failure
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare('DELETE FROM programming_languages WHERE id = :id');
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log('ProgrammingLanguage::delete error: ' . $e->getMessage());
            throw new RuntimeException('Cannot delete this language — it may be referenced by existing questions.');
        }
    }

    /**
     * Count how many questions reference this language.
     *
     * @param int $id
     * @return int
     */
    public static function questionCount(int $id): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE programming_language_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a URL-safe slug from a string.
     *
     * @param string $value
     * @return string
     */
    public static function toSlug(string $value): string
    {
        $slug = mb_strtolower(trim($value), 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
