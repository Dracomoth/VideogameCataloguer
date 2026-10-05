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
                    g.language_id IS NULL OR g.language_id <= 0 OR
                    (g.in_collection = 1 AND NOT EXISTS (
                        SELECT 1 FROM downloadable_files df WHERE df.game_id = g.id
                    ))
                    THEN 1 ELSE 0 END) AS missing_basic,
                SUM(CASE WHEN 
                    NOT (
                        (g.title IS NULL OR TRIM(g.title) = '') OR
                        (g.year IS NULL OR TRIM(g.year) = '') OR
                        g.console_id IS NULL OR g.console_id <= 0 OR
                        g.category_id IS NULL OR g.category_id <= 0 OR
                        g.subcategory_id IS NULL OR g.subcategory_id <= 0 OR
                        g.publisher_id IS NULL OR g.publisher_id <= 0 OR
                        g.language_id IS NULL OR g.language_id <= 0 OR
                        (g.in_collection = 1 AND NOT EXISTS (
                            SELECT 1 FROM downloadable_files df WHERE df.game_id = g.id
                        ))
                    )
                    AND (
                        (g.boxart_path IS NULL OR TRIM(g.boxart_path) = '') OR
                        (g.screenshot_path IS NULL OR TRIM(g.screenshot_path) = '') OR
                        (g.tags IS NULL OR TRIM(g.tags) = '') OR
                        (g.comments IS NULL OR TRIM(g.comments) = '')
                    ) THEN 1 ELSE 0 END) AS missing_secondary,
                SUM(CASE WHEN 
                    NOT (
                        (g.title IS NULL OR TRIM(g.title) = '') OR
                        (g.year IS NULL OR TRIM(g.year) = '') OR
                        g.console_id IS NULL OR g.console_id <= 0 OR
                        g.category_id IS NULL OR g.category_id <= 0 OR
                        g.subcategory_id IS NULL OR g.subcategory_id <= 0 OR
                        g.publisher_id IS NULL OR g.publisher_id <= 0 OR
                        g.language_id IS NULL OR g.language_id <= 0 OR
                        (g.in_collection = 1 AND NOT EXISTS (
                            SELECT 1 FROM downloadable_files df WHERE df.game_id = g.id
                        ))
                    )
                    AND (g.boxart_path IS NOT NULL AND TRIM(g.boxart_path) != '')
                    AND (g.screenshot_path IS NOT NULL AND TRIM(g.screenshot_path) != '')
                    AND (g.tags IS NOT NULL AND TRIM(g.tags) != '')
                    AND (g.comments IS NOT NULL AND TRIM(g.comments) != '')
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

        $completePct  = $totalGames > 0 ? round(($completeGames / $totalGames) * 100, 1) : 0.0;
        $secondaryPct = $totalGames > 0 ? round(($missingSecondary / $totalGames) * 100, 1) : 0.0;
        $basicPct     = $totalGames > 0 ? round(($missingBasic / $totalGames) * 100, 1) : 0.0;

        $addedMonth = (int)Database::fetchColumn("
            SELECT COUNT(*) FROM games WHERE created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $priorTotal = max(0, $totalGames - $addedMonth);
        $growthPct = $priorTotal > 0
            ? round(($addedMonth / $priorTotal) * 100, 1)
            : ($addedMonth > 0 ? 100.0 : 0.0);

        return [
            'total_games'         => $totalGames,
            'owned_games'         => $ownedGames,
            'owned_pct'           => $ownedPct,
            'backlog_games'       => $ownedGames,
            'missing_basic'       => $missingBasic,
            'missing_secondary'   => $missingSecondary,
            'complete_games'      => $completeGames,
            'missing_covers'      => (int)($kpi['missing_covers'] ?? 0),
            'missing_screens'     => (int)($kpi['missing_screens'] ?? 0),
            'health_pct'          => $healthPct,
            'complete_pct'        => $completePct,
            'secondary_pct'       => $secondaryPct,
            'basic_pct'           => $basicPct,
            'catalog_growth_pct'  => $growthPct,
            'catalog_added_month' => $addedMonth,
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
     * Top 10 most downloaded games of all time.
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getTopDownloads(int $limit = 10): array
    {
        $sql = "
            SELECT g.id, g.title, g.boxart_path, c.id AS console_id, c.name AS console_name,
                   ct.name AS console_type_name, ct.badge_bg_color, ct.badge_font_color,
                   GREATEST(
                     COALESCE((SELECT SUM(df.download_count) FROM downloadable_files df WHERE df.game_id = g.id), 0),
                     COALESCE((SELECT SUM(pg.download_count) FROM player_games pg WHERE pg.game_id = g.id), 0)
                   ) AS download_count
            FROM games g
            LEFT JOIN consoles c ON c.id = g.console_id
            LEFT JOIN console_types ct ON ct.id = c.console_type_id
            HAVING download_count > 0
            ORDER BY download_count DESC, g.title ASC
            LIMIT {$limit}
        ";
        return Database::fetchAll($sql);
    }

    /**
     * Ranking of the most played consoles (Podium).
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getTopPlayedConsoles(int $limit = 5): array
    {
        $sql = "
            SELECT c.id, c.name AS console_name, ct.name AS console_type_name,
                   ct.badge_bg_color, ct.badge_font_color,
                   COUNT(DISTINCT pg.game_id) AS played_count,
                   SUM(CASE WHEN pg.is_won = 1 THEN 1 ELSE 0 END) AS won_count
            FROM player_games pg
            INNER JOIN games g ON g.id = pg.game_id
            INNER JOIN consoles c ON c.id = g.console_id
            LEFT JOIN console_types ct ON ct.id = c.console_type_id
            WHERE pg.is_played = 1
            GROUP BY c.id, c.name, ct.name, ct.badge_bg_color, ct.badge_font_color
            ORDER BY played_count DESC, won_count DESC, c.name ASC
            LIMIT {$limit}
        ";
        return Database::fetchAll($sql);
    }

    /**
     * Latest additions to the library (added within last 7 days or recent fallback).
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getLatestAdditions(int $limit = 10): array
    {
        $sql = "
            SELECT g.id, g.title, g.created, c.id AS console_id, c.name AS console_name,
                   ct.name AS console_type_name, ct.badge_bg_color, ct.badge_font_color
            FROM games g
            LEFT JOIN consoles c ON c.id = g.console_id
            LEFT JOIN console_types ct ON ct.id = c.console_type_id
            WHERE g.created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY g.created DESC, g.id DESC
            LIMIT {$limit}
        ";
        $results = Database::fetchAll($sql);

        if (count($results) < 3) {
            $sqlFallback = "
                SELECT g.id, g.title, g.created, c.id AS console_id, c.name AS console_name,
                       ct.name AS console_type_name, ct.badge_bg_color, ct.badge_font_color
                FROM games g
                LEFT JOIN consoles c ON c.id = g.console_id
                LEFT JOIN console_types ct ON ct.id = c.console_type_id
                ORDER BY g.created DESC, g.id DESC
                LIMIT {$limit}
            ";
            $results = Database::fetchAll($sqlFallback);
        }

        return $results;
    }

    /**
     * Returns full player dashboard data (downloaded/played/won stats, pie chart,
     * summary of life, currently playing games, and downloaded collection).
     *
     * @param int $playerId
     * @param int $totalCatalogGames
     * @return array<string, mixed>
     */
    public function getPlayerDashboardData(int $playerId, int $totalCatalogGames): array
    {
        $counts = Database::fetchOne("
            SELECT 
                COUNT(CASE WHEN is_downloaded = 1 THEN 1 END) AS downloaded_count,
                COUNT(CASE WHEN is_played = 1 THEN 1 END) AS played_count,
                COUNT(CASE WHEN is_won = 1 THEN 1 END) AS won_count
            FROM player_games
            WHERE player_id = :pid
        ", [':pid' => $playerId]) ?? [];

        $downloaded = (int)($counts['downloaded_count'] ?? 0);
        $played     = (int)($counts['played_count'] ?? 0);
        $won        = (int)($counts['won_count'] ?? 0);

        $downloadedPct = $totalCatalogGames > 0 ? round(($downloaded / $totalCatalogGames) * 100, 1) : 0.0;
        $playedPct     = $totalCatalogGames > 0 ? round(($played / $totalCatalogGames) * 100, 1) : 0.0;
        $wonPct        = $totalCatalogGames > 0 ? round(($won / $totalCatalogGames) * 100, 1) : 0.0;

        // Pie chart slices (Total 100% of catalog)
        $pieWon        = $won;
        $piePlaying    = max(0, $played - $won);
        $pieDownloaded = max(0, $downloaded - $played);
        $pieUntouched  = max(0, $totalCatalogGames - ($pieWon + $piePlaying + $pieDownloaded));

        $pieWonPct        = $totalCatalogGames > 0 ? round(($pieWon / $totalCatalogGames) * 100, 1) : 0.0;
        $piePlayingPct    = $totalCatalogGames > 0 ? round(($piePlaying / $totalCatalogGames) * 100, 1) : 0.0;
        $pieDownloadedPct = $totalCatalogGames > 0 ? round(($pieDownloaded / $totalCatalogGames) * 100, 1) : 0.0;
        $pieUntouchedPct  = max(0.0, round(100.0 - ($pieWonPct + $piePlayingPct + $pieDownloadedPct), 1));

        // Summary of life
        $favConsole = Database::fetchColumn("
            SELECT c.name
            FROM player_games pg
            JOIN games g ON g.id = pg.game_id
            JOIN consoles c ON c.id = g.console_id
            WHERE pg.player_id = :pid AND (pg.is_downloaded = 1 OR pg.is_played = 1)
            GROUP BY c.id, c.name
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ", [':pid' => $playerId]) ?: 'None yet';

        $favCategory = Database::fetchColumn("
            SELECT cat.name
            FROM player_games pg
            JOIN games g ON g.id = pg.game_id
            JOIN categories cat ON cat.id = g.category_id
            WHERE pg.player_id = :pid AND (pg.is_downloaded = 1 OR pg.is_played = 1)
            GROUP BY cat.id, cat.name
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ", [':pid' => $playerId]) ?: 'None yet';

        $favSubcategory = Database::fetchColumn("
            SELECT sub.name
            FROM player_games pg
            JOIN games g ON g.id = pg.game_id
            JOIN subcategories sub ON sub.id = g.subcategory_id
            WHERE pg.player_id = :pid AND (pg.is_downloaded = 1 OR pg.is_played = 1)
            GROUP BY sub.id, sub.name
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ", [':pid' => $playerId]) ?: 'None yet';

        $playWinRatio = $played > 0 ? round(($won / $played) * 100, 1) . '%' : '0.0%';

        // Currently playing
        $currentlyPlaying = Database::fetchAll("
            SELECT pg.id AS pg_id, pg.played_date, g.id AS game_id, g.title, g.boxart_path,
                   c.name AS console_name, ct.badge_bg_color, ct.badge_font_color,
                   cat.name AS category_name
            FROM player_games pg
            JOIN games g ON g.id = pg.game_id
            LEFT JOIN consoles c ON c.id = g.console_id
            LEFT JOIN console_types ct ON ct.id = c.console_type_id
            LEFT JOIN categories cat ON cat.id = g.category_id
            WHERE pg.player_id = :pid AND pg.is_played = 1 AND pg.is_won = 0
            ORDER BY pg.played_date DESC, pg.updated DESC
        ", [':pid' => $playerId]);

        // My Collection (Downloaded Games)
        $myCollection = Database::fetchAll("
            SELECT pg.id AS pg_id, pg.is_downloaded, pg.first_download_date, pg.last_download_date,
                   pg.is_played, pg.played_date, pg.is_won, pg.win_date,
                   g.id AS game_id, g.title, g.boxart_path,
                   c.id AS console_id, c.name AS console_name,
                   ct.name AS console_type_name, ct.badge_bg_color, ct.badge_font_color,
                   cat.id AS category_id, cat.name AS category_name,
                   sub.id AS subcategory_id, sub.name AS subcategory_name
            FROM player_games pg
            JOIN games g ON g.id = pg.game_id
            LEFT JOIN consoles c ON c.id = g.console_id
            LEFT JOIN console_types ct ON ct.id = c.console_type_id
            LEFT JOIN categories cat ON cat.id = g.category_id
            LEFT JOIN subcategories sub ON sub.id = g.subcategory_id
            WHERE pg.player_id = :pid AND pg.is_downloaded = 1
            ORDER BY pg.last_download_date DESC, pg.updated DESC
        ", [':pid' => $playerId]);

        return [
            'downloaded_count'    => $downloaded,
            'downloaded_pct'      => $downloadedPct,
            'played_count'        => $played,
            'played_pct'          => $playedPct,
            'won_count'           => $won,
            'won_pct'             => $wonPct,
            'pie' => [
                'won'            => $pieWon,
                'playing'        => $piePlaying,
                'downloaded'     => $pieDownloaded,
                'untouched'      => $pieUntouched,
                'won_pct'        => $pieWonPct,
                'playing_pct'    => $piePlayingPct,
                'downloaded_pct' => $pieDownloadedPct,
                'untouched_pct'  => $pieUntouchedPct,
            ],
            'summary' => [
                'favorite_console'     => $favConsole,
                'favorite_category'    => $favCategory,
                'favorite_subcategory' => $favSubcategory,
                'play_win_ratio'       => $playWinRatio,
            ],
            'currently_playing' => $currentlyPlaying,
            'my_collection'     => $myCollection,
        ];
    }
}