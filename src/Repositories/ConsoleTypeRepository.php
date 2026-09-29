<?php
/**
 * src/Repositories/ConsoleTypeRepository.php
 * Data repository managing console hardware types and their badge color styling.
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

final class ConsoleTypeRepository
{
    /**
     * Retrieves all console types joined with linked consoles count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                ct.id,
                ct.name,
                ct.badge_bg_color,
                ct.badge_font_color,
                ct.created_at,
                ct.updated_at,
                COUNT(c.id) AS consoles_count,
                COUNT(c.id) AS console_count
            FROM `console_types` ct
            LEFT JOIN `consoles` c ON c.console_type_id = ct.id
            GROUP BY ct.id
            ORDER BY ct.id ASC
        ";

        return Database::fetchAll($sql);
    }

    /**
     * Retrieves a single console type record by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                ct.id,
                ct.name,
                ct.badge_bg_color,
                ct.badge_font_color,
                ct.created_at,
                ct.updated_at,
                COUNT(c.id) AS consoles_count,
                COUNT(c.id) AS console_count
            FROM `console_types` ct
            LEFT JOIN `consoles` c ON c.console_type_id = ct.id
            WHERE ct.id = :id
            GROUP BY ct.id
            LIMIT 1
        ";

        return Database::fetchOne($sql, [':id' => $id]);
    }

    /**
     * Checks if a console type name already exists, optionally excluding a specific ID.
     *
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $trimmed = trim($name);
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `console_types` WHERE LOWER(name) = LOWER(:name) AND id != :excludeId LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed, ':excludeId' => $excludeId]);
        } else {
            $sql = "SELECT id FROM `console_types` WHERE LOWER(name) = LOWER(:name) LIMIT 1";
            $res = Database::fetchOne($sql, [':name' => $trimmed]);
        }

        return $res !== null;
    }

    /**
     * Counts how many consoles are assigned to a specific console type.
     *
     * @param int $id
     * @return int
     */
    public function getConsolesCount(int $id): int
    {
        $sql = "SELECT COUNT(id) AS total FROM `consoles` WHERE console_type_id = :id";
        $row = Database::fetchOne($sql, [':id' => $id]);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Creates a new console type entry.
     *
     * @param string $name
     * @param string $badgeBgColor
     * @param string $badgeFontColor
     * @return int Inserted record ID
     * @throws InvalidArgumentException
     */
    public function create(string $name, string $badgeBgColor, string $badgeFontColor): int
    {
        $cleanName      = trim($name);
        $cleanBgColor   = $this->validateHexColor($badgeBgColor, 'Background color');
        $cleanFontColor = $this->validateHexColor($badgeFontColor, 'Font color');

        if ($cleanName === '') {
            throw new InvalidArgumentException('Console type name cannot be empty.');
        }

        if (strlen($cleanName) > 100) {
            throw new InvalidArgumentException('Console type name cannot exceed 100 characters.');
        }

        if ($this->existsByName($cleanName)) {
            throw new InvalidArgumentException("A console type named '{$cleanName}' already exists.");
        }

        $sql = "
            INSERT INTO `console_types` (`name`, `badge_bg_color`, `badge_font_color`) 
            VALUES (:name, :badge_bg_color, :badge_font_color)
        ";

        Database::execute($sql, [
            ':name'             => $cleanName,
            ':badge_bg_color'   => $cleanBgColor,
            ':badge_font_color' => $cleanFontColor,
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Updates an existing console type entry.
     *
     * @param int $id
     * @param string $name
     * @param string $badgeBgColor
     * @param string $badgeFontColor
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(int $id, string $name, string $badgeBgColor, string $badgeFontColor): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid console type ID specified.');
        }

        $cleanName      = trim($name);
        $cleanBgColor   = $this->validateHexColor($badgeBgColor, 'Background color');
        $cleanFontColor = $this->validateHexColor($badgeFontColor, 'Font color');

        if ($cleanName === '') {
            throw new InvalidArgumentException('Console type name cannot be empty.');
        }

        if (strlen($cleanName) > 100) {
            throw new InvalidArgumentException('Console type name cannot exceed 100 characters.');
        }

        if ($this->existsByName($cleanName, $id)) {
            throw new InvalidArgumentException("Another console type named '{$cleanName}' already exists.");
        }

        $sql = "
            UPDATE `console_types` 
            SET `name` = :name, 
                `badge_bg_color` = :badge_bg_color, 
                `badge_font_color` = :badge_font_color 
            WHERE `id` = :id
        ";

        $affected = Database::execute($sql, [
            ':name'             => $cleanName,
            ':badge_bg_color'   => $cleanBgColor,
            ':badge_font_color' => $cleanFontColor,
            ':id'               => $id,
        ]);

        return $affected >= 0;
    }

    /**
     * Deletes a console type if unreferenced by consoles.
     *
     * @param int $id
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid console type ID specified.');
        }

        $consolesCount = $this->getConsolesCount($id);
        if ($consolesCount > 0) {
            throw new RuntimeException("Cannot delete: {$consolesCount} console(s) are currently assigned to this type.");
        }

        $sql = "DELETE FROM `console_types` WHERE `id` = :id";
        return Database::execute($sql, [':id' => $id]) > 0;
    }

    /**
     * Validates and normalizes a 6-digit hex color string (e.g. #0F88AA).
     *
     * @param string $color
     * @param string $label
     * @return string Normalized uppercase hex code with leading '#'
     * @throws InvalidArgumentException
     */
    private function validateHexColor(string $color, string $label): string
    {
        $trimmed = trim($color);
        if (!preg_match('/^#?([0-9a-fA-F]{6})$/', $trimmed, $matches)) {
            throw new InvalidArgumentException("{$label} must be a valid 6-digit hex code (e.g. #0F88AA).");
        }
        return '#' . strtoupper($matches[1]);
    }
}
