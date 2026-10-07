<?php
/**
 * src/Repositories/DownloadRepository.php
 * Repository for managing downloadable file pointers for consoles and games.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;
use InvalidArgumentException;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class DownloadRepository
{
    /**
     * Retrieve a downloadable file record by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                df.*,
                c.name AS console_name,
                g.title AS game_title
            FROM `downloadable_files` df
            LEFT JOIN `consoles` c ON c.id = df.console_id
            LEFT JOIN `games` g ON g.id = df.game_id
            WHERE df.id = :id
            LIMIT 1
        ";
        return Database::fetchOne($sql, [':id' => $id]);
    }

    /**
     * Retrieve all downloadable files for a specific console.
     *
     * @param int $consoleId
     * @return array<int, array<string, mixed>>
     */
    public function getByConsoleId(int $consoleId): array
    {
        $sql = "
            SELECT *
            FROM `downloadable_files`
            WHERE console_id = :console_id
            ORDER BY display_name ASC
        ";
        return Database::fetchAll($sql, [':console_id' => $consoleId]);
    }

    /**
     * Retrieve all downloadable files for a specific game.
     *
     * @param int $gameId
     * @return array<int, array<string, mixed>>
     */
    public function getByGameId(int $gameId): array
    {
        $sql = "
            SELECT *
            FROM `downloadable_files`
            WHERE game_id = :game_id
            ORDER BY display_name ASC
        ";
        return Database::fetchAll($sql, [':game_id' => $gameId]);
    }

    /**
     * Retrieve all downloadable files with optional filtering.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                df.*,
                c.name AS console_name,
                g.title AS game_title
            FROM `downloadable_files` df
            LEFT JOIN `consoles` c ON c.id = df.console_id
            LEFT JOIN `games` g ON g.id = df.game_id
            ORDER BY df.id DESC
        ";
        return Database::fetchAll($sql);
    }

    /**
     * Increment download count and update last_download timestamp.
     *
     * @param int $id
     * @return bool
     */
    public function recordDownload(int $id): bool
    {
        $sql = "
            UPDATE `downloadable_files`
            SET download_count = download_count + 1,
                last_download = CURRENT_TIMESTAMP
            WHERE id = :id
        ";
        return Database::execute($sql, [':id' => $id]) > 0;
    }

    /**
     * Create a new downloadable file record.
     *
     * @param array<string, mixed> $data
     * @return int
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO `downloadable_files` (
                console_id,
                game_id,
                display_name,
                storage_provider,
                file_key_or_url,
                download_count
            ) VALUES (
                :console_id,
                :game_id,
                :display_name,
                :storage_provider,
                :file_key_or_url,
                :download_count
            )
        ";

        Database::execute($sql, [
            ':console_id'        => !empty($data['console_id']) ? (int)$data['console_id'] : null,
            ':game_id'           => !empty($data['game_id']) ? (int)$data['game_id'] : null,
            ':display_name'      => trim((string)($data['display_name'] ?? '')),
            ':storage_provider'  => ($data['storage_provider'] ?? '') === 'blackblaze' ? 'blackblaze' : 'external',
            ':file_key_or_url'   => trim((string)($data['file_key_or_url'] ?? '')),
            ':download_count'    => (int)($data['download_count'] ?? 0),
        ]);

        return (int)Database::lastInsertId();
    }

    /**
     * Update an existing downloadable file record.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE `downloadable_files`
            SET console_id = :console_id,
                game_id = :game_id,
                display_name = :display_name,
                storage_provider = :storage_provider,
                file_key_or_url = :file_key_or_url
            WHERE id = :id
        ";

        return Database::execute($sql, [
            ':id'                => $id,
            ':console_id'        => !empty($data['console_id']) ? (int)$data['console_id'] : null,
            ':game_id'           => !empty($data['game_id']) ? (int)$data['game_id'] : null,
            ':display_name'      => trim((string)($data['display_name'] ?? '')),
            ':storage_provider'  => ($data['storage_provider'] ?? '') === 'blackblaze' ? 'blackblaze' : 'external',
            ':file_key_or_url'   => trim((string)($data['file_key_or_url'] ?? '')),
        ]) >= 0;
    }

    /**
     * Delete a downloadable file record.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return Database::execute("DELETE FROM `downloadable_files` WHERE id = :id", [':id' => $id]) > 0;
    }

    /**
     * Records a reported broken link for a downloadable file in the broken_links table.
     *
     * @param int $fileId
     * @param int|null $userId
     * @return bool
     */
    public function reportBrokenLink(int $fileId, ?int $userId = null): bool
    {
        $file = $this->getById($fileId);
        if (!$file) {
            return false;
        }

        $sql = "
            INSERT INTO `broken_links` (file_id, user_id, status, created, updated)
            VALUES (:file_id, :user_id, 'open', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ";

        return Database::execute($sql, [
            ':file_id' => $fileId,
            ':user_id' => $userId > 0 ? $userId : null,
        ]) > 0;
    }
}
