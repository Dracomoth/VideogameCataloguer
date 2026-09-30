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
                SUM(CASE WHEN 
                    (g.title IS NULL OR TRIM(g.title) = '') OR
                    (g.year IS NULL OR TRIM(g.year) = '') OR
                    g.console_id IS NULL OR g.console_id <= 0 OR
                    g.category_id IS NULL OR g.category_id <= 0 OR
                    g.subcategory_id IS NULL OR g.subcategory_id <= 0 OR
                    g.publisher_id IS NULL OR g.publisher_id <= 0 OR
                    g.language_id IS NULL OR g.language_id <= 0
                    THEN 1 ELSE 0 END) AS missing_basic,
                SUM(CASE WHEN 
                    NOT (
                        (g.title IS NULL OR TRIM(g.title) = '') OR
                        (g.year IS NULL OR TRIM(g.year) = '') OR
                        g.console_id IS NULL OR g.console_id <= 0 OR
                        g.category_id IS NULL OR g.category_id <= 0 OR
                        g.subcategory_id IS NULL OR g.subcategory_id <= 0 OR
                        g.publisher_id IS NULL OR g.publisher_id <= 0 OR
                        g.language_id IS NULL OR g.language_id <= 0
                    )
                    AND (
                        (g.boxart_path IS NULL OR TRIM(g.boxart_path) = '') OR
                        (g.screenshot_path IS NULL OR TRIM(g.screenshot_path) = '')
                    ) THEN 1 ELSE 0 END) AS missing_secondary,
                SUM(CASE WHEN 
                    NOT (
                        (g.title IS NULL OR TRIM(g.title) = '') OR
                        (g.year IS NULL OR TRIM(g.year) = '') OR
                        g.console_id IS NULL OR g.console_id <= 0 OR
                        g.category_id IS NULL OR g.category_id <= 0 OR
                        g.subcategory_id IS NULL OR g.subcategory_id <= 0 OR
                        g.publisher_id IS NULL OR g.publisher_id <= 0 OR
                        g.language_id IS NULL OR g.language_id <= 0
                    )
                    AND (g.boxart_path IS NOT NULL AND TRIM(g.boxart_path) != '')
                    AND (g.screenshot_path IS NOT NULL AND TRIM(g.screenshot_path) != '')
                    THEN 1 ELSE 0 END) AS complete_games,
                SUM(CASE WHEN g.boxart_path IS NULL OR TRIM(g.boxart_path) = '' THEN 1 ELSE 0 END) AS missing_covers,
                SUM(CASE WHEN g.screenshot_path IS NULL OR TRIM(g.screenshot_path) = '' THEN 1 ELSE 0 END) AS missing_screens
            FROM games g
        ";

        $kpi = Database::fetchOne($sql) ?? [];

        $totalGames       = (int)($kpi['total_games'] ?? 0);
        $ownedGames       = (int)($kpi['owned_games'] ?? 0);
        $missingBasic     = (int)($kpi['missing_basic'] ?? 0);
        $missingSecondary = (int)($kpi['missing_secondary'] ?? 0);
        $completeGames    = (int)($kpi['complete_games'] ?? 0);

        $ownedPct = $totalGames > 0
            ? round(($ownedGames / $totalGames) * 100, 1)
            : 0.0;

        $healthPct = $totalGames > 0 
            ? round(($completeGames / $totalGames) * 100, 1) 
            : 0.0;

        return [
            'total_games'       => $totalGames,
            'owned_games'       => $ownedGames,
            'owned_pct'         => $ownedPct,
            'backlog_games'     => $ownedGames,
            'missing_basic'     => $missingBasic,
            'missing_secondary' => $missingSecondary,
            'complete_games'    => $completeGames,
            'missing_covers'    => (int)($kpi['missing_covers'] ?? 0),
            'missing_screens'   => (int)($kpi['missing_screens'] ?? 0),
            'health_pct'        => $healthPct,
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
            'console_types' => (int)Database::fetchColumn("SELECT COUNT(*) FROM console_types"),
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
                c.console_type_id,
                ct.name AS console_type_name,
                ct.badge_bg_color,
                ct.badge_font_color,
                COUNT(g.id) AS total_titles,
                SUM(CASE WHEN g.in_collection = 1 THEN 1 ELSE 0 END) AS owned_titles
            FROM consoles c
            LEFT JOIN console_types ct ON c.console_type_id = ct.id
            INNER JOIN games g ON g.console_id = c.id
            GROUP BY c.id, c.name, c.console_type_id, ct.name, ct.badge_bg_color, ct.badge_font_color
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
        return [];
    }
}