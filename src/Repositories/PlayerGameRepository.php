<?php
/**
 * src/Repositories/PlayerGameRepository.php
 * Repository for tracking per-player game statistics: downloads, plays, and completions.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class PlayerGameRepository
{
    /**
     * Retrieve a player_games record by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                pg.*,
                u.first_name,
                u.last_name,
                u.email,
                g.title AS game_title
            FROM `player_games` pg
            LEFT JOIN `users` u ON u.id = pg.player_id
            LEFT JOIN `games` g ON g.id = pg.game_id
            WHERE pg.id = :id
            LIMIT 1
        ";
        return Database::fetchOne($sql, [':id' => $id]);
    }

    /**
     * Finds an existing record for a specific player and game combination.
     *
     * @param int $playerId
     * @param int $gameId
     * @return array<string, mixed>|null
     */
    public function findByPlayerAndGame(int $playerId, int $gameId): ?array
    {
        $sql = "
            SELECT *
            FROM `player_games`
            WHERE `player_id` = :player_id AND `game_id` = :game_id
            LIMIT 1
        ";
        return Database::fetchOne($sql, [
            ':player_id' => $playerId,
            ':game_id'   => $gameId,
        ]);
    }

    /**
     * Records a game download event for a player according to business rules:
     * - If no entry exists for this game and player:
     *     - creates a new entry
     *     - is_downloaded = true (1)
     *     - first_download_date = CURRENT_TIMESTAMP
     *     - last_download_date = CURRENT_TIMESTAMP
     *     - download_count = 1
     *     - created = CURRENT_TIMESTAMP
     *     - updated = CURRENT_TIMESTAMP
     *     - remaining flags (is_played, is_won) = false (0)
     *     - remaining dates (played_date, win_date) = null
     * - If an entry already exists:
     *     - increases download_count by 1
     *     - sets last_download_date = CURRENT_TIMESTAMP
     *     - sets updated = CURRENT_TIMESTAMP
     *     - all other fields remain unchanged
     *
     * @param int $playerId
     * @param int $gameId
     * @return array<string, mixed> The inserted or updated record.
     */
    public function recordGameDownload(int $playerId, int $gameId): array
    {
        $existing = $this->findByPlayerAndGame($playerId, $gameId);

        if ($existing === null) {
            $sql = "
                INSERT INTO `player_games` (
                    `player_id`,
                    `game_id`,
                    `is_downloaded`,
                    `first_download_date`,
                    `last_download_date`,
                    `download_count`,
                    `is_played`,
                    `played_date`,
                    `is_won`,
                    `win_date`,
                    `created`,
                    `updated`
                ) VALUES (
                    :player_id,
                    :game_id,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    1,
                    0,
                    NULL,
                    0,
                    NULL,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ";

            Database::execute($sql, [
                ':player_id' => $playerId,
                ':game_id'   => $gameId,
            ]);

            $newId = (int)Database::lastInsertId();
            return $this->getById($newId) ?? [];
        }

        $sql = "
            UPDATE `player_games`
            SET `download_count` = `download_count` + 1,
                `last_download_date` = CURRENT_TIMESTAMP,
                `updated` = CURRENT_TIMESTAMP
            WHERE `id` = :id
        ";

        Database::execute($sql, [
            ':id' => (int)$existing['id'],
        ]);

        return $this->getById((int)$existing['id']) ?? [];
    }

    /**
     * Retrieve all game statistics for a specific player.
     *
     * @param int $playerId
     * @return array<int, array<string, mixed>>
     */
    public function getByPlayerId(int $playerId): array
    {
        $sql = "
            SELECT 
                pg.*,
                g.title AS game_title,
                g.screenshot_path,
                g.boxart_path
            FROM `player_games` pg
            INNER JOIN `games` g ON g.id = pg.game_id
            WHERE pg.player_id = :player_id
            ORDER BY pg.last_download_date DESC
        ";
        return Database::fetchAll($sql, [':player_id' => $playerId]);
    }

    /**
     * Retrieve all player statistics for a specific game.
     *
     * @param int $gameId
     * @return array<int, array<string, mixed>>
     */
    public function getByGameId(int $gameId): array
    {
        $sql = "
            SELECT 
                pg.*,
                u.first_name,
                u.last_name,
                u.email
            FROM `player_games` pg
            INNER JOIN `users` u ON u.id = pg.player_id
            WHERE pg.game_id = :game_id
            ORDER BY pg.last_download_date DESC
        ";
        return Database::fetchAll($sql, [':game_id' => $gameId]);
    }

    /**
     * Updates game play and win status for a specific player according to business rules:
     * - A game that is not marked as played cannot be marked as won.
     * - When marked as played, sets played_date to CURRENT_TIMESTAMP if not already set.
     * - When marked as unplayed, clears played_date, is_won (to 0), and win_date (to null).
     * - When marked as won, sets win_date to CURRENT_TIMESTAMP if not already set.
     * - When marked as unwon, clears win_date to null.
     *
     * @param int $playerId
     * @param int $gameId
     * @param bool $isPlayed
     * @param bool $isWon
     * @return array<string, mixed> The updated record.
     */
    public function updateGameStatus(int $playerId, int $gameId, bool $isPlayed, bool $isWon): array
    {
        // Enforce rule: cannot be won if not played
        if (!$isPlayed) {
            $isWon = false;
        }

        $existing = $this->findByPlayerAndGame($playerId, $gameId);

        if ($existing === null) {
            $playedDate = $isPlayed ? date('Y-m-d H:i:s') : null;
            $winDate    = $isWon ? date('Y-m-d H:i:s') : null;

            $sql = "
                INSERT INTO `player_games` (
                    `player_id`,
                    `game_id`,
                    `is_downloaded`,
                    `first_download_date`,
                    `last_download_date`,
                    `download_count`,
                    `is_played`,
                    `played_date`,
                    `is_won`,
                    `win_date`,
                    `created`,
                    `updated`
                ) VALUES (
                    :player_id,
                    :game_id,
                    0,
                    NULL,
                    NULL,
                    0,
                    :is_played,
                    :played_date,
                    :is_won,
                    :win_date,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ";

            Database::execute($sql, [
                ':player_id'   => $playerId,
                ':game_id'     => $gameId,
                ':is_played'   => $isPlayed ? 1 : 0,
                ':played_date' => $playedDate,
                ':is_won'      => $isWon ? 1 : 0,
                ':win_date'    => $winDate,
            ]);

            $newId = (int)Database::lastInsertId();
            return $this->getById($newId) ?? [];
        }

        $playedDate = $existing['played_date'];
        if ($isPlayed && empty($playedDate)) {
            $playedDate = date('Y-m-d H:i:s');
        } elseif (!$isPlayed) {
            $playedDate = null;
        }

        $winDate = $existing['win_date'];
        if ($isWon && empty($winDate)) {
            $winDate = date('Y-m-d H:i:s');
        } elseif (!$isWon) {
            $winDate = null;
        }

        $sql = "
            UPDATE `player_games`
            SET `is_played`   = :is_played,
                `played_date` = :played_date,
                `is_won`      = :is_won,
                `win_date`    = :win_date,
                `updated`     = CURRENT_TIMESTAMP
            WHERE `id` = :id
        ";

        Database::execute($sql, [
            ':id'          => (int)$existing['id'],
            ':is_played'   => $isPlayed ? 1 : 0,
            ':played_date' => $playedDate,
            ':is_won'      => $isWon ? 1 : 0,
            ':win_date'    => $winDate,
        ]);

        return $this->getById((int)$existing['id']) ?? [];
    }
}
