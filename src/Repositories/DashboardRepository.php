<?php
/**
 * src/Repositories/DashboardRepository.php
 * Aggregates collection metrics, system breakdowns, and backlog telemetry.
 */

declare(strict_types=1);

namespace Vault\Repositories;

use Vault\Services\Database;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class DashboardRepository
{
    /**
     * Retrieves top-level KPI metrics for the collection.
     *
     * @return array<string, int|float>
     */
    public function getKpiMetrics(): array
    {
        $sql = "
            SELECT 
                COUNT(*) AS total_games,
                SUM(CASE WHEN g.in_collection = 1 THEN 1 ELSE 0 END) AS owned_games,
                SUM(CASE WHEN g.in_collection = 1 AND g.is_played = 0 AND g.is_won = 0 THEN 1 ELSE 0 END) AS backlog_games,
                SUM(CASE WHEN g.in_collection = 1 AND g.is_played = 1 AND g.is_won = 0 THEN 1 ELSE 0 END) AS playing_games,
                SUM(CASE WHEN g.is_won = 1 THEN 1 ELSE 0 END) AS won_games,
                SUM(CASE WHEN g.boxart_path IS NULL OR TRIM(g.boxart_path) = '' THEN 1 ELSE 0 END) AS missing_covers,
                SUM(CASE WHEN g.screenshot_path IS NULL OR TRIM(g.screenshot_path) = '' THEN 1 ELSE 0 END) AS missing_screens,
                SUM(CASE WHEN (g.boxart_path IS NOT NULL AND TRIM(g.boxart_path) != '') 
                         AND (g.screenshot_path IS NOT NULL AND TRIM(g.screenshot_path) != '') THEN 1 ELSE 0 END) AS fully_documented
            FROM games g
        ";

        $kpi = Database::fetchOne($sql) ?? [];

        $totalGames      = (int)($kpi['total_games'] ?? 0);
        $ownedGames      = (int)($kpi['owned_games'] ?? 0);
        $wonGames        = (int)($kpi['won_games'] ?? 0);
        $fullyDocumented = (int)($kpi['fully_documented'] ?? 0);

        $healthPct = $totalGames > 0 
            ? round(($fullyDocumented / $totalGames) * 100, 1) 
            : 0.0;

        $completionPct = $ownedGames > 0 
            ? round(($wonGames / $ownedGames) * 100, 1) 
            : 0.0;

        return [
            'total_games'      => $totalGames,
            'owned_games'      => $ownedGames,
            'backlog_games'    => (int)($kpi['backlog_games'] ?? 0),
            'playing_games'    => (int)($kpi['playing_games'] ?? 0),
            'won_games'        => $wonGames,
            'missing_covers'   => (int)($kpi['missing_covers'] ?? 0),
            'missing_screens'  => (int)($kpi['missing_screens'] ?? 0),
            'fully_documented' => $fullyDocumented,
            'health_pct'       => $healthPct,
            'completion_pct'   => $completionPct,
        ];
    }

    /**
     * Counts totals across taxonomy and reference entities.
     *
     * @return array<string, int>
     */
    public function getEntityCounts(): array
    {
        return [
            'consoles'      => (int)Database::fetchColumn("SELECT COUNT(*) FROM consoles"),
            'publishers'    => (int)Database::fetchColumn("SELECT COUNT(*) FROM publishers"),
            'categories'    => (int)Database::fetchColumn("SELECT COUNT(*) FROM categories"),
            'subcategories' => (int)Database::fetchColumn("SELECT COUNT(*) FROM subcategories"),
            'languages'     => (int)Database::fetchColumn("SELECT COUNT(*) FROM languages"),
        ];
    }

    /**
     * Returns top systems ranked by owned title count.
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getTopConsoles(int $limit = 6): array
    {
        $sql = "
            SELECT 
                c.id, 
                c.name AS console, 
                c.is_handheld,
                COUNT(g.id) AS total_titles,
                SUM(CASE WHEN g.in_collection = 1 THEN 1 ELSE 0 END) AS owned_titles
            FROM consoles c
            INNER JOIN games g ON g.console_id = c.id
            GROUP BY c.id, c.name, c.is_handheld
            ORDER BY owned_titles DESC, total_titles DESC
            LIMIT {$limit}
        ";

        return Database::fetchAll($sql);
    }

    /**
     * Returns active in-progress game records.
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getCurrentlyPlaying(int $limit = 4): array
    {
        $sql = "
            SELECT 
                g.id, 
                g.title AS game, 
                g.year, 
                g.boxart_path, 
                c.name AS console
            FROM games g
            LEFT JOIN consoles c ON g.console_id = c.id
            WHERE g.in_collection = 1 AND g.is_played = 1 AND g.is_won = 0
            ORDER BY g.id DESC
            LIMIT {$limit}
        ";

        return Database::fetchAll($sql);
    }
}