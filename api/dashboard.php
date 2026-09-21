<?php
/**
 * api/dashboard.php - Aggregated Metrics & Health Endpoint
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. High-Level Collection Counters
    $kpiQuery = "
        SELECT 
            COUNT(*) AS total_games,
            SUM(CASE WHEN g.`InCollection` = 1 THEN 1 ELSE 0 END) AS owned_games,
            SUM(CASE WHEN g.`InCollection` = 1 AND g.`Played` = 0 AND g.`Won` = 0 THEN 1 ELSE 0 END) AS backlog_games,
            SUM(CASE WHEN g.`InCollection` = 1 AND g.`Played` = 1 AND g.`Won` = 0 THEN 1 ELSE 0 END) AS playing_games,
            SUM(CASE WHEN g.`Won` = 1 THEN 1 ELSE 0 END) AS won_games,
            SUM(CASE WHEN g.`BoxArt` IS NULL OR TRIM(g.`BoxArt`) = '' THEN 1 ELSE 0 END) AS missing_covers,
            SUM(CASE WHEN g.`Image` IS NULL OR TRIM(g.`Image`) = '' THEN 1 ELSE 0 END) AS missing_screens,
            SUM(CASE WHEN (g.`BoxArt` IS NOT NULL AND TRIM(g.`BoxArt`) != '') 
                     AND (g.`Image` IS NOT NULL AND TRIM(g.`Image`) != '') THEN 1 ELSE 0 END) AS fully_documented
        FROM `Games` g
    ";
    $kpi = $pdo->query($kpiQuery)->fetch();

    $totalGames = (int)$kpi['total_games'];
    $fullyDocumented = (int)$kpi['fully_documented'];
    $healthPct =$totalGames > 0 ? round(($fullyDocumented / $totalGames) * 100, 1) : 0;

    // 2. Secondary Entities Counts
    $counts = [
        'consoles'      => (int)$pdo->query("SELECT COUNT(*) FROM `Consoles`")->fetchColumn(),
        'publishers'    => (int)$pdo->query("SELECT COUNT(*) FROM `Publishers`")->fetchColumn(),
        'categories'    => (int)$pdo->query("SELECT COUNT(*) FROM `Categories`")->fetchColumn(),
        'subcategories' => (int)$pdo->query("SELECT COUNT(*) FROM `Subcategories`")->fetchColumn(),
        'languages'     => (int)$pdo->query("SELECT COUNT(*) FROM `Languages`")->fetchColumn(),
    ];

    // 3. Top Consoles by Owned Library Volume
    $topConsolesQuery = "
        SELECT c.`ID`, c.`Console`, c.`IsHandheld`, 
               COUNT(g.`ID`) AS total_titles,
               SUM(CASE WHEN g.`InCollection` = 1 THEN 1 ELSE 0 END) AS owned_titles
        FROM `Consoles` c
        INNER JOIN `Games` g ON g.`Console ID` = c.`ID`
        GROUP BY c.`ID`, c.`Console`, c.`IsHandheld`
        ORDER BY owned_titles DESC, total_titles DESC
        LIMIT 6
    ";
    $topConsoles = $pdo->query($topConsolesQuery)->fetchAll();

    // 4. Currently In-Progress Games (Now Playing)
    $nowPlayingQuery = "
        SELECT g.`ID`, g.`Game`, g.`Year`, g.`BoxArt`, c.`Console`
        FROM `Games` g
        LEFT JOIN `Consoles` c ON g.`Console ID` = c.`ID`
        WHERE g.`InCollection` = 1 AND g.`Played` = 1 AND g.`Won` = 0
        ORDER BY g.`ID` DESC
        LIMIT 4
    ";
    $nowPlaying = $pdo->query($nowPlayingQuery)->fetchAll();

    json_response([
        'kpi' => [
            'total_games'       => $totalGames,
            'owned_games'       => (int)$kpi['owned_games'],
            'backlog_games'     => (int)$kpi['backlog_games'],
            'playing_games'     => (int)$kpi['playing_games'],
            'won_games'         => (int)$kpi['won_games'],
            'missing_covers'    => (int)$kpi['missing_covers'],
            'missing_screens'   => (int)$kpi['missing_screens'],
            'fully_documented'  => $fullyDocumented,
            'health_pct'        => $healthPct,
            'completion_pct'    => (int)$kpi['owned_games'] > 0 
                ? round(((int)$kpi['won_games'] / (int)$kpi['owned_games']) * 100, 1)                  : 0         ],         'counts'       =>$counts,
        'top_consoles' => $topConsoles,
        'now_playing'  => $nowPlaying
    ]);

} catch (Exception $e) {
    error_log('[Dashboard API Error] ' . $e->getMessage());
    json_error('Failed to load dashboard metrics.', 500);
}