<?php
/**
 * src/Repositories/CategoryRepository.php
 * Data repository managing genre categories, subcategory hierarchies, and game counts.
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

final class CategoryRepository
{
    /**
     * Retrieves all categories with attached subcategories and game reference counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                c.id,
                c.name,
                COUNT(DISTINCT s.id) AS subcat_count,
                COUNT(DISTINCT s.id) AS subcategories_count,
                COUNT(DISTINCT g.id) AS game_count,
                COUNT(DISTINCT g.id) AS games_count,
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name ASC SEPARATOR '||') AS subcategories_raw
            FROM `categories` c
            LEFT JOIN `subcategories` s ON s.category_id = c.id
            LEFT JOIN `games` g ON g.category_id = c.id
            GROUP BY c.id
            ORDER BY c.name ASC
        ";

        $rows = Database::fetchAll($sql);

        return array_map(function (array $row): array {
            $raw = (string)($row['subcategories_raw'] ?? '');
            $row['subcategories'] = $raw !== '' ? explode('||', $raw) : [];
            unset($row['subcategories_raw']);
            return $row;
        }, $rows);
    }

    /**
     * Retrieves a single category by ID with its attached subcategories and game counts.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                c.id,
                c.name,
                COUNT(DISTINCT s.id) AS subcat_count,
                COUNT(DISTINCT s.id) AS subcategories_count,
                COUNT(DISTINCT g.id) AS game_count,
                COUNT(DISTINCT g.id) AS games_count,
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name ASC SEPARATOR '||') AS subcategories_raw
            FROM `categories` c
            LEFT JOIN `subcategories` s ON s.category_id = c.id
            LEFT JOIN `games` g ON g.category_id = c.id
            WHERE c.id = :id
            GROUP BY c.id
        ";

        $row = Database::fetchOne($sql, [':id' => $id]);
        if (!$row) {
            return null;
        }

        $raw = (string)($row['subcategories_raw'] ?? '');
        $row['subcategories'] = $raw !== '' ? explode('||', $raw) : [];
        unset($row['subcategories_raw']);

        return $row;
    }

    /**
     * Checks if a category name already exists, optionally excluding a specific ID.
     *
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $trimmed = trim($name);
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `categories` WHERE LOWER(name) = LOWER(:name) AND id != :excludeId LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed, ':excludeId' => $excludeId]);
        } else {
            $sql = "SELECT id FROM `categories` WHERE LOWER(name) = LOWER(:name) LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed]);
        }

        return $res !== null;
    }

    /**
     * Counts how many games are assigned to a specific category.
     *
     * @param int $id
     * @return int
     */
    public function getGamesCount(int $id): int
    {
        $sql = "SELECT COUNT(id) AS total FROM `games` WHERE category_id = :id";
        $row = Database::fetchOne($sql, [':id' => $id]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Counts how many subcategories are linked to a specific category.
     *
     * @param int $id
     * @return int
     */
    public function getSubcategoriesCount(int $id): int
    {
        $sql = "SELECT COUNT(id) AS total FROM `subcategories` WHERE category_id = :id";
        $row = Database::fetchOne($sql, [':id' => $id]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Creates a new category entry.
     *
     * @param string $name
     * @return int Inserted record ID
     * @throws InvalidArgumentException
     */
    public function create(string $name): int
    {
        $cleanName = trim($name);

        if ($cleanName === '') {
            throw new InvalidArgumentException('Category name cannot be empty.');
        }

        if (strlen($cleanName) > 255) {
            throw new InvalidArgumentException('Category name cannot exceed 255 characters.');
        }

        if ($this->existsByName($cleanName)) {
            throw new InvalidArgumentException("A category named '{$cleanName}' already exists.");
        }

        $sql = "INSERT INTO `categories` (`name`) VALUES (:name)";
        Database::execute($sql, [':name' => $cleanName]);

        return (int)Database::lastInsertId();
    }

    /**
     * Updates an existing category record.
     *
     * @param int $id
     * @param string $name
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(int $id, string $name): bool
    {
        $cleanName = trim($name);

        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid category ID specified.');
        }

        if ($cleanName === '') {
            throw new InvalidArgumentException('Category name cannot be empty.');
        }

        if (strlen($cleanName) > 255) {
            throw new InvalidArgumentException('Category name cannot exceed 255 characters.');
        }

        if ($this->existsByName($cleanName, $id)) {
            throw new InvalidArgumentException("Another category named '{$cleanName}' already exists.");
        }

        $sql = "UPDATE `categories` SET `name` = :name WHERE `id` = :id";
        $affected = Database::execute($sql, [
            ':name' => $cleanName,
            ':id'   => $id,
        ]);

        return $affected >= 0;
    }

    /**
     * Deletes a category by ID, enforcing relational integrity.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid category ID specified.');
        }

        $subcatCount = $this->getSubcategoriesCount($id);
        if ($subcatCount > 0) {
            throw new RuntimeException("Cannot delete this category because it has {$subcatCount} subcategor(ies) attached. Please reassign or delete them first.");
        }

        $gamesCount = $this->getGamesCount($id);
        if ($gamesCount > 0) {
            throw new RuntimeException("Cannot delete this category because it is assigned to {$gamesCount} game(s). Please reassign those games before deleting.");
        }

        $sql = "DELETE FROM `categories` WHERE `id` = :id";
        $affected = Database::execute($sql, [':id' => $id]);

        return $affected > 0;
    }
}
