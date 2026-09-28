<?php
/**
 * src/Repositories/PublisherRepository.php
 * Data repository managing game publishers, console manufacturer flags, and catalog counts.
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

final class PublisherRepository
{
    /**
     * Retrieves all publishers along with console maker status, linked consoles, and game counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.is_console_maker,
                COUNT(DISTINCT c.id) AS consoles_count,
                COUNT(DISTINCT c.id) AS console_count,
                COUNT(DISTINCT g.id) AS games_count,
                COUNT(DISTINCT g.id) AS game_count,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name ASC SEPARATOR '||') AS consoles_raw
            FROM `publishers` p
            LEFT JOIN `consoles` c ON c.publisher_id = p.id
            LEFT JOIN `games` g ON g.publisher_id = p.id
            GROUP BY p.id
            ORDER BY p.name ASC
        ";

        $rows = Database::fetchAll($sql);

        return array_map(function (array $row): array {
            $raw = (string)($row['consoles_raw'] ?? '');
            $row['consoles'] = $raw !== '' ? explode('||', $raw) : [];
            $row['is_console_maker'] = (int)($row['is_console_maker'] ?? 0);
            unset($row['consoles_raw']);
            return $row;
        }, $rows);
    }

    /**
     * Retrieves only publishers marked as console / hardware manufacturers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConsoleMakers(): array
    {
        $sql = "
            SELECT id, name
            FROM `publishers`
            WHERE is_console_maker = 1
            ORDER BY name ASC
        ";

        return Database::fetchAll($sql);
    }

    /**
     * Retrieves a single publisher by ID with attached consoles and game counts.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.is_console_maker,
                COUNT(DISTINCT c.id) AS consoles_count,
                COUNT(DISTINCT c.id) AS console_count,
                COUNT(DISTINCT g.id) AS games_count,
                COUNT(DISTINCT g.id) AS game_count,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name ASC SEPARATOR '||') AS consoles_raw
            FROM `publishers` p
            LEFT JOIN `consoles` c ON c.publisher_id = p.id
            LEFT JOIN `games` g ON g.publisher_id = p.id
            WHERE p.id = :id
            GROUP BY p.id
        ";

        $row = Database::fetchOne($sql, [':id' => $id]);
        if (!$row) {
            return null;
        }

        $raw = (string)($row['consoles_raw'] ?? '');
        $row['consoles'] = $raw !== '' ? explode('||', $raw) : [];
        $row['is_console_maker'] = (int)($row['is_console_maker'] ?? 0);
        unset($row['consoles_raw']);

        return $row;
    }

    /**
     * Checks if a publisher name already exists, optionally excluding a specific ID.
     *
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $trimmed = trim($name);
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `publishers` WHERE LOWER(name) = LOWER(:name) AND id != :excludeId LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed, ':excludeId' => $excludeId]);
        } else {
            $sql = "SELECT id FROM `publishers` WHERE LOWER(name) = LOWER(:name) LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed]);
        }

        return $res !== null;
    }

    /**
     * Counts how many consoles are associated with a specific publisher.
     *
     * @param int $id
     * @return int
     */
    public function getConsolesCount(int $id): int
    {
        $sql = "SELECT COUNT(id) AS total FROM `consoles` WHERE publisher_id = :id";
        $row = Database::fetchOne($sql, [':id' => $id]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Counts how many games are assigned to a specific publisher.
     *
     * @param int $id
     * @return int
     */
    public function getGamesCount(int $id): int
    {
        $sql = "SELECT COUNT(id) AS total FROM `games` WHERE publisher_id = :id";
        $row = Database::fetchOne($sql, [':id' => $id]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Creates a new publisher entry.
     *
     * @param string $name
     * @param bool $isConsoleMaker
     * @return int Inserted record ID
     * @throws InvalidArgumentException
     */
    public function create(string $name, bool $isConsoleMaker = false): int
    {
        $cleanName = trim($name);

        if ($cleanName === '') {
            throw new InvalidArgumentException('Publisher name cannot be empty.');
        }

        if (strlen($cleanName) > 255) {
            throw new InvalidArgumentException('Publisher name cannot exceed 255 characters.');
        }

        if ($this->existsByName($cleanName)) {
            throw new InvalidArgumentException("A publisher named '{$cleanName}' already exists.");
        }

        $sql = "INSERT INTO `publishers` (`name`, `is_console_maker`) VALUES (:name, :is_console_maker)";
        Database::execute($sql, [
            ':name'             => $cleanName,
            ':is_console_maker' => $isConsoleMaker ? 1 : 0,
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Updates an existing publisher record.
     *
     * @param int $id
     * @param string $name
     * @param bool $isConsoleMaker
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(int $id, string $name, bool $isConsoleMaker = false): bool
    {
        $cleanName = trim($name);

        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid publisher ID specified.');
        }

        if ($cleanName === '') {
            throw new InvalidArgumentException('Publisher name cannot be empty.');
        }

        if (strlen($cleanName) > 255) {
            throw new InvalidArgumentException('Publisher name cannot exceed 255 characters.');
        }

        if ($this->existsByName($cleanName, $id)) {
            throw new InvalidArgumentException("Another publisher named '{$cleanName}' already exists.");
        }

        $sql = "UPDATE `publishers` SET `name` = :name, `is_console_maker` = :is_console_maker WHERE `id` = :id";
        $affected = Database::execute($sql, [
            ':name'             => $cleanName,
            ':is_console_maker' => $isConsoleMaker ? 1 : 0,
            ':id'               => $id,
        ]);

        return $affected >= 0;
    }

    /**
     * Deletes a publisher by ID, enforcing relational integrity.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid publisher ID specified.');
        }

        $consolesCount = $this->getConsolesCount($id);
        if ($consolesCount > 0) {
            throw new RuntimeException("Cannot delete this publisher because it has {$consolesCount} console platform(s) registered. Reassign or delete those consoles first.");
        }

        $gamesCount = $this->getGamesCount($id);
        if ($gamesCount > 0) {
            throw new RuntimeException("Cannot delete this publisher because it is assigned to {$gamesCount} game(s). Please reassign those games before deleting.");
        }

        $sql = "DELETE FROM `publishers` WHERE `id` = :id";
        $affected = Database::execute($sql, [':id' => $id]);

        return $affected > 0;
    }
}
