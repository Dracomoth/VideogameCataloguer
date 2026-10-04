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
    private string $publishersImageDir;

    public function __construct()
    {
        $this->publishersImageDir = dirname(__DIR__, 2) . '/images/publishers';
        if (!is_dir($this->publishersImageDir)) {
            @mkdir($this->publishersImageDir, 0775, true);
        }
    }

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
                p.description,
                p.logo_path,
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
            $row['description'] = $row['description'] !== null ? (string)$row['description'] : '';
            $row['logo_path'] = ($row['logo_path'] !== null && $row['logo_path'] !== '') ? (string)$row['logo_path'] : null;
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
                p.description,
                p.logo_path,
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
        $row['description'] = $row['description'] !== null ? (string)$row['description'] : '';
        $row['logo_path'] = ($row['logo_path'] !== null && $row['logo_path'] !== '') ? (string)$row['logo_path'] : null;
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
     * Stores an uploaded logo file for a publisher using the naming pattern "{id}_logo.{ext}".
     *
     * @param array<string, mixed> $file PHP $_FILES entry
     * @param int $publisherId
     * @return string Relative path stored in database (e.g. "images/publishers/12_logo.png")
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function storeLogoFile(array $file, int $publisherId): string
    {
        if (empty($file['tmp_name']) || (!is_uploaded_file($file['tmp_name']) && !file_exists($file['tmp_name']))) {
            throw new InvalidArgumentException("No valid uploaded file found for publisher logo.");
        }

        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Upload failed for publisher logo with error code {$file['error']}.");
        }

        $allowedMimes = [
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/webp'    => 'webp',
            'image/gif'     => 'gif',
            'image/svg+xml' => 'svg',
        ];

        $ext = null;
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (isset($allowedMimes[$mime])) {
                $ext = $allowedMimes[$mime];
            }
        }

        if ($ext === null) {
            $origExt = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (in_array($origExt, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
                $ext = ($origExt === 'jpeg') ? 'jpg' : $origExt;
            }
        }

        if ($ext === null) {
            throw new InvalidArgumentException("Invalid file format for logo. Allowed formats: JPG, PNG, WEBP, GIF, SVG.");
        }

        $filename = "{$publisherId}_logo.{$ext}";
        $destination = $this->publishersImageDir . '/' . $filename;

        // Purge any prior logo files for this publisher ID regardless of extension
        $this->deleteLogoFileOnly($publisherId);

        $saved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $destination)
            : copy($file['tmp_name'], $destination);

        if (!$saved) {
            throw new RuntimeException("Failed to save publisher logo asset to disk.");
        }

        @chmod($destination, 0664);

        return "images/publishers/{$filename}";
    }

    /**
     * Purges physical logo file(s) for a publisher from disk.
     *
     * @param int $publisherId
     */
    public function deleteLogoFileOnly(int $publisherId): void
    {
        $pattern = $this->publishersImageDir . '/' . $publisherId . '_logo.*';
        $files = glob($pattern);
        if (is_array($files)) {
            foreach ($files as $f) {
                if (is_file($f)) {
                    @unlink($f);
                    clearstatcache(true, $f);
                }
            }
        }
    }

    /**
     * Removes the logo for a publisher (both physical file and database field).
     *
     * @param int $publisherId
     */
    public function removeLogo(int $publisherId): void
    {
        $this->deleteLogoFileOnly($publisherId);
        $sql = "UPDATE `publishers` SET `logo_path` = NULL WHERE `id` = :id";
        Database::execute($sql, [':id' => $publisherId]);
    }

    /**
     * Creates a new publisher entry.
     *
     * @param string $name
     * @param bool $isConsoleMaker
     * @param string|null $description
     * @param array<string, mixed>|null $logoFile
     * @return int Inserted record ID
     * @throws InvalidArgumentException
     */
    public function create(string $name, bool $isConsoleMaker = false, ?string $description = null, ?array $logoFile = null): int
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

        $cleanDesc = $description !== null ? trim($description) : null;
        if ($cleanDesc === '') {
            $cleanDesc = null;
        }

        $sql = "INSERT INTO `publishers` (`name`, `is_console_maker`, `description`, `logo_path`) VALUES (:name, :is_console_maker, :description, NULL)";
        Database::execute($sql, [
            ':name'             => $cleanName,
            ':is_console_maker' => $isConsoleMaker ? 1 : 0,
            ':description'      => $cleanDesc,
        ]);

        $newId = (int)Database::lastInsertId();

        if ($logoFile !== null && !empty($logoFile['tmp_name'])) {
            $logoPath = $this->storeLogoFile($logoFile, $newId);
            Database::execute("UPDATE `publishers` SET `logo_path` = :logo_path WHERE `id` = :id", [
                ':logo_path' => $logoPath,
                ':id'        => $newId,
            ]);
        }

        return $newId;
    }

    /**
     * Updates an existing publisher record.
     *
     * @param int $id
     * @param string $name
     * @param bool $isConsoleMaker
     * @param string|null $description
     * @param array<string, mixed>|null $logoFile
     * @param bool $removeLogo
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(int $id, string $name, bool $isConsoleMaker = false, ?string $description = null, ?array $logoFile = null, bool $removeLogo = false): bool
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

        $cleanDesc = $description !== null ? trim($description) : null;
        if ($cleanDesc === '') {
            $cleanDesc = null;
        }

        $existing = $this->getById($id);
        $currentLogoPath = $existing['logo_path'] ?? null;

        if ($removeLogo) {
            $this->deleteLogoFileOnly($id);
            $currentLogoPath = null;
        }

        if ($logoFile !== null && !empty($logoFile['tmp_name'])) {
            $currentLogoPath = $this->storeLogoFile($logoFile, $id);
        }

        $sql = "UPDATE `publishers` SET `name` = :name, `is_console_maker` = :is_console_maker, `description` = :description, `logo_path` = :logo_path WHERE `id` = :id";
        $affected = Database::execute($sql, [
            ':name'             => $cleanName,
            ':is_console_maker' => $isConsoleMaker ? 1 : 0,
            ':description'      => $cleanDesc,
            ':logo_path'        => $currentLogoPath,
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

        $this->deleteLogoFileOnly($id);

        $sql = "DELETE FROM `publishers` WHERE `id` = :id";
        $affected = Database::execute($sql, [':id' => $id]);

        return $affected > 0;
    }

    /**
     * Retrieves all consoles released by a publisher.
     *
     * @param int $publisherId
     * @return array<int, array<string, mixed>>
     */
    public function getConsolesByPublisher(int $publisherId): array
    {
        $sql = "
            SELECT 
                c.id,
                c.name,
                c.image_path,
                c.logo_path,
                c.year,
                c.generation,
                ct.name AS console_type_name
            FROM `consoles` c
            LEFT JOIN `console_types` ct ON ct.id = c.console_type_id
            WHERE c.publisher_id = :publisher_id
            ORDER BY c.year ASC, c.name ASC
        ";

        return Database::fetchAll($sql, [':publisher_id' => $publisherId]);
    }

    /**
     * Retrieves random games released by a publisher.
     *
     * @param int $publisherId
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getRandomGamesByPublisher(int $publisherId, int $limit = 9): array
    {
        $limit = max(1, (int)$limit);
        $sql = "
            SELECT 
                g.id,
                g.title,
                g.boxart_path,
                g.screenshot_path,
                g.year,
                c.name AS console_name
            FROM `games` g
            LEFT JOIN `consoles` c ON c.id = g.console_id
            WHERE g.publisher_id = :publisher_id
            ORDER BY RAND()
            LIMIT {$limit}
        ";

        return Database::fetchAll($sql, [':publisher_id' => $publisherId]);
    }

    /**
     * Retrieves full publisher portal details (metadata, consoles, random games).
     *
     * @param int $publisherId
     * @return array<string, mixed>|null
     */
    public function getPublisherPortalDetail(int $publisherId): ?array
    {
        $publisher = $this->getById($publisherId);
        if (!$publisher) {
            return null;
        }

        $consoles = (int)$publisher['is_console_maker'] === 1
            ? $this->getConsolesByPublisher($publisherId)
            : [];
        $randomGames = $this->getRandomGamesByPublisher($publisherId, 9);

        return [
            'publisher'    => $publisher,
            'consoles'     => $consoles,
            'random_games' => $randomGames,
        ];
    }
}

