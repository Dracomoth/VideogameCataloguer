<?php
/**
 * src/Repositories/SubcategoryRepository.php
 * Data repository managing game subcategories, parent genre hierarchy, and game counts.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;
use InvalidArgumentException;
use RuntimeException;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class SubcategoryRepository
{
    /**
     * Retrieves all subcategories with parent category details and linked game counts.
     *
     * @param int|null $categoryId Optional parent category filter
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?int $categoryId = null): array
    {
        $params = [];
        $whereClause = '';

        if ($categoryId !== null && $categoryId > 0) {
            $whereClause = 'WHERE s.category_id = :cat_id';
            $params[':cat_id'] = $categoryId;
        }

        $sql = "
            SELECT 
                s.id,
                s.category_id,
                s.name,
                COALESCE(c.name, 'Uncategorized') AS category_name,
                COUNT(DISTINCT g.id) AS games_count,
                COUNT(DISTINCT g.id) AS game_count
            FROM `subcategories` s
            LEFT JOIN `categories` c ON c.id = s.category_id
            LEFT JOIN `games` g ON g.subcategory_id = s.id
            {$whereClause}
            GROUP BY s.id
            ORDER BY c.name ASC, s.name ASC
        ";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Retrieves a single subcategory by ID with parent category and linked game count.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                s.id,
                s.category_id,
                s.name,
                COALESCE(c.name, 'Uncategorized') AS category_name,
                COUNT(DISTINCT g.id) AS games_count,
                COUNT(DISTINCT g.id) AS game_count
            FROM `subcategories` s
            LEFT JOIN `categories` c ON c.id = s.category_id
            LEFT JOIN `games` g ON g.subcategory_id = s.id
            WHERE s.id = :id
            GROUP BY s.id
        ";

        return Database::fetchOne($sql, [':id' => $id]);
    }

    /**
     * Checks if a subcategory with the given name already exists under a specific category.
     *
     * @param string $name
     * @param int $categoryId
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByName(string $name, int $categoryId, ?int $excludeId = null): bool
    {
        $trimmed = trim($name);
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `subcategories` WHERE LOWER(name) = LOWER(:name) AND category_id = :cat_id AND id != :excludeId LIMIT 1";
            $res = Database::fetchOne($sql, [
                ':name'      => $trimmed,
                ':cat_id'    => $categoryId,
                ':excludeId' => $excludeId,
            ]);
        } else {
            $sql = "SELECT id FROM `subcategories` WHERE LOWER(name) = LOWER(:name) AND category_id = :cat_id LIMIT 1";
            $res = Database::fetchOne($sql, [
                ':name'   => $trimmed,
                ':cat_id' => $categoryId,
            ]);
        }

        return $res !== null;
    }

    /**
     * Counts how many games are assigned to a specific subcategory.
     *
     * @param int $id
     * @return int
     */
    public function getGamesCount(int $id): int
    {
        $sql = "SELECT COUNT(id) AS total FROM `games` WHERE subcategory_id = :id";
        $row = Database::fetchOne($sql, [':id' => $id]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Creates a new subcategory under a parent category.
     *
     * @param int $categoryId
     * @param string $name
     * @return int Inserted record ID
     * @throws InvalidArgumentException
     */
    public function create(int $categoryId, string $name): int
    {
        $cleanName = trim($name);

        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Please select a valid parent category.');
        }

        // Verify parent category exists
        $cat = Database::fetchOne("SELECT id, name FROM `categories` WHERE id = :id LIMIT 1", [':id' => $categoryId]);
        if (!$cat) {
            throw new InvalidArgumentException('Selected parent category does not exist.');
        }

        if ($cleanName === '') {
            throw new InvalidArgumentException('Subcategory name cannot be empty.');
        }

        if (strlen($cleanName) > 255) {
            throw new InvalidArgumentException('Subcategory name cannot exceed 255 characters.');
        }

        if ($this->existsByName($cleanName, $categoryId)) {
            throw new InvalidArgumentException("A subcategory named '{$cleanName}' already exists under category '{$cat['name']}'.");
        }

        $sql = "INSERT INTO `subcategories` (`category_id`, `name`) VALUES (:cat_id, :name)";
        Database::execute($sql, [
            ':cat_id' => $categoryId,
            ':name'   => $cleanName,
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Updates an existing subcategory record.
     *
     * @param int $id
     * @param int $categoryId
     * @param string $name
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(int $id, int $categoryId, string $name): bool
    {
        $cleanName = trim($name);

        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid subcategory ID specified.');
        }

        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Please select a valid parent category.');
        }

        // Verify parent category exists
        $cat = Database::fetchOne("SELECT id, name FROM `categories` WHERE id = :id LIMIT 1", [':id' => $categoryId]);
        if (!$cat) {
            throw new InvalidArgumentException('Selected parent category does not exist.');
        }

        if ($cleanName === '') {
            throw new InvalidArgumentException('Subcategory name cannot be empty.');
        }

        if (strlen($cleanName) > 255) {
            throw new InvalidArgumentException('Subcategory name cannot exceed 255 characters.');
        }

        if ($this->existsByName($cleanName, $categoryId, $id)) {
            throw new InvalidArgumentException("Another subcategory named '{$cleanName}' already exists under category '{$cat['name']}'.");
        }

        $sql = "UPDATE `subcategories` SET `category_id` = :cat_id, `name` = :name WHERE `id` = :id";
        $affected = Database::execute($sql, [
            ':cat_id' => $categoryId,
            ':name'   => $cleanName,
            ':id'     => $id,
        ]);

        return $affected >= 0;
    }

    /**
     * Deletes a subcategory by ID, enforcing relational integrity.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid subcategory ID specified.');
        }

        $gamesCount = $this->getGamesCount($id);
        if ($gamesCount > 0) {
            throw new RuntimeException("Cannot delete this subcategory because it is assigned to {$gamesCount} game(s). Please reassign those games before deleting.");
        }

        $sql = "DELETE FROM `subcategories` WHERE `id` = :id";
        $affected = Database::execute($sql, [':id' => $id]);

        return $affected > 0;
    }
}
