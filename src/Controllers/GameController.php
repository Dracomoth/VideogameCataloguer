<?php
/**
 * src/Controllers/GameController.php
 * Controller managing game catalog records, taxonomy relations, and visual media assets.
 */

declare(strict_types=1);

namespace Vault\Controllers;

use Vault\Auth\Auth;
use Vault\Repositories\GameRepository;
use Vault\Repositories\ConsoleRepository;
use Vault\Repositories\CategoryRepository;
use Vault\Repositories\SubcategoryRepository;
use Vault\Repositories\PublisherRepository;
use Vault\Repositories\LanguageRepository;
use Vault\Services\Response;
use Vault\Services\View;
use Vault\Services\GeminiService;
use Throwable;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

final class GameController
{
    private GameRepository $repo;
    private ConsoleRepository $consoleRepo;
    private CategoryRepository $categoryRepo;
    private SubcategoryRepository $subcategoryRepo;
    private PublisherRepository $publisherRepo;
    private LanguageRepository $languageRepo;

    public function __construct()
    {
        $this->repo = new GameRepository();
        $this->consoleRepo = new ConsoleRepository();
        $this->categoryRepo = new CategoryRepository();
        $this->subcategoryRepo = new SubcategoryRepository();
        $this->publisherRepo = new PublisherRepository();
        $this->languageRepo = new LanguageRepository();
    }

