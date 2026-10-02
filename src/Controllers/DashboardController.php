<?php
/**
 * src/Controllers/DashboardController.php
 * Controller handling the main workbench dashboard, general telemetry,
 * and player-specific gameplay statistics.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\DashboardRepository;
use Vault\Repositories\PlayerGameRepository;
use Vault\Services\Response;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class DashboardController
{
    private DashboardRepository $repo;
    private PlayerGameRepository $playerGameRepo;

    public function __construct()
    {
        $this->repo = new DashboardRepository();
        $this->playerGameRepo = new PlayerGameRepository();
    }

    /**
     * Renders the primary dashboard workbench HTML view with General and Player stats.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('dashboard', 'read');

        $kpi             = $this->repo->getKpiMetrics();
        $counts          = $this->repo->getEntityCounts();
        $topConsoles     = $this->repo->getTopConsoles(6);
        $topDownloads    = $this->repo->getTopDownloads(10);
        $playedConsoles  = $this->repo->getTopPlayedConsoles(5);
        $latestAdditions = $this->repo->getLatestAdditions(10);

        $playerId = (int)(Auth::id() ?? 1);
        $playerData = $this->repo->getPlayerDashboardData($playerId, (int)($kpi['total_games'] ?? 0));

        $viewData = [
            'pageTitle'       => 'Command Center',
            'activeNav'       => 'dashboard',
            'stats'           => [
                'total_games'    => $kpi['total_games'],
                'owned_games'    => $kpi['owned_games'],
                'total_consoles' => $counts['consoles'],
            ],
            'kpi'             => $kpi,
            'counts'          => $counts,
            'topConsoles'     => $topConsoles,
            'topDownloads'    => $topDownloads,
            'playedConsoles'  => $playedConsoles,
            'latestAdditions' => $latestAdditions,
            'playerData'      => $playerData,
        ];

        $html = View::render('dashboard', $viewData, 'layout');
        Response::html($html);
    }

    /**
     * Supplies JSON telemetry figures for asynchronous dashboard updates.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function api(array $request): void
    {
        Auth::requireAccess('dashboard', 'read');

        $kpi             = $this->repo->getKpiMetrics();
        $counts          = $this->repo->getEntityCounts();
        $topConsoles     = $this->repo->getTopConsoles(6);
        $topDownloads    = $this->repo->getTopDownloads(10);
        $playedConsoles  = $this->repo->getTopPlayedConsoles(5);
        $latestAdditions = $this->repo->getLatestAdditions(10);

        $playerId = (int)(Auth::id() ?? 1);
        $playerData = $this->repo->getPlayerDashboardData($playerId, (int)($kpi['total_games'] ?? 0));

        Response::json([
            'kpi'              => $kpi,
            'counts'           => $counts,
            'top_consoles'     => $topConsoles,
            'top_downloads'    => $topDownloads,
            'played_consoles'  => $playedConsoles,
            'latest_additions' => $latestAdditions,
            'player_data'      => $playerData,
        ]);
    }

    /**
     * Asynchronously updates a game's played/won status for the active player.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function updateGameStatus(array $request): void
    {
        Auth::requireAccess('dashboard', 'read');

        $body = json_decode((string)file_get_contents('php://input'), true) ?? [];
        $gameId   = (int)($body['game_id'] ?? 0);
        $isPlayed = !empty($body['is_played']);
        $isWon    = !empty($body['is_won']);

        if ($gameId <= 0) {
            Response::json(['success' => false, 'error' => 'Invalid game ID.'], 400);
        }

        $playerId = (int)(Auth::id() ?? 1);

        // Update player_games
        $record = $this->playerGameRepo->updateGameStatus($playerId, $gameId, $isPlayed, $isWon);

        // Fetch freshly calculated player data and general stats
        $kpi = $this->repo->getKpiMetrics();
        $playerData = $this->repo->getPlayerDashboardData($playerId, (int)($kpi['total_games'] ?? 0));
        $playedConsoles = $this->repo->getTopPlayedConsoles(5);

        Response::json([
            'success'         => true,
            'record'          => $record,
            'player_data'     => $playerData,
            'played_consoles' => $playedConsoles,
        ]);
    }
}