<?php
/**
 * src/Controllers/PlayerHubController.php
 * Controller orchestrating Player Hub workbench, game selection algorithms,
 * real-time filter updates, and matching title galleries.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\PlayerHubRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class PlayerHubController
{
    private PlayerHubRepository $repo;

    public function __construct()
    {
        $this->repo = new PlayerHubRepository();
    }

    /**
     * Renders the Player Hub screen.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('collection', 'read');

        $taxonomies = $this->repo->getFilterTaxonomies();

        // Support direct linking from Dashboard or Consoles screen via query parameter
        $initialPlatformId = (int)($_GET['console_id'] ?? ($_GET['platform_id'] ?? 0));
        $initialCategoryId = (int)($_GET['category_id'] ?? 0);
        $initialSubcategoryId = (int)($_GET['subcategory_id'] ?? 0);
        $initialSearch = trim((string)($_GET['q'] ?? ''));

        $initialFilters = [
            'platform_id'    => $initialPlatformId,
            'category_id'    => $initialCategoryId,
            'subcategory_id' => $initialSubcategoryId,
            'q'              => $initialSearch,
        ];

        $initialCount = $this->repo->countMatching($initialFilters);
        $initialGame = $this->repo->pickRandom($initialFilters);

        $viewData = [
            'pageTitle'          => 'Player Hub',
            'activeNav'          => 'collection',
            'taxonomies'         => $taxonomies,
            'initialFilters'     => $initialFilters,
            'initialCount'       => $initialCount,
            'initialGame'        => $initialGame,
            'initialPlatformId'  => $initialPlatformId,
        ];

        $html = View::render('player_hub', $viewData, 'layout');
        Response::html($html);
    }

    /**
     * API returning the total count of games meeting current search criteria.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiCount(array $request): void
    {
        Auth::requireAccess('collection', 'read');

        $filters = $this->extractFilters($request);
        $count = $this->repo->countMatching($filters);

        Response::json([
            'count' => $count,
        ]);
    }

    /**
     * API picking a random game meeting criteria and returning updated count.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiPick(array $request): void
    {
        Auth::requireAccess('collection', 'read');

        $filters = $this->extractFilters($request);
        $excludeId = !empty($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;

        $game = $this->repo->pickRandom($filters, $excludeId);
        $count = $this->repo->countMatching($filters);

        Response::json([
            'game'  => $game,
            'count' => $count,
        ]);
    }

    /**
     * API retrieving a specific game by ID to spotlight in the Game Card.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiGetGame(array $request): void
    {
        Auth::requireAccess('collection', 'read');

        $id = (int)($request['params']['id'] ?? ($request['id'] ?? ($_GET['id'] ?? 0)));
        if ($id <= 0) {
            Response::error('Invalid game ID provided.', 400);
        }

        $game = $this->repo->getGameById($id);
        if (!$game) {
            Response::error('Game not found.', 404);
        }

        Response::json([
            'game' => $game,
        ]);
    }

    /**
     * API returning the full list of matching games for the Others Section gallery grid.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiList(array $request): void
    {
        Auth::requireAccess('collection', 'read');

        $filters = $this->extractFilters($request);
        $games = $this->repo->getMatchingGames($filters);

        Response::json([
            'games' => $games,
            'count' => count($games),
        ]);
    }

    /**
     * Normalizes filter parameters from query parameters or request body.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function extractFilters(array $request): array
    {
        $platform = $_GET['platform_id'] ?? ($_GET['console_id'] ?? ($request['platform_id'] ?? null));
        $category = $_GET['category_id'] ?? ($request['category_id'] ?? null);
        $subcategory = $_GET['subcategory_id'] ?? ($request['subcategory_id'] ?? null);
        $search = $_GET['q'] ?? ($_GET['query'] ?? ($request['q'] ?? ''));

        return [
            'platform_id'    => !empty($platform) ? (int)$platform : 0,
            'category_id'    => !empty($category) ? (int)$category : 0,
            'subcategory_id' => !empty($subcategory) ? (int)$subcategory : 0,
            'q'              => trim((string)$search),
        ];
    }
}