    /**
     * Displays the Games Cataloguer & Asset Hub workbench view.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function index(array $request): void
    {
        Auth::requireAccess('games', 'read');

        $games         = $this->repo->getAll();
        $consoles      = $this->consoleRepo->getPlayableConsoles();
        $categories    = $this->categoryRepo->getAll();
        $subcategories = $this->subcategoryRepo->getAll();
        $publishers    = $this->publisherRepo->getAll();
        $languages     = $this->languageRepo->getAll();
        $canWrite      = Auth::canWrite('games');

        $flashMessage = $_SESSION['flash_message'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_message'], $_SESSION['flash_error']);

        $viewData = [
            'pageTitle'     => 'Games Cataloguer',
            'activeNav'     => 'games',
            'games'         => $games,
            'consoles'      => $consoles,
            'categories'    => $categories,
            'subcategories' => $subcategories,
            'publishers'    => $publishers,
            'languages'     => $languages,
            'canWrite'      => $canWrite,
            'flashMessage'  => $flashMessage,
            'flashError'    => $flashError,
        ];

        $html = View::render('games', $viewData, 'layout');
        Response::html($html);
    }

    /**
     * Supplies JSON dataset for the <data-grid> component and asynchronous search.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiList(array $request): void
    {
        Auth::requireAccess('games', 'read');

        $games         = $this->repo->getAll();
        $consoles      = $this->consoleRepo->getPlayableConsoles();
        $categories    = $this->categoryRepo->getAll();
        $subcategories = $this->subcategoryRepo->getAll();
        $publishers    = $this->publisherRepo->getAll();
        $languages     = $this->languageRepo->getAll();

        Response::json([
            'games'         => $games,
            'consoles'      => $consoles,
            'categories'    => $categories,
            'subcategories' => $subcategories,
            'publishers'    => $publishers,
            'languages'     => $languages,
            'can_write'     => Auth::canWrite('games'),
        ]);
    }

    /**
     * Supplies quick collection telemetry numbers for real-time header sync.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function apiTelemetry(array $request): void
    {
        $telemetry = $this->repo->getTelemetry();
        Response::json($telemetry);
    }

    /**
     * Creates a new game record with optional visual assets.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function create(array $request): void
    {
        Auth::requireAccess('games', 'write');

        $body  = $request['body'] ?? [];
        $files = $request['files'] ?? [];
        $title = trim((string)($body['title'] ?? ($body['game'] ?? '')));

        try {
            $newId = $this->repo->create($body, $files);
            $msg = "Game '{$title}' created successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $newId,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/games');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/games');
        }
    }

    /**
     * Updates an existing game record and reconciles its visual assets.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function update(array $request): void
    {
        Auth::requireAccess('games', 'write');

        $body  = $request['body'] ?? [];
        $files = $request['files'] ?? [];
        $id    = (int)($request['params']['id'] ?? ($body['id'] ?? 0));
        $title = trim((string)($body['title'] ?? ($body['game'] ?? '')));

        try {
            $this->repo->update($id, $body, $files);
            $msg = "Game #{$id} updated successfully.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/games');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/games');
        }
    }

    /**
     * Deletes a game record and removes its associated visual assets from disk.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function delete(array $request): void
    {
        Auth::requireAccess('games', 'write');

        $id = (int)($request['params']['id'] ?? ($request['body']['id'] ?? 0));

        try {
            $this->repo->delete($id);
            $msg = "Game #{$id} and its associated images were deleted.";

            if ($this->wantsJson($request)) {
                Response::json([
                    'id'      => $id,
                    'message' => $msg,
                ]);
            }

            $_SESSION['flash_message'] = $msg;
            Response::redirect('/games');
        } catch (Throwable $e) {
            if ($this->wantsJson($request)) {
                Response::error($e->getMessage(), 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            Response::redirect('/games');
        }
    }

    /**
     * AI-powered auto-fill endpoint for game Tags and Personal Notes / Comments.
     *
     * @param array<string, mixed> $request
     * @return never
     */
    public function autofillMetadata(array $request): void
    {
        Auth::requireAccess('games', 'write');

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');

        $body  = $request['body'] ?? [];
        $title = trim((string)($body['title'] ?? ''));

        if ($title === '') {
            Response::error('Game title is required to auto-fill metadata.', 422);
        }

        // Resolve console name if console ID provided
        $consoleName = trim((string)($body['console'] ?? ''));
        $consoleId   = (int)($body['console_id'] ?? 0);
        if ($consoleName === '' && $consoleId > 0) {
            $console = $this->consoleRepo->getById($consoleId);
            if ($console !== null) {
                $consoleName = (string)($console['name'] ?? '');
            }
        }

        // Resolve category name if category ID provided
        $categoryName = trim((string)($body['category'] ?? ''));
        $categoryId   = (int)($body['category_id'] ?? 0);
        if ($categoryName === '' && $categoryId > 0) {
            $cat = $this->categoryRepo->getById($categoryId);
            if ($cat !== null) {
                $categoryName = (string)($cat['name'] ?? '');
            }
        }

        // Resolve subcategory name if subcategory ID provided
        $subcategoryName = trim((string)($body['subcategory'] ?? ''));
        $subcategoryId   = (int)($body['subcategory_id'] ?? 0);
        if ($subcategoryName === '' && $subcategoryId > 0) {
            $subcat = $this->subcategoryRepo->getById($subcategoryId);
            if ($subcat !== null) {
                $subcategoryName = (string)($subcat['name'] ?? '');
            }
        }

        // Resolve publisher name if publisher ID provided
        $publisherName = trim((string)($body['publisher'] ?? ''));
        $publisherId   = (int)($body['publisher_id'] ?? 0);
        if ($publisherName === '' && $publisherId > 0) {
            $pub = $this->publisherRepo->getById($publisherId);
            if ($pub !== null) {
                $publisherName = (string)($pub['name'] ?? '');
            }
        }

        $year = trim((string)($body['year'] ?? ($body['release_year'] ?? '')));

        try {
            $gemini = new GeminiService();
            $result = $gemini->generateGameMetadata([
                'title'       => $title,
                'console'     => $consoleName,
                'category'    => $categoryName,
                'subcategory' => $subcategoryName,
                'publisher'   => $publisherName,
                'year'        => $year,
            ]);

            Response::json([
                'tags'       => $result['tags'] ?? '',
                'comments'   => $result['comments'] ?? '',
                'model_used' => $gemini->getLastUsedModel(),
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 422);
        }
    }

    /**
     * Determines whether the incoming request expects a JSON response.
     *
     * @param array<string, mixed> $request
     * @return bool
     */
    private function wantsJson(array $request): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}
