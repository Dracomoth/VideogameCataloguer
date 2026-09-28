<?php
/**
 * src/Controllers/DashboardController.php
 * Controller handling the main workbench dashboard and telemetry metrics.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Repositories\DashboardRepository;
use Vault\Services\Response;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class DashboardController
{
    private DashboardRepository $repo;

    public function __construct()
    {
        $this->repo = new DashboardRepository();
    }

    /**
     * Renders the primary dashboard workbench HTML view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        $kpi         = $this->repo->getKpiMetrics();
        $counts      = $this->repo->getEntityCounts();
        $topConsoles = $this->repo->getTopConsoles(6);
        $nowPlaying  = $this->repo->getCurrentlyPlaying(4);

        $viewData = [
            'pageTitle'   => 'Command Center',
            'activeNav'   => 'dashboard',
            'stats'       => [
                'total_games'    => $kpi['total_games'],
                'owned_games'    => $kpi['owned_games'],
                'total_consoles' => $counts['consoles'],
            ],
            'kpi'         => $kpi,
            'counts'      => $counts,
            'topConsoles' => $topConsoles,
            'nowPlaying'  => $nowPlaying,
        ];

        $html = View::render('dashboard.view', $viewData, 'layout');
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
        $kpi         = $this->repo->getKpiMetrics();
        $counts      = $this->repo->getEntityCounts();
        $topConsoles = $this->repo->getTopConsoles(6);
        $nowPlaying  = $this->repo->getCurrentlyPlaying(4);

        Response::json([
            'kpi'          => $kpi,
            'counts'       => $counts,
            'top_consoles' => $topConsoles,
            'now_playing'  => $nowPlaying,
        ]);
    }
}