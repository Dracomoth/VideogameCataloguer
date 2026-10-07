<?php
/**
 * src/Repositories/BrokenLinkRepository.php
 * Repository for managing broken link reports and catalog maintenance.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class BrokenLinkRepository
{
    /**
     * Retrieves all open broken link reports with attached game, console, and file details.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOpenBrokenLinks(): array
    {
        $sql = "
            SELECT 
                bl.id AS report_id,
                bl.file_id,
                bl.user_id,
                bl.status,
                bl.created AS report_created,
                df.display_name AS file_name,
                df.file_key_or_url AS file_path,
                df.storage_provider,
                COALESCE(g.title, c.name, 'Unknown Resource') AS game_title,
                COALESCE(c.name, gc.name, 'System') AS console_name,
                ct.badge_bg_color,
                ct.badge_font_color,
                TRIM(CONCAT(COALESCE(u.first_name, 'Guest User'), ' ', COALESCE(u.last_name, ''))) AS reported_by
            FROM `broken_links` bl
            INNER JOIN `downloadable_files` df ON df.id = bl.file_id
            LEFT JOIN `games` g ON g.id = df.game_id
            LEFT JOIN `consoles` c ON c.id = df.console_id
            LEFT JOIN `consoles` gc ON gc.id = g.console_id
            LEFT JOIN `consoles` active_c ON active_c.id = COALESCE(c.id, gc.id)
            LEFT JOIN `console_types` ct ON ct.id = active_c.console_type_id
            LEFT JOIN `users` u ON u.id = bl.user_id
            WHERE bl.status = 'open'
            ORDER BY bl.created DESC, bl.id DESC
        ";

        return Database::fetchAll($sql);
    }

    /**
     * Fixes the downloadable file path/URL and sets the broken link report status to closed.
     *
     * @param int $reportId
     * @param int $fileId
     * @param string $newFilePath
     * @return bool
     */
    public function fixAndCloseReport(int $reportId, int $fileId, string $newFilePath): bool
    {
        $newFilePath = trim($newFilePath);
        if ($newFilePath === '') {
            return false;
        }

        return Database::transaction(function () use ($reportId, $fileId, $newFilePath): bool {
            // Update downloadable file path
            Database::execute("
                UPDATE `downloadable_files`
                SET file_key_or_url = :path
                WHERE id = :file_id
            ", [
                ':path'    => $newFilePath,
                ':file_id' => $fileId,
            ]);

            // Close broken link report
            Database::execute("
                UPDATE `broken_links`
                SET status = 'closed',
                    updated = CURRENT_TIMESTAMP
                WHERE id = :report_id
            ", [
                ':report_id' => $reportId,
            ]);

            return true;
        });
    }

    /**
     * Closes a broken link report without modifying the underlying downloadable file.
     *
     * @param int $reportId
     * @return bool
     */
    public function closeReport(int $reportId): bool
    {
        $sql = "
            UPDATE `broken_links`
            SET status = 'closed',
                updated = CURRENT_TIMESTAMP
            WHERE id = :report_id
        ";

        return Database::execute($sql, [':report_id' => $reportId]) > 0;
    }
}
