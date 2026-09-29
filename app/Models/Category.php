<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Category model.
 *
 * Wraps CRUD operations for the `categories` table.
 * Supports optional parent_id for hierarchical categorisation.
 * All queries use PDO prepared statements. No raw DB errors are exposed.
 */
class Category
{
    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Return all categories, optionally filtered by status.
     *
     * @param string $status  'active', 'inactive', or 'all'
     * @return array<int, array>
     */
    public static function all(string $status = 'all'): array
    {
        $pdo = Database::getConnection();

        if ($status === 'all') {
            $stmt = $pdo->prepare(
                'SELECT c.*, p.name AS parent_name
                 FROM categories c
                 LEFT JOIN categories p ON p.id = c.parent_id
                 ORDER BY c.name ASC'
            );
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare(
                'SELECT c.*, p.name AS parent_name
                 FROM categories c
                 LEFT JOIN categories p ON p.id = c.parent_id
                 WHERE c.status = :status
                 ORDER BY c.name ASC'
            );
            $stmt->execute(['status' => $status]);
        }

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Find a category by primary key.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.*, p.name AS parent_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find a category by its unique slug.
     *
     * @param string $slug
     * @return array|null
     */
    public static function findBySlug(string $slug): ?array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Check whether slug is already taken (excluding a given ID for updates).
     *
     * @param string   $slug
     * @param int|null $excludeId
     * @return bool
     */
    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $pdo = Database::getConnection();

        if ($excludeId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = :slug AND id != :exclude_id');
            $stmt->execute(['slug' => $slug, 'exclude_id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = :slug');
            $stmt->execute(['slug' => $slug]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Return all top-level categories (parent_id IS NULL).
     *
     * @return array<int, array>
     */
    public static function topLevel(): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT * FROM categories WHERE parent_id IS NULL AND status = 'active' ORDER BY name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Insert a new category record.
     *
     * @param array $data  Keys: name, slug, description, parent_id, created_by, status
     * @return int  New record ID
     * @throws RuntimeException on DB failure
     */
    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO categories (name, slug, description, parent_id, created_by, status)
                 VALUES (:name, :slug, :description, :parent_id, :created_by, :status)'
            );
            $stmt->execute([
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'] ?? null,
                'parent_id'   => $data['parent_id'] ?: null,
                'created_by'  => $data['created_by'] ?? null,
                'status'      => $data['status'] ?? 'active',
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Exception $e) {
            error_log('Category::create error: ' . $e->getMessage());
            throw new RuntimeException('Failed to create category.');
        }
    }

    /**
     * Update an existing category record.
     *
     * @param int   $id
     * @param array $data  Keys: name, slug, description, parent_id, status
     * @return bool
     * @throws RuntimeException on DB failure
     */
    public static function update(int $id, array $data): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare(
                'UPDATE categories
                 SET name = :name, slug = :slug, description = :description,
                     parent_id = :parent_id, status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                'id'          => $id,
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'] ?? null,
                'parent_id'   => $data['parent_id'] ?: null,
                'status'      => $data['status'] ?? 'active',
            ]);
            return true;
        } catch (\Exception $e) {
            error_log('Category::update error: ' . $e->getMessage());
            throw new RuntimeException('Failed to update category.');
        }
    }

    /**
     * Delete a category. Fails gracefully if FK constraints prevent it.
     *
     * @param int $id
     * @return bool
     * @throws RuntimeException on DB failure
     */
    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        try {
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log('Category::delete error: ' . $e->getMessage());
            throw new RuntimeException('Cannot delete this category — it may be referenced by questions or sub-categories.');
        }
    }

    /**
     * Count how many questions reference this category.
     *
     * @param int $id
     * @return int
     */
    public static function questionCount(int $id): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE category_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count direct children of a category.
     *
     * @param int $id
     * @return int
     */
    public static function childCount(int $id): int
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = :id');
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
