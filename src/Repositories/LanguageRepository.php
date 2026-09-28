<?php
/**
 * src/Repositories/LanguageRepository.php
 * Data repository managing taxonomy language entities and game references.
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

final class LanguageRepository
{
    /**
     * Retrieves all language records joined with linked games count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                l.id,
                l.name,
                COUNT(g.id) AS games_count,
                COUNT(g.id) AS game_count
            FROM `languages` l
            LEFT JOIN `games` g ON g.language_id = l.id
            GROUP BY l.id
            ORDER BY l.name ASC
        ";

        return Database::fetchAll($sql);
    }

    /**
     * Retrieves a single language record by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                l.id,
                l.name,
                COUNT(g.id) AS games_count,
                COUNT(g.id) AS game_count
            FROM `languages` l
            LEFT JOIN `games` g ON g.language_id = l.id
            WHERE l.id = :id
            GROUP BY l.id
        ";

        return Database::fetchOne($sql, [':id' => $id]);
    }

    /**
     * Checks if a language name already exists, optionally excluding a specific ID.
     *
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $trimmed = trim($name);
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `languages` WHERE LOWER(name) = LOWER(:name) AND id != :excludeId LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed, ':excludeId' => $excludeId]);
        } else {
            $sql = "SELECT id FROM `languages` WHERE LOWER(name) = LOWER(:name) LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed]);
        }

        return $res !== null;
    }

    /**
     * Counts how many games are assigned to a specific language.
     *
     * @param int $id
     * @return int
     */
    public function getGamesCount(int $id): int
    {
        $sql = "SELECT COUNT(id) AS total FROM `games` WHERE language_id = :id";
        $row = Database::fetchOne($sql, [':id' => $id]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Creates a new language entry.
     *
     * @param string $name
     * @return int Inserted record ID
     * @throws InvalidArgumentException
     */
    public function create(string $name): int
    {
        $cleanName = trim($name);

        if ($cleanName === '') {
            throw new InvalidArgumentException('Language name cannot be empty.');
        }

        if (strlen($cleanName) > 255) {
            throw new InvalidArgumentException('Language name cannot exceed 255 characters.');
        }

        if ($this->existsByName($cleanName)) {
            throw new InvalidArgumentException("A language named '{$cleanName}' already exists.");
        }

        $sql = "INSERT INTO `languages` (`name`) VALUES (:name)";
        Database::execute($sql, [':name' => $cleanName]);

        return (int)Database::lastInsertId();
    }

    /**
     * Updates an existing language record.
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
            throw new InvalidArgumentException('Invalid language ID specified.');
        }

        if ($cleanName === '') {
            throw new InvalidArgumentException('Language name cannot be empty.');
        }

        if (strlen($cleanName) > 255) {
            throw new InvalidArgumentException('Language name cannot exceed 255 characters.');
        }

        if ($this->existsByName($cleanName, $id)) {
            throw new InvalidArgumentException("Another language named '{$cleanName}' already exists.");
        }

        $sql = "UPDATE `languages` SET `name` = :name WHERE `id` = :id";
        $affected = Database::execute($sql, [
            ':name' => $cleanName,
            ':id'   => $id,
        ]);

        return $affected >= 0;
    }

    /**
     * Deletes a language by ID, enforcing relational integrity.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid language ID specified.');
        }

        $gamesCount = $this->getGamesCount($id);
        if ($gamesCount > 0) {
            throw new RuntimeException("Cannot delete this language because it is assigned to {$gamesCount} game(s). Please reassign those games before deleting.");
        }

        $sql = "DELETE FROM `languages` WHERE `id` = :id";
        $affected = Database::execute($sql, [':id' => $id]);

        return $affected > 0;
    }
}
